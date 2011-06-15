<?php

function get_values_from_parse_str($key_name, $parse_str_array)
{
    if (!test_query_key_name($key_name)) return false;
    $values = array();
    // […] extract values matching $key_name from $parse_str_array
    return $values ;
}