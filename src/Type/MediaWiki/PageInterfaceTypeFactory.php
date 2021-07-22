<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\PromiseAdapter;
use MediaWiki\GraphQL\Source\ApiSource;

class PageInterfaceTypeFactory {
	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @var NamespaceInfo
	 */
	protected $namespaceInfo;

	/**
	 * @param PromiseAdapter $promise
	 * @param \NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		PromiseAdapter $promise,
		\NamespaceInfo $namespaceInfo
	) {
		$this->promise = $promise;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @param ApiSource $api
	 * @param NamespaceType $namespaceType
	 * @param PageRevisionsType $pageRevisionsType
	 * @param RevisionType $revisionType
	 * @param \IContextSource $context
	 * @param string $prefix
	 * @return PageInterfaceType
	 */
	public function create(
		ApiSource $api,
		NamespaceType $namespaceType,
		PageRevisionsType $pageRevisionsType,
		RevisionType $revisionType,
		\IContextSource $context,
		string $prefix = ''
	): PageInterfaceType {
		return new PageInterfaceType(
			$this->promise,
			$this->namespaceInfo,
			$api,
			$namespaceType,
			$pageRevisionsType,
			$revisionType,
			$context,
			$prefix
		);
	}
}
