<?php

function underscoreToCamelCaseName($underscoreName) {
  return preg_replace('/_([a-z])/e', 'strtoupper(\'$1\')', $underscoreName);
}

function camelCaseToUnderscoreName($camelCaseName) {
  return preg_replace('/([A-Z])/e', '"_" . strtolower(\'$1\')', $camelCaseName);
}

function hyphenatedToCamelCaseName($hyphenatedName) {
  return preg_replace('/-([a-z])/e', 'strtoupper(\'$1\')', $hyphenatedName);
}

function camelCaseToHyphenatedName($camelCaseName) {
  return preg_replace('/([A-Z])/e', '"-" . strtolower(\'$1\')', $camelCaseName);
}
