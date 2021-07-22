<?php

namespace MediaWiki\GraphQL\Type\MediaWiki;

use GraphQL\Executor\Promise\PromiseAdapter;
use MediaWiki\GraphQL\Source\ApiSource;
use MediaWiki\Revision\SlotRoleRegistry;

class RevisionTypeFactory {
	/**
	 * @var PromiseAdapter
	 */
	protected $promise;

	/**
	 * @var SlotRoleRegistry
	 */
	protected $slotRoleRegistery;

	/**
	 * @param PromiseAdapter $promise
	 * @param SlotRoleRegistry $slotRoleRegistery
	 */
	public function __construct(
		PromiseAdapter $promise,
		SlotRoleRegistry $slotRoleRegistery
	) {
		$this->promise = $promise;
		$this->slotRoleRegistery = $slotRoleRegistery;
	}

	/**
	 * @param ApiSource $api
	 * @param UserType $userType
	 * @param RevisionSlotType $slotType
	 * @param \IContextSource $context
	 * @param string $prefix
	 * @return RevisionType
	 */
	public function create(
		ApiSource $api,
		UserType $userType,
		RevisionSlotType $slotType,
		\IContextSource $context,
		string $prefix = ''
	): RevisionType {
		return new RevisionType(
			$this->promise,
			$this->slotRoleRegistery,
			$api,
			$userType,
			$slotType,
			$context,
			$prefix
		);
	}
}
