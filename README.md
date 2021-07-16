# Islandora (Lite) Bagger
Modifications to the [Islandora Bagger Utility](https://github.com/mjordan/islandora_bagger) 
to allow for bagging of [Islandora Repository items](https://github.com/digitalutsc/islandora_content_type) in Drupal 9. 
For instructions on [installing](https://github.com/mjordan/islandora_bagger#installation), 
[requirements](https://github.com/mjordan/islandora_bagger#requirements), [configuration](https://github.com/mjordan/islandora_bagger#configuration),
and other information
not included here, please see the [Islandora Bagger Documentation](https://github.com/mjordan/islandora_bagger#islandora-bagger)

## Usage
As of now, the Islandora Lite Bagger only supports command line usage. To run the bagger
on node `nid` with configuration file `config.yml`, use the command 
```bash
./bin/console app:islandora_bagger:create_bag --settings=config.yml --node=nid
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

#### Relevant configuration information
- `relevant_taxonomies`: specifies which taxonomy fields of the node will be modified (see `taxonomy_info` for more information)

- `taxonomy_info`: specifies the fields that will be added to the taxonomies specified in `relevant_taxonomies` 
in the node JSON.
  
- `collection_info`: specifies the fields that will be added to the node's collection information (i.e. to `field_member_of`)
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
Two post bag (bash) scripts are included but you may add more following the instructions [here](https://github.com/mjordan/islandora_bagger#post-bag-scripts).
### rename
#### Description
Renames the bag according to the following format: `{nid}_{uuid}_{namespace}`
#### Relevant configuration information
- `namespace`: (found under `bag-info`) specifies the namespace used for naming the bag

### validate
#### Description
Creates an [OCFL object](https://ocfl.io/1.0/spec/) from the bag and validates it. 
The OCFL object will be created in the `islandora_bagger` directory according to the 
same naming convention as the bag (`{nid}_{uuid}_{namespace}`). If you will be using both the `rename`
and `validate` scripts, you ***must*** place the `rename` script ***after*** the `validate` script.
This script uses the [oclf-py tool](https://github.com/zimeon/ocfl-py).
#### Relevant configuration information
- `Namespace`: (found under `bag-info`) specifies the namespace used for naming the OCFL object
- `Contact-Name`: (found under `bag-info`) specifies the username that will be placed in the `inventory.json`
- `Contact-Email`: (found under `bag-info`) specifies the user-address that will be placed in the `inventory.json`
- `Message`: (found under `bag-info`) an optional message to be added to the `inventory.json`
## Bag Structure
```text
/tmp/112_2fa72847-287e-462e-adb8-410bb0ad1dea_dsu
├── bag-info.txt
├── bagit.txt
├── data
│   ├── 18
│   │   └── 32
│   │   └── 64
│   │   │    └── file.txt
│   │   │    └── thumbnail.jpeg
│   │   │    └── file_en.json
│   │   │    └── file.jsonld
│   │   ├── media_en.json
│   │   ├── media_en.json
│   │   └── media.jsonld
│   ├── 20
│   ├── node_en.json
│   ├── node_fr.json
│   └── node.jsonld
├── manifest-sha1.txt
└── tagmanifest-sha1.txt
```
where,
- `112`: the NID of the node being bagged 
- `2fa72847-287e-462e-adb8-410bb0ad1dea`: the UUID of the node being bagged
- `dsu`: the namespace of the node being bagged  
- `18` & `20`: the MID of the media entities referenced by the node
- `32` & `64`: the FID of the files belonging to media entity `18`
