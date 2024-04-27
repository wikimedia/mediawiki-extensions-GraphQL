<?php

namespace MediaWiki\GraphQL\Source;

interface ApiSource {

	/**
	 * Make a request to the API.
	 *
	 * @param array $params
	 */
	public function request( array $params );
}
