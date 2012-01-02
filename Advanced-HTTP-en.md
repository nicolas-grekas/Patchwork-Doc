=========================================
Advanced handling of HTTP requests in PHP
=========================================

Nicolas Grekas - nicolas.grekas, gmail.com  
17 June 2011 - Last updated on jan. 2, 2012

English version: https://github.com/nicolas-grekas/Patchwork-Doc/blob/master/Advanced-HTTP-en.md  
Version française : https://github.com/nicolas-grekas/Patchwork-Doc/blob/master/Advanced-HTTP-fr.md  

See also: https://github.com/nicolas-grekas/Patchwork-Doc/blob/master/README.md

Introduction
============

PHP gives access to HTTP submitted input data through autoglobals:

- `$_SERVER` contains the HTTP headers from the request at `HTTP_*` index, environment variables at other index, including connection information about the client,
- `$_GET` contains query parameters of the URL, itself in `$_SERVER['REQUEST_URI']`,
- `$_COOKIE` contains data from the *Cookie* header, available in `$_SERVER['HTTP_COOKIE']`,
- `$_POST` gives access to data present in the body of the request, when they are presented with a content-type handled by PHP, like typically sent by browsers along with the POST method,
- `$_FILES` gives access to the files uploaded in the body of the request,
- `$_REQUEST` contains a merge of `$_GET`, `$_POST` and/or `$_COOKIE`.
- (this behavior can be altered by ini settings `variables_order` and `gpc_order`)

Requests are issued as described in the HTTP protocol. But the way of accessing the data they contain is of course PHP specific. It is then interesting to analyze the adequacy of the interface offered by PHP and the descriptions allowed by the protocol.

Note that the `filter` extension introduced in PHP 5.2 adds an other but equivalent way to get HTTP submitted data without going through the above autoglobals.

Description of an HTTP request
==============================

Before sending a request, the server has access to basic information about the client: IP address and possibly SSL context. This phase takes place before the launch of PHP and is passed to it via environment variables (`$_SERVER['REMOTE_ADDR']` and `$_SERVER['SSL_*']` eg.).

The request itself, in its primary structure, contains three subsections:

1. The first line contains the HTTP method (GET, POST, etc..), the URL and the used protocol version (HTTP/1.1). This line is the only one the web server may need to decide on handing over to PHP. This information is available via `$_SERVER['REQUEST_METHOD']`, `$_SERVER['REQUEST_URI']` and `$_SERVER['SERVER_PROTOCOL']` respectively.
2. The following lines, as they are not empty, make a list of keys and values: the headers.
3. The blank line that ends the headers is followed by the body of the request, whose content should be interpreted according to the *Content-Type* header. The body of the request is typically empty for GET requests and containing the values of form fields for POST requests.

Description and limitations of the PHP interface
================================================

Until the first line of the request, all information is available if the server send it to PHP via environment variables.

HTTP headers
------------

