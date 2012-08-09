<?php

/**
 * @file layout.inc
 *
 * Provides layout management and crud functions
 *
 * os_layout_get_contexts
 * Return an array of all the contexts avalible (optionally) filtered by privacy
 *
 * os_layout_get
 * Return an array of block settings for a single context
 *
 * os_layout_get_multiple
 * Returns an array of block settings for multiple contexts
 *
 * os_layout_set
 * Saves block settings for a single context
 *
 * os_layout_set_multiple
 * Saves and array of block settings for multiple contexts
 *
 * os_layout_put
 * Helper function to add a single block to a layout
 *
 * os_layout_load_block
 * Given a blocks full meta-data including config paths and access parameters
 *
 * os_layout_block_delete
 * Given a bid remove all instances of that block from the layout system
 *
 */

/**
 * Return the public non-admin contexts that are avalible in a site
 *
 * @param $privacy and array (or single value)
 * (0 -> disabled, 2 -> private, 1 -> public)
 * TRUE returns all
 */
function os_layout_get_contexts($privacy = array(1,2)){

  if (! is_array($privacy)) {
    if (! $privacy) {
      //If false was passed then return no contexts
      return array();
    }
  }

  $os_contexts = array();
  foreach ( module_implements('os_layout_contexts') as $module ) {
    $contexts = module_invoke($module, 'os_layout_contexts');
    foreach ( $contexts as $id => $name ) {
      $os_contexts[$id] = $name;
    }
  }

  drupal_alter('os_layout_contexts_alter',$os_contexts);

  //Check privacy settings if needed
  if (is_array($privacy)) {
//    //Retrieve the features that match the privacy settings
//    $avalible_features = array_intersect(variable_get('spaces_features', array()), $privacy);
//
//    //Map contexts to features
//    $context_map = features_get_component_map('context');
//    foreach ( $os_contexts as $value ) {
//      ;
//    }
  }

  return $os_contexts;
}

/**
 * Returns the blocks for a individual context
 *
 * @param string $context
 * The identifier string for a context
 *
 * @param $load_meta
 * Add meta-data used by admin forms
 *
 * @param $unused_blocks
 * Add all unused site blocks
 *
 * @return array
 * List of blocks
 */
function os_layout_get($context, $load_meta = false, $unused_blocks = false){
  /**
   * Context load alters the context (for vsites) so no need to do anything extra
   */
  $o_context = context_load($context);

  if (! is_object($o_context) || ! isset($o_context->reactions['block'])) {
    //This was an invalid context
    return false;
  }

  if (array_key_exists('blocks', $o_context->reactions['block'])) {
    $block_settings = $o_context->reactions['block']['blocks'];
  }
  else {
    //No blocks set yet for this context
    $block_settings = array();
  }

  if ($unused_blocks) {
    $site_blocks = module_invoke_all('os_widget');
    foreach ( $site_blocks as $bid => $block ) {
      if (! isset($block_settings[$bid])) {
        $block['region'] = false;
        $block_settings[$bid] = $block;
      } //Add unused block
    }
  }

  if ($load_meta) {
    foreach ( $block_settings as $bid => $block ) {
      $block_settings[$bid] = os_layout_load_block($bid, true, $block);
    }
  }

  // ensure blocks all have the required fields
  $required = array(
      'region' => false,
      'status' => 0,
      'title' => '',
      'weight' => 0
  );
  foreach ( $block_settings as &$b ) {
    $b += $required;
  }

  return $block_settings;
}

/**
 * Returns the blocks for multiple contexts
 * with child contexts overriding the parents
 *
 * @param array $contexts
 * A list of context identifiers to load and aggregate
 *
 * @param $load_meta
 * Add meta-data used by admin forms
 *
 * @param $unused_blocks
 * Add all unused site blocks
 *
 * @return array
 * List of blocks
 */
function os_layout_get_multiple($contexts, $load_meta = false, $unused_blocks = false){
  usort($contexts, '_os_context_sort_by_hierarchy');

  $context_blocks = array();

  //Retrieve all of the blocks
  foreach ( $contexts as $context ) {
    //Merge the blocks with child blocks replacing parent blocks
    $blocks = os_layout_get($context, $load_meta, $unused_blocks);

    //If this was true then the unused blocks were all added
    //on the first run, there is no need to keep getting them
    //also this prevents un-intended overrides
    $unused_blocks = false;
    
    if (is_array($blocks)) {
      $context_blocks = array_merge($context_blocks, $blocks);
    }
  }

  return $context_blocks;
}

/**
 * Saves an array blocks for a individual context
 *
 * @param string $context
 * @param array $blocks
 *
 * @return boolean Success
 */
function os_layout_set($context, $blocks){
  _os_layout_sanitize_blocks($blocks);
  if (module_exists('vsite_layout')) {
    //Write the context to the overrides table
    vsite_layout_context_set($context, $blocks);
  }
  else {
    $o_context = context_load($context);
    $o_context->reactions['block']['blocks'] = $blocks;
    //write the context to the database
    context_save($o_context);
  }
}

