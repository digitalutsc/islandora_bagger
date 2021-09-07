<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

/**
 * Adds Drupal's JSON representation of the Islandora object to the Bag.
 */
class AddNodeJson_IslandoraLite extends AbstractIbPlugin
{
  /**
   * Constructor.
   *
   * @param array $settings
   *    The configuration data from the .ini file.
   * @param object $logger
   *    The Monolog logger from the main Command.
   */
  public function __construct($settings, $logger)
  {
    parent::__construct($settings, $logger);
  }

  /**
   * {@inheritdoc}
   *
   * Adds Drupal's JSON representation of the Islandora object to the Bag.
   */
  public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json)
  {
    $arr = json_decode($node_json, TRUE);
    $vid = $arr['vid'][0]['value'];
    if (!array_key_exists('taxonomy_info', $this->settings) || !count($this->settings['taxonomy_info'])){
      $bag->createFile($node_json, 'node_' . $nid . '/node.' . 'v' . $vid . '.json');
      return $bag;
    }
    $invalids = [];
    $this->retreivePages($bag, $bag_temp_dir, $nid, $node_json);
    $client = new \GuzzleHttp\Client();
    //use the jsonld to get the translated languages
    $result = $client->request('GET', $this->settings['drupal_base_url'] . '/node/' . $nid, [
      'http_errors' => FALSE,
      'auth' => $this->settings['drupal_basic_auth'],
      'query' => ['_format' => 'jsonld'],
    ]);
    $taxonomies = $this->settings['relevant_taxonomies'];
    $jsonld =  json_decode((string) $result->getBody(), TRUE);
    for ($x = 0; $x<count($jsonld['@graph'][0]['dcterms:title']); $x++){ //repeat for each language
      $node_client = new \GuzzleHttp\Client();
      //get the translated json (if needed)
      if ($jsonld['@graph'][0]['dcterms:title'][$x]['@language'] != $arr['langcode'][0]['value']){
        $node = $node_client->request('GET', $this->settings['drupal_base_url'] . DIRECTORY_SEPARATOR . $jsonld['@graph'][0]['dcterms:title'][$x]['@language'] .'/node/' . $nid, [
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth'],
          'query' => ['_format' => 'json'],
        ]);
        $arr = json_decode((string) $node->getBody(), TRUE);
      }

      $this->expandTaxanomy($taxonomies, $arr);
      //Gather user-specified info about each collection
      if (!count($this->settings['content_type_info'])) goto bag_creation; //empty array => no further modifications
  //    $collections = $arr['field_member_of'];
      $all = false;
      if ($this->settings['content_type_info'][0] == '') $all = true; //get all info

      $collection_client = new \GuzzleHttp\Client();
      $content_references = $this->settings['content_references'];
      for ($p = 0; $p < count($content_references); $p++){
        $collections = $arr[$content_references[$p]];
        for ($y = 0; $y < count($collections); $y++) {
          $result = $collection_client->request('GET', $this->settings['drupal_base_url'] . $collections[0]['url'], [
            'http_errors' => FALSE,
            'auth' => $this->settings['drupal_basic_auth'],
            'query' => ['_format' => 'json'],
          ]);
          $collection_json = json_decode((string) $result->getBody(), TRUE);
          //check for special case, with all info
          $collection_info = $all ? array_keys($collection_json) : $this->settings['content_type_info'];
          //gather all fields in collection_info
          for ($u = 0; $u < count($collection_info); $u++) {
            if (in_array($collection_info[$u], ['nid', 'uuid'])) {
              continue;
            } //skip over fields since they are in the JSON already
            if (!in_array($collection_info[$u], array_keys($collection_json))) { //error checking
              if (!in_array($collection_info[$u], $invalids)) { //no need to repeat error message
                echo "\033[01;31m ERROR: invalid field, \033[0m" . "\033[01;31m'" . $collection_info[$u] . "', skipping...\033[0m\n";
                $invalids[] = $collection_info[$u];
              }
              continue;
            }
            if (!count($collection_json[$collection_info[$u]])) { //field is empty => add empty array
              $arr['field_member_of'][$y]["target_" . $collection_info[$u]] = [];
              continue;
            }
            $arr['field_member_of'][$y]["target_" . $collection_info[$u]] = count($collection_json[$collection_info[$u]][0]) == 1 ?
              $collection_json[$collection_info[$u]][0][array_keys($collection_json[$collection_info[$u]][0])[0]] :
              $collection_json[$collection_info[$u]];
          }
          $collection_taxonomies = [];
          foreach ($taxonomies as $tax) {
            if (in_array("target_" . $tax, array_keys($arr['field_member_of'][$y]))) {
              $collection_taxonomies[] = "target_" . $tax;
            }
          }
          $this->expandTaxanomy($collection_taxonomies, $arr['field_member_of'][$y]);
        }
      }
      bag_creation:
      $bag->createFile(json_encode($arr, JSON_PRETTY_PRINT), 'node_' . $nid . '/node_' . $jsonld['@graph'][0]['dcterms:title'][$x]['@language'] . '.v' . $vid . ".json");
    }
    return $bag;
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
          } //skip over duplicates
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
}
