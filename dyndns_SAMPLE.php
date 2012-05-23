<?php

//README
// The original code was available through the dreamhost web forum.
// http://smoser.brickies.net/git/?p=dreamhost-tools.git

// This script has been modified to allow multiple hosts in the request url, which can save about 1/3 the api calls (allowing more calls/day meaning decreased downtime when your local ip changes.

// Put this file on the server, chmod to 600 or something
// edit the next few values.

// Call via a cron job or something on your server, via a url similar to:
//   http://YOUR_DOMAIN_THIS_FILE_LIVES.com/dyndns.php?host=test0.com&hosts=test.com,test2.com,test3.com&passwd=SOME_PASSWORD
// use host and/or hosts. hosts is comma separated, the password is on set in this file.
// TODO: A better way to pass a password: basic auth via httaccess file?
//
// Licence: IDGF

////////////////////////
// set DH_API_KEY to your key
// (from https://panel.dreamhost.com/?tree=home.api)
// probably should limit this key to dns modifications only
$DH_API_KEY="WHATEVER_DREAMHOST_GENERATED";

// set VALID_HOSTS to contain an array of fields that can be modified
// only entries in this list will be updated
$VALID_HOSTS=array("site1.me.com", "site2.me.com", "site3.me.com");

// set a password, make your url request include 'passwd' key
// set to "" if you don't want a password
$PASSWD="SOME_LOW_SECURITY_PASSWORD";

/////////No need to modify unless you feel like it ///////////////
$APP_NAME="my-dhdyndns";//Todo: is this really necessary?
$DH_API_BASE="https://api.dreamhost.com/";

//$DEBUG=true; // used when running locally, increases output and doesn't make the calls
$errors=array();
$successes=array();
$hosts=array();
$api_calls=0;

function dh_request($cmd,$aargs=false) {
  global $DH_API_BASE, $DH_API_KEY, $APP_NAME, $DEBUG, $api_calls;
  $base=$API_BASE;
  $id=uniqid($APP_NAME);

  $args="?key=$DH_API_KEY&cmd=$cmd&format=json";
  $args.="&unique_id=" . uniqid($APP_NAME);
  if(is_array($aargs)) {
    foreach($aargs as $key => $val) {
      $args.="&" . urlencode($key) . "=" . urlencode($val);
    }
  }

  $url=$DH_API_BASE . $args;
  $curl_handle=curl_init();
  curl_setopt($curl_handle,CURLOPT_URL,$DH_API_BASE . $args);
  curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,5);
  curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
  if(!$DEBUG) {
    $buffer = curl_exec($curl_handle);
  } else {
    print_r('debug curl call: ' . $curl_handle);
  }
  curl_close($curl_handle);
  $api_calls++;

  if (empty($buffer)) {
      return(false);
  } else {
      return(json_decode($buffer,true));
  }
}

//I was just lazy and didn't want to type out print_r
function vp($str) {
  print_r($str);
}

//debug print
function vpd($str) {
  global $DEBUG;
  if($DEBUG) {
    print_r($str);
  }
}

//main change, since this now allows multiple updates, no need to exit on a failure.
function fail($str) {
  global $errors;
  array_push($errors, "error: " . $str);
}
function success($str) {
  global $successes;
  array_push($successes, $str);
}
function bad_input($str) {
  if(empty($str)) {
    $str = "invalid input\n";
  }
  printf("error: %s",$str);
  exit(true);
}

$passwd=$_REQUEST["passwd"];
$host=$_REQUEST["host"];
$hosts_list = $_REQUEST["hosts"];

$addr=$_REQUEST["ip"];
$comment=$_REQUEST["comment"];

//die if the password was bad
if($PASSWD != $passwd) { bad_input(); }

//treat hosts and host the same...concat both to hosts and remove duplicates. A little excessive to keep both host and hosts params
if($hosts_list) {
  $hosts = str_getcsv($hosts_list);
} else {
  $hosts = array();
}
if($host) {
  array_push($hosts, $host);
}
$hosts = array_unique($hosts);
foreach($hosts as $key => $host) {
  if(!in_array($host,$VALID_HOSTS)) {
    //remove from list and print error
    fail("host not valid: " . $host . "\n");
    unset($hosts[$key]);
  }
}

vpd($errors);
vpd($hosts);

if(!$DH_API_KEY) {
  $DH_API_KEY=$_REQUEST["key"];
  if(!$DH_API_KEY) { bad_input(); }
}

if(!$addr) { $addr=$_SERVER["REMOTE_ADDR"]; }

$ret=dh_request("dns-list_records");
if($ret["result"] != "success") {
  fail("failed list records\n");
  return(1);
}

function doit($host, $ret) {
  global $addr, $errors, $successes, $DEBUG;
  $found=false;
  foreach($ret["data"] as $key => $row) {
    if($row["record"] == $host) {
      if($row["editable"] == 0) {
        fail("error: $host not editable");
      }
      if($row["type"] != "A") {//this script is all about A records
        fail("error: $host not a A record");
      }
      $found=$row;
    }
  }

  if($found) {
    if($addr==$found["value"]) {
      printf("record correct: %s => %s\n", $found["record"], $addr);
      return(0);
    }
    $ret=dh_request("dns-remove_record",
                  array("record" => $found["record"],
                        "type" => $found["type"],
                        "value" => $found["value"]));
    if($ret["result"] != "success") {
      fail("failed to remove record" . $found["value"] . "\n");
      return(1);
    }
    $record=$found["record"];
    $type=$found["type"];
    printf("deleted %s. had value %s\n", $record, $found["value"]);
  } else {
    $record=$host;
    $type='A';
  }

  $ret=dh_request("dns-add_record",
                  array("record" => $record,
                        "type" => $type,
                        "value" => $addr,
                        "comment" => $comment));

  if($ret["result"] != "success") {
    fail(sprintf("%s\n\t%s\n",
                 "failed to add $record of type $type to $addr", $ret["data"]));
    return(1);
  }
  success("success: set " . $record . " to " . $addr . "\n");
}

//update a host and/or hosts list
if ($hosts) {
  foreach($hosts as $key => $host) {
    doit($host, $ret);
  }
}

print_r($successes);
print_r($errors);
print_r("API Calls: " . $api_calls);
?>
