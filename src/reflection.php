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

function implementsInterface($cls, $iface) {
  return in_array($iface, class_implements($cls));
}

function getImplementations($interfaceName) {
  return array_filter(get_declared_classes(),
    function($className) use($interfaceName) {
      return implementsInterface($className, $interfaceName);
  });
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

function getClassesDefinedInFile($path) {
  $classes = array();
  $contents = file_get_contents($path);
  $tokens = token_get_all($contents);
  for ($i = 0; $i < count($tokens); ++$i) {
    if (is_array($tokens[$i]) && $tokens[$i][0] === T_CLASS) {
      $i += 2;
      $classes[] = $tokens[$i][1];
    }
  }
  return $classes;
}
