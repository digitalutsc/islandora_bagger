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
    $this->retreivePages($bag, $bag_temp_dir, $nid, $node_json);
    $client = new \GuzzleHttp\Client();
    $added_names = [];
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
        $jsonld['@graph'][0]['dcterms:title'] : $this->getLanguages($json['field_preservation_master_file'][$i]['target_id'], $client);
      if (!count($langs) && $json['default_langcode'][0]['value'])// no languages found
        $langs[] = $json['langcode'][0]['value']; //set to default language
      else if (!count($langs))
        $langs[] = ''; // if all else fails, do not add language indicator
        for ($j = 0; $j < count($langs); $j++){ //create the json for each language
        $curr_lang = gettype($langs[$j]['langcode']) != 'string' ?
          $langs[$j]['@language'] : $this->getLocaleCodeForDisplayLanguage($langs[$j]['langcode']);
        $translation = $this->settings['drupal_base_url'] . DIRECTORY_SEPARATOR
          . $curr_lang .
           $json['field_preservation_master_file'][$i]['url']; //generate the url with the language code
        $url = $curr_lang == $json['langcode'][0]['value'] ? $media_url : str_replace("\n", "", $translation);
        $file_json = $client->request('GET', $url, [
          'http_errors' => FALSE,
          'auth' => $this->settings['drupal_basic_auth'],
          'query' => ['_format' => 'json']
        ]);
        $vid = json_decode((string) $file_json->getBody(), TRUE)['vid'][0]['value'];
        $filename = 'node_' . $nid . DIRECTORY_SEPARATOR . 'media_' . json_decode((string) $file_json->getBody(), TRUE)['mid'][0]['value']
          . "/media_" . $curr_lang . '.v' . $vid . ".json";
        if (!in_array($filename, $added_names)) {
          $bag->createFile((string) $file_json->getBody(), $filename);
          $added_names[] = $filename;
        }
      }
    }
    return $bag;

  }

  function getLocaleCodeForDisplayLanguage($name){
    $languageCodes = array(
      'Afar' => 'aa',
      'Abkhazian' => 'ab',
      'Avestan' => 'ae',
      'Afrikaans' => 'af',
      'Akan' => 'ak',
      'Amharic' => 'am',
      'Aragonese' => 'an',
      'Arabic' => 'ar',
      'Assamese' => 'as',
      'Avaric' => 'av',
      'Aymara' => 'ay',
      'Azerbaijani' => 'az',
      'Bashkir' => 'ba',
      'Belarusian' => 'be',
      'Bulgarian' => 'bg',
      'Bihari' => 'bh',
      'Bislama' => 'bi',
      'Bambara' => 'bm',
      'Bengali' => 'bn',
      'Tibetan' => 'bo',
      'Breton' => 'br',
      'Bosnian' => 'bs',
      'Catalan' => 'ca',
      'Chechen' => 'ce',
      'Chamorro' => 'ch',
      'Corsican' => 'co',
      'Cree' => 'cr',
      'Czech' => 'cs',
      'Church Slavic' => 'cu',
      'Chuvash' => 'cv',
      'Welsh' => 'cy',
      'Danish' => 'da',
      'German' => 'de',
      'Divehi' => 'dv',
      'Dzongkha' => 'dz',
      'Ewe' => 'ee',
      'Greek' => 'el',
      'English' => 'en',
      'Esperanto' => 'eo',
      'Spanish' => 'es',
      'Estonian' => 'et',
      'Basque' => 'eu',
      'Persian' => 'fa',
      'Fulah' => 'ff',
      'Finnish' => 'fi',
      'Fijian' => 'fj',
      'Faroese' => 'fo',
      'French' => 'fr',
      'Western Frisian' => 'fy',
      'Irish' => 'ga',
      'Scottish Gaelic' => 'gd',
      'Galician' => 'gl',
      'Guarani' => 'gn',
      'Gujarati' => 'gu',
      'Manx' => 'gv',
      'Hausa' => 'ha',
      'Hebrew' => 'he',
      'Hindi' => 'hi',
      'Hiri Motu' => 'ho',
      'Croatian' => 'hr',
      'Haitian' => 'ht',
      'Hungarian' => 'hu',
      'Armenian' => 'hy',
      'Herero' => 'hz',
      'Interlingua (International Auxiliary Language Association)' => 'ia',
      'Indonesian' => 'id',
      'Interlingue' => 'ie',
      'Igbo' => 'ig',
      'Sichuan Yi' => 'ii',
      'Inupiaq' => 'ik',
      'Ido' => 'io',
      'Icelandic' => 'is',
      'Italian' => 'it',
      'Inuktitut' => 'iu',
      'Japanese' => 'ja',
      'Javanese' => 'jv',
      'Georgian' => 'ka',
      'Kongo' => 'kg',
      'Kikuyu' => 'ki',
      'Kwanyama' => 'kj',
      'Kazakh' => 'kk',
      'Kalaallisut' => 'kl',
      'Khmer' => 'km',
      'Kannada' => 'kn',
      'Korean' => 'ko',
      'Kanuri' => 'kr',
      'Kashmiri' => 'ks',
      'Kurdish' => 'ku',
      'Komi' => 'kv',
      'Cornish' => 'kw',
      'Kirghiz' => 'ky',
      'Latin' => 'la',
      'Luxembourgish' => 'lb',
      'Ganda' => 'lg',
      'Limburgish' => 'li',
      'Lingala' => 'ln',
      'Lao' => 'lo',
      'Lithuanian' => 'lt',
      'Luba-Katanga' => 'lu',
      'Latvian' => 'lv',
      'Malagasy' => 'mg',
      'Marshallese' => 'mh',
      'Maori' => 'mi',
      'Macedonian' => 'mk',
      'Malayalam' => 'ml',
      'Mongolian' => 'mn',
      'Marathi' => 'mr',
      'Malay' => 'ms',
      'Maltese' => 'mt',
      'Burmese' => 'my',
      'Nauru' => 'na',
      'Norwegian Bokmal' => 'nb',
      'North Ndebele' => 'nd',
      'Nepali' => 'ne',
      'Ndonga' => 'ng',
      'Dutch' => 'nl',
      'Norwegian Nynorsk' => 'nn',
      'Norwegian' => 'no',
      'South Ndebele' => 'nr',
      'Navajo' => 'nv',
      'Chichewa' => 'ny',
      'Occitan' => 'oc',
      'Ojibwa' => 'oj',
      'Oromo' => 'om',
      'Oriya' => 'or',
      'Ossetian' => 'os',
      'Panjabi' => 'pa',
      'Pali' => 'pi',
      'Polish' => 'pl',
      'Pashto' => 'ps',
      'Portuguese' => 'pt',
      'Quechua' => 'qu',
      'Raeto-Romance' => 'rm',
      'Kirundi' => 'rn',
      'Romanian' => 'ro',
      'Russian' => 'ru',
      'Kinyarwanda' => 'rw',
      'Sanskrit' => 'sa',
      'Sardinian' => 'sc',
      'Sindhi' => 'sd',
      'Northern Sami' => 'se',
      'Sango' => 'sg',
      'Sinhala' => 'si',
      'Slovak' => 'sk',
      'Slovenian' => 'sl',
      'Samoan' => 'sm',
      'Shona' => 'sn',
      'Somali' => 'so',
      'Albanian' => 'sq',
      'Serbian' => 'sr',
      'Swati' => 'ss',
      'Southern Sotho' => 'st',
      'Sundanese' => 'su',
      'Swedish' => 'sv',
      'Swahili' => 'sw',
      'Tamil' => 'ta',
      'Telugu' => 'te',
      'Tajik' => 'tg',
      'Thai' => 'th',
      'Tigrinya' => 'ti',
      'Turkmen' => 'tk',
      'Tagalog' => 'tl',
      'Tswana' => 'tn',
      'Tonga' => 'to',
      'Turkish' => 'tr',
      'Tsonga' => 'ts',
      'Tatar' => 'tt',
      'Twi' => 'tw',
      'Tahitian' => 'ty',
      'Uighur' => 'ug',
      'Ukrainian' => 'uk',
      'Urdu' => 'ur',
      'Uzbek' => 'uz',
      'Venda' => 've',
      'Vietnamese' => 'vi',
      'Volapuk' => 'vo',
      'Walloon' => 'wa',
      'Wolof' => 'wo',
      'Xhosa' => 'xh',
      'Yiddish' => 'yi',
      'Yoruba' => 'yo',
      'Zhuang' => 'za',
      'Chinese' => 'zh',
      'Zulu' => 'zu'
    );
    return $languageCodes[$name];
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
  protected function getLanguages($mid, $c): array {
    $translations_url = $this->settings['drupal_base_url'] . '/media/' . $mid . '/translations';
    $res = $c->request('GET', $translations_url, [ //need the jsonld to get languages efficiently
      'http_errors' => FALSE,
      'auth' => $this->settings['drupal_basic_auth'],
      'query' => ['_format' => 'json']
    ]);
    $res = json_decode((string)$res->getBody(), TRUE);
    return $res;
    //check every valid language code, as specified by the Library of Congress
    // see https://www.loc.gov/standards/iso639-2/php/code_list.php for more
/*     $valid_langs = [];
    foreach (file("supplementary/langcodes") as $line){
      //check for a translation with each language code
      $file_headers = @get_headers(str_replace("\n", "", $this->settings['drupal_base_url'] . DIRECTORY_SEPARATOR . $line . $media_url));
      if ($file_headers && !str_ends_with($file_headers[0], '404 Not Found'))
        $valid_langs[] = $line;
    }
    return $valid_langs; */
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
