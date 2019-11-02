<?php

namespace MediaWiki\GraphQL;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Schema;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\SchemaConfig;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\SchemaPrinter;
use GraphQL\Utils\TypeInfo;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\ObjectType as GraphQLObjectType;
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
	 * @var bool
	 */
	private $validateSchema;

	/**
	 * Shema Factory.
	 *
	 * @param InterfaceType $query
	 * @param InterfaceType $page
	 * @param InterfaceType $namespace
	 * @param InterfaceType $pageRevisions
	 * @param InterfaceType $revision
	 * @param InterfaceType $revisionSlot
	 * @param InterfaceType $user
	 * @param \NamespaceInfo $namespaceInfo
	 * @param bool $validate
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
		$validate
	) {
		$this->query = $query;
		$this->page = $page;
		$this->namespace = $namespace;
		$this->pageRevisions = $pageRevisions;
		$this->revision = $revision;
		$this->revisionSlot = $revisionSlot;
		$this->user = $user;
		$this->namespaceInfo = $namespaceInfo;
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
						] ),
						new ObjectType( [
							'name' => 'PageRevisions',
							'fields' => $this->pageRevisions->getFields(),
							'interfaces' => [
								$this->pageRevisions,
							]
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

		// Apollo Federation
		$any = new StringType( [
			'name' => '_Any',
		] );

		$fieldset = new StringType( [
			'name' => '_FieldSet',
		] );

		$entities = array_filter( $schema->getTypeMap(), function ( Type $type ) {
			if ( !( $type instanceof GraphQLObjectType ) ) {
				return false;
			}

			if ( $type->astNode === null ) {
				return false;
			}

			$directiveNodes = $type->astNode->directives;

			return array_reduce( iterator_to_array( $directiveNodes->getIterator() ),
				function ( bool $carry, DirectiveNode $node ) : bool {
					if ( $node->name->kind === 'Name' && $node->name->value === 'key' ) {
						return true;
					}
					return $carry;
				}, false );
		} );

		$service = new ObjectType( [
			'name' => '_Service',
			'fields' => [
				'sdl' => [
					'type' => Type::string(),
				],
			]
		] );

		$fields = [
			'_service' => [
				'type' => $service,
				'resolve' => function ( $rootValue, $args, $context, ResolveInfo $info ) {
					return [
						'sdl' => SchemaPrinter::doPrint( $info->schema ),
					];
				}
			],
		];

		if ( count( $entities ) > 0 ) {
			$entity = new UnionType( [
				'name' => '_Entity',
				'types' => array_map( function ( Type $type ) : string {
						return $type->name;
				}, $this->types ),
			] );
			$fields['_entities'] = [
				'type' => Type::nonNull( Type::listOf( $entity ) ),
				'args' => [
					'representations' => [
						'type' => Type::nonNull( Type::listOf( Type::nonNull( $any ) ) ),
					],
				],
				'resolve' => function ( $rootValue, $args, $context, ResolveInfo $info ) {
					$representations = $args['representations'];
					return array_map( function ( $representation ) use ( $info ) {
							$typeName = $representation['__typename'];
							$type = $info->schema->getType( $typeName );
							if ( !$type || $type instanceof GraphQLObjectType === false ) {
								throw new \Exception(
									'The _entities resolver tried to load an entity for type"'
									. $typeName
									. '", but no object type of that name was found in the schema'
								);
							}
							$resolver = $type->resolveFieldFn ?: function () use ( $representation ) {
								return $representation;
							};
							return $resolver();
					}, $representations );
				},
			];
		}

		$types = $schema->getConfig()->getTypes();
		$types[] = $any;
		$types[] = $fieldset;
		$schema->getConfig()->setTypes( $types );

		$query = $schema->getConfig()->getQuery()->config;
		$query['fields'] = array_merge( $query['fields'], $fields );
		$schema->getConfig()->setQuery( new ObjectType( $query ) );

		$directives = $schema->getConfig()->getDirectives();
		$directives[] = new Directive( [
			'name' => 'external',
			'locations' => [
				DirectiveLocation::FIELD_DEFINITION,
			],
		] );
		$directives[] = new Directive( [
			'name' => 'requires',
			'locations' => [
				DirectiveLocation::FIELD_DEFINITION,
			],
			'args' => [
				'fields' => [
					'type' => Type::nonNull( $fieldset ),
				],
			],
		] );
		$directives[] = new Directive( [
			'name' => 'requires',
			'locations' => [
				DirectiveLocation::FIELD_DEFINITION,
			],
			'args' => [
				'fields' => [
					'type' => Type::nonNull( $fieldset ),
				],
			],
		] );
		$directives[] = new Directive( [
			'name' => 'provides',
			'locations' => [
				DirectiveLocation::FIELD_DEFINITION,
			],
			'args' => [
				'fields' => [
					'type' => Type::nonNull( $fieldset ),
				],
			],
		] );
		$directives[] = new Directive( [
			'name' => 'key',
			'locations' => [
				DirectiveLocation::OBJECT,
				DirectiveLocation::IFACE,
			],
			'args' => [
				'fields' => [
					'type' => Type::nonNull( $fieldset ),
				],
			],
		] );
		$directives[] = new Directive( [
			'name' => 'extends',
			'locations' => [
				DirectiveLocation::OBJECT,
				DirectiveLocation::IFACE,
			],
		] );
		$schema->getConfig()->setDirectives( $directives );

		// Rebuild the schema to ensure everything is up to date.
		return new Schema( $schema->getConfig() );
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
