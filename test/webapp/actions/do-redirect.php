<?php

use \MyPHPLibs\Webapp\DoRedirect;

return function($_) {
  throw new DoRedirect('/hitit/whatever');
};
