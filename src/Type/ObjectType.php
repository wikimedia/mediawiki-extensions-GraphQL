<?php

namespace MediaWiki\GraphQL\Type;

use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ObjectType as GraphQLObjectType;

class ObjectType extends GraphQLObjectType {
	/**
	 * {@inheritdoc}
	 */
	public function assertValid() {
		parent::assertValid();

		foreach ( $this->getFields() as $field ) {
			foreach ( $this->getInterfaces() as $interface ) {
				foreach ( $interface->getFields() as $ifield ) {
					if ( $ifield->name === $field->name ) {
						// The field has been found on an interface (which will be validated later).
						// Continue to the next field.
						continue 3;
					}
				}
			}

			// The field was not found on any interface.
			throw new InvariantViolation(
				wfMessage( 'graphql-schema-error-missing-field-interface', $this->name, $field->name )->text()
			);
		}
	}
}
