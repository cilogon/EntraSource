<?php

App::uses("SOISController", "Controller");

class EntraSourcesController extends SOISController {
  // Class name, used by Cake
  public $name = "EntraSources";

  // Establish pagination parameters for HTML views
  public $paginate = array(
    'limit' => 25,
    'order' => array(
      'sor_label' => 'asc'
    )
  );
  
  public $edit_contains = array(
    'OrgIdentitySource'
  );

  function beforeRender() {
    parent::beforeRender();

    // Pull the list of available OAuth2 servers.
    $args = array();
    $args['contain'] = 'Server';

    $coId = $this->cur_co['Co']['id'];

    $servers = $this->EntraSource->Server->Oauth2Server->find('all', $args);

    $accessTokenServers = array();

    foreach($servers as $s) {
      if($s['Server']['co_id'] == $coId) {
        $id = $s['Oauth2Server']['id'];
        $label = $s['Server']['description'];
        $accessTokenServers[$id] = $label;
      }
    }

    $this->set('vv_access_token_server_ids', $accessTokenServers);

    // Pull the list of available Http servers.
    $servers = $this->EntraSource->Server->HttpServer->find('all', $args);

    $apiServers = array();

    foreach($servers as $s) {
      if($s['Server']['co_id'] == $coId) {
        $id = $s['HttpServer']['id'];
        $label = $s['Server']['description'];
        $apiServers[$id] = $label;
      }
    }

    $this->set('vv_api_server_ids', $apiServers);

    // Pull the list of available UnixClusters.
    $args =  array();
    $args['contain'] = 'Cluster';

    $clusters = $this->EntraSource->UnixCluster->find('all', $args);

    $unixClusters = array();

    foreach($clusters as $c) {
      if($c['Cluster']['co_id'] == $coId) {
        $id = $c['UnixCluster']['id'];
        $label = $c['Cluster']['description'];
        $unixClusters[$id] = $label;
      }
    }

    $this->set('vv_unix_clusters', $unixClusters);
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
    
    // Delete an existing Source?
    $p['delete'] = $roles['cmadmin'] || $coadmin;
    
    // Edit an existing Source?
    $p['edit'] = $roles['cmadmin'] || $coadmin;
    
    // View all existing Sources?
    $p['index'] = $roles['cmadmin'] || $coadmin;
    
    // View an existing Source?
    $p['view'] = $roles['cmadmin'] || $coadmin;
    
    $this->set('permissions', $p);
    return($p[$this->action]);
  }
}
