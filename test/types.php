<?php

function testAtFunction() {
  assertEqual("hi", at(array(4 => 'hi'), 4));
  assertEqual("oh, it's gone", at(array(7 => 'uh-oh'), 4, "oh, it's gone"));
  assertEqual(null, at(array(1 => 'sumthing', 2 => null), 2, "default!"));
}

function testAtFunctionHandlesMissingValueOkay() {
  assertEqual("dEfAuLt", at(array('a' => '1'), 'b', "dEfAuLt"));
}

function testAtFunctionPassesStrictParameterForInArrayFunc() {
  assertEqual(false, at(array(0 => 'something'), 'not-in-there', false));
}

function testReadBoolFromStr() {
  assertTrue(readBoolFromStr('true'));
  assertTrue(readBoolFromStr('t'));
  assertTrue(readBoolFromStr('yes'));
  assertTrue(readBoolFromStr('Yes'));
  assertTrue(readBoolFromStr('YES'));
  assertTrue(readBoolFromStr('y'));
  assertTrue(readBoolFromStr('1'));
  assertFalse(readBoolFromStr('false'));
  assertFalse(readBoolFromStr('FALSE'));
  assertFalse(readBoolFromStr('f'));
  assertFalse(readBoolFromStr('no'));
  assertFalse(readBoolFromStr('No'));
  assertFalse(readBoolFromStr('NO'));
  assertFalse(readBoolFromStr('n'));
  assertFalse(readBoolFromStr('0'));
}
