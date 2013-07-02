<?php

namespace SpareParts\Reflection;

use \ReflectionFunction, \ReflectionClass, \ReflectionObject, \ReflectionMethod;

function numberOfParameters($func) {
  $rf = new ReflectionFunction($func);
  return $rf->getNumberOfParameters();
}

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
