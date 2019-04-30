<?php


error_reporting(E_ALL);

set_error_handler(function ($errno, $errstr, $errfile, $errline) {

    if (!(error_reporting() & $errno)) { //避免@失效
        return;
    }

    //将Warning升级为Exception
    throw new \Exception($errstr);
});


set_exception_handler(function (\Throwable $exception) {
    echo 'Exception:' . $exception->getMessage() . ' #' . $exception->getLine() . PHP_EOL;
    exit;
});


include __DIR__ . "/autoload.php";

$config = array(
    'dsn' => 'mysql:host=127.0.0.1;dbname=test',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
    'tablePrefix' => 'db_',
    'reconnect' => true,
);

$db = new \PFinal\Database\Builder($config);

$db->getConnection()->enableQueryLog();

// ================= //

$db->getConnection()->query('select sleep(1)');
var_dump(count($db->getConnection()->getQueryLog(false)));

sleep(10);
//到mysql中kill此连接，或设置set global wait_timeout=5 模拟连接长时间空闲，被服务端超时关闭

// show full processlist;
// kill <Id>

$db->getConnection()->query('select sleep(1)');
var_dump(count($db->getConnection()->getQueryLog()));
exit;


// =================//
$db->getConnection()->query('select sleep(1)');
var_dump(count($db->getConnection()->getQueryLog(false)));

$db->getConnection()->query('select sleep(10)');

//此时到mysql中kill此连接，强制kill一个条正在执行的sql
//这种情况，不应该再次重连，需要用set_error_handler，在检测到Warning时，终止运行

var_dump(count($db->getConnection()->getQueryLog()));
