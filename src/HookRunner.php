<?php

namespace MediaWiki\GraphQL;

use GraphQL\Type\SchemaConfig;
use MediaWiki\GraphQL\Hook\GraphQLSchemaConfig;
use MediaWiki\HookContainer\HookContainer;

class HookRunner implements GraphQLSchemaConfig {
	/**
	 * @var HookContainer
	 */
	private $container;

	/**
	 * @param HookContainer $container
	 */
	public function __construct( HookContainer $container ) {
		$this->container = $container;
	}

	/**
	 * @inheritDoc
	 */
	public function onGraphQLSchemaConfig( SchemaConfig $config ) {
		$this->container->run(
			'GraphQLSchemaConfig',
			[ $config ]
		);
	}
}
