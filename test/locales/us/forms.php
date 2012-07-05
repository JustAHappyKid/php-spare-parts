<?php

require_once 'webapp/forms.php';      # Forms\...
require_once 'locales/us/forms.php';  # newStateSelectField, newNineDigitZipCodeInput, ...
//require_once 'web-client/html-parsing.php';

use \MyPHPLibs\Test, \MyPHPLibs\Webapp\Forms, \MyPHPLibs\Locales\US;

class TestUSSpecificAddressRelatedFields extends Test\TestHarness {

  function setUp() {
    $_SERVER['REQUEST_URI'] = '/some/path';
  }

  function testBasics() {
    $f = new Forms\Form('post');
    $f->addSection('stuff', array(
      US\newStateSelectField('state', 'State where yer at'),
      US\newZipCodeField('zipWhatev', 'Your full ZIP!', $lastFourRequired = false),
      US\newNineDigitZipCodeField('fullZip', 'Your full ZIP!', $lastFourRequired = true)));
    $f->addSubmitButton('Okay!');
    $f->render();
  }
}
