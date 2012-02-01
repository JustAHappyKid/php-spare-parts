<?php

# A class representing *only* a date -- no time component is kept.
class DateSansTime {
  private $date;
  function __construct($date = "today") {
    $this->date = strftime('%Y-%m-%d', strtotime($date));
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
  $yearNum = intval($year);
  $monthNum = intval($month);
  $m = null; $y = null;
  if ($monthNum == 12) {
    $m = 1; $y = $yearNum + 1;
  } else {
    $m = $monthNum + 1; $y = $yearNum;
  }
  $timestamp = strtotime("$y-$m-01 -1 day");
  return intval(date('d', $timestamp));
}

function getNextDay($date) {
  $timestamp = strtotime($date);
  $nextDayTimestamp = $timestamp + (60 * 60 * 24);
  return strftime('%Y-%m-%d', $nextDayTimestamp);
}