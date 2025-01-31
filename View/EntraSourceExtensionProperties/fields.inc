<?php
  // Determine if fields are editable
  $e = false;
  
  if(($this->action == "add" && $permissions['add']) || ($this->action == "edit" && $permissions['edit']))
    $e = true;
    
  // We shouldn't get here if we don't have at least read permission, but check just in case
  
  if(!$e && !$permissions['view'])
    return(false);

  // Add breadcrumbs
  print $this->element("coCrumb");
  $args = array();
  $args['plugin'] = null;
  $args['controller'] = 'org_identity_sources';
  $args['action'] = 'index';
  $args['co'] = $vv_entra_source['OrgIdentitySource']['co_id'];
  $this->Html->addCrumb(_txt('ct.org_identity_sources.pl'), $args);

  $args = array();
  $args['plugin'] = null;
  $args['controller'] = 'org_identity_sources';
  $args['action'] = 'edit';
  $args[] = $vv_entra_source['OrgIdentitySource']['id'];
  $this->Html->addCrumb($vv_entra_source['OrgIdentitySource']['description'], $args);

  $args = array();
  $args['plugin'] = 'entra_source';
  $args['controller'] = 'entra_sources';
  $args['action'] = 'edit';
  $args[] = $vv_entra_source['EntraSource']['id'];
  $this->Html->addCrumb(_txt('op.config'), $args);

  $args = array();
  $args['plugin'] = 'entra_source';
  $args['controller'] = 'entra_source_extension_properties';
  $args['action'] = 'index';
  $args[] = 'esid:' . $vv_esid;
  $this->Html->addCrumb(_txt('ct.entra_source_extension_properties.pl'), $args);
  
  $crumbTxt = _txt('op.' . $this->action . '-a', array(_txt('ct.entra_source_extension_properties.1')));
  $this->Html->addCrumb($crumbTxt);

  if($this->action != 'view') {
    // Populate cross references
    print $this->Form->hidden('entra_source_id', array('default' => $vv_entra_source['EntraSource']['id'])) . "\n";
  }
?>
<ul id="<?php print $this->action; ?>_entra_source_extension_properties" class="fields form-list">
  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print _txt('pl.entrasource.extension.property'); ?>
        <span class="required">*</span>
      </div>
      <span class="field-desc"><?php print _txt('pl.entrasource.extension.property.desc'); ?></span>
    </div>
    <div class="field-info">
      <?php
        if($e) {
          print $this->Form->input('property');

          if($this->Form->isFieldError('property')) {
            print $this->Form->error('property');
          }
        } else {
          print filter_var($entra_source_extension_properties[0]['EntraSourceExtensionProperty']['property'] , FILTER_SANITIZE_SPECIAL_CHARS);
        }
      ?>
    </div>
  </li>

  <li>
    <div class="field-name">
      <div class="field-title">
        <?php print _txt('pl.entrasource.extension.type'); ?>
        <span class="required">*</span>
      </div>
      <span class="field-desc"><?php print _txt('pl.entrasource.extension.type.desc'); ?></span>
    </div>
    <div class="field-info">
      <?php
        $attrs = array();
        $attrs['value'] = (isset($entra_source_extension_properties[0]['EntraSourceExtensionProperty']['identifier_type'])
                           ? $entra_source_extension_properties[0]['EntraSourceExtensionProperty']['identifier_type']
                           : "");
        $attrs['empty'] = true;
        
        if($e) {
          print $this->Form->select('identifier_type',
                                    $vv_identifier_types,
                                    $attrs);

          if($this->Form->isFieldError('identifier_type')) {
            print $this->Form->error('identifier_type');
          }
        } else {
          print filter_var($vv_identifier_types[ $entra_source_extension_properties[0]['EntraSourceExtensionProperty']['identifier_type'] ], FILTER_SANITIZE_SPECIAL_CHARS);
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