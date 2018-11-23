<?php

namespace PFinal\Database\Relations;

use Leaf\Util;

class HasOne extends RelationBase
{
    public $foreignKey = null;
    public $localKey;
    public $localValue;

    public function __invoke()
    {
        $this->where([$this->foreignKey => $this->localValue]);

        return $this->findOne();
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

        $relationData = Util::arrayColumn($relationData, null, $this->foreignKey);

        foreach ($models as $k => $v) {
            $models[$k][$name] = isset($relationData[$v[$this->localKey]]) ? $relationData[$v[$this->localKey]] : null;
        }
    }
}