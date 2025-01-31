<?php
/**
 */
  
global $cm_lang, $cm_texts;

// When localizing, the number in format specifications (eg: %1$s) indicates the argument
// position as passed to _txt.  This can be used to process the arguments in
// a different order than they were passed.

$cm_entra_source_texts['en_US'] = array(
  // Titles, per-controller
  'ct.entra_sources.1'                      => 'Entra Organizational Identity Source',
  'ct.entra_sources.pl'                     => 'Entra Organizational Identity Sources',
  'ct.entra_source_extension_properties.1'  => 'Schema Extension Property',
  'ct.entra_source_extension_properties.pl' => 'Schema Extension Properties',
  
  // Error messages
  'er.entrasource.access_token.unable'       => 'Unable to obtain new access token',
  'er.entrasource.api.code'                  => 'Microsoft Graph API returned code %1$s',
  'er.entrasource.api.throttled'             => 'Microsoft Graph API returned throttling code 429',
  'er.entrasource.api.throttled.retry'       => 'Microsoft Graph API Retry-After header is %1$s',
  'er.entrasource.api.throttled.retry.error' => 'Could not determine Microsoft Graph API Retry-After header so using 5',
  'er.entrasource.api.throttled.sleep'       => 'Sleeping for %1$s seconds now',
  'er.entrasource.api.throttled.sleep.awake' => 'Done sleeping for %1$s seconds',

  // Plugin texts
  'pl.entrasource.access_token_server_id'      => 'Access Token Server',
  'pl.entrasource.access_token_server_id.desc' => 'The server consuming client credentials and issuing an access token',
  'pl.entrasource.api_server_id'               => 'Microsoft Graph API Server',
  'pl.entrasource.api_server_id.desc'          => 'The server configured for the Microsoft Graph API endpoint',
  'pl.entrasource.max_inventory_cache'         => 'Maximum inventory cache lifetime',
  'pl.entrasource.max_inventory_cache.desc'    => 'The maximum inventory cache validity time in minutes',
  'pl.entrasource.search.mail'                 => 'Email address including domain',
  'pl.entrasource.source_group_filter'         => 'Entra group filter query parameter',
  'pl.entrasource.source_group_filter.desc'    => 'The OData V4 query language $filter query parameter value used to select the set of Entra groups',
  'pl.entrasource.unix_cluster_id'             => 'Unix Cluster',
  'pl.entrasource.unix_cluster_id.desc'        => 'The Unix Cluster with which to associate groups for POSIX groups',
  'pl.entrasource.use_source_groups'           => 'Select Using Groups',
  'pl.entrasource.use_source_groups.desc'      => 'Select the set of users using Entra groups',
  'pl.entrasource.extension.property'          => 'Property',
  'pl.entrasource.extension.property.desc'     => 'The full name of the schema extension property',
  'pl.entrasource.extension.type'              => 'Identifier Type',
  'pl.entrasource.extension.type.desc'         => 'The Identifier type this property will be mapped to',
);
