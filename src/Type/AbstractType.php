<?php

namespace MediaWiki\GraphQL\Type;

interface AbstractType {
	/**
	 * Retrieve all types.
	 *
	 * @return array
	 */
	public function getTypes(): array;
}
