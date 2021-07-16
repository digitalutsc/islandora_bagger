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
class AddMediaJson_IslandoraLite extends AbstractIbPlugin
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
    //get the translations from the jsonld
    $json = json_decode((string) $node_json, TRUE);
    for ($i = 0; $i < count($json['field_preservation_master_file']); $i++) { //loop thru all media
      $media_url = $this->settings['drupal_base_url'] . $json['field_preservation_master_file'][$i]['url'];
      $jsonld = $client->request('GET', $media_url, [ //need the jsonld to get languages efficiently
        'http_errors' => FALSE,
        'auth' => $this->settings['drupal_basic_auth'],
        'query' => ['_format' => 'jsonld']
      ]);
      $jsonld = json_decode((string)$jsonld->getBody(), TRUE);
      $langs = array_key_exists('dcterms:title', $jsonld['@graph'][0]) ? //try to get the translation languages from the jsonld, otherwise, do it the hard way :(
        $jsonld['@graph'][0]['dcterms:title'] : $this->getLanguages($json['field_preservation_master_file'][$i]['url']);

      for ($j = 0; $j < count($langs); $j++){ //create the json for each language
        $curr_lang = gettype($langs[$j]) != 'string' ?
          $langs[$j]['@language'] : $langs[$j];
        $translation = $this->settings['drupal_base_url'] . DIRECTORY_SEPARATOR
          . $curr_lang .
           $json['field_preservation_master_file'][$i]['url']; //generate the url with the language code
        $url = $curr_lang == $json['langcode'][0]['value'] ? $media_url : str_replace("\n", "", $translation);
        $file_json = $client->request('GET', $url, [
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth'],
          'query' => ['_format' => 'json']
        ]);
        $filename = json_decode((string) $file_json->getBody(), TRUE)['mid'][0]['value']
          . "/media_" . $curr_lang .".json";
        $bag->createFile((string) $file_json->getBody(), $filename);
      }
    }
    return $bag;

  }

  /**
   * Gets the languages for which the given media entity has translations
   *
   * @param string $media_url
   *     The url of the relevant media entity
   *
   * @return array
   *     The list of languages for which a translation exists
   */
  protected function getLanguages(string $media_url): array {
    //check every valid language code, as specified by the Library of Congress
    // see https://www.loc.gov/standards/iso639-2/php/code_list.php for more
    $valid_langs = [];
    foreach (file("supplementary/langcodes") as $line){
      //check for a translation with each language code
      $file_headers = @get_headers(str_replace("\n", "", $this->settings['drupal_base_url'] . DIRECTORY_SEPARATOR . $line . $media_url));
      if ($file_headers && $file_headers[0] != 'HTTP/1.1 404 Not Found')
        $valid_langs[] = $line;
    }
    return $valid_langs;
  }
}
