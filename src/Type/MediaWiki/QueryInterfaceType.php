<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Type\InterfaceType;

class QueryInterfaceType extends InterfaceType {

	/**
	 * {@inheritdoc}
	 *
	 * @param InterfaceType $pageInterface
	 * @param array $config
	 */
	public function __construct( InterfaceType $pageInterface, array $config = [] ) {
		$default = [
			'name' => 'MediaWikiQuery',
			'description' => wfMessage( 'graphql-type-mediawiki-query-desc' )->text(),
			'fields' => [
				'page' => [
					'type' => $pageInterface,
					'args' => [
						'id' => Type::int(),
						'title' => Type::string(),
					],
					'resolve' => function ( $root, $args ) {
						if ( !isset( $args['id'] ) && !isset( $args['title'] ) ) {
							throw new UserError( wfMessage( 'graphql-page-error-nosource' )->text(), 'nosource' );
						}

						if ( isset( $args['id'] ) && isset( $args['title'] ) ) {
							throw new UserError( wfMessage( 'graphql-page-error-multisource' )->text(), 'multisource' );
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
			'resolveType' => function () {
				return 'Query';
			},
		];

		parent::__construct( array_merge( $default, $config ) );
	}

}
