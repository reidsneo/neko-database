<?php namespace Neko\Database\ConnectionAdapters;

abstract class BaseAdapter
{
    /**
     * @var \Neko\Facade\Container
     */
    protected $container;

    /**
     * @param \Neko\Facade\Container $container
     */
    public function __construct(\Neko\Facade\Container $container)
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
