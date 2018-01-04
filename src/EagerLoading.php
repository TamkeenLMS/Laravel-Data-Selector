<?php namespace DataSelector;

	/**
	 * Class EagerLoading
	 *
	 * @package DataSelector
	 */
	class EagerLoading{
		/**
		 * @var DataSelector
		 */
		public $selector;
		/**
		 * @var array
		 */
		public $list = [];

		/**
		 * @param DataSelector $selector
		 */
		public function __construct(DataSelector $selector){
			$this->selector = $selector;
		}

		/**
		 * Adds a new eager-loading step after the query is done
		 * @param $relation
		 * @param array $columns
		 * @param null $where
		 * @param bool|FALSE $withTrashed
		 *
		 * @return $this
		 */
		public function add($relation, array $columns = ['*'], $where = null, $withTrashed = false){
			$this->list[$relation] = [
				'columns'       => $columns,
				'where'         => $where,
				'withTrashed'   => $withTrashed
			];

			return $this;
		}

		/**
		 * Does the eager-loading
		 */
		public function load(){
			if(!count($this->list)) return;

			foreach($this->list as $relation => $call){
				$this->selector->data->load([$relation => function($query) use($call){
					$columns        = $call['columns'];
					$where          = $call['where'];
					$withTrashed    = $call['withTrashed'];

					// Column selection
					$query->select($columns);

					// Where
					if($where){
						if(is_array($where)){
							call_user_func_array([$query, 'where'], $where);

						}else{
							$query->whereRaw($call['where']);
						}
					}

					// Include trashed?
					if($withTrashed){
						$query->withTrashed();
					}
				}]);
			}
		}
	}