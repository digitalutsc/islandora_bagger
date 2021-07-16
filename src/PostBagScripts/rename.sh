res=`ls "${2}/data/" | grep -w json`
IFS=' ' read -ra result <<< "${res[0]}"
node_json="${2}/data/${result[0]}"
uuid=`cat $node_json | jq '.uuid' | jq .[] | jq -r '.value'`
#if [ -d ${1}/${2} ]; then
 #   rm -r "${1}/${2}"
#fi

if [ -d "${2}_${uuid}" ]; then
    exit 0
fi
var=`cat ${2}/bag-info.txt`
#IFS='namespace=' read -ra arr <<< "$var"
IFS=$'\n'; arr=($var); unset IFS;
IFS=' ' read -ra new_arr <<< "${arr[2]}"

mv "${2}" "${2}_${uuid}_${new_arr[1]}"
