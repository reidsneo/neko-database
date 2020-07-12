<?php namespace Neko\Database\Connectors;

/**
 * Class ConnectionFactory
 * @package Neko\Database\Connectors
 *
 * Build a connection from a config with a format like:
 *
 *
 * <code>
 *  array(
 *      'read' => array(
 *          'host' => '192.168.1.1',
 *      ),
 *      'write' => array(
 *          'host' => '196.168.1.2'
 *      ),
 *      'driver'    => 'mysql',
 *      'database'  => 'database',
 *      'username'  => 'root',
 *      'password'  => '',
 *      'charset'   => 'utf8',
 *      'collation' => 'utf8_unicode_ci',
 *      'prefix'    => '',
 *      'lazy'      => true/false
 *  )
 * </code>
 */
interface ConnectionFactoryInterface
{
    /**
     * Establish a PDO connection based on the configuration.
     *
     * @param  array $config
     * @return \Neko\Database\Connection
     */
    public function make(array $config);
}
