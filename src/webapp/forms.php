<?php

namespace SpareParts\Webapp\Forms;

require_once dirname(dirname(__FILE__)) . '/types.php';
require_once dirname(dirname(__FILE__)) . '/validation.php';

use \Exception, \InvalidArgumentException, \SpareParts\Validation, \SpareParts\Reflection;

abstract class BaseFormContainer {

  protected $nodes = array();
  protected $errors = array();
  protected $notices = array ();
  private $validated = false;

  public function addSection($name, Array $nodes = array()) {
    $section = new FormSection($name, $nodes);
    $this->nodes []= $section;
    return $section;
  }

  public function addMarkup($html) {
    $this->nodes []= $html;
  }

  public function addField(Field $f) {
    $this->nodes []= $f;
  }

  public function addFields(Array $fs) {
    foreach ($fs as $f) $this->nodes []= $f;
  }

  public function addHiddenInput($name, $value) {
    $this->nodes []= new HiddenInput($name, $value);
  }

  public function addSubmitButton($label) {
    $this->nodes []= '<div class="submit">' . "<input type=\"submit\" value=\"$label\" />\n" .
      '</div>';
  }

  public function addSubmitButtons(Array $namesAndLabels) {
    $inputsHtml = "";
    foreach ($namesAndLabels as $name => $label) {
      $inputsHtml .= "<input type=\"submit\" name=\"$name\" value=\"$label\" />\n";
    }
    $this->nodes []= '<div class="submit">' . $inputsHtml . '</div>';
  }

  public function addJavaScript($code) {
    $this->nodes []= "<script type=\"text/javascript\">\n" . $code . "\n</script>\n";
  }

  public function setOptionalFields(Array $fieldNames) {
    foreach ($fieldNames as $name) {
      $f = $this->findFieldByName($name);
      $f->optional = true;
    }
  }

  public function setValue($field, $value) {
    $f = $this->findFieldByName($field);
    $f->setValue($value);
  }

  public function setDefaultValues($values) {
    foreach ($values as $fieldName => $value) {
      $f = $this->findFieldByName($fieldName);
      $f->setValue($value);
    }
  }

  public function render() {
    return $this->renderNodes();
  }

  public function renderNodes() {
    $html = '';
    foreach ($this->nodes as $n) {
      if (is_object($n)) {
        $html .= $n->render($this);
      } else if (is_string($n)) {
        $html .= $n;
      } else {
        throw new Exception("Expected node to be render-able object or string, " .
                            "but got type " . gettype($n));
      } 
    }
    return $html;
  }

  public function validate(Array $submittedValues) {
    $this->errors = array();
    foreach ($this->nodes as $n) {
      if (!is_string($n)) {
        if (!is_object($n)) {
          throw new Exception("Unexpected non-string, non-object node in form");
        }
        $this->errors = array_merge($this->errors, $n->validate($submittedValues));
      }
    }
    $this->validated = true;
    return $this->errors;
  }

  public function hasBeenValidated() { return $this->validated; }

  public function isValid() {
    $this->assertValidated();
    return count($this->errors) == 0;
  }

  public function getErrors() {
    return $this->errors;
  }

  public function getValue($name) {
    $this->assertValidated();
    $f = $this->findFieldByName($name);
    return $f->getValue();
  }

  public function getBoolValue($name) {
    return readBoolFromStr($this->getValue($name));
  }

  public function addError($field, $msg) {
    $this->errors []= $msg;
  }
  
  public function hasErrors () {
    return count($this->errors) > 0;
  }

  public function addNotice($notice) {
    $this->notices []= $notice;
  }

  public function hasNotices() {
    return count($this->notices) > 0;
  }

  public function renderFieldWithWrapper($name) {
    return $this->findFieldByName($name)->render($this);
  }

  public function renderInput($name) {
    return $this->findFieldByName($name)->renderInputHtml();
  }

  /**
   * @param string $name Name of field
   * @return Field
   * @throws Exception
   */
  public function findFieldByName($name) {
    foreach ($this->nodes as $n) {
      if ($n instanceof Field) {
        if ($n->name == $name) return $n;
      } else if (!is_string($n)) {
        if (!is_object($n)) {
          throw new Exception("Found non-object, non-string in form's input nodes array");
        }
        $f = $n->findFieldByName($name);
        if ($f) return $f;
      }
    }
    return null;
  }

