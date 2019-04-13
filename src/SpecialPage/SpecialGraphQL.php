<?php

namespace MediaWiki\GraphQL\SpecialPage;

use GraphQL\GraphQL;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Type\Schema;
use MediaWiki\Linker\LinkRenderer;

class SpecialGraphQL extends \UnlistedSpecialPage {

	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @var Schema;
	 */
	protected $schema;

	/**
	 * @inheritDoc
	 *
	 * @param LinkRenderer $linkRenderer
	 * @param PromiseAdapter $promise
	 * @param Schema $schema
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		PromiseAdapter $promise,
		Schema $schema
	) {
		parent::__construct( 'GraphQL' );

		$this->promise = $promise;
		$this->schema = $schema;

		$this->setLinkRenderer( $linkRenderer );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		if ( $this->getRequest()->wasPosted() ) {
			$raw = $this->getRequest()->getRawPostString();
			$input = \FormatJson::parse( $raw, \FormatJson::FORCE_ASSOC )->getValue();
			$query = isset( $input['query'] ) ? $input['query'] : '';
			$variables = isset( $input['variables'] ) ? $input['variables'] : null;
		} else {
			$query = $this->getRequest()->getText( 'query', '' );
			$variables = \FormatJson::parse(
				$this->getRequest()->getText( 'variables', '' ),
				\FormatJson::FORCE_ASSOC
			)->getValue();
		}

		try {
				$promise = GraphQL::promiseToExecute(
					$this->promise,
					$this->schema,
					$query,
					null,
					null,
					$variables
				);
				$result = $this->promise->wait( $promise );
				$output = $result->toArray( true );
		} catch ( \Exception $e ) {
				$output = [
						'errors' => [
								[
										'message' => $e->getMessage()
								]
						]
				];
		}

		$header = $this->getRequest()->getHeader( 'Accept', \WebRequest::GETHEADER_LIST );
		$accept = [];
		if ( is_array( $header ) ) {
			$accept = $header;
		} elseif ( is_string( $header ) ) {
			$accept = [ $header ];
		}

		$accept = array_map( function ( $item ) {
			$split = explode( ';', $item );
			return $split[0];
		}, $accept );

		// The default response is JSON.
		if (
			empty( $accept ) ||
			in_array( 'application/json', $accept, true ) ||
			$accept === [ '*/*' ]
		) {
			// Disable the Page output.
			$this->getOutput()->disable();
			$response = $this->getRequest()->response();
			$response->header( 'Access-Control-Allow-Origin: *' );
			$response->header( 'Content-Type: application/json' );

			print \FormatJson::encode( $output, false, \FormatJson::ALL_OK );
		}

		// @TODO If the user's `Accept` header is anything else, response with an
		// in-browser IDE.
		$this->getOutput()->setPageTitle( $this->msg( 'graphql' ) );
	}
}
