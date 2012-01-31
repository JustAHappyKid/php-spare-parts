#! /usr/bin/env php
<?php

use \MyPHPLibs\Test;

function main($argc, $argv) {
  error_reporting(E_ALL);
  $baseDir = realpath(dirname(dirname(__FILE__)));
  set_include_path("$baseDir/src:" . get_include_path());
  require_once $baseDir . '/src/test/base-framework.php';
  $filesToIgnore = array('network-enabled/*', 'test.php');
  Test\testScriptMain("$baseDir/test", $filesToIgnore, $argc, $argv);
}

main($argc, $argv);