/**
 * Reverts a individual context back to defaults
 *
 * @param string $context
 *
 * @return boolean Success
 */
function os_layout_revert($context){
  
  if (module_exists('vsite_layout')) {
    //Write the context to the overrides table
    vsite_layout_context_revert($context);
    return true;
  }
  else {
    //No overrides in single tenant, nothing to do
    return false;
  }
}

/**
 * Saves an array blocks to multiple contexts, resolving weight issues and deciding what overrides need to be made
 *
 * @param array $contexts
 * @param array $blocks
 * An array of blocks to save to the contexts with the correct block keys
 *
 * @return boolean Success
 */
function os_layout_set_multiple($contexts, $blocks){
  usort($contexts, '_os_context_sort_by_hierarchy');
  uasort($blocks, 'os_layout_block_sort');

  $removed_blocks = array();

  //Fields to check for changes
  $block_fields = array(
      'region',
      'status',
      'title',
      'weight'
  );

  $context_settings = array();
  //Resolve dependencies and alter the contexts accordingly
  foreach ( $contexts as $index => $context ) {
    $layout_blocks = os_layout_get($context);
    // TODO: handle case when $layout_blocks is false
    //Sort the blocks
    uasort($layout_blocks, 'os_layout_block_sort');

    $last_child = (count($contexts) == ($index + 1));

    //Get the removed blocks that no longer exist from this context
    $missing_blocks = array_diff(array_keys($layout_blocks), array_keys($blocks));

    //check common blocks for differences)
    foreach ( array_intersect_key($blocks, $layout_blocks) as $bid => $block ) {
      $changed = false;
      foreach ( $block_fields as $key ) {
        if ($layout_blocks[$bid][$key] != $blocks[$bid][$key]) {
          /**
           * @todo Adapt block weights and check for removed bocks
           */
          switch ($key) {
            case 'weight' :
              //If this is the childmost context, no need to adjust weights, just save them,
              //same with if we need to save an override for another reason
              if ($last_child || $changed)
                break;
              _os_layout_adjust_relative_weight($blocks, $layout_blocks, $bid);
              //Check to see if the weight has been reset
              $changed = ($layout_blocks[$bid][$key] != $blocks[$bid][$key]);
              break;
            default :
              //Nothing to do
              $changed = true;
              break;
          }
        }
      }
      //Not changed take no action
      if (! $changed && ! $last_child) {
        $blocks[$bid]['default_matched'] = true;
      }else{
        $blocks[$bid]['default_matched'] = false;
      }
    }

    //If this context has a child context, queue up blocks to be set as removed in child
    if (! $last_child) {
      $removed_blocks = array_merge($missing_blocks, $removed_blocks);
    }
    else {
      //This is the childmost context, add the blocks as regionless to override as removed
      foreach ( $removed_blocks as $bid ) {
        //Add this block without a region
        $layout_blocks[$bid] = os_layout_load_block($bid, false, array(
            'region' => false
        ));
      }
    }

    $context_settings[$context] = $layout_blocks;
  }

  foreach ( $blocks as $bid => $block ) {
    //Remove those blocks that are represented in parents
    if (!empty($block['default_matched'])) {
      unset($blocks[$bid]);
    }
  }

  //Remaining block overrides belong in the child-most context
  $context_settings[$context] = array_merge($context_settings[$context], $blocks);

  //Commit the context settings
  foreach ( $context_settings as $context => $layout_blocks ) {
    os_layout_set($context, $layout_blocks);
  }
}

/**
 * Adds/Updates an individual block within a named context
 *
 * @param array $block
 * @param string $context
 *
 * @return boolean Success
 */
function os_layout_put($block, $context){
  $context_blocks = os_layout_get($context);
  //Valid Context?
  if (is_array($context_blocks)) {
    //Add new block settings
    $context_blocks[$block['module'] . '-' . $block['delta']] = $block;

    os_layout_set($context, $context_blocks);
  }
}

/**
 *
 * @param string $bid
 * Block identifier [os-primary-menu]
 *
 * @param boolean $load_info
 * Load Block meta-data used by admin forms
 *
 * @return array $block
 */
function os_layout_load_block($bid, $load_meta = true, $block = array()){

  list($module, $delta) = explode('-', $bid, 2);

  if (! isset($block['module'], $block['delta'])) {
    $db_block = block_load($module, $delta);
    $block = array_merge($db_block, $block);
  }

  if ($load_meta) {
    //Add standard information gleaned from the block_info hook
    $info_hook = "{$module}_block_info";
    if (function_exists($info_hook)) {
      $block_info = $info_hook();
      if (isset($block_info[$delta])) {
        $block = array_merge($block_info[$delta], $block);
      }
    }

    //Populate block with the defaults from the schema
    $block_schema = drupal_get_schema('block');

    foreach ( $block_schema['fields'] as $field => $info ) {
      //If a default exists populate that
      if (! isset($block[$field]) && isset($info['default'])) {
        $block[$field] = $info['default'];
      }
    }

    //Get the blocks current title by rendering it
    if (! isset($block['title'])) {
      $render_hook = "{$module}_block_view";
      $block_render = $render_hook();
      $block['title'] = $block_render['title'];
    }

    drupal_alter('os_widget', $block);
  }

  return $block;
}

