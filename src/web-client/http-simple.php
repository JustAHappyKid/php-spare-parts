<?php

namespace SpareParts\WebClient\HttpSimple;

require_once dirname(__FILE__) . '/http-client.php';
use \SpareParts\WebClient\HttpClient;

function get($url) {
  $c = new HttpClient;
  $r = $c->get($url);
  return $r->content;
}
