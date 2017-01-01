<?php

namespace PFinal\Database;

/**
 * 数据库操作辅助类
 *
 * @method int count($field = '*')
 * @method string sum($field)
 * @method mixed max($field)
 * @method mixed min($field)
 * @method string avg($field)
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
    protected $useWritePdo = false;

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
     * @param string $tableName
     * @return static
     * @throws Exception
     */
    public function table($tableName = '')
    {
        if (!empty($tableName)) {
            if (strpos($tableName, '{{') === false) {
                $tableName = '{{%' . $tableName . '}}';
            }
            if (!preg_match('/^\{\{%?[\w\-\.\$]+%?\}\}$/', $tableName)) {
                throw new Exception('表名错误');
            }
        }
        $this->table = $tableName;
        return $this;
    }

    /**
     * 执行新增,返回受影响行数
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
     * 根据SQL查询，返回符合条件的所有数据
     *
     * @param string $sql
     * @param array $params
     * @return array|object[]
     */
    public function findAllBySql($sql = '', $params = array())
    {
        $sql = static::appendLock($sql);

        $useWritePdo = $this->useWritePdo;
        $fetchClass = $this->fetchClass;

        $this->reset();

        if ($fetchClass === null) {
            $fetchModel = array(\PDO::FETCH_ASSOC);
        } else {
            $fetchModel = array(\PDO::FETCH_CLASS, $fetchClass);
        }

        return static::getConnection()->query($sql, $params, $fetchModel, !$useWritePdo);
    }

    /**
     *
     * 返回符合条件的所有数据
     *
     * @param string $condition
     * @param array $params
     * @return array|object[]
     */
    public function findAll($condition = '', $params = array())
    {
        $this->where($condition, $params);

        $sql = 'SELECT ' . static::getFieldString() . ' FROM ' . $this->table
            . $this->getWhereString()
            . $this->getOrderByString()
            . $this->getLimitString();

        $sql = static::replacePlaceholder($sql);
        return static::findAllBySql($sql, $this->params);
    }

    /**
     * 根据SQL返回对象
     * @param string $sql
     * @param array $params
     * @return array|object|null
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
     *
     * 返回符合条件的单条数据
     *
     * @param string $condition
     * @param array $params
     * @return array|object|null
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
     * 根据主键查询
     *
     * @param int $pk 主键值，不支持复合主键
     * @param string $primaryKeyField 主键字段,默认为`id`
     * @return array|object|mixed|null
     */
    public function findByPk($pk, $primaryKeyField = 'id')
    {
        static::checkColumnName($primaryKeyField);
        $this->where(array($primaryKeyField => $pk));
        $this->limit = 1;
        return $this->findOne();
    }

    /**
     * 执行更新操作，返回受影响行数
     * @param array $data 需要更新的数据, 关联数组,key为字段名,value为对应的值, 字段名只允许字母、数字或下划线
     * @param string $condition
     * @param array $params
     * @return int
     */
    public function update(array $data, $condition = '', $params = array())
    {
        $this->where($condition, $params);

        $updatePlaceholders = [];
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
     * 自增。如果自减,传入负数即可
     * @param string $field 字段
     * @param int $value 自增值,默认自增1
     * @return int 回受影响行数
     */
    public function increment($field, $value = 1, $condition = '', $params = array())
    {
        static::checkColumnName($field);
        $this->where($condition, $params);

        $sql = 'UPDATE ' . $this->table . ' SET [[' . $field . ']] = [[' . $field . ']] + (' . intval($value) . ')' . $this->getWhereString();
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
     * 统计查询 count()、sum()、max()、min()、avg()
     *
     * @param $method
     * @param $arguments
     * @return mixed
     * @throws Exception
     */
    public function __call($method, $arguments)
    {
        if (!in_array(strtoupper($method), array('SUM', 'COUNT', 'MAX', 'MIN', 'AVG'))) {
            throw new Exception('Call to undefined method ' . __CLASS__ . '::' . $method . '()');
        }

        $field = isset($arguments[0]) ? $arguments[0] : (static::isEmpty($this->field) ? '*' : $this->field);
        $field = trim($field);

        if ($field !== '*') {
            if (!preg_match('/^[\w\.]+$/', $field)) {
                throw new Exception(__CLASS__ . '::' . $method . '() 第一个参数只允许字母、数字、下划线(_)、点(.) 或 星号(*)');
            }
            $field = '[[' . $field . ']]';
        }

        $method = strtoupper($method);

        $sql = 'SELECT ' . $method . '(' . $field . ') FROM ' . $this->table . $this->getWhereString();
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
            return $this->whereWithArray($condition, 'AND', $andWhere);
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
     * 设置条件(IN查询) 例如 `whereIn('id', [1, 2, 3])`
     *
     * @param string $field 字段
     * @param array $values 条件值, 索引数组
     * @param bool $andWhere 对应where()方法条三个参数
     * @return $this
     * @throws Exception
     */
    public function whereIn($field, array $values, $andWhere = true)
    {
        self::checkColumnName($field);

        //in条件为空时，无法确定作为无条件处理(返回全部数据)，还是不匹配任一记录
        if (count($values) == 0) {
            throw new Exception(__CLASS__ . '::whereIn() 第二个参数不能为空数组');
        }

        $values = array_values($values);
        $this->where('[[' . $field . ']] IN (' . rtrim(str_repeat('?,', count($values)), ',') . ')', $values, $andWhere);
        return $this;
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
     * FOR UPDATE 在事务中有效
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
        $return = '*';
        if (!static::isEmpty($field)) {
            if (is_array($field)) {
                $return = array();
                foreach ($field as $value) {
                    $return[] = '[[' . $value . ']]';
                }
                $return = join(',', $return);
            }
            if (!preg_match('/^[\w\s\.\,\[\]`\*]+$/', $return)) {
                throw new Exception(__CLASS__ . '::field() 含有不安全的字符');//字母、数字、下划线、空白、点、星号、逗号、中括号、反引号
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

            $columns = $orderBy;
            $orders = array();
            foreach ($columns as $name => $direction) {
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
        throw  new Exception("offset or limit 包含非法字符");
    }

    /**
     * lockForUpdate
     *
     * @param $sql
     * @return string
     */
    protected function appendLock($sql)
    {
        if ($this->lockForUpdate === true) {
            $sql = rtrim($sql) . ' FOR UPDATE';
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
            throw new Exception('列名只允许字母、数字、下划线、点(.)、中杠(-)');
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
        $this->lockForUpdate = false;
        $this->useWritePdo = null;
    }
}