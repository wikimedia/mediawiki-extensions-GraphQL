<?php

namespace MediaWiki\GraphQL;

use MediaWiki\GraphQL\SpecialPage\SpecialGraphQL;
use MediaWiki\GraphQL\SpecialPage\SpecialGraphQLSandbox;
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

	/**
	 * Gets the special page.
	 *
	 * @return SpecialGraphQL
	 */
	public static function getSpecialPage() {
		return MediaWikiServices::getInstance()->getService( 'SpecialGraphQL' );
	}

	/**
	 * Gets the sandbox special page.
	 *
	 * @return SpecialGraphQLSandbox
	 */
	public static function getSandboxSpecialPage() {
		return MediaWikiServices::getInstance()->getService( 'SpecialGraphQLSandbox' );
	}
}
