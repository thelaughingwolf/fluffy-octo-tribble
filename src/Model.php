<?php

namespace LaughingWolf\API;

use LaughingWolf\API\Helpers as Helpers;

class ModelException extends \Exception {
	private $stage;

	public function __construct( $message, $stage, $code = 0, Exception $previous = null ) {
		$allowedStages = [ 'config', 'operation' ];
		if ( in_array( $stage, $allowedStages ) ) {
			$this->stage = $stage;
		}
		parent::__construct( $message, $code, $previous );
	}

	public function __toString( ) {
		return __CLASS__ . ": [{$this->code}]: " . ( isset( $this->stage ) ? $this->stage . " | " : "" ) . $this->message;
	}
}

class QueryException extends \Exception {
	private $path;

	public function __construct( $message, $path, $code = 0, Exception $previous = null ) {
		$this->path = $path;
		parent::__construct( $message, $code, $previous );
	}

	public function __toString( ) {
		return __CLASS__ . ": [{$this->code}]: " . ( isset( $this->path ) ? $this->path . " | " : "" ) . $this->message;
	}
}

class Model {
	// The PDO handle
	protected $dbconn;
	// The table name, e.g. "users"
	protected $table;
	// The schema name, e.g. "mgmt"
	protected $schema;
	// The full table qualifier
	protected $tablename;
	// The model definition
	protected $fields;
	// Associations, if any
	protected $associations;
	protected $sortableFields;
	protected $searchableFields;

	public function __construct( $schema, $table, $fields, $associations, $dbconn ) {
		$this->schema = $schema;
		$this->table = $table;
		$this->sortableFields = (object) [];
		$this->searchableFields = (object) [];
		$this->tablename = "`" . $this->schema . "`.`" . $this->table . "`";

		//	Fields should be an object of names with various options
		//	Options:
		//		type: type from MySQL
		//		sortable: one of:
		//			boolean FALSE: not a sortable field
		//			boolean TRUE: sortable field, sort by ascending order by default
		//			'ASC': sortable field, sort by ascending order by default
		//			'DESC': sortable field, sort by descending order by default
		$this->fields = $this->processFields( $fields );

		// Associations should be an object of names with various options
		// Options:
		//		type: hasOne, hasMany, or manyToMany
		//		collection: other table name
		//		key: key for this table
		//		foreignKey: key in the other table (use 'key' if not present)
		//		sortable: same as the field definition
		//		filterable: whether 
		$this->associations = $this->processAssociations( $associations );

		// Add a handle internally
		$this->dbconn = $dbconn;
	}

	// Instantiation methods
	private function processFields( $input ) {
		if ( !isset( $input ) ) {
			throw new ModelException( "Model must have fields defined", 'config' );
		}

		if ( !is_object( $input ) ) {
			throw new ModelException( "Fields definition must be an object", 'config' );
		}

		foreach ( $input as $fieldName => $opts ) {
			if ( isset( $opts->type )) {
				$pieces = explode( '(', $opts->type );
				$newType = [ ];
				$type = array_shift( $pieces );
				$length = null;
				if ( count( $pieces ) === 1) {
					$length = substr( array_shift( $pieces ), 0, -1 );
				}

				$opts->type = strtoupper( $type );
				if ( isset( $length ) ) {
					$opts->length = $length;
				}
			} else {
				throw new ModelException( "Every field definition must have a type", 'config' );
			}

			if ( isset( $opts->sortable ) ) {
				if ( $opts->sortable === true ) {
					$opts->sortable = 'ASC';
				}
				if ( $opts->sortable !== false ) {
					$opts->sortable = strtoupper( $opts->sortable );
				}
			} else {
				$opts->sortable = false;
			}

			if ( $opts->sortable ) {
				$this->sortableFields->{$fieldName} = $opts->sortable;
			}
		}

		return $input;
	}

	private function processAssociations( $input ) {
		if ( !isset( $input ) ) {
			return (object) [ ];
		}
		if ( !is_object( $input ) ) {
			throw new ModelException( "Associations definition must be an object", 'config' );
		}

		$allowedAssociationTypes = [ 'hasOne', 'hasMany', 'manyToMany' ];

		foreach ( $input as $fieldName => $opts ) {
			if ( !is_object( $opts ) ) {
				throw new ModelException( "Associations definitions must be objects", 'config' );
			}
			if ( !isset( $opts->type ) ) {
				throw new ModelException( "Every association definition must have a type", 'config' );
			} else if ( !in_array($opts->type, $allowedAssociationTypes ) ) {
				throw new ModelException( "Association type '{$opts->type}' is not recognized", 'config' );
			}
			if ( !isset( $opts->key ) ) {
				throw new ModelException( "Every association definition must have a key", 'config' );
			}
			if ( !isset( $opts->schema ) ) {
				throw new ModelException( "Every association definition must have a schema", 'config' );
			}
			if ( !isset( $opts->table ) ) {
				throw new ModelException( "Every association definition must have a table", 'config' );
			}

			if ( !isset( $opts->foreignKey ) ) {
				$opts->foreignKey = $opts->key;
			}
		}

		return $input;
	}