  public function wrapField(Field $field) {
    $as = $field->containerClass() ? (' class="' . $field->containerClass() . '"') : '';
     return "<li" . $as . "><label>{$field->label}</label>" .
      $field->renderInputHtml() . "</li>";
  }

  private function assertValidated() {
    if (!$this->validated) {
      throw new Exception("Form has not yet been validated");
    }
  }
}

class Form extends BaseFormContainer {

  protected $id = null, $method, $header, $successMessage, $cssClass = 'new-form';

  function __construct($method) {
    if ($method != 'get' && $method != 'post') {
      throw new InvalidArgumentException("First parameter should be either 'get' or 'post'");
    }
    $this->method = strtolower($method);
    $this->action = $_SERVER['REQUEST_URI'];
  }

  public function setAction($action) {
    if (!$action) {
      $this->action = $_SERVER['REQUEST_URI'];
    } else {
      $this->action = $action;
    }
    return $this;
  }

  public function setCSSClass($cls) {
    $this->cssClass = $cls ? $cls : 'new-form';
    return $this;
  }

  public function setID($id) {
    $this->id = $id;
    return $this;
  }

  public function setHeader($msg) {
    $this->header = $msg;
  }

  public function setSuccessMessage($msg) {
    $this->successMessage = $msg;
  }

  public function renderErrorMessages() {
    $errors = '';
    if (count($this->errors) == 1) {
      $errors = '<div class="error">' . $this->errors[0] . "</div>\n";
    } else if (count($this->errors) > 1) {
      $errors = '<ul class="error">';
      foreach ($this->errors as $e) $errors .= "<li>$e</li>";
      $errors .= "</ul>\n";
    }
    return $errors;
  }

  public function renderNotices() {
    $notices = '';
    if (count ($this->notices) == 1) {
      $notices = '<div class="notice">' . $this->notices[0] . "</div>\n";
    } else if (count ($this->notices) > 1) {
      $notices = '<div class="notice"><ul>';
      foreach ($this->notices as $n) $notices .= "<li>$n</li>";
      $notices .= "</ul></div>\n";
    }
    return $notices;
  }

  public function render() {
    return 
      ($this->header ? "<h1>{$this->header}</h1>\n" : "") . 
      ($this->successMessage ? ('<div class="success">' . $this->successMessage . '</div>') : '') . 
      $this->renderErrorMessages () . 
      $this->renderNotices () . 
      $this->openingTag () . "\n" . 
      parent::render() . 
      "</form>\n";
  }

  public function findFieldByName($name) {
    $f = parent::findFieldByName($name);
    if ($f === null) throw new Exception("Could not find field with name '$name'");
    return $f;
  }

  public function openingTag() {
    return "<form " . ($this->id ? "id=\"{$this->id}\" " : "") .
      ($this->cssClass ? "class=\"{$this->cssClass}\" " : "") .
      "method=\"{$this->method}\" action=\"{$this->action}\">";
  }
}

class FormSection extends BaseFormContainer {

  private $id, $header = '';

  function __construct($id, $nodes) {
    $this->id = $id;
    foreach ($nodes as $n) {
      if (!is_string($n) && !($n instanceof Field))
        throw new InvalidArgumentException("All nodes must be of type Field or string");
    }
    $this->nodes = $nodes;
  }

  public function setHeader($markup) {
    $this->header = $markup;
  }

  public function render() {
    return "<fieldset id=\"{$this->id}\" class=\"form-section\">\n" .
      ($this->header ? ("<h2>" . $this->header . "</h2>\n") : "") .
      "\n<ol>\n" . parent::render() . "\n</ol></fieldset>\n";
  }
}

class RadioButtonSet extends Field {
  protected $options, $defaultValue;
  function __construct($name, $label, $options) {
    parent::__construct($name, $label);
    $this->options = $options;
  }
  public function setValue($v) {
    $this->defaultValue = $v;
    return $this;
  }
  public function containerClass() { return 'radio-button-set'; }
  public function renderInputHtml() {
    $html = '';
    foreach ($this->options as $value => $label) {
      $checked = $value == $this->defaultValue ? ' checked="checked"' : '';
      $html .= "<li>" .
        "<input type=\"radio\" value=\"$value\"" . $checked . $this->attrsHtml() . " />" .
        " $label</li>\n";
    }
    return "<ul style=\"list-style: none inside;\">$html</ul>";
  }
}
function newRadioButtonSet($name, $label, $options) {
  return new RadioButtonSet($name, $label, $options); }

