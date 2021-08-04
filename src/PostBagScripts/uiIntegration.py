import os
import shutil
import yaml
from shutil import copyfile
import sys
from distutils.dir_util import copy_tree
with open(sys.argv[3]) as f:
    yml = yaml.safe_load(f)
src = yml['output_dir']
dst = yml['drupal_public_dir']
copy_tree(src, dst)