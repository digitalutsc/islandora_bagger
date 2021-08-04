import os
import sys
import yaml
import re
import json
import zipfile
import shutil
# os.s
#parse configuration file
with open(sys.argv[3]) as f:
    yml = yaml.safe_load(f)

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
        x = open('final_temp', 'w')
        x.write(srcdir + '\n')
        x.close()
    else:
        # srcdir = sys.argv[2]
        output = os.listdir(yml['output_dir'])
        i = 0
        while not output[i].startswith(sys.argv[1]):
            i += 1
        srcdir = yml['output_dir'] + "/" + output[i]

dirs = os.listdir(srcdir + "/data")
for file in dirs:
    if re.search('node_', file):
        final = file
        break
json_file = open(srcdir + "/data/" + final)
contents = json_file.read()
json_file.close()
json = json.loads(contents)
name = '"' + yml['bag-info']['Contact-Name'] + '"'
message = '"' + yml['bag-info']['Message'] + '"'
address = yml['bag-info']['Contact-Email']
Namespace = yml['bag-info']['Namespace']
nid = sys.argv[1]
uuid = json['uuid'][0]['value']

#need id, objdir, name, message, address
id = nid + ':' + uuid + ':' + Namespace
objdir = nid + '_' + uuid + '_' + Namespace

os.system('python3 ocfl-py/ocfl-object.py --create --srcdir ' + srcdir + ' --id ' + id +
              ' --objdir ' + objdir + ' --name ' + name + ' --message ' + message + ' --address ' + address)
os.system('python3 ocfl-py/ocfl-validate.py --verbose ' + objdir)
#clean up temporary directories
if os.path.isdir(yml['output_dir'] + '/validate_temps'):
    shutil.rmtree(yml['output_dir'] + '/validate_temps')