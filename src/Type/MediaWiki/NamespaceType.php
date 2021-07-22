<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Source\ApiSource;

class NamespaceType extends ObjectType {
	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @var ApiSource
	 */
	protected $api;

	/**
	 * @var \IContextSource
	 */
	protected $context;

	/**
	 * {@inheritdoc}
	 *
	 * @param PromiseAdapter $promise
	 * @param ApiSource $api
	 * @param \IContextSource $context
	 * @param string $prefix
	 * @param array $config
	 */
	public function __construct(
		PromiseAdapter $promise,
		ApiSource $api,
		\IContextSource $context,
		string $prefix = '',
		array $config = []
	) {
		$this->promise = $promise;
		$this->api = $api;

		$getProperty = function ( $namespace, $args, $context, ResolveInfo $info ) {
			$fieldName = $info->fieldName;
			$params = [];

			if ( isset( $namespace[$fieldName] ) ) {
				return $this->promise->createFulfilled( $namespace[$fieldName] );
			}

			return $this->getNamespace( $namespace, $params )->then( static function ( $ns ) use ( $fieldName ) {
				return $ns[$fieldName] ?? null;
			} );
		};

		$default = [
			'name' => $prefix . 'Namespace',
			'description' => $context->msg( 'graphql-type-mediawiki-ns-desc' )->text(),
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
		];

		parent::__construct( array_merge( $default, $config ) );
	}

	/**
	 * Get Namespace.
	 *
	 * @param int $ns
	 * @return Promise
	 */
	protected function getNamespace( int $ns ): Promise {
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
			->then( static function ( $data ) use ( $id ) {
				$namespaces = $data['query']['namespaces'] ?? [];

				return $namespaces[$id] ?? null;
			} );
	}
}
