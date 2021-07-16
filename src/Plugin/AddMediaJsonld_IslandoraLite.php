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
  public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json)
  {
    $client = new \GuzzleHttp\Client();
    $string = $this->settings['drupal_base_url'] . '/node/' . $nid;
    $x = $client->request('GET', $string, [
      'http_errors' => FALSE,
      'auth' => $this->settings['drupal_basic_auth'],
      'query' => ['_format' => 'json'],
    ]);
    $json = json_decode((string) $x->getBody(), TRUE);
    $contents = "[";
    $result = [];
    for ($i = 0; $i < count($json['field_preservation_master_file']); $i++) {
      $media_url = $this->settings['drupal_base_url'] . $json['field_preservation_master_file'][$i]['url'];
      $file_json = $client->request('GET', $media_url, [
        'http_errors' => FALSE,
        'auth' => $this->settings['drupal_basic_auth'],
        'query' => ['_format' => 'jsonld'],
      ]);
      if ($i != 0) {
        $contents = $contents . ", ";
      }
      $contents = $contents . (string) $file_json->getBody();
      //$result[] = json_decode((string) $file_json->getBody(), TRUE);
      $result = json_decode((string) $file_json->getBody(), TRUE);
      $arr = explode('media/', json_decode((string) $file_json->getBody(), TRUE)['@graph'][0]['@id']);
      $mid = explode("?_format=", end($arr))[0];
      $bag->createFile((string)$file_json->getBody(), $mid . "/media.jsonld");
    }
    $contents = $contents . "]";
   // $bag->createFile($contents, 'media.jsonld');
    //$bag->createFile(json_encode($result), 'media.jsonld');

    return $bag;
  }
}
