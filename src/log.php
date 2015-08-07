<?php

/**
 * This file/module provides a very basic logging framework.
 */

namespace SpareParts\Log;

/**
 * Provide a file to which all log-messages should be written. If $path is null, then log-messages
 * will be directed to 'stdout'.
 * 
 * @param $path string An absolute path to a file or null.
 */
function setLogFile($path) {
  global $__SpareParts_Log_pathToLogFile, $__SpareParts_Log_logToStdout;
  if ($path === null) {
    $__SpareParts_Log_logToStdout = true;
  } else {
    $__SpareParts_Log_logToStdout = false;
    $__SpareParts_Log_pathToLogFile = $path;
  }
}

/**
 * Provide a callable/function that will be used to format all log messages. The formatting
 * function should accept two parameters, a severity/level (e.g. "warn") and the log-message
 * "payload" (e.g. "something went wrong!").
 *
 * @param $msgFormatFunc callable A function name or a `Closure`.
 */
function setMessageFormatter($msgFormatFunc) {
  global $__SpareParts_Log_msgFormatFunc;
  $__SpareParts_Log_msgFormatFunc = $msgFormatFunc;
}

function logMsg($level, $message) {
  global $__SpareParts_Log_msgFormatFunc;
  $logLine = null;
  if ($__SpareParts_Log_msgFormatFunc) {
    $logLine = call_user_func($__SpareParts_Log_msgFormatFunc, $level, $message) . "\n";
  } else {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    $datetime = strftime('%Y-%m-%d %H:%M');
    $logLine = "[$datetime] [$level] " . ($ip ? "[$ip] " : "") . $message . "\n";
  }
  writeToLog($logLine);
}

function writeToLog($content) {
  global $__SpareParts_Log_logToStdout, $__SpareParts_Log_pathToLogFile,
         $__SpareParts_Log_logFileHandle;
  if ($__SpareParts_Log_logToStdout) {
    echo $content;
  } else {
    if (empty($__SpareParts_Log_pathToLogFile)) {
      throw new \Exception("No log file is configured");
    }
    if (empty($__SpareParts_Log_logFileHandle)) {
      $__SpareParts_Log_logFileHandle = fopen($__SpareParts_Log_pathToLogFile, 'a');
    }
    fwrite($__SpareParts_Log_logFileHandle, $content);
  }
}

function closeLogFile() {
  global $__SpareParts_Log_logFileHandle;
  if (!empty($__SpareParts_Log_logFileHandle)) {
    fclose($__SpareParts_Log_logFileHandle);
  }
  $__SpareParts_Log_logFileHandle = null;
}
