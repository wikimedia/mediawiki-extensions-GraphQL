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
	'GraphQLPromiseAdapter' => static function ( MediaWikiServices $services ): SyncPromiseAdapter {
		return new SyncPromiseAdapter();
	},
	'GraphQLSyncPromiseAdapter' =>
	static function ( MediaWikiServices $services ): WebonyxGraphQLSyncPromiseAdapter {
		return new WebonyxGraphQLSyncPromiseAdapter(
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLSourceApiFactory' => static function ( MediaWikiServices $services ): ApiFactory  {
		return new ApiFactory(
			$services->getService( 'GraphQLSyncPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiQueryTypeFactory' =>
	static function ( MediaWikiServices $services ): QueryTypeFactory {
		return new QueryTypeFactory();
	},
	'GraphQLMediaWikiPageInterfaceTypeFactory' =>
	static function ( MediaWikiServices $services ): PageInterfaceTypeFactory {
		return new PageInterfaceTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'NamespaceInfo' )
		);
	},
	'GraphQLMediaWikiPageRevisionsTypeFactory' =>
	static function ( MediaWikiServices $services ): PageRevisionsTypeFactory  {
		return new PageRevisionsTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiRevisionSlotTypeFactory' =>
	static function ( MediaWikiServices $services ): RevisionSlotTypeFactory  {
		return new RevisionSlotTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiRevisionTypeFactory' =>
	static function ( MediaWikiServices $services ): RevisionTypeFactory  {
		return new RevisionTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' ),
			$services->getService( 'SlotRoleRegistry' )
		);
	},
	'GraphQLMediaWikiNamespaceTypeFactory' =>
	static function ( MediaWikiServices $services ): NamespaceTypeFactory  {
		return new NamespaceTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLMediaWikiUserTypeFactory' =>
	static function ( MediaWikiServices $services ): UserTypeFactory  {
		return new UserTypeFactory(
			$services->getService( 'GraphQLPromiseAdapter' )
		);
	},
	'GraphQLSchemaFactory' => static function ( MediaWikiServices $services ): SchemaFactory  {
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
	static function ( MediaWikiServices $services ): FederatedSchemaFactory  {
		return new FederatedSchemaFactory(
			$services->getService( 'GraphQLSchemaFactory' ),
			WikiMap::getCurrentWikiDbDomain()->getId()
		);
	},
	'GraphQLHookRunner' => static function ( MediaWikiServices $services ): HookRunner  {
		return new HookRunner(
			$services->getHookContainer()
		);
	}
];
