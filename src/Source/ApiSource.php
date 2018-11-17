<?php

namespace MediaWiki\GraphQL\Source;

use MediaWiki\GraphQL\Source\Api\Request;

interface ApiSource {

	/**
	 * Make a request to the API.
	 *
	 * @param array $params
	 */
	public function request( array $params );
}
