<?php

require_once 'database.php';

use \SpareParts\Database as DB;

function testHandlingOfDateTimeObjects() {
  $before = array('added_at' => new DateTime('now'));
  $after = DB\_sanitizeValues($before);
  assertEqual(strftime('%Y-%m-%d'), current(explode(' ', $after['added_at'])));
}
