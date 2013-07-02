<?php

function asString($var, $singleLine = true) {
  $exp = var_export($var, true);
  return $singleLine ? str_replace("\n", " ", $exp) : $exp;
}

function at($arr, $index, $default = null) {
  if ($arr === null) {
    return $default;
  } else if (!is_array($arr)) {
    throw new InvalidArgumentException("First parameter must be an array");
  } else {
    return in_array($index, array_keys($arr), $strict = true) ? $arr[$index] : $default;
  }
}

function attr($obj, $attr, $default = null) {
  return (is_object($obj) && isset($obj->$attr)) ? $obj->$attr : $default;
}

# Return true if the given parameter is of type 'integer' or is a string-representation of
# an integer.
function isInteger($v) {
  return is_int($v) || (is_string($v) && preg_match('/^[0-9]+$/', $v));
}

function readBoolFromStr($str) {
  $lower = strtolower($str);
  $falseValues = array('false', 'f', 'no', 'n');
  return in_array($lower, $falseValues) ? false : ((boolean) $lower);
}

/*
function getSubclasses($parentClassName) {
  $classes = array();
  foreach (get_declared_classes() as $className) {
    if (is_subclass_of($className, $parentClassName)) { $classes[] = $className; }
  }
  return $classes;
}

function isAbstractClass($className) {
  $class = new ReflectionClass($className);
  return $class->isAbstract();
}

function getNamesOfPublicMethods($object) {
  $names = array();
  $ro = new ReflectionObject($object);
  foreach ($ro->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
    $names []= $m->getName();
  }
  return $names;
}
*/
