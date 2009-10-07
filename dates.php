<?php

function getPreviousMonth($date) {
  list($thisYear, $thisMonth) = array_map('intval', explode('-', $date));
  $prevMonthYear = $thisMonth == 1 ? $thisYear - 1 : $thisYear;
  $prevMonth = $thisMonth == 1 ? 12 : $thisMonth - 1;
  return sprintf('%d-%02d', $prevMonthYear, $prevMonth);
}
