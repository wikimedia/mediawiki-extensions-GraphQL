<?php

namespace MediaWiki\GraphQL\HookHandler;

use MediaWiki\Hook\WebRequestPathInfoRouterHook;
use MediaWiki\SpecialPage\SpecialPageFactory;

class Router implements WebRequestPathInfoRouterHook {
	/** @var SpecialPageFactory */
	private $specialPageFactory;

	/** @var \TitleFormatter */
	private $titleFormatter;

	/**
	 * @param SpecialPageFactory $specialPageFactory
	 * @param \TitleFormatter $titleFormatter
	 */
	public function __construct(
		SpecialPageFactory $specialPageFactory,
		\TitleFormatter $titleFormatter
	) {
		$this->specialPageFactory = $specialPageFactory;
		$this->titleFormatter = $titleFormatter;
	}

	/**
	 * @inheritDoc
	 */
	public function onWebRequestPathInfoRouter( $router ) {
		$routes = [
			'/graphql' => [ 'GraphQL' ],
			'/graphql/federation' => [ 'GraphQL', 'Federation' ],
		];

		foreach ( $routes as $route => $value ) {
			// Get localized title!
			$name = $this->specialPageFactory->getLocalNameFor( ...$value );
			$text = $this->titleFormatter->getFullText( new \TitleValue( NS_SPECIAL, $name ) );

			$router->addStrict( $route, [
				'title' => $text,
			] );
		}
	}
}
