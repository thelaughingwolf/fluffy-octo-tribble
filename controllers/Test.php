<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

$GLOBALS['app']->router->get( '/test/queries/custom', function( Request $req, Response $res, $params ) {
	$query = $req->getAttribute( 'all_params' );

	$result = (object) [
		'raw'		=>	$query,
		'parsed'	=>	$GLOBALS['app']->models->user->parseQuery( $query )
	];

	$res->getBody( )->write( json_encode( $result ) );

	return $res;
})->add( $GLOBALS['app']->middleware->returnJson );

$GLOBALS['app']->router->get( '/test/queries/all', function( Request $req, Response $res, $params ) {
	$queries = json_decode( json_encode( \Spyc::YAMLLoad( '../test/queries.yaml' ) ) );
	$result = (object) [
		'queries'	=>	[ ]
	];

	foreach ( $queries as $query ) {
		$result->queries[] = (object) [
			'raw'		=>	$query,
			'parsed'	=>	$GLOBALS['app']->models->user->parseQuery( $query )
		];
	}

	$res->getBody( )->write( json_encode( $result ) );

	return $res;
})->add( $GLOBALS['app']->middleware->returnJson );