<?php

require_once 'template/base.php';
use \SpareParts\Template as T;

/**
 * Render the given template (string), using given $vars, and normalize spaces
 * (as, in these particular test-cases, we aren't concerned with specific whitespace).
 */
function renderAndNormalize($tpl, Array $vars) {
  return normalizeSpace(T\renderString($tpl, $vars));
}

/**
 * Remove all excess whitespace (e.g., convert "  " to " ") and trim whitespace from
 * all lines for given string ($s). This is useful so our tests can make assertions
 * that are less fragile when they're not concerned about particular spacing in the
 * template-engine output.
 */
function normalizeSpace($s) {
  $lines = array_filter(explode("\n", $s), function($l) { return trim($l) != ''; });
  $linesFixed = array_map(
    function($l) { return preg_replace('/\\s{2,}/', ' ', trim($l)); },
    $lines);
  return implode(' ', $linesFixed);
}
