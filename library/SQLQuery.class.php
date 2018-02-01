<?php

class SQLQuery {
    /** @var $_dbHandle PDO */
    protected $_dbHandle;

    /** @var $_result PDOStatement */
    protected $_result;

	protected $_query;
	protected $_table;

	protected $_describe = array();

	protected $_orderBy;
	protected $_order;
	protected $_extraConditions;
	protected $_hO;
	protected $_hM;
	protected $_hMABTM;
	protected $_page;
	protected $_limit;

    /** Connects to database **/
    function connect($host, $db_name, $username, $password){
        $this->_dbHandle = null;
        try{
            $this->_dbHandle = new PDO("mysql:host=" . $host . ";dbname=" . $db_name, $username, $password);
            $this->_dbHandle->exec("set names utf8");
        }catch(PDOException $exception){
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->_dbHandle;
    }
 
    /** Disconnects from database **/
    function disconnect() {
        $this->_dbHandle = null;
    }

    /** Select Query **/

	function where($field, $value) {
		$this->_extraConditions .= '`'.$this->_model.'`.`'.$field.'` = \''.$this->_dbHandle->quote($value).'\' AND ';
	}

	function like($field, $value) {
		$this->_extraConditions .= '`'.$this->_model.'`.`'.$field.'` LIKE \'%'.$this->_dbHandle->quote($value).'%\' AND ';
	}

	function showHasOne() {
		$this->_hO = 1;
	}

	function showHasMany() {
		$this->_hM = 1;
	}

	function showHMABTM() {
		$this->_hMABTM = 1;
	}

	function setLimit($limit) {
		$this->_limit = $limit;
	}

	function setPage($page) {
		$this->_page = $page;
	}

	function orderBy($orderBy, $order = 'ASC') {
		$this->_orderBy = $orderBy;
		$this->_order = $order;
	}

	function search() {

		global $inflect;
		$from = '`'.$this->_table.'` as `'.$this->_model.'` ';
		$conditions = '\'1\'=\'1\' AND ';

		if ($this->_hO == 1 && isset($this->hasOne)) {
			
			foreach ($this->hasOne as $alias => $model) {
				$table = strtolower($inflect->pluralize($model));
				$singularAlias = strtolower($alias);
				$from .= 'LEFT JOIN `'.$table.'` as `'.$alias.'` ';
				$from .= 'ON `'.$this->_model.'`.`'.$singularAlias.'_id` = `'.$alias.'`.`id`  ';
			}
		}
	
		if ($this->id) $conditions .= '`'.$this->_model.'`.`id` = \''.$this->_dbHandle->quote($this->id).'\' AND ';
		if ($this->_extraConditions) $conditions .= $this->_extraConditions;

		$conditions = substr($conditions,0,-4);
		
		if (isset($this->_orderBy)) $conditions .= ' ORDER BY `'.$this->_model.'`.`'.$this->_orderBy.'` '.$this->_order;

		if (isset($this->_page)) {
			$offset = ($this->_page-1)*$this->_limit;
			$conditions .= ' LIMIT '.$this->_limit.' OFFSET '.$offset;
		}
		
		$this->_query = 'SELECT * FROM '.$from.' WHERE '.$conditions;

		$this->_result = $this->_dbHandle->exec($this->_query);
		$result = array();
		$table = array();
		$field = array();
		$tempResults = array();
        $numOfFields = $this->_result->columnCount();

		for ($i = 0; $i < $numOfFields; ++$i) {
            array_push($table, $this->_result->getColumnMeta($i)['table'], $i);
            array_push($field, $this->_result->getColumnMeta($i)['name'], $i);
		}

		if ($this->_result->rowCount() > 0 ) {
			while ($row = $this->_result->fetch()) {
				for ($i = 0;$i < $numOfFields; ++$i) {
					$tempResults[$table[$i]][$field[$i]] = $row[$i];
				}

				if ($this->_hM == 1 && isset($this->hasMany)) {
					foreach ($this->hasMany as $aliasChild => $modelChild) {
						$conditionsChild = '';
						$fromChild = '';

						$tableChild = strtolower($inflect->pluralize($modelChild));
						$pluralAliasChild = strtolower($inflect->pluralize($aliasChild));
						$singularAliasChild = strtolower($aliasChild);

						$fromChild .= '`'.$tableChild.'` as `'.$aliasChild.'`';
						$conditionsChild .= '`'.$aliasChild.'`.`'.strtolower($this->_model).'_id` = \''.$tempResults[$this->_model]['id'].'\'';
	
						$queryChild =  'SELECT * FROM '.$fromChild.' WHERE '.$conditionsChild;	
						#echo '<!--'.$queryChild.'-->';
						$resultChild = $this->_dbHandle->exec($queryChild);
				
						$tableChild = array();
						$fieldChild = array();
						$tempResultsChild = array();
						$resultsChild = array();
						
						if ($resultChild->rowCount() > 0) {
							$numOfFieldsChild = $resultChild->columnCount();
							for ($j = 0; $j < $numOfFieldsChild; ++$j) {
								array_push($tableChild, $resultChild->getColumnMeta($j)['table'], $j);
								array_push($fieldChild, $resultChild->getColumnMeta($j)['name'], $j);
							}

							while ($rowChild = $resultChild->fetch()) {
								for ($j = 0;$j < $numOfFieldsChild; ++$j) {
									$tempResultsChild[$tableChild[$j]][$fieldChild[$j]] = $rowChild[$j];
								}
								array_push($resultsChild,$tempResultsChild);
							}
						}
						
						$tempResults[$aliasChild] = $resultsChild;
						
						$resultChild->closeCursor();
					}
				}


				if ($this->_hMABTM == 1 && isset($this->hasManyAndBelongsToMany)) {
					foreach ($this->hasManyAndBelongsToMany as $aliasChild => $tableChild) {

						$conditionsChild = '';
						$fromChild = '';

						$tableChild = strtolower($inflect->pluralize($tableChild));
						$pluralAliasChild = strtolower($inflect->pluralize($aliasChild));
						$singularAliasChild = strtolower($aliasChild);

						$sortTables = array($this->_table,$pluralAliasChild);
						sort($sortTables);
						$joinTable = implode('_',$sortTables);

						$fromChild .= '`'.$tableChild.'` as `'.$aliasChild.'`,';
						$fromChild .= '`'.$joinTable.'`,';
						
						$conditionsChild .= '`'.$joinTable.'`.`'.$singularAliasChild.'_id` = `'.$aliasChild.'`.`id` AND ';
						$conditionsChild .= '`'.$joinTable.'`.`'.strtolower($this->_model).'_id` = \''.$tempResults[$this->_model]['id'].'\'';
						$fromChild = substr($fromChild,0,-1);

						$queryChild =  'SELECT * FROM '.$fromChild.' WHERE '.$conditionsChild;	
						#echo '<!--'.$queryChild.'-->';
						$resultChild = $this->_dbHandle->exec($queryChild);
				
						$tableChild = array();
						$fieldChild = array();
						$tempResultsChild = array();
						$resultsChild = array();
						
						if ($resultChild->rowCount() > 0) {
							$numOfFieldsChild = $resultChild->ColumnCount();
							for ($j = 0; $j < $numOfFieldsChild; ++$j) {
                                array_push($tableChild, $resultChild->getColumnMeta($j)['table'], $j);
                                array_push($fieldChild, $resultChild->getColumnMeta($j)['name'], $j);
							}

							while ($rowChild = $resultChild->fetch()) {
								for ($j = 0;$j < $numOfFieldsChild; ++$j) {
									$tempResultsChild[$tableChild[$j]][$fieldChild[$j]] = $rowChild[$j];
								}
								array_push($resultsChild,$tempResultsChild);
							}
						}
						
						$tempResults[$aliasChild] = $resultsChild;
						$resultChild->closeCursor();
					}
				}

				array_push($result,$tempResults);
			}

			if ($this->_result->rowCount() == 1 && $this->id != null) {
				$this->_result->closeCursor();
				$this->clear();
				return($result[0]);
			} else {
				$this->_result->closeCursor();
				$this->clear();
				return($result);
			}
		} else {
			$this->_result->closeCursor();
			$this->clear();
			return $result;
		}

	}

    /** Custom SQL Query **/
	function custom($query) {

		global $inflect;

		$this->_result = $this->_dbHandle->exec($query);

		$result = array();
		$table = array();
		$field = array();
		$tempResults = array();

		if(substr_count(strtoupper($query),"SELECT")>0) {
			if ($this->_result->rowCount() > 0) {
				$numOfFields = $this->_result->columnCount();
				for ($i = 0; $i < $numOfFields; ++$i) {
					array_push($table, $this->_result->getColumnMeta($i)['table'], $i);
					array_push($field, $this->_result->getColumnMeta($i)['name'], $i);
				}
					while ($row = $this->_result->fetch()) {
						for ($i = 0;$i < $numOfFields; ++$i) {
							$table[$i] = ucfirst($inflect->singularize($table[$i]));
							$tempResults[$table[$i]][$field[$i]] = $row[$i];
						}
						array_push($result,$tempResults);
					}
			}
			$this->_result->fetch();
		}	
		$this->clear();
		return($result);
	}

    /** Describes a Table **/

	protected function _describe() {
		global $cache;

		$this->_describe = $cache->get('describe'.$this->_table);

		if (!$this->_describe) {
			$this->_describe = array();
			$query = 'DESCRIBE '.$this->_table;
			$this->_result = $this->_dbHandle->exec($query);
			while ($row = $this->_result->fetch()) {
				 array_push($this->_describe,$row[0]);
			}

			$this->_result->closeCursor();
			$cache->set('describe'.$this->_table,$this->_describe);
		}
		foreach ($this->_describe as $field) {
			$this->$field = null;
		}
	}

    /** Delete an Object **/
	function delete() {
		if ($this->id) {
			$query = 'DELETE FROM '.$this->_table.' WHERE `id`=\''.$this->_dbHandle->quote($this->id).'\'';
			$this->_result = $this->_dbHandle->exec($query);
			$this->clear();
			if ($this->_result == 0) {
			    /** Error Generation **/
				return -1;
		   }
		} else {
			/** Error Generation **/
			return -1;
		}
		
	}

    /** Saves an Object i.e. Updates/Inserts Query **/

	function save() {
		$query = '';
		if (isset($this->id)) {
			$updates = '';
			foreach ($this->_describe as $field) {
				if ($this->$field) {
					$updates .= '`'.$field.'` = \''.$this->_dbHandle->quote($this->$field).'\',';
				}
			}

			$updates = substr($updates,0,-1);

			$query = 'UPDATE '.$this->_table.' SET '.$updates.' WHERE `id`=\''.mysql_real_escape_string($this->id).'\'';			
		} else {
			$fields = '';
			$values = '';
			foreach ($this->_describe as $field) {
				if ($this->$field) {
					$fields .= '`'.$field.'`,';
					$values .= '\''.mysql_real_escape_string($this->$field).'\',';
				}
			}
			$values = substr($values,0,-1);
			$fields = substr($fields,0,-1);

			$query = 'INSERT INTO '.$this->_table.' ('.$fields.') VALUES ('.$values.')';
		}
		$this->_result = mysql_query($query, $this->_dbHandle);
		$this->clear();
		if ($this->_result == 0) {
            /** Error Generation **/
			return -1;
        }
	}
 
	/** Clear All Variables **/

	function clear() {
		foreach($this->_describe as $field) {
			$this->$field = null;
		}

		$this->_orderby = null;
		$this->_extraConditions = null;
		$this->_hO = null;
		$this->_hM = null;
		$this->_hMABTM = null;
		$this->_page = null;
		$this->_order = null;
	}

	/** Pagination Count **/

	function totalPages() {
		if ($this->_query && $this->_limit) {
			$pattern = '/SELECT (.*?) FROM (.*)LIMIT(.*)/i';
			$replacement = 'SELECT COUNT(*) FROM $2';
			$countQuery = preg_replace($pattern, $replacement, $this->_query);
			$this->_result = mysql_query($countQuery, $this->_dbHandle);
			$count = mysql_fetch_row($this->_result);
			$totalPages = ceil($count[0]/$this->_limit);
			return $totalPages;
		} else {
			/* Error Generation Code Here */
			return -1;
		}
	}

    /** Get error string **/

    function getError() {
        return mysql_error($this->_dbHandle);
    }
}