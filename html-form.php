<?php

class HtmlForm {

  public $name, $id, $action, $method, $enctype, $fields;
  //private $fields;

  function __construct($formHtml) {
    $this->fields = array();
    $this->parse($formHtml);
  }

  /*function getField($name) {
    echo "this->fields: "; print_r($this->fields);
    return $this->fields[$name];
  }*/

  function parse($formHtml) {
    $openingTag = $this->getFirstTag($formHtml);
    foreach (array('name', 'id', 'class', 'action', 'enctype') as $attr) {
      $this->$attr = $this->getAttribute($openingTag, $attr);
    }
    $this->method = strtolower($this->getAttribute($openingTag, 'method'));

    # <input type="hidden" />
    # XXX: Should we only use "iU" for the regex modifiers when searching for "hidden" fields???
    #      There was originally a comment that said something like...
    #        "Use 'iU', not 'iUs', as the 's' (PCRE_DOTALL) screws up
    #         __EVENTVALIDATION and other hidden searches"
    #   -- Chris, 2009-06-10

    # Do all <input ... /> tags, aside radio buttons...
    $textStyleInputTags = array();
    $checkboxTags = array();
    $radioButtonTags = array();
    $inputMatches = array();
    preg_match_all("/<input.*>/isU", $formHtml, $inputMatches);
    foreach ($inputMatches[0] as $inputHtml) {
      # XXX: We'll treat 'submit' input types like text input types for now, as some
      #      websites expect the value of the submit button to be sent with the POST
      #      request.  The proper way to deal with this would be to have some kind of
      #      interface for "clicking" the button, perhaps...
      $textLikeInputTypes = array('text', 'password', 'hidden', 'submit');
      $type = strtolower($this->getAttribute($inputHtml, 'type'));
      if ($type == null || in_array($type, $textLikeInputTypes)) {
        $textStyleInputTags[] = $inputHtml;
      } else if ($type == 'checkbox') {
        $checkboxTags[] = $inputHtml;
      } else if ($type == 'radio') {
        $radioButtonTags[] = $inputHtml;
      }
    }

    foreach ($textStyleInputTags as $inputHtml) {
      $type = strtolower($this->getAttribute($inputHtml, 'type'));
      if ($type == null || $type == '') { $type = 'text'; }
      $value = $this->getValue($inputHtml);
      $this->fields[$this->getName($inputHtml)] = new HtmlFormField($type, $value);
    }

    foreach ($checkboxTags as $inputHtml) {
      $value = preg_match("/checked/i", $inputHtml) ? 'on' : null;
      $this->fields[$this->getName($inputHtml)] = new HtmlFormField('checkbox', $value);
    }

    foreach ($radioButtonTags as $inputHtml) {
      $v = $this->getValue($inputHtml);
      $value = $v === null ? "on" : $v;  # Default value of "on" is used by web browsers
      $name = $this->getName($inputHtml);
      if (empty($this->fields[$name])) {
        $this->fields[$name] = new HtmlRadioButtonField();
      }
      $this->fields[$name]->options[$value] = $value;
      if (preg_match("/checked/i", $inputHtml)) {
        $this->fields[$name]->value = $value;
      }
    }

    # Do <textarea> tags...
    if (preg_match_all("/<textarea.*>.*<\/textarea>/isU", $formHtml, $textareas)) {
      foreach ($textareas[0] as $textarea) {
        preg_match("/<textarea.*>(.*)<\/textarea>/isU", $textarea, $valueMatch);
        $this->fields[$this->getName($textarea)] =
          new HtmlFormField('textarea', $valueMatch[1]);
      }
    }

/*
   XXX: We'll come back and add support for button types when needed...
        -- Chris, 2009-06-10

    // <input type=submit entries
    if ( preg_match_all("/<input.*type=[\"']?submit[\"']?.*>/iU", $formHtml, $submits) ) {
      foreach ( $submits[0] as $submit ) {
        $this->_return[$this->_counter]['buttons'][] = array(
          'type'  => 'submit', 'name'  => $this->_getName($submit),
          'value'  => $this->_getValue($submit));
      }
    }

    // <input type=button entries
    if ( preg_match_all("/<input.*type=[\"']?button[\"']?.*>/iU", $formHtml, $buttons) ) {
      foreach ( $buttons[0] as $button ) {
        $this->_return[$this->_counter]['buttons'][] = array(
          'type'  => 'button', 'name'  => $this->_getName($button),
          'value'  => $this->_getValue($button));
      }
    }

    // <input type=reset entries
    if ( preg_match_all("/<input.*type=[\"']?reset[\"']?.*>/iU", $formHtml, $resets) ) {
      foreach ( $resets[0] as $reset ) {
        $this->_return[$this->_counter]['buttons'][] = array(
          'type'  => 'reset', 'name'  => $this->_getName($reset),
          'value'  => $this->_getValue($reset));
      }
    }

    // <input type=image entries
    if ( preg_match_all("/<input.*type=[\"']?image[\"']?.*>/iU", $formHtml, $images) ) {
      foreach ( $images[0] as $image ) {
        $this->_return[$this->_counter]['buttons'][] = array(
          'type'  => 'image', 'name'  => $this->_getName($image),
          'value'  => $this->_getValue($image));
      }
    }
*/

    # <input type=select entries
    # XXX: Original comment...
    #   Here I have to go on step around to grep at first all select names and then
    #   the content. Seems not to work in an other way
    $selectMatches = array();
    preg_match_all("/<select.*>.+<\/select>/isU", $formHtml, $selectMatches);
    foreach ($selectMatches[0] as $selectHtml) {
      $name = $this->getName($this->getFirstTag($selectHtml));
      $options = array();
      $defaultValue = null;
      $optionTagsMatch = array();
      preg_match_all("/<option[^>]*>[^<]*/is", $selectHtml, $optionTagsMatch);
      foreach ($optionTagsMatch[0] as $optionHtml) {
        $selected = preg_match("/selected/i", $optionHtml) ? true : false;
        preg_match("/<option.*>(.*)/is", $optionHtml, $innerContentMatch);
        $innerContent = $innerContentMatch[1];
        $thisOptionValue = $this->getValue($optionHtml);
        if ($thisOptionValue === null) { $thisOptionValue = $innerContent; }
        if ($selected) {
          $defaultValue = $thisOptionValue;
        } else if ($defaultValue === null) {
          $defaultValue = $thisOptionValue;
        }
        $options[$thisOptionValue] = $innerContent;
      }
      //if ($defaultValue === null) { $defaultValue = keys($options)[0]; }
      $this->fields[$name] = new HtmlSelectField($options, $defaultValue);
    }
  }

