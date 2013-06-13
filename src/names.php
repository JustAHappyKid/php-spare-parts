<?php

namespace SpareParts\Names;

function underscoreToCamelCase($underscoreName) {
  return preg_replace('/_([a-z])/e', 'strtoupper(\'$1\')', $underscoreName);
}

function camelCaseToUnderscore($camelCaseName) {
  return preg_replace('/([A-Z])/e', '"_" . strtolower(\'$1\')', $camelCaseName);
}

function hyphenatedToCamelCase($hyphenatedName) {
  return preg_replace('/-([a-z])/e', 'strtoupper(\'$1\')', $hyphenatedName);
}

function camelCaseToHyphenated($camelCaseName) {
  return preg_replace('/([A-Z])/e', '"-" . strtolower(\'$1\')', $camelCaseName);
}
