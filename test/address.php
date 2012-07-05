<?php

require_once 'webapp/forms.php';  # Forms\...
require_once 'address.php';       # newStateOrProvinceField, newCountrySelectField
//require_once 'web-client/html-parsing.php';

use \MyPHPLibs\Test, \MyPHPLibs\Webapp\Forms;

class AddressRelatedFieldsTests extends Test\TestHarness {

  /*
  function setUp() {
    $_SERVER['REQUEST_URI'] = '/some/path';
  }
  */

  function testBasicRendering() {
    $f = new Forms\Form('post');
    $f->addSection('name', array(
      newStateOrProvinceField('sp', 'state or province or whatever'),
      newZipOrPostalCodeField('pc', 'postal code'),
      newCountrySelectField('c', 'country')));
    $f->addSubmitButton('Submit it!');
    /*$xp = WebClient\htmlSoupToXPathObject($f->render());
    $this->assertNodeExists($xp, "//h1[text()='What is your name?']");
    $this->assertNodeExists($xp, "//form/fieldset[@id='name']//label[text()='Salutation']");
    $this->assertNodeExists($xp,
      "//form/fieldset[@id='name']//select[@name='prefix']");
    $this->assertNodeExists($xp,
      "//form/fieldset[@id='name']//select/option[@value='Mr' and text()='Mister']");
    $this->assertNodeExists($xp, "//form/fieldset[@id='name']//label[text()='Your name']");
    $this->assertNodeExists($xp,
      "//form/fieldset[@id='name']//input[@type='text' and @name='yername']");
    $this->assertNodeExists($xp, "//form//input[@type='submit' and @value='Submit it!']");*/
  }
}
