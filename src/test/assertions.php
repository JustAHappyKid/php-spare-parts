<?php

use \SpareParts\Test\TestFailure;

function fail($msg) {
  throw new TestFailure($msg);
}

function assertTrue($value, $msg = null) {
  if ($value !== true) {
    throw new TestFailure($msg === null ? "Expected value to be true" : $msg);
  }
}

function assertFalse($value, $msg = null) {
  if ($value !== false) {
    throw new TestFailure($msg === null ? "Expected value to be false" : $msg);
  }
}

function assertEqual($expected, $actual, $msg = null) {
  if ($expected !== $actual) {
    throw new TestFailure($msg === null ?
      ("Expected to get " . asString($expected) . " but got " . asString($actual)) : $msg);
  }
}

function assertNotEqual($value1, $value2, $msg = null) {
  if ($value1 == $value2) {
    throw new TestFailure($msg === null ?
      ("Expected values to be unequal, but both were " . asString($value1)) : $msg);
  }
}

function assertNull($value) {
  if ($value !== null) {
    throw new TestFailure("Expected value to be null");
  }
}

function assertNotNull($value) {
  if ($value == null) {
    throw new TestFailure("Expected value to not be equivalent to null");
  }
}

function assertEmpty($value) {
  if (!empty($value)) {
    throw new TestFailure("Expected value to be empty, but got " . asString($value));
  }
}

function assertInArray($value, $arr) {
  if (!in_array($value, $arr)) {
    throw new TestFailure("Expected to find value " . asString($value) . " in array " .
                          asString($arr));
  }
}

function assertThrows($exception, Closure $code) {
  $raised = null;
  try {
    $code();
    $raised = false;
  } catch (Exception $e) {
    $raised = true;
    assertEqual($exception, get_class($e),
      "Expected to get exception of type $exception; got exception " . get_class($e));
  }
  if (!$raised) fail("No exception was raised, but expected $exception");
}
