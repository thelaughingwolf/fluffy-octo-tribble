<?php

spl_autoload_register( function( $class ) {
	error_log( "Are we responsible for autoloading $class?" );

	// project-specific namespace prefix
	$prefix = 'LaughingWolf\\API';

	// base directory for the namespace prefix
	$base_dir = __DIR__ . '/src';

	// does the class use the namespace prefix?
	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $len ) !== 0 ) {
		// no, move to the next registered autoloader
		error_log( "This class is not a LaughingWolf\API class!" );
		return;
	}

	// get the relative class name
	$relative_class = substr( $class, $len );

	// replace the namespace prefix with the base directory, replace namespace
	// separators with directory separators in the relative class name, append
	// with .php
	$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

	error_log( "Will attempt to load this class: " . $file );

	// if the file exists, require it
	if ( is_readable( $file ) ) {
		require $file;
	}
});

?>