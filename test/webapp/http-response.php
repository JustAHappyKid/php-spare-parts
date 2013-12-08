<?php

require_once 'webapp/http-response.php';

use \SpareParts\Webapp\HttpResponse;

function testBasicHandlingOfHeaders() {
  $r = new HttpResponse;
  assertEqual(array(), $r->getValuesForHeader('X-Whatever'));
  $r->addHeader('X-Whatever', "hey");
  assertTrue(in_array('X-Whatever', $r->headersSet()));
  assertEqual(array("hey"), $r->getValuesForHeader('X-Whatever'));
  $r->addHeader('X-Whatever', "ho");
  assertTrue(in_array('X-Whatever', $r->headersSet()));
  assertEqual(array("hey", "ho"), $r->getValuesForHeader('X-Whatever'));
}
