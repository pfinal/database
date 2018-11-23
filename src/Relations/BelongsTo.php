<?php

namespace PFinal\Database\Relations;

use Leaf\Util;

class BelongsTo extends RelationBase
{
    public $foreignKey = null;
    public $ownerKey;
    public $foreignValue;

    public function __invoke()
    {
        $this->where([$this->ownerKey => $this->foreignValue]);

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

        $ids = Util::arrayColumn($models, $this->foreignKey);
        $ids = array_unique($ids);

        $this->whereIn($this->ownerKey, $ids);
        $relationData = $this->findAll();
        if (count($relations) > 0) {
            $this->appendRelationData($relationData, $relations);
        }

        $relationData = Util::arrayColumn($relationData, null, $this->ownerKey);

        foreach ($models as $k => $v) {
            $models[$k][$name] = isset($relationData[$v[$this->foreignKey]]) ? $relationData[$v[$this->foreignKey]] : null;
        }
    }
}