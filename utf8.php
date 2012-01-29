<?php

function hasInvalidUTF8Chars($s) {
  $regex = '/([\x80-\xBF]' .    # invalid byte in range 10000000 - 10111111
            '|[\xC0-\xFF])/x';  # invalid byte in range 11000000 - 11111111
  return preg_match($regex, $s) > 0;
}

function purgeInvalidUTF8Chars($s) {
  $regex = '/([\x00-\x7F]' .                 # single-byte sequences   0xxxxxxx
            '|[\xC0-\xDF][\x80-\xBF]' .      # double-byte sequences   110xxxxx 10xxxxxx
            '|[\xE0-\xEF][\x80-\xBF]{2}' .   # triple-byte sequences   1110xxxx 10xxxxxx * 2
            '|[\xF0-\xF7][\x80-\xBF]{3})' .  # quadruple-byte sequence 11110xxx 10xxxxxx * 3
            '|./x';
  return preg_replace($regex, '$1', $s);
}
