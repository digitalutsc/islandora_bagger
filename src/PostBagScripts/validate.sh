#!/bin/bash
res=`ls "${2}/data/" | grep -w json`
IFS=' ' read -ra result <<< "${res[0]}"
node_json="${2}/data/${result[0]}"
uuid=`cat $node_json | jq '.uuid' | jq .[] | jq -r '.value'`
var=`cat ${2}/bag-info.txt`
IFS=$'\n'; arr=($var); unset IFS;
IFS=' ' read -ra new_arr <<< "${arr[0]}"
#name=${new_arr[1]}
name=""
for (( i = 1 ; i < ${#new_arr[@]} ; i++ )); do
    name="${name} ${new_arr[$i]}"
done
name=`echo "${name}" | sed -e 's/^[ \t]*//'`

IFS=' ' read -ra new_arr <<< "${arr[1]}"
address="mailto:"${new_arr[1]}

IFS=' ' read -ra new_arr <<< "${arr[2]}"
namespace=${new_arr[1]}

IFS=' ' read -ra new_arr <<< "${arr[3]}"

message=""
#collect message
for (( i = 1 ; i < ${#new_arr[@]} ; i++ )); do
    message="${message} ${new_arr[$i]}"
done
#remove trailing and leading whitespaces from message
message=`echo "${message}" | sed -e 's/^[ \t]*//'`

#create the OCFL object using python script
python3 "ocfl-py/ocfl-object.py" --create --srcdir $2 --id "${1}:${uuid}:${namespace}" --objdir "${1}_${uuid}_${namespace}" --name "${name}" --message "${message}" --address "$address"

#validate the object we just created
python3 "ocfl-py/ocfl-validate.py" --verbose "${1}_${uuid}_${namespace}"