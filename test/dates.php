<?php

require_once 'dates.php';

function testGetPreviousMonth() {
  assertEqual('2009-07', getPreviousMonth('2009-08'));
  assertEqual('1999-12', getPreviousMonth('2000-01'));
  assertEqual('1792-11', getPreviousMonth('1792-12'));
  assertEqual('122-12', getPreviousMonth('123-01'));
}

function testGetLastDayOfMonth() {
  assertEqual(28, getLastDayOfMonth(2007, 2));
  assertEqual(29, getLastDayOfMonth(2008, 2));
  assertEqual(28, getLastDayOfMonth(2009, 2));
  assertEqual(31, getLastDayOfMonth(1100, 1));
  assertEqual(30, getLastDayOfMonth(2030, 11));
  assertEqual(31, getLastDayOfMonth(500, 12));
}

function testGetNextDay() {
  assertEqual('2001-10-11', getNextDay('2001-10-10'));
  assertEqual('1975-01-22', getNextDay('1975-01-21'));
  assertEqual('1983-07-07', getNextDay('1983-07-06'));
  assertEqual('2010-09-01', getNextDay('2010-08-31'));
  assertEqual('2000-01-01', getNextDay('1999-12-31'));
}
