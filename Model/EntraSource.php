<?php

class EntraSource extends AppModel {
  // Required by COmanage Plugins
  public $cmPluginType = "orgidsource";

  // Document foreign keys
  public $cmPluginHasMany = array(
    "OAuth2Server" => array("EntraSource"),
    "HttpServer" => array("EntraSource")
  );

  // Association rules from this model to other models
  public $belongsTo = array(
    "OrgIdentitySource",
    "Server"
  );

  public $hasMany = array(
    "EntraSourceRecord" => array('dependent' => true),
    "EntraSourceGroup" => array('dependent' => true),
    "EntraSourceExtensionProperty" => array('dependent' => true)
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