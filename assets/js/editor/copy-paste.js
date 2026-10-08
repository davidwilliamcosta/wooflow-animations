/**
 * Copiar e colar animação entre elementos do Elementor.
 *
 * Diferenças em relação à primeira versão deste plugin:
 *  - o payload carrega também os ajustes DW, e tem número de versão;
 *  - colar em N elementos é UMA entrada de histórico, não N;
 *  - os atalhos passam pelo $e.shortcuts, com fallback que se desliga sozinho
 *    quando o registro do Elementor assume o teclado.
 */
( function ( $ ) {
	'use strict';

	var STORAGE_KEY = 'dwElementorCopiedAnimation';
	var cfg = window.dwAnimEditor || {};
	var i18n = cfg.i18n || {};
	var keys = cfg.keys || {};
	var memory = null;
	var shortcutHandled = false;

	function t( key, fallback ) {
		return i18n[ key ] || fallback || key;
	}

	function nativeKeys( container ) {
		var isWidget = container.model.get( 'elType' ) === 'widget';

		return isWidget ? keys.nativeWidget : keys.nativeBlock;
	}

	function ourKeys() {
		return keys.ours || [];
	}

	function toast( message ) {
		try {
			elementor.notifications.showToast( { message: message } );
		} catch ( e ) {}
	}

	function log( message, data ) {
		if ( window.console && cfg.debug ) {
			window.console.log( '[DW Animações] ' + message, data );
		}
	}

	function getStored() {
		if ( memory ) {
			return memory;
		}

		var raw = null;

		try {
			raw = window.localStorage.getItem( STORAGE_KEY );
		} catch ( e ) {
			return null;
		}

		if ( ! raw ) {
			return null;
		}

		var data;

		try {
			data = JSON.parse( raw );
		} catch ( e ) {
			return null;
		}

		return migrate( data );
	}

	/**
	 * O formato antigo era um objeto plano com as cinco chaves nativas e sem
	 * versão nenhuma. Quem copiou antes de atualizar o plugin continua colando.
	 */
	function migrate( data ) {
		if ( ! data || typeof data !== 'object' ) {
			return null;
		}

		if ( data.v ) {
			return data;
		}

		return {
			v: keys.version || 2,
			native: {
				name: data.name || '',
				tablet: data.tablet || '',
				mobile: data.mobile || '',
				duration: data.duration || '',
				delay: data.delay || ''
			},
			dw: {}
		};
	}

	function setStored( data ) {
		memory = data;

		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( data ) );
		} catch ( e ) {}
	}

	function selectedContainers() {
		var list = [];

		try {
			list = elementor.selection.getElements() || [];
		} catch ( e ) {}

		return list.filter( function ( c ) {
			return c && c.model && c.settings;
		} );
	}

	function copyAnimation( container ) {
		if ( ! container || ! container.settings ) {
			return;
		}

		var map = nativeKeys( container );
		var data = { v: keys.version || 2, native: {}, dw: {} };

		Object.keys( map ).forEach( function ( slot ) {
			data.native[ slot ] = container.settings.get( map[ slot ] ) || '';
		} );

		ourKeys().forEach( function ( key ) {
			var value = container.settings.get( key );

			if ( value !== undefined ) {
				data.dw[ key ] = value;
			}
		} );

		setStored( data );
		log( 'copiado', data );

		toast( data.native.name || data.dw[ keys.preset ]
			? t( 'copied' )
			: t( 'copiedEmpty' ) );
	}

	/**
	 * Monta o objeto de settings para um tipo de elemento. As chaves nativas
	 * mudam entre widget e bloco, então cada tipo recebe o seu mapa.
	 */
	function settingsFor( container, data ) {
		var map = nativeKeys( container );
		var settings = {};

		Object.keys( map ).forEach( function ( slot ) {
			settings[ map[ slot ] ] = data.native && data.native[ slot ] ? data.native[ slot ] : '';
		} );

		ourKeys().forEach( function ( key ) {
			if ( data.dw && Object.prototype.hasOwnProperty.call( data.dw, key ) ) {
				settings[ key ] = data.dw[ key ];
			}
		} );

		return settings;
	}

	function pasteAnimation( containers ) {
		var data = getStored();

		if ( ! data ) {
			toast( t( 'nothingYet' ) );
			return;
		}

		containers = ( containers || [] ).filter( function ( c ) {
			return c && c.model;
		} );

		if ( ! containers.length ) {
			toast( t( 'selectOne' ) );
			return;
		}

		// Widget e bloco não compartilham as chaves nativas, então vão em grupos
		// separados — mas as duas chamadas entram no mesmo log de histórico,
		// para que um Ctrl+Z desfaça a colagem inteira.
		var groups = { widget: [], block: [] };

		containers.forEach( function ( container ) {
			var bucket = container.model.get( 'elType' ) === 'widget' ? 'widget' : 'block';

			groups[ bucket ].push( container );
		} );

		var historyId = startHistory();

		try {
			Object.keys( groups ).forEach( function ( bucket ) {
				if ( ! groups[ bucket ].length ) {
					return;
				}

				$e.run( 'document/elements/settings', {
					containers: groups[ bucket ],
					settings: settingsFor( groups[ bucket ][ 0 ], data ),
					options: { external: true }
				} );
			} );
		} finally {
			endHistory( historyId );
		}

		log( 'colado', { count: containers.length } );

		toast(
			( containers.length === 1 ? t( 'pastedOne' ) : t( 'pastedMany' ) )
				.replace( '%d', containers.length )
		);
	}

	function startHistory() {
		try {
			return $e.internal( 'document/history/start-log', {
				type: 'change',
				title: t( 'historyPaste' )
			} );
		} catch ( e ) {
			return null;
		}
	}

	function endHistory( id ) {
		if ( ! id ) {
			return;
		}

		try {
			$e.internal( 'document/history/end-log', { id: id } );
		} catch ( e ) {}
	}

	function closeMenu() {
		var docs = [ document ];

		try {
			docs.push( elementor.$preview[ 0 ].contentWindow.document );
		} catch ( e ) {}

		docs.forEach( function ( doc ) {
			try {
				$( doc ).find( '.elementor-context-menu' ).hide();
			} catch ( e ) {}
		} );
	}

	function isMac() {
		return /Mac|iPod|iPhone|iPad/.test( window.navigator.platform || '' );
	}

	function shortcutLabel( letter ) {
		return ( isMac() ? 'Cmd+Alt+' : 'Ctrl+Alt+' ) + letter;
	}

	function addGroup( groups, view ) {
		if ( ! view ) {
			return groups;
		}

		groups.push( {
			name: 'dw-animation',
			actions: [
				{
					name: 'dw-copy-animation',
					icon: 'eicon-animation',
					title: t( 'copyTitle' ),
					shortcut: shortcutLabel( 'C' ),
					callback: function () {
						try {
							copyAnimation( view.getContainer() );
						} catch ( e ) {
							window.console && window.console.error( 'DW Animações:', e );
						}

						closeMenu();
					}
				},
				{
					name: 'dw-paste-animation',
					icon: 'eicon-animation',
					title: t( 'pasteTitle' ),
					shortcut: shortcutLabel( 'V' ),
					isEnabled: function () {
						return !! getStored();
					},
					callback: function () {
						try {
							var container = view.getContainer();
							var selected = selectedContainers();
							var inSelection = selected.some( function ( c ) {
								return c.id === container.id;
							} );

							pasteAnimation( inSelection && selected.length ? selected : [ container ] );
						} catch ( e ) {
							window.console && window.console.error( 'DW Animações:', e );
						}

						closeMenu();
					}
				}
			]
		} );

		return groups;
	}

	function runCopy() {
		var selected = selectedContainers();

		if ( selected.length ) {
			copyAnimation( selected[ 0 ] );
		}
	}

	function runPaste() {
		pasteAnimation( selectedContainers() );
	}

	/**
	 * Registra nos dois formatos possíveis de id de atalho. Só um deles casa com
	 * o evento, então não há risco de disparar duas vezes — e assim o plugin não
	 * depende da ordem em que o Elementor monta o id dos modificadores.
	 */
	function registerShortcuts() {
		if ( ! window.$e || ! $e.shortcuts || typeof $e.shortcuts.register !== 'function' ) {
			return false;
		}

		var pairs = [
			[ 'ctrl+alt+c', runCopy ],
			[ 'alt+ctrl+c', runCopy ],
			[ 'ctrl+alt+v', runPaste ],
			[ 'alt+ctrl+v', runPaste ]
		];

		var registered = false;

		pairs.forEach( function ( pair ) {
			try {
				$e.shortcuts.register( pair[ 0 ], {
					callback: function () {
						shortcutHandled = true;
						pair[ 1 ]();
					}
				} );

				registered = true;
			} catch ( e ) {}
		} );

		return registered;
	}

	/**
	 * Rede de segurança: se o registro do Elementor não disparar, o keydown
	 * assume. A ação é adiada um tick justamente para saber se o registro
	 * disparou — assim ela nunca roda duas vezes.
	 */
	function onKeyDown( e ) {
		if ( ! ( e.ctrlKey || e.metaKey ) || ! e.altKey || e.shiftKey ) {
			return;
		}

		var target = e.target || {};
		var tag = target.tagName ? target.tagName.toLowerCase() : '';

		if ( tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable ) {
			return;
		}

		var action = null;

		if ( e.code === 'KeyC' ) {
			action = runCopy;
		} else if ( e.code === 'KeyV' ) {
			action = runPaste;
		}

		if ( ! action ) {
			return;
		}

		e.preventDefault();
		shortcutHandled = false;

		window.setTimeout( function () {
			if ( ! shortcutHandled ) {
				action();
			}
		}, 0 );
	}

	function bindKeys( doc ) {
		if ( ! doc || doc.__dwAnimBound ) {
			return;
		}

		doc.__dwAnimBound = true;
		doc.addEventListener( 'keydown', onKeyDown, true );
	}

	function bindPreview() {
		try {
			bindKeys( elementor.$preview[ 0 ].contentWindow.document );
		} catch ( e ) {}
	}

	function init() {
		[ 'widget', 'section', 'column', 'container' ].forEach( function ( type ) {
			elementor.hooks.addFilter( 'elements/' + type + '/contextMenuGroups', addGroup );
		} );

		registerShortcuts();
		bindKeys( document );
		bindPreview();

		if ( typeof elementor.on === 'function' ) {
			elementor.on( 'preview:loaded', bindPreview );
		}

		log( 'editor pronto' );
	}

	// API usada pelos botões do painel (panel.js).
	window.dwAnimCopyPaste = {
		copy: copyAnimation,
		paste: pasteAnimation,
		selected: selectedContainers,
		stored: getStored,
		settingsFor: settingsFor,
		startHistory: startHistory,
		endHistory: endHistory,
		toast: toast,
		t: t
	};

	if ( window.elementor && window.$e && elementor.hooks ) {
		init();
	} else {
		$( window ).on( 'elementor:init', init );
	}
}( jQuery ) );
