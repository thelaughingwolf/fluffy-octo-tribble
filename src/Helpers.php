<?php

namespace LaughingWolf\API;

class Helpers {
	public static function extractFilename( $filename ) {
		$pieces = explode( '/', str_replace( '.php', '', $filename ) );
		return array_pop( $pieces );
	}

	// This allows us to check a filter value for any property,
	//   regardless of the property's case
	// Needed for parsing JSON filters from userland
	public static function getProperty( $value, $property ) {
		if ( !is_object( $value ) ) {
			return; // return undefined
		}

		foreach ( $value as $key => $val ) {
			if ( strtolower( $key ) === strtolower( $property ) ) {
				return $val; // May return false, null, or any of the other values we might use to say 'not found'
			}
		}

		return; // return undefined
	}

	// This allows us to check a filter value for any property,
	//   regardless of the property's case
	// Needed for parsing JSON filters from userland
	public static function hasProperty( $value, $property ) {
		$val = Helpers::getProperty( $value, $property );

		if ( isset( $val ) || $val === null ) {
			return true;
		}

		return false;
	}
}

?>