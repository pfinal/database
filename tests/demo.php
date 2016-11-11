<?php

include "./autoload.php";

$config = [
    'dsn' => 'mysql:host=localhost;dbname=test',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
    'tablePrefix' => 'db_',
];

$db = new \PFinal\Database\Builder($config);
$db->getConnection()->enableQueryLog();

$id = $db->table('test')->insertGetId(['username' => 134, 'status' => '0']);

$count = $db->table('test')->lockForUpdate()->count('id');

var_dump($count);

var_dump($db->getConnection()->getQueryLog());

