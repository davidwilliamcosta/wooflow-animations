/**
 * Scroll suave com Lenis.
 *
 * O que sempre quebra em implementação de scroll suave está tratado aqui:
 * a ponte com o ScrollTrigger, as âncoras internas, o offset da barra de
 * administração e a preferência por menos movimento.
 */
( function () {
	'use strict';

	var cfg = window.wfanLenis || {};

	if ( ! window.Lenis ) {
		return;
	}

	if ( cfg.reduced && window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}

	var lenis = new window.Lenis( {
		lerp: parseFloat( cfg.lerp ) || 0.1,
		duration: parseFloat( cfg.duration ) || 1.2,
		smoothWheel: false !== cfg.smoothWheel,
		syncTouch: !! cfg.syncTouch
	} );

	window.wfanLenisInstance = lenis;

	var bridged = false;
	var usingTicker = false;

	/**
	 * Com GSAP na página, quem toca o relógio é o ticker dele — dois loops de
	 * requestAnimationFrame concorrendo produzem tremor no scroll travado.
	 */
	function bridge() {
		if ( bridged || ! window.gsap || ! window.ScrollTrigger ) {
			return false;
		}

		bridged = true;
		usingTicker = true;

		lenis.on( 'scroll', window.ScrollTrigger.update );

		window.gsap.ticker.add( function ( time ) {
			lenis.raf( time * 1000 );
		} );

		window.gsap.ticker.lagSmoothing( 0 );

		return true;
	}

	if ( ! bridge() ) {
		// O GSAP pode ser enfileirado depois: tenta de novo quando a página
		// termina de carregar, e uma última vez um segundo depois.
		window.addEventListener( 'load', bridge );
		window.setTimeout( bridge, 1000 );

		( function raf( time ) {
			if ( ! usingTicker ) {
				lenis.raf( time );
				window.requestAnimationFrame( raf );
			}
		}( 0 ) );
	}

	var offset = -1 * ( parseInt( cfg.adminBar, 10 ) || 0 );

	function target( hash ) {
		if ( ! hash || '#' === hash ) {
			return null;
		}

		try {
			return document.querySelector( hash );
		} catch ( e ) {
			return null;
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var link = event.target && event.target.closest ? event.target.closest( 'a[href*="#"]' ) : null;

		if ( ! link || link.getAttribute( 'target' ) === '_blank' ) {
			return;
		}

		var href = link.getAttribute( 'href' ) || '';
		var hash = href.indexOf( '#' ) === 0 ? href : null;

		// Link para outra página com âncora não é nosso caso.
		if ( ! hash && href.indexOf( '#' ) > 0 ) {
			var parts = href.split( '#' );

			if ( parts[ 0 ] && parts[ 0 ] !== window.location.pathname && parts[ 0 ] !== window.location.href ) {
				return;
			}

			hash = '#' + parts[ 1 ];
		}

		var node = target( hash );

		if ( ! node ) {
			return;
		}

		event.preventDefault();
		lenis.scrollTo( node, { offset: offset } );
	} );

	if ( window.location.hash ) {
		var initial = target( window.location.hash );

		if ( initial ) {
			window.setTimeout( function () {
				lenis.scrollTo( initial, { offset: offset, immediate: true } );
			}, 50 );
		}
	}
}() );
