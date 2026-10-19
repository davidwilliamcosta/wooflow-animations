/**
 * Popup nascendo de um círculo no ponto em que foi aberto.
 *
 * Não passa pelo core.js: não é animação de elemento e não tem gatilho de
 * tela. O gatilho é o evento do Elementor Pro.
 *
 * jQuery não é escolha nossa: `elementor/popup/show` e `elementor/popup/hide`
 * são disparados com `jQuery.trigger()` e não existem como evento de DOM.
 * Ver CLAUDE.md, regra 28.
 */
( function () {
	'use strict';

	var cfg = window.wfanPopup || {};

	// Tem de bater com WFAN_Popup::OPEN_CLASS e ::ITEM_CLASS.
	var ROOT = 'wfan-pop';
	var OPEN = 'wfan-pop-open';
	var ITEM = '.wfan-popup-item';

	// A duração NÃO é lida aqui: ela vive em custom property, escrita pelo
	// PHP, e quem a usa é o CSS. Ver CLAUDE.md, regra 28.
	var ids = Array.isArray( cfg.ids ) ? cfg.ids : [];

	// Origem do círculo: o último clique. Sem configuração nenhuma, porque a
	// coordenada do clique já está dentro do ícone que abriu o popup.
	var lastPoint = null;

	function log() {
		if ( cfg.debug && window.console ) {
			window.console.log.apply( window.console, [ '[wfan-popup]' ].concat( [].slice.call( arguments ) ) );
		}
	}

	function reduced() {
		if ( ! cfg.reduced ) {
			return false;
		}

		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	function wanted( id ) {
		return ! ids.length || -1 !== ids.indexOf( Number( id ) );
	}

	/*
	 * Fase de captura: o Elementor abre o popup no clique, e se o ponto
	 * fosse lido na fase de borbulha ele chegaria depois da abertura.
	 */
	document.addEventListener( 'click', function ( e ) {
		if ( 'number' !== typeof e.clientX ) {
			return;
		}

		// Clique de teclado (Enter num link) chega com coordenada 0,0: não é
		// um ponto na tela, e usá-lo abriria o círculo no canto.
		if ( ! e.clientX && ! e.clientY ) {
			lastPoint = null;
			return;
		}

		lastPoint = { x: e.clientX, y: e.clientY };
	}, true );

	/**
	 * Raio que cobre a caixa inteira a partir da origem: a maior das quatro
	 * distâncias até os cantos, mais uma folga para a sombra.
	 */
	function radiusFor( box, x, y ) {
		return Math.max(
			Math.hypot( x, y ),
			Math.hypot( box.width - x, y ),
			Math.hypot( x, box.height - y ),
			Math.hypot( box.width - x, box.height - y )
		) + 80;
	}

	function open( modal, box ) {
		var point = lastPoint || { x: window.innerWidth - 40, y: 40 };
		var rect = box.getBoundingClientRect();
		var x = point.x - rect.left;
		var y = point.y - rect.top;

		modal.style.setProperty( '--wfan-pop-x', x + 'px' );
		modal.style.setProperty( '--wfan-pop-y', y + 'px' );
		modal.style.setProperty( '--wfan-pop-r', radiusFor( rect, x, y ) + 'px' );
		modal.style.setProperty( '--wfan-pop-vx', point.x + 'px' );
		modal.style.setProperty( '--wfan-pop-vy', point.y + 'px' );

		/*
		 * A transição é desligada enquanto a origem é escrita. Sem isto o
		 * círculo "viaja" da posição padrão até o ícone DURANTE a abertura,
		 * em vez de já nascer nele.
		 */
		box.style.transition = 'none';
		void box.offsetWidth;
		box.style.transition = '';

		window.requestAnimationFrame( function () {
			modal.classList.add( OPEN );
		} );
	}

	function show( id ) {
		if ( ! wanted( id ) ) {
			return;
		}

		var modal = document.getElementById( 'elementor-popup-modal-' + id );

		if ( ! modal ) {
			return;
		}

		var box = modal.querySelector( '.dialog-widget-content' );

		if ( ! box ) {
			return;
		}

		modal.classList.add( ROOT );
		modal.classList.remove( OPEN );

		[].slice.call( box.querySelectorAll( ITEM ) ).forEach( function ( item, index ) {
			item.style.setProperty( '--wfan-pop-i', index );
		} );

		if ( reduced() ) {
			modal.classList.add( OPEN );
			log( 'aberto sem círculo (movimento reduzido)', id );
			return;
		}

		/*
		 * Dois quadros, e não um: na PRIMEIRA abertura o Elementor ainda
		 * está posicionando o popup quando o evento chega, e a caixa medida
		 * no quadro seguinte ainda é a do layout em construção. O círculo
		 * nascia fora do ícone só na primeira vez.
		 */
		window.requestAnimationFrame( function () {
			window.requestAnimationFrame( function () {
				open( modal, box );
			} );
		} );

		watchClose( modal, box );

		log( 'aberto', id, lastPoint );
	}

	/**
	 * O Elementor não avisa que vai fechar: ele põe a classe da animação de
	 * saída na caixa. Observar a classe é o único jeito de reverter o
	 * círculo junto, em vez de o popup sumir com ele ainda aberto.
	 */
	function watchClose( modal, box ) {
		if ( box._wfanObserver ) {
			box._wfanObserver.disconnect();
		}

		if ( ! window.MutationObserver ) {
			return;
		}

		box._wfanObserver = new window.MutationObserver( function () {
			if ( /\breverse\b|Out\b/.test( box.className ) ) {
				modal.classList.remove( OPEN );
			}
		} );

		box._wfanObserver.observe( box, { attributes: true, attributeFilter: [ 'class' ] } );
	}

	function hide( id ) {
		var modal = document.getElementById( 'elementor-popup-modal-' + id );

		if ( modal ) {
			modal.classList.remove( OPEN );
		}
	}

	function bind() {
		if ( ! window.jQuery ) {
			log( 'sem jQuery: os eventos de popup do Elementor Pro não existem' );
			return;
		}

		window.jQuery( document )
			.on( 'elementor/popup/show', function ( event, id ) {
				show( id );
			} )
			.on( 'elementor/popup/hide', function ( event, id ) {
				hide( id );
			} );

		log( 'amarrado', ids.length ? ids : 'todos os popups' );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', bind );
	} else {
		bind();
	}

	// Só para o tests/popup.js: nada no front-end lê isto.
	window.wfanPopupApi = {
		show: show,
		hide: hide,
		radiusFor: radiusFor,
		point: function () {
			return lastPoint;
		}
	};
}() );
