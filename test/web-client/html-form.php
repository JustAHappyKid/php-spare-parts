<?php

require_once 'web-client/html-form.php';
use \SpareParts\WebClient\HtmlForm;

function testParsingForm() {
  $formHtml = '
    <form id="my-form" action="/path/to-it" method="post">
      <input type="hidden" value="abc123def456" name="special-hash" />
      <input type=\'text\' value=\'default text\' name=\'my-text-field\' />
      <input type="password" name="topsecret" value="doNOTtell!" />
      <select name="salutation">
       <option value="Dr.">Dr.</option>
     <option value="Fr.">Fr.</option>
     <option value="Mrs.">Mrs.</option>
      </select>
      <input type="radio" name="radio-opts" value="choice1" />
      <input type="radio" name="radio-opts" value="choice-number-2" />
      <textarea name="yer-message">hey - type here!</textarea>
      <input type="submit" name="submit-btn" value="Click it!" />
    </form>';
  $form = new HtmlForm($formHtml);
  assertEqual('post', $form->method);
  assertEqual('/path/to-it', $form->action);
  assertEqual('my-form', $form->id);
  assertEqual('hidden', $form->fields['special-hash']->type);
  assertEqual('abc123def456', $form->fields['special-hash']->value);
  assertEqual('text', $form->fields['my-text-field']->type);
  assertEqual('default text', $form->fields['my-text-field']->value);
  assertEqual('password', $form->fields['topsecret']->type);
  assertEqual('doNOTtell!', $form->fields['topsecret']->value);
  assertEqual('select', $form->fields['salutation']->type);
  assertEqual(3, count($form->fields['salutation']->options));
  assertEqual('radio', $form->fields['radio-opts']->type);
  assertEqual(2, count($form->fields['radio-opts']->options));
  assertEqual('textarea', $form->fields['yer-message']->type);
  assertEqual('hey - type here!', $form->fields['yer-message']->value);
  assertEqual('submit', $form->fields['submit-btn']->type);
  assertEqual('Click it!', $form->fields['submit-btn']->value);
}

function testParsingFormWithoutActionSpecified() {
  $form = new HtmlForm('<form method="get"><input type="text" name="a" /></form>');
  assertTrue($form->action === null);
}

function testParsingFormWithInputLabelsMatchingInputNames() {
  $formHtml = '
    <form action="/path/to-it" method="post">
      <label for="salutation">Prefix:</label>
      <select name="salutation"><option>Mr.</option><option>Mrs.</option></select>
      <label for="first-name">First Name:</label> <input type="text" name="first-name" />
      <label for="last-name">Last Name:</label> <input type="text" name="last-name" />
      <label for="abcxyz">Message:</label> <textarea id="abcxyz" name="yer-msg">here</textarea>
    </form>';
  $form = new HtmlForm($formHtml);
  assertEqual("Prefix:", $form->fields['salutation']->label);
  assertEqual("First Name:", $form->fields['first-name']->label);
  assertEqual("Last Name:", $form->fields['last-name']->label);
  assertEqual("Message:", $form->fields['yer-msg']->label);
}

function testAssociatingLabelsByProximityToInput() {
  $formHtml = '
    <form action="/path/to-it" method="post">
      <div><label><span class="header">First Name *</span></label>
        <span style="display:none;">First Name is required!</span>
        <input name="fn" type="text" /></div>
      <div><label><span class="header">Last Name *</span></label>
        <span style="display:none;">Last Name is required!</span>
        <input name="ln" type="text" /></div>
      <div>
        <label for="blabla">this should be ignored</label>
        <input name="yuckyuck" type="text" />
      </div>
    </form>';
  $form = new HtmlForm($formHtml);
  assertEqual("First Name *", $form->fields['fn']->label);
  assertEqual("Last Name *", $form->fields['ln']->label);
  assertEqual(null, $form->fields['yuckyuck']->label);
}

