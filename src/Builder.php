<?php
namespace Neko\Database;
use Neko\Framework\Util\Arr;
use Neko\Framework\Util\Str;

class Builder extends \Neko\Database\Query\Builder {
	/**
	 * Alias to set the "offset" value of the query.
	 *
	 * @param  int $value
	 * @return $this
	 */
	public function skip( $value ) {
		return $this->offset( $value );
	}

	/**
	 * Alias to set the "limit" value of the query.
	 *
	 * @param  int $value
	 * @return $this
	 */
	public function take( $value ) {
		return $this->limit( $value );
	}

	/**
	 * Execute the query as a "select" statement.
	 *
	 * @param  array $columns
	 * @return array
	 */
	public function get( $columns = [ '*' ] ) {
		return $this->onceWithColumns( $columns, function () {
			return $this->connection->fetchAll( $this->toSql(), $this->getBindings() );
		} );
	}

	/**
	 * Insert a new record into the database.
	 *
	 * @param  array $values
	 * @return bool
	 */
	public function insert( array $values ) {
		if ( empty( $values ) ) {
			return false;
		}

		return (bool) parent::insert( $values );
	}

	/**
	 * Update a record in the database.
	 *
	 * @param  array $values
	 * @return int
	 */
	public function update( array $values ) {
		return parent::update( $values );
	}

	/**
	 * Increment a column's value by a given amount.
	 *
	 * @param  string $column
	 * @param  int    $amount
	 * @param  array  $extra
	 * @return int
	 */
	public function increment( $column, $amount = 1, array $extra = [] ) {
		if ( ! is_numeric( $amount ) ) {
			throw new \InvalidArgumentException( 'Non-numeric value passed to increment method.' );
		}

		return (int) parent::increment( $column, $amount, $extra );
	}

	/**
	 * Decrement a column's value by a given amount.
	 *
	 * @param  string $column
	 * @param  int    $amount
	 * @param  array  $extra
	 * @return int
	 */
	public function decrement( $column, $amount = 1, array $extra = [] ) {
		if ( ! is_numeric( $amount ) ) {
			throw new \InvalidArgumentException( 'Non-numeric value passed to decrement method.' );
		}

		return (int) parent::decrement( $column, $amount, $extra );
	}

	/**
	 * Delete a record from the database.
	 *
	 * @param  mixed $id
	 * @return int
	 */
	public function delete( $id = null ) {
		return (int) parent::delete( $id );
	}

	/**
	 * Execute a callback over each item while chunking.
	 *
	 * @param  callable $callback
	 * @param  int      $count
	 * @return bool
	 */
	public function each( callable $callback, $count = 1000 ) {
		return $this->chunk( $count, function ( $results ) use ( $callback ) {
			foreach ( $results as $key => $value ) {
				if ( $callback( $value, $key ) === false ) {
					return false;
				}
			}

			return true;
		} );
	}

	/**
	 * Chunk the results of the query.
	 *
	 * @param  int      $count
	 * @param  callable $callback
	 * @return bool
	 */
	public function chunk( $count, callable $callback ) {
		$this->enforceOrderBy();

		$page = 1;

		do {
			// We'll execute the query for the given page and get the results. If there are
			// no results we can just break and return from here. When there are results
			// we will call the callback with the current chunk of these results here.
			$results = $this->forPage( $page, $count )->get();

			$countResults = count( $results );

			if ( 0 === $countResults ) {
				break;
			}

			// On each chunk result set, we will pass them to the callback and then let the
			// developer take care of everything within the callback, which allows us to
			// keep the memory low for spinning through large result sets for working.
			if ( $callback( $results, $page ) === false ) {
				return false;
			}

			unset( $results );

			$page ++;
		} while ( $countResults === $count );

		return true;
	}

	/**
	 * Throw an exception if the query doesn't have an orderBy clause.
	 *
	 * @return void
	 * @throws \RuntimeException
	 */
	protected function enforceOrderBy() {
		if ( empty( $this->orders ) ) {
			throw new \RuntimeException( 'You must specify an orderBy clause when using this function.' );
		}
	}

