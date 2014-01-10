<?php

namespace SpareParts\Test;

require_once dirname(dirname(__FILE__)) . '/array.php';       # commonPrefix
require_once dirname(dirname(__FILE__)) . '/file-path.php';   # normalize
require_once dirname(dirname(__FILE__)) . '/reflection.php';  # getSubclasses
require_once dirname(dirname(__FILE__)) . '/string.php';      # withoutPrefix, beginsWith
require_once dirname(dirname(__FILE__)) . '/system/command-line-args.php';

use \Exception, \SpareParts\System\CommandLineArgs, \SpareParts\Reflection,
  \SpareParts\FilePath as Path, \SpareParts\ArrayLib as A;

# ---------------------------------------------------------------------------------------------
# - main method / argument handling -----------------------------------------------------------
# ---------------------------------------------------------------------------------------------

function testScriptMain($relPathToTestDir, $filesToIgnore, $argc, Array $argv) {
  $conf = new Config;
  $conf->baseTestDir = Path\normalize($relPathToTestDir);
  $conf->filesToIgnore = $filesToIgnore;
  $baseLibDir = dirname(dirname(__FILE__));
  require_once $baseLibDir . '/fs.php';    # recursivelyGetFilesInDir, ...
  require_once $baseLibDir . '/types.php'; # getSubclasses
  handleCommand(CommandLineArgs\separateArgsAndSwitches($argv), $conf);
}

function handleCommand(CommandLineArgs\ArgsAndSwitches $a, Config $conf) {
  $argc = count($a->baseArguments);
  $argv = $a->baseArguments;
  if ($a->hasSwitch("--verbose")) $conf->verbose = true;
  $testFiles = null;
  if ($argc > 2) {
    quit("Please specify a test file to run or provide no arguments to run all tests.");
  } else if ($argc == 2) {
    $pathToTest = beginsWith($argv[1], '/') ? $argv[1] : (getcwd() . '/' . $argv[1]);
    if ($pathToTest == false) {
      quit("The specified test file or directory does not exist or is not accessible.");
    } else if (!isWithinOrIsDirectory($pathToTest, $conf->baseTestDir)) {
      quit("The specified path is not within the test directory.");
    }
    if (is_dir($pathToTest)) {
      $testFiles = gatherTestFiles($conf->baseTestDir, $pathToTest, $conf->filesToIgnore);
    } else {
      $testFiles = array($pathToTest);
    }
  } else {
    $testFiles = gatherTestFiles($conf->baseTestDir, $conf->baseTestDir, $conf->filesToIgnore);
  }
  runTestFiles($conf, $testFiles);
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
    $pathRelativeToBaseTestDir = withoutPrefix(Path\join($pathToTest, $f), $baseTestDir . '/');
    foreach ($filesToIgnore as $ignorePattern) {
      if (fnmatch($ignorePattern, $pathRelativeToBaseTestDir)) return true;
    }
    return false;
  };
  $relativePaths = array_filter(recursivelyGetFilesInDir($pathToTest),
    function($f) use($fileShouldBeIgnored) { return !$fileShouldBeIgnored($f); });
  $testFiles = array_map(function($f) use($pathToTest) { return Path\join($pathToTest, $f); },
    $relativePaths);
  return $testFiles;
}

