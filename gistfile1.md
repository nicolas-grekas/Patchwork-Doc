=======================================
Enhancing PHP handling of HTTP requests
=======================================

Introduction
============

PHP gives access to HTTP submitted input data through autoglobals:

- `$_SERVER` contains the HTTP headers from the request at indexes `HTTP_*`, environment variables at other indexes, including connection information about the client,
- `$_GET` contains URL query parameters, itself in `$_SERVER['REQUEST_URI']`,
- `$_COOKIE` contains cookies from the *Cookie* header, available in `$_SERVER['HTTP_COOKIE']`,
- `$_POST` gives access to data existing in the body of the request, when they are presented with a content-type handled by PHP, like typically sent by browsers along with the POST method,
- `$_FILES` gives acces to the files upload in the body of the request,
- `$_REQUEST` contains a merge of `$_GET`, `$_POST` and/or `$_COOKIE`.

Requests are always issued as described in the HTTP protocol. But the way of accessing the data they contain is of course PHP specific. It is then interesting to analyze the adequacy of the interface offered by PHP and the descriptions allowed by the protocol.

Description of an HTTP request
==============================

Before sending a request, the server has access to basic information about the client: IP address and possibly SSL context. This phase takes place before the launch of PHP, this context is passed via environment variables (`$_SERVER['REMOTE_ADDR']` and `$_SERVER['SSL_*']` eg.).

The request itself, in its primary structure, contains three subsections:

1. The first line contains the HTTP method (GET, POST, etc..), the URL and the used protocol version (HTTP/1.1). This line is the only one the web server may need to decide on handing over to PHP. This information is available via `$_SERVER['REQUEST_METHOD']`, `$_SERVER['REQUEST_URI']` and `$_SERVER['SERVER_PROTOCOL']` respectively.
2. The following lines, as they are not empty, make a list of keys and values: the headers.
3. The blank line that ends the headers is followed by the body of the request, whose content should be interpreted according to the type described in the *Content-Type* header. The body of the request is typically empty for GET requests and containing the values ​​of form fields for POST requests.

Description and limitations of the PHP interface
================================================

Until the first row of the query, all information is available if the server sends them to PHP via environment variables.

HTTP headers
------------

For each key/value pair of header, PHP creates an `$_SERVER['HTTP_KEY']` index containing the raw value. This index is created by putting the original key in capital then altering certain non-alphanumeric characters. To the extent that the name of the header is case insensitive and special characters do not carry specific information, this transformation does not hide any the useful information. As `$_SERVER` is an array, it can not contain two identical index. However, nothing prevents the same key to be present several times in the headers. As this situation may not occur (except in certain targeted attacks) this limitation has no practical consequence. Note also that two headers may be different but identical for PHP after transformation as described above.

Cookies and URL parameters
--------------------------

The *Cookie* header and the URL parameters contains themselves key/value pairs that are available for more comfort with `$_COOKIE` and `$_GET` respectively. The algorithm to go from raw string to an array is the same as the one in `parse_str()`, which allows for easy observation of the operation:

As with headers, some non-alphanumeric characters of the keys are replaced with an underscore or deleted, the case is preserved, but the unicity of keys still applies. As is common here to need multiple values ​​of the same key, PHP allows you to create tables from scalar data sources using brackets in the name of the keys. This trick allows to circumvent the unicity limitation by combining all the values ​​of the same key in a table. This syntax can also appoint key to create an array of arrays, in the hope that this makes life easier for developers.
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

If this technique removes the initial restriction, it is based on a semantic confusion: in the examples above, `foo`, `foo[]` and `foo[0]` should not collide. This is reflected for instance if you want to access to an HTML form element via `document.getElementsByName()` in the browser: the full name, with the brackets if any, is required. However, PHP side, these variants collide. We can consider here that PHP requires the developer to adopt special agreements to circumvent the internal limitations that the protocol does not have.

However, since the raw information can be found in `$_SERVER`, it is possible to create another interface that does not suffer from this defect.

Request body
------------

The body of the request is to be interpreted according to the *Content-Type* header. HTML defines two possible values: *multipart/form-data* and *application/x-www-form-urlencoded*, also interpreted natively by PHP.

