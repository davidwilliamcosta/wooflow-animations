/**
 * View do controle visual `dw-anim-picker`.
 *
 * O template vem do PHP (includes/class-control-picker.php), porque o catálogo
 * é o mesmo para todos os elementos. Aqui ficam só seleção, grupos e busca.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.dwAnimEditor || {};

	function labelFor( value ) {
		var found = ( cfg.presets || [] ).filter( function ( preset ) {
			return preset.id === value;
		} );

		return found.length ? found[ 0 ].label : value;
	}

	function register() {
		if ( ! window.elementor || ! elementor.modules || ! elementor.modules.controls || ! elementor.modules.controls.BaseData ) {
			return false;
		}

		var Base = elementor.modules.controls.BaseData;

		// `ui` e `events` são função na base do Elementor, mas herdar sem
		// assumir isso custa três linhas e sobrevive a uma mudança de versão.
		function inherit( key, context, args ) {
			var source = Base.prototype[ key ];

			if ( 'function' === typeof source ) {
				return source.apply( context, args );
			}

			return _.extend( {}, source || {} );
		}

		var PickerView = Base.extend( {

			ui: function () {
				var ui = inherit( 'ui', this, arguments );

				ui.cards = '.dw-anim-card';
				ui.tabs = '.dw-anim-tab';
				ui.search = '.dw-anim-picker__q';
				ui.saved = '[data-dw-saved]';

				return ui;
			},

			events: function () {
				return _.extend( inherit( 'events', this, arguments ), {
					'click @ui.cards': 'onCardClick',
					'click @ui.tabs': 'onTabClick',
					'input @ui.search': 'onSearch',
					'click .dw-anim-saved__apply': 'onSavedApply',
					'click .dw-anim-saved__delete': 'onSavedDelete'
				} );
			},

			onReady: function () {
				this.syncSelection();

				if ( window.dwAnimPanel ) {
					window.dwAnimPanel.renderSaved();
				}
			},

			// Chamado pelo Elementor sempre que o valor do controle muda.
			applySavedValue: function () {
				Base.prototype.applySavedValue.apply( this, arguments );
				this.syncSelection();
			},

			syncSelection: function () {
				var value = this.getControlValue() || '';

				this.ui.cards.removeClass( 'is-selected' );
				this.ui.cards.filter( '[data-value="' + value.replace( /"/g, '' ) + '"]' ).addClass( 'is-selected' );
			},

			onCardClick: function ( event ) {
				event.preventDefault();

				var value = event.currentTarget.getAttribute( 'data-value' ) || '';

				this.setValue( value );
				this.syncSelection();
			},

			onTabClick: function ( event ) {
				event.preventDefault();

				var group = event.currentTarget.getAttribute( 'data-group' );

				this.ui.tabs.removeClass( 'is-active' );
				$( event.currentTarget ).addClass( 'is-active' );
				this.ui.search.val( '' );
				this.filter( group, '' );
			},

			onSearch: function ( event ) {
				var term = ( event.currentTarget.value || '' ).toLowerCase();
				var group = this.ui.tabs.filter( '.is-active' ).attr( 'data-group' ) || '*';

				this.filter( group, term );
			},

			filter: function ( group, term ) {
				this.ui.cards.each( function () {
					var card = $( this );
					var cardGroup = card.attr( 'data-group' );
					var label = ( card.attr( 'data-label' ) || '' ).toLowerCase();
					var matchGroup = '*' === group || cardGroup === group || '*' === cardGroup;
					var matchTerm = ! term || label.indexOf( term ) !== -1;

					card.toggleClass( 'is-hidden', ! ( matchGroup && matchTerm ) );
				} );
			},

			onSavedApply: function ( event ) {
				event.preventDefault();

				var id = event.currentTarget.getAttribute( 'data-id' );
				var items = ( window.dwAnimEditor && window.dwAnimEditor.library ) || [];
				var found = items.filter( function ( item ) {
					return item.id === id;
				} );

				if ( found.length && window.dwAnimPanel ) {
					window.dwAnimPanel.apply( found[ 0 ].settings );
				}
			},

			onSavedDelete: function ( event ) {
				event.preventDefault();

				var id = event.currentTarget.getAttribute( 'data-id' );

				if ( window.dwAnimPanel ) {
					window.dwAnimPanel.deleteSaved( id );
				}
			}
		} );

		elementor.addControlView( 'dw-anim-picker', PickerView );

		return true;
	}

	window.dwAnimPickerLabel = labelFor;

	if ( window.elementor && elementor.modules ) {
		register();
	} else {
		$( window ).on( 'elementor:init', register );
	}
}( jQuery ) );
