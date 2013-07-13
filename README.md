# Spare Parts for PHP #

`php-spare-parts` is a set of PHP classes, functions, and general tools which
you may or may not find useful. It begins with very basic functionality that
probably should have been included in the base PHP libraries (for example,
better functions for URL parsing/manipulation), but in some cases goes so far
as to provide mini "frameworks" (such as a minimalistic testing framework).

Note though, special care is given to keep the components loosely-coupled.
This way, one can *pick and choose* pieces he wishes to use, as desired.

## Some of the notable components: ##

  * An HttpClient class, for *'browsing'* the Web when more sophistication is
    needed than a simple `fopen` (or `curl` call, or the like) on a URL.

  * Classes/functions for automating web-forms.

  * Common webapp tools / stuff you might use to build your own "framework":

    * A very light-weight, optional (optional!) "MVC-like" framework that
      takes a unique approach by (a) avoiding reinventing the wheel where possible
      by acknowledging the fact that PHP, itself, is essentially a web framework;
      and (b) making use of the file-system's directory structure for routing (which
      makes it very easy for a developer to determine which class/file an HTTP
      request routes to).

    * Form-definition API to define and/or render and/or validate HTML forms,
      allowing for custom input types and custom rendering via sub-classing.

  * A light-weight, customizable testing framework, with test-runner that can
    auto-discover "test functions" and "test classes".

  * Tools for parsing and manipulating URLs (e.g., a thin layer atop PHP's built-in
    `parse_url`).

  * CSV export functions.

  * File-path utility functions: `pathJoin`, `normalizePath`

  * UTF-8 utility functions, for determining if a string is properly encoded in
    UTF-8 and/or purging non-UTF-8 characters.

  * Several other little bits, some not worth mentioning here and some not
    mentioned due to laziness at the present time. :o)
