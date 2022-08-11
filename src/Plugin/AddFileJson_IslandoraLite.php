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
class AddFileJson_IslandoraLite extends AbstractIbPlugin {

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
    $this->retreivePages($bag, $bag_temp_dir, $nid, $node_json);
    $client = new \GuzzleHttp\Client();
    //get all the media
    $json = json_decode((string) $node_json, TRUE);
    $vid = $json['vid'][0]['value'];
    $jsons = [];
    $in_bag = [];
    $file_location = -1;
    foreach ($this->settings['media_fields'] as $media_field) {
      for ($i = 0; $i < count($json[$media_field]); $i++) {
        $media_url = $this->settings['drupal_base_url'] . $json[$media_field][$i]['url'];
        //get json of each media
        $media_json = $client->request('GET', $media_url, [
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth'],
          'query' => ['_format' => 'json']
        ]);
        $media_json = json_decode((string) $media_json->getBody(), TRUE);
        $mid = $media_json['mid'][0]['value'];

        $p = 0;
        #$media_types = ['image', 'document', 'audio_file', 'video_file', 'oembed_video'];
        $media_types = $this->settings['media_types'];
        //locate the field with the media in it
        while($p < count($media_types) && !property_exists((object)$media_json, 'field_media_' .$media_types[$p])){
          $p++;
        }

        $medias = $media_json['field_media_' . $media_types[$p]];

        for ($j = 0; $j < count($medias); $j++) { //get json of each file
          if ($file_location == -1){
            $file_headers = @get_headers(str_replace("\n", "", $this->settings['drupal_base_url'] . "/entity/file/" . $medias[$j]['target_id']));
            if(!str_ends_with($file_headers[0], '404 Not Found'))
              $file_location = "/entity/file/";
            else
              $file_location = '/file/';
        }
          if (!property_exists((object)$medias[$j], 'target_id')) continue; //remote media, skip
          $file_client = new \GuzzleHttp\Client();
          $file_name = explode("/", $medias[$j]['url']);
          $file_client = new \GuzzleHttp\Client();
          $final_json = $file_client->request('GET', $this->settings['drupal_base_url'] . $file_location . $medias[$j]['target_id'], [
            'http_errors' => FALSE,
            'auth' => $this->settings['drupal_basic_auth'],
            'query' => ['_format' => 'json'],
          ]);
          $jsons[] = json_decode((string) $final_json->getBody(), TRUE);
          $fid = json_decode((string) $final_json->getBody(), TRUE)['fid'][0]['value'];
          $p = 'node_' . $nid . '/media_'. $mid . DIRECTORY_SEPARATOR . 'file_' . $fid . "/file.json";
          if (!in_array($p, $in_bag)){
            $bag->createFile((string) $final_json->getBody(), $p);
            $in_bag[] = $p;
          }

        }
        //get json for thumbnail
        $tn_client = new \GuzzleHttp\Client();
        $thumbnail_json = $tn_client->request("GET", $this->settings['drupal_base_url'] . $file_location . $media_json['thumbnail'][0]['target_id'], [//$this->settings['drupal_base_url'] . "/rest/" . end($arr), [
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth'],
          'query' => ['_format' => 'json']
        ]);

        $jsons[] = json_decode((string) $thumbnail_json->getBody(), TRUE);
        $tn_fid = json_decode((string) $thumbnail_json->getBody(), TRUE)['fid'][0]['value'];
        $p = 'node_' . $nid . '/media_' . $mid . DIRECTORY_SEPARATOR . 'file_' . $tn_fid . "/file.json";
        if (!in_array($p, $in_bag)){
          $bag->createFile((string) $thumbnail_json->getBody(), $p);
          $in_bag[] = $p;
        }

      }
    }
    $ghj = json_encode($jsons);
   // $bag->createFile($ghj, 'file.json');
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
