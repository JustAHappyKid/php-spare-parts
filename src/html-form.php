<?php

namespace MyPHPLibs\WebBrowsing;

require_once dirname(__FILE__) . '/html-parsing.php'; # htmlSoupToDOMDocument
use \Exception;

class HtmlForm {

  protected static function supportedInputTypes() {
    return array('text', 'password', 'email', 'search', 'checkbox', 'radio', 'submit',
                 'image', 'reset', 'button', 'hidden'); }

  public $name, $id, $action, $method, $enctype, $fields, $namelessFields;
  public $xpathObj;

  function __construct($formHtml = null) {
    $this->fields = array();
    $this->namelessFields = array();
    if ($formHtml !== null) $this->parse($formHtml);
  }

  public static function fromDOMNode($formNode, $xpathObj) {
    $form = new HtmlForm;
    $form->xpathObj = $xpathObj;
    $form->populateFromDOMNode($formNode);
    return $form;
  }

  public function parse($formHtml) {
    $dom = htmlSoupToDOMDocument($formHtml);
    $this->xpathObj = new \DOMXPath($dom);
    $formNode = null;
    $forms = $this->search('//form');
    if (count($forms) == 0) {
      throw new Exception('Could not find a form node in given HTML');
    } else if (count($forms) > 1) {
      throw new Exception('Multiple form nodes found in given HTML');
    } else {
      $formNode = current($forms);
    }
    $this->populateFromDOMNode($formNode);
  }

  public function populateFromDOMNode($formNode) {
    foreach (array('name', 'id', 'class', 'action', 'enctype') as $attr) {
      $this->$attr = $formNode->hasAttribute($attr) ? $formNode->getAttribute($attr) : null;
    }
    $this->method = strtolower($formNode->getAttribute('method'));

    foreach ($this->search('.//input', $formNode) as $input) {
      $n = $this->getAttribute($input, 'name');
      //if ($n == '') continue;
      $v = $this->getAttribute($input, 'value');
      $typeAttr = $this->getAttribute($input, 'type');
      $type = ($typeAttr == null || $typeAttr == 'input') ? 'text' :
        preg_replace('/[^a-z]/', '', strtolower($this->getAttribute($input, 'type')));
      if (!in_array($type, self::supportedInputTypes())) {
        warn("Found input field with unrecognized type, '$type'");
      }
      if ($type == 'radio') {
        $value = $v === null ? "on" : $v;  # Default value of "on" is used by web browsers
        if (empty($this->fields[$n])) {
          $field = $this->addField($n, new HtmlRadioButtonField());
        }
        $field->options[$value] = $value;
        $subField = new HtmlFormField(null, $value, $input);
        $field->optionLabels[$value] = $this->getLabel(null, $subField);
        $field->textAfter[$value] = $input->nextSibling ?
          trim($this->normalizeSpace($input->nextSibling->textContent)) : null;
        if ($this->getAttribute($input, 'checked')) {
          $field->value = $value;
        }
        $field->xmlNode = $input;
      } else if ($type == 'checkbox') {
        if ($v === null) $v = 'on'; // Default value of "on" is used by web browsers
        $isChecked = $this->getAttribute($input, 'checked') ? true : false;
        $this->addField($n, new HtmlCheckboxField($isChecked, $v, $input));
      } else if ($type == 'submit') {
        $field = $this->addField($n, new HtmlButtonField('submit', $v, $input));
        $field->buttonText = $v;
      } else if ($type == 'image') {
        $field = $this->addField($n, new HtmlButtonField('image', $v, $input));
        $field->buttonText = null;
      } else if ($type == 'button') {
        // XXX: How should <input type="button" ... /> fields be dealt with ???
      } else {
        $this->addField($n, new HtmlTextField($type, $v === null ? '' : $v, $input));
      }
    }

    foreach ($this->search('.//select', $formNode) as $select) {
      $name = $select->getAttribute('name');
      if ($name == '') continue;
      $options = array();
      $defaultValue = null;
      foreach ($this->search('.//option', $select) as $option) {
        $innerContent = trim($this->normalizeSpace($option->textContent));
        $thisOptionValue = $this->getAttribute($option, 'value');
        if ($thisOptionValue === null) { $thisOptionValue = $innerContent; }
        if ($option->getAttribute('selected')) {
          $defaultValue = $thisOptionValue;
        } else if ($defaultValue === null) {
          $defaultValue = $thisOptionValue;
        }
        $options[$thisOptionValue] = $innerContent;
      }
      //if ($defaultValue === null) { $defaultValue = keys($options)[0]; }
      $this->addField($name, new HtmlSelectField($options, $defaultValue, $select));
      //$this->fields[$name]->xmlNode = $select;
    }

    foreach ($this->search('.//textarea', $formNode) as $textarea) {
      $name = $textarea->getAttribute('name');
      if ($name == '') continue;
      $this->addField($name, new HtmlFormField('textarea', $textarea->textContent, $textarea));
      //$this->fields[$name]->xmlNode = $textarea;
    }

    foreach ($this->search('.//button', $formNode) as $button) {
      $n = $this->getAttribute($button, 'name');
      //if ($n == '') continue;
      $v = $this->getAttribute($button, 'value');
      $t = $this->getAttribute($button, 'type');
      $type = $t ? $t : 'submit';
      $f = new HtmlButtonField($type, $v, $button);
      $f->buttonText = $button->textContent;
      $this->addField($n, $f);
    }

    foreach ($this->fields as $n => $f) {
      $this->fields[$n]->label = $this->getLabel($n, $f);
    }
  }

