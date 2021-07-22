<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

class QueryTypeFactory {
	/**
	 * @param PageInterfaceType $pageInterface
	 * @param \IContextSource $context
	 * @param string $prefix
	 * @return ObjectType
	 */
	public function create(
		PageInterfaceType $pageInterface,
		\IContextSource $context,
		string $prefix = ''
	): QueryType {
		return new QueryType(
			$pageInterface,
			$context,
			$prefix
		);
	}
}
