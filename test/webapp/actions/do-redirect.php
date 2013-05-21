<?php

use \SpareParts\Webapp\DoRedirect;

return function($_) {
  throw new DoRedirect('/hitit/whatever');
};
