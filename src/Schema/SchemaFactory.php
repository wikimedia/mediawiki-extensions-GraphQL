<?php

namespace MediaWiki\GraphQL\Schema;

use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\GraphQL\Hook\GraphQLSchemaConfig;
use MediaWiki\GraphQL\Source\ApiFactory;
use MediaWiki\GraphQL\Type\MediaWiki\NamespaceTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\PageInterfaceTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\PageRevisionsTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\QueryTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionSlotTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\UserTypeFactory;

// @TODO Write a test to build the schema and validate it.
class SchemaFactory {
	/**
	 * @var array
	 */
	public const CONSTRUCTOR_OPTIONS = [
		'GraphQLValidateSchema'
	];

	/**
	 * @var ApiFactory
	 */
	private $apiFactory;

	/**
	 * @var QueryTypeFactory
	 */
	private $queryFactory;

	/**
	 * @var PageInterfaceTypeFactory
	 */
	private $pageInterfaceFactory;

	/**
	 * @var NamespaceTypeFactory
	 */
	private $namespaceFactory;

	/**
	 * @var PageRevisionsTypeFactory
	 */
	private $pageRevisionsFactory;

	/**
	 * @var RevisionTypeFactory
	 */
	private $revisionFactory;

	/**
	 * @var RevisionSlotTypeFactory
	 */
	private $revisionSlotFactory;

	/**
	 * @var UserTypeFactory
	 */
	private $userFactory;

	/**
	 * @var GraphQLSchemaConfig
	 */
	private $hookSchemaConfig;

	/**
	 * @var ServiceOptions
	 */
	private $options;

	/**
	 * Schema Factory.
	 *
	 * @param ApiFactory $apiFactory
	 * @param QueryTypeFactory $queryFactory
	 * @param PageInterfaceTypeFactory $pageInterfaceFactory
	 * @param NamespaceTypeFactory $namespaceFactory
	 * @param PageRevisionsTypeFactory $pageRevisionsFactory
	 * @param RevisionTypeFactory $revisionFactory
	 * @param RevisionSlotTypeFactory $revisionSlotFactory
	 * @param UserTypeFactory $userFactory
	 * @param GraphQLSchemaConfig $hookSchemaConfig
	 * @param ServiceOptions $options
	 */
	public function __construct(
		ApiFactory $apiFactory,
		QueryTypeFactory $queryFactory,
		PageInterfaceTypeFactory $pageInterfaceFactory,
		NamespaceTypeFactory $namespaceFactory,
		PageRevisionsTypeFactory $pageRevisionsFactory,
		RevisionTypeFactory $revisionFactory,
		RevisionSlotTypeFactory $revisionSlotFactory,
		UserTypeFactory $userFactory,
		GraphQLSchemaConfig $hookSchemaConfig,
		ServiceOptions $options
	) {
		$this->apiFactory = $apiFactory;
		$this->queryFactory = $queryFactory;
		$this->pageInterfaceFactory = $pageInterfaceFactory;
		$this->namespaceFactory = $namespaceFactory;
		$this->pageRevisionsFactory = $pageRevisionsFactory;
		$this->revisionFactory = $revisionFactory;
		$this->revisionSlotFactory = $revisionSlotFactory;
		$this->userFactory = $userFactory;
		$this->hookSchemaConfig = $hookSchemaConfig;
		$this->options = $options;
	}

	/**
	 * @param \IContextSource $context
	 * @param string $prefix
	 * @return Schema
	 */
	public function create( \IContextSource $context, string $prefix = '' ): Schema {
		$api = $this->apiFactory->create( $context );

		$namespace = $this->namespaceFactory->create( $api, $context, $prefix );

		$user = $this->userFactory->create( $api, $context, $prefix );

		$revisionSlot = $this->revisionSlotFactory->create( $api, $context, $prefix );

		$revision = $this->revisionFactory->create( $api, $user, $revisionSlot, $context, $prefix );

		$pageRevisions = $this->pageRevisionsFactory->create( $revision, $context, $prefix );

		$pageInterface = $this->pageInterfaceFactory->create(
			$api,
			$namespace,
			$pageRevisions,
			$revision,
			$context,
			$prefix
		);

		$query = $this->queryFactory->create( $pageInterface, $context, $prefix );

		$config = SchemaConfig::create( [
				'query' => $query,
				'types' => array_merge( $pageInterface->getTypes(), [
						$pageInterface,
						$namespace,
						$pageRevisions,
						$revision,
						$revisionSlot,
						$user,
				] ),
		] );

		$this->hookSchemaConfig->onGraphQLSchemaConfig( $config );

		$schema = new Schema( $config );

		// Validation should be disabled in production because it is expensive!
		if ( $this->options->get( 'GraphQLValidateSchema' ) === true ) {
			$schema->assertValid();
		}

		return $schema;
	}
}
