<?php

use Patchwork\HttpQueryField;

$field = new HttpQueryField('foo[bar][]');
$values = $field->getValues($_GET);
$single_value = end($values);
// or
$values = HttpQueryField::getNew('foo[bar][]')->getValues($_GET);
// or
$single_value = end(HttpQueryField::getNew('foo[bar][]')->getValues($_GET));