	/**
	 * Execute the given callback while selecting the given columns.
	 *
	 * After running the callback, the columns are reset to the original value.
	 *
	 * @param  array    $columns
	 * @param  callable $callback
	 *
	 * @return mixed
	 */
	protected function onceWithColumns( $columns, $callback ) {
		$original = $this->columns;

		if ( is_null( $original ) ) {
			$this->columns = $columns;
		}

		$result = $callback();

		$this->columns = $original;

		return $result;
	}
	
	/**
     * Paginate the given query into a simple paginator.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     */
	public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null)
    {
		$page = $page ?: self::resolveCurrentPage($pageName);

        $total = $this->getCountForPagination();

		$results = $total ? $this->forPage($page, $perPage)->get($columns) : array();

		return $this->paginator($results, $total, $perPage, $page, [
            'path' => self::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
	}

	
	
	/**
     * Create a new paginator instance.
     *
     * @param  mixed  $items
     * @param  int  $total
     * @param  int  $perPage
     * @param  int|null  $currentPage
     * @param  array  $options (path, query, fragment, pageName)
     * @return void
     */
    public function paginator($items, $total, $perPage, $currentPage = null, array $options = [])
    {
        $this->options = $options;

        foreach ($options as $key => $value) {
            $this->{$key} = $value;
        }

        $this->total = $total;
        $this->perPage = $perPage;
        $this->lastPage = max((int) ceil($total / $perPage), 1);
        $this->path = $this->path !== '/' ? rtrim($this->path, '/') : $this->path;
		$this->currentPage = $this->setCurrentPage($currentPage, $this->pageName);

		//var_dump($this->total);
		//$this->perPage);
		//var_dump($this->lastPage);
		//var_dump($this->path);
		//var_dump($this->currentPage);
		//var_dump($items);
		$this->setItems($items);
		return $this;
    }
	

	public function currentPage()
    {
        return $this->currentPage;
	}
	
    public function path()
    {
        return $this->path;
    }


	public function url($page)
    {
        if ($page <= 0) {
            $page = 1;
        }

        // If we have any extra query string key / value pairs that need to be added
        // onto the URL, we will put them in query string form and then attach it
        // to the URL. This allows for extra information like sortings storage.
        $parameters = [$this->pageName => $page];

        if (count($this->query) > 0) {
            $parameters = array_merge($this->query, $parameters);
		}

        return $this->path()
                        .(Str::contains($this->path(), '?') ? '&' : '?')
                        .Arr::query($parameters)
                        .$this->buildFragment();
    }

	public function toArray()
    {
        return [
            'current_page' => $this->currentPage(),
            'data' => $this->items,
            'first_page_url' => $this->url(1),
            'from' => $this->firstItem(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->lastItem(),
        ];
	}
	public function total()
    {
        return $this->total;
	}
	public function firstItem()
    {
        return count($this->items) > 0 ? ($this->currentPage - 1) * $this->perPage + 1 : null;
	}
	public function nextPageUrl()
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage() + 1);
        }
	}
	public function lastPage()
    {
        return $this->lastPage;
	}
	 public function jsonSerialize()
    {
        return $this->toArray();
	}
	public function toJson($options = 0)
    {
        return json_encode($this->jsonSerialize(), $options);
    }
    public function perPage()
    {
        return $this->perPage;
	}
	
    public function lastItem()
    {
        return count($this->items) > 0 ? $this->firstItem() + $this->count() - 1 : null;
    }
	
    public function previousPageUrl()
    {
        if ($this->currentPage() > 1) {
            return $this->url($this->currentPage() - 1);
        }
    }

	public function hasMorePages()
    {
        return $this->currentPage() < $this->lastPage();
    }

	protected function buildFragment()
    {
        return $this->fragment ? '#'.$this->fragment : '';
    }

    protected static $currentPathResolver;

	public static function resolveCurrentPath($default = '/')
    {
		global $app;
		$protocol = "";
		if($app->request->server['HTTPS']=="on")
		{
			$protocol = "https://";
		}else{
			$protocol = "http://";
		}
		return $protocol.$app->request->server['HTTP_HOST']."/".$app->request->path();
	}

	
	protected function setItems($items)
    {
        $this->items = $items;

        $this->hasMore = count($this->items) > $this->perPage;

		$this->items = array_slice($this->items,0, $this->perPage);
    }


	/**
     * Resolve the current page or return the default value.
     *
     * @param  string  $pageName
     * @param  int  $default
     * @return int
     */
	
    protected static $currentPageResolver;
    public static function resolveCurrentPage($pageName = 'page', $default = 1)
    {
        return empty($_GET[$pageName]) ? 1 : $_GET[$pageName];
	}

	public static function currentPageResolver($resolver)
    {
        static::$currentPageResolver = $resolver;
    }
	

	
	

	public function getCountForPagination($columns = ['*'])
    {
        $results = $this->runPaginationCountQuery($columns);

        // Once we have run the pagination count query, we will get the resulting count and
        // take into account what type of query it was. When there is a group by we will
        // just return the count of the entire results set since that will be correct.
        if (! isset($results[0])) {
            return 0;
        } elseif (is_object($results[0])) {
            return (int) $results[0]->aggregate;
        }

        return (int) array_change_key_case((array) $results[0])['aggregate'];
	}
	
	protected function setAggregate($function, $columns)
    {
        $this->aggregate = compact('function', 'columns');

        if (empty($this->groups)) {
            $this->orders = null;

            $this->bindings['order'] = [];
        }

        return $this;
	}
	
	protected function runPaginationCountQuery($columns = ['*'])
    {
        if ($this->groups || $this->havings) {
            $clone = $this->cloneForPaginationCount();

            if (is_null($clone->columns) && ! empty($this->joins)) {
                $clone->select($this->from.'.*');
            }

            return $this->newQuery()
                ->from(new Expression('('.$clone->toSql().') as '.$this->grammar->wrap('aggregate_table')))
                ->mergeBindings($clone)
                ->setAggregate('count', $this->withoutSelectAliases($columns))
                ->get()->all();
        }

        $without = $this->unions ? ['orders', 'limit', 'offset'] : ['columns', 'orders', 'limit', 'offset'];

        return $this->cloneWithout($without)
                    ->cloneWithoutBindings($this->unions ? ['order'] : ['select', 'order'])
                    ->setAggregate('count', $this->withoutSelectAliases($columns))
                    ->get();
	}
	


	 /**
     * Remove the column aliases since they will break count queries.
     *
     * @param  array  $columns
     * @return array
     */
    protected function withoutSelectAliases(array $columns)
    {
        return array_map(function ($column) {
            return is_string($column) && ($aliasPosition = stripos($column, ' as ')) !== false
                    ? substr($column, 0, $aliasPosition) : $column;
        }, $columns);
    }

	/**
     * Clone the query without the given bindings.
     *
     * @param  array  $except
     * @return static
     */
    public function cloneWithoutBindings(array $except)
    {
        return tap(clone $this, function ($clone) use ($except) {
            foreach ($except as $type) {
                $clone->bindings[$type] = [];
            }
        });
	}
	
	
	/**
     * Clone the query without the given properties.
     *
     * @param  array  $properties
     * @return static
     */
    public function cloneWithout(array $properties)
    {
        return tap(clone $this, function ($clone) use ($properties) {
            foreach ($properties as $property) {
                $clone->{$property} = null;
            }
        });
    }
	
	
	protected function setCurrentPage($currentPage)
    {
        $currentPage = $currentPage;

        return $this->isValidPageNumber($currentPage) ? (int) $currentPage : 1;
	}
	
	protected function isValidPageNumber($page)
    {
        return $page >= 1 && filter_var($page, FILTER_VALIDATE_INT) !== false;
	}

	/**
     * Prepare the value and operator for a where clause.
     *
     * @param  string  $value
     * @param  string  $operator
     * @param  bool  $useDefault
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public function prepareValueAndOperator($value, $operator, $useDefault = false)
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new \InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param  string  $operator
     * @param  mixed  $value
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        return is_null($value) && in_array($operator, $this->operators) &&
             ! in_array($operator, ['=', '<>', '!=']);
    }

	/**
     * Get a scalar type value from an unknown type of input.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function flattenValue($value)
    {
        return is_array($value) ? head(Arr::flatten($value)) : $value;
    }

}
