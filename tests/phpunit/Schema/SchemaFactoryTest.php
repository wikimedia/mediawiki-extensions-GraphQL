<?php

namespace MediaWiki\GraphQL\Tests\Schema;

use GraphQL\Type\Schema;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\GraphQL\Schema\SchemaFactory;
use MediaWiki\GraphQL\Source\Api;
use MediaWiki\GraphQL\Type\MediaWiki\NamespaceInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\PageInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\PageRevisionsInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\QueryInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionSlotInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\UserInterfaceType;
use MediaWiki\Revision\SlotRoleRegistry;
use Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter;

/**
 * @group GraphQLSchema
 */
class SchemaFactoryTest extends \MediaWikiTestCase {
	/**
	 * @covers MediaWiki\GraphQL\Schema\SchemaFactory::create
	 */
	public function testCreate() {
		 $api = $this->createMock( Api::class );
		 $promise = $this->createMock( SyncPromiseAdapter::class );
		 $namepsace = new NamespaceInterfaceType( $api, $promise );
		 $slotRoleRegistery = $this->createMock( SlotRoleRegistry::class );
		 $user = new UserInterfaceType( $api, $promise );
		 $revisionSlot = new RevisionSlotInterfaceType( $api, $promise );
		 $revision = new RevisionInterfaceType(
			 $api,
			 $promise,
			 $slotRoleRegistery,
			 $user,
			 $revisionSlot
		 );
		 $pageRevisions = new PageRevisionsInterfaceType( $api, $promise, $revision );
		 $page = new PageInterfaceType( $api, $promise, $namepsace, $pageRevisions, $revision );
		 $query = new QueryInterfaceType( $page );
		 $namespaceInfo = $this->createMock( \NamespaceInfo::class );
		 $namespaceInfo->method( 'getCanonicalNamespaces' )
			->willReturn( [
					NS_SPECIAL => 'Special',
					NS_MAIN => '',
					NS_TALK => 'Talk',
			] );
		$options = $this->createMock( ServiceOptions::class );
		$options->method( 'get' )
			->willReturn( true );

		 $factory = new SchemaFactory(
			 $query,
			 $page,
			 $namepsace,
			 $pageRevisions,
			 $revision,
			 $revisionSlot,
			 $user,
			 $namespaceInfo,
			 $options
		 );

		 $this->assertInstanceOf( Schema::class, $factory->create() );
	}
}
