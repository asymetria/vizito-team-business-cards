/**
 * Vizito Team Business Cards — "Team Card" block editor script.
 *
 * Plain wp.element.createElement, no JSX and no build step. The block is
 * dynamic: the editor shows the server's own rendering, and save() stores
 * nothing but the attributes.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks ) {
		return;
	}

	var el                = wp.element.createElement;
	var __                = wp.i18n.__;
	var useState          = wp.element.useState;
	var useEffect         = wp.element.useEffect;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps     = wp.blockEditor.useBlockProps;
	var PanelBody         = wp.components.PanelBody;
	var SelectControl     = wp.components.SelectControl;
	var Placeholder       = wp.components.Placeholder;
	var Spinner           = wp.components.Spinner;
	var ServerSideRender  = wp.serverSideRender;

	/**
	 * The card picker's options, loaded once from the private REST route.
	 *
	 * @return {Array|null} Options, or null while loading.
	 */
	function useCards() {
		var state = useState( null );

		useEffect( function () {
			wp.apiFetch( { path: '/tbcards/v1/cards' } ).then(
				function ( cards ) {
					state[ 1 ]( cards.map( function ( card ) {
						return { value: String( card.id ), label: card.name + ' (' + card.slug + ')' };
					} ) );
				},
				function () {
					state[ 1 ]( [] );
				}
			);
		}, [] );

		return state[ 0 ];
	}

	wp.blocks.registerBlockType( 'vizito-team-business-cards/card', {
		edit: function ( props ) {
			var cards  = useCards();
			var attrs  = props.attributes;
			var set    = props.setAttributes;
			var blockProps = useBlockProps();

			var picker = el( SelectControl, {
				label: __( 'Card', 'vizito-team-business-cards' ),
				value: String( attrs.cardId || '' ),
				options: [ { value: '', label: __( '— Choose a card —', 'vizito-team-business-cards' ) } ].concat( cards || [] ),
				onChange: function ( value ) {
					set( { cardId: parseInt( value, 10 ) || 0 } );
				},
			} );

			var inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Card', 'vizito-team-business-cards' ) },
					picker,
					el( SelectControl, {
						label: __( 'If the person has left', 'vizito-team-business-cards' ),
						value: attrs.inactive,
						options: [
							{ value: 'hide', label: __( 'Show nothing', 'vizito-team-business-cards' ) },
							{ value: 'notice', label: __( 'Show company details', 'vizito-team-business-cards' ) },
						],
						onChange: function ( value ) {
							set( { inactive: value } );
						},
					} )
				)
			);

			if ( ! attrs.cardId ) {
				return el(
					'div',
					blockProps,
					inspector,
					el(
						Placeholder,
						{ icon: 'id', label: __( 'Team Card', 'vizito-team-business-cards' ) },
						null === cards ? el( Spinner ) : picker
					)
				);
			}

			return el(
				'div',
				blockProps,
				inspector,
				el( ServerSideRender, { block: 'vizito-team-business-cards/card', attributes: attrs } )
			);
		},

		save: function () {
			return null;
		},
	} );
}( window.wp ) );
