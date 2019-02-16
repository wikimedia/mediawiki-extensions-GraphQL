<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Type\InterfaceType;
use MediaWiki\GraphQL\Source\ApiSource;

class NamespaceInterfaceType extends InterfaceType {

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
	 * @param array $config
	 */
	public function __construct(
		ApiSource $api,
		PromiseAdapter $promise,
		array $config = []
	) {
		$this->api = $api;
		$this->promise = $promise;

		$getProperty = function ( $namespace, $args, $context, ResolveInfo $info ) {
			$fieldName = $info->fieldName;
			$params = [];

			if ( isset( $namespace[$fieldName] ) ) {
				return $this->promise->createFulfilled( $namespace[$fieldName] );
			}

			return $this->getNamespace( $namespace, $params )->then( function ( $ns ) use ( $fieldName ) {
				return $ns[$fieldName] ?? null;
			} );
		};

		$default = [
			'name' => 'MediaWikiNamespace',
			'description' => wfMessage( 'graphql-type-mediawiki-ns-desc' )->text(),
			'fields' => [
				'id' => [
					'type' => Type::int(),
				],
				'case' => [
					'type' => Type::string(),
					'resolve' => $getProperty,
				],
				'name' => [
					'type' => Type::string(),
					'resolve' => $getProperty,
				],
				'subpages' => [
					'type' => Type::boolean(),
					'resolve' => $getProperty,
				],
				'canonical' => [
					'type' => Type::string(),
					'resolve' => $getProperty,
				],
				'content' => [
					'type' => Type::boolean(),
					'resolve' => $getProperty,
				],
				'nonincludable' => [
					'type' => Type::boolean(),
					'resolve' => $getProperty,
				],
			],
			'resolveType' => function () {
				return 'Namespace';
			},
		];

		parent::__construct( array_merge( $default, $config ) );
	}

	/**
	 * Get Namespace.
	 *
	 * @param int $ns
	 * @return GraphQL\Executor\Promise\Promise
	 */
	protected function getNamespace( $ns ) {
		if ( !isset( $ns['id'] ) ) {
			return $this->promise->createFulfilled( null );
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
