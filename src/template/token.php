<?php

namespace SpareParts\Template\Token;

function render($t) {
  return is_array($t) ? $t[1] : $t;
}

function isOfType($token, $type) {
  return is_array($token) && $token[0] == $type;
}
