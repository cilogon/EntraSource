<?php

class EntraSourceExtensionProperty extends AppModel {
  // Association rules from this model to other models
  public $belongsTo = array(
    "EntraSource.EntraSource"
  );

  // Default display fied for cake generated views
  public $displayField = "property";

  public $actsAs = array('Containable');

  // Validation rules for table elements
  public $validate = array(
    'entra_source_id' => array(
      'rule' => 'numeric',
      'required' => true,
      'allowEmpty' => false
    ),
    'property' => array(
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    ),
    'identifier_type' => array(
      'rule' => 'notBlank',
      'required' => true,
      'allowEmpty' => false
    )
  );
}
