<?php

use GraphQL\Type\Schema;
use MediaWiki\GraphQL\SchemaFactory;
use MediaWiki\GraphQL\Source\Api;
use MediaWiki\MediaWikiServices;
use MediaWiki\GraphQL\SpecialPage\SpecialGraphQL;
use MediaWiki\GraphQL\SpecialPage\SpecialGraphQLSandbox;
use MediaWiki\GraphQL\Type\MediaWiki\NamespaceInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\PageInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\PageRevisionsInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\QueryInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionSlotInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\UserInterfaceType;
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
			$services->getService( 'MainConfig' )->get( 'GraphQLValidateSchema' )
		);
	},
	'GraphQLSchema' => function ( MediaWikiServices $services ) : Schema {
		return $services->getService( 'GraphQLSchemaFactory' )->create();
	},
	'SpecialGraphQL' => function ( MediaWikiServices $services ) : SpecialGraphQL {
		return new SpecialGraphQL(
			$services->getService( 'LinkRenderer' ),
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'GraphQLSchema' )
		);
	},
	'SpecialGraphQLSandbox' => function ( MediaWikiServices $services ) : SpecialGraphQLSandbox {
		return new SpecialGraphQLSandbox(
			$services->getService( 'LinkRenderer' ),
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'GraphQLSchema' )
		);
	},
];