function testParsingFormWithLousyMarkup() {
  $form = new HtmlForm('
    <form method=POST
          action="submit.php">
      <input type=text value=default name="txt_field" />
    </form>');
  assert($form->method == "post");
  assert($form->action == "submit.php");
  assert($form->fields['txt_field']->value == "default");
}

function testInputTypeCheckingIsCaseInsensitive() {
  $form = new HtmlForm('
    <form>
      <input type="TEXT" name="myTextbox" />
      <input type="Checkbox" name="myCheckbox" value="Yes" />
      <input type="rAdiO" name="myRadioButton" value="Cool" />
    </form>');
  assertEqual('text', $form->fields['myTextbox']->type);
  assertEqual('checkbox', $form->fields['myCheckbox']->type);
  assertEqual('radio', $form->fields['myRadioButton']->type);
  assertEqual(1, count($form->fields['myRadioButton']->options));
}

function testDefaultValueForTextInput() {
  $form = new HtmlForm('<form><input type="text" name="labamba" /></form>');
  assertEqual('', $form->fields['labamba']->value);
  assertFalse($form->fields['labamba']->value === null);
}

function testHiddenInputValuesAreSubmitted() {
  $form = new HtmlForm('<form><input type="hidden" name="hiddenField" value="123" /></form>');
  assertEqual('123', $form->fields['hiddenField']->value);
  $vs = $form->getDefaultValuesToSubmit();
  assertEqual('123', $vs['hiddenField']);
}

function testInnerContentIsUsedForOptionValueWhenNoExplicitValueGiven() {
  $form = new HtmlForm('
    <form><select name="my-select"><option>Option1</option></select></form>');
  assertEqual('Option1', $form->fields['my-select']->options['Option1']);
}

function testDefaultValueForSelectInput() {
  $cases = array(
    'Dr.' => '
      <select name="salutation">
        <option value="Dr.">Dr.</option>
        <option value="Fr.">Fr.</option>
      </select>',
    'Fr.' => '
      <select name="salutation">
        <option value="Dr.">Dr.</option>
        <option value="Fr." selected="true">Fr.</option>
      </select>',
    '' => '
      <select name="salutation">
        <option value="" selected="">(select one)</option>
        <option value="Dr.">Dr.</option>
        <option value="Fr.">Fr.</option>
      </select>');
  foreach ($cases as $expectedDefault => $selectHtml) {
    $formHtml = "<form>$selectHtml</form>";
    $form = new HtmlForm($formHtml);
    $actualDefault = $form->fields['salutation']->value;
    if ($actualDefault !== $expectedDefault) {
      fail("Expected to get default value of '$expectedDefault' but got '$actualDefault'");
    }
  }
}

function testDefaultValueForCheckboxInput() {
  $form = new HtmlForm('
    <form>
      <input type="checkbox" name="c1" value="yes" />
      <input type="checkbox" name="c2" value="whatEVER" checked="checked" />
      <input type="checkbox" name="c3" checked="checked" />
    </form>');
  $vs = $form->getDefaultValuesToSubmit();
  assertTrue(empty($vs['c1']));
  assertEqual('whatEVER', $vs['c2']);
  assertTrue(isset($vs['c3']));
  assertTrue(!empty($vs['c3']));
}

function testNoValueIsSubmittedForNamelessCheckbox() {
  $form = new HtmlForm('
    <form>
      <input type="checkbox" onclick="toggleSomething();" />
    </form>');
  $vs = $form->getDefaultValuesToSubmit();
  assertFalse(array_key_exists('', $vs));
  assertTrue(count($vs) === 0);
}

function testOptionSelectedAttributeDoesNotNeedExplicitValue() {
  $formHtml = '<form> <select name="whatever"> <option value="value1">V1</option>
    <option value="Value2" selected>V2</option> </select> </form>';
  $form = new HtmlForm($formHtml);
  assertEqual('Value2', $form->fields['whatever']->value);
}

function testParsingOptionFieldWithContainedAbbrTag() {
  $formHtml = '<form><select name="state"><option value="OH">' .
    '<abbr title="Ohio">OH</abbr></option></select></form>';
  $form = new HtmlForm($formHtml);
  assertEqual(1, count($form->fields['state']->options));
  assertEqual('OH', $form->fields['state']->options['OH']);
}

function testSelectInputThatUsesOptgroupElements() {
  $form = new HtmlForm('<form method="post" action="./">
    <select name="topic">
      <optgroup label="Constitution">
        <option value="Second Amendment">Second Amendment</option>
      </optgroup>
      <optgroup label="Education">
        <option value="Student Loans">Student Loans</option>
        <option value="Completely Useless">Completely Useless</option>
      </optgroup>
  </form></select>');
  assertEqual(3, count($form->fields['topic']->options));
}

function testNoTrailingWhitespaceIsSubmittedForSelectedOption() {
  $form = new HtmlForm('<form> <select name="state">
      <OPTION>MI
      <OPTION selected>MN
      <OPTION>MO
    </select> </form>');
  $v = $form->getDefaultValuesToSubmit();
  assertEqual('MN', $v['state']);
}

function testParsingFormWithMultipleSelectFields() {
  $formHtml = '
    <form>
      <select name="select1">
        <option value="opt1">1</option> <option value="opt2">2</option>
      </select>
      <select name="select2">
        <option value="opt3">3</option> <option value="opt4">4</option>
      </select>
    </form>';
  $form = new HtmlForm($formHtml);
  assertEqual(2, count($form->fields['select1']->options));
  assertEqual('2', $form->fields['select1']->options['opt2']);
  assertEqual(2, count($form->fields['select2']->options));
  assertEqual('3', $form->fields['select2']->options['opt3']);
}

function testRadioButtons() {
  $form = new HtmlForm('<form>
    <input type="radio" name="rb" value="choice1" />
    <input type="radio" name="rb" value="choice2" /></form>');
  assert($form->fields['rb']->options['choice1'] == "choice1");
  assert($form->fields['rb']->options['choice2'] == "choice2");
}

# Browsers seem to give radio buttons a default 'value' attribute of "on"...
function testDefaultValueForRadioButton() {
  $form1 = new HtmlForm('<form><input type="radio" name="my-rad-btn" checked="checked" /></form>');
  assertEqual('on', $form1->fields['my-rad-btn']->value);
  $form2 = new HtmlForm(
    '<form><input type="radio" name="my-rad-btn" checked="checked" value="" /></form>');
  assertEqual('', $form2->fields['my-rad-btn']->value);
}

function testObtainingTextNearToRadioButtons() {
  $form1 = new HtmlForm('<form> <div>
      <label class="contactForm" for="required-affl">I would like to subscribe to <br /> 
        Representative Ding Dong\'s e-newsletter* :</label> <br /> 
      <input name="affl" type="radio" value="abc" /> 
      Subscribe&nbsp;&nbsp;&nbsp;&nbsp;
      <input name="affl" type="radio" value="xyz" /> 
      Unsubscribe&nbsp;&nbsp;&nbsp;&nbsp; 
    </div> </form>');
  assertEqual('Subscribe', trim($form1->fields['affl']->textAfter['abc']));
  assertEqual('Unsubscribe', trim($form1->fields['affl']->textAfter['xyz']));
}

function testInputWithNoTypeAttributeDefaultsToTextType() {
  $form = new HtmlForm('<form><input name="no-type" value="somethin" /></form>');
  assertEqual('text', $form->fields['no-type']->type);
  assertEqual('somethin', $form->fields['no-type']->value);
}

function testInputWithTypeInputDefaultsToTextType() {
  $form = new HtmlForm('<form><input type="input" name="yada" /></form>');
  assertEqual('text', $form->fields['yada']->type);
}

function testReadingMaxLengthAttributeForTextField() {
  $form = new HtmlForm('<form><input type="text" name="f" maxlength="4" /></form>');
  assertEqual(4, (int) $form->fields['f']->maxLength);
}

function testFieldsDoNotLeakAcrossForms() {
  $html = '
    <form>
      <input type="text" name="form1-txt" value="nuthin" />
      <select name="select1"><option value="form1-opt">1</option></select>
      <textarea name="the-textarea">hey hey</textarea>
    </form>
    <form>
      <select name="select2"><option value="form2-opt">2</option></select>
      <input type="password" name="pass" value="not-4-u" />
      <textarea name="the-textarea">this is the 2nd form!</textarea>
    </form>';
  $dom = new DOMDocument();
  $dom->loadHTML($html);
  $xpath = new DOMXPath($dom);
  $formNodes = $xpath->evaluate("//form");
  $form1 = HtmlForm::fromDOMNode($formNodes->item(0), $xpath);
  $form2 = HtmlForm::fromDOMNode($formNodes->item(1), $xpath);
  assertEqual(3, count($form1->fields));
  assertEqual(3, count($form2->fields));
  assertEqual('nuthin', $form1->fields['form1-txt']->value);
  assertEqual('1', $form1->fields['select1']->options['form1-opt']);
  assertEqual('hey hey', $form1->fields['the-textarea']->value);
  assertEqual('2', $form2->fields['select2']->options['form2-opt']);
  assertEqual('not-4-u', $form2->fields['pass']->value);
  assertEqual('this is the 2nd form!', $form2->fields['the-textarea']->value);
}

function testSubmissionUsingAnImageInputTypeAsSubmitButton() {
  $form = new HtmlForm('
    <form action="/contacts/subscribe" method="post" id="subscribe_form">
      <div>Email: <input class="text" name="email" type="text" /></div>
      <div><input name="Submit" src="/signup-button.png" type="image" /></div>
    </form>');
  $values = $form->getDefaultValuesToSubmit($submitButton = 'Submit');
  assertTrue(isset($values['Submit.x']));
  assertTrue(is_numeric($values['Submit.x']));
  assertTrue(isset($values['Submit.y']));
  assertTrue(is_numeric($values['Submit.y']));
  assertFalse(array_key_exists('Submit', $values));
  $valuesNoButton = $form->getDefaultValuesToSubmit();
  assertFalse(array_key_exists('Submit', $valuesNoButton));
}

function testNamelessSubmitButtonDoesNotYieldValueToSubmit() {
  $form = new HtmlForm('
    <form action="/where-to-post" method="post">
      Enter your name: <input type="text" name="yourName" />
      <input type="submit" value="Submit" />
    </form>');
  assertEqual(array('yourName'), array_keys($form->getDefaultValuesToSubmit()));
  assertEqual(1, count($form->getDefaultValuesToSubmit()));
}

function testValueIsSubmittedForButtonWhenNotExplicitlySpecifiedAsButtonToUse() {
  $form = new HtmlForm('
    <form action="/huhwhat" method="post">
      Enter sumthing: <input type="text" name="sumthin" />
      <input type="submit" name="myfavbtn" value="Let a rip" />
    </form>');
  $values = $form->getDefaultValuesToSubmit();
  assertTrue(isset($values['myfavbtn']),
    'Value should be submitted for submit button that has a name, even if not explicitly ' .
    'specified in call to getDefaultValuesToSubmit');
  assertEqual('Let a rip', $values['myfavbtn']);
}

function testUsingParticularSubmitButtonOnMultiButtonForm() {
  $form = new HtmlForm('
    <form action="/take-it" method="post">
      Enter your secret: <input type="text" name="s" />
      <input type="submit" name="him" value="Submit to him" />
      <input type="submit" name="her" value="Submit to her" />
    </form>');
  $values = $form->getDefaultValuesToSubmit('her');
  assertTrue(isset($values['her']));
  assertEqual('Submit to her', $values['her']);
  assertTrue(empty($values['him']));
  assertEqual(2, count($values));
}

function testParsingButtonTags() {
  $form = new HtmlForm('
    <form action="/submit-here" method="post">
      Type something: <input type="text" name="stuff" />
      <button type="submit" name="btn" value="ok">Submit your stuff</button>
    </form>');
  $btns = $form->getButtons();
  assertEqual(1, count($btns));
  assertEqual('Submit your stuff', $btns[0]->buttonText);
  assertEqual('submit', $btns[0]->type);
  assertEqual('ok', $btns[0]->value);
}

function testButtonTagWithoutSpecifiedNameIsStillAcknowledged() {
  $form = new HtmlForm('
    <form action="/submit-here" method="post">
      Enter what you want: <input type="text" name="need" /> <button type="submit">Go!</button>
    </form>');
  $btns = $form->getButtons();
  assertEqual(1, count($btns));
  assertEqual('Go!', $btns[0]->buttonText);
}

function testSubmitButtonUsingInputTagWithoutSpecifiedNameIsStillAcknowledged() {
  $form = new HtmlForm('
    <form action="/submit-here" method="post">
      bla bla: <input type="text" name="bla" /> <input type="submit" value="Send" />
    </form>');
  $btns = $form->getButtons();
  assertEqual(1, count($btns));
  assertEqual('Send', $btns[0]->buttonText);
}

function testHandlingFormThatContainsMultipleSubmitButtonsHavingSameName() {
  $form = new HtmlForm('
    <form action="/giddyup" method="post">
      Write me something: <textarea name="txt">here</textarea>
      <input type="submit" name="btn" value="Submit here" />
      <input type="submit" name="btn" value="Or here" />
    </form>');
  $btn = $form->getButtonHavingText('Or here');
  $values = $form->getDefaultValuesToSubmit($btn);
  assertEqual('Or here', $values['btn']);
}

function testAttemptingToSubmitFormUsingNonButtonInput() {
  $form = new HtmlForm('
    <form action="/submit-here" method="post">
      <input type="text" name="no-btn" /> <input type="submit" value="Go" />
    </form>');
  try {
    $form->getDefaultValuesToSubmit($form->fields['no-btn']);
  } catch (InvalidArgumentException $_) { /* That's what we're hoping for. */ }
}

function testRecognizingDefaultValueForInputWithTypeEmail() {
  $form = new HtmlForm('
    <form action="/contact" method="post">
      <div>Email: <input name="e" type="email" value="fred@cracker.com" /></div>
      <div><input type="submit" value="G-g-go" /></div>
    </form>');
  $values = $form->getDefaultValuesToSubmit();
  assertTrue(isset($values['e']));
  assertEqual('fred@cracker.com', $values['e']);
}
