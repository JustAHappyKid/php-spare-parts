<?php

namespace SpareParts\Webapp\UI;

function errorAlert($msg) {
  return '<div style="color: #700; background-color: #fcc; padding: 0 0.9em;
                        border: 0.1em solid #daa; border-radius: 0.2em;
                        max-width: 40em; margin: 3em auto;">' . $msg . '</div>';
}

