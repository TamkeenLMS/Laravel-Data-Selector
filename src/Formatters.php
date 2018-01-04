<?php namespace DataSelector;

	use Illuminate\Support\Arr;

	/**
	 * @package DataSelector
	 */
	class Formatters{
		/**
		 * @var Selector
		 */
		public $selector;
		/**
		 * @var array
		 */
		public $formatters = [];

		/**
		 * @var array
		 */
		static public $globalFormatters = [];

		/**
		 * @param Selector $selector
		 */
		public function __construct(Selector $selector){
			$this->selector = $selector;
		}

		/**
		 * Stores a formatter closure to be called by ::add()
		 * @param $name
		 * @param $column
		 * @param \Closure $formatter
		 */
		public static function setGlobalFormatter($name, \Closure $formatter){
			static::$globalFormatters[$name] = $formatter;
		}

		/**
		 * @param $column
		 * @param \Closure|string $formatter
		 *
		 * @return $this
		 */
		public function add($column, $formatter){
			$this->formatters[] = [
				'column' => $column,
				'formatter' => $formatter
			];

			return $this;
		}

		/**
		 * Apply the formatters
		 */
		public function apply(){
			// No formatters?!
			if(!count($this->formatters)) return;

			foreach($this->formatters as $name => $formatter){
				$columnSegments = explode('.', $formatter['column']);
				$columnName     = $columnSegments[0];
				$subColumnName  = count($columnSegments) == 2 ?$columnSegments[1] :null;

				// Identify the formatter
				$formatter = $formatter['formatter'];

				if(!is_callable($formatter)){
					if(!array_key_exists($formatter, static::$globalFormatters)){
						throw new \Exception("Global formatter \"$formatter\" not found!");
					}

					$formatter = static::$globalFormatters[$formatter];
				}

				// Get the column value
				foreach($this->selector->data as & $item){
					if($subColumnName){
						foreach($item[$columnName] as & $subItem){
							$subItem[$subColumnName . '_formatted'] = $formatter($subItem[$subColumnName]);
						}

					}else{
						$item[$columnName . '_formatted'] = $formatter($item[$columnName]);
					}
				}
			}
		}
	}