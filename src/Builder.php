<?php

namespace PFinal\Database;

use Closure;

/**
 * 数据库操作辅助类
 *
 * @method int count($field = '*')
 * @method mixed sum($field)
 * @method mixed max($field)
 * @method mixed min($field)
 * @method mixed avg($field)
 *
 * @author  Zou Yiliang
 * @since   1.0
 */
class Builder
{
    /**
     * @var Connection 数据库连接对象
     */
    private $db;

    protected $table;
    protected $field;
    protected $orderBy;
    protected $limit;
    protected $offset;
    protected $condition;
    protected $params = array();
    protected $fetchClass;
    protected $lockForUpdate;
    protected $lockInShareMode;
    protected $useWritePdo = false;

    protected $join = array();

    protected $groupBy;
    protected $having;


    /**
     * 自动生成查询绑定参数前缀
     */
    const PARAM_PREFIX = ':_sb_';

    /**
     * @param array $config 配置信息
     */
    public function __construct(array $config = array())
    {
        if (count($config) > 0) {
            $this->setConnection(new Connection($config));
        }
    }

    /**
     * 设置数据库连接
     *
     * @return static
     */
    public function setConnection(Connection $connection)
    {
        $this->db = $connection;
        return $this;
    }

    /**
     * 返回数据库连接
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->db;
    }

    /**
     * 指定查询表名
     *
     * 此方法将自动添加表前缀, 例如配置的表前缀为`cms_`, 则传入参数 `user` 将被替换为 `cms_user`, 等价于`{{%user}}`
     * 如果希望使用后缀, 例如表名为`user_cms`, 使用`{{user%}}`
     * 如果不希望添加表前缀，例如表名为`user`, 使用`{{user}}`
     * 如果使用自定义表前缀(不使用配置中指定的表前缀), 例如表前缀为`wp_`, 使用`{{wp_user}}`
     *
     * @param string $tableName 支持 as 例如 user as u
     * @return static
     */
    public function table($tableName = '')
    {
        if (!empty($tableName)) {
            $tableName = self::addPrefix($tableName);
        }

        $builder = clone $this;
        $builder->table = $tableName;
        return $builder;
    }

    /**
     * 添加表前缀
     *
     * @param $tableName
     * @return string
     * @throws Exception
     */
    public function addPrefix($tableName)
    {
        // user as u
        // {{user}} as u
        // {{%user}} as u
        // user u
        if (preg_match('/^(.+?)\s+(as\s+)?(\w+)$/i', $tableName, $res)) {
            $tableName = $res[1];
            $asName = ' AS ' . $res[3];
        } else {
            $asName = '';
        }

        if (strpos($tableName, '{{') === false) {
            $tableName = '{{%' . $tableName . '}}';
        }
        if (!preg_match('/^\{\{%?[\w\-\.\$]+%?\}\}$/', $tableName)) {
            throw new Exception('表名含有不被允许的字符');
        }
        return $tableName . $asName;
    }

