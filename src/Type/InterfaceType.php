<?php

namespace MediaWiki\GraphQL\Type;

use GraphQL\Type\Definition\InterfaceType as GraphQLInterfaceType;

/**
 * Implements the custom AbstractType in order for the schema to retrieve
 * all of the types.
 */
abstract class InterfaceType extends GraphQLInterfaceType implements AbstractType {
}
