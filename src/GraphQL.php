<?php

namespace MediaWiki\GraphQL;

use MediaWiki\GraphQL\Source\Api;
use MediaWiki\Services\ServiceContainer;
use MediaWiki\GraphQL\Type\MediaWiki\NamespaceInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\PageInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\PageRevisionsInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\QueryInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\RevisionSlotInterfaceType;
use MediaWiki\GraphQL\Type\MediaWiki\UserInterfaceType;
use Overblog\DataLoader\Promise\Adapter\Webonyx\GraphQL\SyncPromiseAdapter;
use Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter;

class GraphQL {

	/**
	 * Hook Impelementation: WebRequestPathInfoRouter
	 *
	 * @param \PathRouter $router
	 * @return void
	 */
	public static function onWebRequestPathInfoRouter( \PathRouter $router ) {
		// Get localized title!
		$title = \SpecialPage::getTitleValueFor( 'Special:GraphQL' );
		$router->addStrict( '/graphql', [
			'title' => $title->getText(),
		] );
	}

	/**
	 * Hook Impelementation: MediaWikiServices
	 *
	 * @param \ServiceContainer $container
	 * @return void
	 */
	public static function onMediaWikiServices( ServiceContainer $container ) {
		$container->defineService( 'GraphQLPromiseAdapter', function ( $instance ) {
			return new SyncPromiseAdapter();
		} );

		$container->defineService( 'GraphQLSyncPromiseAdapter', function ( $instance ) {
			return new WebonyxGraphQLSyncPromiseAdapter(
				$instance->getService( 'GraphQLPromiseAdapter' )
			);
		} );

		$container->defineService( 'GraphQLSourceApi', function ( $instance ) {
			return new Api(
				$instance->getService( 'GraphQLSyncPromiseAdapter' )
			);
		} );

		$container->defineService( 'GraphQLMediaWikiQueryInterfaceType', function ( $instance ) {
			return new QueryInterfaceType(
				$instance->getService( 'GraphQLMediaWikiPageInterfaceType' )
			);
		} );

		$container->defineService( 'GraphQLMediaWikiPageInterfaceType', function ( $instance ) {
			return new PageInterfaceType(
				$instance->getService( 'GraphQLSourceApi' ),
				$instance->getService( 'GraphQLPromiseAdapter' ),
				$instance->getService( 'GraphQLMediaWikiNamespaceInterfaceType' ),
				$instance->getService( 'GraphQLMediaWikiPageRevisionsInterfaceType' ),
				$instance->getService( 'GraphQLMediaWikiRevisionInterfaceType' )
			);
		} );

		$container->defineService( 'GraphQLMediaWikiPageRevisionsInterfaceType', function ( $instance ) {
			return new PageRevisionsInterfaceType(
				$instance->getService( 'GraphQLSourceApi' ),
				$instance->getService( 'GraphQLPromiseAdapter' ),
				$instance->getService( 'GraphQLMediaWikiRevisionInterfaceType' )
			);
		} );

		$container->defineService( 'GraphQLMediaWikiRevisionSlotInterfaceType', function ( $instance ) {
			return new RevisionSlotInterfaceType(
				$instance->getService( 'GraphQLSourceApi' ),
				$instance->getService( 'GraphQLPromiseAdapter' )
			);
		} );

		$container->defineService( 'GraphQLMediaWikiRevisionInterfaceType', function ( $instance ) {
			return new RevisionInterfaceType(
				$instance->getService( 'GraphQLSourceApi' ),
				$instance->getService( 'GraphQLPromiseAdapter' ),
				$instance->getService( 'SlotRoleRegistry' ),
				$instance->getService( 'GraphQLMediaWikiUserInterfaceType' ),
				$instance->getService( 'GraphQLMediaWikiRevisionSlotInterfaceType' )
			);
		} );

		$container->defineService( 'GraphQLMediaWikiNamespaceInterfaceType', function ( $instance ) {
			return new NamespaceInterfaceType(
				$instance->getService( 'GraphQLSourceApi' ),
				$instance->getService( 'GraphQLPromiseAdapter' )
			);
		} );

		$container->defineService( 'GraphQLMediaWikiUserInterfaceType', function ( $instance ) {
			return new UserInterfaceType(
				$instance->getService( 'GraphQLSourceApi' ),
				$instance->getService( 'GraphQLPromiseAdapter' )
			);
		} );

		$container->defineService( 'GraphQLSchemaFactory', function ( $instance ) {
			return new SchemaFactory(
				$instance->getService( 'GraphQLMediaWikiQueryInterfaceType' ),
				$instance->getService( 'GraphQLMediaWikiPageInterfaceType' ),
				$instance->getService( 'GraphQLMediaWikiNamespaceInterfaceType' ),
				$instance->getService( 'GraphQLMediaWikiPageRevisionsInterfaceType' ),
				$instance->getService( 'GraphQLMediaWikiRevisionInterfaceType' ),
				$instance->getService( 'GraphQLMediaWikiRevisionSlotInterfaceType' ),
				$instance->getService( 'GraphQLMediaWikiUserInterfaceType' ),
				\MWNamespace::getCanonicalNamespaces(),
				$instance->getMainConfig()->get( 'GraphQLValidateSchema' )
			);
		} );

		$container->defineService( 'GraphQLSchema', function ( $instance ) {
			return $instance->getService( 'GraphQLSchemaFactory' )->create();
		} );
	}
}
