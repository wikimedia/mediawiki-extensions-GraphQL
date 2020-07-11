<?php

namespace MediaWiki\GraphQL\SpecialPage;

use GraphQL\Executor\Promise\PromiseAdapter;
use GraphQL\GraphQL;
use GraphQL\Type\Introspection;
use MediaWiki\GraphQL\Schema\SchemaFactory;
use MediaWiki\Linker\LinkRenderer;

class SpecialGraphQLSandbox extends \SpecialPage {

	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @var SchemaFactory
	 */
	protected $schemaFactory;

	/**
	 * @var array
	 */
	protected $result;

	/**
	 * {@inheritdoc}
	 *
	 * @param LinkRenderer $linkRenderer
	 * @param PromiseAdapter $promise
	 * @param Factory $schemaFactory
	 */
	public function __construct(
		LinkRenderer $linkRenderer,
		PromiseAdapter $promise,
		SchemaFactory $schemaFactory
	) {
		parent::__construct( 'GraphQLSandbox' );

		$this->promise = $promise;
		$this->schemaFactory = $schemaFactory;

		$this->setLinkRenderer( $linkRenderer );
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$out = $this->getOutput();

		$promise = GraphQL::promiseToExecute(
			$this->promise,
			$this->schemaFactory->create( $this->getContext() ),
			Introspection::getIntrospectionQuery()
		);

		$result = $this->promise->wait( $promise );

		$this->getOutput()->addJsConfigVars( 'GraphQLSchema', $result->toArray()['data'] );

		$out->addModuleStyles( [
			'ext.GraphQL.graphql',
		] );
		$out->addModules( [
			'ext.GraphQL.graphiql',
		] );
		$out->wrapWikiMsg(
			"<div id=\"mw-graphqlsandbox\"><div class=\"mw-graphqlsandbox-nojs error\">\n$1\n</div></div>",
			'apisandbox-jsonly'
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'wiki';
	}
}
