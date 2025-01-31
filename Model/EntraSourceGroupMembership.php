<?php

class EntraSourceGroupMembership extends AppModel {
  // Define calss name for cake
  public $name = "EntraSourceGroupMembership";

  // Add behaviors
  public $actsAs = array('Containable');

  // Association rules from this model to other models
  public $belongsTo =  array(
    "EntraSource.EntraSourceGroup",
    "EntraSource.EntraSourceRecord"
  );

  // Default display field for cake generated views
  public $displayField = "mail_nickname";

  // Validation rules for table elements
  public $validate = array(
    'entra_source_group_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'entra_source_record_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    )
  );
}
