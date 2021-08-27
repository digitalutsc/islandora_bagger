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
class AddRevisions extends AbstractIbPlugin {

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
  public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json) {
    $client = new \GuzzleHttp\Client();
    $json = json_decode((string) $node_json, TRUE);
    $uuid = $json['uuid'][0]['value'];
    #construct the url 
    $curr_ver = $json['vid'][0]['value'] - 1;
    $reachedEnd = false;
    $reachedValid = false;
    #node revisions
    while ($curr_ver >= $nid) {
      $rev_url = $this->settings['drupal_base_url'] . '/jsonapi/node/page/' . $uuid . "/?resourceVersion=id:" . $curr_ver;
      $file_headers = @get_headers(str_replace("\n", "", $rev_url));
      if ($file_headers && !str_ends_with($file_headers[0], '404 Not Found')){
       # echo 'adding version ' . $curr_ver . "\n";
        $rev_json = $client->request('GET', $rev_url,[
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth']
        ]);
        $rev_json = json_decode((string) $rev_json->getBody(), TRUE);
        $lang_code = $rev_json['data']['attributes']['langcode'];
       $bag->createFile(json_encode($rev_json, JSON_PRETTY_PRINT), 'node_' . $nid . '/node_' . $lang_code . '.v' . $curr_ver . '.json');
      }
      $curr_ver = $curr_ver -1;
    }
    #media revisions
    for ($i=0; $i < count($json['field_preservation_master_file']); $i++) { 
      $media_client = new \GuzzleHttp\Client();
      $media_url = $this->settings['drupal_base_url'] . $json['field_preservation_master_file'][$i]['url'];
      $media_json = $media_client->request('GET', $media_url, [ //need the jsonld to get languages efficiently
        'http_errors' => FALSE,
        'auth' => $this->settings['drupal_basic_auth'],
        'query' => ['_format' => 'json']
      ]);

      $media_json = json_decode((string) $media_json->getBody(), TRUE);
      $media_vid = $media_json['vid'][0]['value'] - 1;
      $mid = $media_json['mid'][0]['value'];
      $index = count(array_keys($media_json)) - 1;
      while($index > 0 && !str_starts_with(array_keys($media_json)[$index], 'field_media_')){
        $index = $index - 1;
      }
      $media_type = explode('field_media_', array_keys($media_json)[$index])[1];
      $media_uuid = $json['field_preservation_master_file'][$i]['target_uuid'];
      #construct the url for JSON:API get call
      while ($media_vid >= $mid) {
        $media_url = $this->settings['drupal_base_url'] . '/jsonapi/media/' . $media_type . 
          DIRECTORY_SEPARATOR . $media_uuid . "/?resourceVersion=id:" . $media_vid; 
        $media_file_headers = @get_headers(str_replace("\n", "", $media_url));
        if ($file_headers && !str_ends_with($media_file_headers[0], '404 Not Found')){
          $media_rev_json = $media_client->request('GET', $media_url,[
            'http_errors' => FALSE,
            'auth' => $this->settings['drupal_basic_auth']
          ]);
          $media_rev_json = json_decode((string) $media_rev_json->getBody(), TRUE);
        //  print_r($media_rev_json);
          $media_lang_code = $media_rev_json['data']['attributes']['langcode'];
          $bag->createFile(json_encode($media_rev_json, JSON_PRETTY_PRINT), 'node_' . $nid . '/media_' . $mid . '/media_' . $media_lang_code . '.v' . $media_vid . '.json');
        }
        
        
        $media_vid = $media_vid - 1;
        
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
