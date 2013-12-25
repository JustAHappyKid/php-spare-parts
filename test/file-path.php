<?php

require_once 'file-path.php';
use \SpareParts\FilePath as Path;

function testNormalizingPath() {
  $cases = array(
    'xtra///slashes//'      => 'xtra/slashes',
    '/dot/./slash/.'        => '/dot/slash',
    'let/us/step/../back'   => 'let/us/back',
    'path/../to'            => 'to',
    './some/path'           => 'some/path',
    '/path/to/dir'          => '/path/to/dir');
  foreach ($cases as $from => $to) {
    assertEqual($to, Path\normalize($from));
  }
}
