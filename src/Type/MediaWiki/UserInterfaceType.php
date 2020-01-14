<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Source\ApiSource;
use MediaWiki\GraphQL\Type\InterfaceType;

class UserInterfaceType extends InterfaceType {

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

		$getProperty = function ( $user, $args, $context, ResolveInfo $info ) {
			$fieldName = $info->fieldName;
			$params = [];

			if ( isset( $user[$fieldName] ) ) {
				return $this->promise->createFulfilled( $user[$fieldName] );
			}

			return $this->getUserData( $user, $params )->then( function ( $u ) use ( $fieldName ) {
				return $u[$fieldName] ?? null;
			} );
		};

		$default = [
			'name' => 'MediaWikiUser',
			'description' => wfMessage( 'graphql-type-mediawiki-user-desc' )->text(),
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
			'resolveType' => function ( $value, $context ) {
				$prefix = $context['prefix'] ?? '';
				return $prefix . 'User';
			},
		];

		parent::__construct( array_merge( $default, $config ) );
	}

	/**
	 * Get the user info.
	 *
	 * @param array $user
	 * @param array $params
	 * @return GraphQL\Executor\Promise\Promise
	 */
	protected function getUserData( array $user, array $params = [] ) {
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
			->then( function ( $data ) use ( $user ) {
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
