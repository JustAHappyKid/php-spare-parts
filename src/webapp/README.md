
# PHP Spare Parts Lightweight Web "Framework" #

Here we provide a very lightweight "framework" for web applications. However, unlike most
PHP-based "frameworks", we recognize the fact that PHP is, itself, a sort of web
framework -- it provides, out of the box, the majority of things one needs to handle web
requests, send back HTTP responses, and more.

Here we provide a very skimp guide to getting started.

(**TODO:** Improve this documentation.)

How you structure your code-base is largerly up to you, but you need at least two directories: one named `actions` which contains the PHP files (controllers and/or thinner, simpler "action" files) that handle incoming HTTP requests; and another directory to serve as the "document root", but can be named whatever you like (e.g., `doc-root` would do).

Sub-class the `FrontController`, creating an implementation specific to your website.
The "FrontController" is the component through which all requests will be routed.
As an example:

```
class FrontControllerForMyWebsite extends \SpareParts\Webapp\FrontController {
  /* ... */
}
```

There are a number of methods of `FrontController` that you may choose to override:

  * `filters` - Should return an array of objects implementing the `\SpareParts\Webapp\Filter`
    interface.
  * `info`, `notice`, `warn` - If you want relevant log messages to be logged some where,
    override these methods to hook into your own logging infrastructure (or even into the
    one provided by Spare Parts).
  * `getUserForCurrentRequest` - If you want to implement an authentication/login system, this
    should return an object representing the current user of your website (if the current user
    is authenticated).
  * **TODO:** Describe other methods...

Now create a PHP file, let's say `app.php`, through which all web requests (for non-static content)
will be served, with the following content:

```
$siteDir = /* Your web-application's base directory! */;
$fc = new FrontController($siteDir);
$fc->go();
```

So, if your webapp were at `~/apps/my-app/`, including the aforementioned `actions` directory
living there, you could initialize `$siteDir` as follows:

```
$siteDir = "~/apps/my-app/";
```

(We recommend constructing the path dynamically, using a combination of `dirname` calls, however.)

Now for the `.htaccess` file:

```
# Route all requests through app.php, unless there's a corresponding static file that should
# be served directly by Apache.
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ /app.php/$1 [L,QSA]

# To allow us to route all requests *through* app.php -- for example, /app.php/requested/path
# will get routed to /app.php.
AcceptPathInfo On
```

As an example of the simplest possible "action", put a file named `hi.php` in your `actions`
directory, with the following content:

```
<?php
return function() {
  return "hello, world!";
};
```

Now if you access the path `/hi` you should see a simple text response saying ... "hello, world!"

In other words, if you configured Apache to serve your site from `mysite.com`, visiting
`http://mysite.com/hi` should lead to that action being triggered.
