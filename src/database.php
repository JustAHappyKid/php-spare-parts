<?php

/**
 * This database-access library is just a thin wrapper around PHP's standard PDO API,
 * simply intended to minimize boiler-plate and add a tidbit of type-safety where possible.
 */

namespace SpareParts\Database;

require_once dirname(__FILE__) . '/dates.php';

use \Exception, \PDO, \DateTime, \SpareParts\DateTime\DateSansTime;

class NoMatchingRecords extends Exception {}
class MultipleMatchingRecords extends Exception {}

function setConnectionParams($driver, $dbName, $username, $password, $host) {
  global $__SpareParts_Database_connectionParams;
  $__SpareParts_Database_connectionParams = array('driver' => $driver, 'dbName' => $dbName,
    'username' => $username, 'password' => $password, 'host' => $host);
}

function getConnection() {
  global $__SpareParts_Database_connectionParams, $__SpareParts_Database_connection;
  if (empty($__SpareParts_Database_connectionParams)) {
    throw new Exception("Connection parameters must be set (via 'setConnectionParams') " .
      "before connection to database can be made.");
  }
  if ($__SpareParts_Database_connection === null) {
    $ps = $__SpareParts_Database_connectionParams;
    $dsn = $ps['driver'] . ":dbname=" . $ps['dbName'] . ";host=" . $ps['host'];
    $__SpareParts_Database_connection = new PDO($dsn, $ps['username'], $ps['password'],
                                               array(PDO::ATTR_PERSISTENT => false));
    $__SpareParts_Database_connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }
  return $__SpareParts_Database_connection;
}

function closeConnection() {
  global $__SpareParts_Database_connection;
  $__SpareParts_Database_connection = null;
}

function transaction($action, $params = array()) {
  $conn = getConnection();
  if ($conn->inTransaction()) {
    return call_user_func_array($action, $params);
  } else {
    $conn->beginTransaction();
    $ret = call_user_func_array($action, $params);
    $conn->commit();
    return $ret;
  }
}

/*
function generateNextId($table) {
  $conn = getConnection();
  $stmt = $conn->prepare("SELECT NEXTVAL('" . $table . "_id_seq') AS nextval");
  $stmt->execute();
  $row = $stmt->fetch();
  return $row['nextval'];
}
*/

function query($query, Array $values = array()) {
  $conn = getConnection();
  $stmt = $conn->prepare($query);
  $stmt->execute(_sanitizeValues($values));
  return $stmt;
}

function queryAndFetchAll($query, Array $values = array()) {
  $stmt = query($query, $values);
  return $stmt->fetchAll();
}

function select($selectClause, $table, $whereClause = null, Array $params = array(),
                $orderBy = null, $limit = null) {
  $conn = getConnection();
  $stmt = $conn->prepare("SELECT " . $selectClause . " FROM " . $table .
                         ($whereClause ? (" WHERE " . $whereClause) : "") .
                         ($orderBy ? (" ORDER BY " . $orderBy) : "") .
                         ($limit ? (" LIMIT " . $limit) : ""));
  $stmt->execute(_sanitizeValues($params));
  return $stmt->fetchAll();
}

/**
 * The only difference between 'simpleSelect' and 'select' is that 'select' allows you to
 * specify the columns you're interested in; 'simpleSelect' simply does a "SELECT * FROM ...".
 */
function simpleSelect($table, $whereClause, Array $values, $orderBy = null, $limit = null) {
  return select('*', $table, $whereClause, $values, $orderBy, $limit);
}

function selectByID($table, $id) {
  $rows = simpleSelect($table, 'id = ?', array($id));
  if (count($rows) == 0) {
    throw new NoMatchingRecords("No entry in table '$table' found with ID '$id'");
  } else if (count($rows) > 1) {
    throw new MultipleMatchingRecords("Expected to find exactly 1 row in table '$table' " .
      "with ID '$id', but " . count($rows) . " rows were found");
  }
  return current($rows);
}

function selectExactlyOne($table, $whereClause, Array $values) {
  $rows = simpleSelect($table, $whereClause, $values);
  if (count($rows) == 0) {
    throw new NoMatchingRecords("No entry in table '$table' found matching given constraint");
  } else if (count($rows) > 1) {
    throw new MultipleMatchingRecords(
      "Expected to find exactly 1 row in table '$table' matching constraint, " .
      "but " . count($rows) . " rows were found");
  }
  return current($rows);
}

