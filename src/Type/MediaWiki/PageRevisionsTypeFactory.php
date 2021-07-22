<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

class PageRevisionsTypeFactory {
	/**
	 * @param RevisionType $revisionType
	 * @param \IContextSource $context
	 * @param string $prefix
	 * @return PageRevisionsType
	 */
	public function create(
		RevisionType $revisionType,
		\IContextSource $context,
		string $prefix = ''
	): PageRevisionsType {
		return new PageRevisionsType(
			$revisionType,
			$context,
			$prefix
		);
	}
}
