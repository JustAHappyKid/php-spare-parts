<?php

require_once 'us-phone.php';
use MyPHPLibs\Test, MyPHPLibs\Locales\US;

class PhoneFormInputTests extends Test\TestHarness {

  function testLegitPhoneNumberFormatsAreAccepted() {
    $this->assertInputAccepts('457.129.9237');
    $this->assertInputAccepts('(457) 129-9237');
    $this->assertInputAccepts('(683) 615 2802');
    $this->assertInputAccepts('819 448-6961');
    $this->assertInputAccepts('(866)-857-3949');
    $this->assertInputAccepts('9196763816');
    $this->assertInputAccepts('808 825 8304');
    $this->assertInputAccepts('831 / 737 / 2661');
    $this->assertInputAccepts('907/424-2291');
    $this->assertInputAccepts('1-457-129-9237');
    $this->assertInputAccepts('1 (457) 129-9237');
    $this->assertInputAccepts('19196763816');
    $this->assertInputAccepts('1 808 825 8304');
    $this->assertInputAccepts('339  511  8358');
    $this->assertInputAccepts('202 6681149');
    $this->assertInputAccepts('417-3232377');
  }

  function testBadPhoneNumberFormatsAreRejected() {
    $this->assertInputRejects('147-482-78999');
    $this->assertInputRejects('44571299237');
  }

  private function assertInputAccepts($v) {
    $r = $this->validateViaPhoneNumberInput($v);
    assertEqual(0, count($r), "'$v' should be accepted as a valid phone number");
  }

  private function assertInputRejects($v) {
    assertTrue(count($this->validateViaPhoneNumberInput($v)) > 0,
      "'$v' should be rejected as a valid phone number");
  }

  private function validateViaPhoneNumberInput($v) {
    $input = US\newPhoneNumberInput('phoneNum', 'Phone number');
    return $input->validate(array('phoneNum' => $v));
  }
}
