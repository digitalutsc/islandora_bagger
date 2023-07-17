<?php

/**
 * @file
 * Extends AbstractIbPlugin class.
 */

namespace App\Plugin;

use whikloj\BagItTools\Bag;

/**
 * Adds Drupal's JSON-LD representation of the Islandora object to the Bag.
 */
class AddNodeJsonld_IslandoraLite extends AbstractIbPlugin
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
     * Adds Drupal's JSON-LD representation of the Islandora object to the Bag.
     */
    public function execute(Bag $bag, $bag_temp_dir, $nid, $node_json, $token = NULL)
    {
        $this->retreivePages($bag, $bag_temp_dir, $nid, $node_json);
        $arr = json_decode($node_json, TRUE);
        $vid = $arr['vid'][0]['value'];
        $client = new \GuzzleHttp\Client();
        $url = $this->settings['drupal_base_url'] . '/node/' . $nid;
        $response = $client->request('GET', $url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'http_errors' => false,
            'query' => ['_format' => 'jsonld']
        ]);
        $node_jsonld = (string) $response->getBody();
        $filename = 'node.v' . $vid . ".jsonld";
        $bag->createFile($node_jsonld, 'node_' . $nid . '/' . $filename);
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
