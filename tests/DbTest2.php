<?php

//  ./vendor/bin/phpunit tests/DbTest2
class DbTest2 extends \PHPUnit_Framework_TestCase
{
    private function getConn()
    {
        $config = [
            'dsn' => 'mysql:host=localhost;dbname=test',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8',
            'tablePrefix' => 'db_',
        ];

        return new \PFinal\Database\Connection($config);
    }

    public function testConnection()
    {

        $conn = self::getConn();

        //主库连接
        $this->assertTrue($conn->getPdo() === $conn->getPdo());

        $sql = 'DROP TABLE IF EXISTS db_test2';
        $conn->execute($sql);

        $sql = 'CREATE TABLE `db_test2` (
          `user_id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `username` varchar(255) NOT NULL,
          `auth_key` varchar(32) NOT NULL DEFAULT "",
          `password_hash` varchar(255) NOT NULL DEFAULT "",
          `password_reset_token` varchar(255) DEFAULT NULL,
          `email` varchar(255) NOT NULL DEFAULT "",
          `status` smallint(6) NOT NULL DEFAULT 10,
          `created_at` int(11) NOT NULL DEFAULT 0,
          `updated_at` int(11) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8';

        $conn->execute($sql);


        $sql = 'INSERT INTO {{%test2}} (username,status)  VALUES (?,?)';

        $count = 0;

        $count += $conn->execute($sql, ['Summer', 10]);
        $count += $conn->execute($sql, ['Ethan', 20]);
        $count += $conn->execute($sql, ['Jack', 10]);

        $this->assertTrue($count == 3);
        $this->assertTrue($conn->getLastInsertId() == 3);

        $db = new \PFinal\Database\Builder();
        $db->setConnection(self::getConn());

        $db->getConnection()->enableQueryLog();

        $user = $db->table('test2')->wherePk(1)->findOne();
        $this->assertTrue($user['username'] === 'Summer');


        var_dump($db->getConnection()->getQueryLog());
    }


}


