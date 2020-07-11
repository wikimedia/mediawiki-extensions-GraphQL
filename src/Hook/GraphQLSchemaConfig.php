<?php

namespace MediaWiki\GraphQL\Hook;

use GraphQL\Type\SchemaConfig;

interface GraphQLSchemaConfig {
	/**
	 * @param SchemaConfig $config
	 */
	public function onGraphQLSchemaConfig( SchemaConfig $config );
}
