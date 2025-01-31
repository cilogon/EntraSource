<?php
  if(!$this->action != 'add') {
    $params['topLinks'][] = $this->Html->link(
      _txt('op.manage-a', array(_txt('ct.entra_source_extension_properties.pl'))),
      array(
        'plugin'        => 'entra_source',
        'controller'    => 'entra_source_extension_properties',
        'action'        => 'index',
        'esid'          => $entra_sources[0]['EntraSource']['id']
      ),
      array(
        'class'   => 'editbutton'
      )
    );
  }
