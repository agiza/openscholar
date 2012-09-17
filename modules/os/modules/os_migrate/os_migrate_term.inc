<?php

/**
 * @file
 *
 * Migration class
 **/

/**
 * Vocab table migration class
 */
class TermOSMigration extends DynamicMigration {

  public function __construct(array $arguments){
    $this->description = t('OS taxonomy term migration');
    $this->arguments = $arguments;

    $group = MigrateGroup::getInstance('taxonomy');


    parent::__construct($group);

    $query = Database::getConnection('default', variable_get('os_migrate_database', ''))
      ->select('term_data', 'td')
      ->fields('td', array('tid','name','description','weight'))
      ->orderBy('parent')
      ->condition('td.vid', $arguments['src_vid'])
      ->distinct();

    // Join to the hierarchy so we can sort on parent, but we'll pull the
    // actual parent values in separately in case there are multiples.
    $query->leftJoin('term_hierarchy', 'th', 'td.tid=th.tid');

    $key_schema = array(
      'tid' => array(
        'type' => 'int',
        'unsigned' => TRUE,
        'not null' => TRUE,
        'description' => 'Source term ID'
      )
    );

    $this->source = new MigrateSourceSQL($query, $key_schema, null, array(
      'map_joinable' => FALSE
    ));
    $this->destination = new MigrateDestinationTerm($this->arguments['dst_vocabulary']);

    $this->map = new MigrateSQLMap($this->machineName, $key_schema, MigrateDestinationTerm::getKeySchema());

    // Mappings are straight-forward
    $this->addSimpleMappings(array(
      'name',
      'description',
      'weight'
    ));
    $this->addFieldMapping('parent', 'parent')->sourceMigration($this->machineName);

    $this->addUnmigratedDestinations(array(
      'parent_name'
    ));

    if (module_exists('path')) {
      $this->addFieldMapping('path', 'path')->description('Handled in prepareRow');
    }

    if (module_exists('pathauto')) {
      $this->addFieldMapping('pathauto')->description('By default, disable in favor of migrated paths')->defaultValue(0);
    }
  }

  /**
   * Implements prepareRow
   *
   **/
  public function prepareRow($current_row){
    if (parent::prepareRow($current_row) === FALSE) {
      return false;
    }

    //Add the (potentially multiple) parents
    $result = Database::getConnection('default', variable_get('os_migrate_database', ''))->select('term_hierarchy', 'th')->fields('th', array(
      'parent'
    ))->condition('tid', $current_row->tid)->execute();
    foreach ( $result as $parent_row ) {
      $row->parent[] = $parent_row->parent;
    }

    return TRUE; // FALSE to skip a row.
  }


  /**
   * Construct the machine name from the source file name.
   */
  protected function generateMachineName($class_name = NULL) {

//     $current_row->machine_name = "vocabulary_".$current_row->vid;
//     Migration::registerMigration('TermOSMigration', 'TermOSMigration'.$current_row->machine_name, array('dst_voc

    return 'TermOSMigrationVocabulary_' . $this->arguments['src_vid'];
  }

}


// class DrupalTerm7Migration extends DrupalTermMigration {
//   public function __construct(array $arguments) {
//     parent::__construct($arguments);

//     $this->addFieldMapping('format', 'format')
//     ->callbacks(array($this, 'mapFormat'));
//   }

//   protected function query() {
//     $query = Database::getConnection('default', $this->sourceConnection)
//     ->select('taxonomy_term_data', 'td')
//     ->fields('td', array('tid', 'name', 'description', 'weight', 'format'))
//     ->orderBy('parent')
//     ->distinct();
//     $query->leftJoin('taxonomy_term_hierarchy', 'th', 'td.tid=th.tid');
//     $query->fields('th', array('parent'));
//     $query->innerJoin('taxonomy_vocabulary', 'v', 'td.vid=v.vid');
//     $query->condition('v.machine_name', array($this->sourceVocabulary), 'IN');
//     return $query;
//   }

//   /**
//    * Review a data row after fetch, returning FALSE to skip it.
//    *
//    * @param $row
//    * @return bool
//    */
//   public function prepareRow($row) {
//     if (parent::prepareRow($row) === FALSE) {
//       return FALSE;
//     }

//     $this->version->getSourceValues($row, $row->tid);
//     return TRUE;
//   }
// }

// /**
//  * Handling specific to a Drupal 6 source for taxonomy terms.
//  */
// class DrupalTerm6Migration extends DrupalTermMigration {
//   public function __construct(array $arguments) {
//     $this->sourceFields['parent'] = t('Term: Parent term');
//     parent::__construct($arguments);

