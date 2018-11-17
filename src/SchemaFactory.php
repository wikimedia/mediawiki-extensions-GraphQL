<?php

namespace MediaWiki\GraphQL;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Utils\TypeInfo;
use MediaWiki\GraphQL\Type\ObjectType;
use MediaWiki\GraphQL\Type\MediaWiki\PageInterfaceType;

// @TODO Write a test to build the schema and validate it.
class SchemaFactory {

	/**
	 * @var InterfaceType
	 */
	private $query;

	/**
	 * @var InterfaceType
	 */
	private $page;

	/**
	 * @var InterfaceType
	 */
	private $namespace;

	/**
	 * @var array
	 */
	private $canonicalNamespaces;

	/**
	 * @var bool
	 */
	private $validateSchema;

	/**
	 * Shema Factory.
	 *
	 * @param InterfaceType $query
	 * @param InterfaceType $page
	 * @param InterfaceType $namespace
	 * @param array $canonicalNamespaces
	 * @param bool $validate
	 */
	public function __construct(
		InterfaceType $query,
		PageInterfaceType $page,
		InterfaceType $namespace,
		array $canonicalNamespaces,
		$validate
	) {
		$this->query = $query;
		$this->page = $page;
		$this->namespace = $namespace;
		$this->canonicalNamespaces = $canonicalNamespaces;
		$this->validate = $validate;
	}

	/**
	 * Create the schema.
	 *
	 * @return Schema
	 */
	public function create() {
		$query = new ObjectType( [
			'name' => 'Query',
			'fields' => $this->query->getFields(),
			'interfaces' => [
				$this->query,
			],
		] );

		$config = SchemaConfig::create( [
				'query' => $query,
				'types' => array_merge( $this->getPageTypes(), [
						new ObjectType( [
							'name' => 'Namespace',
							'fields' => $this->namespace->getFields(),
							'interfaces' => [
								$this->namespace,
							]
						] )
				] ),
		] );

		$interfaces = [];

		// Validation should be disabled in production because it is expensive!
		if ( $this->validate ) {
			// Create a new schema object here, because it will have to be rebuilt
			// after the hook.
			$interfaces = array_filter( ( new Schema( $config ) )->getTypeMap(), function ( $type ) {
				return $type instanceof InterfaceType;
			} );
		}

		\Hooks::run( 'GraphQLSchemaConfig', [ $config ] );

		$schema = new Schema( $config );

		// Validation should be disabled in production because it is expensive!
		if ( $this->validate ) {
			$schema->assertValid();

			// Esnure that the interfaces still exist and have not been modified.
			foreach ( $interfaces as $interface ) {
				foreach ( $schema->getTypeMap() as $type ) {
					if ( TypeInfo::isEqualType( $interface, $type ) ) {
						// Same interface found, move to next interface.
						continue 2;
					}
				}

				// The interface is missing or has been modified.
				throw new InvariantViolation(
					wfMessage( 'graphql-schema-error-modified-interface', $interface->name )->text()
				);
			}
		}

		return $schema;
	}

	private function getPageTypes() {
		$pageTypes = [];
		foreach ( $this->canonicalNamespaces as $ns => $title ) {
			// Skip special namespaces.
			if ( $ns < 0 ) {
				continue;
			}

			if ( $ns === 0 ) {
				$title = 'Main';
			}

			// Change namespaces like User_talk to UserTalk
			$pieces = explode( '_', $title );
			$pieces = array_map( function ( $word ) {
				return ucfirst( $word );
			}, $pieces );
			$title = implode( '', $pieces );

			$pageTypes[] = new ObjectType( [
					'name' => $title . 'Page',
					'ns' => $ns,
					'fields' => $this->page->getFields(),
					'interfaces' => [
						$this->page,
					]
			] );
		}

		$this->page->setPageTypes( $pageTypes );

		return $pageTypes;
	}
}
