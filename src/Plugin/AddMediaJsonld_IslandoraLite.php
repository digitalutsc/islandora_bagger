<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

/**
 * Adds the JSON representation of the Islandora object's media to the Bag.
 */
class AddMediaJsonld_IslandoraLite extends AbstractIbPlugin
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
   */
  public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json, $token = NULL)
  {
    $this->retreivePages($bag, $bag_temp_dir, $nid, $node_json);
    $client = new \GuzzleHttp\Client();
/*     $string = $this->settings['drupal_base_url'] . '/node/' . $nid;
    $x = $client->request('GET', $string, [
      'http_errors' => FALSE,
      'auth' => $this->settings['drupal_basic_auth'],
      'query' => ['_format' => 'json'],
    ]); */
    $json = json_decode($node_json, TRUE);
    $contents = "[";
    $result = [];
    foreach ($this->settings['media_fields'] as $media_field) {
      for ($i = 0; $i < count($json[$media_field]); $i++) {
        $media_url = $this->settings['drupal_base_url'] . $json[$media_field][$i]['url'];
        $mid = $json[$media_field][$i]['target_id'];
        $file_json = $client->request('GET', $media_url, [
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth'],
          'query' => ['_format' => 'jsonld']
        ]);
        if ($i != 0) {
          $contents = $contents . ", ";
        }
        $contents = $contents . (string) $file_json->getBody();
        //$result[] = json_decode((string) $file_json->getBody(), TRUE);
        $result = json_decode((string) $file_json->getBody(), TRUE);
        $arr = explode('media/', json_decode((string) $file_json->getBody(), TRUE)['@graph'][0]['@id']);
      # $mid = explode("?_format=", end($arr))[0];
        $bag->createFile((string)$file_json->getBody(), 'node_' . $nid . '/media_' . $mid . "/media.jsonld");
      }
    }
    $contents = $contents . "]";
   // $bag->createFile($contents, 'media.jsonld');
    //$bag->createFile(json_encode($result), 'media.jsonld');

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
