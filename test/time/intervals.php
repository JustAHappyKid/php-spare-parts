<?php

require_once 'time/intervals.php';
use \SpareParts\Time;

function testReadingInterval() {
  $interval = Time\readInterval('30 seconds');
  assertEqual(30, $interval->s);
  assertEqual(0, $interval->i);
  assertEqual(0, $interval->h);
  assertEqual(0, $interval->d);
  assertEqual(0, $interval->m);
  assertEqual(0, $interval->y);
  $interval = Time\readInterval('80 seconds');
  assertEqual(80, $interval->s + ($interval->i * 60));
  assertEqual(0, $interval->h);
}
