<?php
/**
 * Preprocess and Process Functions SEE: http://drupal.org/node/254940#variables-processor
 * 1. Rename each function and instance of "adaptivetheme_subtheme" to match
 *    your subthemes name, e.g. if your theme name is "footheme" then the function
 *    name will be "footheme_preprocess_hook". Tip - you can search/replace
 *    on "adaptivetheme_subtheme".
 * 2. Uncomment the required function to use.
 */

/**
 * add classes to the body of a page
 */
function os_basetheme_preprocess_html(&$vars) {
  if (isset($vars['page']['menu_bar'])) {
    $vars['classes_array'][] = 'navbar-on';
  }
  else {
    $vars['classes_array'][] = 'navbar-off';
  }

}

/**
 * Adds classes to the page element
 */
function os_basetheme_preprocess_page(&$vars) {
  $item = menu_get_item();

  //Adds OpenScholar header region awareness to body classes
  $header = array(
    'header-left' => $vars['page']['header_second'],
    'header-main' => $vars['page']['header_first'],
    'header-right' => $vars['page']['header_third'],
  );
  $content = array(
    'content-top' => $vars['page']['content_top'],
    'content-left' => $vars['page']['content_first'],
    'content-right' => $vars['page']['content_second'],
    'content-bottom' => $vars['page']['content_bottom'],
  );
  $footer = array(
    'footer-left' => $vars['page']['footer_first'],
    'footer' => $vars['page']['footer'],
    'footer-right' => $vars['page']['footer_third'],
  );
  foreach (array('header', 'content', 'footer') as $var) {
    $visible = array_filter($$var, "__os_basetheme_is_empty");
    if (count($visible)) {
      $vars['classes_array'] = array_merge($vars['classes_array'], array_keys($visible));
     }
    else {
      $vars['classes_array'][] = $var.'-none';
    }
  }

  if (module_exists('overlay') && overlay_get_mode() == 'child') {
    // overlay does this, but adaptive theme renders them in a different way that overlay doesn't expect
    $vars['primary_local_tasks'] = $vars['title'] = false;
  }

  if (!isset($vars['use_content_regions'])) {
    $vars['use_content_regions'] = false;
  }

  // Do not show the login button on the following pages, redundant.
  $login_pages = array(
  'user',
  'private_site',
  'user/password'
  );
  if(isset($item) && !in_array($item['path'], $login_pages)) {
    $vars['login_link'] = theme('openscholar_login');
  }

  //hide useless tabs - drupal uses $vars['tabs'], but adaptive loads primary and secondary menu local tasks.
  $vars['primary_local_tasks'] = $vars['tabs']['#primary'];
  $vars['secondary_local_tasks'] = $vars['tabs']['#secondary'];

  $theme_name = $GLOBALS['theme_key'];
  
  // Adds skip link var to page template
  $vars['skip_link'] = 'main-content';
  if (at_get_setting('skip_link_target', $theme_name)) {
    $skip_link_target = at_get_setting('skip_link_target', $theme_name);
    $vars['skip_link'] = trim(check_plain($skip_link_target), '#');
  }
}

/**
 * For header region classes
 */
function __os_basetheme_is_empty($s){
  return $s ? TRUE : FALSE;
}

/**
 * Hide adapativetheme's status message complaining about missing files
 */
function os_basetheme_preprocess_status_messages(&$vars) {
  if (isset($_SESSION['messages']['warning'])) {
    foreach ($_SESSION['messages']['warning'] as $k => $v) {
      if (strpos($v, 'One or more CSS files were not found') !== FALSE) {
        unset($_SESSION['messages']['warning'][$k]);
      }
    }

    if (count($_SESSION['messages']['warning']) == 0) {
      unset ($_SESSION['messages']['warning']);
    }
  }
}

function os_basetheme_preprocess_overlay(&$vars) {
  // we never want these. They look awful
 $vars['tabs'] = false;
}

/**
 * Returns HTML for a menu link and submenu.
 *
 * Adaptivetheme overrides this to insert extra classes including a depth
 * class and a menu id class. It can also wrap menu items in span elements.
 *
 * @param $vars
 *   An associative array containing:
 *   - element: Structured array data for a menu link.
 */
function os_basetheme_menu_link(array $vars) {
  $element = $vars['element'];
  $sub_menu = '';

  if ($element['#below']) {
    $sub_menu = drupal_render($element['#below']);
  }

  if (!empty($element['#original_link'])) {
    if (!empty($element['#original_link']['depth'])) {
      $element['#attributes']['class'][] = 'menu-depth-' . $element['#original_link']['depth'];
    }
    if (!empty($element['#original_link']['mlid'])) {
      $element['#attributes']['class'][] = 'menu-item-' . $element['#original_link']['mlid'];
    }
  }

  if (isset($element['#localized_options']) && !empty($element['#localized_options']['attributes']['title'])) {
    unset($element['#localized_options']['attributes']['title']);
  }

  $output = l($element['#title'], $element['#href'], $element['#localized_options']);
  return '<li' . drupal_attributes($element['#attributes']) . '>' . $output . $sub_menu . "</li>";
}

/**
 * Preprocess variables for comment.tpl.php
 */
function os_basetheme_preprocess_node(&$vars) {

  // Event nodes, inject variables for date month and day shield
  if ($vars['node']->type == 'event' && !$vars['page']) {
    $vars['event_start'] = array();
    $delta = 0;
    if (isset($vars['node']->date_id)) {
      list(,,, $delta,) = explode('.', $vars['node']->date_id . '.');
    }
    if (isset($vars['field_date'][$delta]['value']) && !empty($vars['field_date'][$delta]['value'])) {
 //     date_default_timezone_set($vars['field_date'][$delta]['timezone']);
      $event_start_date = strtotime($vars['field_date'][$delta]['value']);
      $vars['event_start']['month'] = check_plain(date('M', $event_start_date));
      $vars['event_start']['day'] = check_plain(date('d', $event_start_date));
      $vars['classes_array'][] = 'event-start';
    }
  }
}

/**
 * Returns HTML for a link
 *
 * Only change from core is that this makes href optional for the <a> tag
 */
function os_basetheme_link(array $variables) {
  $href = ($variables['path'] === false)?'':'href="' . check_plain(url($variables['path'], $variables['options'])) . '" ';
  return '<a ' . $href . drupal_attributes($variables['options']['attributes']) . '>' . ($variables['options']['html'] ? $variables['text'] : check_plain($variables['text'])) . '</a>';
}
