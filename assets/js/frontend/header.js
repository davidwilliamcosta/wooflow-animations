/**
 * Barra fixa com fundo e sombra progressivos.
 *
 * Não passa pelo core.js: não é animação de elemento, não tem gatilho de tela
 * e não esconde conteúdo. Ver CLAUDE.md, regra 5.
 *
 * A cor NÃO vem de um ajuste nosso — vem do próprio elemento, escolhida no
 * Elementor em Estilo > Fundo. O script desliga o fundo que ele mesmo
 * controla, lê a cor computada, religa e passa a escrever o mesmo RGB com
 * alfa variável. Ver CLAUDE.md, regra 27.
 */
( function () {
	'use strict';

	var cfg = window.wfanHeader || {};

	// Tem de bater com WFAN_Header::CLASS_NAME e ::OFF_CLASS.
	var SELECTOR = cfg.selector || '.wfan-header';
	var OFF = 'wfan-header--off';

	var distance = parseInt( cfg.distance, 10 ) || 280;
	var smooth = parseInt( cfg.smooth, 10 );

	var header = null;
	var rgb = null;
	var maxAlpha = 1;
	var shadow = !! cfg.shadow;

	var current = 0;
	var target = 0;
	var last = 0;
	var running = false;

	if ( isNaN( smooth ) ) {
		smooth = 220;
	}

	function log() {
		if ( cfg.debug && window.console ) {
			window.console.log.apply( window.console, [ '[wfan-header]' ].concat( [].slice.call( arguments ) ) );
		}
	}

	function reduced() {
		if ( ! cfg.reduced ) {
			return false;
		}

		return !! ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches );
	}

	function scrollY() {
		return window.pageYOffset || document.documentElement.scrollTop || 0;
	}

	/**
	 * Lê a cor que o usuário escolheu no Elementor.
	 *
	 * A classe de desligamento existe só para esta leitura: com o fundo
	 * controlado pelo script já aplicado, `getComputedStyle` devolveria o
	 * valor transparente que nós mesmos acabamos de escrever.
	 *
	 * Devolve null quando a cor não é um `rgb()`/`rgba()` reconhecível — um
	 * degradê, por exemplo. Nesse caso o chamador desiste e deixa o fundo
	 * original em paz: barra sem efeito é melhor que barra transparente.
	 */
	function readColor( el ) {
		el.classList.add( OFF );

		var raw = window.getComputedStyle( el ).backgroundColor || '';

		el.classList.remove( OFF );

		if ( 0 !== raw.indexOf( 'rgb' ) ) {
			return null;
		}

		var parts = ( raw.match( /[\d.]+/g ) || [] ).map( Number );

		if ( parts.length < 3 ) {
			return null;
		}

		return {
			rgb: [ parts[ 0 ], parts[ 1 ], parts[ 2 ] ],
			alpha: parts.length > 3 ? parts[ 3 ] : 1
		};
	}

	function progress() {
		return Math.min( Math.max( scrollY() / distance, 0 ), 1 );
	}

	function paint() {
		// Entrada e saída suaves: sem isto, a cor começa a aparecer no
		// primeiro pixel de rolagem e o efeito fica brusco nas duas pontas.
		var eased = current * current * ( 3 - 2 * current );

		header.style.setProperty( '--wfan-hdr-p', shadow ? eased.toFixed( 3 ) : '0' );
		header.style.setProperty(
			'--wfan-hdr-bg',
			'rgba(' + rgb[ 0 ] + ',' + rgb[ 1 ] + ',' + rgb[ 2 ] + ',' + ( eased * maxAlpha ).toFixed( 3 ) + ')'
		);
	}

	/**
	 * Amortecimento exponencial, independente da taxa de quadros: o passo é
	 * calculado pelo tempo decorrido, não por um fator fixo por quadro. O
	 * `dt` é limitado a 64 ms para a aba voltar do segundo plano sem um salto.
	 */
	function frame( now ) {
		var dt = last ? Math.min( now - last, 64 ) : 16;

		last = now;
		current += ( target - current ) * ( 1 - Math.exp( -dt / smooth ) );

		// O laço PRECISA parar: um requestAnimationFrame eterno em toda
		// página do site é trabalho contínuo por nada, e aparece no consumo
		// de bateria antes de aparecer na tela.
		if ( Math.abs( target - current ) < 0.001 ) {
			current = target;
			running = false;
		}

		paint();

		if ( running ) {
			window.requestAnimationFrame( frame );
		}
	}

	function onScroll() {
		target = progress();

		if ( reduced() || ! smooth ) {
			current = target;
			paint();
			return;
		}

		if ( ! running ) {
			running = true;
			last = 0;
			window.requestAnimationFrame( frame );
		}
	}

	function init() {
		header = document.querySelector( SELECTOR );

		if ( ! header ) {
			log( 'nenhuma barra com', SELECTOR );
			return;
		}

		var color = readColor( header );

		if ( ! color ) {
			log( 'cor do fundo não reconhecida; a barra fica como está' );
			return;
		}

		rgb = color.rgb;
		maxAlpha = color.alpha;
		header.classList.add( 'is-ready' );

		window.addEventListener( 'scroll', onScroll, { passive: true } );

		// Estado inicial sem animação: cobre a página aberta já rolada, por
		// âncora ou pela restauração de posição do navegador.
		current = target = progress();
		paint();

		log( 'pronta', rgb, 'alfa', maxAlpha );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	// Só para o teste: nada no front-end lê isto.
	window.wfanHeaderApi = {
		init: init,
		paint: function () {
			return paint();
		},
		state: function () {
			return { current: current, target: target, running: running, rgb: rgb };
		},
		step: function ( now ) {
			return frame( now );
		},
		scroll: onScroll
	};
}() );
