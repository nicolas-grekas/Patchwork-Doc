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