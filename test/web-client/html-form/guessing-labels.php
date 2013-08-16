<?php

require_once 'web-client/html-form.php';  # HtmlForm
require_once 'string.php';                # contains

use \SpareParts\WebClient\HtmlForm;

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

# This test assures that guessing still works properly when two (or more) radio buttons
# have similar, but not matching, text next to them -- in this case, both options include
# text "Subscribe".
# (There was a bug that occurred with this use-case at one point...)
function testGuessingLabelForRadioButtonWhenSameSubstringAppearsAfterMultipleButtons() {
  $f = new HtmlForm('<form>
      Would you like to receive my e-newsletter?
      <span>
        <input type="radio" value="Y" name="radbtn" /> Subscribe
        <input type="radio" value="N" name="radbtn" /> Do Not Subscribe
      </span>
    </form>');
  assertTrue(contains($f->guessFieldLabel($f->fields['radbtn']),
                      'Would you like to receive my e-newsletter?'));
}

# TODO: We also need a test-case that reveals a bug whereby text that appears in a label
#       for a specific radio button (such as "Subscribe") appears also in the label for the
#       label of the field as a whole (e.g., "Subscribe to my newsletter?")...
#       Then the bug needs fixed!

function testCallingGuessFieldLabelTwiceDoesNotBreak() {
  $formHtml = '<form action="/path/to-it" method="post"><input type="text" name="f" /></form>';
  $form = new HtmlForm($formHtml);
  assertEqual(null, $form->guessFieldLabel($form->fields['f']));
  assertEqual(null, $form->guessFieldLabel($form->fields['f']));
}
