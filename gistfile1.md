==========================================
Gestion améliorée des requêtes HTTP en PHP
==========================================

Introduction
============

PHP repose sur des variables auto-globales alimentées automatiquement pour faciliter l'accès aux données transmises via les requêtes HTTP :

- `$_SERVER` contient les entêtes HTTP de la requête dans les index `HTTP_*`, ainsi que les variables d'environnement dans les autres index, dont les informations sur la connexion avec le client,
- `$_GET` contient les paramètres passés dans l'URL de la requête, elle-même accessible via `$_SERVER['REQUEST_URI']`,
- `$_COOKIE` contient les cookies passés dans l'entête *Cookie*, accessible via `$_SERVER['HTTP_COOKIE']`,
- `$_POST` donne accès aux données présentes dans le corps de la requête, lorsqu'elles sont encodées selon un type mime géré par PHP, tel que typiquement envoyé par les navigateurs avec la méthode POST,
- `$_FILES` donne accès aux fichiers transférés avec le corps de la requête,
- `$_REQUEST` contient une agrégation paramétrable de `$_GET`, `$_POST` et/ou `$_COOKIE`.

Si les requêtes sont toutes effectuées selon un format standard défini dans le protocole HTTP, la manière d'y accéder décrite ci-dessus est évidemment propre à PHP. Il est alors intéressant d'analyser l'adéquation entre cette interface offerte par PHP et les capacités de description du protocole.

Description d'une requête HTTP
==============================

En prélude à l'envoi d'une requête, le serveur accède aux informations fondamentales sur le client : adresse IP et contexte SSL le cas échéant. Cette phase ayant lieu avant le lancement de PHP, elles lui sont passées via des variables d'environnement (`$_SERVER['REMOTE_ADDR']` et `$_SERVER['SSL_*']` par ex.).

La requête en elle-même, dans sa structure primaire, contient 3 sous-sections :

1. Une première ligne contient le verbe HTTP (GET, POST, etc.), l'URL et la version du protocole utilisé (HTTP/1.1). Cette ligne est la seule dont le serveur web ait besoin pour décider de passer le relais à PHP. Les informations qu'elle contient sont disponibles via `$_SERVER['REQUEST_METHOD']`, `$_SERVER['REQUEST_URI']` et `$_SERVER['SERVER_PROTOCOL']` respectivement.
2. Les lignes suivantes, tant qu'elles ne sont pas vides, forment une liste de clefs et de valeurs : les entêtes.
3. La ligne vide qui termine les entêtes est suivie par le corps de la requête, dont le contenu est à interpréter suivant le type décrit dans l'entête *Content-Type*. Le corps de la requête est typiquement vide pour les requêtes GET et contenant les valeurs des champs d'un formulaire pour les requêtes POST.

Description et limitations de l'interface PHP
=============================================

Jusqu'à la première ligne de la requête, toutes les informations sont disponibles si le serveur les transmet à PHP via les variables d'environnement.

Entêtes HTTP
------------

Pour chaque couple d'entête clef/valeur, PHP crée un index `$_SERVER['HTTP_CLEF']` qui contient la valeur brute. Cet index est créé en mettant la clef d'origine en majuscule, et en altérant certains caractères non alphanumériques. Dans la mesure où le nom des entêtes est insensible à la casse et que ces caractères spéciaux ne portent pas d'information particulière, cette transformation n'est pas limitante pour accéder à la totalité de l'information utile. `$_SERVER` étant un tableau, il ne peut pas contenir deux index identiques. Pourtant, rien n'empêche une même clef d'être présente plusieurs fois dans les entêtes. Cette situation ne se produisant jamais (sauf dans certaines attaques ciblées) cette limitation n'a pas de conséquence pratique. Noter également que deux entêtes peuvent être différentes à l'origine mais identiques après transformation par PHP tel que décrit ci-dessus.

Cookies et paramètres de l'URL
------------------------------

L'entête *Cookie* et les paramètres de l'URL de la requête contiennent eux-même des couples clef/valeur qui sont disponibles pour plus de confort à travers `$_COOKIE` et `$_GET` respectivement. L'algorithme qui permet de passer de la chaîne brute à un tableau est le même que celui de la fonction `parse_str()`, ce qui permet d'observer facilement l'opération :

Comme pour les entêtes, certains caractères non alphanumériques des clefs sont remplacés par un underscore ou supprimés, la casse est conservée, mais l'unicité des clefs s'applique bien sur toujours. Comme il est courant ici d'avoir besoin des valeurs multiples d'une même clef, PHP permet de créer des tableaux à partir de données sources scalaires en utilisant des crochets dans le nom des clefs. Cette astuce permet de contourner la limitation des clefs uniques en regroupant toutes les valeurs d'une même clef dans un tableau. Cette syntaxe permet aussi de nommer des clefs de façon à créer un tableau de tableaux, dans l'espoir que ceci simplifie la vie des développeurs.
Par exemple :

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

Si cette technique permet de contourner la limitation initiale, elle repose sur une confusion sémantique : dans les exemples ci-dessus, `foo`, `foo[]` et `foo[0]` ne devraient pas entrer en collision car du point de vue de la requête, ce sont des clefs différentes. Ceci transparaît par exemple si on veut accéder via `document.getElementsByName()` à un élément de formulaire HTML dans le navigateur : le nom complet, avec les crochets le cas échéant, est indispensable. Pourtant, coté PHP, ces variantes entrent en collision. On peut considérer ici que PHP oblige le développeur à adopter des conventions particulières pour contourner des limitations internes que le protocole n'impose pas.

