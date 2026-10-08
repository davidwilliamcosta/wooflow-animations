/**
 * Motor próprio: Web Animations API, nenhuma biblioteca.
 *
 * Cobre tudo que é baseado em tempo — entrada, texto e ênfase — que é a maioria
 * esmagadora dos casos. Páginas que só usam estes presets não carregam GSAP.
 */
( function () {
	'use strict';

	if ( ! window.wfan ) {
		return;
	}

	var util = window.wfan.util;

	function px( value ) {
		return value + 'px';
	}

	/**
	 * Quadros de cada preset. Devolve { keyframes, loop, origin }.
	 */
	function build( spec ) {
		var d = spec.dist;
		var frames;
		var loop = false;
		var alternate = false;

		switch ( spec.preset ) {
			case 'fade':
				frames = [ { opacity: 0 }, { opacity: 1 } ];
				break;

			case 'fade-up':
				frames = [ { opacity: 0, transform: 'translateY(' + px( d ) + ')' }, { opacity: 1, transform: 'translateY(0)' } ];
				break;

			case 'fade-down':
				frames = [ { opacity: 0, transform: 'translateY(' + px( -d ) + ')' }, { opacity: 1, transform: 'translateY(0)' } ];
				break;

			case 'fade-left':
				frames = [ { opacity: 0, transform: 'translateX(' + px( -d ) + ')' }, { opacity: 1, transform: 'translateX(0)' } ];
				break;

			case 'fade-right':
				frames = [ { opacity: 0, transform: 'translateX(' + px( d ) + ')' }, { opacity: 1, transform: 'translateX(0)' } ];
				break;

			case 'slide-up':
				frames = [ { transform: 'translateY(' + px( d ) + ')' }, { transform: 'translateY(0)' } ];
				break;

			case 'slide-down':
				frames = [ { transform: 'translateY(' + px( -d ) + ')' }, { transform: 'translateY(0)' } ];
				break;

			case 'slide-left':
				frames = [ { transform: 'translateX(' + px( -d ) + ')' }, { transform: 'translateX(0)' } ];
				break;

			case 'slide-right':
				frames = [ { transform: 'translateX(' + px( d ) + ')' }, { transform: 'translateX(0)' } ];
				break;

			case 'zoom-in':
				frames = [ { opacity: 0, transform: 'scale(' + spec.scale + ')' }, { opacity: 1, transform: 'scale(1)' } ];
				break;

			case 'zoom-out':
				frames = [ { opacity: 0, transform: 'scale(' + ( 2 - spec.scale ) + ')' }, { opacity: 1, transform: 'scale(1)' } ];
				break;

			case 'blur-in':
				frames = [ { opacity: 0, filter: 'blur(' + px( spec.blur ) + ')' }, { opacity: 1, filter: 'blur(0px)' } ];
				break;

			case 'rotate-in':
				frames = [ { opacity: 0, transform: 'rotate(' + spec.rot + 'deg)' }, { opacity: 1, transform: 'rotate(0deg)' } ];
				break;

			case 'flip-x':
				frames = [ { opacity: 0, transform: 'perspective(1000px) rotateY(90deg)' }, { opacity: 1, transform: 'perspective(1000px) rotateY(0deg)' } ];
				break;

			case 'flip-y':
				frames = [ { opacity: 0, transform: 'perspective(1000px) rotateX(90deg)' }, { opacity: 1, transform: 'perspective(1000px) rotateX(0deg)' } ];
				break;

			case 'mask-up':
				frames = [ { clipPath: 'inset(100% 0 0 0)' }, { clipPath: 'inset(0 0 0 0)' } ];
				break;

			case 'mask-left':
				frames = [ { clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0 0 0)' } ];
				break;

			case 'text-lines':
			case 'text-words':
			case 'text-chars':
				frames = [ { opacity: 0, transform: 'translateY(' + px( d ) + ')' }, { opacity: 1, transform: 'translateY(0)' } ];
				break;

			case 'text-mask':
				frames = [ { transform: 'translateY(110%)' }, { transform: 'translateY(0)' } ];
				break;

			case 'pulse':
				frames = [ { transform: 'scale(1)' }, { transform: 'scale(1.06)' }, { transform: 'scale(1)' } ];
				loop = true;
				break;

			case 'float':
				frames = [ { transform: 'translateY(0)' }, { transform: 'translateY(' + px( -Math.abs( d ) ) + ')' }, { transform: 'translateY(0)' } ];
				loop = true;
				break;

			case 'shake':
				frames = [
					{ transform: 'translateX(0)' },
					{ transform: 'translateX(' + px( -Math.abs( d ) / 4 ) + ')' },
					{ transform: 'translateX(' + px( Math.abs( d ) / 4 ) + ')' },
					{ transform: 'translateX(0)' }
				];
				loop = true;
				break;

			case 'wobble':
				frames = [
					{ transform: 'rotate(0deg)' },
					{ transform: 'rotate(' + spec.rot + 'deg)' },
					{ transform: 'rotate(' + -spec.rot + 'deg)' },
					{ transform: 'rotate(0deg)' }
				];
				loop = true;
				break;

			case 'glow':
				frames = [
					{ filter: 'brightness(1)' },
					{ filter: 'brightness(1.25)' },
					{ filter: 'brightness(1)' }
				];
				loop = true;
				alternate = false;
				break;

			default:
				frames = [ { opacity: 0 }, { opacity: 1 } ];
		}

		return {
			keyframes: frames,
			loop: loop && spec.loop,
			alternate: alternate
		};
	}

	/**
	 * O preset de cortina por linha precisa de um invólucro com overflow
	 * escondido: é ele que corta o texto que sobe.
	 */
	function prepare( spec, targets ) {
		if ( 'text-mask' !== spec.preset ) {
			return targets;
		}

		return targets.map( function ( line ) {
			if ( line.__wfanMaskInner ) {
				return line.__wfanMaskInner;
			}

			var inner = document.createElement( 'span' );

			inner.className = 'wfan-mask-inner';

			while ( line.firstChild ) {
				inner.appendChild( line.firstChild );
			}

			line.appendChild( inner );
			line.style.overflow = 'hidden';
			line.style.display = 'block';
			line.__wfanMaskInner = inner;

			return inner;
		} );
	}

	function playOn( node, built, spec, index ) {
		var each = spec.stagger ? spec.stagger.each : 0;
		var options = {
			duration: Math.max( 1, spec.duration ),
			delay: spec.delay + ( index * each ),
			easing: util.cssEase( spec.easing ),
			fill: built.loop ? 'none' : 'both'
		};

		if ( built.loop ) {
			options.iterations = Infinity;
			options.duration = Math.max( 400, spec.duration * 2 );

			if ( built.alternate ) {
				options.direction = 'alternate';
			}
		}

		var player;

		try {
			player = node.animate( built.keyframes, options );
		} catch ( e ) {
			// Navegador sem Web Animations: o conteúdo já está visível, então
			// não animar é só não animar.
			return null;
		}

		if ( ! built.loop ) {
			// Sem isto o quadro final fica "preso" pelo fill e o elemento
			// mantém uma camada de composição para sempre. O estado final é
			// igual ao natural, então cancelar não muda nada na tela.
			player.onfinish = function () {
				try {
					player.cancel();
				} catch ( e ) {}
			};
		}

		return player;
	}

	window.wfan.register( 'css', {

		play: function ( el, spec, targets ) {
			var built = build( spec );
			var nodes = prepare( spec, targets );

			nodes.forEach( function ( node, index ) {
				var player = playOn( node, built, spec, index );

				if ( player ) {
					node.__wfanPlayer = player;
				}
			} );
		},

		reset: function ( el, spec, targets ) {
			var nodes = prepare( spec, targets );

			nodes.forEach( function ( node ) {
				if ( node.__wfanPlayer ) {
					try {
						node.__wfanPlayer.cancel();
					} catch ( e ) {}

					node.__wfanPlayer = null;
				}
			} );
		},

		build: build
	} );

	window.wfanCssBuild = build;
}() );