For each key/value header pair, PHP creates an `$_SERVER['HTTP_KEY']` index containing the raw value. This index is created by putting the original key in capital then altering certain non-alphanumeric characters. To the extent that the name of the header is case insensitive and special characters do not carry specific information, this transformation does not hide any useful information. As `$_SERVER` is an array, it can not contain two identical index. However, nothing prevents the same key to be present several times in the headers. As this situation may not occur (except in certain targeted attacks) this limitation has no practical consequence. Note also that two headers may be different but identical for PHP after transformation as described above. In fact, following the [HTTP RFC](http://www.w3.org/Protocols/rfc2616/rfc2616-sec4.html#sec4.2), multiple header fields are often combined into one by the web server.

The `getallheaders()` function is an other mean to fetch HTTP request headers, which is only available when using Apache SAPI (or FastCGI since PHP 5.4). It returns key => value pairs, so duplicate headers still collide.

Cookies and URL parameters
--------------------------

The *Cookie* header and the URL parameters contain themselves key/value pairs that are available for more comfort with `$_COOKIE` and `$_GET` respectively. The algorithm to go from raw string to an array is exposed by `parse_str()`, which allows for easy observation of the operation:

As with headers, some non-alphanumeric characters of the keys are replaced with an underscore or deleted, the case is preserved, but the unicity of keys still applies. As is common here to need multiple values of the same key, PHP allows you to create arrays from scalar data sources using brackets in the name of the keys. This trick allows to circumvent the unicity limitation by combining all the values of the same key in an array. This syntax can also appoint key to create an array of arrays, in the hope that this makes life easier for developers.
For example:

```php
<?php

foo=A&foo=B       => $_GET['foo'] = 'B';
foo[]=A&foo[]=B   => $_GET['foo'] = array(0 => 'A', 1 => 'B');
foo[]=A&foo=B     => $_GET['foo'] = 'B';
foo=A&foo[]=B     => $_GET['foo'] = array(0 => 'B');
foo[bar][]=A      => $_GET['foo'] = array('bar' => array(0 => 'A'));
foo[]=A&foo[0]=B  => $_GET['foo'] = array(0 => 'B');

?>
```

If this technique removes the initial restriction, it is based on a semantic confusion: in the above example, `foo`, `foo[]` and `foo[0]` should not collide. This is reflected for instance if you want to access to an HTML form element via `document.getElementsByName()` in the browser: the full name, with the brackets if any, is required. However, PHP side, these variants collide. We can consider here that PHP requires the developer to adopt special agreements to circumvent the internal limitations that the protocol does not have.

However, since the raw information can be found in `$_SERVER`, it is possible to create another interface that does not suffer from this.

Request body
------------

The body of the request is to be interpreted according to the *Content-Type* header. HTML defines two possible values: *multipart/form-data* and *application/x-www-form-urlencoded*, also interpreted natively by PHP. Other contents are possible, eg JSON or XML for some web-services (server to server and AJAX).

The type *multipart/form-data* is opaque to PHP developers: only `$_POST` and `$_FILES` are available, without any access to raw data. Other types of content are accessible via the `php://stdin` stream. This point remains to be verified by testing the various SAPI (Apache module, FastCGI, CGI, etc.). Since PHP 5.4, request body processing can be disabled by setting off the `enable_post_data_reading` ini setting.

How these arrays are filled is identical to that described above (specific characters altered, brackets in the name of the keys, collisions). They therefore suffer the same limitations.

Bracketed syntax: an unnecessary complexity
-------------------------------------------

The magic of bracketed syntax creates unnecessary complexity for the PHP developer:

- Where the data source is a simple keys/values list, PHP has a recursive tree structure thus more difficult to handle.
- It introduces a difference between PHP and HTML addressing.
- The literal syntax is anyway needed in the generated HTML.

It is thus an abstraction that doesn't abstract anything, because it adds to the literal syntax without improving or replacing it. It would be equally effective to access data via the literal name of the keys, using prefixes or suffixes where necessary to contextualize the variables (functional equivalence between `user[name]` and `user_name` for example). The brackets are still needed for multi-valued keys.

Improving native PHP interface
==============================

Problems and (false) solutions
--------------------------------

To summarize the previous section, the interface provided by PHP suffers from the following limitations:

1. `$ _REQUEST`:
  1. mixing different sources contexts, should never be used.
2. `$_SERVER`:
  1. alteration of particular characters in headers names,
  2. name collision for repeated headers,
  3. web server dependency to get request context via environment variables.
3. `$_GET`, `$_COOKIE`, `$_POST`, `$_FILES`:
  1. alteration of particular characters in keys names,
  2. name collision for multi-valued keys,
  3. non-semantic collision created by the bracketed syntax and by items 2.1 and 3.1,
  4. complexity introduced by the bracketed syntax.
4. Access to raw data:
  1. no method is referenced to access the raw HTTP headers, but `getallheaders()` can help when available,
  2. the input to `$_GET` and `$_COOKIE` are in `$_SERVER`,
  3. `php://stdin` should allow access to the body of the request, but only when the `enable_post_data_reading` ini setting is set to off or for contents other than *multipart/form-data*.

Destroying `$_REQUEST` as soon as possible to avoid any temptation to use it solves item 1.1. Anyway, its portability is limited by *php.ini*.

Item 4.1 makes it impossible to fix 2.1 and 2.2. Point 2.3 is inherent in how PHP works.

Point 4.3 requires writing and parsing the request body in PHP. When a large file is transferred, it is embarrassing to monopolize the server with such a process. In addition, this is unlikely to be portable because it requires changing the configuration of the web server. This therefore seems not viable as a general solution.

If 4.3 is not working, altered data in `$_POST` and `$_FILES` are the only one available to access input data. For the sake of consistency, despite 4.2, rebuilding `$_GET` and `$_COOKIE` from their raw data does not seem a good idea. However, here is an implementation that can analyze a raw string like `$_SERVER['HTTP_COOKIE']`:

```php
<?php

function parseQuery($query)
{
    $fields = array();

    foreach (explode('&', $query) as $q)
    {
        $q = explode('=', $q, 2);
        if ('' === $q[0]) continue;
        $q = array_map('urldecode', $q);
        $fields[$q[0]][] = isset($q[1]) ? $q[1] : '';
    }

    return $fields;
}

?>
```

Finally, to avoid breaking the current interface, documentation and customs that go with `$_GET` `$_COOKIE`, `$_POST` or `$_FILES`, their original state as defined by PHP should be kept.

The desired solution should allow to mitigate points 3.1, 3.2, 3.3 and 3.4 using `$_GET`, `$_COOKIE`, `$_POST` and `$_FILES` as data sources, without modifying them. As a corollary, access to data in PHP and HTML should be done using exactly the same keys.

Key normalization
-----------------

Item 3.1 is not a very restrictive limitation. Indeed, the keys are already known by the application that needs them. They carry no information other than the labeling of form data. The real problem is that 3.1 introduces a potential difference between the way of naming fields in HTML, and how to access them in PHP. For example, an HTML field named `foo.bar` will be available via `$_GET['foo_bar']`. The solution however is quite simple: just avoid all keys names that introduce a difference between HTML and PHP. This solution also solves 3.3.

Bracketed syntax apart, PHP processing of keys is more complicated than a search and replace. For example, a `.` is replaced by a `_` only if it is not preceded by a `[` and so on. Fortunately, `parse_str()` allows us to test and especially to reproduce this behavior in PHP. To verify that a key name is acceptable to PHP, just use `parse_str()`:

```php
<?php

$key_name = 'foo.bar';
$query_str = rawurlencode($key_name);
parse_str($query_str, $array_result);

// KO in this example, foo.bar becomes foo_bar
echo isset($array_result[$key_name])
    ? 'OK: PHP and HTML key addressing is identical'
    : 'KO: key contains specials characters for PHP';

?>
```

The collision 3.2 for multi-valued keys can only be circumvented by using empty brackets in keys names. Again, `parse_str()` allows us to access this behavior while ignoring implementation details. To test whether a particular key can be used to manage multiple values, consider the following code:

```php
<?php

$key_name = 'foo';
$query_str = rawurlencode($key_name);
$query_str .= '&' . $query_str; // Here is the trick
parse_str($query_str, $array_result);

// KO in this example, foo can't address multiple values  
echo is_array($array_result[$key_name])
    ? 'OK: key name can address multiple values'
    : 'KO: PHP restricts this key name to a single value';

?>
```

If the principle of this code is a step in the right direction, it does not work because it does not take into account the specifics of the bracketed syntax.

To account for the syntax, a key name has to be rebuild from the `$array_result` structure as given by `parse_str()` and then compared to `$key_name`. Here is such a function:

```php
<?php

function test_query_key_name($key_name, &$multivalues_capable = null)
{
    $multivalues_capable = false;
    $a = rawurlencode($key_name);
    parse_str($a . '&' . $a, $a);
    $canonic_name = key($a);
    if (null === $canonic_name) return false;
    $a = $a[$canonic_name];

    while (is_array($a))
    {
        if (2 === count($a))
        {
            $canonic_name .= '[]';
            $multivalues_capable = true;
        }
        else
        {
            $canonic_name .= '[' . key($a) . ']';
        }

        $a = end($a);
    }

    return $canonic_name === $key_name;
}

?>
```

This function can test if a key name is acceptable in PHP, if it can accept multiple values and supports the bracketed syntax (it also illustrates very well the unnecessary complexity introduced by this syntax). If each key name used by an application satisfies this test, then 3.1, 3.2 and 3.3 are solved.

Access by literal keys
----------------------

The point 3.4 is the last to be resolved: accessing data in `$_GET`, `$_COOKIE`, `$_POST` or `$_FILES` (or more generally, an array built by `parse_str()`) using the literal version of the keys.

The function we seek to create is therefore at least two input parameters: the looked up literal `$key_name` and an `$input_array` collection built by `parse_str()`. It returns a list of values in `$input_array` that match `$key_name`. In the case where `$key_name` would not be allowed by PHP (see above), the function could return `false` or cause an exception:

```php
<?php

function get_values_from_parse_str($key_name, $parse_str_array)
{
    if (!test_query_key_name($key_name)) return false;
    $values = array();
    // [...] extract values matching $key_name from $parse_str_array
    return $values ;
}

?>
```

Thus, instead of using `$_GET['foo']['bar']` to access URL parameter `foo[bar][]`, we could use `get_values_from_parse_str('foo[bar][]', $ _GET)`.

The structure of `$_FILES` is a bit more complex, but the same logic applies.

Conclusion
==========

PHP offers comprehensive autoglobals to access external data sent with each request. These variables do not expose all the possibilities allowed by the HTTP protocol, but a controlled use can in practice minimize the impact of this limitation.

Two problems are particularly troublesome:

1. lack of access to multi-valued keys without using a special syntax,
2. complexity of the magic bracketed syntax.

Until PHP natively provides another interface freed from these problems, a different interface in user space can circumvent them.

Appendix: Reference Implementation
----------------------------------

See [HttpQueryField](https://github.com/nicolas-grekas/Patchwork/blob/master/core/http/class/Patchwork/HttpQueryField.php) for a class resulting from this reflection. It is to be used like this :

```php
<?php

use Patchwork\HttpQueryField as Field;

$field = new Field('foo[bar][]');
$values = $field->getValues($_GET);
$single_value = end($values);
// or
$values = Field::getNew('foo[bar][]')->getValues($_GET);
// or
$single_value = end(Field::getNew('foo[bar][]')->getValues($_GET));

?>
```