function selectAllRows($table, $orderBy = null) {
  return simpleSelect($table, 'TRUE', array(), $orderBy);
}

function countRows($table, $whereClause = null, Array $values = array()) {
  $rows = select('count(*) AS cnt', $table, $whereClause, $values);
  return intval($rows[0]['cnt']);
}

function insertOne($table, Array $values, $returnId = false) {
  $conn = getConnection();
  $stmt = $conn->prepare("INSERT INTO " . $table . " " . _fieldList($values) .
                         " VALUES " . _questionMarks($values));
  $stmt->execute(_sanitizeValues(array_values($values)));
  return $returnId ? $conn->lastInsertId($table . '_id_seq') : null;
}

function updateByID($table, $id, Array $valuesToUpdate) {
  $conn = getConnection();
  $assignments = array();
  foreach ($valuesToUpdate as $f => $_) {
    $assignments[] = "$f = ?";
  }
  $setClause = implode(', ', $assignments);
  $stmt = $conn->prepare("UPDATE " . $table . " SET " . $setClause . " WHERE id = ?");
  $allParams = array_merge(array_values($valuesToUpdate), array($id));
  $stmt->execute(_sanitizeValues($allParams));
}

/*
  XXX: The arguments to this method are too confusing... Wouldn't it be better to just
       require query() to be called with the full syntax of the UPDATE statement?
       Seems like that would provide a lot more clarity in client code...
function XXXupdate($table, Array $values, $matchOn) {
  $conn = getConnection();
  $assignments = array();
  foreach ($values as $f => $_) {
    $assignments[] = "$f = ?";
  }
  $setClause = implode(', ', $assignments);
  $constraints = array();
  foreach ($matchOn as $f => $_) {
    $constraints[] = "$f = ?";
  }
  $whereClause = implode(' AND ', $constraints);
  $stmt = $conn->prepare("UPDATE " . $table . " SET " . $setClause . " " .
                         "WHERE " . $whereClause);
  //$stmt = $conn->prepare("UPDATE " . $table . " SET " . _fieldList($values) . " = " .
  //                       _questionMarks($values) . " WHERE " . $whereClause);
  $stmt->execute(_sanitizeValues(array_merge(array_values($values), array_values($matchOn))));
}

function updateOrInsert($table, Array $values, Array $matchOn) {
  $constraints = array();
  foreach ($matchOn as $f => $_) {
    $constraints[] = "$f = ?";
  }
  $rows = simpleSelect($table, implode(' AND ', $constraints), array_values($matchOn));
  if (count($rows) == 0) {
    insert($table, array_merge($matchOn, $values));
  } else if (count($rows) == 1) {
    XXXupdate($table, $values, $matchOn);
  } else {
    throw new Exception("Multiple records found matching given constraints");
  }
}
*/

function delete($table, $whereClause, Array $values) {
  query('DELETE FROM ' . $table . ' WHERE ' . $whereClause, $values);
}

/**
 * The following are "local" methods, only intended to be used here in this file.
 */

function _fieldList(Array $values) {
  return "(" . implode(', ', array_keys($values)) . ")";
}

function _questionMarks(Array $values) {
  $qMarks = '?';
  for ($i = 0; $i < count($values) - 1; ++$i) { $qMarks .= ', ?'; }
  return "(" . $qMarks . ")";
}

function _sanitizeValues(Array $origValues) {
  $newValues = array();
  foreach ($origValues as $k => $v) {
    $sanitized = null;
    if (is_bool($v)) {
      # G-damn, PHP is a stupid-ass language...  Why on Earth would it not properly pass booleans
      # to Postgres?!  What is the bloody purpose in having a database abstraction layer?!
      $sanitized = ($v == true ? 't' : 'f');
    } else if ($v instanceof DateTime) {      /* XXX: DateTime needs namespace qualification ??? */
      $sanitized = $v->format('Y-m-d H:i:s');
    } else if ($v instanceof DateSansTime) {    /* XXX: DateSansTime needs namespace qualification */
      $sanitized = $v->asDatabaseString();
    } else {
      $sanitized = $v;
    }
    $newValues[$k] = $sanitized;
  }
  return $newValues;
}

/*
function getTableNames() {
  return array_map(function ($r) { return $r['tablename']; },
    queryAndFetchAll("select * from pg_tables where schemaname = 'public'"));
}
*/

$__SpareParts_Database_connectionParams = null;
$__SpareParts_Database_connection = null;
