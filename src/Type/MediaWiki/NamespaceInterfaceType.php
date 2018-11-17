<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Type\InterfaceType;
use MediaWiki\GraphQL\Source\ApiSource;

class NamespaceInterfaceType extends InterfaceType {

	/**
	 * @var ApiSource;
	 */
	protected $api;

	/**
	 * {@inheritdoc}
	 *
	 * @param ApiSource $api
	 * @param array $config
	 */
	public function __construct( ApiSource $api, array $config = [] ) {
		$this->api = $api;

		$default = [
			'name' => 'MediaWikiNamespace',
			'description' => wfMessage( 'graphql-type-mediawiki-ns-desc' )->text(),
			'fields' => [
				'id' => [
					'type' => Type::int(),
				],
				'case' => [
					'type' => Type::string(),
					'resolve' => $this->getProperty( 'case' ),
				],
				'name' => [
					'type' => Type::string(),
					'resolve' => $this->getProperty( 'name' ),
				],
				'subpages' => [
					'type' => Type::boolean(),
					'resolve' => $this->getProperty( 'subpages' ),
				],
				'canonical' => [
					'type' => Type::string(),
					'resolve' => $this->getProperty( 'canonical' ),
				],
				'content' => [
					'type' => Type::boolean(),
					'resolve' => $this->getProperty( 'content' ),
				],
				'nonincludable' => [
					'type' => Type::boolean(),
					'resolve' => $this->getProperty( 'nonincludable' ),
				],
			],
			'resolveType' => function () {
				return 'Namespace';
			},
		];

		parent::__construct( array_merge( $default, $config ) );
	}

	/**
	 * Get the property.
	 *
	 * @param string $prop
	 * @return callable
	 */
	protected function getProperty( $prop ) {
		return function ( $ns ) use ( $prop ) {
			if ( isset( $ns[$prop] ) ) {
				return $ns[$prop];
			}

			return $this->getNamespace( $ns )->then( function ( $ns ) use ( $prop ) {
				if ( $ns === null ) {
					return null;
				}

				return $ns[$prop] ?? null;
			} );
		};
	}

	/**
	 * Get Namespace.
	 *
	 * @param int $ns
	 * @return GraphQL\Executor\Promise\Promise
	 */
	protected function getNamespace( $ns ) {
		if ( !isset( $ns['id'] ) ) {
			return null;
		}

		$id = $ns['id'];

		$params = [
			'action' => 'query',
			'meta' => 'siteinfo',
			'siprop' => 'namespaces',
		];

		return $this->api->request( $params )
			->then( function ( $data ) use ( $id ) {
				$namespaces = $data['query']['namespaces'] ?? [];

				return $namespaces[$id] ?? null;
			} );
	}
}
