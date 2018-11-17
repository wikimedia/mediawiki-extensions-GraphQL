<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Type\Definition\Type;
use MediaWiki\GraphQL\Type\InterfaceType;
use MediaWiki\GraphQL\Source\ApiSource;

class PageInterfaceType extends InterfaceType {

	/**
	 * @var ApiSource;
	 */
	protected $api;

	/**
	 * @var ObjectType[];
	 */
	protected $pageTypes;

	/**
	 * {@inheritdoc}
	 *
	 * @param ApiSource $api
	 * @param InterfaceType $namespaceType
	 * @param array $config
	 */
	public function __construct( ApiSource $api, InterfaceType $namespaceType, array $config = [] ) {
		$this->api = $api;

		$getInfo = $this->getPagePropertyParams( [ 'prop' => 'info' ] );

		$default = [
			'name' => 'MediaWikiPageType',
			'description' => wfMessage( 'graphql-type-mediawiki-page-desc' )->text(),
			'fields' => [
				'pageid' => [
					'type' => Type::int(),
					'resolve' => $this->getPageProperty( 'pageid' ),
				],
				'ns' => [
					'type' => $namespaceType,
					'resolve' => function ( $page ) {
						return ( $this->getPageProperty( 'ns' ) )( $page )->then( function ( $id ) {
							return [
								'id' => $id
							];
						} );
					},
				],
				'title' => [
					'type' => Type::string(),
					'resolve' => $this->getPageProperty( 'title' ),
				],
				'contentmodel' => [
					'type' => Type::string(),
					'resolve' => $getInfo( 'contentmodel' )
				],
				'pagelanguage' => [
					'type' => Type::string(),
					'resolve' => $getInfo( 'pagelanguage' )
				],
				'pagelanguagehtmlcode' => [
					'type' => Type::string(),
					'resolve' => $getInfo( 'pagelanguagehtmlcode' )
				],
				'pagelanguagedir' => [
					'type' => Type::string(),
					'resolve' => $getInfo( 'pagelanguagedir' )
				],
				'touched' => [
					'type' => Type::string(),
					'resolve' => $getInfo( 'touched' )
				],
				// @TODO Use Revision Object!
				'lastrevid' => [
					'type' => Type::int(),
					'resolve' => $getInfo( 'lastrevid' )
				],
				'length' => [
					'type' => Type::int(),
					'resolve' => $getInfo( 'length' )
				],
				'new' => [
					'type' => Type::boolean(),
					'resolve' => $getInfo( 'new' )
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
	 * Get the page property.
	 *
	 * @param array $params
	 * @return callable
	 */
	protected function getPagePropertyParams( array $params ) {
		return function ( $prop ) use ( $params ) {
			return $this->getPageProperty( $prop, $params );
		};
	}

	/**
	 * Get the page property.
	 *
	 * @param string $prop
	 * @param array $params
	 * @return callable
	 */
	protected function getPageProperty( $prop, array $params = [] ) {
		return function ( $page ) use ( $prop, $params ) {
		 if ( isset( $page[$prop] ) ) {
			 return $page[$prop];
		 }

		 return $this->getPageData( $page, $params )->then( function ( $page ) use ( $prop ) {
			 if ( $page === null ) {
				 return null;
			 }

			 return $page[$prop] ?? null;
		 } );
	 };
	}

	/**
	 * Get the page info.
	 *
	 * @param array $page
	 * @param array $params
	 * @return GraphQL\Executor\Promise\Promise
	 */
	protected function getPageData( array $page, array $params = [] ) {
		$params = array_merge( [
			'action' => 'query',
		], $params );

		if ( isset( $page['title'] ) ) {
			$params['titles'] = $page['title'];
		}

		if ( isset( $page['pageid'] ) ) {
			$params['pageids'] = $page['pageid'];
		}

		return $this->api->request( $params )
			->then( function ( $data ) use ( $page ) {
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
