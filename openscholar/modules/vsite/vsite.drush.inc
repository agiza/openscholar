<?php

/**
 * @file
 * Drush functionality file for the vsite module.
 */


/**
 * Implements hook_drush_command().
 */
function vsite_drush_command() {
  $vrd_help = "Disable a vsite custom url(VRD - vsite redirect disable).\n";
  $vrd_help .= "After disabling the custom URLs you'll need to set the variable purl_base_domain to your local machine address. ";
  $vrd_help .= "You can use use the 'drush vset' command for that.";
  $items['vsite-redirect-disable'] = array(
    'description' => dt($vrd_help),
    'options' => array(
      'nid' => dt('The nid of the vsite.'),
      'no-purl-warning' => dt('Hide the confirmation about purl_base_domain'),
    ),
    'examples' => array(
      'drush vrd --nid=1' => dt('Delete the url for the vsite with the 1 identifier'),
      'drush vrd' => dt('Disable all of the custom URL.'),
    ),
    'aliases' => array('vrd'),
  );
  $items['vsite-redirect-list'] = array(
    'description' => dt('List custom vsite URLs.'),
    'options' => array(),
    'examples' => array('drush vrl'),
    'aliases' => array('vrl'),
  );
  return $items;
}

/**
 * Return a list of custom vsite URLs
 */
function drush_vsite_redirect_list() {
  $query = db_select('purl')
    ->fields('purl', array('value', 'id'))
    ->condition('provider', 'vsite_domain');
  $query->join('node', 'n', 'n.nid = purl.id');
  $query->fields('n', array('title'));
  $results = $query->execute();

  foreach ($results->fetchAllAssoc('id') as $result) {
    drush_print(dt('@id: @domain -> @title', array(
      '@title' => $result->title,
      '@id' => $result->id,
      '@domain' => $result->value,
    )));
  }
}

/**
 * Command callback.
 */
function drush_vsite_redirect_disable() {
  $nid = drush_get_option('nid', 0);

  $spaces_query = db_delete('spaces_overrides')
    ->condition('object_type', 'variable')
    ->condition('object_id', 'vsite_domain_name');

  $purl_query = db_delete('purl')
    ->condition('provider', 'vsite_domain');

  if ($nid) {
    $spaces_query->condition('id', $nid);
    $purl_query->condition('id', $nid);
  }

  $spaces_query->execute();
  $purl_query->execute(); 

  if ($nid) {
    // Get the vsite path.
    $result = db_select('purl')
      ->fields('purl', array('value'))
      ->condition('provider', 'spaces_og')
      ->condition('id', $nid)
      ->execute()
      ->fetchAssoc();
  }

  if (!(drush_get_option('no-purl-warning', FALSE))) {
    // Asking the user if he would like to change the purl_base_domain variable.
    if (drush_confirm(dt('Would you like to change the purl base domain variable value?'))) {
      $purl_base_domain = drush_prompt(dt('Please enter the new purl base domain of your OS installation'));
      variable_set('purl_base_domain', $purl_base_domain);
      drush_log(dt("The variable 'purl_base_domain' has changed to @domain", array('@domain' => $purl_base_domain)), 'success');
    }
  }

  // Clearing the cache for the change to take place.
  drupal_flush_all_caches();

  if ($nid) {
    $node = node_load($nid);
    $params = array(
      '@name' => $node->title,
      '@address' => variable_get('purl_base_domain') . '/' . $result['value'],
    );
    drush_log(dt('The custom url for @name has disabled. The address is @address', $params), 'success');
  }
  else {
    drush_log(dt('All of the custom URLs were deleted.'), 'success');
  }

}
