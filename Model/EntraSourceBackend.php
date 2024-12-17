<?php

App::uses('EntraSource', 'EntraSource.Model');
App::uses('HttpSocket', 'Network/Http');
App::uses('OrgIdentitySourceBackend', 'Model');
App::uses('Server', 'Model');

class EntraSourceBackend extends OrgIdentitySourceBackend {
  public $name = "EntraSourceBackend";

  // Cache the access token server configuration.
  protected $accessTokenServer = null;

  // Cache the API server configuration.
  protected $apiServer = null;

  // Cache the instance ID, used in logging.
  protected $activeId = null;

  /**
   * Add a source record.
   *
   * @since  COmanage Registry v4.4.0
   * @param  String $graphId Graph API ID for user
   * @param  Integer $entraSourceId EntraSource ID
   * @param  Integer $entraSourceGroupId EntraSourceGroup ID
   * @return Null
   */

  protected function addSourceRecord($graphId, $entraSourceId, $entraSourceGroupId) {
    $args = array();
    $args['conditions']['EntraSourceRecord.graph_id'] = $graphId;
    $args['conditions']['EntraSourceRecord.entra_source_id'] = $entraSourceId;
    $args['contains'] = false;

    $EntraSource = new EntraSource();
    $sourceRecord = $EntraSource->EntraSourceRecord->find('first', $args);

    if(empty($sourceRecord)) {
      $data = array();
      $data['EntraSourceRecord']['entra_source_id'] = $entraSourceId;
      $data['EntraSourceRecord']['graph_id'] = $graphId;

      $EntraSource->EntraSourceRecord->clear();
      $EntraSource->EntraSourceRecord->save($data);
      $recordId = $EntraSource->EntraSourceRecord->id;

      $this->log("Saved source record " . print_r($data, true));
    } else {
      $recordId = $sourceRecord['EntraSourceRecord']['id'];
    }

    // Record the Entra group membership if necessary.
    $args = array();
    $args['conditions']['EntraSourceGroupMembership.entra_source_group_id'] = $entraSourceGroupId;
    $args['conditions']['EntraSourceGroupMembership.entra_source_record_id'] = $recordId;
    $args['contains'] = false;

    $membership = $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->find('first', $args);

    if(empty($membership)) {
      $data = array();
      $data['EntraSourceGroupMembership']['entra_source_group_id'] = $entraSourceGroupId;
      $data['EntraSourceGroupMembership']['entra_source_record_id'] = $recordId;

      $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->clear();
      $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->save($data);

      $this->log("Saved group membership " . print_r($data, true));
    }
  }

  /**
   * Establish a connection to the API server.
   *
   * @since  COmanage Registry v4.4.0
   * @return Boolean true on success
   * @throws InvalidArgumentException
   */

  protected function apiConnect() {
    $cfg = $this->getConfig();

    $Server = new Server();

    // Cache the access token server details.
    $id = $cfg['access_token_server_id'];
    $args = array();
    $args['conditions']['Oauth2Server.id'] = $id;
    $args['contain'] = false;

    $this->accessTokenServer = $Server->Oauth2Server->find('first', $args);

    if(!$this->accessTokenServer) {
      throw new InvalidArgumentException(_txt('er.notfound', array(_txt('ct.servers.1'), $id)));
    }

    // Cache the API server details.
    $id = $cfg['api_server_id'];
    $args = array();
    $args['conditions']['HttpServer.id'] = $id;
    $args['contain'] = false;

    $this->apiServer = $Server->HttpServer->find('first', $args);

    if(!$this->apiServer) {
      throw new InvalidArgumentException(_txt('er.notfound', array(_txt('ct.servers.1'), $id)));
    }

    $this->Http = new HttpSocket();

    return true;
  }

  /**
   * Make a request to the API server.
   *
   * @since  COmanage Registry v4.4.0
   * @param  String $urlPath  URL Path to request from API
   * @param  Array  $query    Array of query parameters
   * @param  String $action   HTTP action
   * @return Array  Decoded json message body
   * @throws RuntimeException
   */

