<?php

namespace MediaWiki\GraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\InterfaceType as GraphQLInterfaceType;
use GraphQL\Type\Definition\LeafType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Definition\WrappingType;

class InterfaceType extends GraphQLInterfaceType {
	/**
	 * {@inheritdoc}
	 */
	public function assertValid() {
		parent::assertValid();

		$fields = $this->getFields();

		foreach ( $fields as $field ) {
			$type = $field->getType();
			if ( $type instanceof LeafType ) {
				continue;
			}

			if ( $type instanceof WrappingType ) {
				$type = $type->getWrappedType( true );
			}

			if ( $type instanceof ObjectType || $type instanceof UnionType ) {
				throw new InvariantViolation(
					wfMessage(
						'graphql-schema-error-no-composite-fields',
						$this->name,
						$field->name,
						$type->name
					)->text()
				);
			}
		}
	}
}
