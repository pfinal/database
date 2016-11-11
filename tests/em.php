<?php
namespace A;

include "./autoload.php";

class User
{
    public static function tableName()
    {
        return 'user';
    }

    public function beforeSave()
    {
        $this->username = $this->username . '12345678';
        return true;
    }

}

$config = [
    'dsn' => 'mysql:host=localhost;dbname=test',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
    'tablePrefix' => '',
];

$em = new \PFinal\Database\EntityManager($config);

$user = $em->entity(User::class)->where(['id' => 3])->findOne();

var_dump($user);

$user->username = 'new' . time();

$em->save($user);

$user = $em->entity(User::class)->where(['id' => 3])->findOne();
var_dump($user);

var_dump($em->entity(User::class)->count());

$user = new User();
$em->loadDefaultValues($user);
$user->username = 123456;
$em->save($user);
var_dump($user);
