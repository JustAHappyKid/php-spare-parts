<?php

namespace SpareParts\Webapp\UI;

function errorAlert($msg) {
  return '<div style="color: #700; background-color: #fcc; padding: 0 0.9em;
                        border: 0.1em solid #daa; border-radius: 0.2em;
                        max-width: 40em; margin: 3em auto;">' . $msg . '</div>';
}

function simpleErrorPage($msg, $title = "An error has occurred") {
  return "<!DOCTYPE html>\n" .
    "<html>\n" .
    "  <head>\n" .
    "    <title>" . htmlspecialchars($title) . "</title>\n" .
    "  </head>\n" .
    "  <body>\n" .
    "    " . errorAlert($msg) . "\n" .
    "  </body>\n" .
    "</html>";
}