/**
 * Remove a block from one or more contexts
 *
 * @param string $bid
 * Block identifier [os-primary-menu]
 *
 * @param array/string $contexts
 * An array of contexts or individual context to delete a block from, defaults to all contexts
 */
function os_layout_block_delete($bid, $contexts = false){
  if(!$contexts){
    $contexts = os_layout_get_contexts(true);
  }elseif (!is_array($contexts)){
    $contexts = array($contexts);
  }

  //Remove from each context
  foreach ($contexts as $context => $title) {
    $blocks = os_layout_get($context);
    $set = false;
    if (isset($blocks[$bid])){
      unset($blocks[$bid]);
      $set = true;
    }
    if ($set) {
      os_layout_set($context, $blocks);
    }

  }

  return true;
}

/**
 * Return a context ranking
 *
 * @todo Add weight to context table
 */
function _os_context_get_rank($context){
  $context_rank = drupal_static(__FUNCTION__, array());

  if (! isset($context_rank[$context])) {
    $o_context = context_load($context);
    if (isset($o_context->weight)) {
      $context_rank[$context] = $o_context->weight;
    }
    else {

      //Global Special Contexts
      if (in_array($context, array(
          'os_public',
          'os_cp'
      ))) {
        $context_rank[$context] = 1;
        return $context_rank[$context];
      }

      $map = features_get_component_map('context');
      if (array_key_exists($context, $map)) {
        //Feature context
        $context_rank[$context] = 2;
      }
      else {
        //Page or other non-feature context
        $context_rank[$context] = 3;
      }
    }
  }

  return $context_rank[$context];
}

/**
 * Sort a list of contexts by hierarchy
 *
 * @param string $context1
 * @param string $context2
 */
function _os_context_sort_by_hierarchy($context1, $context2){
  $a = _os_context_get_rank($context1);
  $b = _os_context_get_rank($context2);

  if ($a == $b) {
    return 0;
  }
  return ($a < $b) ? - 1 : 1;
}

/**
 * Adjust the weights of blocks in multiple contexts
 *
 * @param array $block_settings
 * New block settings (already sorted)
 * @param array $previous_layout
 * Layout to compare against (already sorted)
 * @param string $bid
 * The block in question with the changed weight
 *
 */
function _os_layout_adjust_relative_weight(&$block_settings, $previous_layout, $bid){

  //Has the relative order changed
  if (array_keys(array_intersect_key($block_settings, $previous_layout)) != array_keys(array_intersect_key($previous_layout, $block_settings))) {
    return;
  }

  //Loop through the old array
  $w_new = $block_settings[$bid]['weight'];
  $w_old = $previous_layout[$bid]['weight'];
  //Blocks that need thier weight adjusted
  $impacted_blocks = array();
  $range_begin = false;
  $range_end = false;

  foreach ( $block_settings as $id => $block ) {
    //Skip this block if there is no weight setting OR they are not in the same region
    if (! isset($block['weight']) || $block_settings[$bid]['region'] != $block['region'])
      continue;

    if ($block['weight'] < min($w_new, $w_old)) {
      $range_begin = $block['weight'];
      continue;
    }
    elseif ($block['weight'] > max($w_new, $w_old)) {
      $range_end = $block['weight'];
      break;
    }

    if (!empty($block['default_matched'])) {
      //One of the blocks that we would have to adjust to reconcile this weight is
      //owned by a parent context, so this is an override
      return;
    }

    if ($id != $bid) {
      $impacted_blocks[] = $id;
    }
  }

  //Its ok to change it's weight
  $block_settings[$bid]['weight'] = $w_old;

  //No other blocks impacted
  if (! count($impacted_blocks))
    return;

  if ($w_old < $w_new) {
    //Decreasing weight
    $increment = is_bool($range_begin) ? - 10 : ($range_begin - $w_old) / count($impacted_blocks);
  }
  else {
    //Increasing weight
    $increment = is_bool($range_end) ? 10 : ($range_end - $w_old) / count($impacted_blocks);
  }

  foreach ( $impacted_blocks as $key => $impacted_bid ) {
    $block_settings[$impacted_bid]['weight'] = $w_old + ($increment * ($key + 1));
  }
}

/**
 * Remove fields from blocks that are not part of the block schema
 *
 * @param array $blocks
 */
function _os_layout_sanitize_blocks(&$blocks){

  $block_schema = drupal_get_schema('block');

  foreach ( $blocks as $bid => $block ) {
    $blocks[$bid] = array_intersect_key($block, $block_schema['fields']);
  }

}

/**
 * Sort callback.
 */
function os_layout_block_sort($a, $b){
  $aw = is_object($a) ? $a->weight : $a['weight'];
  $bw = is_object($b) ? $b->weight : $b['weight'];
  return ($aw - $bw);
}