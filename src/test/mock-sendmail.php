<?php

# Use functionality here to help you "mock out" PHP's email-sending functionality for
# testing purposes... The idea is to use a "mock" sendmail binary (or script) that will
# store the emails passed it somewhere (e.g., in a database or in temporary files), so
# assertions can be made against them.
#
# XXX: More to come soon (hopefully!)...

namespace SpareParts\Test;

/**
 * Assert 'sendmail_path' was properly configured for testing environment and put its
 * directory on the PATH environment variable.
 */
function addMockSendmailToPath($pathToExecutable) {
  $filename = basename($pathToExecutable);
  $smPath = ini_get("sendmail_path");
  if ($smPath != 'mock-sendmail') {
    throw new Exception("Expected 'sendmail_path' config variable to be set to " .
                        "'$filename' but it is set as '$smPath'");
  }
  $binDir = dirname($pathToExecutable);
  putenv("PATH=$binDir:" . getenv('PATH'));
}