    /**
     * 执行新增
     *
     * @param array $data
     * @return bool
     */
    public function insert(array $data)
    {
        $names = array();
        $replacePlaceholders = array();
        foreach ($data as $name => $value) {
            static::checkColumnName($name);
            $names[] = '[[' . $name . ']]';
            $phName = ':' . $name;
            $replacePlaceholders[] = $phName;
        }
        $sql = 'INSERT INTO ' . $this->table . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', $replacePlaceholders) . ')';
        return 0 < static::getConnection()->execute($sql, $data);
    }

    /**
     * 执行新增,返回自增ID
     *
     * @param array $data
     * @return int
     */
    public function insertGetId(array $data)
    {
        if (static::insert($data)) {
            return static::getConnection()->getLastInsertId();
        }
        return 0;
    }

    /**
     * 根据SQL查询, 返回符合条件的所有数据, 没有结果时返回空数组
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function findAllBySql($sql = '', $params = array())
    {
        $sql = static::appendLock($sql);

        $useWritePdo = $this->useWritePdo;
        $fetchClass = $this->fetchClass;

        $afterFind = $this->afterFind;

        $this->reset();

        if ($fetchClass === null) {
            $fetchModel = array(\PDO::FETCH_ASSOC);
        } else {
            $fetchModel = array(\PDO::FETCH_CLASS, $fetchClass);
        }

        $data = static::getConnection()->query($sql, $params, $fetchModel, !$useWritePdo);

        if ($afterFind !== null) {
            call_user_func_array($afterFind, array($data));
        }

        return $data;
    }

    /**
     * 返回符合条件的所有数据, 没有结果时返回空数组
     *
     * @param string $condition
     * @param array $params
     * @return array
     */
    public function findAll($condition = '', $params = array())
    {
        $this->where($condition, $params);

        $sql = 'SELECT ' . static::getFieldString() . ' FROM ' . $this->table
            . $this->getJoinString()
            . $this->getWhereString()
            . $this->getGroupByString()
            . $this->getHavingByString()
            . $this->getOrderByString()
            . $this->getLimitString();

        $sql = static::replacePlaceholder($sql);
        return static::findAllBySql($sql, $this->params);
    }

    /**
     * 拆分查询，用于处理非常多的查询结果,而不会消耗大量内存，建议加上排序字段
     *
     * @param int $num 每次取出的数据数量 例如 100
     * @param callback $callback 每次取出数据时被调用,传入每次查询得到的数据(数组)
     *
     * 可以通过从闭包函数中返回 false 来中止组块的运行
     *
     * 示例
     *
     * DB::select('user')->where('status=1')->orderBy('id')->chunk(100, function ($users) {
     *    foreach ($users as $user) {
     *      // ...
     *    }
     * });
     *
     * @return boolean
     */
    public function chunk($num, $callback)
    {
        $offset = 0;
        $limit = (int)$num;
        do {
            $query = clone $this;
            $query->offset($offset);
            $query->limit($limit);
            $data = $query->findAll();
            $offset += $limit;

            if (count($data) > 0) {
                if (call_user_func($callback, $data) === false) {
                    return false;
                }
            }
            unset($query);
        } while (count($data) === $limit);
        $this->reset();
        return true;
    }

    /**
     * chunkById
     *
     * @param int $num
     * @param callable $callback
     * @param string $column
     * @return bool
     *
     * @see chunk
     */
    public function chunkById($num, callable $callback, $column = 'id')
    {
        $this->checkColumnName($column);

        $lastId = 0;

        do {
            $query = clone $this;

            $results = $query->where($column . ' > ?', array($lastId))
                ->orderBy($column)
                ->limit($num)
                ->findAll();

            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            $last = end($results);
            $lastId = $last[$column];

        } while ($countResults == $num);

        return true;
    }

