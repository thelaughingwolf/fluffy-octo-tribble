<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

// Return JSON data
$GLOBALS['app']->middleware->returnJson = function( Request $req, RequestHandler $handler ) {
	$res = $handler->handle( $req );
	$res = $res->withHeader( 'Content-Type', 'application/json' );
	return $res;
};