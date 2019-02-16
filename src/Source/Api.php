<?php

namespace MediaWiki\GraphQL\Source;

use MediaWiki\Session\SessionManager;
use MediaWiki\GraphQL\Source\Api\Request;
use Overblog\DataLoader\DataLoader;
use Overblog\PromiseAdapter\PromiseAdapterInterface;

class Api implements ApiSource {

	/**
	 * @var DataLoader
	 */
	protected $dataLoader;

	/**
	 * @var PromiseAdapterInterface
	 */
	protected $adapter;

	/**
	 * Api Constructor.
	 *
	 * @param PromiseAdapterInterface $adapter
	 */
	public function __construct( PromiseAdapterInterface $adapter ) {
		$this->adapter = $adapter;

		$this->dataLoader = new DataLoader(
			function ( $keys ) {
				return $this->batchLoad( $keys );
			},
			$adapter
		);
	}

	/**
	 * Make a request to the API.
	 *
	 * @param array $params
	 * @return GraphQL\Executor\Promise\Promise
	 */
	public function request( array $params ) {
		return $this->dataLoader->load( self::addDefaultValues( self::getMain( $params ), $params ) );
	}

	/**
	 * DataLoader callback function.
	 *
	 * @param array[] $keys
	 * @return GraphQL\Executor\Promise\Promise
	 */
	protected function batchLoad( array $keys ) {
		$main = self::getMain();
		$requests = array_reduce( $keys, function ( $carry, $params ) {
			$request = self::findExistingRequest( $carry, $params );

			if ( $request ) {
				$request->addKey( $params );

				return $carry;
			}

			$carry[] = new Request( $params );

			return $carry;
		}, [] );

		$results = array_map( function ( $request ) {
			return self::makeRequest( $request->getParams() );
		}, $requests );

		return $this->adapter->createFulfilled(
			array_map( function ( $key ) use ( $requests, $results ) {
				$index = self::findRequestIndex( $requests, $key );

				if ( $index === null ) {
					throw new \Exception( 'Cannot find request index!' );
				}

				return $results[ $index ];
			}, $keys )
		);
	}

	/**
	 * Make an API Request.
	 *
	 * @param array $params
	 * @return array
	 */
	protected static function makeRequest( array $params ) {
		$module = self::getMain( $params );
		$module->execute();

		return $module->getResult()->getResultData( null, [ 'Strip' => 'all' ] );
	}

	/**
	 * Retrieves an instance of the main module.
	 *
	 * @param array $params
	 * @return \ApiMain
	 */
	protected static function getMain( $params = [] ) {
		$request = new \FauxRequest( $params, true, SessionManager::getGlobalSession() );
		$main = new \ApiMain( $request, true );

		return $main;
	}

	/**
	 * Find an existing request that fits.
	 *
	 * @param Request[] $requests
	 * @param array $params
	 * @return int|null
	 */
	protected static function findRequestIndex( array $requests, array $params ) {
		foreach ( $requests as $index => $request ) {
			if ( in_array( $params, $request->getKeys() ) ) {
				return $index;
			}
		}

		return null;
	}

	/**
	 * Get prefixed module params.
	 *
	 * @param \ApiBase $module
	 * @return array
	 */
	protected static function getPrefixedParams( \ApiBase $module ) {
		$params = $module->getFinalParams( \ApiBase::GET_VALUES_FOR_HELP );
		$prefix = $module->getModulePrefix();

		$prefixed = [];
		foreach ( $params as $key => $value ) {
			$prefixed[ $prefix . $key ] = $value;
		}

		return $prefixed;
	}