class SelectField extends Field {

  private $options, $defaultValue;

  function __construct($name, $label, $options) {
    parent::__construct($name, $label);
    $this->options = $options;
  }

  public function setValue($v) {
    $this->defaultValue = $v;
    return $this;
  }

  public function renderInputHtml() {
    $optionsHtml = '';
    foreach ($this->options as $value => $label) {
      $selected = $value == $this->defaultValue ? ' selected="selected"' : '';
      $optionsHtml .= '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' .
        htmlspecialchars($label) . '</option>' . "\n";
    }
    return "<select" . $this->attrsHtml() . ">\n" . $optionsHtml . "</select>\n";
  }
}
function newSelectField($name, $label, $options) {
  return new SelectField($name, $label, $options); }

class BasicTextField extends Field {

  public function placeholder($txt) {
    $this->attributes['placeholder'] = $txt;
    return $this;
  }

  public function setValue($v) {
    $this->attributes['value'] = $v;
    return $this;
  }

  public function renderInputHtml() {
    $this->attributes['type'] = 'text';
    return "<input" . $this->attrsHtml() ." />";
  }
}
function newBasicTextField($name, $label) { return new BasicTextField($name, $label); }

class PasswordField extends BasicTextField {
  public function renderInputHtml() {
    $this->attributes['type'] = 'password';
    return "<input" . $this->attrsHtml() ." />";
  }
}
function newPasswordField($name, $label) { return new PasswordField($name, $label); }

class EmailAddressField extends BasicTextField {
  private $allowExtendedFormat = false;
  public function allowNameAndAddressFormat() {
    $this->allowExtendedFormat = true;
    return $this;
  }
  protected function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {
    if (!Validation\isValidEmailAddr($trimmedValue, $this->allowExtendedFormat)) {
      return array("The provided email address is not valid.");
    } else {
      $this->cleanedValue = $trimmedValue;
      return array();
    }
  }
  public function renderInputHtml() {
    $this->attributes['type'] = 'email';
    return "<input" . $this->attrsHtml() ." />";
  }
}
function newEmailAddressField($name, $label) { return new EmailAddressField($name, $label); }

class WebAddressField extends BasicTextField {
  public function validate(Array $submittedValues) {
    $errs = parent::validate($submittedValues);
    $v = $this->getTrimmedValue($submittedValues);
    if (count($errs) > 0) {
      return $errs;
    } else if ($v != '' && !Validation\isValidWebAddr($v)) {
      return array("Please provide a complete URL for the {$this->nameForValidation}, " .
                   "including the \"http://\" prefix.");
    } else {
      $this->cleanedValue = $v;
      return array();
    }
  }
}
function newWebAddressField($name, $label) { return new WebAddressField($name, $label); }

class DateTimeField extends BasicTextField {
  protected function formatString() { return '%Y-%m-%d %H:%M:%S'; }
  protected function errMsg() {
    return "Please provide a valid date and time for the {$this->nameForValidation}.";
  }
  protected function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {
    $timestamp = strtotime($trimmedValue);
    if ($timestamp == false) {
      return array($this->errMsg());
    } else {
      $this->cleanedValue = strftime($this->formatString(), $timestamp);
      return array();
    }
  }
}
function newDateTimeField($name, $label) { return new DateTimeField($name, $label); }

class DateField extends DateTimeField {
  function __construct($name, $label) {
    $this->setClass('date');
    parent::__construct($name, $label);
  }
  protected function formatString() { return '%Y-%m-%d'; }
  protected function errMsg() {
    return "Please provide a valid date for the {$this->nameForValidation}.";
  }
}
function newDateField($name, $label) { return new DateField($name, $label); }

class DollarAmountField extends BasicTextField {

  protected
    $minAmount = 0,    $minAmountErr,
    $maxAmount = null, $maxAmountErr;

  function __construct($name, $label) {
    $this->setClass('dollar-amount');
    parent::__construct($name, $label);
  }

  function minAmount($amount, $validationErr = null) {
    $this->minAmount = $amount;
    $this->minAmountErr = $validationErr;
    return $this;
  }

  function maxAmount($amount, $validationErr = null) {
    $this->maxAmount = $amount;
    $this->maxAmountErr = $validationErr;
    return $this;
  }