//    /**
//     * 游标迭代处理数据库记录, 执行查询并返回 Generator
//     *
//     * 使用示例:
//     *
//     * foreach (DB::table('user')->where('status=1')->cursor() as $user) {
//     *      //
//     * }
//     *
//     * @return \Generator
//     */
//    public function cursor()
//    {
//        $sql = 'SELECT ' . static::getFieldString() . ' FROM ' . $this->table
//            . $this->getWhereString()
//            . $this->getOrderByString()
//            . $this->getLimitString();
//
//        $sql = static::replacePlaceholder($sql);
//
//        $params = $this->params;
//
//        $sql = static::appendLock($sql);
//
//        $useWritePdo = $this->useWritePdo;
//        $fetchClass = $this->fetchClass;
//
//        $this->reset();
//
//        if ($fetchClass === null) {
//            $fetchModel = array(\PDO::FETCH_ASSOC);
//        } else {
//            $fetchModel = array(\PDO::FETCH_CLASS, $fetchClass);
//        }
//
//        return static::getConnection()->cursor($sql, $params, $fetchModel, !$useWritePdo);
//    }

    /**
     * 根据SQL返回对象, 没有结果时返回null
     *
     * @param string $sql
     * @param array $params
     * @return mixed
     */
    public function findOneBySql($sql = '', $params = array())
    {
        $rows = static::findAllBySql($sql, $params);
        if (count($rows) > 0) {
            return $rows[0];
        }
        return null;
    }

    /**
     * 返回符合条件的单条数据, 没有结果时返回null
     *
     * @param string $condition
     * @param array $params
     * @return mixed
     */
    public function findOne($condition = '', $params = array())
    {
        $this->limit = 1;
        $arr = $this->findAll($condition, $params);
        if (count($arr) == 0) {
            return null;
        }
        return $arr[0];
    }

    /**
     * @param string $condition
     * @param array $params
     * @return mixed
     */
    public function findOneOrFail($condition = '', $params = array())
    {
        $data = static::findOne($condition, $params);
        if ($data == null) {
            throw new NotFoundException('Data not found.');
        }
        return $data;
    }

    /**
     * 根据主键查询, 没有结果时返回null
     *
     * @param int|array $id 主键值
     * @param string|array $primaryKeyField 主键字段
     * @return mixed
     */
    public function findByPk($id, $primaryKeyField = null)
    {
        if (empty($id)) {
            return null;
        }
        $this->wherePk($id, $primaryKeyField);
        $this->limit = 1;
        return $this->findOne();
    }

    /**
     * 根据主键查询，没有记录时，抛出异常
     *
     * @param int|array $id
     * @param string|array $primaryKeyField
     * @return mixed
     * @throws NotFoundException
     * @see findByPk
     */
    public function findByPkOrFail($id, $primaryKeyField = null)
    {
        $data = static::findByPk($id, $primaryKeyField);
        if ($data == null) {
            throw new NotFoundException('Data not found: #' . $id);
        }
        return $data;
    }

    /**
     * 执行更新操作，返回受影响行数
     *
     * @param array $data 需要更新的数据, 关联数组,key为字段名,value为对应的值, 字段名只允许字母、数字或下划线
     * @param string $condition
     * @param array $params
     * @return int
     */
    public function update(array $data, $condition = '', $params = array())
    {
        $this->where($condition, $params);

        $updatePlaceholders = array();
        foreach ($data as $name => $value) {
            static::checkColumnName($name);
            $updatePlaceholders[] = "[[$name]]" . ' = ' . self::PARAM_PREFIX . $name;
            $this->params[self::PARAM_PREFIX . $name] = $value;
        }

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $updatePlaceholders) . $this->getWhereString();
        $sql = static::replacePlaceholder($sql);

        $rowCount = static::getConnection()->execute($sql, $this->params);
        $this->reset();
        return $rowCount;
    }

    /**
     * 自增 (如果自减,传入负数即可)
     *
     * @param string $field 字段
     * @param int|float $value 自增值,默认自增1
     * @param array $data 同时更新的其它字段值
     * @return int 回受影响行数
     */
    public function increment($field, $value = 1, $data = array())
    {
        static::checkColumnName($field);

        $updatePlaceholders = array();
        foreach ($data as $name => $val) {
            static::checkColumnName($name);
            $updatePlaceholders[] = "[[$name]]" . ' = ' . self::PARAM_PREFIX . $name;
            $this->params[self::PARAM_PREFIX . $name] = $val;
        }

        $updateStr = '';
        if (count($updatePlaceholders) > 0) {
            $updateStr = ', ' . implode(', ', $updatePlaceholders);
        }

        $sql = 'UPDATE ' . $this->table . ' SET [[' . $field . ']] = [[' . $field . ']] + ' . self::PARAM_PREFIX . '_increment' . $updateStr . $this->getWhereString();
        $this->params[self::PARAM_PREFIX . '_increment'] = $value;

        $sql = static::replacePlaceholder($sql);

        $rowCount = static::getConnection()->execute($sql, $this->params);
        $this->reset();
        return $rowCount;
    }

    /**
     * 执行删除操作，返回受影响行数
     *
     * @param string $condition
     * @param array $params
     * @return int
     */
    public function delete($condition = '', $params = array())
    {
        $this->where($condition, $params);

        $sql = 'DELETE FROM ' . $this->table . $this->getWhereString();
        $sql = static::replacePlaceholder($sql);

        $rowCount = static::getConnection()->execute($sql, $this->params);
        $this->reset();
        return $rowCount;
    }

    /**
     * 加载数据库字段默认值
     *
     * @param null $entity 对象，如果为空，此方法返回数组
     * @return array|object
     */
    public function loadDefaultValues($entity = null)
    {
        $fields = static::findAllBySql('SHOW FULL FIELDS FROM ' . $this->table);
        $defaults = array_column($fields, 'Default', 'Field');

        if ($entity === null) {
            return $defaults;
        }

        foreach ($defaults as $key => $value) {
            $entity->$key = $value;
        }
        return $entity;
    }

    /**
     * 分页获取数据
     *
     * @param int $pageSize 每页数据条数
     * @return DataProvider
     */
    public function paginate($pageSize = null)
    {
        $pageConfig = array();
        if ($pageSize !== null) {
            $pageConfig['pageSize'] = $pageSize;
        }
        return new DataProvider($this, $pageConfig);
    }

    /**
     * 统计查询 count()、sum()、max()、min()、avg()
     *
     * @param $method
     * @param $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        if (!in_array(strtoupper($method), array('SUM', 'COUNT', 'MAX', 'MIN', 'AVG'))) {
            throw new Exception('Call to undefined method ' . __CLASS__ . '::' . $method . '()');
        }

        $field = isset($arguments[0]) ? $arguments[0] : '*';

        if (!($field instanceof Expression)) {

            $field = trim($field);
            if ($field !== '*') {
                if (!preg_match('/^[\w\.]+$/', $field)) {
                    throw new Exception(__CLASS__ . '::' . $method . '() 第一个参数只允许字母、数字、下划线(_)、点(.) 或 星号(*)');
                }
                $field = '[[' . $field . ']]';
            }
        }

        $method = strtoupper($method);

        $sql = 'SELECT ' . $method . '(' . $field . ') FROM ' . $this->table
            . $this->getJoinString()
            . $this->getWhereString();

        $sql = static::replacePlaceholder($sql);
        $sql = static::appendLock($sql);
        $result = static::getConnection()->queryScalar($sql, $this->params, !$this->useWritePdo);
        $this->reset();
        return $result;
    }

    /**
     * 限制查询返回记录条数
     *
     * @param int|string $limit 为string时,可以时指定offset,例如"20,10"
     * @return $this
     */
    public function limit($limit)
    {
        if (is_string($limit) && strpos($limit, ',') !== false) {
            list($this->offset, $limit) = explode(',', $limit);
        }
        $this->limit = trim($limit);
        return $this;
    }

    /**
     * 设置查询跳过记录数
     *
     * @param int $offset
     * @return $this
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * 排序
     *
     * MySQL随机排序 orderBy(new \PFinal\Database\Expression('rand()'))
     *
     * @param array|string $columns
     * @return $this
     */
    public function orderBy($columns)
    {
        $this->orderBy = $this->normalizeOrderBy($columns);
        return $this;
    }

    /**
     * 设置条件
     *
     * @param string|array $condition 条件 例如 `name=? AND status=?` 或者 `['name'=>'Ethan', 'status'=>1]`, 为数组时字段之间使用AND连接
     * @param array $params 条件中占位符对应的值。 当`$condition`为`array`时，此参数无效
     * @param bool $andWhere 重复调用`where()`时, 默认使用`AND`与已有条件连接, 此参数为`false`时, 使用`OR`连接有条件
     * @return static
     */
    public function where($condition = '', $params = array(), $andWhere = true)
    {
        if (static::isEmpty($condition)) {
            return $this;
        }

        if (is_array($condition)) {
            return $this->whereWithArray($condition, true, $andWhere);
        }

        if (is_object($params) && method_exists($params, '__toString')) {
            $params = $params->__toString();
        }

        if (!is_array($params)) {
            $params = array($params); //防止传入单个值时未使用数组类型
        }

        if (empty($this->condition)) {
            $this->condition = $condition;
            $this->params = $params;
        } else {
            $glue = $andWhere ? ' AND ' : ' OR ';
            $this->condition = '(' . $this->condition . ')' . $glue . '(' . $condition . ')';
            $this->params = array_merge($this->params, $params);
        }
        return $this;
    }

    /**
     * 主键作为条件
     *
     * @param int|array $id 主键的值
     * @param string|array $primaryKeyField 主键字段名，如果不传，则自动获取
     * @return static
     */
    public function wherePk($id, $primaryKeyField = null)
    {
        if ($primaryKeyField == null) {
            $primaryKeyField = self::primaryKeyFields();
        }

        return $this->where(array_combine((array)$primaryKeyField, (array)$id));
    }

    /**
     * inner Join
     *
     * @param string $table 表名，例如 "user as u"
     * @param string $on
     * @return $this
     */
    public function join($table, $on)
    {
        $type = 'JOIN';
        $table = self::addPrefix($table);
        $this->join[] = compact('type', 'table', 'on');
        return $this;
    }

    /**
     * left Join
     *
     * @param string $table 表名，例如 "user as u"
     * @param string $on
     * @return $this
     */
    public function leftJoin($table, $on)
    {
        $type = 'LEFT JOIN';
        $table = self::addPrefix($table);
        $this->join[] = compact('type', 'table', 'on');
        return $this;
    }

    /**
     * group by
     *
     * @param $groupBy
     * @return $this
     */
    public function groupBy($groupBy)
    {
        $this->groupBy = $groupBy;
        return $this;
    }

    /**
     * having
     *
     * @param string $having 占位符目前只支持冒号格式 例如 having('account_id > : account_id', ['account_id'>100])
     * @param array $params
     * @return $this
     */
    public function having($having, array $params = array())
    {
        //占位符目前只支持冒号格式，检测是否有问号
        if (strpos($having, '?') !== false) {
            throw new Exception('having cannot contain a question mark');
        }

        $this->having = compact('having', 'params');
        return $this;
    }

    /**
     * 主键字段
     *
     * @return array 例如 ['id']
     */
    private function primaryKeyFields()
    {
        $fields = static::schema();

        $primary = array();
        foreach ($fields as $field) {
            if ($field['Key'] === 'PRI') {
                $primary[] = $field['Field'];
            }
        }

        return $primary;
    }

    private static $schemas = array();

    /**
     * @return array
     */
    private function schema()
    {
        if (!array_key_exists($this->table, static::$schemas)) {
            static::$schemas[$this->table] = $this->getConnection()->query('SHOW FULL FIELDS FROM ' . $this->table);
        }
        return static::$schemas[$this->table];
    }

    /**
     * 设置条件(IN查询) 例如 `whereIn('id', [1, 2, 3])`
     *
     * @param string $field 字段
     * @param array $values 条件值, 索引数组
     * @param bool $andWhere 对应where()方法条三个参数
     * @return $this
     */
    public function whereIn($field, array $values, $andWhere = true)
    {
        self::checkColumnName($field);

        if (count($values) == 0) {
            //in条件为空， 给一个值为false的条件，避免查询到任何结果
            return $this->where('1 != 1');
        }

        $values = array_values($values);

        //如果不是类似 "user.id"，则加上转义 "`id`"，防止列名是关键字的情况
        if (strpos($field, '.') === false) {
            $field = '[[' . $field . ']]';
        }

        return $this->where($field . ' IN (' . rtrim(str_repeat('?,', count($values)), ',') . ')', $values, $andWhere);
    }

    /**
     * 指定查询返回的类名, 默认情况下`findAll()`以关联数组格式返回结果,调用`asEntity()`指定类名后,查询将以对象返回
     *
     * 默认情况下 `findAll()`方法返回:
     * [
     *      ['id'=>1, 'name'=>'Jack'],
     *      ['id'=>2, 'name'=>'Mary']
     * ]
     *
     * 指定返回类名之后, asEntity('User')->findAll() 方法将返回:
     *  [
     *      object(User) public 'id'=>1, 'name'=>'Jack',
     *      object(User) public 'id'=>2, 'name'=>'Mary'
     * ]
     *
     * @param $className
     * @return $this
     */
    public function asEntity($className)
    {
        $this->fetchClass = $className;
        return $this;
    }

    /**
     * 更新锁可避免行被其它共享锁修改或选取 (在事务中有效)
     *
     * @return $this
     */
    public function lockForUpdate()
    {
        $this->lockForUpdate = true;
        $this->useWritePdo();
        return $this;
    }

    /**
     * 共享锁(sharedLock) 可防止选中的数据被篡改，直到事务被提交为止 (在事务中有效)
     *
     * @return $this
     */
    public function lockInShareMode()
    {
        $this->lockInShareMode = true;
        $this->useWritePdo();
        return $this;
    }

    /**
     * @see lockInShareMode
     * @return $this
     */
    public function sharedLock()
    {
        return $this->lockInShareMode();
    }

    /**
     * 指定查询字段 推荐使用数组,例如 ['id','name','age']
     * @param array|string $field
     * @return $this
     */
    public function field($field)
    {
        $this->field = $field;
        return $this;
    }

    /**
     * 在一个 try/catch 块中执行给定的回调，如果回调用没有抛出任何异常，将自动提交事务
     *
     * 如果捕获到任何异常, 将自动回滚事务后，继续抛出异常
     *
     * @param  \Closure $callback
     * @return mixed
     *
     * @throws \Throwable
     */
    public function transaction(Closure $callback)
    {
        try {

            $this->getConnection()->beginTransaction();
            $result = $callback($this);
            $this->getConnection()->commit();
            return $result;

        } catch (\Exception $ex) {  //PHP 5.x

            $this->getConnection()->rollBack();
            throw $ex;               //回滚事务后继续向外抛出异常，让开发人员自行处理后续操作

        } catch (\Throwable $ex) {  //PHP 7

            $this->getConnection()->rollBack();
            throw $ex;
        }
    }

    /**
     * 返回sql语句，用于调试 select 语句
     *
     * @return string
     */
    public function toSql()
    {
        $sql = 'SELECT ' . static::getFieldString() . ' FROM ' . $this->table
            . $this->getJoinString()
            . $this->getWhereString()
            . $this->getGroupByString()
            . $this->getHavingByString()
            . $this->getOrderByString()
            . $this->getLimitString();

        $sql = static::replacePlaceholder($sql);
        $conn = $this->getConnection();
        $sql = $conn->parsePlaceholder($conn->quoteSql($sql), $this->params);
        $this->reset();
        return $sql;
    }

    /**
     * group by
     *
     * @return string
     */
    protected function getGroupByString()
    {
        if (static::isEmpty($this->groupBy)) {
            return '';
        }
        return ' GROUP BY ' . $this->groupBy;
    }

    /**
     * having
     *
     * @return string
     */
    protected function getHavingByString()
    {
        if (static::isEmpty($this->having)) {
            return '';
        }

        if (!static::isEmpty($this->having['params'])) {
            $this->params = array_merge($this->params, $this->having['params']);
        }

        return ' HAVING ' . $this->having['having'];
    }

    /**
     * 处理数组条件
     *
     * @param array $where
     * @param bool $andGlue 是否使用AND连接数组中的多个成员
     * @param bool $andWhere 重复调用`where()`时, 默认使用`AND`与已有条件连接, 此参数为`false`时, 使用`OR`连接有条件
     * @return $this
     */
    protected function whereWithArray(array $where, $andGlue = true, $andWhere = true)
    {
        if (static::isEmpty($where)) {
            return $this;
        }
        $params = array();
        $conditions = array();
        foreach ($where as $k => $v) {
            static::checkColumnName($k);
            $conditions[] = '[[' . $k . ']] = ?';
            $params[] = $v;
        }
        $glue = $andGlue ? ' AND ' : ' OR ';
        return $this->where(join($glue, $conditions), $params, $andWhere);
    }

    /**
     * 规范为数组格式
     *
     * @param array|string $columns
     * @return array
     */
    protected function normalizeOrderBy($columns)
    {
        if ($columns instanceof Expression) {
            return $columns;
        }

        if (is_array($columns)) {
            return $columns;
        }

        if (static::isEmpty($columns)) {
            return null;
        }

        $columns = preg_split('/\s*,\s*/', trim($columns), -1, PREG_SPLIT_NO_EMPTY);

        $result = array();
        foreach ($columns as $column) {
            if (preg_match('/^(.*?)\s+(asc|desc)$/i', $column, $matches)) {
                $result[$matches[1]] = strcasecmp($matches[2], 'desc') ? SORT_ASC : SORT_DESC;
            } else {
                $result[$column] = SORT_ASC;
            }
        }

        return $result;
    }

    /**
     * 返回 join 语句
     * @return string
     */
    protected function getJoinString()
    {
        if (static::isEmpty($this->join)) {
            return '';
        }

        $join = array();

        foreach ($this->join as $value) {
            $join[] = $value['type'] . ' ' . $value['table'] . ' ON ' . $value['on'];
        }

        return ' ' . join(' ', $join);
    }

    /**
     * 返回where部份sql
     *
     * @return string
     */
    protected function getWhereString()
    {
        return static::isEmpty($this->condition) ? '' : (' WHERE ' . $this->condition);
    }

    /**
     * 返回字段部份sql
     *
     * @return array|string
     * @throws Exception
     */
    protected function getFieldString()
    {
        $field = $this->field;

        if ($field instanceof Expression) {
            return $field;
        }

        $return = '*';
        if (!static::isEmpty($field)) {
            if (is_array($field)) {
                $return = array();
                foreach ($field as $value) {
                    $return[] = '[[' . $value . ']]';
                }
                $return = join(',', $return);
            } else {
                $return = $field;
            }
            if (!preg_match('/^[\w\s\.\,\[\]`\*]+$/', $return)) {
                throw new Exception('字段名含有不被允许的字符');//字母、数字、下划线、空白、点、星号、逗号、中括号、反引号
            }
        }
        return $return;
    }

    /**
     * 返回排序部份sql
     *
     * @return string
     */
    protected function getOrderByString()
    {
        $orderBy = $this->orderBy;
        if ($orderBy !== null) {

            if ($orderBy instanceof Expression) {
                return ' ORDER BY ' . $orderBy;
            }

            $orders = array();
            foreach ($orderBy as $name => $direction) {
                static::checkColumnName($name);
                $orders[] = $name . ($direction === SORT_DESC ? ' DESC' : '');
            }
            return ' ORDER BY ' . implode(', ', $orders);
        }
        return '';
    }

    /**
     * 返回limit部份sql
     *
     * @return string
     * @throws Exception
     */
    protected function getLimitString()
    {
        $limit = trim($this->limit);
        $offset = trim($this->offset);

        if (static::isEmpty($limit)) {
            return '';
        }

        if (static::isEmpty($offset)) {
            $offset = 0;
        }

        if (preg_match('/^\d+$/', $limit) && preg_match('/^\d+$/', $offset)) {
            if ($offset == 0) {
                return ' LIMIT ' . $limit;
            } else {
                return ' LIMIT ' . $offset . ', ' . $limit;
            }
        }
        throw new Exception("offset 或 limit 含有不被允许的字符");
    }

    /**
     * lock
     *
     * @param $sql
     * @return string
     */
    protected function appendLock($sql)
    {
        if ($this->lockForUpdate === true) {
            $sql = rtrim($sql) . ' FOR UPDATE';
        } else if ($this->lockInShareMode === true) {
            $sql = rtrim($sql) . ' LOCK IN SHARE MODE';
        }

        return $sql;
    }

    /**
     * 检查列名是否有效
     *
     * @param string $column 列名只允许字母、数字、下划线、点(.)、中杠(-)
     * @throws Exception
     */
    protected static function checkColumnName($column)
    {
        if (!preg_match('/^[\w\-\.]+$/', $column)) {
            throw new Exception('列名含有不被允许的字符');//只允许字母、数字、下划线、点(.)、中杠(-)
        }
    }

    /**
     * 统一占位符 如果同时存在问号和冒号，则将问号参数转为冒号
     *
     * @param $sql
     * @return string
     */
    protected function replacePlaceholder($sql)
    {
        static $staticCount = 0;
        if (strpos($sql, '?') !== false && strpos($sql, ':') !== false) {
            $count = substr_count($sql, '?');
            for ($i = 0; $i < $count; $i++) {
                $num = $i + $staticCount;
                $staticCount++;
                $sql = preg_replace('/\?/', static::PARAM_PREFIX . $num, $sql, 1);
                $this->params[static::PARAM_PREFIX . $num] = $this->params[$i];
                unset($this->params[$i]);
            }
        }
        return $sql;
    }

    /**
     * 检查是否为空 以下值: null、''、空数组、空白字符("\t"、"\n"、"\r"等) 被为认为是空值
     *
     * @param mixed $value
     * @return boolean
     */
    protected static function isEmpty($value)
    {
        return $value === '' || $value === array() || $value === null || is_string($value) && trim($value) === '';
    }

    /**
     * 在查询操作中，默认使用从库，调用此方法后，将强制使用主库做查询
     *
     * @return $this
     */
    public function useWritePdo()
    {
        $this->useWritePdo = true;
        return $this;
    }

    protected $afterFind;

    /**
     * 查询之后的处理函数，对每个查询得到的结果应用此函数
     *
     * @param $callback
     * @return $this
     * @throws Exception
     */
    public function afterFind($callback)
    {
        if (!is_callable($callback)) {
            throw new Exception('$callback is not a callable');
        }
        $this->afterFind = $callback;

        return $this;
    }

    /**
     * 清空所有条件
     */
    protected function reset()
    {
        $this->table = null;
        $this->fetchClass = null;
        $this->orderBy = null;
        $this->field = null;
        $this->limit = null;
        $this->offset = null;
        $this->condition = null;
        $this->params = array();
        $this->lockForUpdate = null;
        $this->lockInShareMode = null;
        $this->useWritePdo = null;
        $this->afterFind = null;
        $this->join = array();
        $this->groupBy = null;
        $this->having = null;
    }
}