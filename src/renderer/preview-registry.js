/** Trusted editor assets register initializers here; content never supplies code. */
const initializers = new Map();
const listeners = new Set();

export function registerPreview( id, mount ) {
	if (
		typeof id !== 'string' ||
		id.length > 100 ||
		! /^[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)?$/.test( id ) ||
		typeof mount !== 'function'
	) {
		throw new TypeError(
			'A preview needs a registered renderer ID and initializer.'
		);
	}
	if ( initializers.has( id ) ) {
		throw new Error(
			'A preview initializer is already registered for this renderer.'
		);
	}
	initializers.set( id, mount );
	listeners.forEach( ( listener ) => listener() );
	return () => {
		if ( initializers.get( id ) === mount ) {
			initializers.delete( id );
			listeners.forEach( ( listener ) => listener() );
		}
	};
}

export function getPreviewInitializer( id ) {
	return initializers.get( id );
}

export function subscribePreviewRegistry( listener ) {
	listeners.add( listener );
	return () => listeners.delete( listener );
}

// One mounted DOM instance owns its abort signal and optional cleanup.
export function mountPreview( initializer, { root, settings, context } ) {
	const Controller = root.ownerDocument.defaultView.AbortController;
	const controller = new Controller();
	let cleanup;
	try {
		cleanup = initializer( {
			root,
			settings,
			context,
			signal: controller.signal,
		} );
		if ( cleanup !== undefined && typeof cleanup !== 'function' ) {
			throw new TypeError(
				'Preview initializers must return a cleanup function or undefined.'
			);
		}
	} catch ( error ) {
		controller.abort();
		throw error;
	}
	let disposed = false;
	return () => {
		if ( disposed ) {
			return;
		}
		disposed = true;
		controller.abort();
		if ( cleanup ) {
			cleanup();
		}
	};
}