Other content is possible, eg JSON or XML for some web-services (server to server and AJAX).

The type *multipart/form-data* is opaque to PHP developpers: only `$_POST` and `$_FILES` are available, without any access to raw data. Other types of content are accessible via the `php://stdin` stream. This point remains to be verified by testing the various SAPI (Apache module, FastCGI, CGI, etc.).

How these tables are filled is identical to that described above (specific characters altered, brackets in the name of the keys, collisions). They therefore suffer the same defects.

Bracketed syntax: an unnecessary complexity
-------------------------------------------

The magic of bracketed syntax creates unnecessary complexity for the PHP developer:

- Where the data source is a simple keys/values list, PHP has a recursive tree structure thus more difficult to handle.
- It introduces a difference between PHP and HTML addressing.
- The literal syntax is anyway needed in the HTML.

It is thus an abstraction that doesn't abstract anything, because it adds to the literal syntax without improving or replacing it. It would be equally effective to access data via the literal name of the keys, using prefixes or suffixes where necessary to contextualize the variables (functional equivalence between `user[name]` and `user_name` for example). The brackets are still needed for multi-valued keys.

Improved native PHP interface
=============================

Problems and (false) solutions
--------------------------------

To summarize the previous section, the interface provided by PHP suffers from the following defects:

1. `$ _REQUEST`:
  1. mixing different contexts sources, should never be used.
2. `$_SERVER`:
  1. uppercasing and particular characters removing in header names,
  2. name collision for repeated headers,
  3. dependence on the web server to transmit the context of the request via environment variables.
3. `$_GET`, `$_COOKIE`, `$_POST`, `$_FILES`:
  1. alteration of particular characters in key names,
  2. name collision for multi-valued keys,
  3. non-semantic collision created by bracketed syntax and by items 2.a and 3.a,
  4. complexity introduced by the bracketed syntax.
4. Access to raw data:
  1. no method is referenced to access the raw HTTP headers,
  2. the input to `$_GET` and `$_COOKIE` are in `$_SERVER`,
  3. `php://stdin` should allow access to the body of the request, but only for content other than *multipart/form-data*.

Destroying `$_REQUEST` as soon as possible to avoid any temptation to use it solves item 1.1. In any case, its portability `$ _REQUEST` is limited by *php.ini*.

Item 4.1 makes it impossible to fix defects 2.1 and 2.2. Point 2.3 is inherent in how PHP works.

If point 4.3 is proved, then a change in *Content-Type: multipart/form-data* by the web server could be used to circumvent the opacity of PHP for this particular type. However, this solution requires rewriting and especially to run interpretation of the content in PHP. When a large file is transferred, it is embarrassing to monopolize a server process. In addition, it is unlikely to be portable because it requires changing the configuration of the web server. This solution therefore seems not viable.

If 4.3 is not working, altered data in `$_POST` and `$_FILES` are the only one available to access the form data. For the sake of consistency, despite 4.2, rebuilding `$_GET` and `$_COOKIE` from their raw data does not seem a good idea. However, here is an implementation that can analyze a raw string like `$_SERVER['HTTP_COOKIE']`:

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

Enfin, pour ne pas casser l'interface actuelle, la documentation et les habitudes qui vont avec, les variables `$_GET`, `$_COOKIE`, `$_POST` ou `$_FILES` devraient être conservées dans leur état d'origine défini par PHP.

La solution recherchée doit donc permettre de mitiger les défauts 3.1, 3.2, 3.3 et 3.4 en utilisant `$_GET`, `$_COOKIE`, `$_POST` et `$_FILES` comme données sources, sans les modifier. À titre corollaire, l'accès aux données en PHP et en HTML devrait s'effectuer en utilisant exactement les mêmes clefs.

Normalisation des clefs
-----------------------

Dans l'absolu, le point 3.1 n'est pas une limitation très contraignante. En effet, les clefs prises en compte sont de toute façon déjà connues par l'application qui en a besoin. Elles ne portent aucune information autre que l'étiquetage de données de formulaire. Le réel problème est que 3.1 introduit une différence potentielle entre la façon de nommer des champs en HTML, et la façon d'y accéder en PHP. Par exemple, un champs HTML nommé `foo.bar` sera accessible via `$_GET['foo_bar']`. La solution de 3.1 est donc assez simple : il suffit d'éviter tous les noms de clefs qui introduisent une différence entre la façon d'adresser un champs en HTML et en PHP. Cette solution résout également 3.3.

