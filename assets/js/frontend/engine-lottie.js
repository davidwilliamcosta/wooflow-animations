/**
 * Motor Lottie.
 *
 * O progresso no scroll é calculado aqui mesmo, sem GSAP: uma animação Lottie
 * travada no scroll não deve obrigar a página a baixar o ScrollTrigger.
 */
( function () {
	'use strict';

	if ( ! window.wfan || ! window.lottie ) {
		return;
	}

	var lottie = window.lottie;
	var util = window.wfan.util;

	function container( el ) {
		var found = el.querySelector( '.wfan-lottie' );

		if ( found ) {
			return found;
		}

		var box = document.createElement( 'div' );

		box.className = 'wfan-lottie';
		el.appendChild( box );

		return box;
	}

	function load( el, spec, autoplay ) {
		if ( el.__wfanLottie ) {
			return el.__wfanLottie;
		}

		if ( ! spec.lottie || ! spec.lottie.url ) {
			util.reveal( el );
			return null;
		}

		var player = lottie.loadAnimation( {
			container: container( el ),
			renderer: 'svg',
			loop: !! spec.lottie.loop,
			autoplay: !! autoplay,
			path: spec.lottie.url
		} );

		player.setSpeed( spec.lottie.speed || 1 );
		el.__wfanLottie = player;

		return player;
	}

	/**
	 * 0 quando o topo do elemento entra pela base da tela, 1 no ponto final
	 * escolhido nos ajustes.
	 */
	function progress( el, spec ) {
		var rect = el.getBoundingClientRect();
		var vh = window.innerHeight || document.documentElement.clientHeight;
		var start = vh;
		var end;

		if ( 'center' === spec.end ) {
			end = vh / 2;
		} else if ( 'top' === spec.end ) {
			end = 0;
		} else {
			end = -rect.height;
		}

		var span = start - end;

		if ( span <= 0 ) {
			return 0;
		}

		return Math.max( 0, Math.min( 1, ( start - rect.top ) / span ) );
	}

	window.wfan.register( 'lottie', {

		play: function ( el, spec ) {
			var player = load( el, spec, false );

			if ( ! player ) {
				return;
			}

			window.setTimeout( function () {
				player.goToAndPlay( 0, true );
			}, spec.delay );
		},

		bind: function ( el, spec ) {
			var player = load( el, spec, false );

			if ( ! player ) {
				return;
			}

			var ticking = false;

			function update() {
				ticking = false;

				var frames = player.totalFrames || 0;

				if ( ! frames ) {
					return;
				}

				player.goToAndStop( progress( el, spec ) * ( frames - 1 ), true );
			}

			function onScroll() {
				if ( ticking ) {
					return;
				}

				ticking = true;
				window.requestAnimationFrame( update );
			}

			player.addEventListener( 'DOMLoaded', update );
			window.addEventListener( 'scroll', onScroll, { passive: true } );
			window.addEventListener( 'resize', onScroll );
			el.__wfanLottieScroll = onScroll;
		},

		reset: function ( el ) {
			if ( el.__wfanLottie ) {
				el.__wfanLottie.goToAndStop( 0, true );
			}
		}
	} );
}() );
