<?php
/**
 * @file
 * os_image_gallery.context.inc
 */

/**
 * Implements hook_context_default_contexts().
 */
function os_image_gallery_context_default_contexts() {
  $export = array();

  $context = new stdClass();
  $context->disabled = FALSE; /* Edit this to true to make a default context disabled initially */
  $context->api_version = 3;
  $context->name = 'gallery_gallery';
  $context->description = 'Image gallery or image page';
  $context->tag = 'OS features';
  $context->conditions = array(
    'menu' => array(
      'values' => array(
        'galleries' => 'galleries',
      ),
    ),
    'node' => array(
      'values' => array(
        'media_gallery' => 'media_gallery',
      ),
      'options' => array(
        'node_form' => '0',
      ),
    ),
    'path' => array(
      'values' => array(
        'galleries' => 'galleries',
        'galleries/*' => 'galleries/*',
      ),
    ),
    'views' => array(
      'values' => array(
        'os_image_gallery' => 'os_image_gallery',
        'os_image_gallery_slideshow' => 'os_image_gallery_slideshow',
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
          'weight' => '-54',
        ),
        'boxes-os_image_gallery_latest' => array(
          'module' => 'boxes',
          'delta' => 'os_image_gallery_latest',
          'region' => 'sidebar_second',
          'weight' => '-53',
        ),
        'boxes-os_addthis' => array(
          'module' => 'boxes',
          'delta' => 'os_addthis',
          'region' => 'sidebar_second',
          'weight' => '-52',
        ),
      ),
    ),
  );
  $context->condition_mode = 0;

  // Translatables
  // Included for use with string extractors like potx.
  t('Image gallery or image page');
  t('OS features');
  $export['gallery_gallery'] = $context;

  return $export;
}