<?php namespace Neko\Database\ConnectionAdapters;

abstract class BaseAdapter
{
    /**
     * @var \Neko\Database\Containers
     */
    protected $container;

    /**
     * @param \Neko\Database\Containers $container
     */
    public function __construct(\Neko\Database\Container\Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param $config
     *
     * @return \PDO
     */
    public function connect($config)
    {
        if (!isset($config['options'])) {
            $config['options'] = array();
        }
        return $this->doConnect($config);
    }

    /**
     * @param $config
     *
     * @return mixed
     */
    abstract protected function doConnect($config);
}
