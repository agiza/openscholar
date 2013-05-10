<?php

use Drupal\DrupalExtension\Context\DrupalContext;
use Behat\Behat\Context\Step\Given;
use Behat\Gherkin\Node\TableNode;
use Behat\Gherkin\Node\PyStringNode;
use Guzzle\Service\Client;
use Behat\Behat\Context\Step;
use Behat\Behat\Context\Step\When;

require 'vendor/autoload.php';

class FeatureContext extends DrupalContext {

  /**
   * Variable for storing the random string we used in the text.
   */
  private $randomText;

  /**
   * Variable to pass into the last xPath expression.
   */
  private $xpath = '';

  /**
   * The box delta we need to hide.
   */
  private $box = '';

  /**
   * Hold the user name and password for the selenium tests for log in.
   */
  private $drupal_users;

  /**
   * Hold the NID of the vsite.
   */
  private $nid;

  /**
   * Initializes context.
   *
   * Every scenario gets its own context object.
   *
   * @param array $parameters.
   *   Context parameters (set them up through behat.yml or behat.local.yml).
   */
  public function __construct(array $parameters) {
    if (isset($parameters['drupal_users'])) {
      $this->drupal_users = $parameters['drupal_users'];
    }

    if (isset($parameters['vsite'])) {
      $this->nid = $parameters['vsite'];
    }
  }

  /**
   * Authenticates a user with password from configuration.
   *
   * @Given /^I am logged in as "([^"]*)"$/
   */
  public function iAmLoggedInAs($username) {
    try {
      $password = $this->drupal_users[$username];
    } catch (Exception $e) {
      throw new Exception("Password not found for '$username'.");
    }

    // Log in.
    // Go to the user page.
    $element = $this->getSession()->getPage();
    $this->getSession()->visit($this->locatePath('/user'));
    $element->fillField('Username', $username);
    $element->fillField('Password', $password);
    $submit = $element->findButton('Log in');
    $submit->click();
  }

  /**
   * @Given /^I am on a "([^"]*)" page titled "([^"]*)"(?:, in the tab "([^"]*)"|)$/
   */
  public function iAmOnAPageTitled($page_type, $title, $subpage = NULL) {
    $table = 'node';
    $id = 'nid';
    $path = "$page_type/%";
    $type = str_replace('-', '_', $page_type);

    $path .= "/$subpage";

    //TODO: The title and type should be properly escaped.
    $query = "\"
      SELECT $id AS identifier
      FROM $table
      WHERE title = '$title'
      AND type = '$type'
    \"";

    $result = $this->getDriver()->drush('sql-query', array($query));
    $id = trim(substr($result, strlen('identifier')));

    if (!$id) {
      throw new \Exception("No $page_type with title '$title' was found.");
    }
    $path = str_replace('%', $id, $path);

    return new Given("I am at \"node/$id\"");
  }

  /**
   * @Given /^I should see the "([^"]*)" table with the following <contents>:$/
   */
  public function iShouldSeeTheTableWithTheFollowingContents($class, TableNode $table) {
    $page = $this->getSession()->getPage();
    $table_element = $page->find('css', "table.$class");
    if (!$table_element) {
      throw new Exception("A table with the class $class wasn't found");
    }

    $table_rows = $table->getRows();
    $hash = $table->getRows();
    // Iterate over each row, just so if there's an error we can supply
    // the row number, or empty values.
    foreach ($table_rows as $i => $table_row) {
      if (empty($table_row)) {
        continue;
      }
      if ($diff = array_diff($hash[$i], $table_row)) {
        throw new Exception(sprintf('The "%d" row values are wrong.', $i + 1));
      }
    }
  }

