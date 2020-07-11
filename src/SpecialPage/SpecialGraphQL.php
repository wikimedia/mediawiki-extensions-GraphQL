<?php

namespace MediaWiki\GraphQL\SpecialPage;

use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\GraphQL;
use MediaWiki\GraphQL\Schema\FederatedSchemaFactory;
use MediaWiki\GraphQL\Schema\SchemaFactory;
use MediaWiki\Linker\LinkRenderer;

class SpecialGraphQL extends \UnlistedSpecialPage {

	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @var SchemaFactory
	 */
	protected $schemaFactory;

	/**
	 * @var FederatedSchemaFactory
	 */
	protected $federatedSchemaFactory;

	/**
	 * {@inheritdoc}
	 *
	 * @param LinkRenderer $linkRenderer
	 * @param PromiseAdapter $promise
	 * @param SchemaFactory $schemaFactory
	 * @param FederatedSchemaFactory $federatedSchemaFactory
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		PromiseAdapter $promise,
		SchemaFactory $schemaFactory,
		FederatedSchemaFactory $federatedSchemaFactory
	) {
		parent::__construct( 'GraphQL' );

		$this->promise = $promise;
		$this->schemaFactory = $schemaFactory;
		$this->federatedSchemaFactory = $federatedSchemaFactory;

		$this->setLinkRenderer( $linkRenderer );
	}

	/**
	 * @inheritDoc
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		// Attempt to parse as JSON first$raw = $this->getRequest()->getRawPostString();
		$query = '';
		$variables = null;
		if ( $this->getRequest()->wasPosted() ) {
			$raw = $this->getRequest()->getRawPostString();
			$result = \FormatJson::parse( $raw, \FormatJson::FORCE_ASSOC );
			if ( $result->isOK() ) {
				$data = $result->getValue();
				$query = $data['query'] ?? '';
				$variables = $data['variables'] ?? null;
			}
		} else {
			$query = $this->getRequest()->getVal( 'query' );
			$variables = $this->getRequest()->getVal( 'variables', null );
			// Parse the variables as JSON.
			if ( $variables !== null ) {
				$variables = \FormatJson::parse(
					$this->getRequest()->getVal( 'variables', null ),
					\FormatJson::FORCE_ASSOC
				)->getValue();
			}
		}

		$schema = $subPage === 'Federation'
			? $this->federatedSchemaFactory->create( $this->getContext() )
			: $this->schemaFactory->create( $this->getContext() );

		$result = [];
		try {
			$promise = GraphQL::promiseToExecute(
				$this->promise,
				$schema,
				$query,
				null,
				null,
				$variables
			);
			$result = $this->promise->wait( $promise );
			$result = $result->toArray( true );
		} catch ( \Exception $e ) {
				$result = [
						'errors' => [
								[
										'message' => $e->getMessage()
								]
						]
				];
		}

		// Disable the Page output.
		$this->getOutput()->disable();
		$response = $this->getRequest()->response();
		$response->header( 'Access-Control-Allow-Origin: *' );
		$response->header( 'Content-Type: application/json' );

		print \FormatJson::encode( $result, false, \FormatJson::ALL_OK );
	}
}
