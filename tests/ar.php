<?php
namespace A;

include "./autoload.php";

class User extends \PFinal\Database\ActiveRecord
{
}

$config = [
    'dsn' => 'mysql:host=localhost;dbname=test',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
    'tablePrefix' => '',
];

$db = new \PFinal\Database\Connection($config);

$user = User::find($db)->where(['id' => 3])->one();

var_dump((array)$user->isNewRecord());
var_dump($user);
