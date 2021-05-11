#!/usr/bin/jq -rf
.policies[].tagLists[].tagListData | select(.name=="Project").tags[] | select(.enabled==true).name | gsub("\\\\";"")
