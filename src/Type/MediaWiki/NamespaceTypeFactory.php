<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\PromiseAdapter;
use MediaWiki\GraphQL\Source\ApiSource;

class NamespaceTypeFactory {

	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @param PromiseAdapter $promise
	 */
	public function __construct(
		PromiseAdapter $promise
	) {
		$this->promise = $promise;
	}

	/**
	 * @param ApiSource $api
	 * @param \IContextSource $context
	 * @param string $prefix
	 * @return NamespaceType
	 */
	public function create(
		ApiSource $api,
		\IContextSource $context,
		string $prefix = ''
	): NamespaceType {
		return new NamespaceType(
			$this->promise,
			$api,
			$context,
			$prefix
		);
	}
}
