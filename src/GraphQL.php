<?php

namespace MediaWiki\GraphQL;

use MediaWiki\MediaWikiServices;

class GraphQL {

	/**
	 * Hook Impelementation: WebRequestPathInfoRouter
	 *
	 * @param \PathRouter $router
	 * @return void
	 */
	public static function onWebRequestPathInfoRouter( \PathRouter $router ) {
		$routes = [
			'/graphql' => [ 'GraphQL' ],
			'/graphql/federation' => [ 'GraphQL', 'Federation' ],
		];

		foreach ( $routes as $route => $value ) {
			// Get localized title!
			$title = \SpecialPage::getTitleValueFor( ...$value );
			$text = MediaWikiServices::getInstance()->getTitleFormatter()->getFullText( $title );

			$router->addStrict( $route, [
				'title' => $text,
			] );
		}
	}
}