  public function getDefaultValuesToSubmit($submitButton = null) {
    $fieldTypesToSubmit = array('text', 'password', 'textarea', 'checkbox', 'radio', 'select',
                                'hidden');
    $values = array();
    $submitButtons = array();
    foreach ($this->fields as $name => $field) {
      if (in_array($field->type, $fieldTypesToSubmit)) $values[$name] = $field->value;
      if ($field->isSubmitButton()) {
        $submitButtons[$name] = $field;
        if ($submitButton != null && $name == $submitButton) {
          $values = array_merge($values, $field->valuesToSubmit($name));
        }
      }
    }
    if ($submitButton &&
        (empty($this->fields[$submitButton]) || !$this->fields[$submitButton]->isSubmitButton())) {
      throw new Exception("Form has no submit button named '$submitButton'");
    }
    if (count($submitButtons) > 1 && $submitButton == null) {
      throw new Exception("Multiple submit buttons found on form, but no submit button " .
                          "specified for form submission");
    }
    if ($submitButton == null && count($submitButtons) == 1) {
      $btnName = key($submitButtons);
      if (!empty($btnName)) {
        $values = array_merge($values, current($submitButtons)->valuesToSubmit($btnName));
      }
    }
    return $values;
  }

  public function getButtons() {
    $buttons = array();
    foreach ($this->fields as $n => $f) {
      if ($f instanceof HtmlButtonField) $buttons []= $f;
    }
    foreach ($this->namelessFields as $n => $f) {
      if ($f instanceof HtmlButtonField) $buttons []= $f;
    }
    return $buttons;
  }

  public function hasSubmitButton() {
    foreach ($this->getButtons() as $b) {
      if ($b->isSubmitButton()) return true;
    }
    return false;
  }

