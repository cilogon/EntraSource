<?php

App::uses('EntraSource', 'EntraSource.Model');
App::uses('HttpSocket', 'Network/Http');
App::uses('OrgIdentitySourceBackend', 'Model');
App::uses('Server', 'Model');

class EntraSourceBackend extends OrgIdentitySourceBackend {
  public $name = "EntraSourceBackend";


  protected $accessTokenServer = null;
  protected $apiServer = null;

  /**
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
   */

  protected function apiRequest($urlPath, $query, $action="get") {
    $EntraSource = new EntraSource();
    $atServerId = $this->accessTokenServer['Oauth2Server']['id'];

    $deltat = 10;
    $expired = $EntraSource->Server->Oauth2Server->isExpired($atServerId, $deltat);

    if($expired === null) {
      $this->log("FOO expired is null");
    } elseif ($expired === true) {
      $this->log("FOO expired is true");
    } elseif ($expired === false) {
      $this->log("FOO expired is false");
    }

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
   * @since  COmanage Registry v2.0.0
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
   * @since  COmanage Registry v2.0.0
   * @return Array Array of unique keys
   * @throws DomainException If the backend does not support this type of requests
   */
  
  public function inventory() {
    try {
      $this->apiConnect();
    } catch(InvalidArgumentException $e) {
      // TODO
    }

    $cfg = $this->getConfig();

    if($cfg['use_source_groups']) {
      return $this->inventoryBySourceGroups();
    } else {
      return $this->inventoryAllUsers();
    }
  }

  /**
   */

  protected function inventoryAllUsers() {
    // TODO

    return array();
  }

  /**
   */

  protected function inventoryBySourceGroups() {
    $cfg = $this->getConfig();

    $args = array();
    $args['conditions']['EntraSourceGroup.entra_source_id'] = $cfg['id'];
    $args['contain'] = false;

    $EntraSource = new EntraSource();

    $sourceGroups = $EntraSource->EntraSourceGroup->find('all', $args);

    if(empty($sourceGroups)) {
      return array();
    }

    $this->log("FOO sourceGroups is " . print_r($sourceGroups, true));

    foreach($sourceGroups as $g) {
      if(empty($g['EntraSourceGroup']['graph_id'])) {
        $mailNickname = $g['EntraSourceGroup']['mail_nickname'];
        $apiId = $this->sourceGroupApiIdByNickname($mailNickname);

        // Update the source group with the api ID.
        $EntraSource->EntraSourceGroup->clear();
        $EntraSource->EntraSourceGroup->id = $g['EntraSourceGroup']['id'];
        $EntraSource->EntraSourceGroup->saveField('graph_id', $apiId);
      } else {
        $apiId = $g['EntraSourceGroup']['graph_id'];
      }

      $done = false;

      while(!$done) {
        $query = array();

        if(!empty($g['EntraSourceGroup']['delta_next_link'])) {
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

        $this->log("FOO body is " . print_r($body, true));

        if(!empty($body['value'])) {
          if(!empty($body['value'][0]['members@delta'])) {
            foreach($body['value'][0]['members@delta'] as $m) {
              if(array_key_exists('@removed', $m)) {
                // TODO Remove
              } else {
                // Add if necessary.
                $args = array();
                $args['conditions']['EntraSourceRecord.graph_id'] = $m['id'];
                $args['conditions']['EntraSourceRecord.entra_source_id'] = $g['EntraSourceGroup']['entra_source_id'];
                $args['contains'] = false;

                $sourceRecord = $EntraSource->EntraSourceRecord->find('first', $args);

                if(empty($sourceRecord)) {
                  $data = array();
                  $data['EntraSourceRecord']['entra_source_id'] = $g['EntraSourceGroup']['entra_source_id'];
                  $data['EntraSourceRecord']['graph_id'] = $m['id'];

                  $EntraSource->EntraSourceRecord->clear();
                  $EntraSource->EntraSourceRecord->save($data);
                  $recordId = $EntraSource->EntraSourceRecord->id;

                  $this->log("FOO saved source record " . print_r($data, true));
                } else {
                  $recordId = $sourceRecord['EntraSourceRecord']['id'];
                }

                // Record the Entra group membership if necessary.
                $args = array();
                $args['conditions']['EntraSourceGroupMembership.entra_source_group_id'] = $g['EntraSourceGroup']['id'];
                $args['conditions']['EntraSourceGroupMembership.entra_source_record_id'] = $recordId;
                $args['contains'] = false;

                $membership = $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->find('first', $args);

                if(empty($membership)) {
                  $data = array();
                  $data['EntraSourceGroupMembership']['entra_source_group_id'] = $g['EntraSourceGroup']['id'];
                  $data['EntraSourceGroupMembership']['entra_source_record_id'] = $recordId;

                  $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->clear();
                  $EntraSource->EntraSourceGroup->EntraSourceGroupMembership->save($data);

                  $this->log("FOO saved group membership " . print_r($data, true));
                }
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

          $this->log("FOO saving next link now...");
          $ret = $EntraSource->EntraSourceGroup->saveField('graph_next_link', $body['@odata.nextLink']);
          if(!$ret) {
            $this->log("FOO validation errors is " . print_r($EntraSource->EntraSourceGroup->validationErrors, true));
          }

          $g['EntraSourceGroup']['graph_next_link'] = $body['@odata.nextLink'];
        } elseif(!empty($body['@odata.deltaLink'])) {
          $EntraSource->EntraSourceGroup->clear();
          $EntraSource->EntraSourceGroup->id = $g['EntraSourceGroup']['id'];

          $this->log("FOO saving delta link now...");
          $ret = $EntraSource->EntraSourceGroup->saveField('delta_next_link', $body['@odata.deltaLink']);
          if(!$ret) {
            $this->log("FOO validation errors is " . print_r($EntraSource->EntraSourceGroup->validationErrors, true));
          }

          $this->log("FOO clearing next link now...");
          $ret = $EntraSource->EntraSourceGroup->saveField('graph_next_link', null);
          if(!$ret) {
            $this->log("FOO validation errors is " . print_r($EntraSource->EntraSourceGroup->validationErrors, true));
          }

          $g['EntraSourceGroup']['delta_next_link'] = $body['@odata.deltaLink'];
          $g['EntraSourceGroup']['graph_next_link'] = null;
        }



      }


    }

    // At this point we have looped over all the source groups and so
    // we should have an up-to-date set of source records.

    $args = array();
    $args['fields'] = array('id', 'graph_id');
    $args['contain'] = false;

    $inventory = $EntraSource->EntraSourceRecord->find('list', $args);

    return array_values($inventory);


  }

  /**
   */

  protected function sourceGroupApiIdByNickname($mailNickname) {

    $urlPath = "groups/";
    $query = array(
      '$filter' => "mailNickname eq '$mailNickname'"
    );

    $body = $this->apiRequest($urlPath, $query);

    $this->log("FOO body is " . print_r($body, true));

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
   */

  protected function resultToOrgIdentity($result, $extensions = array()) {

    $record = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

    $orgdata = array();

    $orgdata['OrgIdentity'] = array();

    $orgdata['OrgIdentity']['affiliation'] = AffiliationEnum::Member;

    $orgdata['Name'] = array();

    if(!empty($record['givenName'])) {
      $orgdata['Name'][0]['given'] = $record['givenName'];
    }

    if(!empty($record['surname'])) {
      $orgdata['Name'][0]['family'] = $record['surname'];
    }

    if(!empty($orgdata['Name'][0])) {
      $orgdata['Name'][0]['type'] = NameEnum::Official;
      $orgdata['Name'][0]['primary_name'] = true;
    }

    if(!empty($record['mail'])) {
      $orgdata['EmailAddress'][0]['mail'] = $record['mail'];
      $orgdata['EmailAddress'][0]['type'] = EmailAddressEnum::Official;
      $orgdata['EmailAddress'][0]['verified'] = true;
    }

    $orgdata['Identifier'][0] = array(
      'identifier' => $record['id'],
      'type' => IdentifierEnum::SORID,
      'status' => SuspendableStatusEnum::Active
    );

    // TODO make the type configuration.
    $orgdata['Identifier'][1] = array(
      'identifier' => $record['userPrincipalName'],
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
    try {
      $this->apiConnect();
    } catch(InvalidArgumentException $e) {
      // TODO
    }

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

    $this->log("FOO record is " . print_r($record, true));

    // Pull extension properties if any.
    $args = array();
    $args['conditions']['EntraSourceExtensionProperty.entra_source_id'] = $cfg['id'];
    $args['contain'] = false;

    $extensions = $EntraSource->EntraSourceExtensionProperty->find('all', $args);

    $this->log("FOO extensions is " . print_r($extensions, true));



    $done = false;

    while(!$done) {
      $query = array();

      if(!empty($record['EntraSourceRecord']['delta_next_link'])) {
        $urlPath = $record['EntraSourceRecord']['delta_next_link'];
      } elseif(!empty($record['EntraSourceRecord']['graph_next_link'])) {
        $urlPath = $record['EntraSourceRecord']['graph_next_link'];
      } else {
        $urlPath = "users/delta";
        $select = array('id', 'givenName', 'surname', 'mail', 'userPrincipalName');
        foreach($extensions as $e) {
          $select[] = $e['EntraSourceExtensionProperty']['property'];
        }

        $this->log("FOO select is " . print_r($select, true));

        $query = array(
          '$select' => implode(',', $select),
          '$filter' => "id eq '$id'"
        );
      }

      $this->log("FOO urlPath is $urlPath");
      $this->log("FOO query is " . print_r($query, true));

      $body = $this->apiRequest($urlPath, $query);

      $this->log("FOO body is " . print_r($body, true));

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

        $this->log("FOO saving next link now...");
        $ret = $EntraSource->EntraSourceRecord->saveField('graph_next_link', $body['@odata.nextLink']);
        if(!$ret) {
          $this->log("FOO validation errors is " . print_r($EntraSource->EntraSourceRecord->validationErrors, true));
        }

        $record['EntraSourceRecord']['graph_next_link'] = $body['@odata.nextLink'];
      } elseif(!empty($body['@odata.deltaLink'])) {
        $EntraSource->EntraSourceRecord->clear();
        $EntraSource->EntraSourceRecord->id = $record['EntraSourceRecord']['id'];

        $this->log("FOO saving delta link now...");
        $ret = $EntraSource->EntraSourceRecord->saveField('delta_next_link', $body['@odata.deltaLink']);
        if(!$ret) {
          $this->log("FOO validation errors is " . print_r($EntraSource->EntraSourceRecord->validationErrors, true));
        }

        $this->log("FOO clearing next link now...");
        $ret = $EntraSource->EntraSourceRecord->saveField('graph_next_link', null);
        if(!$ret) {
          $this->log("FOO validation errors is " . print_r($EntraSource->EntraSourceRecord->validationErrors, true));
        }

        $record['EntraSourceRecord']['delta_next_link'] = $body['@odata.deltaLink'];
        $record['EntraSourceRecord']['graph_next_link'] = null;
      }
    }

    $raw = $record['EntraSourceRecord']['source_record'];
    $orgId = $this->resultToOrgIdentity($raw, $extensions);

    $this->log("FOO raw is " . print_r($raw, true));
    $this->log("FOO orgId is " . print_r($orgId, true));

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
