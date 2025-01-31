<?php
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
  $args[] = $vv_entra_source['OrgIdentitySource']['id'];
  $this->Html->addCrumb($vv_entra_source['OrgIdentitySource']['description'], $args);

  $args = array();
  $args['plugin'] = 'entra_source';
  $args['controller'] = 'entra_sources';
  $args['action'] = 'edit';
  $args[] = $vv_entra_source['EntraSource']['id'];
  $this->Html->addCrumb(_txt('op.config'), $args);

  $this->Html->addCrumb(_txt('ct.entra_source_extension_properties.pl'));

  // Add page title
  $params = array();
  $params['title'] = $title_for_layout;

  // Add top links
  $params['topLinks'] = array();

  if($permissions['add']) {
    $params['topLinks'][] = $this->Html->link(
      _txt('op.add-a', array(_txt('ct.entra_source_extension_properties.1'))),
      array(
        'plugin'     => 'entra_source',
        'controller' => 'entra_source_extension_properties',
        'action'     => 'add',
        'esid'       => $vv_entra_source['EntraSource']['id']
      ),
      array('class' => 'addbutton')
    );
  }
  
  print $this->element("pageTitleAndButtons", $params);
?>

<div class="table-container">
  <table id="entra_source_extension_properties">
    <thead>
      <tr>
        <th><?php print $this->Paginator->sort('description', _txt('fd.desc')); ?></th>
        <th><?php print _txt('fd.actions'); ?></th>
      </tr>
    </thead>

    <tbody>
      <?php $i = 0; ?>
      <?php foreach ($entra_source_extension_properties as $p): ?>
      <tr class="line<?php print ($i % 2)+1; ?>">
        <td>
          <?php
            print $this->Html->link($p['EntraSourceExtensionProperty']['property'],
                                    array(
                                      'plugin' => 'entra_source',
                                      'controller' => 'entra_source_extension_properties',
                                      'action' => ($permissions['edit'] ? 'edit' : ($permissions['view'] ? 'view' : '')),
                                      $p['EntraSourceExtensionProperty']['id']));
          ?>
        </td>
        <td>
          <?php
            if($permissions['edit']) {
              print $this->Html->link(
                _txt('op.edit'),
                array(
                  'plugin' => 'entra_source',
                  'controller' => 'entra_source_extension_properties',
                  'action' => 'edit',
                  $p['EntraSourceExtensionProperty']['id']
                ),
                array('class' => 'editbutton')
              ) . "\n";
            }

            if($permissions['delete']) {
              print '<button type="button" class="deletebutton" title="' . _txt('op.delete')
                . '" onclick="javascript:js_confirm_generic(\''
                . _txt('js.delete') . '\',\''    // dialog body text
                . $this->Html->url(              // dialog confirm URL
                  array(
                    'plugin' => 'entra_source',
                    'controller' => 'entra_source_extension_properties',
                    'action' => 'delete',
                    $p['EntraSourceExtensionProperty']['id'],
                    'esid' => $p['EntraSourceExtensionProperty']['entra_source_id']
                  )
                ) . '\',\''
                . _txt('op.delete') . '\',\''    // dialog confirm button
                . _txt('op.cancel') . '\',\''    // dialog cancel button
                . _txt('op.delete') . '\',[\''   // dialog title
                . filter_var(_jtxt($p['EntraSourceExtensionProperty']['property']),FILTER_SANITIZE_STRING)  // dialog body text replacement strings
                . '\']);">'
                . _txt('op.delete')
                . '</button>';
            }
          ?>
          <?php ; ?>
        </td>
      </tr>
      <?php $i++; ?>
      <?php endforeach; ?>
    </tbody>

  </table>
</div>

<?php
  print $this->element("pagination");