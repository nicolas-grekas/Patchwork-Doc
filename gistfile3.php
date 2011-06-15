<?php

$key_name = 'foo.bar';
$query_str = rawurlencode($key_name);
parse_str($query_str, $array_result);

// KO in this example, foo.bar becomes foo_bar 
echo isset($array_result[$key_name])
    ? 'OK: PHP and HTML key addressing is identical'
    : 'KO: key contains specials characters for PHP';