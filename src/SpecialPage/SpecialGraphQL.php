<?php

namespace MediaWiki\GraphQL\SpecialPage;

use GraphQL\GraphQL;
use MediaWiki\MediaWikiServices;

class SpecialGraphQL extends \UnlistedSpecialPage {
	public function __construct() {
		parent::__construct( 'GraphQL' );
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param string|null $subPage
	 */
	public function execute( $subPage ) {
		$schema = MediaWikiServices::getInstance()->getService( 'GraphQLSchema' );

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
				$adapter = MediaWikiServices::getInstance()->getService( 'GraphQLPromiseAdapter' );
				$promise = GraphQL::promiseToExecute(
					$adapter,
					$schema,
					$query,
					null,
					null,
					$variables
				);
				$result = $adapter->wait( $promise );
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

		// Disable the Page output.
		// @TODO If the user's `Accept` header is `text/html` respond with an
		// in-browser GraphQL IDE.
		$this->getOutput()->disable();

		$response = $this->getRequest()->response();
		$response->header( 'Access-Control-Allow-Origin: *' );
		$response->header( 'Content-Type: application/json' );

		print \FormatJson::encode( $output, false, \FormatJson::ALL_OK );
	}
}
