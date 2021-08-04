import json
import sys
import os
import zipfile
import re
import yaml
import shutil


path = sys.argv[2]
with open(sys.argv[3]) as f:
    yml = yaml.safe_load(f)
namespace = yml['bag-info']['Namespace']
if yml['serialize'] == 'zip':
    parent_dir = yml['output_dir']
    directory = "rename_temps"
    path = os.path.join(parent_dir, directory)
    os.mkdir(path)
    with zipfile.ZipFile(sys.argv[2], 'r') as zip_ref:
        zip_ref.extractall(path)
    dirs = os.listdir(path + "/" + sys.argv[1] + "/data")
    path = yml['output_dir'] + '/rename_temps/' + sys.argv[1]
else:
    dirs = os.listdir(path + "/data")

for file in dirs:
    if re.search('node_', file):
        final = file
        break
json_file = open(path + "/data/" + final)
contents = json_file.read()
json_file.close()
json = json.loads(contents)
uuid = json['uuid'][0]['value']
parent = os.path.abspath(os.path.join(sys.argv[2], os.pardir))
new_name = sys.argv[1] + "_" + uuid + "_" + namespace
# check if this new directory already exists
if os.path.isdir(parent + "/" + new_name):
    shutil.rmtree(parent + "/" + new_name)
if not isinstance(yml['serialize'], bool):
    new_name = new_name + "." + str(yml['serialize'])
os.rename(sys.argv[2], parent + "/" + new_name)
if os.path.isdir(yml['output_dir'] + '/rename_temps'):
    shutil.rmtree(yml['output_dir'] + '/rename_temps')
