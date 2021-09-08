# Islandora (Lite) Bagger
Modifications to the [Islandora Bagger Utility](https://github.com/mjordan/islandora_bagger) 
to allow for bagging of [Islandora Repository items](https://github.com/digitalutsc/islandora_content_type) in Drupal 9. 
For instructions on [installing](https://github.com/mjordan/islandora_bagger#installation), 
[requirements](https://github.com/mjordan/islandora_bagger#requirements), [configuration](https://github.com/mjordan/islandora_bagger#configuration),
and other information
not included here, please see the [Islandora Bagger Documentation](https://github.com/mjordan/islandora_bagger#islandora-bagger)

## Installation
1. Clone the Islandora Lite Bagger branch of this repository, `git clone -b IslandoraLiteBagger https://github.com/digitalutsc/islandora_bagger.git`
2. `cd islandora_bagger`
3. `php composer.phar install` (or equivalent on your system, e.g., `./composer install`)
4. `sudo pip3 install bagit fs fs-s3fs Pairtree PyYAML` (to install Python packages required for Post Bag Scripts)
5. Clone the OCFL tool, `git clone https://github.com/zimeon/ocfl-py.git` (required for OCFL post bag scripts) 

## Usage
### Command Line
To run the bagger on a single node with node ID `nid` and configuration file `config.yml`, use the command: 
```bash
./bin/console app:islandora_bagger:create_bag --settings=config.yml --node=nid
```
To run the bagger on multiple nodes, place the node IDs into a CSV file, `nodes.csv`, and in the `islandora_bagger` directory, run,
```bash
python3 supplementary/bagNodes.py nodes.csv
```
For more information, see [here](https://github.com/mjordan/islandora_bagger#command-line-usage).

### UI Usage (via Islandora Bagger Integration)
To run the bagger using a button on the page, install the [Islandora Bagger Integration module](https://github.com/mjordan/islandora_bagger_integration) and in the configuration file, do the following:
1. Set `serialize` to `zip`  
2. Set `output_dir` to your Drupal public file system 
3. Set `download_ocfl` to `true`
4. Under `/admin/config/islandora_bagger_integration/settings`, select "Local", enter the full paths to the configuration file and your Islandora Lite installation, and save the configuration
5. Click "Create Bag" in the Islandora Bagger Block on the node you want to bag and download the bag from the generated link. 

## Additional Plugins
### AddFile_IslandoraLite
#### Description
Adds all files (including any thumbnails) related to any media referenced
by the node to the bag. Files are stored in directories named after their FID, inside the relevant media directory.
In the case that a media entity does not have a file (e.g. references to external YouTube videos)
a text file containing the url of the resource is created.

### AddNodeJson_IslandoraLite
#### Description
Adds the JSON representation of an Islandora Repository item to the bag. All available [translations](#Translation-Support)
of the node are added to the bag in the form `node_{lang}.json` where `{lang}` 
is the corresponding language code.

### AddNodeJsonld_IslandoraLite
Adds the JSON-LD representation of an Islandora Repository item to the bag.

#### Relevant configuration information
- `relevant_taxonomies`: specifies which taxonomy fields of the node will be modified (see `taxonomy_info` for more information)

- `taxonomy_info`: specifies the fields that will be added to the taxonomies specified in `relevant_taxonomies` 
in the node JSON.

- `content_references`: specifies which entity references of the node will be modified (see `content_type_info` for more information)
  
- `content_type_info`: specifies the fields that will be added to the entity references specified in `content_references`
  in the node JSON.
  

### AddFileJson_IslandoraLite
Adds the JSON representation of each file related to media referenced by the Islandora Repository item.
File JSONs are stored in directories named after their FID, inside the relevant media directory.

### AddFileJsonld_IslandoraLite
#### Description
Adds the JSON-LD representation of the Islandora Repository item to the bag.

### AddMediaJson_IslandoraLite
#### Description
Adds the JSON representation of each media entity referenced by the node to the bag.
All available translations
of the media entity are added to the bag in the form `media_{lang}.json` where `{lang}`
is the corresponding language code.
Media JSONs are stored in directories named after their MID.

### AddMediaJsonld_IslandoraLite
Adds the JSON-LD representation of each media entity referenced by the node to the bag.
Media JSON-LDs are stored in directories named after their MID.

### AddNodeRevisionsJson
Adds the JSON representation of all revisions of the node to the bag.

### AddNodeRevisionsJsonld
Adds the JSON-LD representation of all revisions of the node to the bag.

### AddMediaRevisionsJson
Adds the JSON representation of all revisions of the node's media to the bag.

### AddMediaRevisionsJsonld
Adds the JSON-LD representation of all revisions of the node's media to the bag.


## Translation Support
This modified utility supports the serialization of all translations of an Islandora Repository item and its 
media. Support is offered for all [language codes registered by the Library of Congress](https://www.loc.gov/standards/iso639-2/php/code_list.php).
If you wish to add support for another language not already registered by the Library of Congress, do so by adding 
the two-letter language code to `supplementary/langcodes` (do `echo [code]>>supplementary/langcodes`, where `[code]` is the 
two letter language code)

## Post Bag Scripts
Three post bag scripts are included but you may add more following the instructions [here](https://github.com/mjordan/islandora_bagger#post-bag-scripts).
### rename
#### Description
Renames the bag according to the following format: `{nid}_{uuid}_{namespace}`.
To use, set `serialize` to `false` or `zip`
#### Relevant configuration information
- `namespace`: (found under `bag-info`) specifies the namespace used for naming the bag

### validate
#### Description
Creates an [OCFL object](https://ocfl.io/1.0/spec/) from the bag and validates it. 
The OCFL object will be created in the `islandora_bagger` directory according to the 
same naming convention as the bag (`{nid}_{uuid}_{namespace}`). If you will be using both the `rename`
The result of the validation script can be found in the output directory under the name `validation_results_node_{nid}`.
This script uses the [oclf-py tool](https://github.com/zimeon/ocfl-py).
To use, set `serialize` to `false` or `zip`

#### Relevant configuration information
- `Namespace`: (found under `bag-info`) specifies the namespace used for naming the OCFL object
- `Contact-Name`: (found under `bag-info`) specifies the username that will be placed in the `inventory.json`
- `Contact-Email`: (found under `bag-info`) specifies the user-address that will be placed in the `inventory.json`
- `Message`: (found under `bag-info`) an optional message to be added to the `inventory.json`

<!---
### uiIntegration
#### Description
Copies the contents of the output directory into the Drupal public file system to allow for support of the [Islandora 
Bagger UI](https://github.com/mjordan/islandora_bagger_integration). If you do not want to also have a copy of your
files in a directory outside of the Drupal public file system, you may simply set the `output_dir` to Drupal public file system path
and omit this script.

#### Relevant configuration information 
- `output_dir`: the original location of the bag
- `drupal_public_dir`: the full path to the Drupal public file system, can be found under `/admin/config/media/file-system` 


## Islandora Bagger Integration
The Islandora Lite Bagger allows for bagging nodes directly from their page via the 
[Islandora Bagger Integration module](https://github.com/mjordan/islandora_bagger_integration).
To setup bagging directly from the Drupal user interface, follow these instructions.
1. [Install](https://github.com/mjordan/islandora_bagger_integration#installation) and [configure](https://github.com/mjordan/islandora_bagger_integration#configuration) the Islandora Bagger Integration module.
2. In the configuration file, do the following:
  
    1. Set `serialize` to zip or tar.
    2. Set `drupal_public_dir` to your Drupal public file system
    3. Set `post_bag_scripts` to `["python3 src/PostBagScripts/uiIntegration.py"]`
3. Under `/admin/config/islandora_bagger_integration/settings`, select "Local", enter the full paths to the configuration file and your Islandora Lite installation, and save the configuration
4. Click "Create Bag" in the Islandora Bagger Block on the node you want to bag and download the bag from the generated link. 
-->

## Bag Structure
```text
/tmp/112_2fa72847-287e-462e-adb8-410bb0ad1dea_dsu
├── bag-info.txt
├── bagit.txt
├── data
│   ├──node_112
│   │   ├──media_18
│   │   │    ├──file_32
│   │   │    ├──file_64
│   │   │    │    ├── file.txt
│   │   │    │    ├── thumbnail.jpeg
│   │   │    │    ├── file_en.json
│   │   │    │    └── file.jsonld
│   │   │    ├── media_en.v1.json
│   │   │    ├── media_en.v2.json
│   │   │    ├── media.v1.jsonld
│   │   │    └── media.v2.jsonld
│   │   ├──media_20
│   │   ├── node_en.v1.json
│   │   ├── node_en.v2.json
│   │   ├── node_fr.v1.json
│   │   ├── node_fr.v2.json
│   │   ├── node.v1.jsonld
│   │   └── node.v2.jsonld
│   └──node_113
├── manifest-sha1.txt
└── tagmanifest-sha1.txt
```