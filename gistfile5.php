<?php

use Patchwork\HttpQueryField as Field;

$field = new Field('foo[bar][]');
$values = $field->getValues($_GET);
$single_value = end($values);
// or
$values = Field::getNew('foo[bar][]')->getValues($_GET);
// or
$single_value = end(Field::getNew('foo[bar][]')->getValues($_GET));