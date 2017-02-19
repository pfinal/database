<?php

namespace PFinal\Database;

use Leaf\Application;
use Leaf\Pagination;

/**
 * 数据提供者
 */
class DataProvider implements \JsonSerializable, \ArrayAccess, \Iterator
{
    /** @var  $page Pagination */
    protected $page;
    /** @var  $query Builder */
    protected $query;

    private $data;

    /**
     * @param \PFinal\Database\Builder $query
     */
    public function __construct($query)
    {
        $this->query = $query;
    }

    /**
     * @return array
     */
    public function getData()
    {
        $this->fillData();
        return $this->data;
    }

    /**
     * @return Pagination
     */
    public function getPage()
    {
        if ($this->page === null) {

            $this->page = Application::$app->make('Leaf\\Pagination');

            $countQuery = clone $this->query;
            $this->page->itemCount = $countQuery->count();
        }

        return $this->page;
    }

    /**
     * 分页按扭
     *
     * @param string $baseUrl
     * @param null $prefix
     * @param null $suffix
     * @return string
     */
    public function createLinks($baseUrl = '', $prefix = null, $suffix = null)
    {
        return $this->getPage()->createLinks($baseUrl, $prefix, $suffix);
    }

    /**
     * @return array
     */
    public function jsonSerialize()
    {
        return array(
            'page' => $this->getPage(),
            'data' => $this->getData(),
        );
    }

    private function fillData()
    {
        if ($this->data === null) {
            $this->data = $this->query->limit($this->getPage()->limit)->findAll();
        }
    }

    public function offsetExists($offset)
    {
        $this->fillData();
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset)
    {
        $this->fillData();
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->fillData();
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        $this->fillData();
        unset($this->data[$offset]);
    }

    private $position = 0;

    public function current()
    {
        $this->fillData();
        return $this->data[$this->position];
    }

    public function next()
    {
        ++$this->position;
    }

    public function key()
    {
        return $this->position;
    }

    public function valid()
    {
        $this->fillData();
        return isset($this->data[$this->position]);
    }

    public function rewind()
    {
        $this->position = 0;
    }
}