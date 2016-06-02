<?php

// Configure the application here.

define('BOT_TOKEN', '1-2-3-4-token-here');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
define('RSS_URL', 'http://feeds.feedburner.com/tweakers/');
define('CHANNEL_NAME', '@TweakersChannel'); // The channel name. Must be in format @channelusername.
// Bot must be admin on channel.
define('JSON_FILE', './ids.json');
// End of configuration.

include('vendor/autoload.php');

$jsonFile = file_get_contents(JSON_FILE);
if ($jsonFile !== "") {
    $jsonDecoded = json_decode($jsonFile);
} else {
    $jsonDecoded = array("blanktest123");
}
function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  header("Content-Type: application/json");
  echo json_encode($parameters);
  return true;
}

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}

function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);

  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);

  return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

  return exec_curl_request($handle);
}

$rss = Feed::loadRss(RSS_URL);
//echo 'Title: ', $rss->title;
//echo 'Description: ', $rss->description;
//echo 'Link: ', $rss->link;


foreach ($rss->item as $item) {
    global $jsonDecoded;
    $minimumtimestamp = time() - CRON_SYNC_TIME;
    $timestamp = $item->timestamp[0];
    if (!in_array(intval($item->timestamp), $jsonDecoded)) {
        // Strip HTML.
        add_to_array(intval($item->timestamp));

        $descriptionStripped = preg_replace("/<[^>]*>/", '', $item->description);

        $messageText = "<b>" .  $item->title . "</b>
<i>Gepubliceerd om " . date("H:i:s", intval($timestamp[0])) . "</i>
" . $descriptionStripped . '
<a href="' . $item->link . '">Ga naar het volledige artikel</a>';

//       print $messageText;
      apiRequest("sendMessage", array('chat_id' => CHANNEL_NAME, 'text' => $messageText, 'parse_mode' => "html"));
    }
}

function add_to_array($text) {
    echo $text;
    global $jsonDecoded;
    if($jsonDecoded == NULL) {
        $jsonDecoded = array();
    }
//    print_r ($jsonDecoded);
    array_push($jsonDecoded, $text);
    print_r($jsonDecoded);
    echo($text[0]);
    file_put_contents(JSON_FILE, json_encode($jsonDecoded));
}
