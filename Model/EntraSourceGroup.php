<?php

class EntraSourceGroup extends AppModel {
  // Define calss name for cake
  public $name = "EntraSourceGroup";

  // Add behaviors
  public $actsAs = array('Containable');

  // Association rules from this model to other models
  public $belongsTo =  array(
    "EntraSource.EntraSource"
  );

  // Default display field for cake generated views
  public $displayField = "mail_nickname";

  // Validation rules for table elements
  public $validate = array(
    'entra_source_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'mail_nickname' => array(
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    ),
    'graph_id' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
    'graph_next_link' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    ),
    'delta_next_link' => array(
      'rule' => 'notBlank',
      'required' => false,
      'allowEmpty' => true
    )
  );


}