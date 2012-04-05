<?php

return function($context) {
  return '<p>The next component: ' . $context->takeNextPathComponent() . '</p>';
};
