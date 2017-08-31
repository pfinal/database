
实例化对象

```
$config = array(
    'dsn' => 'mysql:host=localhost;dbname=test',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8',
    'tablePrefix' => 'db_',
);

$db = new \PFinal\Database\Builder($config);
```

新增数据

```
$user = ['name' => 'jack', 'email' => 'jack@gmail.com'];
$bool = $db->table('user')->insert($user);
$userId = $db->table('user')->insertGetId($user);

```

更新数据

```
//UPDATE `user` SET `name` = 'mary' WHERE `id` = 1
$rowCount = $db->table('user')->where('id=?', 1)->update(['name' => 'mary']);
$rowCount = $db->table('user')->wherePk(1)->update(['name' => 'mary']);

//UPDATE `user` SET `age` = `age` + 1, `updated_at` = '1504147628' WHERE id = 1
$db->table('user')->where('id=?', 1)->increment('age', 1, ['updated_at' => time()]);

```

删除数据

```
$rowCount = $db->table('user')->where('id=?', 1)->delete();
```

查询数据

```
//查询user表所有数据
$users = $db->table('user')->findAll();

//跳过4条，返回2条
$users = $db->table('user')->limit('4, 2')->findAll();

//排序
$users = $db->table('user')->limit('4, 2')->orderBy('id desc')->findAll();

//返回单条数据
$user = $db->table('user')->where('id=?', 1)->findOne();

//查询主键为1的单条数据
$user = $db->table('user')->findByPk(1);

//统计查询
$count = $db->table('user')->count();

$maxUserId = $db->table('user')->max('id');

$minUserId= $db->table('user')->min('id');

$avgAge = $db->table('user')->avg('age');

$sumScore = $db->table('user')->sum('score');

```

更多Where条件用法

```
//SELECT * FROM `user` WHERE `name`='jack' AND `status`='1'
$users = $db->table('user')->where('name=? and status=?', ['jack', '1'])->findAll();
$users = $db->table('user')->where('name=:name and status=:status', ['name' => 'jack', 'status' => '1'])->findAll();
$users = $db->table('user')->where(['name' => 'jack', 'status' => 1])->findAll();
$users = $db->table('user')->where(['name' => 'jack'])->where(['status' => 1])->findAll();
//以上4种写法，是一样的效果

//SELECT * FROM `user` WHERE `name` like '%j%'
$users = $db->table('user')->where('name like ?', '%j%')->findAll();

//SELECT * FROM `user` WHERE `id` IN ('1','2','3')
$users = $db->table('user')->whereIn('id', [1, 2, 3])->findAll();

//SELECT * FROM `user` WHERE `name`='jack' OR `name` ='mary'
$users = $db->table('user')->where('name=? or name =? ', ['jack', 'mary'])->findAll();
$users = $db->table('user')->where('name=?', 'jack')->where('name=?', 'mary',false)->findAll();

```

事务

```
$db->getConnection()->beginTransaction();    //开启事务
$db->getConnection()->commit();              //提交事务
$db->getConnection()->rollBack();            //回滚事务
```

调试SQL

```
$db->getConnection()->enableQueryLog();
// your code ...
$sql = $db->getConnection()->getQueryLog();
var_dump($sql);

```