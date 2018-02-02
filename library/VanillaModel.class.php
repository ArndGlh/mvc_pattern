<?php
class VanillaModel extends SQLQuery {
	protected $_model;

	function __construct() {
		
		global $inflect;

		$this->connect(DB_HOST,DB_NAME,DB_USER, DB_PASSWORD);
		$this->_limit = PAGINATE_LIMIT;
		$this->_model = get_class($this);
		$this->_table = strtolower($inflect->pluralize($this->_model));
		if (!isset($this->abstract)) {
			$this->_describe();
		}
	}

	function __destruct() {
	}
}
