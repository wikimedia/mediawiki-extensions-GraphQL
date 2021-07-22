<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\Promise;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Source\ApiSource;
use MediaWiki\GraphQL\Type\InterfaceType;

class PageInterfaceType extends InterfaceType {
	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @var NamespaceInfo
	 */
	protected $namespaceInfo;

	/**
	 * @var ApiSource
	 */
	protected $api;

	/**
	 * @var string
	 */
	protected $prefix;

	/**
	 * @var ObjectType[]|null
	 */
	protected $pageTypes;

	/**
	 * {@inheritdoc}
	 *
	 * @param PromiseAdapter $promise
	 * @param \NamespaceInfo $namespaceInfo
	 * @param ApiSource $api
	 * @param NamespaceType $namespaceType
	 * @param PageRevisionsType $pageRevisionsType
	 * @param RevisionType $revisionType
	 * @param \IContextSource $context
	 * @param string $prefix
	 * @param array $config
	 */
	public function __construct(
		PromiseAdapter $promise,
		\NamespaceInfo $namespaceInfo,
		ApiSource $api,
		NamespaceType $namespaceType,
		PageRevisionsType $pageRevisionsType,
		RevisionType $revisionType,
		\IContextSource $context,
		string $prefix = '',
		array $config = []
	) {
		$this->namespaceInfo = $namespaceInfo;
		$this->promise = $promise;
		$this->api = $api;
		$this->prefix = $prefix;

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

			return $this->getPageData( $page, $params )->then( static function ( $p ) use ( $fieldName ) {
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
			'name' => $prefix . 'Page',
			'description' => $context->msg( 'graphql-type-mediawiki-page-desc' )->text(),
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
								'edges' => array_map( static function ( $revision ) {
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

					$pageType = $this->pageTypes[ $ns ] ?? null;

					return $pageType;
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
	 * @return Promise
	 */
	protected function makePageRequest( array $page, array $params = [] ): Promise {
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
	protected function findPage( array $data, array $page ): ?array {
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
	 * @return Promise
	 */
	protected function getPageData( array $page, array $params = [] ): Promise {
		return $this->makePageRequest( $page, $params )
			->then( function ( $data ) use ( $page ) {
				return $this->findPage( $data, $page );
			} );
	}

	/**
	 * @inheritDoc
	 */
	public function getTypes(): array {
		if ( $this->pageTypes === null ) {
			$pageTypes = [];
			foreach ( $this->namespaceInfo->getCanonicalNamespaces() as $ns => $title ) {
				// Skip special namespaces.
				if ( $ns < 0 ) {
					continue;
				}

				if ( $ns === 0 ) {
					$title = 'Main';
				}

				// Change namespaces like User_talk to UserTalk
				$pieces = explode( '_', $title );
				$pieces = array_map( static function ( $word ) {
					return ucfirst( $word );
				}, $pieces );
				$title = implode( '', $pieces );

				$pageTypes[] = new ObjectType( [
						'name' => $this->prefix . $title . 'Page',
						'ns' => $ns,
						'fields' => $this->getFields(),
						'interfaces' => [
							$this,
						]
				] );
			}

			$this->pageTypes = $pageTypes;
		}

		return $this->pageTypes;
	}

}