  /**
   * @Then /^I should get:$/
   */
  public function iShouldGet(PyStringNode $string) {
    $page = $this->getSession()->getPage();
    $comapre_string = $string->getRaw();
    $page_string = $page->getContent();

    if (strpos($comapre_string, '{{*}}')) {
      // Attributes that may changed in different environments.
      foreach (array('sourceUrl', 'id', 'value', 'href', 'os_version') as $attribute) {
        $page_string = preg_replace('/ '. $attribute . '=".+?"/', '', $page_string);
        $comapre_string = preg_replace('/ '. $attribute . '=".+?"/', '', $comapre_string);

        // Dealing with JSON.
        $page_string = preg_replace('/"'. $attribute . '":".+?"/', '', $page_string);
        $comapre_string = preg_replace('/"'. $attribute . '":".+?"/', '', $comapre_string);
      }

      if ($page_string != $comapre_string) {
        $output = "The strings are not matching.\n";
        $output .= "Page: {$page_string}\n";
        $output .= "Search: {$comapre_string}\n";
        throw new Exception($output);
      }
    }
    else {
      // Normal compare.
      foreach (explode("\n", $comapre_string) as $text) {
        if (strpos($page_string, $text) === FALSE) {
          throw new Exception(sprintf('The text "%s" was not found.', $text));
        }
      }
    }
  }

  /**
   * @When /^I clear the cache$/
   */
  public function iClearTheCache() {
    $this->getDriver()->drush('cc all');
  }

  /**
   * @Then /^I should print page$/
   */
  public function iShouldPrintPage() {
    $element = $this->getSession()->getPage();
    print_r($element->getContent());
  }

  /**
   * @Then /^I should see the images:$/
   */
  public function iShouldSeeTheImages(TableNode $table) {
    $page = $this->getSession()->getPage();
    $table_rows = $table->getRows();
    foreach ($table_rows as $rows) {
      $image = $page->find('xpath', "//img[contains(@src, '{$rows[0]}')]");
      if (!$image) {
        throw new Exception(sprintf('The image "%s" wasn\'t found in the page.', $rows[0]));
      }
    }
  }

  /**
   * @Given /^I drag&drop "([^"]*)" to "([^"]*)"$/
   */
  public function iDragDropTo($element, $destination) {
    $selenium = $this->getSession()->getDriver();
    $selenium->evaluateScript("jQuery('#{$element}').detach().prependTo('#{$destination}');");
  }

  /**
   * @Given /^I verify the element "([^"]*)" under "([^"]*)"$/
   */
  public function iVerifyTheElementUnder($element, $container) {
    $page = $this->getSession()->getPage();
    $element = $page->find('xpath', "//*[contains(@id, '{$container}')]//div[contains(@id, '{$element}')]");

    if (!$element) {
      throw new Exception(sprintf("The element with %s wasn't found in %s", $element, $container));
    }
  }

  /**
   * Find any element that contain the text and has the css class or id
   * selector.
   */
  private function findAnyElement($text, $container, $childElement = '*') {
    $page = $this->getSession()->getPage();
    $attributes = array(
      'id',
      'class',
    );

    // Find the element wrapped under an element with the class.
    foreach ($attributes as $attribute) {
      $this->xpath = "//*[contains(@$attribute, '{$container}')]/{$childElement}[contains(., '{$text}')]";
      $element = $page->find('xpath', $this->xpath);
      if ($element) {
        return $element;
      }
    }

    // Find the element with the class.
    foreach ($attributes as $attribute) {
      $this->xpath = "//*[contains(@$attribute, '{$container}') and contains(., '{$text}')]";
      $element = $page->find('xpath', $this->xpath);
      if ($element) {
        return $element;
      }
    }

    throw new Exception(sprintf("An element containing the text %s with the class %s wasn't found", $text, $container));
  }

  /**
   * @Given /^a node of type "([^"]*)" with the title "([^"]*)" exists in site "([^"]*)"$/
   */
  public function assertNodeTypeTitleVsite($type, $title, $site = 'john') {
    return array(
      new Step\When('I visit "' . $site . '/node/add/' . $type . '"'),
      new Step\When('I fill in "Title" with "'. $title . '"'),
      new Step\When('I press "edit-submit"'),
    );
  }

  /**
   * @Given /^I create a new publication$/
   */
  public function iCreateANewPublication() {
    return array(
      new Step\When('I visit "john/node/add/biblio"'),
      new Step\When('I select "Book" from "Publication Type"'),
      new Step\When('I press "edit-biblio-next"'),
      new Step\When('I fill in "Title" with "'. time() . '"'),
      new Step\When('I press "edit-submit"'),
    );
  }

