<?php

require_once 'dates.php';

use \SpareParts\DateTime\DateSansTime, \SpareParts\DateTime as DT;

function testDateSansTimeClass() {
  $d1 = new DateSansTime('February 17, 1970 7:56 PM');
  assertEqual('1970-02-17', $d1->asDatabaseString());
  $d2 = new DateSansTime(new DateTime('2012-11-29 14:29:35'));
  assertEqual('2012-11-29', $d2->asDatabaseString());
  foreach (array(1, 3.2, new stdClass) as $v) {
    try {
      new DateSansTime($v);
      fail("Expected InvalidArgumentException for value $v");
    } catch (InvalidArgumentException $_) { /* that's what we want! */ }
  }
}

function testGetPreviousMonth() {
  assertEqual('2009-07', DT\getPreviousMonth('2009-08'));
  assertEqual('1999-12', DT\getPreviousMonth('2000-01'));
  assertEqual('1792-11', DT\getPreviousMonth('1792-12'));
  assertEqual('122-12',  DT\getPreviousMonth('123-01'));
}

function testGetLastDayOfMonth() {
  assertEqual(28, DT\getLastDayOfMonth(2007, 2));
  assertEqual(29, DT\getLastDayOfMonth(2008, 2));
  assertEqual(28, DT\getLastDayOfMonth(2009, 2));
  assertEqual(31, DT\getLastDayOfMonth(1997, 1));
  assertEqual(31, DT\getLastDayOfMonth(1902, 1));
  //assertEqual(31, DT\getLastDayOfMonth(1901, 1));
  //assertEqual(31, DT\getLastDayOfMonth(1100, 1));
  assertEqual(30, DT\getLastDayOfMonth(2030, 11));
  //assertEqual(31, DT\getLastDayOfMonth(500, 12));
  assertEqual(31, DT\getLastDayOfMonth(1955, 12));
}

function testGetNextDay() {
  assertEqual('2001-10-11', DT\getNextDay('2001-10-10'));
  assertEqual('1975-01-22', DT\getNextDay('1975-01-21'));
  assertEqual('1983-07-07', DT\getNextDay('1983-07-06'));
  assertEqual('2010-09-01', DT\getNextDay('2010-08-31'));
  assertEqual('2000-01-01', DT\getNextDay('1999-12-31'));
}
