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

  /**
   * Obtain the CO ID for a record.
   *
   * @since  COmanage Registry v0.8
   * @param  integer Record to retrieve for
   * @return integer Corresponding CO ID, or NULL if record has no corresponding CO ID
   * @throws InvalidArgumentException
   * @throws RuntimeException
   */

  public function findCoForRecord($id) {
    $args = array();
    $args['conditions']['EntraSourceExtensionProperty.id'] = $id;
    $args['contain']['EntraSource'] = 'OrgIdentitySource';

    $entraSourceExtensionProperty = $this->find('first', $args);

    $coId = $entraSourceExtensionProperty['EntraSource']['OrgIdentitySource']['co_id'];

    return $coId;
  }
}