	// Utility methods
	public function parseQuery( $query ) {
		error_log( "Parsing query: " . json_encode( $query ) );
		$result = (object) [
			'sqlPieces'	=>	[ ],
			'sql'		=>	'',
			'values'	=>	[ ],
			'filters'	=>	(object) [ ],
			'sort'		=>	null,
			'skip'		=>	0,
			'limit'		=>	50
		];

		if ( isset( $query->filters ) ) {
			try {
				$result->filters = $this->parseFilters( $query->filters );
				$result->sqlPieces[] = 'WHERE ' . $result->filters->sql;
				$result->values = array_merge( $result->values, $result->filters->values );
			} catch ( Exception $e ) {
				throw new QueryException( $e->message, "filters" );
			}
		}

		if ( isset( $query->sort ) ) {
			try {
				$result->sort = $this->parseSort( $query->sort );
				$result->sql .= ' ORDER BY ' . $result->sort->sql;
				$result->sqlPieces[] = 'ORDER BY ' . $result->sort->sql;
				// No values for ORDER BY - PDO can't handle it
			} catch ( Exception $e ) {
				throw new QueryException( $e->message, "sort" );
			}
		}
		
		if ( isset( $query->skip ) ) {
			try {
				$result->skip = $this->parseSkip( $query->skip );
			} catch ( Exception $e ) {
				throw new QueryException( $e->message, "skip" );
			}
		}

		if ( isset( $query->limit ) ) {
			try {
				$result->limit = $this->parseLimit( $query->limit );
			} catch ( Exception $e ) {
				throw new QueryException( $e->message, "limit" );
			}
		}

		// Skip and limit are always set
		$result->sqlPieces[] = 'LIMIT ?, ?';
		$result->values[] = $result->skip;
		$result->values[] = $result->limit;

		$result->sql = implode( ' ', $result->sqlPieces );

		return $result;
	}
	
	// Sort should be passed as a comma-separated list of fields & directions
	protected function parseSort( $sort ) {
		$pieces = explode( ',', $sort );
		$result = (object) [
			'sqlPieces'	=>	[ ],
			'sql'		=>	'',
			'exprs'		=>	[ ]
		];
		foreach ( $pieces as $piece ) {
			$sortExpr = (object) [
				'field'	=>	'',
				'dir'	=>	''
			];
			$piece = trim( $piece );
			$chunks = explode( ' ', $piece );
			$sortExpr->field = array_shift( $chunks );
			
			if ( count( $chunks ) > 0 ) {
				$sortExpr->dir = strtoupper( array_shift( $chunks ) );
			}

			if ( !in_array( $sortExpr->dir, [ 'ASC', 'DESC' ] ) ) {
				error_log( __CLASS__ . " | Refused to sort in unrecognized direction '{$sortExpr->dir}'" );
				$sortExpr->dir = '';
			}

			if ( isset( $this->sortableFields->{$sortExpr->field} ) ) {
				if ( !$sortExpr->dir ) { // Set default sort direction
					$sortExpr->dir = $this->sortableFields->{$sortExpr->field};
				}
				$result->exprs[] = $sortExpr;
				$result->sqlPieces[] = $sortExpr->field . ' ' . $sortExpr->dir;
			} else {
				error_log( __CLASS__ . " | Refused to sort by unrecognized field '{$sortExpr->field}'" );
			}
		}
		$result->sql = implode( ', ', $result->sqlPieces );
		return $result;
	}

	protected function parseSkip( $skip ) {
		$skip = (int) $skip;
		if ( $skip >= 0 ) {
			return $skip;
		}
		return 0;
	}

	protected function parseLimit( $limit ) {
		$limit = (int) $limit;
		if ( $limit > 0 ) {
			return $limit;
		}
		return 30;
	}

