"""
This script creates and validates OCFL objects from bags
"""
import os
import sys
import yaml
import re
import json
import zipfile
import shutil
from pathlib import Path
#parse configuration file
with open(sys.argv[3]) as f:
    yml = yaml.safe_load(f)

#clean up directories from failed exits
if os.path.isdir(yml['output_dir'] + '/validate_temps'):
    shutil.rmtree(yml['output_dir'] + '/validate_temps')

if os.path.isdir(sys.argv[2]) or os.path.isfile(sys.argv[2]): #no renaming has occured
    # check for archived file
    if not isinstance(yml['serialize'], bool):
        parent_dir = yml['output_dir']
        directory = "validate_temps"
        path = os.path.join(parent_dir, directory)
        os.mkdir(path)
        with zipfile.ZipFile(sys.argv[2], 'r') as zip_ref:
            zip_ref.extractall(path)
        srcdir = yml['output_dir'] + '/validate_temps/' + sys.argv[1]
    else:
        srcdir = sys.argv[2]
else: #the bag has already undergone renaming
    if not isinstance(yml['serialize'], bool): #check for archived file
        parent_dir = yml['output_dir']
        directory = "validate_temps"
        path = os.path.join(parent_dir, directory)
        os.mkdir(path)
        #find renamed zip file
        temp = os.listdir(yml['output_dir'])
        i = 0
        while not (temp[i].startswith(sys.argv[1]) and temp[i].endswith('.zip')):
            i+=1
        with zipfile.ZipFile(yml['output_dir'] + '/' + temp[i], 'r') as zip_ref:
            zip_ref.extractall(path)
        srcdir = yml['output_dir'] + '/validate_temps/' + sys.argv[1] # os.path.splitext(temp[i])[0]
    else:
        # srcdir = sys.argv[2]
        output = os.listdir(yml['output_dir'])
        i = 0
        while not output[i].startswith(sys.argv[1]+'_'): #may need to change this
            i += 1
        srcdir = yml['output_dir'] + "/" + output[i]

dirs = os.listdir(srcdir + "/data/node_" + sys.argv[1])
for file in dirs:
    if re.search('node_', file):
        final = file
        break
json_file = open(srcdir + "/data/node_" + sys.argv[1] + '/' + final)
contents = json_file.read()
json_file.close()
json = json.loads(contents)
name = '"' + yml['bag-info']['Contact-Name'] + '"'
message = '"' + yml['bag-info']['Message'] + '"'
address = yml['bag-info']['Contact-Email']
Namespace = yml['bag-info']['Namespace']
nid = sys.argv[1]
uuid = json['uuid'][0]['value']
id = nid + ':' + uuid + ':' + Namespace
if yml['download_ocfl']:
    objdir = yml['output_dir'] + '/' + id
    if os.path.isfile(yml['output_dir'] + '/' + nid + '.zip'):
        os.remove(yml['output_dir'] + '/' + nid + '.zip')
else:
    objdir = str(Path(srcdir).parent.absolute()) + '/OCFL_objects/' + nid + '_' + uuid + '_' + Namespace
#need id, objdir, name, message, address

command = 'python3 ocfl-py/ocfl-object.py --create --srcdir ' + srcdir + ' --id ' + id + \
    ' --objdir ' + objdir + ' --name ' + name + ' --message ' + message + ' --address ' + address
os.system(command)
os.system('python3 ocfl-py/ocfl-validate.py --verbose ' + objdir + '>' + yml['output_dir'] + '/validation_results_node_' + nid)
if yml['download_ocfl']:
    shutil.make_archive(yml['output_dir'] + '/' + nid, 'zip', objdir)
    shutil.rmtree(yml['output_dir'] + '/' + id)

#clean up temporary directories
if os.path.isdir(yml['output_dir'] + '/validate_temps'):
    shutil.rmtree(yml['output_dir'] + '/validate_temps')