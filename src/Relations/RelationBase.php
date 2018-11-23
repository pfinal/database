<?php

namespace PFinal\Database\Relations;

use PFinal\Database\Builder;

class RelationBase extends Builder
{
    public static function appendRelationData($models, array $relations)
    {
        if (count($models) > 0) {
            foreach ($relations as $relation) {

                $ind = strpos($relation, '.');
                if ($ind !== false) {
                    $methodName = substr($relation, 0, $ind);
                    $nextMethodName = substr($relation, $ind + 1);
                } else {
                    $methodName = $relation;
                    $nextMethodName = [];
                }

                $relationObj = call_user_func([$models[0], $methodName]);
                $relationObj->appendData($models, $methodName, $nextMethodName);
            }
        }
    }
}