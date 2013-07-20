# Diet PHP (Templating Tool) #

PHP, having effectively begun life as templating engine and since grown
into a full-fledged programming language, lies in a peculiar position.
Some still choose to use PHP for templating purposes, but this can prove
to be clunky, except in the simplest cases of templating. 

Others have opted to create entirely new languages for templating
purposes (e.g., [Smarty](http://www.smarty.net/)), but this not only
requires one to learn a whole-new language... It often makes things that
would be _easy_ in PHP difficult, tedious, or impossible.

*Diet PHP* (name not yet 100% settled) aims to _"tweak"_ the base PHP
language just enough to address the things that make it unideal for
purposes of templating. It's effectively a small pre-processor layer
atop PHP; a Diet PHP file is ultimately converted to 100% PHP-compatible
code before being rendered.

## Diet PHP makes the following enhancements to standard PHP: ##

  * Provides short-hand syntax for control structures (`if`-statements, `for`-loops, etc.)
    to avoid having hard-to-read angle-bracket-question-mark sequences all over tarnation.

  * Provides short-hand variable substitution syntax -- forget the angle brackets and just
    assume any "word" that begins with `$` is a variable!

  * Allows for defining xxx

  * Automatically escapes variables to HTML, or whatever the underlying document type
    requires. (COMING SOON)

  * Prevents "dangerous" function-calls by limiting what functions may be used. The list
    of allowable functions is customizable. (COMING SOON)

Aside from those additions, it's _just PHP_. You don't have to learn a new syntax or
remember another wad of function names. Anything that's valid PHP flies (except
intentionally-suppressed functions), and you can choose to use short-hand syntax when
you want, where you want.

## TODO: Give examples... ##

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

Instead of...

    <p>You are logged in as <?= $username ?>.</p>

Just try...

    <p>You are logged in as $username.</p>