	// Accepts Waterline query language, because I like it
	// Returns a WHERE block for PDO->prepare( )
	protected function parseFilters( $filters, $depth = 0, $mode = 'AND', $parentMode = null ) {
		$result = (object) [
			'sqlPieces'	=>	[ ],
			'sql'		=>	'',
			'values'	=>	[ ],
			'mode'		=>	$mode
		];

		if ( is_array( $filters ) ) {
			foreach ( $filters as $clause ) {
				$clause = $this->parseFilters( $clause, ( $depth + 1 ), $mode, $mode );
				$result->sqlPieces[] = $clause->sql;
				$result->values = array_merge( $result->values, $clause->values );
			}
		} else {
			$mode = 'AND'; // Objects inside of ORs are always ANDs
			foreach ( $filters as $key => $value ) {
				if ( strtoupper( $key ) === 'OR' || strtoupper( $key ) === 'AND' ) {
					if ( !is_array( $value ) ) {
						throw new ModelException( strtoupper( $key ) . " subordinate clauses must be represented by an array", "operation" );
					}
					$clause = $this->parseFilters( $value, ( $depth + 1 ), strtoupper( $key ), $mode );
					$result->sqlPieces[] = "{$clause->sql}";
					$result->values = array_merge( $result->values, $clause->values );
				} else {
					$phrase = $this->parseFilterField( $value );
					foreach ( $phrase->clauses as $clause ) {
						$result->sqlPieces[] = "$key " . $clause->sql;
						$result->values = array_merge( $result->values, ( is_array( $clause->value ) ? $clause->value : [ $clause->value ] ) );
					}
				}
			}
		}

		$result->sql = implode(" $mode ", $result->sqlPieces );

		if ( isset( $parentMode ) && $parentMode !== $mode) {
			$result->sql = "({$result->sql})";
		}

		return $result;
	}

	protected function parseFilterField( $fieldValue ) {
		$result = (object) [
			'clauses'	=>	[ ]
		];

		if ( !is_object( $fieldValue ) ) {
			$fieldValue = (object) [
				'='	=>	$fieldValue
			];
		}

		foreach ( $fieldValue as $key => $value ) {
			$result->clauses[] = $this->parseFilterValue( $key, $value );
		}

		return $result;
	}

	protected function parseFilterValue( $operand, $value ) {
		$result = (object) [
			'sql'		=>	'',
			'operand'	=>	$operand,
			'value'		=>	$value
		];

		switch ( strtolower( $result->operand ) ) {
			case '=':
			case '<':
			case '<=':
			case '>':
			case '>=':
			case '!=':
				// No actions taken - these will translate directly
				break;
			case '!':
				$result->operand = '!=';
				break;
			case 'contains':
				$result->operand = 'LIKE';
				$result->value = "%" . $result->value . "%";
				break;
			case 'startswith':
				$result->operand = 'LIKE';
				$result->value = $result->value . "%";
				break;
			case 'endswith':
				$result->operand = 'LIKE';
				$result->value = "%" . $result->value;
				break;
			case 'like':
				$result->operand = 'LIKE';
				$result->value = $result->value;
				break;
			case '!contains':
				$result->operand = 'NOT LIKE';
				$result->value = "%" . $result->value . "%";
				break;
			case '!startswith':
				$result->operand = 'NOT LIKE';
				$result->value = $result->value . "%";
				break;
			case '!endswith':
				$result->operand = 'NOT LIKE';
				$result->value = "%" . $result->value;
				break;
			case '!like':
				$result->operand = 'NOT LIKE';
				$result->value = $result->value;
				break;
		}

		if ( is_array( $result->value ) ) {
			if ( $result->operand === '=' ) {
				$result->operand = 'IN';
			} else if ($result->operand === '!=') {
				$result->operand = 'NOT IN';
			} else {
				throw new ModelException( "Array as value for operand {$result->operand} is not supported", 'operation' );
			}
			$result->sql = "{$result->operand} (" . implode( ", ", array_fill( 0, count( $result->value ), "?" ) ) . ")";
		} else {
			$result->sql = "{$result->operand} ?";
		}

		/* if ( isset( $value ) ) {
			if ( is_object( $value ) ) {
				if ( Helpers::hasProperty( $value, '<' ) ) { // Key less than value
					$result->operand = '<';
					$result->value = Helpers::getProperty( $value, '<' );
				} else if ( Helpers::hasProperty( $value, '<=' ) ) { // Key less than or equal to value
					$result->operand = '<=';
					$result->value = Helpers::getProperty( $value, '<=' );
				} else if ( Helpers::hasProperty( $value, '>' ) ) { // Key greater than value
					$result->operand = '>';
					$result->value = Helpers::getProperty( $value, '>' );
				} else if ( Helpers::hasProperty( $value, '>=' ) ) { // Key greater than or equal to value
					$result->operand = '>=';
					$result->value = Helpers::getProperty( $value, '>=' );
				} else if ( Helpers::hasProperty( $value, '!' ) ) { // Key not equal to value (value may be an array)
					$result->operand = '!=';
					$result->value = Helpers::getProperty( $value, '!' );
				} else if ( Helpers::hasProperty( $value, 'contains' ) ) { // Key contains value (wrap with % and convert to LIKE)
					$result->operand = 'LIKE';
					$result->value = "%" . Helpers::getProperty( $value, 'contains' ) . "%";
				} else if ( Helpers::hasProperty( $value, 'startswith' ) ) { // Key starts with value (append % and convert to LIKE)
					$result->operand = 'LIKE';
					$result->value = Helpers::getProperty( $value, 'startswith' ) . "%";
				} else if ( Helpers::hasProperty( $value, 'endswith' ) ) { // Key ends with value (prepend % and convert to LIKE)
					$result->operand = 'LIKE';
					$result->value = "%" . Helpers::getProperty( $value, 'endswith' );
				} else if ( Helpers::hasProperty( $value, '!contains' ) ) { // Key does not contain value (wrap with % and convert to NOT LIKE)
					$result->operand = 'NOT LIKE';
					$result->value = "%" . Helpers::getProperty( $value, '!contains' ) . "%";
				} else if ( Helpers::hasProperty( $value, '!startswith' ) ) { // Key does not start with value (append % and convert to NOT LIKE)
					$result->operand = 'NOT LIKE';
					$result->value = Helpers::getProperty( $value, '!startswith' ) . "%";
				} else if ( Helpers::hasProperty( $value, '!endswith' ) ) { // Key does not end with value (prepend % and convert to NOT LIKE)
					$result->operand = 'NOT LIKE';
					$result->value = "%" . Helpers::getProperty( $value, '!endswith' );
				} else if ( Helpers::hasProperty( $value, 'like' ) ) { // Key is like value (wildcards assumed present)
					$result->operand = 'LIKE';
					$result->value = Helpers::getProperty( $value, 'like' );
				} else if ( Helpers::hasProperty( $value, '!like' ) ) { // Key is not like value (wildcards assumed present)
					$result->operand = 'NOT LIKE';
					$result->value = Helpers::getProperty( $value, '!like' );
				}
			} else {
				$result->value = $value;
			}
		}

		if ( is_array( $result->value ) ) {
			if ( $result->operand === '=' ) {
				$result->operand = 'IN';
			} else if ($result->operand === '!') {
				$result->operand = 'NOT IN';
			} else {
				throw new ModelException( "Array as value for operand {$result->operand} is not supported", 'operation' );
			}
			$result->sql = "{$result->operand} (" . implode( ", ", array_fill( 0, count( $result->value ), "?" ) ) . ")";
		} else {
			$result->sql = "{$result->operand} ?";
		} */

		return $result;
	}

