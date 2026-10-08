/**
 * Botões e amarrações do painel DW Animações.
 *
 *  - ▶ Testar reproduz a animação no preview sem salvar nada;
 *  - Copiar/Colar reusam exatamente o mesmo código do menu de contexto;
 *  - Salvar na biblioteca grava o conjunto de ajustes com um nome;
 *  - a lista de gatilhos é reduzida ao que o preset escolhido aceita.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.dwAnimEditor || {};
	var keys = cfg.keys || {};
	var i18n = cfg.i18n || {};

	function cp() {
		return window.dwAnimCopyPaste || null;
	}

	function t( key, fallback ) {
		return i18n[ key ] || fallback || key;
	}

	function toast( message ) {
		if ( cp() ) {
			cp().toast( message );
		}
	}

	function selected() {
		return cp() ? cp().selected() : [];
	}

	function currentContainer() {
		var list = selected();

		return list.length ? list[ 0 ] : null;
	}

	function apply( settings ) {
		var container = currentContainer();

		if ( ! container ) {
			toast( t( 'selectOne' ) );
			return;
		}

		var historyId = cp() ? cp().startHistory() : null;

		try {
			$e.run( 'document/elements/settings', {
				container: container,
				settings: settings,
				options: { external: true }
			} );
		} finally {
			if ( cp() ) {
				cp().endHistory( historyId );
			}
		}
	}

	function currentSettings() {
		var container = currentContainer();
		var out = {};

		if ( ! container ) {
			return out;
		}

		( keys.ours || [] ).forEach( function ( key ) {
			var value = container.settings.get( key );

			if ( value === undefined || value === null ) {
				return;
			}

			out[ key ] = value;
		} );

		return out;
	}

	function play() {
		var list = selected();

		if ( ! list.length ) {
			toast( t( 'selectOne' ) );
			return;
		}

		var win = null;

		try {
			win = elementor.$preview[ 0 ].contentWindow;
		} catch ( e ) {}

		if ( ! win || typeof win.dwAnimPlay !== 'function' ) {
			toast( t( 'error' ) );
			return;
		}

		list.forEach( function ( container ) {
			win.dwAnimPlay( container.id );
		} );

		toast( t( 'playing' ) );
	}

	function save() {
		var settings = currentSettings();

		if ( ! settings[ keys.preset ] ) {
			toast( t( 'noPreset' ) );
			return;
		}

		var label = window.prompt( t( 'askName' ), '' );

		if ( ! label ) {
			return;
		}

		$.post( cfg.ajaxUrl, {
			action: 'dwanim_save_preset',
			nonce: cfg.nonce,
			label: label,
			settings: JSON.stringify( settings )
		} ).done( function ( response ) {
			if ( response && response.success ) {
				cfg.library = response.data.items;
				window.dwAnimEditor.library = response.data.items;
				toast( t( 'saved' ) );
				renderSaved();
			} else {
				toast( ( response && response.data && response.data.message ) || t( 'error' ) );
			}
		} ).fail( function () {
			toast( t( 'error' ) );
		} );
	}

	function deleteSaved( id ) {
		$.post( cfg.ajaxUrl, {
			action: 'dwanim_delete_preset',
			nonce: cfg.nonce,
			id: id
		} ).done( function ( response ) {
			if ( response && response.success ) {
				cfg.library = response.data.items;
				window.dwAnimEditor.library = response.data.items;
				renderSaved();
			}
		} );
	}

	/**
	 * Desenha a lista "Minhas animações" dentro do controle. Fica aqui, e não na
	 * view do controle, para que salvar e apagar atualizem a lista na hora, sem
	 * depender de reabrir o painel.
	 */
	function renderSaved() {
		var items = ( window.dwAnimEditor && window.dwAnimEditor.library ) || [];
		var slots = $( '#elementor-panel' ).find( '[data-dw-saved]' );

		if ( ! slots.length ) {
			return;
		}

		if ( ! items.length ) {
			slots.empty();
			return;
		}

		var html = '<div class="dw-anim-saved"><div class="dw-anim-saved__title">'
			+ escapeHtml( t( 'savedTitle', 'Minhas animações' ) ) + '</div>';

		items.forEach( function ( item ) {
			html += '<div class="dw-anim-saved__row">'
				+ '<button type="button" class="dw-anim-saved__apply" data-id="' + escapeHtml( item.id ) + '">'
				+ escapeHtml( item.label ) + '</button>'
				+ '<button type="button" class="dw-anim-saved__delete" data-id="' + escapeHtml( item.id ) + '" title="'
				+ escapeHtml( t( 'deleteOne', 'Apagar' ) ) + '">&times;</button>'
				+ '</div>';
		} );

		slots.html( html + '</div>' );
	}

	function escapeHtml( value ) {
		return $( '<span>' ).text( value === undefined || value === null ? '' : value ).html();
	}

	function presetById( id ) {
		var found = ( cfg.presets || [] ).filter( function ( preset ) {
			return preset.id === id;
		} );

		return found.length ? found[ 0 ] : null;
	}

	/**
	 * Preset de scroll travado não aceita "no hover", e preset de ênfase não
	 * aceita "travado no scroll". Esconder o que não serve evita a configuração
	 * que não faz nada.
	 */
	function narrowTriggers() {
		var panel = $( '#elementor-panel' );
		var value = panel.find( '.dw-anim-picker__value' ).val() || '';
		var preset = presetById( value );
		var select = panel.find( '[data-setting="' + keys.trigger + '"]' );

		if ( ! select.length ) {
			return;
		}

		if ( ! preset ) {
			select.find( 'option' ).prop( 'hidden', false );
			return;
		}

		var allowed = preset.triggers || [];

		select.find( 'option' ).each( function () {
			var option = $( this );
			var ok = ! allowed.length || allowed.indexOf( option.attr( 'value' ) ) !== -1;

			option.prop( 'hidden', ! ok );
		} );

		// Gatilho atual incompatível: cai no primeiro aceito pelo preset.
		if ( allowed.length && allowed.indexOf( select.val() ) === -1 ) {
			select.val( allowed[ 0 ] ).trigger( 'input' ).trigger( 'change' );
		}
	}

	function onPanelClick( event ) {
		var action = event.currentTarget.getAttribute( 'data-dw-action' );

		event.preventDefault();

		if ( 'play' === action ) {
			play();
			return;
		}

		if ( 'copy' === action ) {
			var container = currentContainer();

			if ( ! container ) {
				toast( t( 'selectOne' ) );
				return;
			}

			cp().copy( container );
			return;
		}

		if ( 'paste' === action ) {
			cp().paste( selected() );
			return;
		}

		if ( 'save' === action ) {
			save();
		}
	}

	function init() {
		var panel = $( document.body );

		panel.on( 'click', '#elementor-panel [data-dw-action]', onPanelClick );
		panel.on( 'click', '#elementor-panel .dw-anim-card', function () {
			window.setTimeout( narrowTriggers, 50 );
		} );

		if ( elementor.channels && elementor.channels.editor ) {
			elementor.channels.editor.on( 'change', function () {
				window.setTimeout( narrowTriggers, 50 );
			} );
		}

	}

	window.dwAnimPanel = {
		apply: apply,
		renderSaved: renderSaved,
		deleteSaved: deleteSaved,
		play: play,
		narrowTriggers: narrowTriggers
	};

	if ( window.elementor && window.$e ) {
		init();
	} else {
		$( window ).on( 'elementor:init', init );
	}
}( jQuery ) );
