<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Definition\ObjecTType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Source\ApiSource;

class UserType extends ObjectType {
	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @var ApiSource
	 */
	protected $api;

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

		$getProperty = function ( $user, $args, $context, ResolveInfo $info ) {
			$fieldName = $info->fieldName;
			$params = [];

			if ( isset( $user[$fieldName] ) ) {
				return $this->promise->createFulfilled( $user[$fieldName] );
			}

			return $this->getUserData( $user, $params )->then( static function ( $u ) use ( $fieldName ) {
				return $u[$fieldName] ?? null;
			} );
		};

		$default = [
			'name' => $prefix . 'User',
			'description' => $context->msg( 'graphql-type-mediawiki-user-desc' )->text(),
			'fields' => [
				'userid' => [
					'type' => Type::int(),
					'resolve' => $getProperty,
				],
				'name' => [
					'type' => Type::string(),
					'resolve' => $getProperty,
				],
			],
		];

		parent::__construct( array_merge( $default, $config ) );
	}

	/**
	 * Get the user info.
	 *
	 * @param array $user
	 * @param array $params
	 * @return Promise
	 */
	protected function getUserData( array $user, array $params = [] ): Promise {
		$params = array_merge( [
			'action' => 'query',
			'list' => 'users',
		], $params );

		if ( isset( $user['name'] ) ) {
			$params['ususers'] = $user['name'];
		}

		if ( isset( $user['userid'] ) ) {
			$params['ususerids'] = $user['userid'];
		}

		return $this->api->request( $params )
			->then( static function ( $data ) use ( $user ) {
				$users = $data['query']['users'] ?? [];

				if ( isset( $user['userid'] ) ) {
					foreach ( $users as $u ) {
						if ( $u['userid'] === $user['userid'] ) {
							return $u;
						}
					}
				}

				if ( isset( $user['name'] ) ) {
					foreach ( $users as $u ) {
						if ( $u['name'] === $user['name'] ) {
							return $u;
						}
					}
				}

				return null;
			} );
	}

}
