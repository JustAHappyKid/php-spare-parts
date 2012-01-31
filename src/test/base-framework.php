<?php

namespace MyPHPLibs\Test;

use \ErrorHandlerInvokedException, \Exception;

# ---------------------------------------------------------------------------------------------
# - main method -------------------------------------------------------------------------------
# ---------------------------------------------------------------------------------------------

function testScriptMain($relPathToTestDir, $filesToIgnore, $argc, $argv) {
  $dirContainingTests = realpath($relPathToTestDir);
  $baseLibDir = dirname(dirname(__FILE__));
  require_once $baseLibDir . '/fs.php';    # recursivelyGetFilesInDir, ...
  require_once $baseLibDir . '/types.php'; # getSubclasses
  $testFiles = null;
  if ($argc > 2) {
    quit("Please specify a test file to run or give no arguments to run all tests.");
  } else if ($argc == 2) {
    $path = realpath($argv[1]);
    if ($path == false) {
      quit("The specified test file or directory does not exist or is not accessible.");
    } else if (!isWithinOrIsDirectory($path, realpath($dirContainingTests))) {
      quit("The specified path is not within the test directory.");
    }
    if (is_dir($path)) {
      $testFiles = array();
      foreach (recursivelyGetFilesInDir($path) as $f) { $testFiles []= pathJoin($path, $f); }
    } else {
      $testFiles = array($path);
    }
  } else {
    $testFiles = array();
    foreach (recursivelyGetFilesInDir($dirContainingTests) as $f) {
      $skip = false;
      foreach ($filesToIgnore as $ignorePattern) {
        if (fnmatch($ignorePattern, $f)) $skip = true;
      }
      if (!$skip) $testFiles []= pathJoin($dirContainingTests, $f);
    }
  }
  runTestFiles($dirContainingTests, $testFiles);
}

# ---------------------------------------------------------------------------------------------
# - helper methods ----------------------------------------------------------------------------
# ---------------------------------------------------------------------------------------------

function quit($msg) {
  echo "$msg\n";
  exit(-1);
}

function runTestFiles($baseTestDir, $testFiles) {
  requireTestFiles($testFiles);
  set_exception_handler('\MyPHPLibs\Test\exceptionHandler');
  $tests = runDefinedTests();
  echo "Ran " . $tests['functions'] . " test functions and " .
    $tests['methods'] . " test methods in " . $tests['classes'] . " classes.\n";
}

function requireTestFiles($files) {
  foreach ($files as $f) {
    if (strtolower(substr($f, -4)) != '.php') {
      echo "WARNING: Found non-PHP file, $f.\n";
    } else {
      require_once $f;
    }
  }
}

function runDefinedTests() {
  require_once dirname(dirname(__FILE__)) . '/string.php'; # beginsWith
  require_once dirname(__FILE__) . '/assertions.php';
  $allFuncs = get_defined_functions();
  $userDefined = $allFuncs['user'];
  $testFuncs = array();
  foreach ($userDefined as $f) {
    if (strstr(strtolower($f), 'test') && !beginsWith(strtolower($f), "myphplibs\\test\\")) {
      $testFuncs[] = $f;
    }
  }
  $testClasses = array();
  foreach (getSubclasses('TestHarness') as $c) {
    if (!isAbstractClass($c)) $testClasses []= $c;
  }
  if (count($testFuncs) == 0 && count($testClasses) == 0) {
    throw new Exception("No test functions or test classes found");
  }
  foreach ($testFuncs as $f) {
    call_user_func($f);
  }
  $numMethodsRun = 0;
  foreach ($testClasses as $c) {
    $numMethodsRun += runTestMethods($c);
  }
  return array('functions' => count($testFuncs), 'classes' => count($testClasses),
               'methods' => $numMethodsRun);
}

function runTestMethods($className) {
  $t = new $className;
  $ro = new ReflectionObject($t);
  $methods = $ro->getMethods(ReflectionMethod::IS_PUBLIC);
  $methodsRun = 0;
  foreach ($methods as $m) {
    $methodName = $m->getName();
    if ($methodName != 'setUp') {
      $t->setUp();
      $t->$methodName();
      $methodsRun += 1;
    }
  }
  return $methodsRun;
}

function exceptionHandler($exception) {
  try {
    $trace = ($exception instanceof ErrorHandlerInvokedException) ?
      $exception->getAdjustedTraceAsString() : $exception->getTraceAsString();
    echo(
      "An exception of type " . get_class($exception) . " went uncaught...\n" .
      "  Message: " . $exception->getMessage() . "\n" .
      "  File: " . $exception->getFile() . "\n" .
      "  Line: " . $exception->getLine() . "\n" .
      "  Stack trace:\n" . $trace . "\n\n");
  }
  catch (Exception $e) {
    exit("UH-OH!  An exception was raised from within the exception " .
      "handler!  The exception's message follows:\n" . $e->getMessage());
  }
}

class TestHarness {
  public function setUp() {
    // XXX: is this relevant here?
    //expectErrorLogMessages(0);
  }
}

class TestFailure extends Exception {}
