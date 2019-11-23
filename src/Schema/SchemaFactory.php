<?php

namespace MediaWiki\GraphQL\Schema;

use MediaWiki\Config\ServiceOptions;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Utils\TypeInfo;
use MediaWiki\GraphQL\Type\ObjectType;
use MediaWiki\GraphQL\Type\MediaWiki\PageInterfaceType;

// @TODO Write a test to build the schema and validate it.
class SchemaFactory implements Factory {

	/**
	 * @var array
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'GraphQLValidateSchema'
	];

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
	 * @var InterfaceType
	 */
	private $pageRevisions;

	/**
	 * @var InterfaceType
	 */
	private $revisions;

	/**
	 * @var NamespaceInfo
	 */
	private $namespaceInfo;

	/**
	 * @var ServiceOptions
	 */
	private $options;

	/**
	 * Schema Factory.
	 *
	 * @param InterfaceType $query
	 * @param InterfaceType $page
	 * @param InterfaceType $namespace
	 * @param InterfaceType $pageRevisions
	 * @param InterfaceType $revision
	 * @param InterfaceType $revisionSlot
	 * @param InterfaceType $user
	 * @param \NamespaceInfo $namespaceInfo
	 * @param ServiceOptions $options
	 */
	public function __construct(
		InterfaceType $query,
		PageInterfaceType $page,
		InterfaceType $namespace,
		InterfaceType $pageRevisions,
		InterfaceType $revision,
		InterfaceType $revisionSlot,
		InterfaceType $user,
		\NamespaceInfo $namespaceInfo,
		ServiceOptions $options
	) {
		$this->query = $query;
		$this->page = $page;
		$this->namespace = $namespace;
		$this->pageRevisions = $pageRevisions;
		$this->revision = $revision;
		$this->revisionSlot = $revisionSlot;
		$this->user = $user;
		$this->namespaceInfo = $namespaceInfo;
		$this->options = $options;
	}

	/**
	 * @inheritDoc
	 */
	public function create() : Schema {
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
						// Objects.
						new ObjectType( [
							'name' => 'Namespace',
							'fields' => $this->namespace->getFields(),
							'interfaces' => [
								$this->namespace,
							],
						] ),
						new ObjectType( [
							'name' => 'PageRevisions',
							'fields' => $this->pageRevisions->getFields(),
							'interfaces' => [
								$this->pageRevisions,
							],
						] ),
						new ObjectType( [
							'name' => 'Revision',
							'fields' => $this->revision->getFields(),
							'interfaces' => [
								$this->revision,
							]
						] ),
						new ObjectType( [
							'name' => 'RevisionSlot',
							'fields' => $this->revisionSlot->getFields(),
							'interfaces' => [
								$this->revisionSlot,
							]
						] ),
						new ObjectType( [
							'name' => 'User',
							'fields' => $this->user->getFields(),
							'interfaces' => [
								$this->user,
							]
						] ),
				] ),
		] );

		$interfaces = [];

		// Validation should be disabled in production because it is expensive!
		if ( $this->options->get( 'GraphQLValidateSchema' ) === true ) {
			// Create a new schema object here, because it will have to be rebuilt
			// after the hook.
			$interfaces = array_filter( ( new Schema( $config ) )->getTypeMap(), function ( $type ) {
				return $type instanceof InterfaceType;
			} );
		}

		\Hooks::run( 'GraphQLSchemaConfig', [ $config ] );

		$schema = new Schema( $config );

		// Validation should be disabled in production because it is expensive!
		if ( $this->options->get( 'GraphQLValidateSchema' ) === true ) {
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
