<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\GraphQL\Schema\FederatedSchemaFactory;
use MediaWiki\GraphQL\Schema\SchemaFactory;
use MediaWiki\GraphQL\Source\Api;
use MediaWiki\GraphQL\SpecialPage\SpecialGraphQL;
use MediaWiki\GraphQL\SpecialPage\SpecialGraphQLSandbox;
use MediaWiki\GraphQL\Type\MediaWiki\NamespaceInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\PageInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\PageRevisionsInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\QueryInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionSlotInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\UserInterfaceType;
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
	'GraphQLSourceApi' => function ( MediaWikiServices $services ) : Api  {
		return new Api(
			\RequestContext::getMain(),
			$services->getService( 'GraphQLSyncPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiQueryInterfaceType' =>
	function ( MediaWikiServices $services ) : QueryInterfaceType {
		return new QueryInterfaceType(
			$services->getService( 'GraphQLMediaWikiPageInterfaceType' )
		);
	},
	'GraphQLMediaWikiPageInterfaceType' =>
	function ( MediaWikiServices $services ) : PageInterfaceType {
		return new PageInterfaceType(
			$services->getService( 'GraphQLSourceApi' ),
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'GraphQLMediaWikiNamespaceInterfaceType' ),
			$services->getService( 'GraphQLMediaWikiPageRevisionsInterfaceType' ),
			$services->getService( 'GraphQLMediaWikiRevisionInterfaceType' )
		);
	},
	'GraphQLMediaWikiPageRevisionsInterfaceType' =>
	function ( MediaWikiServices $services ) : PageRevisionsInterfaceType  {
		return new PageRevisionsInterfaceType(
			$services->getService( 'GraphQLSourceApi' ),
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'GraphQLMediaWikiRevisionInterfaceType' )
		);
	},
	'GraphQLMediaWikiRevisionSlotInterfaceType' =>
	function ( MediaWikiServices $services ) : RevisionSlotInterfaceType  {
		return new RevisionSlotInterfaceType(
			$services->getService( 'GraphQLSourceApi' ),
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiRevisionInterfaceType' =>
	function ( MediaWikiServices $services ) : RevisionInterfaceType  {
		return new RevisionInterfaceType(
			$services->getService( 'GraphQLSourceApi' ),
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'SlotRoleRegistry' ),
			$services->getService( 'GraphQLMediaWikiUserInterfaceType' ),
			$services->getService( 'GraphQLMediaWikiRevisionSlotInterfaceType' )
		);
	},
	'GraphQLMediaWikiNamespaceInterfaceType' =>
	function ( MediaWikiServices $services ) : NamespaceInterfaceType  {
		return new NamespaceInterfaceType(
			$services->getService( 'GraphQLSourceApi' ),
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiUserInterfaceType' =>
	function ( MediaWikiServices $services ) : UserInterfaceType  {
		return new UserInterfaceType(
			$services->getService( 'GraphQLSourceApi' ),
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLSchemaFactory' => function ( MediaWikiServices $services ) : SchemaFactory  {
		return new SchemaFactory(
			$services->getService( 'GraphQLMediaWikiQueryInterfaceType' ),
			$services->getService( 'GraphQLMediaWikiPageInterfaceType' ),
			$services->getService( 'GraphQLMediaWikiNamespaceInterfaceType' ),
			$services->getService( 'GraphQLMediaWikiPageRevisionsInterfaceType' ),
			$services->getService( 'GraphQLMediaWikiRevisionInterfaceType' ),
			$services->getService( 'GraphQLMediaWikiRevisionSlotInterfaceType' ),
			$services->getService( 'GraphQLMediaWikiUserInterfaceType' ),
			$services->getService( 'NamespaceInfo' ),
			new ServiceOptions( SchemaFactory::CONSTRUCTOR_OPTIONS, $services->getMainConfig() )
		);
	},
	'GraphQLFederatedSchemaFactory' =>
	function ( MediaWikiServices $services ) : FederatedSchemaFactory  {
		return new FederatedSchemaFactory(
			WikiMap::getCurrentWikiDbDomain()->getId(),
			$services->getService( 'GraphQLSchemaFactory' )
		);
	},
	'SpecialGraphQL' => function ( MediaWikiServices $services ) : SpecialGraphQL {
		return new SpecialGraphQL(
			$services->getService( 'LinkRenderer' ),
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'GraphQLSchemaFactory' ),
			$services->getService( 'GraphQLFederatedSchemaFactory' )
		);
	},
	'SpecialGraphQLSandbox' => function ( MediaWikiServices $services ) : SpecialGraphQLSandbox {
		return new SpecialGraphQLSandbox(
			$services->getService( 'LinkRenderer' ),
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'GraphQLSchemaFactory' )
		);
	},
];