  /**
   * @When /^I create a new "([^"]*)" entry with the name "([^"]*)"$/
   */
  public function iCreateANewEntryWithTheName($type, $name) {
    return array(
      new Step\When('I visit "john/node/add/' . $type . '"'),
      new Step\When('I fill in "Title" with "'. $name . '"'),
      new Step\When('I press "edit-submit"'),
    );
  }

  /**
   * @Then /^I should verify the node "([^"]*)" not exists$/
   */
  public function iShouldVerifyTheNodeNotExists($title) {
    $nid = $this->invoke_code('os_migrate_demo_get_node_id', array("'$title'"));

    $this->invoke_code('os_migrate_demo_delete_node', array("'$title'"));

    $this->Visit('I visit "john/node/' . $nid . '"');

    return array(
      new Step\When('I should not get a "200" HTTP response'),
    );
  }

  /**
   * @Given /^I add a comment "([^"]*)" using the comment form$/
   */
  public function iAddACommentUsingTheCommentForm($comment) {
    return array(
      new Step\When('I fill in "Comment" with "' . $comment . '"'),
      new Step\When('I press "Save"'),
    );
  }

  /**
   * @Given /^the widget "([^"]*)" is set in the "([^"]*)" page with the following <settings>:$/
   */
  public function theWidgetIsSetInThePageWithSettings($page, $widget, TableNode $table) {
    $code = "os_migrate_demo_set_box_in_region({$this->nid}, '$page', '$widget');";
    $this->box[] = $this->getDriver()->drush("php-eval \"{$code}\"");
    $hash = $table->getRows();

    list($box, $delta, $context) = explode(",", $this->box[0]);

    $metasteps = array();
    // @TODO: Don't use the hard coded address - remove john from the address.
    $this->visit('john/os/widget/boxes/' . $delta . '/edit');

    // @TODO: Use XPath to fill the form instead of giving the type of the in
    // the scenario input.
    foreach ($hash as $form_elements) {
      switch ($form_elements[2]) {
        case 'select list':
          $metasteps[] = new Step\When('I select "' . $form_elements[1] . '" from "'. $form_elements[0] . '"');
          break;
        case 'checkbox':
          $metasteps[] = new Step\When('I '. $form_elements[1] . ' the box "' . $form_elements[0] . '"');
          break;
        case 'textfield':
          $metasteps[] = new Step\When('I fill in "' . $form_elements[0] . '" with "1"');
          break;
        case 'radio':
          $metasteps[] = new Step\When('I select the radio button "' . $form_elements[0] . '" with the id "' . $form_elements[1] . '"');
          break;
      }
    }

    $metasteps[] = new Step\When('I press "Save"');

    return $metasteps;
  }

  /**
   * @Given /^the widget "([^"]*)" is set in the "([^"]*)" page$/
   */
  public function theWidgetIsSetInThePage($page, $widget) {
    $code = "os_migrate_demo_set_box_in_region({$this->nid}, '$page', '$widget');";
    $this->box[] = $this->getDriver()->drush("php-eval \"{$code}\"");
  }

  /**
   * @When /^I assign the node "([^"]*)" to the term "([^"]*)"$/
   */
  public function iAssignTheNodeToTheTerm($node, $term) {
    $this->invoke_code('os_migrate_demo_assign_node_to_term', array("'$node'","'$term'"));
  }

  /**
   * @Given /^I assign the node "([^"]*)" with the type "([^"]*)" to the term "([^"]*)"$/
   */
  public function iAssignTheNodeWithTheTypeToTheTerm($node, $type, $term) {
    $code = "os_migrate_demo_assign_node_to_term('$node', '$term', '$type');";
    $this->getDriver()->drush("php-eval \"{$code}\"");
  }


  /**
   * Hide the boxes we added during the scenario.
   *
   * @AfterScenario
   */
  public function afterScenario($event) {
    if (empty($this->box)) {
      return;
    }

    // Loop over the box we collected in the scenario, hide them and delete
    // them.
    foreach ($this->box as $box_handler) {
      $data = explode(',', $box_handler);
      foreach ($data as &$value) {
        $value = trim($value);
      }
      $code = "os_migrate_demo_hide_box({$this->nid}, '{$data[0]}', '{$data[1]}', '{$data[2]}');";
      $this->getDriver()->drush("php-eval \"{$code}\"");
    }
  }

