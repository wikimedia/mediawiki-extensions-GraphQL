import { createElement, useReducer, useEffect } from 'react';
import { render } from 'react-dom';
import GraphiQL from 'graphiql';
import 'whatwg-fetch';
import 'graphiql/graphiql.css';
import './graphiql.css';

function fetcher(graphQLParams) {
  return fetch(window.location.origin + '/graphql', {
    method: 'post',
    headers: {
		Accept: 'application/json',
		'Content-Type': 'application/json',
	},
    body: JSON.stringify(graphQLParams),
  }).then(response => response.json());
}

const initialState = {
	query: undefined,
	variables: undefined,
};

function reducer(state, action) {
	switch (action.type) {
		case 'load':
			return {
				...state,
				query: action.query,
				variables: action.variables,
			};
		case 'edit':
			return {
				...state,
				[action.key]: action.value,
			};
		default:
			throw Error('Unkown Action ');
	}
}

function App() {
	const [state, dispatch] = useReducer(reducer, initialState);

	const createEditHandler = ( key ) => (
		( value ) => {
			if ( window ) {
				const url = new URL(window.location.href);

				if ( value ) {
					url.searchParams.set( key, value );
				} else {
					url.searchParams.delete( key );
				}

				window.history.replaceState({}, '', url.toString());
			}

			dispatch({
				type: 'edit',
				key,
				value
			});
		}
	);

	useEffect(() => {
		if ( !window ) {
			return;
		}

		const url = new URL(window.location.href);

		dispatch({
			type: 'load',
			query: url.searchParams.get('query') || undefined,
			variables: url.searchParams.get('variables') || undefined,
		});
	}, []);

	return createElement(
		GraphiQL,
		{
			fetcher,
			query: state.query,
			variables: state.variables,
			onEditQuery: createEditHandler('query'),
			onEditVariables: createEditHandler('variables'),
		},
		createElement( GraphiQL.Logo, null, ' ' ),
	);
}

const text = document.getElementById('mw-content-text');
const container = document.createElement('div');
text.appendChild( container );

render( createElement( App ), container );

