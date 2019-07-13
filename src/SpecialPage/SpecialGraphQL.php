<?php

namespace MediaWiki\GraphQL\SpecialPage;

use GraphQL\GraphQL;
use GraphQL\Error\SyntaxError;
use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\Language\Parser;
use GraphQL\Language\Source;
// use GraphQL\Type\Introspection;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use MediaWiki\Linker\LinkRenderer;

class SpecialGraphQL extends \FormSpecialPage {

	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @var Schema;
	 */
	protected $schema;

	/**
	 * @var array;
	 */
	protected $result;

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
	 * @inheritDoc
	 */
	protected function getFormFields() {
		$this->getOutput()->addModuleStyles( [
			'ext.GraphQL.graphql',
		] );
		$this->getOutput()->addModules( [
			'ext.GraphQL.graphiql',
		] );
		$this->getOutput()->addVaryHeader( 'Accept' );

		// $promise = GraphQL::promiseToExecute(
		// $this->promise,
		// $this->schema,
		// Introspection::getIntrospectionQuery()
		// );
		// $result = $this->promise->wait( $promise );

		// $this->getOutput()->addJsConfigVars( 'GraphQLSchema', $result->toArray()['data'] );

		return [
			'query' => [
				'name' => 'query',
				'type' => 'textarea',
				'label-message' => 'graphql-special-query',
				'rows' => 15,
				'required' => true,
				'spellcheck' => false,
				'useeditfont' => true,
				'validation-callback' => function ( $value ) {
					// If the value is empty, that can mean that it was POST'd as JSON.
					// If that is the case, skip validation since it will occure on
					// execution.
					if ( empty( $value ) ) {
						return true;
					}

					// @TODO Figure out a way to localize the error messages.
					try {
						$documentNode = Parser::parse( new Source( $value ?: '', 'GraphQL' ) );
					} catch ( SyntaxError $e ) {
						return $e->getMessage();
					}

					$errors = DocumentValidator::validate( $this->schema, $documentNode );

					if ( empty( $errors ) ) {
						return true;
					}

					return array_map( function ( $error ) {
						return $error->getMessage();
					}, $errors );
				},
			],
			'variables' => [
				'name' => 'variables',
				'type' => 'textarea',
				'label-message' => 'graphql-special-variables',
				'rows' => 5,
				'required' => false,
				'spellcheck' => false,
				'useeditfont' => true,
				'validation-callback' => function ( $value ) {
					if ( empty( $value ) ) {
						return true;
					}

					$result = \FormatJson::parse( $value );
					return $result->isOK() ? true : $result->getMessage();
				},
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$this->getOutput()->addVaryHeader( 'Accept' );
		// Attempt to parse as JSON first$raw = $this->getRequest()->getRawPostString();
		$raw = $this->getRequest()->getRawPostString();
		$result = \FormatJson::parse( $raw, \FormatJson::FORCE_ASSOC );
		if ( $result->isOK() ) {
			$data = $result->getValue();
		} else {
			$data['variables'] = isset( $data['variables'] ) ?
				\FormatJson::parse( $data['variables'], \FormatJson::FORCE_ASSOC )->getValue() :
				null;
		}

		$query = $data['query'] ?? '';
		$variables = $data['variables'] ?? null;

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
				$this->result = $result->toArray( true );
		} catch ( \Exception $e ) {
				$this->result = [
						'errors' => [
								[
										'message' => $e->getMessage()
								]
						]
				];
		}

		return \Status::newGood( $this->result );
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
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
			$response->header( $this->getOutput()->getVaryHeader() );

			print \FormatJson::encode( $this->result, false, \FormatJson::ALL_OK );
		} else {
			$this->getForm()->showAlways();
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function postText() {
		if ( empty( $this->result ) ) {
			return '';
		}

		$content = new \JsonContent( \FormatJson::encode( $this->result, true, \FormatJson::ALL_OK ) );
		$output = $content->getParserOutput( $this->getPageTitle() );
		$this->getOutput()->addParserOutputMetadata( $output );

		return \Html::element(
			'h2',
			[],
			$this->msg( 'graphql-special-result' )
		) . $output->getText();
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'wiki';
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}
}
