/**
 * Motor GSAP + ScrollTrigger.
 *
 * Só entra em cena nos presets que realmente precisam dele: scroll travado,
 * parallax, pin, barra de progresso e contador — e quando alguém troca o motor
 * à mão nos ajustes do elemento.
 */
( function () {
	'use strict';

	if ( ! window.wfan || ! window.gsap ) {
		return;
	}

	var gsap = window.gsap;
	var ScrollTrigger = window.ScrollTrigger;
	var util = window.wfan.util;
	var refreshTimer = null;

	if ( ScrollTrigger ) {
		gsap.registerPlugin( ScrollTrigger );
		ScrollTrigger.config( { ignoreMobileResize: true } );
	}

	/**
	 * Imagem com lazy load, slider e widget re-renderizado mudam a altura da
	 * página depois que os gatilhos já foram calculados. Sem refresh, a animação
	 * dispara no lugar errado.
	 */
	function refresh() {
		if ( ! ScrollTrigger ) {
			return;
		}

		window.clearTimeout( refreshTimer );
		refreshTimer = window.setTimeout( function () {
			ScrollTrigger.refresh();
		}, 200 );
	}

	window.addEventListener( 'load', refresh );

	if ( window.elementorFrontend && elementorFrontend.hooks ) {
		elementorFrontend.hooks.addAction( 'frontend/element_ready/global', refresh );
	}

	function endFor( spec ) {
		if ( 'center' === spec.end ) {
			return 'center center';
		}

		if ( 'top' === spec.end ) {
			return 'top top';
		}

		return 'bottom top';
	}

	/**
	 * Mesmos presets do motor próprio, escritos nas propriedades que o GSAP
	 * anima melhor. Usado quando o motor é trocado à mão.
	 */
	function fromVars( spec ) {
		var d = spec.dist;

		switch ( spec.preset ) {
			case 'fade': return { opacity: 0 };
			case 'fade-up': return { opacity: 0, y: d };
			case 'fade-down': return { opacity: 0, y: -d };
			case 'fade-left': return { opacity: 0, x: -d };
			case 'fade-right': return { opacity: 0, x: d };
			case 'slide-up': return { y: d };
			case 'slide-down': return { y: -d };
			case 'slide-left': return { x: -d };
			case 'slide-right': return { x: d };
			case 'zoom-in': return { opacity: 0, scale: spec.scale };
			case 'zoom-out': return { opacity: 0, scale: 2 - spec.scale };
			case 'blur-in': return { opacity: 0, filter: 'blur(' + spec.blur + 'px)' };
			case 'rotate-in': return { opacity: 0, rotation: spec.rot };
			case 'flip-x': return { opacity: 0, rotationY: 90, transformPerspective: 1000 };
			case 'flip-y': return { opacity: 0, rotationX: 90, transformPerspective: 1000 };
			case 'mask-up': return { clipPath: 'inset(100% 0 0 0)' };
			case 'mask-left': return { clipPath: 'inset(0 100% 0 0)' };
			case 'text-lines':
			case 'text-words':
			case 'text-chars': return { opacity: 0, y: d };
			case 'text-mask': return { yPercent: 110 };
			case 'pulse': return { scale: 1 };
			case 'float': return { y: 0 };
			case 'shake': return { x: 0 };
			case 'wobble': return { rotation: 0 };
			default: return { opacity: 0 };
		}
	}

	function loopVars( spec ) {
		switch ( spec.preset ) {
			case 'pulse': return { scale: 1.06 };
			case 'float': return { y: -Math.abs( spec.dist ) };
			case 'shake': return { x: Math.abs( spec.dist ) / 4 };
			case 'wobble': return { rotation: spec.rot };
			default: return null;
		}
	}

	function clear( targets ) {
		targets.forEach( function ( node ) {
			if ( node.__wfanTween ) {
				node.__wfanTween.kill();
				node.__wfanTween = null;
			}
		} );

		gsap.set( targets, { clearProps: 'all' } );
	}

	function counter( el, spec ) {
		var text = ( el.textContent || '' ).trim();
		var start = parseFloat( text.replace( /[^\d.,-]/g, '' ).replace( /\./g, '' ).replace( ',', '.' ) );
		var decimals = ( String( spec.to ).split( '.' )[ 1 ] || '' ).length;
		var proxy = { value: isNaN( start ) ? 0 : start };

		return gsap.to( proxy, {
			value: spec.to,
			duration: Math.max( 0.1, spec.duration / 1000 ),
			delay: spec.delay / 1000,
			ease: spec.easing,
			onUpdate: function () {
				el.textContent = proxy.value.toLocaleString( undefined, {
					minimumFractionDigits: decimals,
					maximumFractionDigits: decimals
				} );
			}
		} );
	}

	window.wfan.register( 'gsap', {

		play: function ( el, spec, targets ) {
			if ( 'counter' === spec.preset ) {
				el.__wfanTween = counter( el, spec );
				return;
			}

			var loop = loopVars( spec );

			if ( loop && spec.loop ) {
				el.__wfanTween = gsap.to( targets, assign( {}, loop, {
					duration: Math.max( 0.2, spec.duration / 1000 ),
					delay: spec.delay / 1000,
					ease: spec.easing,
					repeat: -1,
					yoyo: true
				} ) );

				return;
			}

			var tween = gsap.from( targets, assign( {}, fromVars( spec ), {
				duration: Math.max( 0.01, spec.duration / 1000 ),
				delay: spec.delay / 1000,
				ease: spec.easing,
				stagger: spec.stagger ? spec.stagger.each / 1000 : 0,
				clearProps: 'all'
			} ) );

			targets.forEach( function ( node ) {
				node.__wfanTween = tween;
			} );
		},

		/**
		 * Gatilho travado no scroll: aqui o GSAP é dono da matemática, então o
		 * core não observa nada e só entrega o elemento.
		 */
		bind: function ( el, spec, targets ) {
			if ( ! ScrollTrigger ) {
				util.reveal( el );
				return;
			}

			var trigger = {
				trigger: el,
				start: 'top bottom',
				end: endFor( spec ),
				scrub: true,
				pin: spec.pin ? el : false,
				invalidateOnRefresh: true
			};

			if ( 'pin' === spec.preset ) {
				ScrollTrigger.create( {
					trigger: el,
					start: 'top top',
					end: endFor( spec ),
					pin: true,
					pinSpacing: true,
					invalidateOnRefresh: true
				} );

				return;
			}

			var from = {};
			var to = {};

			switch ( spec.preset ) {
				case 'parallax-y':
					from = { y: -spec.dist };
					to = { y: spec.dist };
					break;

				case 'parallax-x':
					from = { x: -spec.dist };
					to = { x: spec.dist };
					break;

				case 'scrub-fade':
					from = { opacity: 0 };
					to = { opacity: 1 };
					break;

				case 'scrub-scale':
					from = { scale: spec.scale };
					to = { scale: 1 };
					break;

				case 'scrub-rotate':
					from = { rotation: 0 };
					to = { rotation: spec.rot };
					break;

				case 'scrub-reveal':
					from = { clipPath: 'inset(0 0 100% 0)' };
					to = { clipPath: 'inset(0 0 0% 0)' };
					break;

				case 'progress-bar':
					from = { scaleX: 0, transformOrigin: 'left center' };
					to = { scaleX: 1, transformOrigin: 'left center' };
					break;

				case 'lottie-scrub':
					return;

				default:
					from = { opacity: 0 };
					to = { opacity: 1 };
			}

			to.ease = 'none';
			to.scrollTrigger = trigger;

			el.__wfanTween = gsap.fromTo( targets, from, to );
		},

		reset: function ( el, spec, targets ) {
			if ( el.__wfanTween ) {
				el.__wfanTween.kill();
				el.__wfanTween = null;
			}

			clear( targets );
		},

		refresh: refresh
	} );

	function assign( target ) {
		[].slice.call( arguments, 1 ).forEach( function ( source ) {
			if ( ! source ) {
				return;
			}

			Object.keys( source ).forEach( function ( key ) {
				target[ key ] = source[ key ];
			} );
		} );

		return target;
	}
}() );
