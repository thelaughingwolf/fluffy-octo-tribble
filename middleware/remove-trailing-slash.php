<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response;

// Parse JSON Requests
$GLOBALS['app']->middleware->removeTrailingSlash = function ( Request $req, RequestHandler $handler  ) {
	$uri = $req->getUri( );
	$path = $uri->getPath( );
	
	if ( $path != '/' && substr( $path, -1 ) == '/' ) {
		// permanently redirect paths with a trailing slash
		// to their non-trailing counterpart
		$uri = $uri->withPath( substr( $path, 0, -1 ) );
		
		if ( $req->getMethod( ) == 'GET' ) {
			$res = new Response( );
			return $res
				->withHeader( 'Location', (string) $uri )
				->withStatus( 301 );
		} else {
			$req = $req->withUri( $uri );
		}
	}

	return $handler->handle( $req );
};