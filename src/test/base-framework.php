<?php

namespace SpareParts\Test;

require_once dirname(dirname(__FILE__)) . '/string.php';  # withoutPrefix

use \ErrorHandlerInvokedException, \Exception;

# ---------------------------------------------------------------------------------------------
# - main method -------------------------------------------------------------------------------
# ---------------------------------------------------------------------------------------------

function C($relPathToTestDir, $filesToIgnore, $argc, $argv) {
  $dirContainingTests = realpath($relPathToTestDir);
  $baseLibDir = dirname(dirname(__FILE__));
  require_once $baseLibDir . '/fs.php';    # recursivelyGetFilesInDir, ...
  require_once $baseLibDir . '/types.php'; # getSubclasses
  $testFiles = null;
  if ($argc > 2) {
    quit("Please specify a test file to run or give no arguments to run all tests.");
  } else if ($argc == 2) {
    $pathToTest = realpath($argv[1]);
    if ($pathToTest == false) {
      quit("The specified test file or directory does not exist or is not accessible.");
    } else if (!isWithinOrIsDirectory($pathToTest, realpath($dirContainingTests))) {
      quit("The specified path is not within the test directory.");
    }
    if (is_dir($pathToTest)) {
      $testFiles = gatherTestFiles($dirContainingTests, $pathToTest, $filesToIgnore);
    } else {
      $testFiles = array($pathToTest);
    }
  } else {
    $testFiles = gatherTestFiles($dirContainingTests, $dirContainingTests, $filesToIgnore);
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

function gatherTestFiles($baseTestDir, $pathToTest, $filesToIgnore) {
  # Does file $f match one of the patterns in "files to ignore"?
  $fileShouldBeIgnored = function($f) use($baseTestDir, $pathToTest, $filesToIgnore) {
    $pathRelativeToBaseTestDir = withoutPrefix(pathJoin($pathToTest, $f), $baseTestDir . '/');
    foreach ($filesToIgnore as $ignorePattern) {
      if (fnmatch($ignorePattern, $pathRelativeToBaseTestDir)) return true;
    }
    return false;
  };
  $relativePaths = array_filter(recursivelyGetFilesInDir($pathToTest),
    function($f) use($fileShouldBeIgnored) { return !$fileShouldBeIgnored($f); });
  $testFiles = array_map(function($f) use($pathToTest) { return pathJoin($pathToTest, $f); },
    $relativePaths);
  return $testFiles;
}

function runTestFiles($baseTestDir, $testFiles) {
  requireTestFiles($testFiles);
  set_exception_handler('\SpareParts\Test\exceptionHandler');
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
    if (strstr(strtolower($f), 'test') && !beginsWith(strtolower($f), "spareparts\\test\\")) {
      $testFuncs[] = $f;
    }
  }
  $testClasses = array();
  $baseHarnessClass = 'SpareParts\Test\TestHarness';
  if (!class_exists($baseHarnessClass)) {
    throw new Exception("Something is wrong: class $baseHarnessClass does not exist!");
  }
  foreach (getSubclasses($baseHarnessClass) as $c) {
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
  $ro = new \ReflectionObject($t);
  $methods = $ro->getMethods(\ReflectionMethod::IS_PUBLIC);
  $methodsRun = 0;
  foreach ($methods as $m) {
    $methodName = $m->getName();
    if ($methodName != 'setUp' && $methodName != 'tearDown') {
      $t->setUp();
      $t->$methodName();
      $t->tearDown();
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

abstract class TestHarness {
  public function setUp() {
    // XXX: is this relevant here?
    //expectErrorLogMessages(0);
  }
  public function tearDown() { }
}

class TestFailure extends Exception {}
