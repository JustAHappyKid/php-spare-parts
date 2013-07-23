<?php

namespace SpareParts\Template\Token;

function render($t) {
  return is_array($t) ? $t[1] : $t;
}