  /**
   * @Given /^cache is enabled for anonymous users$/
   */
  public function cacheIsEnabledForAnonymousUsers() {
    $this->getDriver()->drush('vset cache 1');
  }

  /**
   * @Then /^response header "([^"]*)" should be "([^"]*)"$/
   */
  public function responseHeaderShouldBe($key, $result) {
    $headers = $this->getSession()->getResponseHeaders();
    if (empty($headers[$key]) || $headers[$key][0] !== $result) {
      throw new Exception(sprintf('The "%s" key in the response header is "%s" instead of the expected "%s".', $key, $headers[$key][0], $result));
    }
  }

  /**
   * @Given /^I should see the following <links>$/
   */
  public function iShouldNotSeeTheFollowingLinks(TableNode $table) {
    $page = $this->getSession()->getPage();
    $hash = $table->getRows();

    foreach ($hash as $i => $table_row) {
      if (empty($table_row)) {
        continue;
      }
      $element = $page->find('xpath', "//a[.='{$table_row[0]}']");

      if (empty($element)) {
        throw new Exception(printf("The link %s wasn't found on the page", $table_row[0]));
      }
    }
  }

  /**
   * @Then /^I should see tineMCE in "([^"]*)"$/
   */
  public function iShouldSeeTinemceIn($field) {
    $page = $this->getSession()->getPage();
    $iframe = $page->find('xpath', "//label[contains(., '{$field}')]//..//iframe[@id='edit-body-und-0-value_ifr']");

    if (!$iframe) {
      throw new Exception("tinyMCE wysiwyg does not appear.");
    }
  }

  /**
   * @Given /^I sleep for "([^"]*)"$/
   */
  public function iSleepFor($sec) {
    sleep($sec);
  }

  /**
   * Invoking a php code with drush.
   *
   *  @param $function
   *    The function name to invoke.
   *  @param $arguments
   *    Array contain the arguments for function.
   *  @param $debug
   *    Set as TRUE/FALSE to diplay the output the function print on the screen.
   */
  private function invoke_code($function, $arguments, $debug = FALSE) {
    $code = "$function(" . implode(',', $arguments) . ");";
    $output = $this->getDriver()->drush("php-eval \"{$code}\"");

    if ($debug) {
      print_r($output);
    }

    return $output;
  }

  /**
   * @Then /^I should see the following <json>:$/
   */
  public function iShouldSeeTheFollowingJson(TableNode $table) {
    // Get the json output and decode it.
    $json_output = $this->getSession()->getPage()->getContent();
    $json = json_decode($json_output);


    // Hasing table, and define variables for later.
    $hash = $table->getRows();
    $errors = array();

    // Run over the tale and start matching between the values of the JSON and
    // the user input.
    foreach ($hash as $i => $table_row) {
      if (isset($json->{$table_row[0]})) {
        if ($json->{$table_row[0]} != $table_row[1]) {
          $error['values'][$table_row[0]] = ' Not equal to ' . $table_row[1];
        }
      }
      else {
        $error['not_found'][$table_row[0]] = " Dosen't exists.";
      }
    }

    // Build the error string if needed.
    if (!empty($error)) {
      $string = array();

      if (!empty($error['values'])) {
        foreach ($error['values'] as $variable => $message) {
          $string[] = '  ' . $variable . $message;
        }
      }

      if (!empty($error['not_found'])) {
        foreach ($error['not_found'] as $variable => $message) {
          $string[] = '  ' . $variable . $message;
        }
      }

      throw new Exception("Some errors were found:\n" . implode("\n", $string));
    }
  }

  /**
   * Generate random text.
   */
  private function randomizeMe($length = 10) {
    return $this->randomText = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz"), 0, $length);
  }

  /**
   * @Given /^I fill "([^"]*)" with random text$/
   */
  public function iFillWithRandomText($elementId) {
    $page = $this->getSession()->getPage();
    $element = $page->find('xpath', "//input[@id='{$elementId}']");

    if (!$element) {
      throw new Exception(sprintf("Could not find the element with the id %s", $elementId));
    }

    $element->setValue($this->randomizeMe());
  }

  /**
   * @Given /^I visit the site "([^"]*)"$/
   */
  public function iVisitTheSite($site) {
    if ($site == "random") {
      $this->visit("/" . $this->randomText);
    }
    else {
      $this->visit("/" . $site);
    }
  }