	/**
	 * Add default values to the params.
	 *
	 * @param \ApiBase $main
	 * @param array $params
	 * @return array
	 */
	protected static function addDefaultValues( \ApiBase $main, array $params ) {
		$allowed = self::getPrefixedParams( $main );
		foreach ( $allowed as $key => $options ) {
			if (
				is_array( $options ) &&
				array_key_exists( \ApiBase::PARAM_TYPE, $options ) &&
				$options[\ApiBase::PARAM_TYPE] === 'submodule'
			) {
				$name = null;
				if ( array_key_exists( $key, $params ) ) {
					$name = $params[$key];
					$merge[$key] = $name;
				} elseif ( array_key_exists( \ApiBase::PARAM_DFLT, $options ) ) {
					$name = $options[\ApiBase::PARAM_DFLT];
					$merge[$key] = $name;
				}

				if ( !$name ) {
					continue;
				}

				$module = $main->getModuleManager()->getModule( $name );
				$merge = array_merge( $merge, self::addDefaultValues( $module, $params ) );
			}

			if ( array_key_exists( $key, $params ) ) {
				$merge[$key] = $params[$key];
			} else {
				if ( !is_array( $option ) ) {
					$merge[$key] = $option;
				} elseif ( array_key_exists( \ApiBase::PARAM_DFLT, $options ) ) {
					$merge[$key] = $options[\ApiBase::PARAM_DFLT];
				}
			}
		}

		return $merge;
	}

	/**
	 * Get the mergable keys from an array of params
	 *
	 * @param \ApiBase $main
	 * @param array $params
	 * @return array
	 */
	protected static function getMergeKeys( \ApiBase $main, $params ) {
		$merge = [];
		$allowed = self::getPrefixedParams( $main );
		$subject = array_intersect( array_keys( $params ), array_keys( $allowed ) );
		foreach ( $subject as $key ) {
			if (
				!empty( $params[$key] ) &&
				is_array( $allowed[$key] ) &&
				array_key_exists( \ApiBase::PARAM_TYPE, $allowed[$key] ) &&
				$allowed[$key][\ApiBase::PARAM_TYPE] === 'submodule'
			) {
				$module = $main->getModuleManager()->getModule( $params[$key] );
				$merge = array_merge( $merge, self::getMergeKeys( $module, $params ) );
				continue;
			}

			if (
				is_array( $allowed[$key] ) &&
				array_key_exists( \ApiBase::PARAM_ISMULTI, $allowed[$key] ) &&
				$allowed[$key][\ApiBase::PARAM_ISMULTI] === true
			) {
				$merge[] = $key;
			}
		}

		return $merge;
	}

	/**
	 * Find an existing request that fits.
	 *
	 * @param Request[] $requests
	 * @param array $params
	 * @return Request|null
	 */
	protected static function findExistingRequest( array $requests, array $params ) {
		$main = self::getMain( $params );
		$merge = self::getMergeKeys( $main, $params );
		$unique = array_diff( $merge, array_keys( $params ) );
		foreach ( $requests as $index => $request ) {
			$keys = $request->getMergedKeys();

			// A request cannot have both an id and title parameter. These keys
			// are merge keys since they are a list, but they are unique in the
			// fact that you can have one or the other.
			// @TODO Figure out a better way to handle mutually exclusive properties.
			// maybe use `null` as an identifier on the key to know that it
			// cannot be merged and must remain null (or unset).
			if ( isset( $keys['pageids'] ) && isset( $params['titles'] ) ) {
				continue;
			}

			if ( isset( $keys['titles'] ) && isset( $params['pageids'] ) ) {
				continue;
			}

			// If the merge key has execeeded the limit, continue to the next
			// request.
			if ( $merge ) {
				foreach ( $merge as $prop ) {
					$keys[$prop] = is_array( $keys[$prop] ) ? $keys[$prop] : [ $keys[$prop] ];
					if (
						isset( $keys[$prop] ) &&
						count( $keys[$prop] ) >= \ApiBase::LIMIT_SML1
					) {
						continue 2;
					}
				}
			}

			// Each unique key must only appear on the request a single time
			// with the same value.
			if ( $unique ) {
				foreach ( $unique as $prop ) {
					if ( isset( $keys[$prop] ) && $keys[$prop] !== $params[$prop] ) {
						continue 2;
					}
				}
			}

			return $request;
		}

		return null;
	}
}
