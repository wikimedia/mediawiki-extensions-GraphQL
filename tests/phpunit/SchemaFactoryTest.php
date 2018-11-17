<?php

namespace MediaWiki\GraphQL\Tests;

use GraphQL\Type\Schema;
use MediaWiki\GraphQL\SchemaFactory;
use MediaWiki\GraphQL\Type\MediaWiki\QueryInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\NamespaceInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\PageInterfaceType;
use MediaWiki\GraphQL\Source\Api;

/**
 * @group GraphQLSchema
 */
class SchemaFactoryTest extends \MediaWikiTestCase {
	/**
	 * @covers MediaWiki\GraphQL\SchemaFactory::create
	 */
	public function testCreate() {
		 $api = $this->createMock( Api::class );
		 $namepsace = new NamespaceInterfaceType( $api );
		 $page = new PageInterfaceType( $api, $namepsace );
		 $query = new QueryInterfaceType( $page );
		 $factory = new SchemaFactory(
			 $query,
			 $page,
			 $namepsace,
			 \MWNamespace::getCanonicalNamespaces(),
			 true
		 );

		 $this->assertInstanceOf( Schema::class, $factory->create() );
	}
}
