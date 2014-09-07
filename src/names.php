<?php

namespace SpareParts\Names;

function underscoreToCamelCase($underscoreName) {
  return preg_replace_callback('/_([a-z])/', function($ms) { return strtoupper($ms[1]); },
                               $underscoreName);
}

function camelCaseToUnderscore($camelCaseName) {
  return preg_replace_callback('/([A-Z])/', function($ms) { return "_" . strtolower($ms[1]); },
                               $camelCaseName);
}

function hyphenatedToCamelCase($hyphenatedName) {
  return preg_replace_callback('/-([a-z])/', function($ms) { return strtoupper($ms[1]); },
                               $hyphenatedName);
}

function camelCaseToHyphenated($camelCaseName) {
  return preg_replace_callback('/([A-Z])/', function($ms) { return "-" . strtolower($ms[1]); },
                               $camelCaseName);
}
