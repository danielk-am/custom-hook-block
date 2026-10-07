import {
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Button,
	ComboboxControl,
	Notice,
	PanelBody,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { store as coreDataStore } from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';
import Preview from './preview';
import {
	getPreviewInitializer,
	subscribePreviewRegistry,
} from './preview-registry';
import './editor.scss';

export default function Edit( { attributes, setAttributes, context, name } ) {
	const renderers = window.rrbSettings?.renderers || [];
	const legacy = name === 'mytheme/custom-hook-block';
	const chosen =
		renderers.find( ( item ) => item.id === attributes.renderer ) ||
		( legacy &&
			! attributes.renderer &&
			renderers.find( ( item ) =>
				item.legacyHooks.includes( attributes.hookName )
			) );
	const currentPostId = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostId?.(),
		[]
	);
	const [ interact, setInteract ] = useState( false );
	const [ , setRegistryRevision ] = useState( 0 );
	useEffect(
		() =>
			subscribePreviewRegistry( () =>
				setRegistryRevision( ( value ) => value + 1 )
			),
		[]
	);
	const initializer = getPreviewInitializer( chosen?.id );
	const [ previewPost, setPreviewPost ] = useState( '' );
	const [ previewType, setPreviewType ] = useState( 'post' );
	const [ previewSearch, setPreviewSearch ] = useState( '' );
	const previewTypes = useSelect( ( select ) => {
		const types = select( coreDataStore ).getPostTypes( { per_page: -1 } );
		return ( types || [] ).filter(
			( type ) =>
				type.rest_base &&
				[ 'post', 'page', 'product' ].includes( type.slug )
		);
	}, [] );
	useEffect( () => {
		if (
			context?.postType &&
			previewTypes.some( ( type ) => type.slug === context.postType )
		) {
			setPreviewType( context.postType );
		}
	}, [ context?.postType, previewTypes ] );
	const previewRecords = useSelect(
		( select ) => {
			if (
				! previewTypes.some( ( type ) => type.slug === previewType )
			) {
				return [];
			}
			return select( coreDataStore ).getEntityRecords(
				'postType',
				previewType,
				{
					per_page: 20,
					search: previewSearch,
					context: 'edit',
				}
			);
		},
		[ previewType, previewSearch, previewTypes ]
	);
	const previewOptions = ( previewRecords || [] ).map( ( post ) => ( {
		value: String( post.id ),
		label: decodeEntities(
			post.title?.rendered ||
				post.title?.raw ||
				__( 'Untitled', 'custom-hook-block' )
		),
	} ) );
	const contextualId = Number( context?.postId || currentPostId );
	let postId =
		Number.isInteger( contextualId ) && contextualId > 0 ? contextualId : 0;
	if ( previewPost ) {
		postId = Number( previewPost );
	}
	const blockRoot = useRef();
	const blockProps = useBlockProps( {
		className: 'rrb-editor',
		ref: blockRoot,
	} );
	const settings = attributes.settings || {};
	const resolvedSettings = Object.fromEntries(
		Object.entries( chosen?.settings || {} ).map( ( [ key, schema ] ) => [
			key,
			settings[ key ] ?? schema.default,
		] )
	);
	useEffect( () => setInteract( false ), [ chosen?.id, postId ] );
	const exitInteraction = () => {
		setInteract( false );
		blockRoot.current?.focus();
	};
	const update = ( key, value ) =>
		setAttributes( { settings: { ...settings, [ key ]: value } } );
	const previewAttributes = { ...attributes };
	// Compatibility-only hookName is never sent to the primary block schema.
	if ( ! legacy ) {
		delete previewAttributes.hookName;
	}
	return (
		<>
			{ chosen?.interactive && (
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							isPressed={ interact }
							disabled={ ! initializer }
							onClick={ () => setInteract( ! interact ) }
						>
							{ __( 'Interact', 'custom-hook-block' ) }
						</ToolbarButton>
					</ToolbarGroup>
				</BlockControls>
			) }
			<InspectorControls>
				<PanelBody
					title={ __( 'Hook or renderer', 'custom-hook-block' ) }
				>
					<SelectControl
						label={ __(
							'Approved hook or renderer',
							'custom-hook-block'
						) }
						value={ chosen?.id || '' }
						options={ [
							{
								label: __(
									'Choose a hook or renderer',
									'custom-hook-block'
								),
								value: '',
							},
							...renderers.map( ( item ) => ( {
								label: item.title,
								value: item.id,
							} ) ),
						] }
						onChange={ ( renderer ) =>
							setAttributes( {
								renderer,
								hookName: '',
								settings: {},
							} )
						}
					/>
					{ chosen?.description && <p>{ chosen.description }</p> }
					{ interact && (
						<>
							<p>
								{ __(
									'Interact mode is on. Press Escape or return to editing to change the block.',
									'custom-hook-block'
								) }
							</p>
							<Button
								variant="secondary"
								onClick={ exitInteraction }
							>
								{ __(
									'Return to editing',
									'custom-hook-block'
								) }
							</Button>
						</>
					) }
					{ ! chosen && legacy && attributes.hookName && (
						<Notice status="warning" isDismissible={ false }>
							{ __(
								'This saved hook is not registered:',
								'custom-hook-block'
							) }{ ' ' }
							<code>{ attributes.hookName }</code>.{ ' ' }
							{ __(
								'A developer must register it before it can run.',
								'custom-hook-block'
							) }
						</Notice>
					) }
					{ Object.entries( chosen?.settings || {} ).map(
						( [ key, schema ] ) => {
							const value = settings[ key ] ?? schema.default;
							const common = {
								label: schema.title || key,
								help: schema.description,
							};
							if ( schema.type === 'boolean' ) {
								return (
									<ToggleControl
										key={ key }
										{ ...common }
										checked={ !! value }
										onChange={ ( next ) =>
											update( key, next )
										}
									/>
								);
							}
							if ( schema.enum ) {
								return (
									<SelectControl
										key={ key }
										{ ...common }
										value={ String( value ) }
										options={ schema.enum.map(
											( option ) => ( {
												label: String( option ),
												value: String( option ),
											} )
										) }
										onChange={ ( next ) =>
											update(
												key,
												schema.type === 'string'
													? next
													: Number( next )
											)
										}
									/>
								);
							}
							if (
								schema.type === 'number' ||
								schema.type === 'integer'
							) {
								return (
									<TextControl
										key={ key }
										{ ...common }
										type="number"
										value={ value }
										min={ schema.minimum }
										max={ schema.maximum }
										step={
											schema.type === 'integer'
												? 1
												: 'any'
										}
										onChange={ ( next ) => {
											if (
												next !== '' &&
												Number.isFinite(
													Number( next )
												)
											) {
												update( key, Number( next ) );
											}
										} }
									/>
								);
							}
							return (
								<TextareaControl
									key={ key }
									{ ...common }
									value={ value }
									maxLength={ schema.maxLength }
									onChange={ ( next ) => update( key, next ) }
								/>
							);
						}
					) }
				</PanelBody>
				<PanelBody
					title={ __( 'Preview context', 'custom-hook-block' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Content type', 'custom-hook-block' ) }
						value={ previewType }
						options={ previewTypes.map( ( type ) => ( {
							label: type.name,
							value: type.slug,
						} ) ) }
						onChange={ ( value ) => {
							setPreviewType( value );
							setPreviewPost( '' );
							setPreviewSearch( '' );
						} }
					/>
					<ComboboxControl
						label={ __( 'Preview content', 'custom-hook-block' ) }
						value={ previewPost || null }
						options={ previewOptions }
						onFilterValueChange={ setPreviewSearch }
						onChange={ ( value ) => setPreviewPost( value || '' ) }
						help={ __(
							'Search by title. Only content you can edit may be previewed. This choice is temporary and never changes the live page.',
							'custom-hook-block'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ ! chosen ? (
					<Notice status="info" isDismissible={ false }>
						{ __(
							'Choose an approved hook or renderer. Unregistered hooks are not executed.',
							'custom-hook-block'
						) }
					</Notice>
				) : (
					<>
						{ ! postId && (
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Sample preview: no post is selected. Choose a preview post for content that depends on a record.',
									'custom-hook-block'
								) }
							</Notice>
						) }
						{ chosen.interactive && ! initializer && (
							<Notice status="info" isDismissible={ false }>
								{ __(
									'Interactive preview is unavailable: its editor initializer has not loaded.',
									'custom-hook-block'
								) }
							</Notice>
						) }
						<Preview
							key={ JSON.stringify( [
								previewAttributes,
								postId,
								interact,
							] ) }
							block={
								legacy
									? 'mytheme/custom-hook-block'
									: 'registered-render-blocks/renderer'
							}
							attributes={ previewAttributes }
							postId={ postId }
							settings={ resolvedSettings }
							initializer={
								chosen.interactive ? initializer : undefined
							}
							interact={ interact && chosen.interactive }
							onExit={ exitInteraction }
						/>
					</>
				) }
			</div>
		</>
	);
}
