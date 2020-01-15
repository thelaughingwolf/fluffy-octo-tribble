<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// Load users
$GLOBALS['app']->router->get( '/users', function ( Request $req, Response $res, $args ) {
	$result = [
		'records'	=>	$GLOBALS['app']->models->users->retrieve( ),
		'total'		=>	$GLOBALS['app']->models->users->count( )
	];
	$res->getBody( )->write( json_encode( $result ) );
	return $res;
})->add($returnJson);

?>