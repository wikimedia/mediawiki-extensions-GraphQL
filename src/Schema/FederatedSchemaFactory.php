<?php

namespace MediaWiki\GraphQL\Schema;

use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType as GraphQLObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\SchemaPrinter;
use MediaWiki\GraphQL\Type\ObjectType;

class FederatedSchemaFactory {
	/**
	 * @var SchemaFactory
	 */
	private $schemaFactory;

	/**
	 * @var string
	 */
	private $wikiId;

	/**
	 * Federated Schema Factory.
	 *
	 * @param SchemaFactory $schemaFactory
	 * @param string $wikiId
	 */
	public function __construct(
		SchemaFactory $schemaFactory,
		string $wikiId
	) {
		$this->schemaFactory = $schemaFactory;
		$this->wikiId = $wikiId;
	}

	/**
	 * Get the wiki prefix.
	 *
	 * @return string
	 */
	public function getPrefix(): string {
		return implode( '', array_map( static function ( $part ) {
			return ucfirst( $part );
		}, explode( '-', $this->wikiId ) ) );
	}

	/**
	 * @param \IContextSource $context
	 * @return Schema
	 */
	public function create( \IContextSource $context ): Schema {
		$schema = $this->schemaFactory->create( $context, $this->getPrefix() );

		$config = clone $schema->getConfig();

		// Apollo Federation
		$any = new StringType( [
			'name' => '_Any',
		] );

		$fieldset = new StringType( [
			'name' => '_FieldSet',
		] );

		// Rename the query and move it to the types.
		$siteQuery = $config->getQuery();
		$config->setQuery( null );

		$types[] = $siteQuery;

		$any = new StringType( [
			'name' => '_Any',
		] );
		$types[] = $any;

		$fieldset = new StringType( [
			'name' => '_FieldSet',
		] );
		$types[] = $fieldset;

		$entities = [];
		// $entities = array_filter( $schema->getTypeMap(), function ( Type $type ) {
		// 	if ( !( $type instanceof GraphQLObjectType ) ) {
		// 		return false;
		// 	}

		// 	if ( !isset( $type->config['directives'] ) || !is_array( $type->config['directives'] ) ) {
		// 		return false;
		// 	}

		// 	foreach ( $type->config['directives'] as $directive ) {
		// 		if ( is_array( $directive ) ) {
		// 			if ( isset( $directive['name'] ) && $directive['name'] === 'key' ) {
		// 				return true;
		// 			}
		// 		} elseif ( $direcive->name === 'key' ) {
		// 			return true;
		// 		}

		// 		return false;
		// 	}
		// } );

		$service = new ObjectType( [
			'name' => '_Service',
			'fields' => [
				'sdl' => [
					'type' => Type::string(),
				],
			]
		] );
		$types[] = $service;

		$fields = [
			$fieldName => [
				'type' => Type::nonNull( $siteQuery ),
				'resolve' => static function () {
					return [];
				},
			],
			'_service' => [
				'type' => $service,
				'resolve' => static function ( $rootValue, $args, $context, ResolveInfo $info ) {
					$config = clone $info->schema->getConfig();

					// Remove the federation directives.
					// Why do you have to do this???
					$federationDirectives = [
						'external',
						'requires',
						'provides',
						'key',
						'extends',
					];
					$directives = array_filter(
						$config->getDirectives(),
						static function ( $directives ) use ( $federationDirectives ) {
							return in_array( $directive->name, $federationDirectives );
						}
					);
					$config->setDirectives( $directives );

					// Remove the federation query.
					// Why do you have to do this???
					$query = $config->getQuery();
					$queryConfig = $query->config;
					$queryConfig['fields'] = array_filter( $queryConfig['fields'], static function ( $name ) {
						return $name !== '_service';
					}, ARRAY_FILTER_USE_KEY );
					$config->setQuery( new ObjectType( $queryConfig ) );

					return [
						'sdl' => SchemaPrinter::doPrint( new Schema( $config ) ),
					];
				}
			],
		];

		if ( count( $entities ) > 0 ) {
			$entity = new UnionType( [
				'name' => '_Entity',
				'types' => array_map( static function ( Type $type ): string {
						return $type->name;
				}, $this->types ),
			] );
			$types[] = $entity;
			$fields['_entities'] = [
				'type' => Type::nonNull( Type::listOf( $entity ) ),
				'args' => [
					'representations' => [
						'type' => Type::nonNull( Type::listOf( Type::nonNull( $any ) ) ),
					],
				],
				'resolve' => static function ( $rootValue, $args, $context, ResolveInfo $info ) {
					$representations = $args['representations'];
					return array_map( static function ( $representation ) use ( $info ) {
							$typeName = $representation['__typename'];
							$type = $info->schema->getType( $typeName );
							if ( !$type || $type instanceof GraphQLObjectType === false ) {
								throw new \Exception(
									'The _entities resolver tried to load an entity for type"'
									. $typeName
									. '", but no object type of that name was found in the schema'
								);
							}
							$resolver = $type->resolveFieldFn ?: static function () use ( $representation ) {
								return $representation;
							};
							return $resolver();
					}, $representations );
				},
			];
		}

		$config->setQuery( new ObjectType( [
			'name' => 'Query',
			'fields' => $fields,
		] ) );

		$config->setTypes( $types );

		$config->setDirectives( [
			new Directive( [
				'name' => 'external',
				'locations' => [
					DirectiveLocation::FIELD_DEFINITION,
				],
			] ),
			new Directive( [
				'name' => 'requires',
				'locations' => [
					DirectiveLocation::FIELD_DEFINITION,
				],
				'args' => [
					'fields' => [
						'type' => Type::nonNull( $fieldset ),
					],
				],
			] ),
			new Directive( [
				'name' => 'provides',
				'locations' => [
					DirectiveLocation::FIELD_DEFINITION,
				],
				'args' => [
					'fields' => [
						'type' => Type::nonNull( $fieldset ),
					],
				],
			] ),
			new Directive( [
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
			] ),
			new Directive( [
				'name' => 'extends',
				'locations' => [
					DirectiveLocation::OBJECT,
					DirectiveLocation::IFACE,
				],
			] ),
		] );

		// Rebuild the schema to ensure everything is up to date.
		return new Schema( $config );
	}
}