	// Create methods
	protected function beforeCreate( $records ) {
		return $records;
	}
	protected function performCreate( $input ) {
		try {
			$this->dbconn->beginTransaction( );

			$result = [ ];

			foreach ( $records as $record ) {
				$record->user_id = md5( json_encode( $record ) );

				$fieldSql = "";
				$valuesSql = [ ];
				$values = [ ];
				
				foreach ( $record as $field => $value ) {
					$fieldSql .= "`" . $field . "`";
					$valuesSql[] = "?";
					$values[] = $value;
				}

				$sql = "INSERT INTO {$this->tablename} (" . $fieldSql . ") VALUES (" . implode( ",", $valuesSql ) . ");";

				$this->dbconn->prepare( $sql )->execute( $values );
			}

			$this->dbconn->commit( );
		} catch ( Exception $e ) {
			$this->dbconn->rollback( );
			throw $e;
		}
	}
	protected function afterCreate( $records ) {
		return $records;
	}
	public function create( $input ) {
		return $this->afterCreate( $this->performCreate( $this->beforeCreate( $input ) ) );
	}

	// Retrieve methods
	protected function beforeRetrieve( $query ) {
		return $query;
	}
	protected function performRetrieve( $query ) {
		$stmt = $this->dbconn->query( "SELECT * FROM {$this->schema}" );
		$result = [ ];

		foreach ( $stmt as $row ) {
			$result->push( $row );
		}

		return $result;
	}
	protected function afterRetrieve( $result ) {
		return $result;
	}
	public function retrieve( $query ) {
		return $this->afterRetrieve( $this->performRetrieve( $this->beforeRetrieve( $input ) ) );
	}
	public function count( $query ) {

	}

	// Update methods
	protected function beforeUpdate( $records ) {
		return $records;
	}
	protected function performUpdate( $records ) {
		// This should call PDO->prepare() to prepare the SQL
	}
	protected function afterUpdate( $records ) {
		return $records;
	}
	public function update( $input ) {
		return $this->afterUpdate( $this->performUpdate( $this->beforeUpdate( $input ) ) );
	}

	// Delete methods
	protected function beforeDelete( $query ) {
		return $query;
	}
	protected function performDelete( $input ) {
		// This should call PDO->prepare() to prepare the SQL
	}
	protected function afterDelete( $records ) {
		return $records;
	}
	public function delete( $input ) {
		return $this->afterDelete( $this->performDelete( $this->beforeDelete( $input ) ) );
	}
}

?>