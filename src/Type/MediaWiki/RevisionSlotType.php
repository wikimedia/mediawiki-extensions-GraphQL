<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Source\ApiSource;

class RevisionSlotType extends ObjectType {
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

		$getProperty = function ( $slot, $args, $context, ResolveInfo $info ) {
			$fieldName = $info->fieldName;
			$parmas = [];

			switch ( $fieldName ) {
				case 'size':
					$params = [ 'rvprop' => [ 'ids', 'slotsize' ] ];
					break;
				case 'sha1':
					$params = [ 'rvprop' => [ 'ids', 'slotsha1' ] ];
					break;
				case 'contentformat':
					$params = [ 'rvprop' => [ 'ids', 'content' ] ];
					break;
				default:
					$params = [ 'rvprop' => [ 'ids', $fieldName ] ];
					break;
			}

			return $this->getSlotData( $slot, $params )->then( static function ( $slot ) use ( $fieldName ) {
				return $slot[$fieldName] ?? null;
			} );
		};

		$default = [
			'name' => $prefix . 'RevisionSlot',
			'description' => $context->msg( 'graphql-type-mediawiki-revision-slot-desc' )->text(),
			'fields' => [
				'size' => [
					'type' => Type::int(),
					'resolve' => $getProperty,
				],
				'sha1' => [
					'type' => Type::string(),
					'resolve' => $getProperty,
				],
				'contentmodel' => [
					'type' => Type::string(),
					'resolve' => $getProperty,
				],
				'contentformat' => [
					'type' => Type::string(),
					'resolve' => $getProperty,
				],
				'content' => [
					'type' => Type::string(),
					'resolve' => $getProperty,
				],
			],
		];

		parent::__construct( array_merge( $default, $config ) );
	}

	/**
	 * Get the slot info.
	 *
	 * @param array $slot
	 * @param array $params
	 * @return Promise
	 */
	protected function getSlotData( array $slot, array $params = [] ): Promise {
		$role = $slot['_role'] ?? null;

		$params = array_merge( [
			'action' => 'query',
			'prop' => 'revisions',
			'rvslots' => [ $role ]
		], $params );

		if ( isset( $slot['_revid'] ) ) {
			$params['revids'] = $slot['_revid'];
		}

		return $this->api->request( $params )
			->then( static function ( $data ) use ( $slot ) {
				$role = $slot['_role'] ?? null;
				$pages = $data['query']['pages'] ?? [];

				$revisions = array_reduce( $pages, static function ( $carry, $page ) {
					return array_merge( $carry, $page['revisions'] ?? [] );
				}, [] );

				foreach ( $revisions as $r ) {
					if ( $r['revid'] === $slot['_revid'] ) {
						return $r['slots'][$role] ?? [];
					}
				}

				return [];
			} );
	}
}
