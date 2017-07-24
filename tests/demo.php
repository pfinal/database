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

$res = $db->getConnection()->queryScalar('select count(id) from pre_wechat_reply group by id');
var_dump($res);

//$db->table('test')->where('id=?', [44])->increment('aa', 1, ['bb' => 6]);
//
//$id = $db->table('test')->insertGetId(['username' => 134, 'status' => '0']);
//
//$count = $db->table('test')->lockForUpdate()->count('id');
//
//var_dump($count);

var_dump($db->getConnection()->getQueryLog());

