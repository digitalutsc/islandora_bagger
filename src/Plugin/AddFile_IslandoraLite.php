<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

/**
 * Adds a node's media to the Bag.
 */
class AddFile_IslandoraLite extends AbstractIbPlugin
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
   * Adds a node's media to the Bag.
   */
  public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json)
  {

    $media_client = new \GuzzleHttp\Client();
    $json = json_decode((string) $node_json, TRUE);
    $added_to_bag = [];

    //get media json
    $file_client = new \GuzzleHttp\Client();
    //get json for each media entity
    for($i = 0; $i < count($json['field_preservation_master_file']); $i++){
      $media_url = $this->settings['drupal_base_url'] . $json['field_preservation_master_file'][$i]['url'];
      $file_json = $media_client->request('GET', $media_url, [
        'http_errors' => FALSE,
        'auth' => $this->settings['drupal_basic_auth'],
        'query' => ['_format' => 'json'],
      ]);
      $x = json_decode((string) $file_json->getBody(), TRUE);
      $p = 0;
      $media_types = $this->settings['media_types'];
      while($p < count($media_types) && !property_exists((object)$x, 'field_media_' . $media_types[$p])){
        $p++;
      }

      //get json for each file in each media entity
      if($x['bundle'][0]['target_id'] != 'remote_video'){

        $arr = explode("/", $x['field_media_' . $media_types[$p]][0]['url']);//end($x)[0]['url']);
        $foldername = end($arr);
        for ($j = 0; $j < count($x['field_media_' . $media_types[$p]]); $j++){//count(end($x)); $j++){
          $file_response = $file_client->get($x['field_media_' . $media_types[$p]][$j]['url'], ['stream' => true,//end($x)[$j]['url'], ['stream' => true,
            'timeout' => $this->settings['http_timeout'],
            'connect_timeout' => $this->settings['http_timeout'],
            'verify' => $this->settings['verify_ca']
          ]);

          //may need to actually loop thru and find the relevant field
          $arr = explode("/", $x['field_media_' . $media_types[$p]][$j]['url']);//end($x)[$j]['url']);
          $filename = end($arr);
        //  $filename = $x["mid"][0]["value"];
          $temp_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $filename; //change file name to media id
          $temp_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $x["mid"][0]["value"] . "_" . $filename;
          if (file_exists($temp_file_path)) continue;

          $file_body = $file_response->getBody();
          while (!$file_body->eof()) {
            file_put_contents($temp_file_path, $file_body->read(2048), FILE_APPEND);
          }
          $path = "/media/" . $foldername . DIRECTORY_SEPARATOR . "media_content/". $filename;
          $fid = $x['field_media_' . $media_types[$p]][$j]['target_id'];
          $path = DIRECTORY_SEPARATOR . $x["mid"][0]["value"] . DIRECTORY_SEPARATOR . $fid . DIRECTORY_SEPARATOR .$filename;

          //!file_exists($temp_file_path) &&
          if (!in_array($path, $added_to_bag)){
            $bag->addFile($temp_file_path, $path);
            $added_to_bag[] = $path;

          }
        }
      }
      else { //remote media
        //no file to download, create text file with video url instead
        $video_url = $x['field_media_oembed_video'][0]['value'];
        $foldername = $x['name'][0]['value'];
        $path = "/media/" . $foldername . DIRECTORY_SEPARATOR . "media_content/video_url";
        $path = DIRECTORY_SEPARATOR . $x["mid"][0]["value"] . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR ."video_url";
        if (!in_array($path, $added_to_bag)){
          $bag->createFile($video_url, $path);
          $added_to_bag[] = $path;
        }
      }


      //repeat for thumbnail
      $file_response = $file_client->get($x['thumbnail'][0]['url'], ['stream' => true,
        'timeout' => $this->settings['http_timeout'],
        'connect_timeout' => $this->settings['http_timeout'],
        'verify' => $this->settings['verify_ca']
      ]);
      $tn_arr = explode("/", $x['thumbnail'][0]['url']);
      $tn_name = end($tn_arr);
      $temp_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $tn_name;

     // if (file_exists($temp_file_path)) continue;
    //  $temp_file_path = $bag_temp_dir . DIRECTORY_SEPARATOR . $x["mid"][0]["value"] . DIRECTORY_SEPARATOR . $tn_name;
      $file_body = $file_response->getBody();
      while (!$file_body->eof()) {
        file_put_contents($temp_file_path, $file_body->read(2048), FILE_APPEND);
      }

      // $path = "/media/" . $withoutExt . '(' . $ext . ')' . DIRECTORY_SEPARATOR . 'thumbnails/'. $tn_name;
      $path = "/media/" . $foldername . DIRECTORY_SEPARATOR . 'thumbnail/'. $tn_name;

      $path = DIRECTORY_SEPARATOR .  $x["mid"][0]["value"] . DIRECTORY_SEPARATOR . $x['thumbnail'][0]['target_id'] . DIRECTORY_SEPARATOR .$tn_name;

      // var_dump($bag);
      if (!in_array($path, $added_to_bag)){
        $bag->addFile($temp_file_path, $path);
        $added_to_bag[] = $path;
      }
    }
    return $bag;
  }

  protected function getFilenameFromUrl($url)
  {
    $path = parse_url($url, PHP_URL_PATH);
    $filename = pathinfo($path, PATHINFO_BASENAME);
    return $filename;
  }

  protected function fetchTermInfo($term)
  {
    $client = new \GuzzleHttp\Client();
    $url = $this->settings['drupal_base_url'] . $term;
    $response = $client->request('GET', $url, [
      'http_errors' => false,
      'auth' => $this->settings['drupal_basic_auth'],
      'query' => ['_format' => 'json']
    ]);
    $body = (string) $response->getBody();
    $tag_info = json_decode($body, true);
    return $tag_info;
  }
}