  protected function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {

    // TODO: Don't just blindly strip out commas...  We should actually check that the commas
    //       are in the right places, if they're present.  E.g., imagine someone entered the value
    //       "$1,00.00" -- should that be assumed to be $100?  Because the user probably intended
    //       it to be $1,000 ...
    $noCommas = str_replace(',', '', $trimmedValue);

    $amount = $trimmedValue[0] == '$' ? substr($noCommas, 1) : $noCommas;
    if (!is_numeric($amount)) {
      $err = "Please provide a valid dollar amount" .
        (empty($this->nameForValidation) ? "." : " for the {$this->nameForValidation}.");
      return array($err);
    } else if ($this->minAmount !== null && floatval($amount) < $this->minAmount) {
      return array($this->minAmountErr ? $this->minAmountErr :
        "Please specify an amount of at least {$this->minAmount}.");
    } else if ($this->maxAmount !== null && floatval($amount) > $this->maxAmount) {
      return array($this->maxAmountErr ? $this->maxAmountErr :
        "Sorry, the maximum allowed amount is {$this->maxAmount}.");
    } else {
      $this->cleanedValue = (float) $amount;
      return array();
    }
  }
}
function newDollarAmountField($name, $label) { return new DollarAmountField($name, $label); }

class TextArea extends Field {
  protected $value = '';

  public function placeholder($txt) {
    $this->attributes['placeholder'] = $txt;
    return $this;
  }

  public function setValue($v) { $this->value = $v; return $this; }

  public function renderInputHtml() {
    return '<textarea' . $this->attrsHtml() . '>' . htmlspecialchars($this->value) . '</textarea>';
  }

  public function containerClass() {
    return 'textarea';
  }
}
function newTextArea($name, $label) { return new TextArea($name, $label); }

class CheckboxField extends Field {
  function __construct($name, $label, $checked) {
    parent::__construct($name, $label);
    $this->attributes['type'] = 'checkbox';
    $this->setChecked($checked);
    $this->optional = true;
  }
  public function setValue($v) { $this->setChecked(!empty($v)); return $this; }
  public function getValue() { return at($this->attributes, 'checked') != null; }
  # XXX: This Field instance should not hard-code "<li>" as the field container type,
  #      but it was a necessary hack due to the fact that this CheckboxField renders its
  #      <input/> tag logically *before* its <label>.
  public function render(BaseFormContainer $f) {
    return '<li class="checkbox">' . $this->renderInputHtml() .
      '<label>' . $this->label . '</label></li>';
  }
  //public function containerClass() { return 'checkbox'; }
  public function renderInputHtml() { return "<input" . $this->attrsHtml() ." />"; }
  private function setChecked($checked) {
    if ($checked) {
      $this->attributes['checked'] = 'checked';
    } else {
      unset($this->attributes['checked']);
    }
  }
}
function newCheckboxField($name, $label, $checked) {
  return new CheckboxField($name, $label, $checked); }

class MultiCheckboxField extends Field {
  protected $options, $defaultValue, $checked;
  function __construct($name, $label, $options) {
    parent::__construct($name, $label);
    $this->options = $options;
    $this->checked = array();
  }
  public function setValue($v) { $this->checked = $v; }
  public function containerClass() { return 'checkbox-set'; }
  public function renderInputHtml() {
    $html = '';
    foreach ($this->options as $value => $label) {
      $checked = in_array($value, $this->checked) ? ' checked="checked"' : '';
      $html .= "<li>" .
        "<input type=\"checkbox\" name=\"{$this->name}[$value]\" value=\"t\"" .
        $checked . " />" . " $label</li>\n";
    }
    return "<ul style=\"list-style: none inside;\">$html</ul>";
  }
  public function validate(Array $submittedValues) {
    $this->checked = array();
    foreach ($this->options as $v => $_) {
      if (!empty($submittedValues[$this->name][$v])) $this->checked []= $v;
    }
    return array();
  }
  public function getValue() {
    return $this->checked;
  }
}
function newMultiCheckboxField($name, $label, $options) {
  return new MultiCheckboxField($name, $label, $options); }

