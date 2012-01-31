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
