<?php

namespace SpareParts\DateTime;

require_once dirname(__FILE__) . '/reflection.php'; # identifyClassOrType

use \DateTime, \InvalidArgumentException, \SpareParts\Reflection;

# A class representing *only* a date -- no time component is kept.
class DateSansTime {
  private $date;
  function __construct($date = "today") {
    if ($date instanceof DateTime) {
      $this->date = $date->format('Y-m-d');
    } else if (is_string($date)) {
      $this->date = strftime('%Y-%m-%d', strtotime($date));
    } else {
      throw new InvalidArgumentException("Expected DateTime object or string representation " .
        "of date, but got " . Reflection\identifyClassOrType($date));
    }
  }
  public function dayAfter() {
    return new DateSansTime($this->date . ' + 1 day');
  }
  public function asDatabaseString() {
    return $this->date;
  }
}

function getMonthName($monthNum) {
  return strftime('%B', strtotime(sprintf('2000-%02d', $monthNum)));
}

function isMonthAbbreviation($str) {
  $a = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
  return in_array(strtolower($str), array_map('strtolower', $a));
}

function getPreviousMonth($date) {
  list($thisYear, $thisMonth) = array_map('intval', explode('-', $date));
  $prevMonthYear = $thisMonth == 1 ? $thisYear - 1 : $thisYear;
  $prevMonth = $thisMonth == 1 ? 12 : $thisMonth - 1;
  return sprintf('%d-%02d', $prevMonthYear, $prevMonth);
}

function getNextMonth($date) {
  list($thisYear, $thisMonth) = array_map('intval', explode('-', $date));
  $nextMonthYear = $thisMonth == 12 ? $thisYear + 1 : $thisYear;
  $nextMonth = $thisMonth == 12 ? 1 : $thisMonth + 1;
  return sprintf('%d-%02d', $nextMonthYear, $nextMonth);
}

function getLastDayOfMonth($year, $month) {
  if (!isInteger($year)) throw new InvalidArgumentException("Expected integer value for \$year");
  if (!isInteger($month)) throw new InvalidArgumentException("Expected integer value for \$month");
  $yearNum = intval($year);
  $monthNum = intval($month);
  if ($yearNum < 1902)
    throw new InvalidArgumentException("Sorry, no dates before 1902 supported");
  if ($monthNum < 1 || $monthNum > 12)
    throw new InvalidArgumentException("Invalid month provided: $month");
  $m = null; $y = null;
  if ($monthNum == 12) {
    $m = 1; $y = $yearNum + 1;
  } else {
    $m = $monthNum + 1; $y = $yearNum;
  }
  // $timestamp = strtotime("$y-$m-01 -1 day");
  $oneDay = (60 * 60 * 24);
  $timestamp = strtotime("$y-$m-01") - $oneDay;
  return intval(date('d', $timestamp));
}

function getNextDay($date) {
  $timestamp = strtotime($date);
  $nextDayTimestamp = $timestamp + (60 * 60 * 24);
  return strftime('%Y-%m-%d', $nextDayTimestamp);
}
