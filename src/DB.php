<?php
namespace Neko\Database;

use Neko\Database\Connectors\ConnectionFactory;
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
 * @method static \Database\QueryLogger getLogger()
 * @method static bool                                           logging()
 * @method static \Neko\Database\Connection						enableQueryLog()
 * @method static \Neko\Database\Connection						disableQueryLog()
 * @method static \Neko\Database\Builder						newQuery()
 *
 * @package \Neko\Database
 */
class DB {
	/**
	 * The database connection.
	 *
	 * @var \Neko\Database\Connection
	 */
	protected static $connection;
	

	/**
	 * Begin a fluent query against a database table.
	 *
	 * @param  string $table
	 *
	 * @return \Neko\Database\Builder
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

	public static function connection($con_string) {
		global $app;
		if($con_string == null)
		{
			$con_string = $app->config['db']["default"];
		}else{
			$con_string = $app->config['db'][$con_string];
		}

		$factory = new ConnectionFactory();
		$con = $factory->make($con_string);
		return $con;
	}

	/**
	 * Get the connection instance.
	 *
	 * @return \Database\ConnectionInterface|\Neko\Database\Connection
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
	public static function setConnection($con_string) {
		global $app;
		$factory = new ConnectionFactory();

		if($con_string == null)
		{
			$con_string = $app->config['db']["default"];
		}else{
			$con_string = $app->config['db'][$con_string];
		}

		$con = $factory->make($con_string);
		$logging = (isset($con_string['logging']) && $con_string['logging']!==false) ? true : false;		
		$con->enableQueryLog($logging);
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
