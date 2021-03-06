<?php

namespace SpareParts\System\Process;

/**
 * Run $func as an "exclusive process", one that should only have one active instance
 * at any given time. This is done by using a lock/PID file; if an existing file is found
 * at the location specified by $pidFile, a notification will be emitted (to stdout) and
 * the current PHP process will exit.
 */
function runExclusiveProcess($pidFile, $func) {
  global $__SpareParts_System_Process_PIDFiles;
  $__SpareParts_System_Process_PIDFiles = array();
  register_shutdown_function('\SpareParts\System\Process\cleanPidFiles');
  checkPidFile($pidFile);
  $func();
}

function checkPidFile($file) {
  $f = @ fopen($file, 'r');
  if ($f) {
    flock($f, LOCK_SH);
    $pid = trim(fgets($f));
    if (posix_getsid($pid)) {
      echo "Found already-running job with PID $pid, according to PID file $file.\n";
      exit();
    }
    fclose($f);
  }
  $f = fopen($file, 'w');
  flock($f, LOCK_EX);
  fwrite($f, posix_getpid() . "\n");
  fclose($f);
  global $__SpareParts_System_Process_PIDFiles;
  $__SpareParts_System_Process_PIDFiles[] = $file;
  return $file;
}

function cleanPidFiles() {
  global $__SpareParts_System_Process_PIDFiles;
  foreach ($__SpareParts_System_Process_PIDFiles as $file) {
    $f = fopen($file, 'r');
    if (!$f) { continue; }
    flock($f, LOCK_SH);
    $pid = trim(fgets($f));
    fclose($f);
    if ($pid == posix_getpid()) {
      unlink($file);
    }
  }
}