//     // Drupal 6 had no format for terms.
//     $this->addFieldMapping('format');
//   }

//   /**
//    * Implementation of DrupalTermMigration::query().
//    *
//    * @return SelectQueryInterface
//    */
//   protected function query() {
//     // Note the explode - this supports the (admittedly unusual) case of
//     // consolidating multiple vocabularies into one.
//     $query = Database::getConnection('default', $this->sourceConnection)
//     ->select('term_data', 'td')
//     ->fields('td', array('tid', 'name', 'description', 'weight'))
//     ->condition('vid', explode(',', $this->sourceVocabulary), 'IN')
//     ->orderBy('parent')
//     ->distinct();
//     // Join to the hierarchy so we can sort on parent, but we'll pull the
//     // actual parent values in separately in case there are multiples.
//     $query->leftJoin('term_hierarchy', 'th', 'td.tid=th.tid');
//     return $query;
//   }

//   /**
//    * Implementation of Migration::prepareRow().
//    *
//    * @param $row
//    */
//   public function prepareRow($row) {
//     if (parent::prepareRow($row) === FALSE) {
//       return FALSE;
//     }

//     // Add the (potentially multiple) parents
//     $result = Database::getConnection('default', $this->sourceConnection)
//     ->select('term_hierarchy', 'th')
//     ->fields('th', array('parent'))
//     ->condition('tid', $row->tid)
//     ->execute();
//     foreach ($result as $parent_row) {
//       $row->parent[] = $parent_row->parent;
//     }
//   }
// }

// /**
//  * Base class for migrations of Drupal taxonomy terms from another Drupal
//  * installation.
//  */
// abstract class DrupalTermMigration extends Migration {
//   /**
//    * The machine name, or vocabulary ID (pre-D7), of the vocabulary we're
//    * migrating from.
//    *
//    * @var mixed
//    */
//   protected $sourceVocabulary;

//   /**
//    * The machine name of the Drupal 7 vocabulary we're migrating into.
//    *
//    * @var string
//    */
//   protected $destinationVocabulary;


//   /**
//    * In addition to the arguments supported by DrupalMigration, we add the
//    * following required arguments:
//    *
//    * source_vocabulary - Unique identifier for the source vocabulary (a vid
//    *   through Drupal 6, machine name for Drupal 7 and later). This may be a
//    *   comma-separated list, to support merging multiple vocabularies into one.
//    * destination_vocabulary - Machine name of the destination vocabulary.
//    *
//    * @param array $arguments
//    */
//   public function __construct(array $arguments) {
//     parent::__construct($arguments);
//     $this->sourceVocabulary = $arguments['source_vocabulary'];
//     $this->destinationVocabulary = $arguments['destination_vocabulary'];
//     $this->sourceFields += $this->version->getSourceFields(
//       'taxonomy_term', $this->sourceVocabulary);
//     if ($this->moduleExists('path')) {
//       $this->sourceFields['path'] = array('label' => t('Node: Path alias'));
//     }

//     // Create our three main objects - source, destination, and map
//     $this->source = new MigrateSourceSQL($this->query(), $this->sourceFields,
//       NULL, $this->sourceOptions);

//     $this->destination = new MigrateDestinationTerm($this->destinationVocabulary);

//     $this->map = new MigrateSQLMap($this->machineName,
//       array(
//         'tid' => array('type' => 'int',
//           'unsigned' => TRUE,
//           'not null' => TRUE,
//           'description' => 'Source term ID',
//         ),
//       ),
//       MigrateDestinationTerm::getKeySchema()
//     );

//     // Mappings are straight-forward
//     $this->addSimpleMappings(array('name', 'description', 'weight'));
//     $this->addFieldMapping('parent', 'parent')
//     ->sourceMigration($this->machineName);

//     $this->addUnmigratedDestinations(array('parent_name'));

//     if ($this->moduleExists('path')) {
//       $this->addFieldMapping('path', 'path')
//       ->description('Handled in prepareRow');
//     }

//     if (module_exists('pathauto')) {
//       $this->addFieldMapping('pathauto')
//       ->description('By default, disable in favor of migrated paths')
//       ->defaultValue(0);
//     }
//   }

//   /**
//    * Review a data row after fetch, returning FALSE to skip it.
//    *
//    * @param $row
//    * @return bool
//    */
//   public function prepareRow($row) {
//     if (parent::prepareRow($row) === FALSE) {
//       return FALSE;
//     }

//     // Add the path to the source row, if relevant
//     if ($this->moduleExists('path')) {
//       $path = $this->version->getPath('taxonomy/term/' . $row->tid);
//       if ($path) {
//         $row->path = $path;
//       }
//     }
//     return TRUE;
//   }
// }


