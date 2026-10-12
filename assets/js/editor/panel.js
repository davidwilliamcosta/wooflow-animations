/**
 * Botões e amarrações do painel WooFlow Animations.
 *
 *  - mexeu num controle nosso, o preview toca sozinho — como no nativo;
 *  - ▶ Testar repete quando se quiser, sem salvar nada;
 *  - Copiar/Colar reusam exatamente o mesmo código do menu de contexto;
 *  - Salvar na biblioteca grava o conjunto de ajustes com um nome;
 *  - a lista de gatilhos é reduzida ao que o preset escolhido aceita.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.wfanEditor || {};
	var keys = cfg.keys || {};
	var i18n = cfg.i18n || {};

	// Espera entre a última mexida no controle e o preview. Abaixo disso, um
	// slider arrastado vira uma rajada de requisições; acima, parece travado.
	var PREVIEW_DELAY = 250;

	// O fundo animado não passa pelo servidor: o contrato dele já está no CSS
	// que o Elementor acabou de reescrever (regra 20). Só o tempo de o
	// Elementor escrever esse CSS.
	var OVERLAY_DELAY = 80;

	// Contratos já respondidos pelo servidor, por assinatura dos ajustes. É o
	// que faz o ▶ Testar ser instantâneo depois da primeira vez: quando o
	// usuário clica, o contrato daquela configuração já veio junto com o
	// preview automático.
	var specs = {};
	var SPECS_MAX = 60;

	var previewTimer = null;
	var overlayTimer = null;

	function cp() {
		return window.wfanCopyPaste || null;
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

		// Aplicar da biblioteca não passa pelo `change` do painel: o modelo
		// muda por comando, não por controle mexido.
		window.setTimeout( narrowTriggers, 50 );
		schedulePreview();
	}

	function settingsOf( container ) {
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

	function currentSettings() {
		return settingsOf( currentContainer() );
	}

	/**
	 * A janela do canvas, onde o core.js mora.
	 */
	function preview() {
		var win = null;

		try {
			win = elementor.$preview[ 0 ].contentWindow;
		} catch ( e ) {}

		return win && typeof win.wfanPlay === 'function' ? win : null;
	}

	/**
	 * Pede ao servidor o contrato de cada elemento selecionado.
	 *
	 * No canvas do editor o wrapper é construído pelo Backbone do Elementor e
	 * não traz o `data-wfan` — o atributo só existe no front-end e na carga
	 * inicial do preview (regra 19). Quem decide motor e gatilho continua sendo
	 * o servidor; aqui só se guarda a resposta por assinatura dos ajustes, para
	 * que repetir a mesma configuração não custe outra ida.
	 *
	 * @param {Array}    items Pares id/ajustes.
	 * @param {Function} done  Recebe (items|null, mensagem).
	 */
	function fetchSpecs( items, done ) {
		var key = JSON.stringify( items );

		if ( specs[ key ] ) {
			done( specs[ key ], '' );
			return;
		}

		$.post( cfg.ajaxUrl, {
			action: cfg.previewAction,
			nonce: cfg.nonce,
			items: JSON.stringify( items )
		} ).done( function ( response ) {
			if ( ! response || ! response.success || ! response.data || ! response.data.items ) {
				done( null, ( response && response.data && response.data.message ) || t( 'error' ) );
				return;
			}

			// O cache é por configuração, e configuração é o que mais muda
			// enquanto se arrasta um slider: sem teto, a sessão inteira de
			// edição ficaria na memória do painel.
			if ( Object.keys( specs ).length > SPECS_MAX ) {
				specs = {};
			}

			specs[ key ] = response.data.items;

			done( response.data.items, '' );
		} ).fail( function () {
			done( null, t( 'error' ) );
		} );
	}

	/**
	 * Reproduz a animação dos elementos selecionados no canvas.
	 *
	 * @param {boolean} loud Avisa por toast. Falso no preview automático, que
	 *                       acontece a cada mexida e não pode encher a tela.
	 */
	function play( loud ) {
		var list = selected();
		var win = preview();

		if ( ! list.length ) {
			if ( loud ) {
				toast( t( 'selectOne' ) );
			}

			return;
		}

		if ( ! win ) {
			if ( loud ) {
				toast( t( 'error' ) );
			}

			return;
		}

		var items = list.map( function ( container ) {
			return { id: container.id, settings: settingsOf( container ) };
		} );

		fetchSpecs( items, function ( found, message ) {
			if ( ! found ) {
				if ( loud ) {
					toast( message || t( 'error' ) );
				}

				return;
			}

			found.forEach( function ( item ) {
				win.wfanPlay( item.id, item.spec );
			} );

			if ( loud ) {
				toast( t( 'playing' ) );
			}
		} );
	}

	/**
	 * Preview automático: toca o que acabou de ser configurado, sem botão.
	 *
	 * É o comportamento da animação de entrada nativa do Elementor, e era o que
	 * faltava aqui — ajustar duração às cegas e clicar em ▶ Testar a cada
	 * tentativa é o que deixava o painel pesado.
	 */
	function schedulePreview() {
		window.clearTimeout( previewTimer );
		previewTimer = window.setTimeout( function () {
			play( false );
		}, PREVIEW_DELAY );
	}

	/**
	 * O fundo animado aparece na hora, sem passar pelo servidor: todo parâmetro
	 * dele viaja no CSS que o Elementor acabou de reescrever (regra 20). Sem
	 * isto, ligar o toggle não mostrava nada até o elemento ser re-renderizado
	 * por outro motivo — ou até recarregar o editor.
	 */
	function scheduleOverlay() {
		window.clearTimeout( overlayTimer );
		overlayTimer = window.setTimeout( function () {
			var win = preview();

			if ( ! win || ! win.wfanOverlay ) {
				return;
			}

			selected().forEach( function ( container ) {
				win.wfanOverlay.syncId( container.id );
			} );
		}, OVERLAY_DELAY );
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
			action: 'wfan_save_preset',
			nonce: cfg.nonce,
			label: label,
			settings: JSON.stringify( settings )
		} ).done( function ( response ) {
			if ( response && response.success ) {
				cfg.library = response.data.items;
				window.wfanEditor.library = response.data.items;
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
			action: 'wfan_delete_preset',
			nonce: cfg.nonce,
			id: id
		} ).done( function ( response ) {
			if ( response && response.success ) {
				cfg.library = response.data.items;
				window.wfanEditor.library = response.data.items;
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
		var items = ( window.wfanEditor && window.wfanEditor.library ) || [];
		var slots = $( '#elementor-panel' ).find( '[data-wfan-saved]' );

		if ( ! slots.length ) {
			return;
		}

		if ( ! items.length ) {
			slots.empty();
			return;
		}

		var html = '<div class="wfan-saved"><div class="wfan-saved__title">'
			+ escapeHtml( t( 'savedTitle', 'Minhas animações' ) ) + '</div>';

		items.forEach( function ( item ) {
			html += '<div class="wfan-saved__row">'
				+ '<button type="button" class="wfan-saved__apply" data-id="' + escapeHtml( item.id ) + '">'
				+ escapeHtml( item.label ) + '</button>'
				+ '<button type="button" class="wfan-saved__delete" data-id="' + escapeHtml( item.id ) + '" title="'
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
	 *
	 * A lista é REESCRITA inteira a cada passada, nunca marcada como `hidden`.
	 * Esconder opção deixa estado no DOM, e o DOM do painel sobrevive à troca
	 * de elemento: era isso que fazia opções sumirem do "Gatilho" até recarregar
	 * a página do editor. Reescrever é idempotente — o resultado só depende do
	 * preset que está selecionado agora.
	 */
	function narrowTriggers() {
		var select = $( '#elementor-panel' ).find( 'select[data-setting="' + keys.trigger + '"]' );

		if ( ! select.length ) {
			return;
		}

		var all = cfg.triggers || {};
		var preset = presetById( presetOf( currentContainer() ) );
		var allowed = preset && preset.triggers && preset.triggers.length
			? preset.triggers
			: Object.keys( all );

		allowed = allowed.filter( function ( id ) {
			return undefined !== all[ id ];
		} );

		if ( ! allowed.length ) {
			return;
		}

		var stamp = allowed.join( ',' );
		var current = select.val();

		if ( select.attr( 'data-wfan-triggers' ) !== stamp ) {
			select.html( allowed.map( function ( id ) {
				return '<option value="' + escapeHtml( id ) + '">' + escapeHtml( all[ id ] ) + '</option>';
			} ).join( '' ) ).attr( 'data-wfan-triggers', stamp );
		}

		// Gatilho atual incompatível: cai no primeiro aceito pelo preset. O
		// `change` é o que avisa o Elementor — sem ele o painel mostraria um
		// valor que o modelo não tem.
		if ( allowed.indexOf( current ) === -1 ) {
			select.val( allowed[ 0 ] ).trigger( 'input' ).trigger( 'change' );
			return;
		}

		select.val( current );
	}

	function presetOf( container ) {
		if ( ! container ) {
			return '';
		}

		return container.settings.get( keys.preset ) || '';
	}

	/**
	 * A mexida foi num controle nosso?
	 *
	 * @param {Object} view View do controle que mudou.
	 * @return {string} Nome da chave, ou vazio se não é nossa.
	 */
	function ourKey( view ) {
		var name = '';

		try {
			name = view.model.get( 'name' ) || '';
		} catch ( e ) {
			return '';
		}

		return 0 === name.indexOf( keys.prefix ) ? name : '';
	}

	function onPanelChange( view ) {
		var name = ourKey( view );

		if ( ! name ) {
			return;
		}

		narrowTriggers();

		if ( 0 === name.indexOf( keys.prefix + 'ovl' ) ) {
			scheduleOverlay();
			return;
		}

		schedulePreview();
	}

	function onPanelClick( event ) {
		var action = event.currentTarget.getAttribute( 'data-wfan-action' );

		event.preventDefault();

		if ( 'play' === action ) {
			play( true );
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

		panel.on( 'click', '#elementor-panel [data-wfan-action]', onPanelClick );

		// O card do picker escreve o valor pela view do controle, e o painel
		// só anuncia a mudança depois. Os 50 ms são para ler o valor novo.
		panel.on( 'click', '#elementor-panel .wfan-card', function () {
			window.setTimeout( narrowTriggers, 50 );
		} );

		if ( elementor.channels && elementor.channels.editor ) {
			elementor.channels.editor.on( 'change', onPanelChange );

			// Abrir a seção também redesenha os gatilhos: o controle pode ter
			// acabado de nascer, com a lista inteira do PHP.
			elementor.channels.editor.on( 'section:activated', function () {
				window.setTimeout( narrowTriggers, 50 );
			} );
		}

		// Trocar de elemento é o caso que mais importa: o <select> do painel é
		// reaproveitado, e sem redesenhar aqui ele chegaria com a lista do
		// elemento anterior.
		if ( elementor.hooks ) {
			[ 'widget', 'container', 'section', 'column' ].forEach( function ( type ) {
				elementor.hooks.addAction( 'panel/open_editor/' + type, function () {
					window.setTimeout( narrowTriggers, 50 );
				} );
			} );
		}
	}

	window.wfanPanel = {
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
