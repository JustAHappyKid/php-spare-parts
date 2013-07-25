<?php

namespace SpareParts\Database\Paranoid;

require_once dirname(dirname(__FILE__)) . '/database.php';
use \SpareParts\Database as DB, \Exception;

# TODO: Extend this -- allow custom "policies", etc.

function insertOne($table, Array $values, $returnId = false, $policy = null) {
  securityScan($values, $policy);
  return DB\insertOne($table, $values, $returnId);
}

function updateByID($table, $id, Array $valuesToUpdate) {
  securityScan($valuesToUpdate);
  return DB\updateByID($table, $id, $valuesToUpdate);
}

function securityScan(Array $values, $policy = null) {
  foreach ($values as $column => $value) {
    if (!($value instanceof \DateTime)) {
      if (contains($value,  '<') || contains($value,  '>') ||
          contains($column, '<') || contains($column, '>')) {
        throw new Exception('No angle brackets allowed!');
      }
    }
  }
}
