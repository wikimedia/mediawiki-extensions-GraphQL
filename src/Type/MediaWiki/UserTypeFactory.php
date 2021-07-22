<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\PromiseAdapter;
use MediaWiki\GraphQL\Source\ApiSource;

class UserTypeFactory {
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
	 * @return UserType
	 */
	public function create(
		ApiSource $api,
		\IContextSource $context,
		string $prefix = ''
	): UserType {
		return new UserType(
			$this->promise,
			$api,
			$context,
			$prefix
		);
	}
}