  public function guessFieldLabel($field) {
    if ($field->labelWasGuessed) return $field->guessedLabel;

    $textContent = '';
    $node = $field->xmlNode->parentNode;

    if (empty($node)) {
      if (isset($field->xmlNode->tagName)) {
        warn("Element with tag name '{$field->xmlNode->tagName}' has no parent node!");
      } else {
        warn("Node has no parent node!");
      }
      return null;
    }

    # We must remove the input-field node, itself, in case it is a <textarea> or <select>
    # input and it actually contains some text in itself.
    $node->removeChild($field->xmlNode);

    while ($textContent == '' && $node != null) {
      if ($node) $textContent = trim($node->textContent);
      if ($field instanceof HtmlRadioButtonField) {
        foreach ($field->textAfter as $t) $textContent = trim(str_replace($t, '', $textContent));
      }
      $node = $node->parentNode; //current($this->search('..', $node));
    }

    $field->guessedLabel = $textContent == '' ? null : $this->normalizeSpace($textContent);
    $field->labelWasGuessed = true;
    return $field->guessedLabel;
  }

  private function addField($name, $field) {
    if (empty($name)) {
      $this->namelessFields []= $field;
    } else {
      if (isset($this->fields[$name])) warn("Form has multiple fields with name '$name'");
      //if ($name == '') throw new Exception("Internal error: \$name is empty string");
      $this->fields[$name] = $field;
    }
    return $field;
  }

  private function search($xpath, $contextNode = null) {
    $xp = $this->xpathObj;
    $r = $contextNode ? $xp->query($xpath, $contextNode) : $xp->query($xpath);
    $nodes = array();
    for ($i = 0; $i < $r->length; ++$i) {
      $nodes[] = $r->item($i);
    }
    return $nodes;
  }

  private function getAttribute($node, $attrName) {
    foreach ($node->attributes as $a) {
      if ($a->name == $attrName) return $a->value;
    }
    return null;
  }

  private function getLabel($fieldName, $field) {
    $labels = $this->search("//label[@for='{$field->id}']");
    if (count($labels) == 0 && isset($fieldName)) {
      $labels = $this->search("//label[@for='$fieldName']");
    }
    # If we haven't found a label yet, try to locate one by its proximity to the input...
    $node = $field->xmlNode->parentNode;
    while (count($labels) == 0 && $node != null) {
      $labels = $this->search(".//label[not(@for)]", $node);
      $node = $node->parentNode;
    }
    return (count($labels) == 1) ? $this->normalizeSpace(current($labels)->textContent) : null;
  }

  private function normalizeSpace($txt) {
    return preg_replace('/(\n|\s{2,}|\xC2\xA0)/', ' ', $txt);
  }
}

class HtmlFormField {
  public $id, $type, $value, $label, $xmlNode, $labelWasGuessed, $guessedLabel;
  function __construct($type, $value, $node) {
    $this->id = $node ? $node->getAttribute('id') : null;
    $this->type = $type; $this->value = $value; $this->xmlNode = $node; $this->label = null;
  }
  public function isSubmitButton() { return false; }
}

class HtmlTextField extends HtmlFormField {
  function __construct($type, $value, $node) {
    parent::__construct($type, $value, $node);
    $this->maxLength = $node && $node->hasAttribute('maxlength') ?
      $node->getAttribute('maxlength') : null;
  }
}

class HtmlSelectField extends HtmlFormField {
  public $options;
  function __construct($options, $value, $node) {
    parent::__construct('select', $value, $node);
    $this->options = $options;
  }
}

class HtmlCheckboxField extends HtmlFormField {
  public $valueWhenChecked;
  function __construct($checked, $v, $node) {
    $this->valueWhenChecked = $v;
    return parent::__construct('checkbox', $checked ? $v : null, $node);
  }
}

class HtmlRadioButtonField extends HtmlFormField {
  public $options, $optionLabels;
  function __construct() {
    parent::__construct('radio', null, null);
    $this->options = array();
  }
}

class HtmlButtonField extends HtmlFormField {
  public $buttonText;
  public function isSubmitButton() {
    return in_array($this->type, array('submit', 'image'));
  }
  public function valuesToSubmit($name) {
    if ($this->type == 'image') {
      return array("$name.x" => '0', "$name.y" => '0');
    } else {
      return array($name => $this->value);
    }
  }
}