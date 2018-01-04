<?php namespace DataSelector;

use Illuminate\Support\Arr;

/**
	 * @package DataSelector
	 */
	class Formatters{
		/**
		 * @var DataSelector
		 */
		public $selector;
		/**
		 * @var array
		 */
		public $formatters;
		/**
		 * Ignore global formatters?
		 * @var bool
		 */
		public $ignoreGlobalFormatters = false;
		/**
		 * @var array
		 */
		public $skippedGlobalFormatters = [];

		/**
		 * @var array
		 */
		static public $globalFormatters;

		/**
		 * @param DataSelector $selector
		 */
		public function __construct(DataSelector $selector){
			$this->selector = $selector;
		}

		/**
		 * To ignore/omit global formatters
		 * @param bool|TRUE $ignore
		 *
		 * @return $this
		 */
		public function ignoreGlobalFormatters($ignore = true){
			$this->ignoreGlobalFormatters = $ignore;

			return $this;
		}

		/**
		 * Skips/ignores one or more of the global formatters.
		 * <code>
		 * $formatter->skipGlobalFormatter('time-formatter');
		 * $formatter->skipGlobalFormatter('time-formatter', 'name-formatter');
		 * </code>
		 * @return $this
		 */
		public function skipGlobalFormatter(){
			$this->skippedGlobalFormatters += func_get_args();

			return $this;
		}

		/**
		 * Add a global formatter to be applied on all data selections
		 * @param $name
		 * @param $column
		 * @param \Closure $formatter
		 */
		public static function addGlobal($name, $column, \Closure $formatter){
			static::$globalFormatters[$name] = [
				'column' => $column,
				'formatter' => $formatter
			];
		}

		/**
		 * @param $name
		 * @param $column
		 * @param \Closure $formatter
		 *
		 * @return $this
		 */
		public function add($name, $column, \Closure $formatter){
			$this->formatters[$name] = [
				'column' => $column,
				'formatter' => $formatter
			];

			return $this;
		}

		/**
		 * Apply the formatters
		 */
		public function apply(){
			// Get a unified list of the formatters
			$formatters = array_merge(static::$globalFormatters, $this->formatters);

			// No formatters?!
			if(!count($formatters)) return;

			foreach($formatters as $name => $formatter){
				$column = $formatter['column'];
				$formatter = $formatter['formatter'];

				// Get the column value
				$columnValue = Arr::get($this->selector->data, $column);

				Arr::set($this->selector->data, $formatter['column'], $formatter($columnValue));
			}
		}
	}