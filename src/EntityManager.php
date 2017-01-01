<?php

namespace PFinal\Database;

class EntityManager extends Builder
{
    /**
     * @var \SplObjectStorage
     */
    public static $modelStorage;

    public function __construct(array $config = array())
    {
        parent::__construct($config);

        static::$modelStorage = new \SplObjectStorage();
    }

    protected function getTableName($class)
    {
        if (method_exists($class, 'tableName')) {
            return call_user_func($class . '::tableName');
        }

        //去掉namespace
        $name = rtrim(str_replace('\\', '/', $class), '/\\');
        if (($pos = mb_strrpos($name, '/')) !== false) {
            $name = mb_substr($name, $pos + 1);
        }

        //大写转为下划线风格
        return trim(strtolower(preg_replace('/[A-Z]/', '_\0', $name)), '_');
    }

    public function entity($class)
    {
        $this->table($this->getTableName($class));
        return parent::asEntity($class);
    }

    /**
     * @return bool
     */
    public function save($entity)
    {
        if (method_exists($entity, 'beforeSave')) {
            if (!call_user_func(array($entity, 'beforeSave'))) {
                return false;
            }
        }

        if ($this->isNewRecord($entity)) {

            //$entity->created_at = $entity->updated_at = time();

            if (($id = $this->table($this->getTableName(get_class($entity)))->insertGetId((array)$entity)) > 0) {

                $field = $this->getAutoIncrementField();
                if ($field != null) {
                    $entity->$field = $id;
                }
                return true;
            }
            return false;

        } else {

            //$entity->updated_at = time();

            $original = unserialize(static::$modelStorage->offsetGet($entity));

            // 提取修改部份数据
            $data = array_diff((array)$entity, $original);
            if (count($data) == 0) {
                return false;
            }

            return 1 == $this->table($this->getTableName(get_class($entity)))->where($this->getPkWhere($original))->update($data);
        }
    }

    /**
     * @param object $entity
     * @return bool
     */
    public function remove($entity)
    {
        $original = unserialize(static::$modelStorage->offsetGet($entity));
        return 1 == $this->table($this->getTableName(get_class($entity)))->where($this->getPkWhere($original))->delete();
    }

    public function isNewRecord($entity)
    {
        return !static::$modelStorage->contains($entity);
    }

    public function findAllBySql($sql = '', $params = array())
    {
        $models = parent::findAllBySql($sql, $params);

        array_walk($models, function (&$model) {
            if (is_object($model)) {
                static::$modelStorage->attach($model, serialize((array)$model));
            }
        });
        return $models;
    }

    private function getPkWhere(array $attributes)
    {
        $return = array();
        foreach ($this->queryPrimaryKeyFields() as $field) {
            if (array_key_exists($field, $attributes)) {
                $return[$field] = $attributes[$field];
            }
        }

        return $return;
    }

    /**
     * 查询主键字段
     * @return array
     * @throws Exception
     */
    private function queryPrimaryKeyFields()
    {
        $fields = static::schema($this->table);

        $primary = [];
        foreach ($fields as $field) {
            if ($field['Key'] === 'PRI') {
                $primary[] = $field['Field'];
            }
        }

        if (count($primary) == 0) {
            throw new Exception('没有主键字段');
        }

        return $primary;
    }

    /**
     * 查询自增字段
     * @return string | null
     */
    private function getAutoIncrementField()
    {
        foreach (static::schema($this->table) as $field) {
            if (stripos($field['Extra'], 'auto_increment') !== false) {
                return (string)$field['Field'];
            }
        }
    }

    private static $schemas = array();

    /**
     * @param string $tableName
     * @return array
     */
    private function schema($tableName)
    {
        if (!array_key_exists($tableName, static::$schemas)) {
            static::$schemas[$tableName] = static::getConnection()->query('SHOW FULL FIELDS FROM ' . $tableName);
        }
        return static::$schemas[$tableName];
    }

    /**
     * 加载数据库字段默认值
     * @param object $entity
     * @return object
     */
    public function loadDefaultValues($entity = null)
    {
        $fields = static::findAllBySql('SHOW FULL FIELDS FROM ' . $this->getTableName(get_class($entity)));
        $defaults = array_column($fields, 'Default', 'Field');

        foreach ($defaults as $key => $value) {
            $entity->$key = $value;
        }
        return $entity;
    }
}
