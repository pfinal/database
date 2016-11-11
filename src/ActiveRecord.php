<?php

namespace PFinal\Database;

class ActiveRecord extends Builder
{
    public function __construct(array $config = array())
    {
        parent::__construct($config);

        if (static::$modelStorage === null) {
            static::$modelStorage = new \SplObjectStorage();
        }
        $this->table(static::tableName());
    }

    public static function tableName()
    {
        $name = get_called_class();

        //去掉namespace
        $name = rtrim(str_replace('\\', '/', $name), '/\\');
        if (($pos = mb_strrpos($name, '/')) !== false) {
            $name = mb_substr($name, $pos + 1);
        }

        //大写转为下划线风格
        return trim(strtolower(preg_replace('/[A-Z]/', '_\0', $name)), '_');
    }

    /**
     * @param Connection $db
     * @return static
     */
    public static function find($db)
    {
        $model = new static;
        return $model->setConnection($db)->asEntity(get_called_class());
    }

    public function one()
    {
        return parent::findOne();
    }

    public function all()
    {
        return parent::findAll();
    }

    /**
     * @var \SplObjectStorage
     */
    protected static $modelStorage = null;

    public function findAllBySql($sql = '', $params = array(), $fetchClass = null)
    {
        if ($fetchClass === null) {
            return parent::findAllBySql($sql, $params, $fetchClass);
        } else {

            $models = parent::findAllBySql($sql, $params, $fetchClass);

            array_walk($models, function (&$model) {
                static::$modelStorage->attach($model, serialize((array)$model));
            });

            return $models;
        }
    }

    public function isNewRecord()
    {
        return !static::$modelStorage->contains($this);
    }

    /**
     * @return bool
     */
    public function save()
    {
        if ($this->isNewRecord()) {
            if (($id = $this->insertGetId((array)$this)) > 0) {
                $this->id = $id;//todo pk
                return true;
            }
            return false;
        } else {

            $pk = static::$modelStorage->offsetGet($this);

            return 1 == $this->where('id=?', array($pk))->update((array)$this);//todo pk
        }
    }
}