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
    if (!array_key_exists('taxonomy_info', $this->settings) || !count($this->settings['taxonomy_info'])){
      $bag->createFile($node_json, 'node.json');
      return $bag;
    }
    $invalids = [];
    $arr = json_decode($node_json, TRUE);
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
      if (!count($this->settings['collection_info'])) goto bag_creation; //empty array => no further modifications
      $collections = $arr['field_member_of'];
      $all = false;
      if ($this->settings['collection_info'][0] == '') $all = true; //get all info

      $collection_client = new \GuzzleHttp\Client();
      for ($y = 0; $y < count($collections); $y++){
        $result = $collection_client->request('GET', $this->settings['drupal_base_url'] . $collections[0]['url'], [
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth'],
          'query' => ['_format' => 'json']
        ]);
        $collection_json = json_decode((string) $result->getBody(), TRUE);
        //check for special case, with all info
        $collection_info = $all ? array_keys($collection_json) : $this->settings['collection_info'];
        //gather all fields in collection_info
        for ($u = 0; $u < count($collection_info); $u++){
          if (in_array($collection_info[$u], ['nid', 'uuid'])) continue; //skip over fields since they are in the JSON already
          if (!in_array($collection_info[$u], array_keys($collection_json))) { //error checking
            if (!in_array($collection_info[$u], $invalids)) { //no need to repeat error message
              echo "\033[01;31m ERROR: invalid field, \033[0m" . "\033[01;31m'" . $collection_info[$u] . "', skipping...\033[0m\n";
              $invalids[] = $collection_info[$u];
            }
            continue;
          }
          if (!count($collection_json[$collection_info[$u]])){ //field is empty => add empty array
            $arr['field_member_of'][$y]["target_" . $collection_info[$u]] = [];
            continue;
          }
          $arr['field_member_of'][$y]["target_" . $collection_info[$u]] = count($collection_json[$collection_info[$u]][0]) == 1 ?
            $collection_json[$collection_info[$u]][0][array_keys($collection_json[$collection_info[$u]][0])[0]] :
            $collection_json[$collection_info[$u]];
        }
        $collection_taxonomies = [];
        foreach ($taxonomies as $tax){
          if (in_array("target_" .$tax, array_keys($arr['field_member_of'][$y]))) $collection_taxonomies[] = "target_" . $tax;
        }
        $this->expandTaxanomy($collection_taxonomies, $arr['field_member_of'][$y]);
      }
      bag_creation:
      $bag->createFile(json_encode($arr, JSON_PRETTY_PRINT), 'node_' . $jsonld['@graph'][0]['dcterms:title'][$x]['@language'] . ".json");
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
}
