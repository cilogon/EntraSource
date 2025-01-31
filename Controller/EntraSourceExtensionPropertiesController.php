<?php

App::uses("StandardController", "Controller");

class EntraSourceExtensionPropertiesController extends StandardController {
  // Class name, used by Cake
  public $name = "EntraSourceExtensionProperties";

  // Establish pagination parameters for HTML views
  public $paginate = array(
    'limit' => 25,
    'order' => array(
      'EntraSourceExtensionProperty.id' => 'asc'
    )
  );

  public $uses = array(
    'EntraSource.EntraSourceExtensionProperty',
    'EntraSource.EntraSource'
  );

  private $esid = null;

  function beforeFilter() {
    parent::beforeFilter();

    $esid = null;

    if($this->action == 'add' || $this->action == 'delete' || $this->action == 'index') {
      if(!empty($this->request->params['named']['esid'])) {
        $esid = filter_var($this->request->params['named']['esid'],FILTER_SANITIZE_SPECIAL_CHARS);
      } elseif(!empty($this->request->data['EntraSourceExtensionProperty']['entra_source_id'])) {
        $esid = filter_var($this->request->data['EntraSourceExtensionProperty']['entra_source_id'],FILTER_SANITIZE_SPECIAL_CHARS);
      }
    } elseif(!empty($this->request->params['pass'][0])) {
      $args = array();
      $args['conditions']['EntraSourceExtensionProperty.id'] = $this->request->params['pass'][0];
      $args['contain'] = false;

      $entraSourceExtensionProperty = $this->EntraSourceExtensionProperty->find('first', $args);

      $esid = $entraSourceExtensionProperty['EntraSourceExtensionProperty']['entra_source_id'];
    }
    
    if(!empty($esid)) {
      $args = array();
      $args['conditions']['EntraSource.id'] = $esid;
      $args['contain'] = array('OrgIdentitySource');
      
      $entraSource = $this->EntraSourceExtensionProperty->EntraSource->find('first', $args);
      
      if($entraSource) {
        $coId = $entraSource['OrgIdentitySource']['co_id'];
        $this->set('vv_entra_source', $entraSource);
        $this->esid = $entraSource['EntraSource']['id'];
        $this->set('vv_esid', $esid);
        $this->set('vv_identifier_types', $this->EntraSourceExtensionProperty
                                               ->EntraSource
                                               ->OrgIdentitySource
                                               ->Co
                                               ->CoPerson
                                               ->Identifier
                                               ->types($coId, 'type'));
      }
    }


  }

  /**
   * Determine the CO ID based on some attribute of the request.
   * This method is intended to be overridden by model-specific controllers.
   *
   * @since  COmanage Registry v3.3.0
   * @return Integer CO ID, or null if not implemented or not applicable.
   * @throws InvalidArgumentException
   */

  protected function calculateImpliedCoId($data = null) {
    // If an EntraSource  is specified, use it to get to the CO ID
    $esid = null;

    if(in_array($this->action, array('add', 'index'))
       && !empty($this->params->named['esid'])) {
      $esid = $this->params->named['esid'];
    } elseif(!empty($this->request->data['EntraSourceExtensionProperty']['entra_source_id'])) {
      $esid = $this->request->data['EntraSourceExtensionProperty']['entra_source_id'];
    }

    if(!empty($esid)) {
      // Map EntraSource to CO

      $args = array();
      $args['conditions']['EntraSource.id'] = $esid;
      $args['contain'] = 'OrgIdentitySource';

      $entraSources = $this->EntraSourceExtensionProperty->EntraSource->find('first', $args);
      
      $coId = $entraSources['OrgIdentitySource']['co_id'];

      if(!$coId) {
        throw new InvalidArgumentException(_txt('er.notfound', array(_txt('ct.clusters.1'), $cid)));
      }
      
      return $coId;
    }

    // Or try the default behavior
    return parent::calculateImpliedCoId();
  }

  function isAuthorized() {
    $roles = $this->Role->calculateCMRoles();
    
    // Construct the permission set for this user, which will also be passed to the view.
    $p = array();
    
    // Determine what operations this user can perform
    
    $coadmin = false;
    
    if($roles['coadmin'] && !$this->CmpEnrollmentConfiguration->orgIdentitiesPooled()) {
      // CO Admins can only manage org identity sources if org identities are NOT pooled
      $coadmin = true;
    }

    // Add an EntraSourceExtensionProperty?
    $p['add'] = $roles['cmadmin'] || $coadmin;
    
    // Delete an existing EntraSourceExtensionProperty?
    $p['delete'] = $roles['cmadmin'] || $coadmin;
    
    // Edit an existing EntraSourceExtensionProperty?
    $p['edit'] = $roles['cmadmin'] || $coadmin;
    
    // View all existing EntraSourceExtensionProperty?
    $p['index'] = $roles['cmadmin'] || $coadmin;
    
    // View an existing EntraSourceExtensionProperty?
    $p['view'] = $roles['cmadmin'] || $coadmin;
    
    $this->set('permissions', $p);
    return($p[$this->action]);
  }

  function paginationConditions() {
    $ret = array();

    $ret['conditions']['EntraSourceExtensionProperty.entra_source_id'] = $this->esid;

    return $ret;
  }

  /**
   * Perform a redirect back to the controller's default view.
   * - postcondition: Redirect generated
   *
   * @since  COmanage Registry v3.3.0
   */
  
  function performRedirect() {
    // Figure out where to redirect back to based on how we were called
    
    if(isset($this->esid)) {
      $params = array(
        'plugin'     => 'entra_source',
        'controller' => 'entra_source_extension_properties',
        'action'     => 'index',
        'esid'       => $this->esid
      );
    } else {
      // A perhaps not ideal default, but we shouldn't get here
      $params = array(
        'plugin'     => '',
        'controller' => 'org_identity_sources',
        'action'     => 'index',
        'co'         => $this->cur_co['Co']['id']
      );
    }
    
    $this->redirect($params);
  }
}
