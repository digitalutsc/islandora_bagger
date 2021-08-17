import sys
import csv
import os
with open(sys.argv[1], newline='') as csvfile:
    spamreader = csv.reader(csvfile, delimiter=' ', quotechar='|')
    for row in spamreader:
        if row[0].isnumeric():
            os.system('./bin/console app:islandora_bagger:create_bag --settings=config.yml --node=' + row[0])
