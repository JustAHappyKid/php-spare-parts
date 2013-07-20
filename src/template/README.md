# Diet PHP (Templating Tool) #

PHP, having effectively begun life as a templating engine and since grown
into a full-fledged programming language, lies in a peculiar position.
Some still choose to use PHP for templating purposes, but this can prove
to be clunky. Scaling up to large websites, where some sort of
"inheritance" is supported, is especially unsavory.

Others have opted to create entirely new languages for templating
purposes (e.g., [Smarty](http://www.smarty.net/)), but this not only
requires one to learn a whole-new language... It often makes things that
would be _easy_ in PHP _difficult_, tedious, or impossible.

**Diet PHP** (name not yet 100% settled) aims to _"tweak"_ the base PHP
language just enough to address the things that make it unideal for
purposes of templating. It's effectively a small pre-processor layer
atop PHP; a Diet PHP file is ultimately converted to 100% PHP-compatible
code before being rendered (as HTML, plain-text, or whatever it is you
want to generate).

## Diet PHP provides the following enhancements to standard PHP: ##

  * [Shorthand syntax for control structures](#shorthand-control-structure-syntax)
    (`if`-statements, `for`-loops, etc.)
    to avoid having hard-to-read angle-bracket-question-mark sequences all over tarnation.

  * Shorthand variable substitution syntax -- forget the angle brackets and just
    assume any "word" that begins with `$` is a variable!

  * A more natural and elegant mechanism for defining hierarchies of templates.

  * Automatic escaping of variables to HTML, or whatever the underlying document type
    requires. (COMING SOON)

  * Preventing of "dangerous" function-calls by limiting what functions may be used.
    The list of allowable functions is customizable. (COMING SOON)

Aside from those additions, it's _just PHP_. You don't have to learn a new syntax or
remember another wad of function names. Anything that's valid PHP flies (except
intentionally-disallowed functions, of course), and you can choose to use short-hand
syntax when you want, where you want; fallback to standard PHP whenever you want.

## Examples ##

### Shorthand Control Structure Syntax ###

Instead of:

    <?php if ($error == true) { ?>
      <p>Something went wrong dude.</p>
    <?php } ?>

Just write:

    ? if ($error == true) {
      <p>Something went wrong dude.</p>
    }

Should be obvious what's going on there, even to someone that doesn't know what Diet
PHP is!

### Shorthand Variable Syntax ###

Instead of...

    <p>You are logged in as <?= $username ?>.</p>

Just try...

    <p>You are logged in as $username.</p>

### TODO: Add examples for inheritance ###
