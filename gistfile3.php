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