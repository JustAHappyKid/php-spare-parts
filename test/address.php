<?php

require_once 'webapp/forms.php';  # Forms\...
require_once 'address.php';       # newStateOrProvinceField, newCountrySelectField

use \SpareParts\Test, \SpareParts\Webapp\Forms;

class AddressRelatedFieldsTests extends Test\TestHarness {

  function testBasicRendering() {
    $f = new Forms\Form('post');
    $f->addSection('name', array(
      newStateOrProvinceField('sp', 'state or province or whatever'),
      newZipOrPostalCodeField('pc', 'postal code'),
      newCountrySelectField('c', 'country')));
    $f->addSubmitButton('Submit it!');
  }
}
