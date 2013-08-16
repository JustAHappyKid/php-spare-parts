<?php

function testGuessingLabelForRadioButtonSet() {
  $form = new HtmlForm('<form> <div>
      <p>Would you like a banana? <strong class="required">*</strong></p>
      <div>
        <div>
          <label for="banana-check-1">
            <input type="radio" name="banana-check" id="banana-check-1" value="Y" />
            Yes</label>
        </div>
        <div>
          <label for="banana-check-2">
            <input type="radio" name="banana-check" id="banana-check-2" value="N" />
            No</label>
        </div>
      </div>
    </div> </form>');
  assertTrue(strstr($form->guessFieldLabel($form->fields['banana-check']),
                    'Would you like a banana') != false);
}

function testGuessingLabels() {
  $formHtml = '
    <form action="/path/to-it" method="post">
      <div>Prefix:
        <select name="salutation"><option>Mr.</option><option>Mrs.</option></select></div>
      <div>Your Name: <input type="text" name="name" /></div>
      <div>Message: <textarea name="yer-msg">here</textarea></div>
    </form>';
  $form = new HtmlForm($formHtml);
  assertEqual("Prefix:", $form->guessFieldLabel($form->fields['salutation']));
  assertEqual("Your Name:", $form->guessFieldLabel($form->fields['name']));
  assertEqual("Message:", $form->guessFieldLabel($form->fields['yer-msg']));
}

function testCallingGuessFieldLabelTwiceDoesNotBreak() {
  $formHtml = '<form action="/path/to-it" method="post"><input type="text" name="f" /></form>';
  $form = new HtmlForm($formHtml);
  assertEqual(null, $form->guessFieldLabel($form->fields['f']));
  assertEqual(null, $form->guessFieldLabel($form->fields['f']));
}
