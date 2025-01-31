<?php

App::uses('EntraSource', 'EntraSource.Model');
App::uses('HttpSocket', 'Network/Http');
App::uses('OrgIdentitySourceBackend', 'Model');
App::uses('Server', 'Model');
App::uses('UnixClusterGroup', 'UnixCluster.Model');

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
   * @return Integer EntraSourceRecord ID
   */

  protected function addSourceRecord($graphId, $entraSourceId) {
    $args = array();
    $args['conditions']['EntraSourceRecord.graph_id'] = $graphId;
    $args['conditions']['EntraSourceRecord.entra_source_id'] = $entraSourceId;
    $args['contain'] = false;

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

    return $recordId;
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
   * @param  String $action   HTTP action
   * @param  Array  $query    Array of query parameters
   * @param  Array  $headers  Array of additional headers. Do not include the colon.
   * @return Array  Decoded json message body
   * @throws RuntimeException
   */

  protected function apiRequest($urlPath, $action="get", $query=array(), $headers=array()) {
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

    foreach($headers as $h => $v) {
      $options['header'][$h] = $v;
    }

    $urlBase = $this->apiServer['HttpServer']['serverurl'];

    if(str_starts_with($urlPath, $urlBase)) {
      $url = $urlPath;
    } else {
      $url = $urlBase . '/' . $urlPath;
    }

    $throttled = false;

    do {
      $response = $this->Http->$action($url, $query, $options);

      // See https://learn.microsoft.com/en-us/graph/throttling
      if($response->code == 429) {
        $throttled = true;
        $this->log(_txt('er.entrasource.api.throttled'));

        try {
          $sleep = (int) $response->getHeader('Retry-After');
          $this->log(_txt('er.entrasource.api.throttled.retry', array($sleep)));
        } catch (Exception $e) {
          $sleep = 5;
          $this->log(_txt('er.entrasource.api.throttled.retry.error'));
        }

        $this->log(_txt('er.entrasource.api.throttled.retry.sleep', array($sleep)));
        sleep($sleep);
        $this->log(_txt('er.entrasource.api.throttled.retry.sleep.awake', array($sleep)));
      } else {
        $throttled = false;
      }

      if($response->code != 200 && !$throttled) {
        $msg = _txt('er.entrasource.api.code', array($response->code));
        $this->log($msg);
        $this->log($response->body);
        throw new RuntimeException($msg);
      }

    } while ($throttled == true);

    return json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * Retrieve extension properties.
   *
   * @since  COmanage Registry v4.4.0
   * @return Array Array of EntraSourceExtensionProperty objects
   */

  protected function getExtensionProperties() {
    $EntraSource = new EntraSource();

    $cfg = $this->getConfig();

    $args = array();
    $args['conditions']['EntraSourceExtensionProperty.entra_source_id'] = $cfg['id'];
    $args['contain'] = false;

    $extensions = $EntraSource->EntraSourceExtensionProperty->find('all', $args);

    return $extensions;
  }

  /**
   * Get the list of source group IDs the user is in filtered by the
   * synchronized list of source groups. The list of source groups should
   * have already been synchronized.
   *
   * @since  COmanage Registry v4.4.0
   * @param  String $graphId Graph ID for the source record for the user
   * @return Array list of arrays, each indexed by id, mailNickName, and entraSourceGroupId
   */

  protected function getFilteredSourceGroupsForSourceRecord($graphId) {
    $cfg = $this->getConfig();

    $EntraSource = new EntraSource();

    $transitiveMemberOf = array();
    $done = false;
    $nextLink = null;

    while(!$done) {
      // Query for the list of transitive group memberships.
      //
      // See https://learn.microsoft.com/en-us/graph/api/user-list-transitivememberof?view=graph-rest-1.0&tabs=http
      if(!empty($nextLink)) {
        $urlPath = $nextLink;
        $query = array();
      } else {
        $urlPath = "users/$graphId/transitiveMemberOf";
        $query = array(
          '$select' => 'id,mailNickname',
          '$top' => '999'
        );
      }

      $body = $this->apiRequest($urlPath, query: $query);  

      if(!empty($body['@odata.nextLink'])) {
        $nextLink = $body['@odata.nextLink'];
      } else {
        $nextLink = null;
        $done = true;
      }

      if(!empty($body['value'])) {
        foreach($body['value'] as $g) {
          // We only want groups and not other types of directory objects.
          if($g['@odata.type'] == '#microsoft.graph.group') {
            $args = array();
            $args['conditions']['EntraSourceGroup.graph_id'] = $g['id'];
            $args['conditions']['EntraSourceGroup.entra_source_id'] = $cfg['id'];
            $args['contain'] = false;

            $sg = $EntraSource->EntraSourceGroup->find('first', $args);
            if(!empty($sg)) {
              $transitiveMemberOf[] = array_merge($g, array('entraSourceGroupId' => $sg['EntraSourceGroup']['id']));
            }
          }
        }
      } else {
        $done = true;
      }
    }

    return $transitiveMemberOf;
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
    return array(
      'memberOf' => 'memberOf'
    );
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
    $cfg = $this->getConfig();

    // If we are using source groups then deliver the inventory
    // using the stored records if the cache is still valid
    // as configured.
    if($cfg['use_source_groups']) {
      if($this->inventoryCacheValid()) {
        $this->log("inventory cache is valid");
        return $this->inventoryFromCache();
      } else {
        $this->log("inventory cache is invalid");
      }
    }

    $this->log("inventory called");

    // Record the inventory start time.
    $this->recordInventoryStart();

    // Refresh the access token if necessary.
    $this->apiConnect();

    if($cfg['use_source_groups']) {
      // If configured use group memberships to determine the inventory.
      $inventory = $this->inventoryBySourceGroups();
    } else {
      // Else find all users to determine the inventory.
      $inventory = $this->inventoryAllUsers();
    }

    $this->log("inventory is returning");

    return $inventory;
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

    $EntraSource = new EntraSource();

    // Synchronize the list of source groups.
    $this->synchronizeSourceGroups();

    // Find the current set of source groups.
    $args = array();
    $args['conditions']['EntraSourceGroup.entra_source_id'] = $cfg['id'];
    $args['contain'] = false;

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

    return $this->inventoryFromCache();
  }

  /**
   * Determine if the iventory cache is valid based on time.
   *
   * @since  COmanage Registry v4.4.0
   * @return Boolean
   */

  protected function inventoryCacheValid() {
    $cfg = $this->getConfig();

    if(empty($cfg['inventory_cache_start'])) {
      return false;
    }

    $now = new DateTime();

    $lastInventoryStart = new DateTime($cfg['inventory_cache_start']);

    // Add the configured number of minutes to the last inventory start time.
    $lastInventoryStart->modify('+ ' . strval($cfg['max_inventory_cache']) . ' min');

    // Return true if the last inventory start time plus the max cache age
    // in minutes is in the future, meaning the cache is still valid.
    return ($lastInventoryStart > $now);
  }

  /**
   * Return inventory of source records using stored records.
   *
   * @since COmanage Registry v4.4.0
   * @return Array As specified for inventory() method
   */

  protected function inventoryFromCache() {
    $cfg = $this->getConfig();

    $EntraSource = new EntraSource();

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
    $this->log("inventoring source group " . $g['EntraSourceGroup']['mail_nickname']);
    $EntraSource = new EntraSource();

    // We use the paging functionality and loop until there are
    // no more transitive memberships to process.
    $nextLink = null;
    $transitiveMembers = array();

    do {

      // Use the next link if there are still pages to read.
      // Otherwise start a new query.
      //
      // See https://learn.microsoft.com/en-us/graph/api/group-list-transitivemembers?view=graph-rest-1.0&tabs=http

      if(!empty($nextLink)) {
        $urlPath = $nextLink;
        $query = array();
      } else {
        $graphId = $g['EntraSourceGroup']['graph_id'];
        $urlPath = "groups/$graphId/transitiveMembers/microsoft.graph.user";
        $query = array(
          '$select' => 'id',
          '$top' => '999'
        );
      }

      try {
        $body = $this->apiRequest($urlPath, query: $query);
      } catch (Exception $e){
        $this->log("inventoryOneSourceGroup caught exception: " . print_r($e, true));
        // Return to go onto the next source group.
        return;
      }

      if(!empty($body['value'])) {
        foreach($body['value'] as $o) {
          $transitiveMembers[] = $o['id'];
        }
      }

      if(!empty($body['@odata.nextLink'])) {
        $nextLink = $body['@odata.nextLink'];
      } else {
        $nextLink = null;
      }

    } while (!empty($nextLink));

    // Now syncrhonize the transitive memberships from Entra with
    // our set of EntraSourceGroup objects.
    $this->synchronizeTransitiveMembers($transitiveMembers, $g);
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
   * Record start time of inventory.
   *
   * @since  COmanage Registry v4.4.0
   * @return null
   */

  protected function recordInventoryStart() {
    $cfg = $this->getConfig();

    $EntraSource = new EntraSource();

    $EntraSource->id = $cfg['id'];
    $EntraSource->saveField('inventory_cache_start', date('Y-m-d H:i:s'));
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
    $rawArray = json_decode($raw, true, 512);

    $valueArray = array();

    foreach($rawArray['memberOf'] as $m) {
      $valueArray[] = array('value' => $m);
    }

    return array(
      'memberOf' => $valueArray
    );
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
    $this->log("retrieve called with id $id");

    // Refresh the access token if necessary.
    $this->apiConnect();
    $cfg = $this->getConfig();
    $entraSourceId = $cfg['id'];

    // Always do an inventory first since we will use the cache
    // if it is still valid.
    $this->inventory();

    // Pull extension properties if any.
    $extensions = $this->getExtensionProperties();

    // Query for the user record.
    $urlPath = "users/$id";

    $select = array('id', 'givenName', 'surname', 'mail', 'userPrincipalName');
    foreach($extensions as $e) {
      $select[] = $e['EntraSourceExtensionProperty']['property'];
    }

    $query = array(
      '$select' => implode(',', $select),
    );

    $body = $this->apiRequest($urlPath, query: $query);
    $rawArray = $body;

    // 

    // See if this source record is known and add if necessary.
    $sourceRecordId = $this->addSourceRecord($id, $entraSourceId);

    // Next get a filtered list of source group memberships for this record
    // and synchronize EntraSourceGroupMemberships.
    //$this->synchronizeSourceGroups();
    //$transitiveMemberOf = $this->getFilteredSourceGroupsForSourceRecord($id);
    //$this->syncMembershipsSourceRecord($sourceRecordId, $transitiveMemberOf);

    $args = array();
    $args['conditions']['EntraSourceGroupMembership.entra_source_record_id'] = $sourceRecordId;
    $args['contain'] = 'EntraSourceGroup';

    $EntraSource = new EntraSource();

    $memberships = $EntraSource->EntraSourceRecord->EntraSourceGroupMembership->find('all', $args);

    // Compute an artificial memberOf property and add it to
    // the raw record so that it can be used for managing
    // CoGroupMembers.

    $rawArray['memberOf'] = array();

    foreach($memberships as $m) {
      $rawArray['memberOf'][] = $m['EntraSourceGroup']['mail_nickname'];
    }

    // Sort the memberships so that we present the same raw
    // record when nothing has actually changed.
    sort($rawArray['memberOf']);

    $raw = json_encode($rawArray);

    // Compute the OrgIdentity from the raw record.
    $orgId = $this->resultToOrgIdentity($raw, $extensions);

    $this->log("retrieve is returning");

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
    $this->log("search called with attributes " . print_r($attributes, true));

    $mail = $attributes['mail'];

    $ret = array();

    // Refresh the access token if necessary.
    $this->apiConnect();

    // Query using the passed value for mail to find any candidate source records.
    $urlPath = "/users";

    $select = array('id', 'givenName', 'surname', 'mail', 'userPrincipalName');

    $extensions = $this->getExtensionProperties();
    foreach($extensions as $e) {
      $select[] = $e['EntraSourceExtensionProperty']['property'];
    }

    $query = array(
      '$select' => implode(',', $select),
      '$filter'  => "mail eq '$mail'",
    );

    $headers = array('ConsistencyLevel' => 'eventual');

    $body = $this->apiRequest($urlPath, query: $query, headers: $headers);

    if(!empty($body['value'][0])) {
      $candidate = $body['value'][0];
    } else {
      // No candidate found so return empty array.
      return $ret;
    }

    // If we are using groups to determine our set of user source records
    // then we need to determine if this candidate is in the set.
    $cfg = $this->getConfig();

    if($cfg['use_source_groups']) {
      // First synchronize the source groups.
      $this->synchronizeSourceGroups();

      // Next get a filtered list of source group memberships.
      $transitiveMemberOf = $this->getFilteredSourceGroupsForSourceRecord($candidate['id']);

      // If the filtered list of source group memberships is empty then the
      // candidate is not part of the set.
      if(empty($transitiveMemberOf)) {
        return $ret;
      } 
    } 

    $candidateRaw = json_encode($candidate);
      
    $ret[$candidate['id']] = $this->resultToOrgIdentity($candidateRaw, $extensions);

    $this->log("search is returning");

    return $ret;
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
    return array(
      'mail' => _txt('pl.entrasource.search.mail')
    );
  }

  /**
   * Synchronize the EntraSourceGroup objects.
   *
   * @since  COmanage Registry 4.4.0
   */

  protected function synchronizeSourceGroups() {
    $cfg = $this->getConfig();

    $EntraSource = new EntraSource();

    // TODO Remove the assumption that we are using a source_group_filter.
    $entraGroups = array();
    $done = false;
    $nextLink = null;

    while(!$done) {
      $query = array();

      // Query for the list of groups using the configured query parameter, which will
      // probably be a filter on mailNickname.
      //
      // See https://learn.microsoft.com/en-us/graph/api/group-list?view=graph-rest-1.0&tabs=http
      if(!empty($nextLink)) {
        $urlPath = $nextLink;
        $query = array();
      } else {
        $urlPath = 'groups';
        $query['$filter'] = $cfg['source_group_filter'];
        // TODO remove hardcoded extension for gidNumber.
        $query['$select'] = 'id,mailNickname,extension_c5a01f20b34f469fad2518bfb66e7107_gidNumber';
        $query['$top'] = '999';
      }

      $body = $this->apiRequest($urlPath, query: $query);  

      if(!empty($body['@odata.nextLink'])) {
        $nextLink = $body['@odata.nextLink'];
      } else {
        $nextLink = null;
        $done = true;
      }

      if(!empty($body['value'])) {
        foreach($body['value'] as $g) {
          $entraGroups[$g['mailNickname']] =  array('id' => $g['id']);
          if(!empty($g['extension_c5a01f20b34f469fad2518bfb66e7107_gidNumber'])) {
            $entraGroups[$g['mailNickname']]['gidNumber'] = $g['extension_c5a01f20b34f469fad2518bfb66e7107_gidNumber'];
          }
        }
      } else {
        $done = true;
      }
    }

    // TODO remove this hard-coded additional list of groups.
    $entraGroups['nic-cluster-admins'] = array(
      'id' => '18acec66-005e-47f9-8685-c5a983bb8f13',
      'gidNumber' => 647975
      );
    $entraGroups['nic-cluster-admins-tier1-admin'] = array(
      'id' => '8d00e1de-8f74-48b3-a626-1d3f8aff6675',
      'gidNumber' => 100494552
      );
    $entraGroups['nic-software-installer'] = array(
      'id' => '57efb1ea-cb40-424e-8c7d-6a89f23a26b7',
      'gidNumber' => 100385889
      );

    // Synchronize the list of source groups. First make sure each group
    // returned by the Graph API query is saved as an EntraSourceGroup object.
    foreach($entraGroups as $mailNickname => $a) {
      $graphId = $a['id'] ?? null;
      $gidNumber = $a['gidNumber'] ?? null;

      $args = array();

      $args['conditions']['EntraSourceGroup.mail_nickname'] = $mailNickname;
      $args['conditions']['EntraSourceGroup.entra_source_id'] = $cfg['id'];
      $args['contain']= false;

      $entraSourceGroup = $EntraSource->EntraSourceGroup->find('first', $args);

      $data = array();

      $data['EntraSourceGroup']['entra_source_id'] = $cfg['id'];
      $data['EntraSourceGroup']['mail_nickname'] = $mailNickname;
      if(!empty($graphId)) {
        $data['EntraSourceGroup']['graph_id'] = $graphId;
      }
      if(!empty($gidNumber)) {
        $data['EntraSourceGroup']['gidnumber'] = $gidNumber;
      }

      if(empty($entraSourceGroup)) {
        $EntraSource->EntraSourceGroup->clear();
        $EntraSource->EntraSourceGroup->save($data);
        $this->log("Added EntraSourceGroup with mailNickname $mailNickname");
      } elseif(empty($entraSourceGroup['EntraSourceGroup']['graph_id']) ||
               empty($entraSourceGroup['EntraSourceGroup']['gidnumber'])) {
        $data['EntraSourceGroup']['id'] = $entraSourceGroup['EntraSourceGroup']['id'];
        $EntraSource->EntraSourceGroup->clear();
        $EntraSource->EntraSourceGroup->save($data);
        $this->log("Updated EntraSourceGroup with mailNickname $mailNickname");
      }
    }

    // Now see if we need to delete any EntraSourceGroup objects.
    // We purposely do not delete related CoGroup objects at this time.
    $args = array();
    $args['conditions']['EntraSourceGroup.entra_source_id'] = $cfg['id'];
    $args['contain'] = false;

    $sourceGroups = $EntraSource->EntraSourceGroup->find('all', $args);

    foreach($sourceGroups as $g) {
      if(!array_key_exists($g['EntraSourceGroup']['mail_nickname'], $entraGroups)) {
        $EntraSource->EntraSourceGroup->clear();
        $EntraSource->EntraSourceGroup->delete($g['EntraSourceGroup']['id']);

        $this->log("Deleted EntraSourceGroup with mailNickname " . $g['EntraSourceGroup']['mail_nickname']);
      }
    }

    // Now that we are reconciled pull the existing EntraSourceGroups again.
    $args = array();
    $args['conditions']['EntraSourceGroup.entra_source_id'] = $cfg['id'];
    $args['contain'] = false;

    $sourceGroups = $EntraSource->EntraSourceGroup->find('all', $args);

    // Make sure we have a CO Group, CoGroupOisMapping, and UnixClusterGroup 
    // for each EntraSourceGroup and that the CO Group has an Identifier
    // with the gidNumber type and the uid type.
    $args = array();
    $args['conditions']['OrgIdentitySource.id'] = $cfg['org_identity_source_id'];
    $args['contain'] = false;

    $orgIdentitySource = $EntraSource->OrgIdentitySource->find('first', $args);

    $coId = $orgIdentitySource['OrgIdentitySource']['co_id'];

    foreach($sourceGroups as $sg) {
      $args = array();
      $args['conditions']['CoGroup.name'] = $sg['EntraSourceGroup']['mail_nickname'];
      $args['conditions']['CoGroup.co_id'] = $coId;
      $args['contain'] = array('CoGroupOisMapping', 'Identifier');

      $coGroup = $EntraSource->OrgIdentitySource->Co->CoGroup->find('first', $args);

      if(empty($coGroup['CoGroup'])) {
        $data= array();
        $data['CoGroup']['co_id'] = $coId;
        $data['CoGroup']['name'] = $sg['EntraSourceGroup']['mail_nickname'];
        $data['CoGroup']['open'] = false;
        $data['CoGroup']['group_type'] = GroupEnum::Clusters;
        $data['CoGroup']['status'] = SuspendableStatusEnum::Active;

        $EntraSource->OrgIdentitySource->Co->CoGroup->clear();
        $EntraSource->OrgIdentitySource->Co->CoGroup->save($data);

        $this->log("Added CoGroup " . $data['CoGroup']['name']);

        $coGroupId = $EntraSource->OrgIdentitySource->Co->CoGroup->id;
      } else {
        $coGroupId = $coGroup['CoGroup']['id'];
      }

      $UnixClusterGroup = new UnixClusterGroup();

      $args = array();
      $args['conditions']['UnixClusterGroup.co_group_id'] = $coGroupId;
      $args['conditions']['UnixClusterGroup.unix_cluster_id'] = $cfg['unix_cluster_id'];
      $args['contain'] = false;

      $unixClusterGroup = $UnixClusterGroup->find('first', $args);

      if(empty($unixClusterGroup)) {
        $data = array();
        $data['UnixClusterGroup']['co_group_id'] = $coGroupId;
        $data['UnixClusterGroup']['unix_cluster_id'] = $cfg['unix_cluster_id'];

        $UnixClusterGroup->clear();
        $UnixClusterGroup->save($data);

        $this->log("Added UnixClusterGroup for " . $sg['EntraSourceGroup']['mail_nickname']);
      }

      if(empty($coGroup['CoGroupOisMapping'])) {
        $data = array();
        $data['CoGroupOisMapping']['org_identity_source_id'] = $cfg['org_identity_source_id'];
        $data['CoGroupOisMapping']['attribute'] = 'memberOf';
        $data['CoGroupOisMapping']['comparison'] = ComparisonEnum::Equals;
        $data['CoGroupOisMapping']['pattern'] = $sg['EntraSourceGroup']['mail_nickname'];
        $data['CoGroupOisMapping']['co_group_id'] = $coGroupId;

        $EntraSource->OrgIdentitySource->Co->CoGroup->CoGroupOisMapping->clear();
        $EntraSource->OrgIdentitySource->Co->CoGroup->CoGroupOisMapping->save($data);

        $this->log("Added CoGroupOisMapping for " . $data['CoGroupOisMapping']['pattern']);
      }

      // TODO remove assumptions here.
      // Add an Identifier of type uid.
      $args = array();

      $args['conditions']['Identifier.co_group_id'] = $coGroupId;
      $args['conditions']['Identifier.type'] = 'uid';
      $args['conditions']['Identifier.status'] = SuspendableStatusEnum::Active;
      $args['contain'] = false;

      $identifier = $EntraSource->OrgIdentitySource->Co->CoGroup->Identifier->find('first', $args);

      if(empty($identifier) || ($identifier['Identifier']['identifier'] != $sg['EntraSourceGroup']['mail_nickname'])) {
        $data = array();

        $data['Identifier']['identifier'] = $sg['EntraSourceGroup']['mail_nickname'];
        $data['Identifier']['type'] = 'uid';
        $data['Identifier']['login'] = false;
        $data['Identifier']['status'] = SuspendableStatusEnum::Active;
        $data['Identifier']['co_group_id'] = $coGroupId;

        if(!empty($identifier['Identifier']['id'])) {
          $data['Identifier']['id'] = $identifier['Identifier']['id'];
          $msg = "Updated Identifier of type uid for CoGroup " . $sg['EntraSourceGroup']['mail_nickname'];
        } else {
          $msg = "Added Identifier of type uid for CoGroup " . $sg['EntraSourceGroup']['mail_nickname'];
        }

        $EntraSource->OrgIdentitySource->Co->CoGroup->Identifier->clear();
        $EntraSource->OrgIdentitySource->Co->CoGroup->Identifier->save($data, array('validate' => false));

        $this->log($msg);
      }

      // Go onto the next EntraSourceGroup if this one does not have a
      // gidnumber.
      if(empty($sg['EntraSourceGroup']['gidnumber'])) {
        continue;
      }

      // TODO remove assumptions here about Identifier and even the
      // need for an Identifier.
      // Add an Identifier of type gidnumber.
      $args = array();

      $args['conditions']['Identifier.co_group_id'] = $coGroupId;
      $args['conditions']['Identifier.type'] = 'gidnumber';
      $args['conditions']['Identifier.status'] = SuspendableStatusEnum::Active;
      $args['contain'] = false;

      $identifier = $EntraSource->OrgIdentitySource->Co->CoGroup->Identifier->find('first', $args);

      if(empty($identifier) || ($identifier['Identifier']['identifier'] != strval($sg['EntraSourceGroup']['gidnumber']))) {
        $data = array();

        $data['Identifier']['identifier'] = strval($sg['EntraSourceGroup']['gidnumber']);
        $data['Identifier']['type'] = 'gidnumber';
        $data['Identifier']['login'] = false;
        $data['Identifier']['status'] = SuspendableStatusEnum::Active;
        $data['Identifier']['co_group_id'] = $coGroupId;

        if(!empty($identifier['Identifier']['id'])) {
          $data['Identifier']['id'] = $identifier['Identifier']['id'];
          $msg = "Updated Identifier for CoGroup " . $sg['EntraSourceGroup']['mail_nickname'];
        } else {
          $msg = "Added Identifier for CoGroup " . $sg['EntraSourceGroup']['mail_nickname'];
        }

        $EntraSource->OrgIdentitySource->Co->CoGroup->Identifier->clear();
        $EntraSource->OrgIdentitySource->Co->CoGroup->Identifier->save($data, array('validate' => false));

        $this->log($msg);
      }

    }

    return;
  }

  /**
   * Synchronize the source group memberships for a single source record.
   *
   * @since  COmanage Registry 4.4.0
   * @param  Integer $sourceRecordId EntraSourceRecord id
   * @param  Array   $transitiveMemberOf list of arrays, each indexed by id, mailNickName, and entraSourceGroupId
   * @return null
   *
   */

  protected function syncMembershipsSourceRecord($sourceRecordId, $transitiveMemberOf) {
    $EntraSource = new EntraSource();

    // Loop over list of transitive memberships and add
    // EntraSourceGroupMemberships as necessary.

    // Record a list of EntraSourceGroup ids based on the input
    // list of memberships.
    $sourceGroupIds = array();

    foreach($transitiveMemberOf as $m) {
      $args = array();
      $args['conditions']['EntraSourceGroupMembership.entra_source_record_id'] = $sourceRecordId;
      $args['conditions']['EntraSourceGroupMembership.entra_source_group_id'] = $m['entraSourceGroupId'];
      $args['contain'] = false;

      $sgm = $EntraSource->EntraSourceRecord->EntraSourceGroupMembership->find('first', $args);

      if(empty($sgm)) {
        $data = array();
        $data['EntraSourceGroupMembership']['entra_source_record_id'] = $sourceRecordId;
        $data['EntraSourceGroupMembership']['entra_source_group_id'] = $m['entraSourceGroupId'];

        $EntraSource->EntraSourceRecord->EntraSourceGroupMembership->clear();
        $EntraSource->EntraSourceRecord->EntraSourceGroupMembership->save($data);
        $this->log("Added EntraSourceGroupMembership " . print_r($data, true));
      }

      $sourceGroupIds[] = $m['entraSourceGroupId'];
    }

    // Next remove any EntraSourceGroupMemberships if necessary.
    $args = array();
    $args['conditions']['EntraSourceGroupMembership.entra_source_record_id'] = $sourceRecordId;
    $args['contain'] = false;

    $memberships = $EntraSource->EntraSourceRecord->EntraSourceGroupMembership->find('all', $args);

    foreach($memberships as $m) {
      if(!in_array($m['EntraSourceGroupMembership']['entra_source_group_id'], $sourceGroupIds)) {
        $EntraSource->EntraSourceRecord->EntraSourceGroupMembership->clear();
        $EntraSource->EntraSourceRecord->EntraSourceGroupMembership->delete($m['EntraSourceGroupMembership']['id']);
        $this->log("Deleted EntraSourceGroupMembership " . print_r($m, true));
      }
    }
  }

  /**
   * Synchronize a list of transitive members by Entra user ID with
   * the EntraSourceGroupMembership objects.
   *
   * @since  COmanage Registry 4.4.0
   * @param  Array $members Array where values are the Entra ID for users
   * @param  Array $g EntraSourceGroup object
   */

  protected function synchronizeTransitiveMembers($members, $g) {
    $entraSourceId = $g['EntraSourceGroup']['entra_source_id'];
    $entraSourceGroupId = $g['EntraSourceGroup']['id'];

    $EntraSource = new EntraSource();

    $recordIds = array();

    // Loop over the list of Entra IDs for users that are transitive
    // members.

    foreach($members as $graphId) {
      // Add a EntraSourceRecord object.
      $recordId = $this->addSourceRecord($graphId, $entraSourceId, $entraSourceGroupId);

      $recordIds[] = $recordId;

      // Record the Entra group membership if necessary.
      $args = array();
      $args['conditions']['EntraSourceGroupMembership.entra_source_group_id'] = $entraSourceGroupId;
      $args['conditions']['EntraSourceGroupMembership.entra_source_record_id'] = $recordId;
      $args['contain'] = false;

      $membership = $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->find('first', $args);

      if(empty($membership)) {
        $data = array();
        $data['EntraSourceGroupMembership']['entra_source_group_id'] = $entraSourceGroupId;
        $data['EntraSourceGroupMembership']['entra_source_record_id'] = $recordId;

        $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->clear();
        $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->save($data);

        $this->log("Saved EntraSourceGroupMembership " . print_r($data, true));
      }
    }

    // Now loop over EntraSourceGroupMembership objects and delete
    // any that are not represented in Entra.

    $args = array();
    $args['conditions']['EntraSourceGroupMembership.entra_source_group_id'] =  $entraSourceGroupId;
    $args['contain'] = false;

    $memberships = $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->find('all', $args);

    foreach($memberships as $m) {
      $recordId = $m['EntraSourceGroupMembership']['entra_source_record_id'];

      if(!in_array($recordId, $recordIds)) {
        $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->clear();
        $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->delete($m['EntraSourceGroupMembership']['id']);

        $this->log("Deleted EntraSourceGroupMembership " . print_r($m, true));
      }
    }
  }
}
