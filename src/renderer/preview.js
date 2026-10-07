import { Disabled, Notice, Spinner } from '@wordpress/components';
import {
	RawHTML,
	useEffect,
	useMemo,
	useRef,
	useState,
} from '@wordpress/element';
import { useServerSideRender } from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';
import { mountPreview } from './preview-registry';

// Executable preview behavior must come from registered editor assets.
export function preparePreviewHTML( content, ownerDocument ) {
	const template = ownerDocument.createElement( 'template' );
	template.innerHTML = content;
	template.content
		.querySelectorAll(
			'script,iframe,object,embed,base,meta,link,animate,animateMotion,animateTransform,set'
		)
		.forEach( ( element ) => element.remove() );
	template.content.querySelectorAll( '*' ).forEach( ( element ) => {
		for ( const attribute of [ ...element.attributes ] ) {
			const name = attribute.name.toLowerCase();
			const value = attribute.value
				.replace( /[\u0000-\u0020]/g, '' )
				.toLowerCase();
			if (
				name.startsWith( 'on' ) ||
				name === 'srcdoc' ||
				( [
					'href',
					'xlink:href',
					'src',
					'action',
					'formaction',
				].includes( name ) &&
					/^(javascript|vbscript|data):/.test( value ) &&
					! (
						name === 'src' &&
						/^data:image\/(png|jpeg|gif|webp);/.test( value )
					) )
			) {
				element.removeAttribute( attribute.name );
			}
		}
	} );
	return template.innerHTML;
}

export default function Preview( {
	block,
	attributes,
	postId,
	settings,
	initializer,
	interact,
	onExit,
} ) {
	const root = useRef();
	const exitRef = useRef( onExit );
	exitRef.current = onExit;
	const [ failed, setFailed ] = useState( false );
	const { content, status, error } = useServerSideRender( {
		block,
		attributes,
		urlQueryArgs: { post_id: postId },
		httpMethod: 'POST',
		skipBlockSupportAttributes: true,
	} );
	const html = useMemo(
		() => preparePreviewHTML( content || '', document ),
		[ content ]
	);
	const settingsKey = JSON.stringify( settings );
	const previewSettings = useMemo(
		() => JSON.parse( settingsKey ),
		[ settingsKey ]
	);
	const active =
		interact && !! initializer && ! failed && status === 'success';
	useEffect( () => {
		if ( ! active || ! root.current ) {
			return;
		}
		let dispose;
		try {
			dispose = mountPreview( initializer, {
				root: root.current,
				settings: previewSettings,
				context: { post_id: postId, preview: true },
			} );
		} catch {
			setFailed( true );
			return;
		}
		const element = root.current;
		const stopPropagation = ( event ) => event.stopPropagation();
		const handleEscape = ( event ) => {
			if ( event.key === 'Escape' ) {
				event.preventDefault();
				event.stopPropagation();
				exitRef.current();
			}
		};
		// Keep widget events inside the preview without replacing widget handlers.
		for ( const event of [ 'pointerdown', 'click', 'keydown' ] ) {
			element.addEventListener( event, stopPropagation );
		}
		element.addEventListener( 'keydown', handleEscape, true );
		return () => {
			for ( const event of [ 'pointerdown', 'click', 'keydown' ] ) {
				element.removeEventListener( event, stopPropagation );
			}
			element.removeEventListener( 'keydown', handleEscape, true );
			try {
				dispose();
			} catch {
				// A faulty integration must not stop the editor unmounting this block.
			}
		};
	}, [ active, initializer, html, postId, previewSettings ] );
	if ( status === 'error' ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ error ||
					__(
						'The preview could not be loaded.',
						'custom-hook-block'
					) }
			</Notice>
		);
	}
	if ( status !== 'success' ) {
		return <Spinner />;
	}
	const output = (
		<div
			ref={ root }
			role="group"
			aria-label={ __( 'Block preview', 'custom-hook-block' ) }
			tabIndex={ active ? 0 : -1 }
		>
			<RawHTML key={ active ? 'interactive' : 'static' }>
				{ html }
			</RawHTML>
		</div>
	);
	return (
		<>
			{ failed && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'This interactive preview could not start. Return to editing and try again.',
						'custom-hook-block'
					) }
				</Notice>
			) }
			{ active ? output : <Disabled>{ output }</Disabled> }
		</>
	);
}
