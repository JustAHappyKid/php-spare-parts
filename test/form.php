<?php

require_once 'form.php';
require_once 'html-parsing.php';

use \MyPHPLibs\Test, \MyPHPLibs\WebClient;

class InputFormTests extends Test\TestHarness {

  function setUp() {
    $_SERVER['REQUEST_URI'] = '/some/path';
  }

  function testBasicRendering() {
    $f = new InputForm('post');
    $f->setHeader('What is your name?');
    $prefix = new SelectInput('prefix', 'Salutation', array('Mr' => 'Mister', 'Mrs' => 'Misses'));
    $yername = new TextLineInput('yername', 'Your name');
    $prefix->setAttribute('onchange', 'alert("quotes should be escaped!");');
    $f->addSection('name', array($prefix, $yername));
    $f->addSubmitButton('Submit it!');
    $xp = WebClient\htmlSoupToXPathObject($f->render());
    $this->assertNodeExists($xp, "//h1[text()='What is your name?']");
    $this->assertNodeExists($xp, "//form/fieldset[@id='name']//label[text()='Salutation']");
    $this->assertNodeExists($xp,
      "//form/fieldset[@id='name']//select[@name='prefix']");
    $this->assertNodeExists($xp,
      "//form/fieldset[@id='name']//select/option[@value='Mr' and text()='Mister']");
    $this->assertNodeExists($xp, "//form/fieldset[@id='name']//label[text()='Your name']");
    $this->assertNodeExists($xp,
      "//form/fieldset[@id='name']//input[@type='text' and @name='yername']");
    $this->assertNodeExists($xp, "//form//input[@type='submit' and @value='Submit it!']");
  }

  function testBasicValidation() {
    $f = new InputForm('post');
    $f->addHiddenInput('hehe', 'haha');
    $f->addSection('name', array(new TextLineInput('fn', 'First Name'),
                                 new TextLineInput('ln', 'Last Name')));
    $f->validate(array('fn' => '', 'ln' => 'Potter'));
    assertFalse($f->isValid());
    $xp = WebClient\htmlSoupToXPathObject($f->render());
    assertFalse($f->isValid());
    $this->assertNodeExists($xp, "//form//input[@name='fn' and @value='']");
    $this->assertErrorMessageForFieldIsPresent($xp, 'fn', 'First Name');
    $f->validate(array('hehe' => 'haha', 'fn' => '', 'ln' => ''));
    assertEqual('haha', $f->getValue('hehe'));
    $xp = WebClient\htmlSoupToXPathObject($f->render());
    assertFalse($f->isValid());
    $this->assertNodeExists($xp, "//form//input[@name='ln' and @value='']");
    $this->assertErrorMessageForFieldIsPresent($xp, 'fn', 'First Name');
    $this->assertErrorMessageForFieldIsPresent($xp, 'ln', 'Last Name');
  }

  function testValidationSucceedsWhenOptionalFieldsAreBlank() {
    $f = new InputForm('post');
    $f->addSection('name', array(newTextLineInput('txt', 'Text'),
                                 newPasswordInput('pass', 'Password'),
                                 newEmailAddressInput('email', 'Email'),
                                 newDateTimeInput('datetime', 'Date/time'),
                                 newDollarAmountInput('amount', 'Amount')));
    $f->setOptionalFields(array('txt', 'pass', 'email', 'datetime', 'amount'));
    $f->validate(array('txt' => '', 'pass' => '', 'email' => '', 'datetime' => '', 'amount' => ''));
    assertTrue($f->isValid());
  }

  function testHiddenInputsAreNotRequiredToHaveAValue() {
    $f = new InputForm('post');
    $f->addHiddenInput('ohnothing', '');
    $f->addSection('name', array(new TextLineInput('name', 'Name')));
    $f->validate(array('name' => 'Fred'));
    assertTrue($f->isValid());
  }

  function testDefaultValues() {
    $f = new InputForm('post');
    $prefix = new SelectInput('prefix', 'Prefix', array('Mr' => 'Mister', 'Mrs' => 'Misses',
                                                        'R' => 'Revered'));
    $yername = new TextLineInput('n', 'Your name');
    $likeit = new CheckboxInput('like', 'Do you like it?', false);
    $f->addSection('name', array($prefix, $yername, $likeit));
    $f->addSubmitButton('Submit');
    $f->setDefaultValues(array('prefix' => 'R', 'n' => 'Chuck', 'like' => true));
    $xp = WebClient\htmlSoupToXPathObject($f->render());
    $this->assertNodeExists($xp,
      "//select[@name='prefix']/option[@value='R' and @selected='selected']");
    $this->assertNodeExists($xp, "//input[@name='n' and @value='Chuck']");
    $this->assertNodeExists($xp, "//input[@name='like' and @checked='checked']");
  }

  function testCheckboxRetainsValue() {
    $f = new InputForm('post');
    $f->addSection('checkbox-test', array(newCheckboxInput('checkit', 'Check me!', false)));
    $f->addSubmitButton('Go');
    $f->validate(array('checkit' => 'on'));
    $this->renderFormAndAssertNodeExists($f, "//input[@name='checkit' and @checked]");
  }

  function testMultiCheckboxInput() {
    $f = new InputForm('post');
    $f->addSection('multicheckbox-test', array(
      newMultiCheckboxInput('boxes', 'Check some',
                            array(1 => 'Not checked', 2 => 'Checked by default'))));
    $f->setDefaultValues(array('boxes' => array(2)));
    $f->validate(array('boxes' => array(2 => 'on')));
    assertEqual(array(2), $f->getValue('boxes'));
    $f->validate(array('boxes' => array(1 => 'on')));
    assertEqual(array(1), $f->getValue('boxes'));
    $f->validate(array('boxes' => array(1 => 'on', 2 => 'on')));
    assertEqual(array(1, 2), $f->getValue('boxes'));
  }

  function testEmailAddressInput() {
    $f = new InputForm('post');
    $f->addSection('name', array(
      newEmailAddressInput('email', 'Email')->allowNameAndAddressFormat()));
    $f->validate(array('email' => 'Joe Patty <joey@tabcollab.net>'));
    assertTrue($f->isValid());
    assertEqual('Joe Patty <joey@tabcollab.net>', $f->getValue('email'));
  }

  private function renderFormAndAssertNodeExists($f, $q) {
    $xp = WebClient\htmlSoupToXPathObject($f->render());
    $this->assertNodeExists($xp, $q);
  }

  private function assertNodeExists($xpObj, $q) {
    $numResults = $xpObj->query($q)->length;
    assertTrue($numResults > 0, "Could not find node matching following XPath expression: $q");
    assertFalse($numResults > 1, "Multiple matches found for following XPath expression: $q");
  }

  private function assertErrorMessageForFieldIsPresent($xp, $name, $label) {
    $this->assertNodeExists($xp,
      "//*[contains(text(), 'provide a value for the field')]/em[text()='$label']");
  }
}