  /**
   * @Given /^I set the term "([^"]*)" under the term "([^"]*)"$/
   */
  public function iSetTheTermUnderTheTerm($child, $parent) {
    $function = 'os_migrate_demo_set_term_under_term';
    $this->invoke_code($function, array("'$child'", "'$parent'"));
  }

  /**
   * @When /^I set the variable "([^"]*)" to "([^"]*)"$/
   */
  public function iSetTheVariableTo($variable, $value) {
    $function = 'os_migrate_demo_variable_set';
    $this->invoke_code($function, array($variable, $value));
  }

  /**
   * @Then /^I should see a pager$/
   */
  public function iShouldSeeAPager() {
    $page = $this->getSession()->getPage();
    $element = $page->find('xpath', "//div[@class='item-list']");

    if (!$element) {
      throw new Exception("The pager wasn't found.");
    }
  }

  /**
   * @Given /^I set courses to import$/
   */
  public function iSetCoursesToImport() {
    $metasteps = array();
    $this->getDriver()->drush("php-eval \"drupal_flush_all_caches();\"");
    $this->getDriver()->drush("cc all");
    $metasteps[] = new Step\When('I visit "admin"');
    $metasteps[] = new Step\When('I visit "admin/structure/feeds/course/settings/HarvardFetcher"');
    $metasteps[] = new Step\When('I check the box "Debug mode"');
    $metasteps[] = new Step\When('I press "Save"');
    $metasteps[] = new Step\When('I visit "john/cp/build/features/harvard_courses"');
    $metasteps[] = new Step\When('I fill in "Department ID" with "Architecture"');
    $metasteps[] = new Step\When('I select "Harvard Graduate School of Design" from "School name"');
    $metasteps[] = new Step\When('I press "Save configuration"');

    return $metasteps;
  }

  /**
   * @When /^I enable harvard courses$/
   */
  public function iEnableHarvardCourses() {
    $code = "os_migrate_demo_define_harvard_courses();";
    $this->getDriver()->drush("php-eval \"{$code}\"");
  }

  /**
   * @Given /^I refresh courses$/
   */
  public function iRefreshCourses() {
    $code = "os_migrate_demo_import_courses();";
    $this->getDriver()->drush("php-eval \"{$code}\"");
  }

  /**
   * @Given /^I remove harvard courses$/
   */
  public function iRemoveHarvardCourses() {
    $metasteps = array();
    $metasteps[] = new Step\When('I visit "john/cp/build/features/harvard_courses"');
    $metasteps[] = new Step\When('I press "Remove"');
    $metasteps[] = new Step\When('I sleep for "2"');
    $metasteps[] = new Step\When('I press "Save configuration"');

    return $metasteps;
  }

  /**
   * @Given /^I invalidate cache$/
   */
  public function iInvalidateCache() {
    $code = "views_og_cache_invalidate_cache(node_load($this->nid));";
    $this->getDriver()->drush("php-eval \"{$code}\"");
  }

  /**
   * @Given /^I should be redirected in the following <cases>:$/
   */
  public function iShouldBeRedirectedInTheFollowingCases(TableNode $table) {
    $rows = $table->getRows();
    $baseUrl = $this->locatePath('');

    if (count(reset($rows)) == 3) {
      foreach ($rows as $row) {
        $this->visit($row[0]);
        $url = $this->getSession()->getCurrentUrl();

        if ($url != $baseUrl . $row[2] && $url != 'http://lincoln.local/' . $row[2]) {
          throw new Exception("When visiting {$row[0]} we did not redirected to {$row[2]} but to {$url}.");
        }

        $john_response_code = $this->responseCode($baseUrl . $row[0]);
        $lincoln_response_code = $this->responseCode('http://lincoln.local/' . $row[0]);
        if ($john_response_code != $row[1] && $lincoln_response_code != $row[1]) {
          throw new Exception("When visiting {$row[0]} we did not get a {$row[1]} reponse code but the {$john_response_code}/{$lincoln_response_code} reponse code.");
        }
      }
    }
    else {
      foreach ($rows as $row) {
        $code = "os_migrate_demo_get_node_nid('$row[1]');";
        $nid = $this->getDriver()->drush("php-eval \"{$code}\"");


        if ($row[2] == 'No') {
          $VisitUrl = 'node/' . $nid;
        }
        else {
          $code = "print drupal_get_path_alias('node/{$nid}');";
          $VisitUrl = $this->getDriver()->drush("php-eval \"{$code}\"");
        }

        if (!empty($row[0])) {
          $VisitUrl = $row[0] . $VisitUrl;
        }

        $this->visit($VisitUrl);
        $url = $this->getSession()->getCurrentUrl();

        if ($url != $baseUrl . $row[4]) {
          throw new Exception("When visiting {$VisitUrl} we did not redirected to {$row[4]} but to {$url}.");
        }

        $response_code = $this->responseCode($baseUrl . $VisitUrl);
        if ($response_code != $row[3]) {
          throw new Exception("When visiting {$VisitUrl} we did not get a {$row[3]} reponse code but the {$response_code} reponse code.");
        }
      }
    }
  }

