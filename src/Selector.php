<?php namespace DataSelector;

	use Illuminate\Database\Eloquent\Collection;
	use Illuminate\Database\Query\Builder;

	/**
	 * @package DataSelector
	 */
	class Selector{

		use \Spatie\Macroable\Macroable;

		/**
		 * @var Builder
		 */
		public $query;
		/**
		 * @var EagerLoading
		 */
		public $eagerLoading = null;
		/**
		 * Data formatters
		 * @var Formatters
		 */
		public $formatters = null;
		/**
		 * Pagination setup
		 * @var null
		 */
		public $pagination = null;
		/**
		 * Whether the whole query was canceled
		 * @var bool
		 */
		public $canceled = false;
		/**
		 * The retrieved data
		 * @var Collection
		 */
		public $data;
		/**
		 * The name of the "created_at" column
		 * @var string
		 */
		public $createdAtColumn = 'created_at';
		/**
		 * The name of the "updated_at" column
		 * @var string
		 */
		public $updatedAtColumn = 'updated_at';

		/**
		 * @param string $model
		 * @param array|NULL $columns
		 * @param array|NULL $defaultColumns
		 * @param bool|FALSE $includeTrashed
		 */
		public function __construct($model, array $columns = null, array $defaultColumns = null, $includeTrashed = false){
			// Start the query
			$columnsArray = $columns ?: ($defaultColumns ?: ['*']);
			$this->query = call_user_func_array([$model, 'select'], [$columnsArray]);

			// Include the trashed ?
			if($includeTrashed === true){
				$this->includeTrashed();
			}
		}

		/**
		 * <code>
		 * $selector->defineWhere('oldEnough', function(){ $this->query->where('age' '>', 30) })
		 * $selector->whereOldEnough();
		 * </code>
		 * @param $methodName
		 * @param \Closure $call
		 */
		public static function defineWhere($methodName, \Closure $call){
			static::macro('where' . ucfirst($methodName), $call);
		}

		/**
		 * Returns the query
		 * @return $this
		 */
		public function getQuery(){
			return $this->query;
		}

		/**
		 * Pagination setup
		 * @param $itemsPerPage
		 * @param bool|FALSE $queryString
		 *
		 * @return $this
		 */
		public function paginate($itemsPerPage, $queryString = false){
			$this->pagination = [
				'itemsPerPage' => $itemsPerPage,
				'queryString' => $queryString
			];

			return $this;
		}

		/**
		 * @return EagerLoading
		 */
		public function eagerLoading(){
			if($this->eagerLoading){
				return $this->eagerLoading;
			}

			return $this->eagerLoading = new EagerLoading($this);
		}

		/**
		 * @return Formatters
		 */
		public function formatters(){
			if($this->formatters){
				return $this->formatters;
			}

			return $this->formatters = new Formatters($this);
		}

		/**
		 * Set a new data formatter
		 * @param $column
		 * @param $formatter [optional]
		 */
		public function format($column, $formatter = null){
			if(is_array($column)){
				foreach($column as $format){
					$this->formatters()->add($format[0], $format[1]);
				}

			}elseif($formatter){
				$this->formatters()->add($column, $formatter);
			}
		}

		/**
		 * Add an eager-loading call
		 * @param $relation
		 * @param array|NULL $columns
		 *
		 * @return $this
		 */
		public function with($relation, array $columns = null){
			$this->eagerLoading()->add($relation, $columns);

			return $this;
		}

		/**
		 * Add new columns to the selection statement. You can either provide an array of these columns, or pass a
		 * raw columns list statement.
		 * <code>
		 *  $selector->select(['id', 'name', 'desc'])
		 *  $selector->select("id, name, LEFT(desc, 100)")
		 * </code>
		 * @param array|string $columns
		 * @param array|string $override When TRUE is overrides the current set of columns selected previously
		 *
		 * @return $this
		 */
		public function select($columns, $override = false){
			if($override === true){
				$this->query->getQuery()->columns = [];
			}

			if(is_array($columns)){
				$this->query->select($columns);

			}else{
				$this->query->selectRaw($columns);
			}

			return $this;
		}

		/**
		 * Include the trashed items in the selection
		 * @return $this
		 */
		public function includeTrashed(){
			$this->query->withTrashed();

			return $this;
		}

		/**
		 * Selects only the trashed items
		 * @return $this
		 */
		public function onlyTrashed(){
			$this->query->onlyTrashed();

			return $this;
		}

		/**
		 * @param ...$args
		 *
		 * @return $this
		 */
		public function where(... $args){
			call_user_func_array([$this->query, 'where'], $args);

			return $this;
		}

		/**
		 * @param $column
		 * @param $values
		 *
		 * @return $this
		 */
		public function whereIn($column, array $values){
			$this->query->whereIn($column, $values);

			return $this;
		}

		/**
		 * WHERE IN (id)
		 * @param $values
		 *
		 * @return Selector
		 */
		public function ofIds($values){
			return $this->whereIn('id', $values);
		}

		/**
		 * @param $column
		 * @param bool|TRUE $asc
		 *
		 * @return $this
		 */
		public function orderBy($column, $asc = true){
			$this->query->orderBy($column, $asc ?'asc' :'desc');

			return $this;
		}

		/**
		 * @return Selector
		 */
		public function latestFirst(){
			return $this->orderBy($this->createdAtColumn, false);
		}

		/**
		 * @return Selector
		 */
		public function oldestFirst(){
			return $this->orderBy($this->createdAtColumn);
		}

		/**
		 * Data ordering: last modified first
		 *
*@return Selector
		 */
		public function lastModifiedFirst(){
			return $this->orderBy($this->updatedAtColumn, true);
		}

		/**
		 * @return Selector
		 */
		public function lastModifiedLast(){
			return $this->orderBy($this->updatedAtColumn);
		}

		/**
		 * Cancel the whole query
		 * @return $this
		 */
		public function cancel(){
			$this->canceled = true;

			return $this;
		}

		/**
		 * The data
		 * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|Collection|static[]
		 */
		public function get(){
			// Return an empty collection
			if($this->canceled === true){
				return new Collection();
			}

			// Pagination?
			if($this->pagination){
				$this->data = $this->query->paginate($this->pagination['itemsPerPage']);

				// Url query string ?
				if(is_array($this->pagination['queryString'])){
					$this->data->appends($this->pagination['queryString']);
				}

			}else{
				$this->data = clone $this->query->get();
			}

			// Apply eager-loading
			if($this->eagerLoading){
				$this->eagerLoading()->load();
			}

			// Formatters
			if($this->formatters){
				$this->formatters()->apply();
			}

			return $this->data;
		}

		/**
		 * Returns the count, not the actual data
		 * @return int
		 */
		public function getCount(){
			return $this->query->count();
		}

		/**
		 * Tells if the query has no results
		 * @return bool
		 */
		public function isEmpty(){
			return $this->getCount() == 0;
		}

		/**
		 * Tells if the query returns data
		 * @return bool
		 */
		public function isNotEmpty(){
			return $this->getCount() > 0;
		}

		/**
		 * Returns the SQL code for the query
		 * @return string
		 */
		public function getSQL(){
			return $this->query->toSql();
		}
	}