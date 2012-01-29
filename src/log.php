<?php

function logMsg($level, $message) {
  global $__msgFormatFunc;
  $logLine = null;
  if ($__msgFormatFunc) {
    $logLine = call_user_func($__msgFormatFunc, $level, $message) . "\n";
  } else {
    $datetime = strftime('%Y-%m-%d %H:%M');
    $logLine = "[$datetime] [$level] $message\n";
  }
  writeToLog($logLine);
}

function writeToLog($content) {
  global $__logToStdout, $__pathToLogFile, $__logFileHandle;
  if ($__logToStdout) {
    echo $content;
  } else {
    if (empty($__pathToLogFile)) {
      throw new Exception("No log file is configured");
    }
    if (empty($__logFileHandle)) {
      $__logFileHandle = fopen($__pathToLogFile, 'a');
    }
    fwrite($__logFileHandle, $content);
  }
}

function configureLogging($path, $msgFormatFunc = null) {
  global $__pathToLogFile, $__logToStdout, $__msgFormatFunc;
  if ($path === null) {
    $__logToStdout = true;
  } else {
    $__logToStdout = false;
    $__pathToLogFile = $path;
  }
  $__msgFormatFunc = $msgFormatFunc;
}
