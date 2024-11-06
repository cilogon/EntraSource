<?php
/**
 */
  
global $cm_lang, $cm_texts;

// When localizing, the number in format specifications (eg: %1$s) indicates the argument
// position as passed to _txt.  This can be used to process the arguments in
// a different order than they were passed.

$cm_entra_source_texts['en_US'] = array(
  // Titles, per-controller
  'ct.entra_sources.1'  => 'Entra Organizational Identity Source',
  'ct.entra_sources.pl' => 'Entra Organizational Identity Sources',
  
  // Error messages
  'er.entrasource.access_token.unable' => 'Unable to obtain new access token', 
  'er.entrasource.api.code' => 'Microsoft Graph API returned code %1$s',

  'er.apisource.kafka.meta'     => 'Invalid value for metadata attribute %1$s at offset %2$s (found "%3$s", expecting "%4$s")',
  'er.apisource.kafka.json'     => 'Invalid JSON at offset %1$s',
  'er.apisource.kafka.sorid'    => 'No SORID in message at offset %1$s',
  'er.apisource.role.id'        => 'Role does not include roleIdentifier',
  'er.apisource.sorid.notfound' => 'No record found for specified SORID',
  
  // Plugin texts
  'pl.entrasource.access_token_server_id' => 'Access Token Server',
  'pl.entrasource.access_token_server_id.desc' => 'The server consuming client credentials and issuing an access token',
  'pl.entrasource.api_server_id' => 'Microsoft Graph API Server',
  'pl.entrasource.api_server_id.desc' => 'The server configured for the Microsoft Graph API endpoint',
  'pl.entrasource.use_source_groups' => 'Select Using Groups',
  'pl.entrasource.use_source_groups.desc' => 'Select the set of users using Entra groups',


  'pl.apisource.api_user.desc'    => 'The API User authorized to make requests to this endpoint, leave blank to disable Push Mode',
  'pl.apisource.info'             => 'The API endpoint for using this plugin in Push Mode is %1$s',
  'pl.apisource.job'              => 'Run API Source Polling',
  'pl.apisource.job.poll.eof'     => 'No further messages available to be processed',
  'pl.apisource.job.poll.finish'  => 'API Source Poll Job completed, processed %1$s messages (%2$s success, %3$s error)',
  'pl.apisource.job.poll.id'      => 'API Source ID to poll',
  'pl.apisource.job.poll.max'     => 'Maximum number of loops to try (default is 10)',
  'pl.apisource.job.poll.msg'     => 'Processed message at offset %1$s',
  'pl.apisource.job.poll.start'   => 'Polling for new records (mode %1$s)',
  'pl.apisource.mode.poll'        => 'Poll Mode',
  'pl.apisource.mode.poll.desc'   => 'The messaging technology to use for polling, leave blank to disable Poll Mode',
  'pl.apisource.mode.push'        => 'Push Mode',
  'pl.apisource.servers.none'     => 'There are no defined Kafka servers to use for this provisioner.',
  'pl.apisource.sor'              => 'SOR Label',
  'pl.apisource.sor.desc'         => 'Alphanumeric label for the API Client/System of Record (will become part of the URL or message metadata)',
);

