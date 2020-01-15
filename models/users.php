<?php

use LaughingWolf\API\Model as Model;

class users extends Model {
	public function __construct( $dbconn ) {
		$fields = (object) [
			'user_id'		=>	(object) [
				'type'		=>	'CHAR(32)'
			],
			'username'		=>	(object) [
				'type'		=>	'VARCHAR(255)',
				'sortable'	=>	true
			],
			'email'			=>	(object) [
				'type'		=>	'VARCHAR(127)',
				'sortable'	=>	true
			],
			'password'		=>	(object) [
				'type'		=>	'VARCHAR(127)'
			],
			'name'			=>	(object) [
				'type'		=>	'VARCHAR(255)',
				'sortable'	=>	true
			],
			'status_id'		=>	(object) [
				'type'		=>	'INT(10)',
				'sortable'	=>	true
			],
			'created'		=>	(object) [
				'type'		=>	'TIMESTAMP',
				'sortable'	=>	'DESC'
			],
			'updated'		=>	(object) [
				'type'		=>	'TIMESTAMP',
				'sortable'	=>	'DESC'
			],
			'created_by_id'	=>	(object) [
				'type'		=>	'CHAR(32)'
			],
			'updated_by_id'	=>	(object) [
				'type'		=>	'CHAR(32)'
			]
		];

		$associations = (object) [
			'status'	=>	(object) [
				'type'			=>	'hasOne',
				'schema'		=>	'badwolf_master',
				'table'			=>	'statuses',
				'key'			=>	'status_id',
				'foreignKey'	=>	'status_id'
			]
		];

		parent::__construct( 'badwolf_master', 'users', $fields, $associations, $dbconn );
	}

	protected function beforeCreate( $records ) {
		foreach ( $records as $record ) {
			$record->user_id = md5( json_encode( $record ) );
		}

		return $records;
	}

	public function count( $where = '' ) {
		$sql = "SELECT COUNT(*) FROM {$this->tablename}";
		if ( $where ) {
			$sql .= " WHERE $where";
		}
		return $this->dbconn->prepare( $sql )->execute( );
	}

	protected function afterRetrieve( $records ) {
		foreach ( $records as $record ) {
			unset( $record->password );
		}

		return $records;
	}
}

?>