<?php

namespace SpareParts\Template;

use \Exception;

class TemplateException extends Exception {}
class SecurityException extends TemplateException {}
class NoSuchTemplate    extends TemplateException {}
class ParseError        extends TemplateException {}
