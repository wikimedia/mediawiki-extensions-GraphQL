<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class QueryType extends ObjectType {

	/**
	 * {@inheritdoc}
	 *
	 * @param PageInterfaceType $pageInterface
	 * @param \IContextSource $context
	 * @param string $prefix
	 * @param array $config
	 */
	public function __construct(
		PageInterfaceType $pageInterface,
		\IContextSource $context,
		string $prefix = '',
		array $config = []
	) {
		$default = [
			'name' => $prefix . 'Query',
			'description' => $context->msg( 'graphql-type-mediawiki-query-desc' )->text(),
			'fields' => [
				'page' => [
					'type' => $pageInterface,
					'args' => [
						'id' => Type::int(),
						'title' => Type::string(),
					],
					'resolve' => static function ( $root, $args ) use ( $context ) {
						if ( !isset( $args['id'] ) && !isset( $args['title'] ) ) {
							throw new UserError( $context->msg( 'graphql-page-error-nosource' )->text(), 'nosource' );
						}

						if ( isset( $args['id'] ) && isset( $args['title'] ) ) {
							throw new UserError(
								$context->msg( 'graphql-page-error-multisource' )->text(),
								'multisource'
							);
						}

						if ( isset( $args['id'] ) ) {
							return [
								'pageid' => $args['id']
							];
						}

						if ( isset( $args['title'] ) ) {
							return [
								'title' => $args['title']
							];
						}
					}
				],
			],
		];

		parent::__construct( array_merge( $default, $config ) );
	}

}