  protected function apiRequest($urlPath, $query=array(), $action="get") {
    $EntraSource = new EntraSource();
    $atServerId = $this->accessTokenServer['Oauth2Server']['id'];

    // Will the access token expire within the next ten seconds?
    $deltat = 10;
    $expired = $EntraSource->Server->Oauth2Server->isExpired($atServerId, $deltat);

    if($expired === true || $expired === null) {
      $data = $EntraSource->Server->Oauth2Server->obtainToken($atServerId, 'client_credentials');

      // We should have received a new access token.
      if(!property_exists($data, 'access_token')) {
        $msg = _txt('er.entrasource.access_token.unable');
        $this->log($msg);
        throw new RuntimeException($msg);
      }

      // Force caching of server objects to pick up new access token.
      $this->apiConnect();
    }

    $options = array(
      'header' => array(
        'Authorization' => 'Bearer ' . $this->accessTokenServer['Oauth2Server']['access_token']
      )
    );

    $urlBase = $this->apiServer['HttpServer']['serverurl'];

    if(str_starts_with($urlPath, $urlBase)) {
      $url = $urlPath;
    } else {
      $url = $urlBase . '/' . $urlPath;
    }

    $response = $this->Http->$action($url, $query, $options);

    // TODO manage throttling. See https://learn.microsoft.com/en-us/graph/throttling

    if($response->code != 200) {
      $msg = _txt('er.entrasource.api.code', array($response->code));
      $this->log($msg);
      throw new RuntimeException($msg);
    }

    return json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Generate the set of attributes for the IdentitySource that can be used to map
   * to group memberships. The returned array should be of the form key => label,
   * where key is meaningful to the IdentitySource (eg: a number or a field name)
   * and label is the localized string to be displayed to the user. Backends should
   * only return a non-empty array if they wish to take advantage of the automatic
   * group mapping service.
   *
   * @since  COmanage Registry v4.4.0
   * @return Array As specified
   */

  public function groupableAttributes() {
    // Not currently supported.
    return array();
  }
  
  /**
   * Obtain all available records in the IdentitySource, as a list of unique keys
   * (ie: suitable for passing to retrieve()).
   *
   * @since  COmanage Registry v4.4.0
   * @return Array Array of unique keys
   * @throws DomainException If the backend does not support this type of requests
   */
  
  public function inventory() {
    $this->apiConnect();

    $cfg = $this->getConfig();

    if($cfg['use_source_groups']) {
      // If configured use group memberships to determine the inventory.
      return $this->inventoryBySourceGroups();
    } else {
      // Else find all users to determine the inventory.
      return $this->inventoryAllUsers();
    }
  }

  /**
   * Obtain all available records from the API server, as a list of unique keys
   * (ie: suitable for passing to retrieve()).
   *
   * @since  COmanage Registry v4.4.0
   * @return Array Array of unique keys
   */

  protected function inventoryAllUsers() {
    // Not currently supported.
    return array();
  }

  /**
   * Obtain all available records from the API server, as a list of unique keys
   * (ie: suitable for passing to retrieve()), using memberships in source groups
   * to determine the set of records in the inventory.
   *
   * @since  COmanage Registry v4.4.0
   * @return Array Array of unique keys
   */

  protected function inventoryBySourceGroups() {
    $cfg = $this->getConfig();

    // Find the source groups.
    $args = array();
    $args['conditions']['EntraSourceGroup.entra_source_id'] = $cfg['id'];
    $args['contain'] = false;

    $EntraSource = new EntraSource();

    $sourceGroups = $EntraSource->EntraSourceGroup->find('all', $args);

    if(empty($sourceGroups)) {
      return array();
    }

    foreach($sourceGroups as $g) {
      $this->inventoryOneSourceGroup($g);
    }

    // At this point we have looped over all the source groups and so
    // we should have an up-to-date set of EntraSourceRecords, so query
    // to find them all.

    $args = array();
    $args['fields'] = array('id', 'graph_id');
    $args['contain'] = false;

    $inventory = $EntraSource->EntraSourceRecord->find('list', $args);

    return array_values($inventory);
  }

  /**
   * Obtain records from the API server using memberships in one source group
   * and create (or remove) EntraSourceRecord objects and EntraSourceGroupMembership
   * objects.
   *
   * @since  COmanage Registry v4.4.0
   * @param  Array $g EntraSourceGroup object
   * @return Null
   */

  protected function inventoryOneSourceGroup($g) {
    $EntraSource = new EntraSource();

    if(empty($g['EntraSourceGroup']['graph_id'])) {
      // If the graph_id for the Entra group is not recorded yet
      // exchange the mail_nickname for the graph_id.
      $mailNickname = $g['EntraSourceGroup']['mail_nickname'];
      $apiId = $this->sourceGroupApiIdByNickname($mailNickname);

      // Update the source group with the api ID.
      $EntraSource->EntraSourceGroup->clear();
      $EntraSource->EntraSourceGroup->id = $g['EntraSourceGroup']['id'];
      $EntraSource->EntraSourceGroup->saveField('graph_id', $apiId);
    } else {
      $apiId = $g['EntraSourceGroup']['graph_id'];
    }

    // We use the paging functionaity of the groups/delta route and
    // loop until there are no more memberships to process.
    $done = false;

    while(!$done) {
      $query = array();

      // Use the delta link if available to query for the next set of
      // changes, or the next link if there are still pages to read.
      // Otherwise start a new delta query.
      //
      // See https://learn.microsoft.com/en-us/graph/api/group-delta?view=graph-rest-1.0&tabs=http

      if(!empty($g['EntraSourceGroup']['delta_next_link']) &&
         // Cannot use a delta link that is older than 30 days.
         (time() - strtotime($g['EntraSourceGroup']['modified']) < 2592000 )) {
        $urlPath = $g['EntraSourceGroup']['delta_next_link'];
      } elseif(!empty($g['EntraSourceGroup']['graph_next_link'])) {
        $urlPath = $g['EntraSourceGroup']['graph_next_link'];
      } else {
        $urlPath = "groups/delta";
        $query = array(
          '$select' => 'members',
          '$filter' => "id eq '$apiId'"
        );
      }

      $body = $this->apiRequest($urlPath, $query);
      $this->log("Route groups/delta returned body " . print_r($body, true));

      if(!empty($body['value'])) {
        if(!empty($body['value'][0]['members@delta'])) {
          foreach($body['value'][0]['members@delta'] as $m) {
            if(array_key_exists('@removed', $m)) {
              // TODO handle membership being removed
            } else {
              $this->addSourceRecord($m['id'], $g['EntraSourceGroup']['entra_source_id'], $g['EntraSourceGroup']['id']);
            }
          }
        }
      } else {
        $done = true;
      }

      // Update nextLink or deltaLink
      if(!empty($body['@odata.nextLink'])) {
        $EntraSource->EntraSourceGroup->clear();
        $EntraSource->EntraSourceGroup->id = $g['EntraSourceGroup']['id'];
        $EntraSource->EntraSourceGroup->saveField('graph_next_link', $body['@odata.nextLink']);

        $g['EntraSourceGroup']['graph_next_link'] = $body['@odata.nextLink'];
      } elseif(!empty($body['@odata.deltaLink'])) {
        $EntraSource->EntraSourceGroup->clear();
        $EntraSource->EntraSourceGroup->id = $g['EntraSourceGroup']['id'];
        $EntraSource->EntraSourceGroup->saveField('delta_next_link', $body['@odata.deltaLink']);
        $EntraSource->EntraSourceGroup->saveField('graph_next_link', null);

        $g['EntraSourceGroup']['delta_next_link'] = $body['@odata.deltaLink'];
        $g['EntraSourceGroup']['graph_next_link'] = null;
      }
    }
  }

  /**
   * Log output from this backend.
   *
   * @since  COmanage Registry v4.4.0
   * @return bool Success of log write
   */

  public function log($msg, $type = LOG_ERR, $scope=null) {
    if(empty($this->activeId)) {
      $cfg = $this->getConfig();
      $this->activeId = $cfg['id'];
    }

    $prefix = "EntraSourceBackend ID " . $this->activeId . ": ";

    return parent::log($prefix . $msg, $type, $scope);
  }

  /**
   * Use a source group mail nickname to find the Graph API ID
   * for the source group.
   *
   * @since  COmanage Registry 4.4.0
   * @param  String $mailNickname mail nickname for the source group
   * @return String Graph API ID for the source group
   */

  protected function sourceGroupApiIdByNickname($mailNickname) {
    $urlPath = "groups/";
    $query = array(
      '$filter' => "mailNickname eq '$mailNickname'"
    );

    $body = $this->apiRequest($urlPath, $query);
    $apiId = $body['value'][0]['id'];

    return $apiId;
  }
  
  /**
   * Convert a raw result, as from eg retrieve(), into an array of attributes that
   * can be used for group mapping.
   *
   * @since  COmanage Registry v2.0.0
   * @param  String $raw Raw record, as obtained via retrieve()
   * @return Array Array, where keys are attribute names and values are lists (arrays) of arrays with these keys: value, valid_from, valid_through
   */
  
  public function resultToGroups($raw) {
    // Not currently supported.

    return array();
  }

  /**
   * Convert the Graph API record for a user to OrgIdentity format.
   *
   * @since  COmanage Registry v4.4.0
   * @param  String $result Graph API record string in JSON format
   * @param  Array $extensions array of EntraSourceExtensionProperty objects
   * @return Array in OrgIdentity format
   */

  protected function resultToOrgIdentity($result, $extensions = array()) {
    // Decode the JSON string to an array.
    $record = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

    $orgdata = array();
    $orgdata['OrgIdentity'] = array();

    // TODO affiliation should be configurable.
    $orgdata['OrgIdentity']['affiliation'] = AffiliationEnum::Member;

    $orgdata['Name'] = array();

    if(!empty($record['givenName'])) {
      $orgdata['Name'][0]['given'] = $record['givenName'];
    }

    if(!empty($record['surname'])) {
      $orgdata['Name'][0]['family'] = $record['surname'];
    }

    if(!empty($orgdata['Name'][0])) {
      // TODO Name type should be configurable.
      $orgdata['Name'][0]['type'] = NameEnum::Official;
      $orgdata['Name'][0]['primary_name'] = true;
    }

    if(!empty($record['mail'])) {
      $orgdata['EmailAddress'][0]['mail'] = $record['mail'];
      // TODO EmailAddress type should be configurable.
      $orgdata['EmailAddress'][0]['type'] = EmailAddressEnum::Official;
      $orgdata['EmailAddress'][0]['verified'] = true;
    }

    $orgdata['Identifier'][0] = array(
      'identifier' => $record['id'],
      'type' => IdentifierEnum::SORID,
      'status' => SuspendableStatusEnum::Active
    );

    $orgdata['Identifier'][1] = array(
      'identifier' => $record['userPrincipalName'],
      // TODO Identifier type should be configurable.
      'type' => 'upn',
      'status' => SuspendableStatusEnum::Active
    );

    foreach($extensions as $e) {
      $property = $e['EntraSourceExtensionProperty']['property'];
      if(!empty($record[$property])) {
        if(is_array($record[$property])) {
          $identifier = $record[$property][0];
        } else {
          $identifier = $record[$property];
        }
        $orgdata['Identifier'][] = array(
          'identifier' => $identifier,
          'type' => $e['EntraSourceExtensionProperty']['identifier_type'],
          'status' => SuspendableStatusEnum::Active
        );
      }
    }

    return $orgdata;
  }

  /**
   * Retrieve a single record from the IdentitySource. The return array consists
   * of two entries: 'raw', a string containing the raw record as returned by the
   * IdentitySource backend, and 'orgidentity', the data in OrgIdentity format.
   *
   * @since  COmanage Registry v4.1.0
   * @param  String $id Unique key to identify record
   * @return Array As specified
   * @throws InvalidArgumentException if not found
   * @throws OverflowException if more than one match
   * @throws RuntimeException on backend specific errors
   */
  
  public function retrieve($id) {
    $this->apiConnect();
    $cfg = $this->getConfig();

    $args = array();
    $args['conditions']['EntraSourceRecord.entra_source_id'] = $cfg['id'];
    $args['conditions']['EntraSourceRecord.graph_id'] = $id;
    $args['contain'] = false;

    $EntraSource = new EntraSource();

    $record = $EntraSource->EntraSourceRecord->find('first', $args);

    if(empty($record)) {
      throw new InvalidArgumentException(_txt('er.notfound', array(_txt('fd.sorid'), $id)));
    }

    $this->log("Retrieved record " . print_r($record, true));

    // Pull extension properties if any.
    $args = array();
    $args['conditions']['EntraSourceExtensionProperty.entra_source_id'] = $cfg['id'];
    $args['contain'] = false;

    $extensions = $EntraSource->EntraSourceExtensionProperty->find('all', $args);

    // We use the paging functionaity of the users/delta route and
    // loop until there are no more updates to process.
    $done = false;

    while(!$done) {
      $query = array();

      // Use the delta link if available to query for the next set of
      // changes, or the next link if there are still pages to read.
      // Otherwise start a new delta query.
      //
      // See https://learn.microsoft.com/en-us/graph/api/user-delta?view=graph-rest-1.0&tabs=http

      if(!empty($record['EntraSourceRecord']['delta_next_link'])) {
        $urlPath = $record['EntraSourceRecord']['delta_next_link'];
      } elseif(!empty($record['EntraSourceRecord']['graph_next_link'])) {
        $urlPath = $record['EntraSourceRecord']['graph_next_link'];
      } else {
        $urlPath = "users/delta";

        // Use the select query parameter to request the specific set of properties
        // to be returned.
        $select = array('id', 'givenName', 'surname', 'mail', 'userPrincipalName');

        // Include any extension properties also.
        foreach($extensions as $e) {
          $select[] = $e['EntraSourceExtensionProperty']['property'];
        }

        $query = array(
          '$select' => implode(',', $select),
          '$filter' => "id eq '$id'"
        );
      }

      $body = $this->apiRequest($urlPath, $query);
      $this->log("Route users/deleta returned body " . print_r($body, true));

      if(!empty($body['value'][0])) {
        $EntraSource->EntraSourceRecord->clear();
        $EntraSource->EntraSourceRecord->id = $record['EntraSourceRecord']['id'];

        $sourceRecord = json_encode($body['value'][0]);
        $EntraSource->EntraSourceRecord->saveField('source_record', $sourceRecord);
        $record['EntraSourceRecord']['source_record'] = $sourceRecord;
      } elseif(!empty($body['@odata.deltaLink'])) {
        $done = true;
      }

      // Update nextLink or deltaLink
      if(!empty($body['@odata.nextLink'])) {
        $EntraSource->EntraSourceRecord->clear();
        $EntraSource->EntraSourceRecord->id = $record['EntraSourceRecord']['id'];
        $ret = $EntraSource->EntraSourceRecord->saveField('graph_next_link', $body['@odata.nextLink']);

        $record['EntraSourceRecord']['graph_next_link'] = $body['@odata.nextLink'];
      } elseif(!empty($body['@odata.deltaLink'])) {
        $EntraSource->EntraSourceRecord->clear();
        $EntraSource->EntraSourceRecord->id = $record['EntraSourceRecord']['id'];
        $ret = $EntraSource->EntraSourceRecord->saveField('delta_next_link', $body['@odata.deltaLink']);
        $ret = $EntraSource->EntraSourceRecord->saveField('graph_next_link', null);

        $record['EntraSourceRecord']['delta_next_link'] = $body['@odata.deltaLink'];
        $record['EntraSourceRecord']['graph_next_link'] = null;
      }
    }

    $raw = $record['EntraSourceRecord']['source_record'];
    $orgId = $this->resultToOrgIdentity($raw, $extensions);

    return array(
      'raw' => $raw,
      'orgidentity' => $orgId
    );
  }

  /**
   * Perform a search against the IdentitySource. The returned array should be of
   * the form uniqueId => attributes, where uniqueId is a persistent identifier
   * to obtain the same record and attributes represent an OrgIdentity, including
   * related models.
   *
   * @since  COmanage Registry v2.0.0
   * @param  Array $attributes Array in key/value format, where key is the same as returned by searchableAttributes()
   * @return Array Array of search results, as specified
   */
  
  public function search($attributes) {
    // TODO
  }
  
  /**
   * Generate the set of searchable attributes for the IdentitySource.
   * The returned array should be of the form key => label, where key is meaningful
   * to the IdentitySource (eg: a number or a field name) and label is the localized
   * string to be displayed to the user.
   *
   * @since  COmanage Registry v2.0.0
   * @return Array As specified
   */
  
  public function searchableAttributes() {
    return array();
  }
}