  private function getFirstTag($html) {
    $m = array();
    # XXX: This match isn't fool-proof; there could be a '>' character within an
    #      attribute string.
    preg_match('/<[^>]*>/', $html, $m);
    return $m[0];
  }

  private function getName($tagHtml) {
    return $this->getAttribute($tagHtml, 'name');
  }

  private function getValue($tagHtml) {
    return $this->getAttribute($tagHtml, 'value');
  }

  private function getAttribute($tagHtml, $attr) {
    $attrPatters = array('"([^\"]*)"', "'([^']*)'", "([^>\s]*)");
    foreach ($attrPatters as $pattern) {
      $regex = '/' . $attr . '=\s*' . $pattern . '([^>]*)?>' . '/is';
      $match = array();
      if (preg_match($regex, $tagHtml, $match)) {
        return $match[1];
      }
    }
    return null;
  }
}

class HtmlFormField {
  public $type, $value;
  function __construct($type, $value) {
    $this->type = $type; $this->value = $value;
  }
}

class HtmlSelectField extends HtmlFormField {
  public $options;
  function __construct($options, $value) {
    parent::__construct('select', $value);
    $this->options = $options;
  }
}

class HtmlRadioButtonField extends HtmlFormField {
  public $options;
  function __construct() {
    parent::__construct('radio', null);
    $this->options = array();
  }
}