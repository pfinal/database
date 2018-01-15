<?php

namespace PFinal\Database\Relations;

use Leaf\Util;
use PFinal\Database\Builder;

class HasOne extends Builder
{
    public $foreignKey = null;
    public $localKey;
    public $localValue;

    public function __invoke()
    {
        $this->where([$this->foreignKey => $this->localValue]);

        return $this->findOne();
    }

    public function appendData($models, $name)
    {
        $ids = Util::arrayColumn($models, $this->localKey);
        $ids = array_unique($ids);

        $this->whereIn($this->foreignKey, $ids);

        $relationData = $this->findAll();

        $relationData = Util::arrayColumn($relationData, null, $this->foreignKey);

        foreach ($models as $k => $v) {
            $models[$k][$name] = isset($relationData[$v[$this->localKey]]) ? $relationData[$v[$this->localKey]] : null;
        }
    }
}