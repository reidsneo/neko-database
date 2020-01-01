<?php namespace Neko\Database\QueryBuilder\Adapters;

class Pgsql extends BaseAdapter
{
    /**
     * @var string
     */
    protected $sanitizer = '"';
}
