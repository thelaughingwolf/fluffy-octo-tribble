<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

// Parse JSON Requests
$GLOBALS['app']->middleware->parseJson = function ( Request $req, RequestHandler $handler ) {
	$contentType = $req->getHeaderLine( 'Content-Type' );
	$queryParams = $req->getQueryParams( );

	// This contains the parsed body + the parsed query params (if any)
	$all_params = (object) [ ];

	$req = $req->withAttribute( 'all_params', (object) [ ] );

	// Parse body of request
	if ( strstr( $contentType, 'application/json' ) ) {
		$contents = json_decode( file_get_contents( 'php://input' ), true );
		if ( json_last_error( ) === JSON_ERROR_NONE ) {
			$req = $req->withParsedBody( $contents );
		}
		$all_params = $contents;
	}

	// Parse query params
	foreach ( $req->getQueryParams( ) as $param => $value ) {
		try {
			$jsonValue = json_decode( $value );
			if ( json_last_error( ) === JSON_ERROR_NONE ) {
				$all_params->{$param} = $jsonValue;
			}
		} catch ( Exception $ex ) {
			$all_params->{$param} = $value;
		}
	}

	// Set the combined body & query values here
	$req = $req->withAttribute( 'all_params', $all_params );

	return $handler->handle( $req );
};