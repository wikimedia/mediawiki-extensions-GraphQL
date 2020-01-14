<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Source\ApiSource;
use MediaWiki\GraphQL\Type\InterfaceType;

class PageRevisionsInterfaceType extends InterfaceType {

	/**
	 * @var ApiSource;
	 */
	protected $api;

	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * {@inheritdoc}
	 *
	 * @param ApiSource $api
	 * @param PromiseAdapter $promise
	 * @param InterfaceType $revisionType
	 * @param array $config
	 */
	public function __construct(
		ApiSource $api,
		PromiseAdapter $promise,
		InterfaceType $revisionType,
		array $config = []
	) {
		$this->api = $api;
		$this->promise = $promise;

		$default = [
			'name' => 'MediaWikiPageRevisions',
			'description' => wfMessage( 'graphql-type-mediawiki-page-revisions-desc' )->text(),
			'fields' => [
				'continue' => [
					'type' => Type::string(),
				],
				'edges' => [
					'type' => Type::listOf( Type::nonNull( $revisionType ) ),
				],
			],
			'resolveType' => function ( $value, $context ) {
				$prefix = $context['prefix'] ?? '';
				return $prefix . 'PageRevisions';
			},
		];

		parent::__construct( array_merge( $default, $config ) );
	}
}
