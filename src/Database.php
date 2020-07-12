<?php
namespace Neko\Database;

use Neko\Database\ConnectionInterface;
use Neko\Database\Connectors\ConnectionFactory;
use PDO;

/**
 * Main database class.
 *
 * @method static array                                          fetch( $query, array $bindings = [] )
 * @method static array                                          fetchAll( $query, array $bindings = [] )
 * @method static mixed                                          fetchOne( $query, array $bindings = [] )
 * @method static int|false                                      query( $query, array $bindings = [] )
 * @method static mixed                                          transaction( \Closure $callback )
 * @method static array                                          pretend( \Closure $callback )
 * @method static bool                                           pretending()
 * @method static \Psr\Log\LoggerInterface|\Database\QueryLogger getLogger()
 * @method static bool                                           logging()
 * @method static \Awethemes\Database\Connection                 enableQueryLog()
 * @method static \Awethemes\Database\Connection                 disableQueryLog()
 * @method static \Awethemes\Database\Builder                    newQuery()
 *
 * @package Awethemes\WP_Object\Database
 */
class DB {
	/**
	 * The database connection.
	 *
	 * @var \Awethemes\Database\Connection
	 */
	protected static $connection;
	

	/**
	 * Begin a fluent query against a database table.
	 *
	 * @param  string $table
	 *
	 * @return \Awethemes\Database\Builder
	 */
	public static function table( $table ) {
		return static::getConnection()->table( $table );
	}

	/**
	 * Run a select statement against the database.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return array
	 */
	public static function select( $query, array $bindings = [] ) {
		return static::getConnection()->fetchAll( $query, $bindings );
	}

	public static function connection($constring) {
		global $app;
		//$app->dd($app->config['db'][$constring]);
		$factory = new \Neko\Database\Connectors\ConnectionFactory();
		$con = $factory->make($app->config['db'][$constring]);
		return $con;
	}

	/**
	 * Get the connection instance.
	 *
	 * @return \Database\ConnectionInterface|\Awethemes\Database\Connection
	 */
	public static function getConnection() {
		global $pdo;


		if ( is_null( static::$connection ) ) {
			static::$connection = new Connection( $pdo );
		}

		return static::$connection;
	}

	/**
	 * Set the connection implementation.
	 *
	 * @param \Database\ConnectionInterface $connection
	 */
	public static function setConnection($constr) {
		global $app;
		$factory = new \Neko\Database\Connectors\ConnectionFactory();
		$con = $factory->make($app->config['db'][$constr]);
		static::$connection = $con;
	}

	/**
	 * Handle forward call connection methods.
	 *
	 * @param string $name
	 * @param array  $arguments
	 *
	 * @return mixed
	 */
	public static function __callStatic( $name, $arguments ) {
		if ( method_exists( $connection = static::getConnection(), $name ) ) {
			return $connection->{$name}( ...$arguments );
		}

		throw new \BadMethodCallException( 'Method [' . $name . '] in class [' . get_class( $connection ) . '] does not exist.' );
	}
}
