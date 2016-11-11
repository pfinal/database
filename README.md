# [Database](http://pfinal.cn)

数据库操作类

PHP交流 QQ 群：`16455997`

环境要求：PHP >= 5.3

使用 [composer](https://getcomposer.org/)

  ```shell
  composer require "pfinal/database"
  ```

  ```php
<?php

    require __DIR__ . '/../vendor/autoload.php';

    $config = array(
        'dsn' => 'mysql:host=localhost;dbname=test',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8',
        'tablePrefix' => 'db_',
    );
    
    $db = new \PFinal\Database\Builder($config);
    
    $id = $db->table('user')->insertGetId(['username' => 'Jack', 'email' => 'jack@gmail.com']);
    
    echo $id;
    
  ```
如果你的项目未使用Composer,请使用下面的方式做类自动加载

```php
<?php

    include "./src/ClassLoader.php";
    
    $loader = new \PFinal\Database\ClassLoader();
    $loader->register();
        
```