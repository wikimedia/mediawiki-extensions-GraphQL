<?php

namespace MediaWiki\GraphQL;

use MediaWiki\MediaWikiServices;
use MediaWiki\GraphQL\SpecialPage\SpecialGraphQL;
use MediaWiki\GraphQL\SpecialPage\SpecialGraphQLSandbox;

class GraphQL {

	/**
	 * Hook Impelementation: WebRequestPathInfoRouter
	 *
	 * @param \PathRouter $router
	 * @return void
	 */
	public static function onWebRequestPathInfoRouter( \PathRouter $router ) {
		// Get localized title!
		$title = \SpecialPage::getTitleValueFor( 'GraphQL' );
		$text = MediaWikiServices::getInstance()->getTitleFormatter()->getFullText( $title );

		$router->addStrict( '/graphql', [
			'title' => $text,
		] );
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
