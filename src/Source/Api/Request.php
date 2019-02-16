<?php

namespace MediaWiki\GraphQL\Source\Api;

class Request {

	/**
	 * @var array
	 */
	protected $keys;

	/**
	 * @param array $key
	 */
	public function __construct( array $key ) {
		$this->keys = [ $key ];
	}

	/**
	 * Add to the keys.
	 *
	 * @param array $key
	 */
	public function addKey( array $key ) {
		$this->keys[] = $key;
	}

	/**
	 * Get keys for the request.
	 *
	 * @return array[]
	 */
	public function getKeys() {
		return $this->keys;
	}

	/**
	 * Get the merged keys. Useful for comparisons.
	 *
	 * @return array
	 */
	public function getMergedKeys() {
		$params = array_merge_recursive( ...$this->keys );

		// Flatten arrays if they are empty.
		foreach ( $params as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = array_unique( $value );
				$params[$key] = count( $value ) === 1 ? $value[0] : $value;
			}
		}

		return $params;
	}

	/**
	 * Get the merged params from all of the keys.
	 *
	 * Remove any boolean keys that are set. Missing is false, but
	 * sending 'false' is considered true. Also, remove any undefined keys.
	 *
	 * @return array
	 */
	public function getParams() {
		$params = $this->getMergedKeys();

		foreach ( $params as $key => &$value ) {
			if ( is_array( $value ) ) {
				// If any of the values is * just use that.
				if ( in_array( '*', $value, true ) ) {
					$value = '*';
				} else {
					$value = implode( '|', $value );
				}
			} elseif ( $value === null ) {
				unset( $params[$key] );
			} elseif ( $value === false ) {
				unset( $params[$key] );
			}
		}

		return $params;
	}
}
