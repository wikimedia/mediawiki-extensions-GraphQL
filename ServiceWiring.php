<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\GraphQL\HookRunner;
use MediaWiki\GraphQL\Schema\FederatedSchemaFactory;
use MediaWiki\GraphQL\Schema\SchemaFactory;
use MediaWiki\GraphQL\Source\ApiFactory;
use MediaWiki\GraphQL\Type\MediaWiki\NamespaceTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\PageInterfaceTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\PageRevisionsTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\QueryTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionSlotTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionTypeFactory;
use MediaWiki\GraphQL\Type\MediaWiki\UserTypeFactory;
use MediaWiki\MediaWikiServices;
use Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter;
use Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter;

return [
	'GraphQLPromiseAdapter' => function ( MediaWikiServices $services ) : SyncPromiseAdapter {
		return new SyncPromiseAdapter();
	},
	'GraphQLSyncPromiseAdapter' =>
	function ( MediaWikiServices $services ) : WebonyxGraphQLSyncPromiseAdapter {
		return new WebonyxGraphQLSyncPromiseAdapter(
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLSourceApiFactory' => function ( MediaWikiServices $services ) : ApiFactory  {
		return new ApiFactory(
			$services->getService( 'GraphQLSyncPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiQueryTypeFactory' =>
	function ( MediaWikiServices $services ) : QueryTypeFactory {
		return new QueryTypeFactory();
	},
	'GraphQLMediaWikiPageInterfaceTypeFactory' =>
	function ( MediaWikiServices $services ) : PageInterfaceTypeFactory {
		return new PageInterfaceTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'NamespaceInfo' )
		);
	},
	'GraphQLMediaWikiPageRevisionsTypeFactory' =>
	function ( MediaWikiServices $services ) : PageRevisionsTypeFactory  {
		return new PageRevisionsTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiRevisionSlotTypeFactory' =>
	function ( MediaWikiServices $services ) : RevisionSlotTypeFactory  {
		return new RevisionSlotTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiRevisionTypeFactory' =>
	function ( MediaWikiServices $services ) : RevisionTypeFactory  {
		return new RevisionTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'SlotRoleRegistry' )
		);
	},
	'GraphQLMediaWikiNamespaceTypeFactory' =>
	function ( MediaWikiServices $services ) : NamespaceTypeFactory  {
		return new NamespaceTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiUserTypeFactory' =>
	function ( MediaWikiServices $services ) : UserTypeFactory  {
		return new UserTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLSchemaFactory' => function ( MediaWikiServices $services ) : SchemaFactory  {
		return new SchemaFactory(
			$services->getService( 'GraphQLSourceApiFactory' ),
			$services->getService( 'GraphQLMediaWikiQueryTypeFactory' ),
			$services->getService( 'GraphQLMediaWikiPageInterfaceTypeFactory' ),
			$services->getService( 'GraphQLMediaWikiNamespaceTypeFactory' ),
			$services->getService( 'GraphQLMediaWikiPageRevisionsTypeFactory' ),
			$services->getService( 'GraphQLMediaWikiRevisionTypeFactory' ),
			$services->getService( 'GraphQLMediaWikiRevisionSlotTypeFactory' ),
			$services->getService( 'GraphQLMediaWikiUserTypeFactory' ),
			$services->get( 'GraphQLHookRunner' ),
			new ServiceOptions( SchemaFactory::CONSTRUCTOR_OPTIONS, $services->getMainConfig() )
		);
	},
	'GraphQLFederatedSchemaFactory' =>
	function ( MediaWikiServices $services ) : FederatedSchemaFactory  {
		return new FederatedSchemaFactory(
			$services->getService( 'GraphQLSchemaFactory' ),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	},
	'GraphQLHookRunner' => function ( MediaWikiServices $services ) : HookRunner  {
		return new HookRunner(
			$services->getHookContainer()
		);
	}
];
