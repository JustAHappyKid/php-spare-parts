<?php

function debug($message) {
  log_msg('debug', $message);
}

function info($message) {
  log_msg('info', $message);
}

function warn($message) {
  log_msg('warning', $message);
}

function error($message) {
  log_msg('error', $message);
}

function log_msg($level, $message) {
  global $__path_to_log_file, $__log_file_handle, $__log_to_stdout;
  $datetime = strftime('%Y-%m-%d %H:%M');
  $log_line = "[$datetime] [$level] $message\n";
  if ($__log_to_stdout) {
    echo $log_line;
  } else {
    if (empty($__path_to_log_file)) {
      throw new Exception("No log file is configured");
    }
    if (empty($__log_file_handle)) {
      $__log_file_handle = fopen($__path_to_log_file, 'a');
    }
    fwrite($__log_file_handle, $log_line);
  }
}

function configure_logging($path) {
  global $__path_to_log_file, $__log_to_stdout;
  if ($path === null) {
    $__log_to_stdout = true;
  } else {
    $__log_to_stdout = false;
    $__path_to_log_file = $path;
  }
}