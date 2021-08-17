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
4. `pip3 install bagit fs fs-s3fs Pairtree PyYAML` (to install Python packages required for Post Bag Scripts)
5. Clone the OCFL tool, `git clone https://github.com/zimeon/ocfl-py.git` (required for OCFL post bag scripts) 

## Usage
As of now, the Islandora Lite Bagger only supports command line usage. To run the bagger
on a single node with node ID `nid` and configuration file `config.yml`, use the command: 
```bash
./bin/console app:islandora_bagger:create_bag --settings=config.yml --node=nid
```
To run the bagger on multiple nodes, place the node IDs into a CSV file, `nodes.csv`, and in the `islandora_bagger` directory, run,
```bash
python3 supplementary/bagNodes.py nodes.csv
```
For more information, see [here](https://github.com/mjordan/islandora_bagger#command-line-usage).

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
and `validate` scripts, you ***must*** place the `rename` script ***after*** the `validate` script.
This script uses the [oclf-py tool](https://github.com/zimeon/ocfl-py).
To use, set `serialize` to `false` or `zip`

#### Relevant configuration information
- `Namespace`: (found under `bag-info`) specifies the namespace used for naming the OCFL object
- `Contact-Name`: (found under `bag-info`) specifies the username that will be placed in the `inventory.json`
- `Contact-Email`: (found under `bag-info`) specifies the user-address that will be placed in the `inventory.json`
- `Message`: (found under `bag-info`) an optional message to be added to the `inventory.json`

### uiIntegration
#### Description
Copies the contents of the output directory into the Drupal public file system to allow for support of the [Islandora 
Bagger UI](https://github.com/mjordan/islandora_bagger_integration). If you do not want to also have a copy of your
files in a directory outside of the Drupal public file system, you may simply set the `output_dir` to Drupal public file system path
and omit this script.

#### Relevant configuration information 
- `output_dir`: the original location of the bag
- `drupal_public_dir`: the full path to the Drupal public file system, can be found under `/admin/config/media/file-system` 

## Bag Structure
```text
/tmp/112_2fa72847-287e-462e-adb8-410bb0ad1dea_dsu
├── bag-info.txt
├── bagit.txt
├── data
│   ├──112
│   │   ├── 18
│   │   │    ├── 32
│   │   │    ├── 64
│   │   │    │    ├── file.txt
│   │   │    │    ├── thumbnail.jpeg
│   │   │    │    ├── file_en.json
│   │   │    │    └── file.jsonld
│   │   │    ├── media_en.json
│   │   │    ├── media_en.json
│   │   │    └── media.jsonld
│   │   ├── 20
│   │   ├── node_en.json
│   │   ├── node_fr.json
│   │   └── node.jsonld
│   └──113
├── manifest-sha1.txt
└── tagmanifest-sha1.txt
```
where,
- `112`: the NID of the node being bagged
- `2fa72847-287e-462e-adb8-410bb0ad1dea`: the UUID of the node being bagged
- `113`: the NID of a page belonging node 112, when expanded, will follow the same strucutre as that of `112` 
- `dsu`: the namespace of the node being bagged  
- `18` & `20`: the MID of the media entities referenced by the node
- `32` & `64`: the FID of the files belonging to media entity `18`
