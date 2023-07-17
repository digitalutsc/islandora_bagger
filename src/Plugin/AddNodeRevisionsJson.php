<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

/**
 * Adds the JSON representation of the Islandora object's revisions to the Bag.
 */
class AddNodeRevisionsJson extends AbstractIbPlugin {

  /**
   * Constructor.
   *
   * @param array $settings
   *    The configuration data from the .ini file.
   * @param object $logger
   *    The Monolog logger from the main Command.
   */
  public function __construct($settings, $logger) {
    parent::__construct($settings, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json, $token = NULL) {
    $this->retreivePages($bag, $bag_temp_dir, $nid, $node_json);
    $json = json_decode((string) $node_json, TRUE);
    $curr_ver = $json['vid'][0]['value'];
    $client = new \GuzzleHttp\Client();
    $url = $this->settings['drupal_base_url'] . '/node/' . $nid . '/revisions/rest';
    $rev_json = $client->request('GET', $url, [
      'http_errors' => FALSE,
      'auth' => $this->settings['drupal_basic_auth'],
      'query' => ['_format' => 'json']
    ]);

    $taxonomies = $this->settings['relevant_taxonomies'];
    $rev_json = json_decode((string) $rev_json->getBody(), TRUE);

    for ($i=0; $i < count($rev_json); $i++) {
        $this->expandTaxanomy($taxonomies, $rev_json[$i]);
        $bag->createFile(json_encode($rev_json[$i], JSON_PRETTY_PRINT), 'node_' . $nid . '/node_' . $rev_json[$i]['langcode'][0]['value'] . '.v' . $rev_json[$i]['vid'][0]['value'] . ".json");
    }

    return $bag;
  }
  /**
   * Initializes bagging for all pages of Paged content
   */
  protected function retreivePages(Bag $bag, $bag_temp_dir, $nid, $node_json){
    $node_arr = json_decode($node_json, TRUE);
    $tax_url = $node_arr['field_model'][0]['url'];
    $client = new \GuzzleHttp\Client();
    $result = $client->request('GET', $this->settings['drupal_base_url'] . $tax_url, [
      'http_errors' => FALSE,
      'auth' => $this->settings['drupal_basic_auth'],
      'query' => ['_format' => 'json'],
    ]);
    $result = json_decode((string) $result->getBody(), TRUE);
    $model = $result['name'][0]['value'];
    if ($model != 'Paged Content') return;
    //otherwise, we have a book, get children
    $url = $this->settings['drupal_base_url'] . '/node/' . $nid . '/children_rest';
    $children_result = $result = $client->request('GET', $url, [
      'http_errors' => FALSE,
      'auth' => $this->settings['drupal_basic_auth'],
      'query' => ['_format' => 'json'],
    ]);
    $children_result_arr = json_decode((string) $children_result->getBody(), TRUE);
    for($i = 0; $i < count($children_result_arr); $i++){
      $page_json = $client->request('GET', $this->settings['drupal_base_url'] . '/node/' . $children_result_arr[$i]['nid'], [
        'http_errors' => FALSE,
        'auth' => $this->settings['drupal_basic_auth'],
        'query' => ['_format' => 'json'],
      ]);
      $this->execute($bag, $bag_temp_dir, $children_result_arr[$i]['nid'], (string) $page_json->getBody());
    }
  }

 /**
   * Expands the taxonomy fields specified by the configuration file to include
   * all properties of the taxonomy specified by the configuration
   * @param array $taxonomies
   *     The list of taxonomy fields to be modified
   * @param array $arr
   *     The array representation of the node JSON being modified
   */
  protected function expandTaxanomy(array $taxonomies, array &$arr) {
    $invalids = [];
    //expand the designated taxonomy fields to include user-specified information
    for ($k = 0; $k < count($taxonomies); $k++) { //in case we later need to expand to other taxonomies, just add them to "$taxonomies"
      //Loop thru each taxonomy term in the field
      for ($j = 0; $j < count($arr[$taxonomies[$k]]); $j++) {
        $client = new \GuzzleHttp\Client();
        //retrieve the json of the taxonomy, to get desired info
        $result = $client->request('GET', $this->settings['drupal_base_url'] . $arr[$taxonomies[$k]][$j]['url'], [
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth'],
          'query' => ['_format' => 'json'],
        ]);
        $taxonomy_json = json_decode((string) $result->getBody(), TRUE);
        //check for special, empty string, case
        $fields = $this->settings['taxonomy_info'][0] == '' ?
        array_keys($taxonomy_json) : $this->settings['taxonomy_info']; //if special case is met, get all info, otherwise, only user-specified
        // $this->expandTaxanomy($fields, $taxonomy_json, $arr[$taxonomies[$k]][$j]);
        //parse and add relevant fields

        for ($i = 0; $i < count($fields); $i++) {
          if (in_array($fields[$i], ['tid', 'uuid'])) {
            continue;
          }

          //skip over duplicates
          if (!in_array($fields[$i], array_keys($taxonomy_json))) { //error checking
            if (!in_array($fields[$i], $invalids)) { //invalid field
              echo "\033[01;31m ERROR: invalid field, \033[0m" . "\033[01;31m'" . $fields[$i] . "', skipping...\033[0m\n";
              $invalids[] = $fields[$i];
            }
            continue;
          }
          if (!count($taxonomy_json[$fields[$i]])) { //field is empty => add empty array
            $arr[$taxonomies[$k]][$j]["target_" . $fields[$i]] = [];
            continue;
          }
          $temp = array_keys($taxonomy_json[$fields[$i]][0])[0];
          $arr[$taxonomies[$k]][$j]["target_" . $fields[$i]] = //if field has just one value, copy it, other copy entire object
            count($taxonomy_json[$fields[$i]][0]) == 1 ? $taxonomy_json[$fields[$i]][0][$temp] :
              $taxonomy_json[$fields[$i]][0];
        }
      }
    }
  }

}