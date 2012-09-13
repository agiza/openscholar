<?php
// $Id$


/**
 * @file
 * os_biocv.context.inc
 */

/**
 * Implements hook_context_default_contexts().
 */
function os_biocv_context_default_contexts() {
  $export = array();

  $context = new stdClass();
  /* Edit this to true to make a default context disabled initially */
  $context->disabled = FALSE;
  $context->api_version = 3;
  $context->name = 'biocv_biocv';
  $context->description = '';
  $context->tag = 'OS features';
  $context->conditions = array(
    'node' => array(
      'values' => array(
        'bio' => 'bio',
        'cv' => 'cv',
      ),
      'options' => array(
        'node_form' => '1',
      ),
    ),
    'path' => array(
      'values' => array(
        'biocv' => 'biocv',
        'biocv/*' => 'biocv/*',
        'bio' => 'bio',
        'bio/*' => 'bio/*',
      ),
    ),
  );
  $context->reactions = array(
    'block' => array(
      'blocks' => array(
        'boxes-os_taxonomy_fltr_cat' => array(
          'module' => 'boxes',
          'delta' => 'os_taxonomy_fltr_cat',
          'region' => 'sidebar_second',
          'weight' => '-10',
        ),
        'boxes-os_biocv_bio' => array(
          'module' => 'os_biocv',
          'delta' => 'bio',
          'region' => 'content_top',
          'weight' => '-10',
        ),
      ),
    ),
  );
  $context->condition_mode = 0;

  // Translatables
  // Included for use with string extractors like potx.
  t('OS features');
  $export['biocv_biocv'] = $context;

  return $export;
}
