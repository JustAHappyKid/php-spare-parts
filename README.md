# Spare Parts for PHP #

`php-spare-parts` is a library (or maybe better described as a set of libraries) for PHP,
with components ranging from simple *functional-style* functions (such as `takeWhile`) that
should have probably been in PHP in the first place, to a very minimalistic testing framework
as well as a minimalistic web framework.

Special care is given though, to keep the components loosely-coupled, so one can
*pick and choose* pieces as desired.

## Some of the notable components: ##

  * An HttpClient class, for *'browsing'* the Web when more sophistication is
    needed than a simple `fopen` (or `curl` call, or the like) on a URL.

  * Classes/tools for automating web-forms.

  * Common webapp tools / stuff you might use to build your own "framework":

    * A very light-weight, optional (optional!) "MVC-like" framework, but it
      takes a unique approach by (a) avoiding reinventing the wheel where possible
      by acknowledging the fact that PHP, itself, is essentially a web framework;
      and (b) making use of the file-system's directory structure for routing (which
      makes it very easy for a developer to determin which class/file an HTTP
      request routes to).

    * Form-definition API to define and/or render and/or validate HTML forms,
      allowing for custom input types and custom rendering via sub-classing.

  * Tools for parsing and manipulating URLs (a thin layer atop PHP's built-in
    `parse_url` and et al).

  * UTF-8 utility functions, for determining if a string is properly encoded in
    UTF-8 and/or purging non-UTF-8 characters.

  * CSV export functions.

  * File-path utility functions: `pathJoin`, `normalizePath`

  * A light-weight, customizable testing framework, with test-runner that can
    auto-discover "test functions" and "test classes".

  * Several other little bits, some not worth mentioning here and some not
    mentioned due to laziness at the present time. :o)
