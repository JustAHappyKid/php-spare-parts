<?php

namespace SpareParts\WebClient;

use \Exception, \DOMDocument, \DOMXPath;

function makeValidXhtml($crappyHtml, $passedOptions = array()) {
  if (!class_exists('tidy')) {
    throw new Exception("'tidy' class does not exist; the tidy extension " .
                        "is apparently not installed or not enabled");
  }
  $tidy = new \tidy();
  $defaultOptions = array('output-xhtml' => true, 'clean' => true, 'indent' => false,
                          'tidy-mark' => false, 'wrap' => 0);
  $options = array_merge($defaultOptions, $passedOptions);
  $tidy->parseString($crappyHtml, $options, 'utf8');
  $tidy->cleanRepair();
  $cleanHtml = (string) $tidy;
  return $cleanHtml;
}

function findNodes($xpathObj, $xpathExpr) {
  $r = $xpathObj->evaluate($xpathExpr);
  $nodes = array();
  if (empty($r)) return $nodes;
  for ($i = 0; $i < $r->length; ++$i) {
    $nodes[] = $r->item($i);
  }
  return $nodes;
}

# This helps us avoid DOMDocument->loadHTML choking on HTML that has problems, such as
# multiple elements with the same ID.  In fact, for some reason errors reading something like
# "ID suchandsuch defined in Entity" seem to be coming up even when there are not multiple
# elements claiming the same ID; perhaps a bug in some versions of libxml2 or something...
function htmlSoupToDOMDocument($html, $logPrefix = null) {
  $prevUseExtErrorsVal = libxml_use_internal_errors(true);

  $dom = new DOMDocument();
  $dom->loadHTML($html);

  # Output (if requested) and then clear the errors that libxml produced...
  if ($logPrefix !== null) {
    # TODO: We should not be directly accessing logging library here!
    foreach (libxml_get_errors() as $error) warn($logPrefix . ": " . trim($error->message));
  }
  libxml_clear_errors();

  libxml_use_internal_errors($prevUseExtErrorsVal);
  return $dom;
}

function htmlSoupToXPathObject($html) {
  return new \DOMXPath(htmlSoupToDOMDocument($html));
}