Cependant, puisque les informations brutes sont présentes dans `$_SERVER`, il est possible de créer une autre interface qui ne souffre pas de ce défaut.

Corps de la requête
-------------------

Le corps de la requête est à interpréter selon le contenu de l'entête *Content-Type*. HTML en définit deux particuliers : *multipart/form-data* et *application/x-www-form-urlencoded*, interprétés nativement par PHP.

D'autres contenus sont possibles, par exemple JSON ou XML pour certains web-services (de serveur à serveur ou AJAX).

Le type *multipart/form-data* est géré de façon opaque par PHP : le développeur n'a accès qu'aux tableaux `$_POST` et `$_FILES`, sans pouvoir accéder aux données brutes. Les autres types de contenus sont accessibles via le flux `php://stdin`. Ce point reste à vérifier en testant les différentes SAPI (module Apache, FastCGI, CGI, etc.).

La manière dont ces tableaux se remplissent est identique à celle décrite précédemment (caractères particuliers altérés, crochets spéciaux dans le nom des clefs, gestion des collisions). Ils souffrent donc des mêmes défauts.

Syntaxe à crochets : une complexité inutile
-------------------------------------------

Le caractère magique de la syntaxe à crochets crée une complexité inutile pour le développeur PHP :

- Alors que la source de données est une simple liste de clefs/valeurs, PHP présente une structure arborescente donc récursive, plus difficile à manipuler.
- Elle introduit une différence d'adressage entre PHP et HTML.
- La syntaxe littérale est de toute façon nécessaire dans le code HTML.

C'est donc une abstraction qui n'abstrait rien, car elle s'ajoute à la syntaxe littérale sans l'améliorer ni la remplacer. Il serait tout aussi efficace d'accéder aux données via le nom littéral des clefs, en utilisant des préfixes ou des suffixes si nécessaire pour contextualiser les variables (équivalence fonctionnelle entre `user[name]` et `user_name` par exemple). Les crochets sont malgré tout indispensables pour les clefs à valeurs multiples.

Amélioration de l'interface native PHP
======================================

Problèmes et (fausses) solutions
--------------------------------

Pour résumer la section précédente, l'interface fournie par PHP souffre des défauts suivants :

1. `$_REQUEST` :
  1. mélange contextes sources différents, ne devrait jamais être utilisé.
2. `$_SERVER` :
  1. mise en majuscules et suppression de caractères particuliers du nom des entêtes,
  2. collision de nom pour les entêtes répétées,
  3. dépendance sur le serveur web pour transmettre le contexte de la requête via les variables d'environnement.
3. `$_GET`, `$_COOKIE`, `$_POST`, `$_FILES` :
  1. altération de caractères particuliers du nom des clefs,
  2. collision de nom pour les clefs à valeurs multiples,
  3. collision non-sémantique créée par la syntaxe à crochets et par les points 2.a et 3.a,
  4. complexité apportée par la syntaxe à crochets.
4. Accès aux données brutes :
  1. aucune méthode n'est référencée pour accéder aux entêtes HTTP brutes,
  2. les données qui alimentent `$_GET` et `$_COOKIE` sont dans `$_SERVER`,
  3. `php://stdin` devrait permettre d'accéder au corps de la requête, mais uniquement pour les contenus autres que multipart/form-data.

Le point 1.1 est soluble de façon trivial en détruisant la variable le plus tôt possible pour éviter toute tentation de s'en servir. De toute façon, la portabilité de `$_REQUEST` est limitée par le *php.ini*.

Le point 4.1 rend impossible toute tentative d'améliorer les défauts 2.1 et 2.2. Le point 2.3 est inhérent à la manière de fonctionner de PHP.

Si le point 4.3 est avéré, alors une modification du *Content-Type: multipart/form-data* par le serveur web pourrait permettre de contourner l'opacité de PHP pour ce type particulier. Pourtant, cette solution nécessite de réécrire et surtout d'exécuter en PHP l'interprétation du contenu. Lorsqu'un fichier lourd est transféré, il est gênant de monopoliser ainsi un processus serveur. De plus, elle a peu de chance d'être portable car elle nécessite de modifier la configuration du serveur web. Cette solution semble donc peu viable.

Si 4.3 n'est pas opérationnel, les données altérées présentes dans `£_POST` et `$_FILES` sont les seules à disposition pour accéder aux données de formulaires. Par soucis d'homogénéité et accessoirement de performance, et ce malgré 4.2, reconstruire un équivalent à `$_GET` et `$_COOKIE` à partir de leurs données brutes ne semble pas non plus une bonne idée. Voici cependant une implémentation qui permet d'analyser une chaîne brute telle que `$_SERVER['HTTP_COOKIE']` :


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

Syntaxe à crochets entre parenthèses quelques instants, la transformation effectuée par PHP est plus compliquée qu'un simple rechercher/remplacer. Par exemple, un « . » n'est remplacé par un « _ » que s'il n'est pas précédé par un « [ », etc. Heureusement, la fonction `parse_str()` nous permet de tester et surtout de reproduire ce comportement en PHP. Pour vérifier qu'un nom de clef est acceptable par PHP, il suffit d'utiliser `parse_str()` ainsi :

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

La classe ci-dessous implémente le code qui résulte de cette réflexion. Chaque instance représente une seule clef à la fois, et est à utiliser ainsi par exemple :

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