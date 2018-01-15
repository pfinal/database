<?php

namespace PFinal\Database;

use PFinal\Database\Relations\BelongsTo;
use PFinal\Database\Relations\HasMany;
use PFinal\Database\Relations\HasOne;
use Leaf\DB;

/**
 * 模型
 *
 * @method static Builder where($condition = '', $params = array())
 * @method static Builder wherePk($id)
 * @method static Builder whereIn($field, $array)
 * @method static static findByPk($id)
 * @method static static findByPkOrFail($id)
 * @method static static findOne($condition = '', $params = array())
 * @method static static findOneBySql($sql, $params = array())
 * @method static array findAll($condition = '', $params = array())
 * @method static array findAllBySql($sql, $params = array())
 * @method static DataProvider paginate($pageSize = null)
 * @method static int count($field = '*')
 * @method static Builder useWritePdo()
 * @method static Builder orderBy($columns)
 * @method static boolean chunk($num, $callback)
 *
 * @method static bool insert(array $data);
 * @method static int insertGetId(array $data);
 *
 * @see http://manual.phpdoc.org/HTMLSmartyConverter/HandS/phpDocumentor/tutorial_phpDocumentor.pkg.html
 */
trait ModelTrait
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%' . self::getTableName(get_called_class()) . '}}';
    }

    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array([DB::table(static::tableName())->asEntity(static::className()), $name], $arguments);
    }

    public function loadDefaultValues()
    {
        return DB::table(static::tableName())->loadDefaultValues($this);
    }

    private static function getTableName($className)
    {
        //去掉namespace
        $name = rtrim(str_replace('\\', '/', $className), '/\\');
        if (($pos = mb_strrpos($name, '/')) !== false) {
            $name = mb_substr($name, $pos + 1);
        }

        //大写转为下划线风格
        $name = trim(strtolower(preg_replace('/[A-Z]/', '_\0', $name)), '_');

        return $name;
    }

    /**
     * 一对一
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $localKey
     * @return $this
     */
    public function hasOne($related, $foreignKey = null, $localKey = 'id')
    {
        if ($foreignKey === null) {
            $foreignKey = self::getTableName(get_called_class()) . '_id';
        }

        $hasOne = new HasOne();
        $hasOne->setConnection(DB::getConnection());

        $obj = $hasOne->table($related::tableName())->asEntity($related);

        $obj->foreignKey = $foreignKey;
        $obj->localKey = $localKey;
        $obj->localValue = $this->{$localKey};

        return $obj;
    }

    /**
     * 从属关联
     *
     * @param string $related
     * @param null $foreignKey
     * @param string $localKey
     * @return $this
     */
    public function belongsTo($related, $foreignKey = null, $ownerKey = 'id')
    {
        if ($foreignKey === null) {
            $foreignKey = self::getTableName($related) . '_id';
        }

        $hasOne = new BelongsTo();
        $hasOne->setConnection(DB::getConnection());

        $obj = $hasOne->table($related::tableName())->asEntity($related);

        $obj->ownerKey = $ownerKey;
        $obj->foreignKey = $foreignKey;
        $obj->foreignValue = $this->{$foreignKey};

        return $obj;
    }

    /**
     * hasMany
     *
     * @param string $related
     * @param null $foreignKey
     * @param string $localKey
     * @return $this
     */
    public function hasMany($related, $foreignKey = null, $localKey = 'id')
    {
        if ($foreignKey === null) {
            $foreignKey = self::getTableName(get_called_class()) . '_id';
        }

        $hasOne = new HasMany();
        $hasOne->setConnection(DB::getConnection());

        $obj = $hasOne->table($related::tableName())->asEntity($related);

        $obj->foreignKey = $foreignKey;
        $obj->localKey = $localKey;
        $obj->localValue = $this->{$localKey};

        return $obj;
    }

    /**
     * 渴求式加载
     *
     * @param  string|array $relations 关联名称
     */
    public static function with($relations)
    {
        $relations = is_string($relations) ? func_get_args() : $relations;

        return DB::table(static::tableName())->asEntity(static::className())->afterFind(function ($models) use ($relations) {
            if (count($models) > 0) {
                foreach ($relations as $relation) {
                    $relation = call_user_func([$models[0], $relation]);
                    $relation->appendData($models, $relation);
                }
            }
        });
    }

    public function __get($getter)
    {
        if (!method_exists($this, $getter)) {
            return parent::__get($getter);
        }

        $relation = $this->$getter();

        return call_user_func($relation);
    }

}