<?php

namespace SpareParts\Char;

function isLowerLetter($c) {
  return isWithinRange($c, 'a', 'z');
}

function isUpperLetter($c) {
  return isWithinRange($c, 'A', 'Z');
}

/**
 * Is the character-code for character $c within the range (inclusively) of the character codes
 * for $from and $to.
 *
 * TODO: Write better description?
 */
function isWithinRange($c, $from, $to) {

  if (!is_string($c) || strlen($c) != 1)
    throw new \InvalidArgumentException("Parameter must be a one-character string");

  return ord($c) >= ord($from) && ord($c) <= ord($to);
}
