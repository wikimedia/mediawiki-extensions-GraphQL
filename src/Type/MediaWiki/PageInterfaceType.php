<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Type\InterfaceType;
use MediaWiki\GraphQL\Source\ApiSource;

class PageInterfaceType extends InterfaceType {

	/**
	 * @var ApiSource;
	 */
	protected $api;

	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @var ObjectType[];
	 */
	protected $pageTypes;

	/**
	 * {@inheritdoc}
	 *
	 * @param ApiSource $api
	 * @param PromiseAdapter $promise
	 * @param InterfaceType $namespaceType
	 * @param InterfaceType $pageRevisionsType
	 * @param InterfaceType $revisionType
	 * @param array $config
	 */
	public function __construct(
		ApiSource $api,
		PromiseAdapter $promise,
		InterfaceType $namespaceType,
		InterfaceType $pageRevisionsType,
		InterfaceType $revisionType,
		array $config = []
	) {
		$this->api = $api;
		$this->promise = $promise;

		$getProperty = function ( $page, $args, $context, ResolveInfo $info ) {
			$fieldName = $info->fieldName;
			$params = [];

			if ( isset( $page[$fieldName] ) ) {
				return $this->promise->createFulfilled( $page[$fieldName] );
			}

			switch ( $fieldName ) {
				case 'pageid':
				case 'ns':
				case 'title':
					$params = [];
					break;
				case 'contentmodel':
				case 'pagelanguage':
				case 'pagelanguagehtmlcode':
				case 'pagelanguagedir':
				case 'touched':
				case 'lastrev':
				case 'length':
				case 'new':
					$params = [ 'prop' => [ 'info' ] ];
					break;
				default:
					$params = [ 'prop' => [ $fieldName ] ];
					break;
			}

			return $this->getPageData( $page, $params )->then( function ( $p ) use ( $fieldName ) {
				switch ( $fieldName ) {
					case 'ns':
						return [
							'id' => $p['ns'] ?? null,
						];
					case 'lastrev':
						return [
							'revid' => $p['lastrevid'] ?? null,
						];
					case 'new':
						return $p[$fieldName] ?? false;
					default:
						return $p[$fieldName] ?? null;
				}
			} );
		};

		$default = [
			'name' => 'MediaWikiPageType',
			'description' => wfMessage( 'graphql-type-mediawiki-page-desc' )->text(),
			'fields' => [
				'pageid' => [
					'type' => Type::int(),
					'resolve' => $getProperty,
				],
				'ns' => [
					'type' => $namespaceType,
					'resolve' => $getProperty,
				],
				'title' => [
					'type' => Type::string(),
					'resolve' => $getProperty,
				],
				'contentmodel' => [
					'type' => Type::string(),
					'resolve' => $getProperty
				],
				'pagelanguage' => [
					'type' => Type::string(),
					'resolve' => $getProperty
				],
				'pagelanguagehtmlcode' => [
					'type' => Type::string(),
					'resolve' => $getProperty
				],
				'pagelanguagedir' => [
					'type' => Type::string(),
					'resolve' => $getProperty
				],
				'touched' => [
					'type' => Type::string(),
					'resolve' => $getProperty
				],
				'lastrev' => [
					'type' => $revisionType,
					'resolve' => $getProperty,
				],
				'length' => [
					'type' => Type::int(),
					'resolve' => $getProperty
				],
				'new' => [
					'type' => Type::boolean(),
					'resolve' => $getProperty
				],
				'revisions' => [
					'type' => $pageRevisionsType,
					'args' => [
						'limit' => [
							// @TODO Should we support 'max'?
							'type' => Type::int()
						],
						'section' => [
							'type' => Type::int()
						],
						'startid' => [
							'type' => Type::int(),
						],
						'endid' => [
							'type' => Type::int(),
						],
						'start' => [
							'type' => Type::string(),
						],
						'end' => [
							'type' => Type::string(),
						],
						'dir' => [
							// @TODO Use an enum with the real values.
							'type' => Type::string(),
						],
						'excludeuser' => [
							'type' => Type::string(),
						],
						'continue' => [
							'type' => Type::string(),
						],
					],
					'resolve' => function ( $page, $args ) {
						$params = [
							'prop' => 'revisions',
						];

						foreach ( $args as $key => $value ) {
							$params[ 'rv' . $key ] = $value;
						}

						return $this->makePageRequest( $page, $params )->then( function ( $data ) use ( $page ) {
							$p = $this->findPage( $data, $page );
							return [
								'continue' => $data['continue']['rvcontinue'] ?? null,
								'edges' => array_map( function ( $revision ) {
									return [
										'revid' => $revision['revid'] ?? null,
									];
								}, $p['revisions'] ?? [] ),
							];
						} );
					}
				],
			],
			'resolveType' => function ( $page ) {
				return $this->getPageData( $page )->then( function ( $page ) {
					if ( $page === null ) {
						return null;
					}

					$ns = $page['ns'] ?? null;

					if ( $ns === null ) {
						return null;
					}

					return $this->pageTypes[ $ns ] ?? null;
				} );
			},
		];

		parent::__construct( array_merge( $default, $config ) );
	}

	/**
	 * Make a PageRequest
	 *
	 * @param array $page
	 * @param array $params
	 * @return GraphQL\Executor\Promise\Promise
	 */
	protected function makePageRequest( array $page, array $params = [] ) {
		$params = array_merge( [
			'action' => 'query',
		], $params );

		if ( isset( $page['title'] ) ) {
			$params['titles'] = $page['title'];
		}

		if ( isset( $page['pageid'] ) ) {
			$params['pageids'] = $page['pageid'];
		}

		return $this->api->request( $params );
	}

	/**
	 * Find the page from a repsonse.
	 *
	 * @param array $data
	 * @param array $page
	 * @return array|null
	 */
	protected function findPage( $data, $page ) {
		$pages = $data['query']['pages'] ?? [];

		if ( isset( $page['pageid'] ) ) {
			$id = $page['pageid'];
			return $pages[$id] ?? null;
		}

		foreach ( $pages as $p ) {
			if ( $p['title'] === $page['title'] ) {
				return $p;
			}
		}

		return null;
	}

	/**
	 * Get the page info.
	 *
	 * @param array $page
	 * @param array $params
	 * @return GraphQL\Executor\Promise\Promise
	 */
	protected function getPageData( array $page, array $params = [] ) {
		return $this->makePageRequest( $page, $params )
			->then( function ( $data ) use ( $page ) {
				return $this->findPage( $data, $page );
			} );
	}

	/**
	 * Set Page Types.
	 *
	 * @param ObjectType[] $types
	 */
	public function setPageTypes( $types ) {
		$this->pageTypes = $types;
	}

}
