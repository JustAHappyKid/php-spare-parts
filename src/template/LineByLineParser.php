<?php

namespace SpareParts\Template;

class LineByLineParser {

  public $lines, $currentLine;

  public function __construct($input) {
    $this->lines = explode("\n", $input);
    $this->currentLine = 0;
  }

  public function takeLine() {
    if ($this->currentLine >= count($this->lines)) throw new Exception("No more lines");
    $this->currentLine += 1;
    return $this->lines[$this->currentLine - 1];
  }

  public function moreLinesLeft() {
    return $this->currentLine < count($this->lines);
  }

  public function lineNum() { return $this->currentLine + 1; }

/*
  public function match($x) {
    if ($this->lookahead->type == $x ) {
      $this->consume();
    } else {
      throw new Exception("Expecting token " .
                          $this->input->getTokenName($x) .
                          ":Found " . $this->lookahead);
    }
  }

  public function consume() {
    $this->lookahead = $this->input->nextToken();
  }
*/

}
