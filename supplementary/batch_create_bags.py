#!/usr/bin/env python
# coding: utf-8

import pandas as pd
import numpy as np
import sys
import os

csv_file_path = sys.argv[1]

df = pd.read_csv(csv_file_path)
df.head(2)

for index, row in df.iterrows():
    node_id = str(row["node_id"])
    cmd = './bin/console app:islandora_bagger:create_bag --settings=config.yml --node=' + node_id
    os.system(cmd)

