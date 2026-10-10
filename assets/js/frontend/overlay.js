/**
 * Fundo animado por elemento — o canvas de ruído da seção "WooFlow — Fundo
 * animado". Não depende do core.js nem de biblioteca nenhuma.
 *
 * O contrato inteiro chega em custom properties que o Elementor escreve a
 * partir dos `selectors` dos controles (ver WFAN_Overlay). Nada vem em
 * atributo: no canvas do editor o wrapper é reconstruído pelo Backbone a cada
 * mexida e perderia qualquer atributo do PHP — o CSS por elemento, não.
 *
 * As duas cores são a exceção que confirma a regra: custom property lida
 * direto devolve o texto cru, e com uma cor global do Elementor isso é
 * literalmente "var(--e-global-color-x)". Por isso o frontend.css as aplica a
 * `color` e `outline-color` do canvas, onde o navegador resolve de verdade.
 */
( function () {
	'use strict';

	var cfg = window.wfanOverlayConfig || {};

	var VARS = cfg.vars || {
		on: '--wfan-ovl',
		speed: '--wfan-ovl-sp',
		size: '--wfan-ovl-sz'
	};

	var HOST_CLASS = cfg.hostClass || 'wfan-ovl';
	var CANVAS_CLASS = cfg.canvas || 'wfan-ovl-canvas';

	// Resolução do canvas, que o CSS estica para o tamanho do elemento. É baixa
	// de propósito: o upscale borrado é de graça e o custo por quadro é
	// res² chamadas de ruído. 110² a 30 fps já são 363 mil por segundo.
	var RES = 110;
	var RES_SMALL = 90;
	var FRAME_MS = 1000 / 30;

	// Grau por segundo com que o eixo da mistura das duas cores gira. É
	// independente da velocidade escolhida, como no original.
	var SPIN = 60;

	var DEFAULT_SPEED = 0.25;
	var DEFAULT_SIZE = 0.7;
	var DEFAULT_C1 = [ 17, 92, 250 ];
	var DEFAULT_C2 = [ 100, 66, 255 ];

	function log() {
		if ( cfg.debug && window.console ) {
			window.console.log.apply( window.console, [ '[WooFlow Animations/fundo]' ].concat( [].slice.call( arguments ) ) );
		}
	}

	// ------------------------------------------------------------ ruído simplex

	/*
	 * Simplex 3D clássico (Gustavson). A permutação é sorteada por um gerador
	 * com semente fixa em vez de Math.random: o mesmo campo de ruído em toda
	 * carga, para o fundo não mudar de desenho a cada F5.
	 */
	var GRAD = [
		1, 1, 0, -1, 1, 0, 1, -1, 0, -1, -1, 0,
		1, 0, 1, -1, 0, 1, 1, 0, -1, -1, 0, -1,
		0, 1, 1, 0, -1, 1, 0, 1, -1, 0, -1, -1
	];

	var perm = new Uint8Array( 512 );
	var permMod12 = new Uint8Array( 512 );

	( function buildPermutation() {
		var p = new Uint8Array( 256 );
		var seed = 1337;
		var i;
		var j;
		var swap;

		for ( i = 0; i < 256; i++ ) {
			p[ i ] = i;
		}

		for ( i = 255; i > 0; i-- ) {
			seed = ( seed * 16807 ) % 2147483647;
			j = seed % ( i + 1 );
			swap = p[ i ];
			p[ i ] = p[ j ];
			p[ j ] = swap;
		}

		for ( i = 0; i < 512; i++ ) {
			perm[ i ] = p[ i & 255 ];
			permMod12[ i ] = perm[ i ] % 12;
		}
	}() );

	var F3 = 1 / 3;
	var G3 = 1 / 6;

	function noise3D( xin, yin, zin ) {
		var s = ( xin + yin + zin ) * F3;
		var i = Math.floor( xin + s );
		var j = Math.floor( yin + s );
		var k = Math.floor( zin + s );
		var t = ( i + j + k ) * G3;
		var x0 = xin - ( i - t );
		var y0 = yin - ( j - t );
		var z0 = zin - ( k - t );
		var i1;
		var j1;
		var k1;
		var i2;
		var j2;
		var k2;

		if ( x0 >= y0 ) {
			if ( y0 >= z0 ) {
				i1 = 1; j1 = 0; k1 = 0; i2 = 1; j2 = 1; k2 = 0;
			} else if ( x0 >= z0 ) {
				i1 = 1; j1 = 0; k1 = 0; i2 = 1; j2 = 0; k2 = 1;
			} else {
				i1 = 0; j1 = 0; k1 = 1; i2 = 1; j2 = 0; k2 = 1;
			}
		} else if ( y0 < z0 ) {
			i1 = 0; j1 = 0; k1 = 1; i2 = 0; j2 = 1; k2 = 1;
		} else if ( x0 < z0 ) {
			i1 = 0; j1 = 1; k1 = 0; i2 = 0; j2 = 1; k2 = 1;
		} else {
			i1 = 0; j1 = 1; k1 = 0; i2 = 1; j2 = 1; k2 = 0;
		}

		var x1 = x0 - i1 + G3;
		var y1 = y0 - j1 + G3;
		var z1 = z0 - k1 + G3;
		var x2 = x0 - i2 + ( 2 * G3 );
		var y2 = y0 - j2 + ( 2 * G3 );
		var z2 = z0 - k2 + ( 2 * G3 );
		var x3 = x0 - 1 + ( 3 * G3 );
		var y3 = y0 - 1 + ( 3 * G3 );
		var z3 = z0 - 1 + ( 3 * G3 );

		var ii = i & 255;
		var jj = j & 255;
		var kk = k & 255;

		var n = 0;
		var g;
		var t0 = 0.6 - ( x0 * x0 ) - ( y0 * y0 ) - ( z0 * z0 );
		var t1 = 0.6 - ( x1 * x1 ) - ( y1 * y1 ) - ( z1 * z1 );
		var t2 = 0.6 - ( x2 * x2 ) - ( y2 * y2 ) - ( z2 * z2 );
		var t3 = 0.6 - ( x3 * x3 ) - ( y3 * y3 ) - ( z3 * z3 );

		if ( t0 > 0 ) {
			g = permMod12[ ii + perm[ jj + perm[ kk ] ] ] * 3;
			t0 *= t0;
			n += t0 * t0 * ( ( GRAD[ g ] * x0 ) + ( GRAD[ g + 1 ] * y0 ) + ( GRAD[ g + 2 ] * z0 ) );
		}

		if ( t1 > 0 ) {
			g = permMod12[ ii + i1 + perm[ jj + j1 + perm[ kk + k1 ] ] ] * 3;
			t1 *= t1;
			n += t1 * t1 * ( ( GRAD[ g ] * x1 ) + ( GRAD[ g + 1 ] * y1 ) + ( GRAD[ g + 2 ] * z1 ) );
		}

		if ( t2 > 0 ) {
			g = permMod12[ ii + i2 + perm[ jj + j2 + perm[ kk + k2 ] ] ] * 3;
			t2 *= t2;
			n += t2 * t2 * ( ( GRAD[ g ] * x2 ) + ( GRAD[ g + 1 ] * y2 ) + ( GRAD[ g + 2 ] * z2 ) );
		}

		if ( t3 > 0 ) {
			g = permMod12[ ii + 1 + perm[ jj + 1 + perm[ kk + 1 ] ] ] * 3;
			t3 *= t3;
			n += t3 * t3 * ( ( GRAD[ g ] * x3 ) + ( GRAD[ g + 1 ] * y3 ) + ( GRAD[ g + 2 ] * z3 ) );
		}

		return 32 * n;
	}

	// ------------------------------------------------------------------ leitura

	function number( raw, fallback ) {
		var value = parseFloat( String( raw ).trim() );

		return isFinite( value ) ? value : fallback;
	}

	/**
	 * `rgb(17, 92, 250)` ou `rgba(17, 92, 250, 0.5)` — é sempre essa a forma
	 * computada de uma propriedade de cor, mesmo quando o valor escrito era um
	 * hex ou uma cor global do Elementor.
	 */
	function rgb( computed, fallback ) {
		var parts = String( computed ).match( /-?\d+(\.\d+)?/g );

		if ( ! parts || parts.length < 3 ) {
			return fallback;
		}

		return [ +parts[ 0 ], +parts[ 1 ], +parts[ 2 ] ];
	}

	function isSmallScreen() {
		return window.innerWidth <= ( parseInt( cfg.mobileBp, 10 ) || 767 );
	}

	function prefersReduced() {
		if ( false === cfg.reduced ) {
			return false;
		}

		return !! ( window.matchMedia && window.matchMedia( '( prefers-reduced-motion: reduce )' ).matches );
	}

	function isOn( el ) {
		return '1' === window.getComputedStyle( el ).getPropertyValue( VARS.on ).trim();
	}

	// ------------------------------------------------------------------ instância

	function Overlay( el ) {
		this.el = el;
		this.res = isSmallScreen() ? RES_SMALL : RES;
		this.visible = true;
		this.frozen = prefersReduced();
		this.start = 0;
		this.ratio = { x: 1.4, y: 1.4 };

		this.canvas = document.createElement( 'canvas' );
		this.canvas.className = CANVAS_CLASS;
		this.canvas.width = this.res;
		this.canvas.height = this.res;
		this.canvas.setAttribute( 'aria-hidden', 'true' );

		this.ctx = this.canvas.getContext && this.canvas.getContext( '2d' );

		// Sem contexto 2D não há o que desenhar. A instância nasce inerte e
		// quem a criou a descarta.
		if ( ! this.ctx ) {
			return;
		}

		this.image = this.ctx.createImageData( this.res, this.res );
		this.data = this.image.data;

		this.attach();
		this.read();
		this.measure();
		this.watch();

		instances.push( this );
		run();

		log( 'montado', el );
	}

	/**
	 * O canvas entra como primeiro filho e sobe pela pilha de pintura com
	 * `z-index:-1` dentro de um contexto isolado: acima do fundo do elemento,
	 * abaixo de todo o conteúdo. O `isolation` vai inline porque a classe do
	 * wrapper não sobrevive ao re-render do editor — a custom property, sim.
	 */
	Overlay.prototype.attach = function () {
		var el = this.el;

		if ( 'static' === window.getComputedStyle( el ).position ) {
			el.style.position = 'relative';
			this.positioned = true;
		}

		el.style.isolation = 'isolate';
		el.insertBefore( this.canvas, el.firstChild );
	};

	Overlay.prototype.read = function () {
		var host = window.getComputedStyle( this.el );
		var own = window.getComputedStyle( this.canvas );

		this.speed = number( host.getPropertyValue( VARS.speed ), DEFAULT_SPEED );
		this.size = number( host.getPropertyValue( VARS.size ), DEFAULT_SIZE );
		this.c1 = rgb( own.color, DEFAULT_C1 );
		this.c2 = rgb( own.outlineColor, DEFAULT_C2 );
		this.single = this.c1[ 0 ] === this.c2[ 0 ] && this.c1[ 1 ] === this.c2[ 1 ] && this.c1[ 2 ] === this.c2[ 2 ];
	};

	/**
	 * Corrige a proporção para a mancha não achatar junto com o canvas, que é
	 * sempre quadrado e esticado para o retângulo do elemento.
	 */
	Overlay.prototype.measure = function () {
		var w = this.el.clientWidth;
		var h = this.el.clientHeight;

		if ( ! w || ! h ) {
			return;
		}

		var proportion = h / w;

		this.ratio = proportion < 1
			? { x: 1.4, y: proportion * 1.4 }
			: { x: proportion / 3, y: 1 };
	};

	Overlay.prototype.watch = function () {
		var self = this;

		if ( ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		// Fora da tela não se desenha: é o que impede uma página com vários
		// fundos de gastar CPU com o que ninguém está vendo.
		this.observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				self.visible = entry.isIntersecting;
			} );
		} );

		this.observer.observe( this.el );
	};

	Overlay.prototype.draw = function ( now ) {
		if ( ! this.visible || ( this.frozen && this.start ) ) {
			return;
		}

		if ( ! this.start ) {
			this.start = now;
		}

		var seconds = ( now - this.start ) / 1000;
		var z = seconds * this.speed;
		var radians = ( Math.PI / 180 ) * seconds * SPIN;
		var cos = Math.cos( radians );
		var sin = Math.sin( radians );

		var res = this.res;
		var data = this.data;
		var size = this.size;
		var rx = this.ratio.x * size / res;
		var ry = this.ratio.y * size / res;
		var half = res / 2;
		var single = this.single;
		var c1 = this.c1;
		var c2 = this.c2;
		var dr = c2[ 0 ] - c1[ 0 ];
		var dg = c2[ 1 ] - c1[ 1 ];
		var db = c2[ 2 ] - c1[ 2 ];

		// 1,75 / res: o mesmo fator do original, onde saía de
		// (100/res/100) * 3.5 / 2.
		var spread = 1.75 / res;

		var x;
		var y;
		var n;
		var mix;
		var offset;

		for ( y = 0; y < res; y++ ) {
			for ( x = 0; x < res; x++ ) {
				n = noise3D( x * rx, y * ry, z );
				offset = ( x + ( y * res ) ) * 4;

				if ( single ) {
					data[ offset ] = c1[ 0 ];
					data[ offset + 1 ] = c1[ 1 ];
					data[ offset + 2 ] = c1[ 2 ];
				} else {
					mix = ( ( cos * ( x - half ) ) + ( sin * ( y - half ) ) + half ) * spread * n;
					data[ offset ] = c1[ 0 ] + ( dr * mix );
					data[ offset + 1 ] = c1[ 1 ] + ( dg * mix );
					data[ offset + 2 ] = c1[ 2 ] + ( db * mix );
				}

				// O alfa sai do mesmo ruído: é ele que faz as manchas
				// respirarem em vez de o quadro inteiro trocar de cor.
				data[ offset + 3 ] = n * 265;
			}
		}

		this.ctx.putImageData( this.image, 0, 0 );
	};

	/**
	 * Chamado depois de cada re-render do editor: o conteúdo do elemento é
	 * substituído e leva o canvas junto, mas a instância continua pendurada no
	 * nó, que o Backbone reaproveita.
	 */
	Overlay.prototype.refresh = function () {
		if ( ! this.el.contains( this.canvas ) ) {
			this.attach();
		}

		this.read();
		this.measure();
	};

	Overlay.prototype.destroy = function () {
		var at = instances.indexOf( this );

		if ( at > -1 ) {
			instances.splice( at, 1 );
		}

		if ( this.observer ) {
			this.observer.disconnect();
		}

		if ( this.canvas.parentNode ) {
			this.canvas.parentNode.removeChild( this.canvas );
		}

		this.el.style.isolation = '';

		if ( this.positioned ) {
			this.el.style.position = '';
		}

		this.el.__wfanOverlay = null;

		log( 'desmontado', this.el );
	};

	// ---------------------------------------------------------------- laço único

	var instances = [];
	var running = false;
	var lastFrame = 0;
	var lastRead = 0;

	function tick( now ) {
		if ( ! instances.length ) {
			running = false;
			return;
		}

		window.requestAnimationFrame( tick );

		if ( now - lastFrame < FRAME_MS ) {
			return;
		}

		lastFrame = now;

		// No editor, mudar uma cor ou a velocidade reescreve o CSS do elemento
		// sem re-renderizar nada: sem reler, o fundo ficaria com o valor antigo
		// até a próxima edição que force um re-render.
		var reread = cfg.editor && now - lastRead > 400;

		if ( reread ) {
			lastRead = now;
		}

		for ( var i = 0; i < instances.length; i++ ) {
			if ( reread ) {
				instances[ i ].read();
			}

			instances[ i ].draw( now );
		}
	}

	function run() {
		if ( running ) {
			return;
		}

		running = true;
		window.requestAnimationFrame( tick );
	}

	// -------------------------------------------------------------------- boot

	function blocked() {
		return !! cfg.offMobile && isSmallScreen();
	}

	/**
	 * Decide pelo que está no CSS, nunca pela classe: no editor a classe some
	 * no primeiro re-render e a custom property fica.
	 */
	function sync( el ) {
		if ( ! el || 1 !== el.nodeType ) {
			return;
		}

		var current = el.__wfanOverlay;

		if ( ! isOn( el ) ) {
			if ( current ) {
				current.destroy();
			}

			return;
		}

		if ( current ) {
			current.refresh();
			return;
		}

		if ( blocked() ) {
			return;
		}

		var fresh = new Overlay( el );

		// Sem contexto 2D não há o que desenhar, e uma instância sem contexto
		// só gastaria um quadro por vez tentando.
		if ( ! fresh.ctx ) {
			fresh.destroy();
			return;
		}

		el.__wfanOverlay = fresh;
	}

	function scan( root ) {
		var scope = root && root.querySelectorAll ? root : document;

		[].forEach.call( scope.querySelectorAll( '.' + HOST_CLASS ), sync );
	}

	var resizing;

	function onResize() {
		window.clearTimeout( resizing );

		resizing = window.setTimeout( function () {
			instances.forEach( function ( instance ) {
				instance.measure();
			} );
		}, 150 );
	}

	function boot() {
		scan( document );

		window.addEventListener( 'resize', onResize );

		// O mesmo gancho que o core.js usa: no editor, cada edição re-renderiza
		// o elemento e destrói o canvas junto com o conteúdo.
		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function ( $scope ) {
				if ( $scope && $scope[ 0 ] ) {
					sync( $scope[ 0 ] );
					scan( $scope[ 0 ] );
				}
			} );
		}
	}

	window.wfanOverlay = {
		sync: sync,
		refresh: scan,
		instances: instances
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