class HiddenInput extends Field {
  private $value;
  function __construct($name, $value) {
    $this->attributes['type'] = "hidden";
    $this->name = $name;
    $this->attributes['value'] = $value;
  }
  public function setValue($v) { $this->value = $v; return $this; }
  public function renderInputHtml() {
    return "<input" . $this->attrsHtml() ." />";
  }
  public function render(BaseFormContainer $f) {
    return $this->renderInputHtml();
  }
  public function validate(Array $submittedValues) {
    $this->cleanedValue = $this->getTrimmedValue($submittedValues);
    return array();
  }
}
function newHiddenInput($name, $value) { return new HiddenInput($name, $value); }

abstract class Field {

  abstract public function setValue($v);
  abstract public function renderInputHtml();

  public $name, $label, $optional, $cleanedValue;
  protected $attributes = array(), $shouldMatch = array();
  protected $nameForValidation = null;
  protected $requiredErr = null;
  protected $minLength = null, $minLengthErr, $maxLength = null, $maxLengthErr;
  private $customValidations = array();

  function __construct($name, $label) {
    $this->name = $name;
    $this->label = $label;
  }

  public function setAttribute($attr, $value) {
    $this->attributes[$attr] = $value;
    return $this;
  }

  public function render(BaseFormContainer $f) {
    return $f->wrapField($this);
  }

  protected function attrsHtml() {
    $html = " name=\"{$this->name}\"";
    foreach ($this->attributes as $n => $v) {
      $html .= ' ' . $n . '="' . htmlspecialchars($v) . '"';
    }
    return $html;
  }

  public function containerClass() {
    return null;
  }

  public function setID($id) {
    $this->attributes['id'] = $id;
    return $this;
  }

  public function setClass($cls) {
    $this->attributes['class'] = $cls;
    return $this;
  }

  public function nameForValidation($n) {
    $this->nameForValidation = $n;
    return $this;
  }

  public function required($err) {
    $this->optional = false;
    $this->requiredErr = $err;
    return $this;
  }
  
  public function shouldMatch($otherFieldName, $err) {
    $this->shouldMatch[$otherFieldName] = $err;
    return $this;
  }

  public function minLength($len, $err) {
    $this->minLength = $len;
    $this->minLengthErr = $err;
    return $this;
  }

  public function maxLength($len, $err) {
    $this->maxLength = $len;
    $this->maxLengthErr = $err;
    return $this;
  }

  public function validate(Array $submittedValues) {
    $v = $this->getTrimmedValue($submittedValues);
    $this->setValue($v);
    if ($v == '' && $this->optional) {
      return array();
    } else if ($v == '' && !$this->optional) {
      $err = null;
      if ($this->requiredErr) {
        $err = $this->requiredErr;
      } else if ($this->nameForValidation) {
        $err = "Please provide the {$this->nameForValidation}.";
      } else {
        $err = "Please provide a value for the field <em>{$this->label}</em>.";
      }
      return array($err);
    }
    if ($v != '') {
      if ($this->minLength !== null && strlen($v) < $this->minLength) {
        return array($this->minLengthErr);
      } else if ($this->maxLength !== null && strlen($v) > $this->maxLength) {
        return array($this->maxLengthErr);
      }
    }
    foreach ($this->shouldMatch as $f => $err) {
      if ($v != trim($submittedValues[$f])) return array($err);
    }
    $this->cleanedValue = $v;
    $errs = $this->validateWhenNotEmpty($submittedValues, $v);
    if (count($errs) == 0) {
      foreach ($this->customValidations as $validateFunc) {
        $numParams = Reflection\numberOfParameters($validateFunc);
        if ($numParams != 1 && $numParams != 2) {
          throw new InvalidArgumentException('Validation function must accept either ' .
                                             'one or two parameters');
        }
        $newErrs = $numParams == 1 ? $validateFunc($v) : $validateFunc($this, $v);
        if (!is_array($newErrs)) {
          throw new InvalidArgumentException('Validation function must return an array ' .
            'containing validation errors, or an empty array if validation was successful');
        }
        $errs = array_merge($errs, $newErrs);
      }
    }
    return $errs;
  }

  public function addValidation($validateFunc) {
    $this->customValidations []= $validateFunc;
    return $this;
  }

  public function getValue() {
    return $this->cleanedValue;
  }

  # This method can be used by sub-classes to do validation that is only relevant when a
  # non-empty/non-whitespace value has been provided.
  protected function validateWhenNotEmpty(Array $submittedValues, $trimmedValue) {
    return array();
  }

  protected function getTrimmedValue(Array $submittedValues) {
    return trim(at($submittedValues, $this->name, ''));
  }
}
