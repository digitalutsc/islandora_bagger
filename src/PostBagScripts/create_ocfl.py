#!/usr/bin/env python
# coding: utf-8

import pandas as pd
import numpy as np
import os
import zipfile
import subprocess
import yaml
import sys
import logging
import shutil


# Setup logger for deubugging
logging.basicConfig(filename='ocfl_script.log', level=logging.DEBUG)

# dataframe column value lookup
def lookup_fn(df, key_col, key_val, val_col, idx=0):
    try:
        result_df = df.loc[df[key_col] == key_val].iloc[0]
        value = result_df[val_col]
        return value
    except IndexError:
        return 0

# Funciton to create/update/validate ocfl object given a source dir
def create_update_ocfl_object(ark_id, source_dir, ocfl_object_dir):
    create_update_success = "Invalid"
    try:
        if os.path.isdir(ocfl_object_dir):
            output = subprocess.check_output(["ocfl-object.py", "--update", "--id", ark_id, "--src", source_dir, "--objdir", ocfl_object_dir, "-v"], stderr=subprocess.STDOUT)    
        else:
            output = subprocess.check_output(["ocfl-object.py", "--create", "--id", ark_id, "--src", source_dir, "--objdir", ocfl_object_dir], stderr=subprocess.STDOUT)
        output = subprocess.check_output(["ocfl-validate.py", ocfl_object_dir], stderr=subprocess.STDOUT)
        print(output)
        create_update_success = "Valid"
    except:
        logging.debug("Error in creating/updating/validationg ocfl object")
    return create_update_success

def zipdir(path, zipfile_name):
    # ziph is zipfile handle
    for root, dirs, files in os.walk(path):
        for file in files:
            zipfile_name.write(os.path.join(root, file), 
                       os.path.relpath(os.path.join(root, file), 
                                       os.path.join(path, '..')))

node_id = sys.argv[1]
config_file_path = sys.argv[3]
bags_report_file_path = "bags_report.csv"

# Used for testing
#node_id = 9
#config_file_path = "/home/nat/Desktop/bagit_scripts/config.yml"
#bags_report_file_path = "bags_report.csv"


# Get dir and path info via the bagger config file
with open(config_file_path) as f:
    yml = yaml.safe_load(f)
    
output_dir = yml["output_dir"]
source_dir = output_dir + "/" + str(node_id)
path_to_zip = source_dir + ".zip"


# Unzip the bag zip to point to ocfl script
if os.path.exists(path_to_zip):
    with zipfile.ZipFile(path_to_zip, 'r') as zip_ref:
        zip_ref.extractall(output_dir)
else:
    print("Items bag zip does not exist.")

try:
    # Get the ARK 
    df = pd.read_csv(bags_report_file_path)
    df["node_id"] = df["node_id"].astype(str)

    ark_id = lookup_fn(df, "node_id", str(node_id), "ark_id", idx=0)
    ocfl_object_dir = output_dir +  "/" + str(ark_id)

    if os.path.isfile(ocfl_object_dir + ".zip"):
        with zipfile.ZipFile(ocfl_object_dir + ".zip", 'r') as zip_ref:
            zip_ref.extractall(output_dir)

    # Create/update and validate ocfl object
    ocfl_object_status = create_update_ocfl_object(ark_id, source_dir, ocfl_object_dir)
    
    with zipfile.ZipFile(ocfl_object_dir + ".zip", 'w', zipfile.ZIP_DEFLATED) as zip_file:
        zipdir(ocfl_object_dir, zip_file)

    df.loc[df['node_id'] == node_id, 'bag_status'] = ocfl_object_status
    df.to_csv(bags_report_file_path, index = False)
    logging.info("Created and validated ocfl object" + ocfl_object_dir)
except Exception as ex_msg:
    logging.debug("Unable to create ocfl object" + str(ex_msg))


# Cleanup source dirs
shutil.rmtree(source_dir)
shutil.rmtree(ocfl_object_dir)
os.remove(path_to_zip)
