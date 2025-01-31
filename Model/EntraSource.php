<?php

class EntraSource extends AppModel {
  // Required by COmanage Plugins
  public $cmPluginType = "orgidsource";

  // TODO Remove assumption that UnixCluster plugin is enabled.

  // Document foreign keys
  public $cmPluginHasMany = array(
    "OAuth2Server" => array("EntraSource"),
    "HttpServer" => array("EntraSource"),
    "UnixCluster" => array("EntraSource")
  );

  // Association rules from this model to other models
  public $belongsTo = array(
    "OrgIdentitySource",
    "Server",
    "UnixCluster"
  );

  public $hasMany = array(
    "EntraSource.EntraSourceRecord" => array('dependent' => true),
    "EntraSource.EntraSourceGroup" => array('dependent' => true),
    "EntraSource.EntraSourceExtensionProperty" => array('dependent' => true)
  );

  // Default display field for cake generated views
  public $displayField = "id";

  public $actsAs = array('Containable');

  // Validation rules for table elements
  public $validate = array(
    'org_identity_source_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'access_token_server_id' => array(
      'rule' => 'notBlank',
      'required' => true,
      'allowEmtpy' => false
    ),
    'api_server_id' => array(
      'rule' => 'notBlank',
      'required' => true,
      'allowEmtpy' => false
    ),
    'use_source_groups' => array(
      'rule' => 'boolean',
      'required' => false,
      'allowEmpty' => true
    ),
    'source_group_filter' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
    'max_inventory_cache' => array(
      'rule' => 'numeric',
      'required' => false,
      'allowEmpty' => true
    ),
    'inventory_cache_start' => array(
      'rule' => 'validateTimestamp',
      'required' => false,
      'allowEmpty' => true
    ),
    'unix_cluster_id' => array(
      'rule' => 'numeric',
      'required' => false,
      'allowEmpty' => true
    )
  );


  /**
   * Expose menu items.
   * 
   * @since COmanage Registry v4.3.5
   * @return Array with menu location type as key and array of labels, controllers, actions as values.
   */
  
  public function cmPluginMenus() {
    return array();
  }
}
