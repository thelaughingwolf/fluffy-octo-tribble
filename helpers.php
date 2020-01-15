<?php

namespace LaughingWolf\API;

function extractFilename( $filename ) {
	$pieces = explode( '/', str_replace( '.php', '', $filename ) );
	return array_pop( $pieces );
}

?>