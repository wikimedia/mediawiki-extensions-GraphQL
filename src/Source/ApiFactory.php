<?php

namespace MediaWiki\GraphQL\Source;

use Overblog\PromiseAdapter\PromiseAdapterInterface;

class ApiFactory {

	/**
	 * @var PromiseAdapterInterface
	 */
	protected $adapter;

	/**
	 * @param PromiseAdapterInterface $adapter
	 */
	public function __construct(
		PromiseAdapterInterface $adapter
	) {
		$this->adapter = $adapter;
	}

	/**
	 * @param \IContextSource $context
	 * @return Api
	 */
	public function create( \IContextSource $context ): Api {
		return new Api(
			$this->adapter,
			$context
		);
	}
}
