<?php

//  ./vendor/bin/phpunit tests/DbTest
class DbTest extends \PHPUnit_Framework_TestCase
{
    private function getConn()
    {
        $config = array(
            'dsn' => 'mysql:host=127.0.0.1;dbname=test',
            'username' => 'root',
            'password' => 'root',
            'charset' => 'utf8',
            'tablePrefix' => 'db_',
            'slave' => array(
                array(
                    'dsn' => 'mysql:host=127.0.0.1;dbname=test',
                    'username' => 'root',
                    'password' => 'root',
                )
            ),
        );

        return new \PFinal\Database\Connection($config);
    }

    public function testConnection()
    {

        $conn = self::getConn();

        //主库连接
        $this->assertTrue($conn->getPdo() === $conn->getPdo());
        //从库连接
        $this->assertTrue($conn->getPdo() !== $conn->getReadPdo());

        $sql = 'DROP TABLE IF EXISTS db_test';
        $conn->execute($sql);

        $sql = 'CREATE TABLE `db_test` (
          `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
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


        $sql = 'INSERT INTO {{%test}} (username,status)  VALUES (?,?)';

        $count = 0;

        $count += $conn->execute($sql, ['Summer', 10]);
        $count += $conn->execute($sql, ['Ethan', 20]);
        $count += $conn->execute($sql, ['Jack', 10]);

        $this->assertTrue($count == 3);
        $this->assertTrue($conn->getLastInsertId() == 3);

    }


    public function testDb()
    {
        $db = new \PFinal\Database\Builder();
        $db->setConnection(self::getConn());

        $db->getConnection()->enableQueryLog();

        $rowCount = $db->table('{{%test}}')->insert(['username' => 'aaa', 'status' => 10]);
        $this->assertTrue($rowCount == 1);

        $id = $db->table('{{db_test}}')->insertGetId(['username' => 'bbb', 'status' => 10]);
        $this->assertTrue($id >0);

        $arr = $db->findAllBySql('SELECT * FROM {{%test}} WHERE status=? LIMIT 2', [10]);
        $this->assertTrue(count($arr) == 2);

        $arr = $db->table('test')->where('id=1 or id=2')->findAll();
        $this->assertTrue(count($arr) == 2);
        $this->assertTrue($arr[0]['id'] == 1 && $arr[1]['id'] == 2);

        $arr = $db->table('test')->where('id=?', [1])->where('id=:id', ['id' => 2], false)->findAll();
        $this->assertTrue(count($arr) == 2);
        $this->assertTrue($arr[0]['id'] == 1 && $arr[1]['id'] == 2);

        $row = $db->table('test')->findOne('id=?', [2]);
        $this->assertTrue($row['username'] === 'Ethan');

        $row = $db->table('test')->findOne(['id' => 2]);
        $this->assertTrue($row['username'] === 'Ethan');


        $rowCount = $db->table('test')->update(['username' => 'new'], 'id=?', [2]);
        $this->assertTrue($rowCount == 1);
        $row = $db->table('test')->findByPk(2);
        $this->assertTrue($row['username'] === 'new');

        $rowCount = $db->table('test')->update(['username' => 'new2'], ['id' => 2]);
        $this->assertTrue($rowCount == 1);
        $row = $db->table('test')->findByPk(2);
        $this->assertTrue($row['username'] === 'new2');

        $rowCount = $db->table('test')->where('id=?', [2])->update(['username' => 'new3']);
        $this->assertTrue($rowCount == 1);
        $row = $db->table('test')->where(['id' => 2])->findOne();
        $this->assertTrue($row['username'] === 'new3');

        $rowCount = $db->table('test')->where('id=?', [2])->increment('status');
        $this->assertTrue($rowCount == 1);
        $row = $db->table('test')->where(['id' => 2])->findOne();
        $this->assertTrue($row['status'] == 21);


        $rowCount = $db->table('test')->where('id=?', [3])->delete();
        $this->assertTrue($rowCount == 1);


        //count()、sum()、max()、min()、avg()
        $count = $db->table('test')->where('id>?', [3])->count();
        $this->assertTrue($count == 2);

        $arr = $db->table('test')->loadDefaultValues();
        $this->assertTrue($arr['status'] == 10);

        $arr = $db->table('test')->loadDefaultValues(new stdClass());
        $this->assertTrue($arr->status == 10);


        //dump($db->getConnection()->getQueryLog());
    }

}


