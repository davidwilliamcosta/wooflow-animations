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

			/*
			 * `blur(0px)` e não `blur(0)` no quadro final: a interpolação de
			 * filtros exige a mesma lista de funções nas duas pontas, e um
			 * zero sem unidade faz o Chrome tratar o quadro como `none` e
			 * cortar o desfoque de uma vez, sem transição.
			 */
			case 'text-blur-words':
			case 'text-blur-chars':
				frames = [
					{ opacity: 0, filter: 'blur(' + px( spec.blur ) + ')', transform: 'translateY(' + px( d ) + ')' },
					{ opacity: 1, filter: 'blur(0px)', transform: 'translateY(0)' }
				];
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
			line.classList.add( 'wfan-line--mask' );
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


	/*
	 * Contador. O número escrito no HTML é o FINAL — é o que robô, buscador e
	 * leitor de tela leem —, e o motor só troca o texto por valores menores
	 * durante a contagem, devolvendo o original ao fim. Ver CLAUDE.md, regra 32.
	 *
	 * Só o nó de texto que tem o número é tocado: "+ de 450" ou "R$ 1.250,50"
	 * mantêm o que vem antes e depois, e um <span> vizinho não é desmontado.
	 */
	var NUMBER = /\d+(?:[.,]\d+)*/;

	function numberNode( el ) {
		var walker = document.createTreeWalker( el, 4 /* SHOW_TEXT */ );
		var node;

		while ( ( node = walker.nextNode() ) ) {
			var tag = node.parentNode && node.parentNode.nodeName;

			if ( 'SCRIPT' !== tag && 'STYLE' !== tag && NUMBER.test( node.nodeValue ) ) {
				return node;
			}
		}

		return null;
	}

	/**
	 * Lê "1.250,5", "1,250.5", "1.250", "3,5" e "450": acha qual caractere é o
	 * decimal e qual é o de milhar, para escrever os valores intermediários no
	 * mesmo formato do texto. Um separador só, seguido de exatamente três
	 * dígitos, é milhar ("1.250"); com outra quantidade de dígitos, decimal.
	 */
	function readNumber( token ) {
		var last = Math.max( token.lastIndexOf( '.' ), token.lastIndexOf( ',' ) );
		var dec = '';
		var thou = '';

		if ( last > -1 ) {
			var ch = token.charAt( last );
			var other = '.' === ch ? ',' : '.';
			var count = token.split( ch ).length - 1;
			var tail = token.length - last - 1;

			if ( token.indexOf( other ) > -1 ) {
				dec = ch;
				thou = other;
			} else if ( count > 1 || ( 3 === tail && ! /^0\D/.test( token ) ) ) {
				thou = ch;
			} else {
				dec = ch;
			}
		}

		var plain = thou ? token.split( thou ).join( '' ) : token;

		if ( dec ) {
			plain = plain.replace( dec, '.' );
		}

		return {
			value: parseFloat( plain ),
			decimals: dec ? token.length - token.lastIndexOf( dec ) - 1 : 0,
			dec: dec,
			thou: thou
		};
	}

	function formatNumber( value, f ) {
		var parts = Math.max( 0, value ).toFixed( f.decimals ).split( '.' );

		if ( f.thou ) {
			parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, f.thou );
		}

		return parts[ 0 ] + ( parts.length > 1 ? ( f.dec || '.' ) + parts[ 1 ] : '' );
	}

	/**
	 * Curva de Bézier cúbica, a mesma que o CSS entregaria ao `easing`. Sai da
	 * string de util.cssEase para os motores concordarem sobre o que "power2.out"
	 * quer dizer.
	 */
	function bezier( css ) {
		var m = /cubic-bezier\(\s*([-\d.]+)\s*,\s*([-\d.]+)\s*,\s*([-\d.]+)\s*,\s*([-\d.]+)\s*\)/.exec( css );

		if ( ! m ) {
			return function ( t ) { return t; };
		}

		var x1 = +m[ 1 ], y1 = +m[ 2 ], x2 = +m[ 3 ], y2 = +m[ 4 ];

		function axis( a, b, t ) {
			return 3 * a * t * ( 1 - t ) * ( 1 - t ) + 3 * b * t * t * ( 1 - t ) + t * t * t;
		}

		return function ( x ) {
			if ( x <= 0 ) { return 0; }
			if ( x >= 1 ) { return 1; }

			var lo = 0, hi = 1, t = x;

			for ( var i = 0; i < 24; i++ ) {
				t = ( lo + hi ) / 2;

				if ( axis( x1, x2, t ) < x ) { lo = t; } else { hi = t; }
			}

			return axis( y1, y2, t );
		};
	}

	function counterStop( el ) {
		var c = el.__wfanCounter;

		if ( c && c.raf ) {
			cancelAnimationFrame( c.raf );
			c.raf = 0;
		}
	}

	function counterRestore( el ) {
		var c = el.__wfanCounter;

		counterStop( el );

		if ( c && c.node && c.node.nodeValue !== c.original ) {
			c.node.nodeValue = c.original;
		}
	}

	function counterPlay( el, spec ) {
		counterRestore( el );

		var c = el.__wfanCounter;
		var node = c && c.node && c.node.parentNode ? c.node : numberNode( el );

		if ( ! node ) {
			return;
		}

		// O texto de partida é guardado uma vez: depois do primeiro play() o nó
		// pode estar no meio da contagem, e é dele que o reset devolve o final.
		if ( ! c || c.node !== node ) {
			var match = NUMBER.exec( node.nodeValue );
			var fmt = readNumber( match[ 0 ] );

			c = el.__wfanCounter = {
				node: node,
				original: node.nodeValue,
				before: node.nodeValue.slice( 0, match.index ),
				after: node.nodeValue.slice( match.index + match[ 0 ].length ),
				fmt: fmt,
				raf: 0
			};
		}

		var cfg = spec.counter || { from: 0, to: null };
		var to = null === cfg.to ? c.fmt.value : cfg.to;
		var from = cfg.from;

		if ( isNaN( to ) || isNaN( from ) || from === to ) {
			return;
		}

		// Formato da contagem: o do texto; se o final veio do painel, a casa
		// decimal que ele próprio tem.
		var fmt2 = c.fmt;

		if ( null !== cfg.to ) {
			fmt2 = {
				dec: c.fmt.dec || ',',
				thou: c.fmt.thou,
				decimals: ( String( cfg.to ).split( '.' )[ 1 ] || '' ).length
			};
		}

		var ease = bezier( util.cssEase( spec.easing ) );
		var span = Math.max( 100, spec.duration );
		var t0 = null;
		var wait = spec.delay;

		function write( value ) {
			c.node.nodeValue = c.before + formatNumber( value, fmt2 ) + c.after;
		}

		// Primeiro valor já, antes da primeira pintura: o elemento acabou de ser
		// revelado e mostraria o número final por um quadro.
		write( from );

		function frame( now ) {
			if ( null === t0 ) {
				t0 = now + wait;
			}

			var p = ( now - t0 ) / span;

			if ( p >= 1 ) {
				c.node.nodeValue = c.original;
				c.raf = 0;
				return;
			}

			write( p <= 0 ? from : from + ( to - from ) * ease( p ) );
			c.raf = requestAnimationFrame( frame );
		}

		c.raf = requestAnimationFrame( frame );
	}

	window.wfan.register( 'css', {

		play: function ( el, spec, targets ) {
			if ( 'counter' === spec.preset ) {
				counterPlay( el, spec );
				return;
			}

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
			if ( 'counter' === spec.preset ) {
				counterRestore( el );
				return;
			}

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
