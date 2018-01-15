<?php

namespace PFinal\Database\Relations;

use Leaf\Util;
use PFinal\Database\Builder;

class BelongsTo extends Builder
{
    public $foreignKey = null;
    public $ownerKey;
    public $foreignValue;

    public function __invoke()
    {
        $this->where([$this->ownerKey => $this->foreignValue]);

        return $this->findOne();
    }

    public function appendData($models, $name)
    {
        $ids = Util::arrayColumn($models, $this->foreignKey);
        $ids = array_unique($ids);

        $this->whereIn($this->ownerKey, $ids);
        $relationData = $this->findAll();

        $relationData = Util::arrayColumn($relationData, null, $this->ownerKey);

        foreach ($models as $k => $v) {
            $models[$k][$name] = isset($relationData[$v[$this->foreignKey]]) ? $relationData[$v[$this->foreignKey]] : null;
        }
    }
}