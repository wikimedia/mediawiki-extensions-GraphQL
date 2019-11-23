<?php

namespace MediaWiki\GraphQL\Schema;

use GraphQL\Type\Schema;

interface Factory {
	/**
	 * Create Schema
	 *
	 * @return Schema
	 */
	public function create() : Schema;
}
