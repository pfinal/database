<?php

namespace PFinal\Database\Relations;

use Leaf\Util;

class HasMany extends RelationBase
{
    public $foreignKey = null;
    public $localKey;
    public $localValue;

    public function __invoke()
    {
        $this->where([$this->foreignKey => $this->localValue]);

        return $this->findAll();
    }

    public function appendData(array $models, $name, $relations = [])
    {
        if (count($models) == 0) {
            return;
        }

        $relations = (array)$relations;

        if (isset($models[0]->$name) && count($relations) > 0) {
            self::appendRelationData(array_column($models, $name), $relations);
            return;
        }

        $ids = Util::arrayColumn($models, $this->localKey);
        $ids = array_unique($ids);

        $this->whereIn($this->foreignKey, $ids);

        $relationData = $this->findAll();
        if (count($relations) > 0) {
            $this->appendRelationData($relationData, $relations);
        }

        foreach ($models as $k => $model) {
            $models[$k][$name] = [];
        }

        $models = Util::arrayColumn($models, null, $this->localKey);

        foreach ($relationData as $v) {
            $id = $v[$this->foreignKey];

            //PHP 7.1.16
            //ErrorException Indirect modification of overloaded element of XXX has no effect
            //$models[$id][$name][] = $v;

            $temp = $models[$id][$name];
            $temp[] = $v;
            $models[$id][$name] = $temp;
        }
    }
}