<?php

namespace MediaWiki\GraphQL\Source;

use MediaWiki\GraphQL\Source\Api\Request;
use Overblog\DataLoader\DataLoader;
use Overblog\PromiseAdapter\PromiseAdapterInterface;

class Api implements ApiSource {

	/**
	 * @var \IContextSource
	 */
	protected $context;

	/**
	 * @var DataLoader
	 */
	protected $dataLoader;

	/**
	 * @var PromiseAdapterInterface
	 */
	protected $adapter;

	/**
	 * @param PromiseAdapterInterface $adapter
	 * @param \IContextSource $context
	 */
	public function __construct(
		PromiseAdapterInterface $adapter,
		\IContextSource $context
	) {
		$this->adapter = $adapter;
		$this->context = $context;

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
		return $this->dataLoader->load( self::addDefaultValues( $this->getMain( $params ), $params ) );
	}

	/**
	 * DataLoader callback function.
	 *
	 * @param array[] $keys
	 * @return GraphQL\Executor\Promise\Promise
	 */
	protected function batchLoad( array $keys ) {
		// There is no efficient way to determine if a request can safely be merged
		// before it is executed. Therefor, only merge requests that are idenentical
		// which the batch loader already does for us by checking the equality of
		// the keys.
		// @see https://phabricator.wikimedia.org/T216890
		return $this->adapter->createFulfilled( array_map( function ( $params ) {
			$request = new Request( $params );
			return $this->makeRequest( $request->getParams() );
		}, $keys ) );
	}

	/**
	 * Make an API Request.
	 *
	 * @param array $params
	 * @return array
	 */
	protected function makeRequest( array $params ) {
		$module = $this->getMain( $params );
		$module->execute();

		return $module->getResult()->getResultData( null, [ 'Strip' => 'all' ] );
	}

	/**
	 * Retrieves an instance of the main module.
	 *
	 * @param array $params
	 * @return \ApiMain
	 */
	protected function getMain( $params = [] ) {
		$request = $this->context->getRequest();

		$request = new \DerivativeRequest(
			$request,
			$params,
			$request->wasPosted()
		);

		return new \ApiMain( $request, true );
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

				$names = is_array( $name ) ? $name : [ $name ];
				foreach ( $names as $name ) {
					$module = $main->getModuleManager()->getModule( $name );
					$merge = array_merge( $merge, self::addDefaultValues( $module, $params ) );
				}
			}

			if ( array_key_exists( $key, $params ) ) {
				$merge[$key] = $params[$key];
			} else {
				if ( !is_array( $options ) ) {
					$merge[$key] = $options;
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
}
