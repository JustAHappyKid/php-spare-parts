<?php

namespace SpareParts\WebClient\HttpSimple;

require_once dirname(__FILE__) . '/http-client.php';
use \SpareParts\WebClient\HttpClient;

function get($url) {
  $c = new HttpClient;
  $r = $c->get($url);
  return $r->content;
}

/*
function get($url) {
  $ch = curl_init();
  $timeout = 30;
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
  $data = curl_exec($ch);
  echo "data: $data\n";
  curl_close($ch);
  return $data;
}
*/
