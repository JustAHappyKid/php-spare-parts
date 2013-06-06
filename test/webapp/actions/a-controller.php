<?php

require_once 'webapp/base-controller.php';

class AController extends \SpareParts\Webapp\Controller {

  # This is just a dummy method so we can assert (in test case) that it cannot be
  # accessed directly via a request.
  function init() {
    # Init some stuff - ok...
  }

  function twoWords() {
    return 'Sun Shine';
  }
}

return 'AController';
