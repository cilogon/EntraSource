<?php

class EntraSourceRecord extends AppModel {
  // Association rules from this model to other models
  public $belongsTo = array(
    "EntraSource.EntraSource"
  );

  public $hasMany = array(
    "EntraSource.EntraSourceGroupMembership" => array('dependent' => true)
  );

  // Default display fied for cake generated views
  public $displayField = "graph_id";

  public $actsAs = array('Containable');

  // Validation rules for table elements
  public $validate = array(
    'entra_source_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'graph_id' => array(
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    )
  );
}