function runTestFiles(Config $conf, Array $testFiles) {
  requireTestFiles($testFiles);
  try {
    $tests = runDefinedTests($conf);
    echo "Ran " . $tests['functions'] . " test functions and " .
      $tests['methods'] . " test methods in " . $tests['classes'] . " classes.\n";
  } catch (Exception $e) {
    describeException($e);
  }
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

function runDefinedTests(Config $conf) {
  require_once dirname(__FILE__) . '/assertions.php';
  $allFuncs = get_defined_functions();
  $userDefined = $allFuncs['user'];
  $testFuncs = array();
  $ignorePrefix = strtolower(__NAMESPACE__ . '\\');
  foreach ($userDefined as $f) {
    if (strstr(strtolower($f), 'test') && !beginsWith(strtolower($f), $ignorePrefix)) {
      $testFuncs[] = $f;
    }
  }
  $testClasses = array();
  $baseHarnessClass = __NAMESPACE__ . '\\TestHarness';
  if (!class_exists($baseHarnessClass)) {
    throw new Exception("Something is wrong: class $baseHarnessClass does not exist!");
  }
  foreach (Reflection\getSubclasses($baseHarnessClass) as $c) {
    if (!Reflection\isAbstractClass($c)) $testClasses []= $c;
  }
  if (count($testFuncs) == 0 && count($testClasses) == 0) {
    throw new Exception("No test functions or test classes found");
  }
  foreach ($testFuncs as $f) {
    if ($conf->verbose) echo "Running test-function $f...\n";
    call_user_func($f);
  }
  $numMethodsRun = 0;
  foreach ($testClasses as $c) {
    if ($conf->verbose) echo "Running test-class $c...\n";
    $numMethodsRun += runTestMethods(new $c, $conf);
  }
  return array('functions' => count($testFuncs), 'classes' => count($testClasses),
               'methods' => $numMethodsRun);
}

function runTestMethods(TestHarness $testObject, Config $conf) {
  $ro = new \ReflectionObject($testObject);
  $methods = $ro->getMethods(\ReflectionMethod::IS_PUBLIC);
  $methodsRun = 0;
  foreach ($methods as $m) {
    $methodName = $m->getName();
    if ($methodName != 'setUp' && $methodName != 'tearDown') {
      if ($conf->verbose) echo "  Running test-method $methodName...\n";
      $testObject->setUp();
      $testObject->$methodName();
      $testObject->tearDown();
      $methodsRun += 1;
    }
  }
  return $methodsRun;
}

function describeException(Exception $exception) {
  $entries = $exception->getTrace();
  if (empty($entries[0]['file'])) unset($entries[0]);
  $commonPathPrefix = implode('/',
    A\commonPrefix(
      array_map(function($t) { return explode('/', $t['file']); },
                array_filter($entries, function($t) { return !empty($t['file']); })))) . '/';
  foreach ($entries as $i => $t) {
    if (empty($entries[$i]['file'])) $entries[$i]['file'] = '?';
    if (empty($entries[$i]['line'])) $entries[$i]['line'] = '?';
    $entries[$i]['relative-path'] = withoutPrefix($entries[$i]['file'], $commonPathPrefix);
    $entries[$i]['full-function-id'] =
      (isset($t['class']) ? ($t['class'] . $t['type']) : '') . $t['function'];
  }
  $maxLenOf = function($f) use($entries) {
    return max(array_map(function($t) use($f) { return strlen(strval($t[$f])); }, $entries));
  };
  $maxPathLength = $maxLenOf('relative-path');
  $maxLineNumLength = $maxLenOf('line');
  $maxFuncLength = $maxLenOf('full-function-id');
  $trace = implode("\n",
    array_map(
      function($t) use($commonPathPrefix, $maxPathLength, $maxLineNumLength, $maxFuncLength) {
        $paddedPath = str_pad($t['relative-path'], $maxPathLength, ' ', STR_PAD_RIGHT);
        $paddedFunc = str_pad($t['full-function-id'], $maxFuncLength, ' ', STR_PAD_RIGHT);
        $paddedLine = str_pad($t['line'], $maxLineNumLength);
        return "| $paddedFunc : $paddedPath : $paddedLine |"; }, $entries));
  echo("\n" .
    "An exception of type " . get_class($exception) . " went uncaught...\n" .
    "  Message: " . $exception->getMessage() . "\n" .
    "  File: " . $exception->getFile() . "\n" .
    "  Line: " . $exception->getLine() . "\n\n" .
    "A full stack trace follows:\n\n" . $trace . "\n\n");
}

abstract class TestHarness {
  public function setUp() {
    // XXX: is this relevant here?
    //expectErrorLogMessages(0);
  }
  public function tearDown() { }
}

class TestFailure extends Exception {}

class Config {
  public $baseTestDir, $filesToIgnore = array(), $verbose = false;
}
