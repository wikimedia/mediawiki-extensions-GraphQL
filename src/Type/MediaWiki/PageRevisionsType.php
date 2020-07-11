<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class PageRevisionsType extends ObjectType {
	/**
	 * {@inheritdoc}
	 *
	 * @param RevisionType $revisionType
	 * @param \IContextSource $context
	 * @param string $prefix
	 * @param array $config
	 */
	public function __construct(
		RevisionType $revisionType,
		\IContextSource $context,
		string $prefix = '',
		array $config = []
	) {
		$default = [
			'name' => $prefix . 'PageRevisions',
			'description' => $context->msg( 'graphql-type-mediawiki-page-revisions-desc' )->text(),
			'fields' => [
				'continue' => [
					'type' => Type::string(),
				],
				'edges' => [
					'type' => Type::listOf( Type::nonNull( $revisionType ) ),
				],
			],
		];

		parent::__construct( array_merge( $default, $config ) );
	}
}