  /**
   * Get the response code for a URL.
   *
   *  @param $address
   *    The URL address.
   *
   *  @return
   *    The response code for the URL address.
   */
  function responseCode($address) {
    $ch = curl_init($address);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 1); // Return header.
    curl_setopt($ch, CURLOPT_NOBODY, 1); // Will not return the body.

    $linkHeaders = curl_exec($ch);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);

    return $curlInfo['http_code'];
  }

  /**
   * @Then /^I should see the random string$/
   */
  public function iShouldSeeTheRandomString() {
    $metasteps = array(new Step\When('I should see "' . $this->randomText . '"'));
    return $metasteps;
  }

  /**
   * @Then /^I verify the "([^"]*)" term link redirect to the original page$/
   */
  public function iVerifyTheTermLinkRedirectToTheOriginalPage($term) {
    $code = "os_migrate_demo_get_term_id('$term');";
    $tid = $this->getDriver()->drush("php-eval \"{$code}\"");

    $page = $this->getSession()->getPage();
    $element = $page->find('xpath', "//a[contains(., '{$term}')]");

    if (strpos($element->getAttribute('href'), 'taxonomy/term/') !== FALSE) {
      throw new exception("The term {$term} linked us to his original path(taxonomy/term/{$tid})");
    }
  }

  /**
   * @Given /^I verify the "([^"]*)" term link doesn\'t redirect to the original page$/
   */
  public function iVerifyTheTermLinkDoesnTRedirectToTheOriginalPage($term) {
    $code = "os_migrate_demo_get_term_id('$term');";
    $tid = $this->getDriver()->drush("php-eval \"{$code}\"");

    $page = $this->getSession()->getPage();
    $element = $page->find('xpath', "//a[contains(., '{$term}')]");

    if (strpos($element->getAttribute('href'), 'taxonomy/term/') === FALSE) {
      throw new exception("The term {$term} linked us to his original path(taxonomy/term/{$tid})");
    }
  }

  /**
   * @Given /^I should not see "([^"]*)" under "([^"]*)"$/
   */
  public function iShouldNotSeeUnder($text, $id) {
    $page = $this->getSession()->getPage();
    $element = $page->find('xpath', "//input[@id='{$id}']//*[contains(.,'{$text}')]");
    if ($element) {
      throw new Exception("The text {$text} found under #{$id}");
    }
  }

  /**
   * @Then /^I should verify i am at "([^"]*)"$/
   */
  public function iShouldVerifyIAmAt($given_url) {
    $url = $this->getSession()->getCurrentUrl();
    $base_url = $startUrl = rtrim($this->getMinkParameter('base_url'), '/') . '/';

    $path = str_replace($base_url, '', $url);

    if ($path != $given_url) {
      throw new Exception("The given url: '{$given_url}' is not equal to the current path {$path}");
    }
  }

  /**
   * @When /^I visit the original page for the term "([^"]*)"$/
   */
  public function iVisitTheOriginalPageForTheTerm($term) {
    $code = "os_migrate_demo_get_term_id('$term');";
    $tid = $this->getDriver()->drush("php-eval \"{$code}\"");
    $this->getSession()->visit($this->locatePath('taxonomy/term/' . $tid));
  }
}
