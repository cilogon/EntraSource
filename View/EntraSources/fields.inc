<?php
  // Determine if fields are editable
  $e = false;
  
  if(($this->action == "add" && $permissions['add']) || ($this->action == "edit" && $permissions['edit']))
    $e = true;
    
  // We shouldn't get here if we don't have at least read permission, but check just in case
  
  if(!$e && !$permissions['view'])
    return false;
  
  // Add breadcrumbs
  print $this->element("coCrumb");

  $args = array();
  $args['plugin'] = null;
  $args['controller'] = 'org_identity_sources';
  $args['action'] = 'index';
  $args['co'] = $cur_co['Co']['id'];
  $this->Html->addCrumb(_txt('ct.org_identity_sources.pl'), $args);

  $args = array();
  $args['plugin'] = null;
  $args['controller'] = 'org_identity_sources';
  $args['action'] = 'edit';
  $args[] = $vv_oisid;
  $this->Html->addCrumb($entra_sources[0]['OrgIdentitySource']['description'], $args);

  $crumbTxt = _txt('op.config');
  $this->Html->addCrumb($crumbTxt);
  
  print $this->Form->hidden('org_identity_source_id', array('default' => $vv_oisid)) . "\n";
?>
<script type="text/javascript">
  <!-- JS specific to these fields -->
  
  
  function js_local_onload() {
  }
</script>
<ul id="<?php print $this->action; ?>_entra_source" class="fields form-list">
  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print _txt('pl.entrasource.access_token_server_id'); ?>
        <span class="required">*</span>
      </div>
      <span class="field-desc"><?php print _txt('pl.entrasource.access_token_server_id.desc'); ?></span>
    </div>
    <div class="field-info">
      <?php
        $attrs = array();
        $attrs['value'] = (isset($entra_sources[0]['EntraSource']['access_token_server_id'])
                           ? $entra_sources[0]['EntraSource']['access_token_server_id']
                           : "");
        $attrs['empty'] = true;

        if($e) {
          print $this->Form->select('access_token_server_id',
                                    $vv_access_token_server_ids,
                                    $attrs);

          if($this->Form->isFieldError('access_token_server_id')) {
            print $this->Form->error('access_token_server_id');
          }
        } else {
          print filter_var($vv_access_token_server_ids[ $entra_sources[0]['EntraSource']['access_token_server_id'] ], FILTER_SANITIZE_SPECIAL_CHARS);
        }
      ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print _txt('pl.entrasource.api_server_id'); ?>
        <span class="required">*</span>
      </div>
      <span class="field-desc"><?php print _txt('pl.entrasource.api_server_id.desc'); ?></span>
    </div>
    <div class="field-info">
      <?php
        $attrs = array();
        $attrs['value'] = (isset($entra_sources[0]['EntraSource']['api_server_id'])
                           ? $entra_sources[0]['EntraSource']['api_server_id']
                           : "");
        $attrs['empty'] = true;

        if($e) {
          print $this->Form->select('api_server_id',
                                    $vv_api_server_ids,
                                    $attrs);

          if($this->Form->isFieldError('api_server_id')) {
            print $this->Form->error('api_server_id');
          }
        } else {
          print filter_var($vv_api_server_ids[ $entra_sources[0]['EntraSource']['api_server_id'] ], FILTER_SANITIZE_SPECIAL_CHARS);
        }
      ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print _txt('pl.entrasource.use_source_groups'); ?>
      </div>
    <span class="field-desc"><?php print _txt('pl.entrasource.use_source_groups.desc'); ?></span>
    </div>
    <div class="field-info">
      <?php
        if($e) {
          print $this->Form->input('use_source_groups');

          if($this->Form->isFieldError('use_source_groups')) {
            print $this->Form->error('use_source_groups');
          }
        } else {
          print filter_var($entra_sources[0]['EntraSource']['use_source_groups'] , FILTER_SANITIZE_SPECIAL_CHARS);
        }
      ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print _txt('pl.entrasource.source_group_filter'); ?>
      </div>
      <span class="field-desc"><?php print _txt('pl.entrasource.source_group_filter.desc'); ?></span>
    </div>
    <div class="field-info">
      <?php
        if($e) {
          print $this->Form->input('source_group_filter');

          if($this->Form->isFieldError('source_group_filter')) {
            print $this->Form->error('source_group_filter');
          }
        } else {
          print filter_var($entra_sources[0]['EntraSource']['source_group_filter'] , FILTER_SANITIZE_SPECIAL_CHARS);
        }
      ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print _txt('pl.entrasource.max_inventory_cache'); ?><br />
      </div>
      <span class="field-desc"><?php print _txt('pl.entrasource.max_inventory_cache.desc'); ?></span>
    </div>
    <div class="field-info">
      <?php
        if($e) {
          print $this->Form->input('max_inventory_cache');

          if($this->Form->isFieldError('max_inventory_cache')) {
            print $this->Form->error('max_inventory_cache');
          }
        } else {
          print filter_var($entra_sources[0]['EntraSource']['max_inventory_cache'] , FILTER_SANITIZE_SPECIAL_CHARS);
        }
      ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print _txt('pl.entrasource.unix_cluster_id'); ?><br />
      </div>
      <span class="field-desc"><?php print _txt('pl.entrasource.unix_cluster_id.desc'); ?></span>
    </div>
    <div class="field-info">
      <?php
        $attrs = array();
        $attrs['value'] = (isset($entra_sources[0]['EntraSource']['unix_cluster_id'])
                           ? $entra_sources[0]['EntraSource']['unix_cluster_id']
                           : "");
        $attrs['empty'] = true;

        if($e) {
          print $this->Form->select('unix_cluster_id',
                                    $vv_unix_clusters,
                                    $attrs);

          if($this->Form->isFieldError('unix_cluster_id')) {
            print $this->Form->error('unix_cluster_id');
          }
        } else {
          print filter_var($vv_unix_cluster_ids[ $entra_sources[0]['EntraSource']['unix_cluster_id'] ], FILTER_SANITIZE_SPECIAL_CHARS);
        }
      ?>
    </div>
  </li>

  <?php if($e): ?>
  <li class="fields-submit">
    <div class="field-name">
      <span class="required"><?php print _txt('fd.req'); ?></span>
    </div>
    <div class="field-info">
      <?php print $this->Form->submit($submit_label); ?>
    </div>
  </li>
  <?php endif; ?>
</ul>
