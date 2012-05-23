#!/bin/bash
# License: IDGF

#where is this script located? This really can be anywhere
base_url="stuff.vincentcharles.com/dyndns.php"
pass="SOMETHING" # the password you created in the dyndns.php script
ip="" # update addr, or leave empty to have the server grab the clients address

# comma separated list of whatever host and/or hosts you want to update record for
host="test0.me.com"
hosts="test1.me.com,test2.me.com,test3.me.com"

wget "${base_url}?host=${host}&passwd=${pass}&ip=${ip}"
