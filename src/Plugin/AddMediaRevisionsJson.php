<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

use function GuzzleHttp\json_encode;

/**
 * Adds the JSON representation of the Islandora object's media revisions to the Bag.
 */
class AddMediaRevisionsJson extends AbstractIbPlugin {

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

    foreach ($this->settings['media_fields'] as $media_field) {
      for ($i=0; $i < count($json[$media_field]); $i++) { 

        $url = $this->settings['drupal_base_url'] . $json[$media_field][$i]['url'];
        $client = new \GuzzleHttp\Client();
        $media_json = $client->request('GET', $url, [
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth'],
          'query' => ['_format' => 'json']
        ]);
        $media_json = json_decode((string) $media_json->getBody(), TRUE);
        $original_vid = $media_json['vid'][0]['value'];

        $mid = $json[$media_field][$i]['target_id'];
        $url = $this->settings['drupal_base_url'] . $json[$media_field][$i]['url'] . '/revisions/rest';
        $client = new \GuzzleHttp\Client();
        $rev_json = $client->request('GET', $url, [
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth'],
          'query' => ['_format' => 'json']
        ]);
        $rev_json = json_decode((string) $rev_json->getBody(), TRUE);
        for ($j=0; $j < count($rev_json); $j++) {
          $lang_code = $rev_json[$j]['langcode'][0]['value'];
          $vid = $rev_json[$j]['vid'][0]['value'];
          if($vid != $original_vid) #the current revision is handled by another plugin
            $bag->createFile(json_encode($rev_json[$j],JSON_PRETTY_PRINT), 'node_' . $nid . '/media_' . $mid . '/media_' . $lang_code . '.v' . $vid . '.json');
        }
      }
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

}
