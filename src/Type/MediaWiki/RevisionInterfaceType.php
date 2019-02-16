<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\GraphQL\Type\InterfaceType;
use MediaWiki\GraphQL\Source\ApiSource;

class RevisionInterfaceType extends InterfaceType {

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
	 * @param SlotRoleRegistry $slotRoleRegistery
	 * @param InterfaceType $userType
	 * @param InterfaceType $slotType
	 * @param array $config
	 */
	public function __construct(
		ApiSource $api,
		PromiseAdapter $promise,
		SlotRoleRegistry $slotRoleRegistery,
		InterfaceType $userType,
		InterfaceType $slotType,
		array $config = []
	) {
		$this->api = $api;
		$this->promise = $promise;

		$getProperty = function ( $revision, $args, $context, ResolveInfo $info ) {
			$fieldName = $info->fieldName;
			$params = [];

			if ( isset( $revision[$fieldName] ) ) {
				return $this->promise->createFulfilled( $revision[$fieldName] );
			}

			switch ( $fieldName ) {
				case 'revid':
				case 'parent':
				case 'slots':
					$params = [ 'rvprop' => [ 'ids' ] ];
					break;
				case 'user':
					$params = [ 'rvprop' => [ 'ids', 'user', 'userid' ] ];
					break;
				case 'anon':
					$params = [ 'rvprop' => [ 'ids', 'user' ] ];
					break;
				default:
					$params = [ 'rvprop' => [ 'ids', $fieldName ] ];
					break;
			}

			return $this->getRevisionData( $revision, $params )->then( function ( $r ) use ( $fieldName ) {
				switch ( $fieldName ) {
					case 'parent':
						return [
							'revid' => $r['parentid'] ?? null,
						];
					case 'user';
						return [
							'userid' => $r['userid'] ?? null,
							'name' => $r['user'] ?? null,
						];
					case 'anon':
						return $r[$fieldName] ?? false;
					case 'tags':
					case 'roles':
						return $r[$fieldName] ?? [];
					case 'slots':
						return [
							'revid' => $r['revid'] ?? null
						];
					default:
						return $r[$fieldName] ?? null;
				}
			} );
		};

		$default = [
			'name' => 'MediaWikiRevision',
			'description' => wfMessage( 'graphql-type-mediawiki-revision-desc' )->text(),
			'fields' => [
				'revid' => [
					'type' => Type::int(),
					'resolve' => $getProperty,
				],
				'parent' => [
					'type' => $this,
					'resolve' => $getProperty,
				],
				'user' => [
					'type' => $userType,
					'resolve' => $getProperty,
				],
				'anon' => [
					'type' => Type::boolean(),
					'resolve' => $getProperty,
				],
				'timestamp' => [
					'type' => Type::string(),
					'resolve' => $getProperty
				],
				'size' => [
					'type' => Type::int(),
					'resolve' => $getProperty,
				],
				'sha1' => [
					'type' => Type::string(),
					'resolve' => $getProperty,
				],
				'comment' => [
					'type' => Type::string(),
					'resolve' => $getProperty
				],
				'parsedcomment' => [
					'type' => Type::string(),
					'resolve' => $getProperty
				],
				'tags' => [
					'type' => Type::listOf( Type::nonNull( Type::string() ) ),
					'resolve' => $getProperty
				],
				'roles' => [
					'type' => Type::listOf( Type::nonNull( Type::string() ) ),
					'resolve' => $getProperty
				],
				'slot' => [
					'type' => $slotType,
					'args' => [
						'role' => [
							'type' => Type::nonNull( Type::string() ),
						],
					],
					'resolve' => function ( $revision, $args ) use ( $slotRoleRegistery ) {
						if (
							isset( $args['role'] )
							&& in_array( $args['role'], $slotRoleRegistery->getKnownRoles() )
						) {
							return [
								'_role' => $args['role'],
								'_revid' => $revision['revid'],
							];
						}

						return null;
					},
				],
				'slots' => [
					'type' => Type::nonNull( Type::listOf( $slotType ) ),
					'args' => [
						'role' => [
							'type' => Type::listOf( Type::string() ),
						],
					],
					'resolve' => function ( $revision, $args ) use ( $slotRoleRegistery ) {
						$roles = [];
						if ( !isset( $args['role'] ) ) {
							$roles = $slotRoleRegistery->getKnownRoles();
						} else {
							$roles = array_intersect( $args['role'], $slotRoleRegistery->getKnownRoles() );
						}

						return array_map( function ( $role ) use ( $revision ) {
							return [
								'_role' => $role,
								'_revid' => $revision['revid'],
							];
						}, $roles );
					},
				],
			],
			'resolveType' => function () {
				return 'Revision';
			},
		];

		parent::__construct( array_merge( $default, $config ) );
	}

	/**
	 * Get the revision info.
	 *
	 * @param array $revision
	 * @param array $params
	 * @return GraphQL\Executor\Promise\Promise
	 */
	protected function getRevisionData( array $revision, array $params = [] ) {
		$params = array_merge( [
			'action' => 'query',
			'prop' => 'revisions',
		], $params );

		if ( isset( $revision['revid'] ) ) {
			$params['revids'] = $revision['revid'];
		}

		return $this->api->request( $params )
			->then( function ( $data ) use ( $revision, $params ) {
				$pages = $data['query']['pages'] ?? [];

				$revisions = array_reduce( $pages, function ( $carry, $page ) {
					return array_merge( $carry, $page['revisions'] ?? [] );
				}, [] );

				foreach ( $revisions as $r ) {
					if ( $r['revid'] === $revision['revid'] ) {
						return $r;
					}
				}

				return null;
			} );
	}

}
