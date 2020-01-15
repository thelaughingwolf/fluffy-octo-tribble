<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

// Parse JSON Requests
$GLOBALS['app']->router->add( function ( Request $req, RequestHandler $handler ) {
	$contentType = $req->getHeaderLine( 'Content-Type' );
	$queryParams = $req->getQueryParams( );

	// This contains the parsed body + the parsed query params (if any)
	$params = (object) [ ];

	$req = $req->withAttribute( 'params', (object) [ ] );

	if ( strstr( $contentType, 'application/json' ) ) {
		$contents = json_decode( file_get_contents( 'php://input' ), true );
		if ( json_last_error( ) === JSON_ERROR_NONE ) {
			$req = $req->withParsedBody( $contents );
		}
		$params = $contents;
	}

	foreach ( $req->getQueryParams( ) as $param => $value ) {
		try {
			$jsonValue = json_decode( $value );
			if ( json_last_error( ) === JSON_ERROR_NONE ) {
				$params->{$param} = $jsonValue;
			}
		} catch ( Exception $ex ) {
			$params->{$param} = $value;
		}
	}

	// Set the combined body & query values here
	$req = $req->withAttribute( 'params', $params );

	return $handler->handle( $req );
});

?>