Syntaxe à crochets entre parenthèses quelques instants, la transformation effectuée par PHP est plus compliquée qu'un simple rechercher/remplacer. Par exemple, un `.` n'est remplacé par un `_` que s'il n'est pas précédé par un `[`, etc. Heureusement, la fonction `parse_str()` nous permet de tester et surtout de reproduire ce comportement en PHP. Pour vérifier qu'un nom de clef est acceptable par PHP, il suffit d'utiliser `parse_str()` ainsi :

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

La collision 3.2 pour les clefs à valeurs multiples ne peut être contournée qu'en utilisant des crochets vides dans le nom des clefs. La fonction `parse_str()` nous permet à nouveau d'accéder à ce comportement en faisant abstraction des spécificités d'implémentation. Pour tester si une clef particulière permet de gérer les valeurs multiples, il est ainsi possible d'envisager le code suivant :

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

Si sur le principe ce code est un pas dans la bonne direction, il ne fonctionne pas car il ne prend pas en compte les spécificités de la syntaxe à crochets.

Pour prendre en compte la syntaxe à crochets, la solution est de reconstruire un nom de clef à partir de la structure `$array_result` retournée par `parse_str()`, puis de comparer cette version canonique à l'entrée `$key_name`. Voici une telle fonction :

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

Cette fonction permet de tester si un nom de clef est acceptable en PHP, s'il permet d'accepter des valeurs multiples et prend en charge la syntaxe à crochets (elle illustre aussi très bien l'inutile complexité apportée par cette syntaxe). Si chaque nom de clef utilisé par une application vérifie ce test, alors les points 3.1, 3.2 et 3.3 sont résolus.

Accès par clefs littérales
--------------------------

Le point 3.4 est le dernier à résoudre : il s'agit d'accéder aux données présentes dans `$_GET`, `$_COOKIE`, `$_POST` ou `$_FILES` (ou de façon plus générale, un tableau construit par `parse_str()`) en utilisant directement la version littérale des clefs.

La fonction que nous cherchons à créer prend donc au moins deux paramètres en entrée : le nom littéral `$key_name` de la clef recherchée et une collection `$input_array` de valeurs construite par `parse_str()`. Elle retourne une liste de valeurs présentes dans $input_array qui correspondent à la clef `$key_name`. Dans le cas où `$key_name` ne serait pas autorisé par PHP (cf ci-dessus), la fonction pourrait retourner `false` ou bien générer une exception :

```php
<?php

function get_values_from_parse_str($key_name, $parse_str_array)
{
    if (!test_query_key_name($key_name)) return false;
    $values = array();
    // […] extract values matching $key_name from $parse_str_array
    return $values ;
}

?>
```

Ainsi, au lieu d'utiliser `$_GET['foo']['bar']` pour accéder aux valeurs du paramètre `foo[bar][]` de l'URL, il faudra utiliser `get_values_from_parse_str('foo[bar][]', $_GET)`.

La structure de `$_FILES` est un peu plus complexe, mais la même logique s'applique.

Conclusion
==========

PHP propose des variables auto-globales pour accéder aux données externes envoyées avec chaque requête. Ces variables ne permettent pas d'exploiter la totalité des possibilités permises par le protocole HTTP, mais une utilisation contrôlée permet en pratique de minimiser l'impact de cette limitation.

Deux problèmes sont particulièrement gênants :

1. l'impossibilité d'accéder aux valeurs multiples sans passer par une syntaxe spéciale,
2. les complexités introduites par la magie de la syntaxe à crochets.

En attendant que PHP propose nativement une autre interface débarassée de ces défauts, une interface différente en espace utilisateur permet d'en limiter la portée.

Annexe : implémentation de référence
------------------------------------

See https://gist.github.com/1027180#file_gistfile2.php for a class resulting from this reflection. It is to be used like this :

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