/**
 * Orquestrador das animações do WooFlow Animations.
 *
 * Único script sempre presente numa página com animação (~6 KB). Ele lê o
 * contrato data-wfan, resolve o gatilho e entrega ao motor. Os motores se
 * registram aqui; nenhum deles conhece scroll, hover ou clique.
 */
( function () {
	'use strict';

	var cfg = window.wfanConfig || {};
	var PENDING = 'wfan-pending';

	// Liga a inversão de cor sem mouse. O nome tem de bater com o
	// WFAN_Controls::HOVER_PREVIEW_CLASS, que é quem escreve a regra.
	var HOVER_ON = 'wfan-hv-on';

	// Avisa o bootstrap do <head> que o JS chegou: sem isso, ele revela tudo
	// depois do tempo de segurança.
	window.wfanLoaded = true;

	var api = {
		engines: {},
		specs: [],
		register: register,
		refresh: scan,
		util: {}
	};

	function log() {
		if ( cfg.debug && window.console ) {
			window.console.log.apply( window.console, [ '[WooFlow Animations]' ].concat( [].slice.call( arguments ) ) );
		}
	}

	function register( name, engine ) {
		api.engines[ name ] = engine;
		log( 'motor registrado:', name );
	}

	function prefersReduced() {
		if ( ! cfg.reduced ) {
			return false;
		}

		return window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function isSmallScreen() {
		return window.innerWidth <= ( parseInt( cfg.mobileBp, 10 ) || 767 );
	}

	function reveal( el ) {
		el.classList.remove( PENDING );
	}

	function cssEase( name ) {
		var map = cfg.bezier || {};

		return map[ name ] || 'cubic-bezier(0.33, 1, 0.68, 1)';
	}

	/**
	 * Normaliza o JSON cru num objeto com nomes legíveis. Os motores só veem
	 * esta forma — se o contrato mudar de versão, muda só aqui.
	 */
	function normalize( raw ) {
		var trigger = raw.tr || {};
		var stagger = raw.st || null;
		var lottie = raw.lt || null;

		return {
			version: raw.v || 1,
			preset: raw.p || '',
			engine: raw.eng || 'css',
			trigger: trigger.t || 'scroll-in',
			viewport: typeof trigger.vp === 'number' ? trigger.vp : 20,
			once: !! trigger.once,
			end: trigger.end || 'out',
			pin: !! trigger.pin,
			duration: typeof raw.d === 'number' ? raw.d : 800,
			delay: typeof raw.dl === 'number' ? raw.dl : 0,
			easing: raw.e || 'power2.out',
			dist: typeof raw.dist === 'number' ? raw.dist : 40,
			scale: typeof raw.scale === 'number' ? raw.scale : 0.85,
			blur: typeof raw.blur === 'number' ? raw.blur : 10,
			rot: typeof raw.rot === 'number' ? raw.rot : 12,
			to: typeof raw.to === 'number' ? raw.to : 100,
			loop: !! raw.loop,
			split: raw.sp || '',
			cssOnly: !! raw.co,
			offMobile: !! raw.om,
			stagger: stagger ? {
				sel: stagger.sel || '> *',
				each: typeof stagger.each === 'number' ? stagger.each : 80,
				from: stagger.from || 'start',
				kids: !! stagger.kids
			} : null,
			lottie: lottie ? {
				url: lottie.url || '',
				loop: !! lottie.loop,
				speed: lottie.speed || 1
			} : null
		};
	}

	/**
	 * Quebra o texto do elemento em spans, preservando o markup interno
	 * (links, negrito) porque só os nós de texto são tocados.
	 */
	function splitText( el, mode ) {
		if ( el.__wfanSplit ) {
			return el.__wfanSplit;
		}

		var original = el.textContent;
		var walker = document.createTreeWalker( el, NodeFilter.SHOW_TEXT, null, false );
		var nodes = [];
		var node;

		while ( ( node = walker.nextNode() ) ) {
			if ( node.nodeValue && node.nodeValue.trim() ) {
				nodes.push( node );
			}
		}

		var pieces = [];

		nodes.forEach( function ( textNode ) {
			var parts = 'chars' === mode
				? textNode.nodeValue.split( '' )
				: textNode.nodeValue.split( /(\s+)/ );

			var fragment = document.createDocumentFragment();

			parts.forEach( function ( part ) {
				if ( '' === part ) {
					return;
				}

				if ( /^\s+$/.test( part ) ) {
					fragment.appendChild( document.createTextNode( part ) );
					return;
				}

				var span = document.createElement( 'span' );

				span.className = 'wfan-part';
				span.textContent = part;
				fragment.appendChild( span );
				pieces.push( span );
			} );

			textNode.parentNode.replaceChild( fragment, textNode );
		} );

		// O conteúdo picado em spans confunde leitor de tela: o texto inteiro
		// volta pelo aria-label e os pedaços saem da árvore de acessibilidade.
		if ( pieces.length ) {
			el.setAttribute( 'aria-label', original.trim() );

			pieces.forEach( function ( piece ) {
				piece.setAttribute( 'aria-hidden', 'true' );
			} );
		}

		if ( 'lines' === mode ) {
			pieces = groupLines( pieces );
		}

		el.__wfanSplit = pieces;

		return pieces;
	}

	/**
	 * Agrupa as palavras por linha visual, medindo o offsetTop de cada uma.
	 */
	function groupLines( words ) {
		var lines = [];
		var current = null;
		var lastTop = null;

		words.forEach( function ( word ) {
			var top = word.offsetTop;

			if ( null === lastTop || Math.abs( top - lastTop ) > 2 ) {
				current = document.createElement( 'span' );
				current.className = 'wfan-line';
				word.parentNode.insertBefore( current, word );
				lines.push( current );
				lastTop = top;
			}

			current.appendChild( word );
		} );

		return lines;
	}

	function order( list, from ) {
		var copy = [].slice.call( list );

		if ( 'end' === from ) {
			return copy.reverse();
		}

		if ( 'random' === from ) {
			return copy.sort( function () {
				return Math.random() - 0.5;
			} );
		}

		if ( 'center' === from ) {
			var middle = Math.floor( copy.length / 2 );

			return copy.sort( function ( a, b ) {
				return Math.abs( copy.indexOf( a ) - middle ) - Math.abs( copy.indexOf( b ) - middle );
			} );
		}

		return copy;
	}

	/**
	 * O que, de fato, vai animar: o elemento, os filhos escolhidos ou os
	 * pedaços do texto.
	 */
	function targetsFor( el, spec ) {
		if ( spec.split ) {
			var pieces = splitText( el, spec.split );

			if ( pieces.length ) {
				return order( pieces, spec.stagger ? spec.stagger.from : 'start' );
			}
		}

		if ( spec.stagger && spec.stagger.kids ) {
			var sel = spec.stagger.sel || '> *';
			var scoped = sel.replace( /^\s*>\s*/, ':scope > ' );
			var found = [];

			try {
				found = [].slice.call( el.querySelectorAll( scoped ) );
			} catch ( e ) {
				try {
					found = [].slice.call( el.querySelectorAll( sel ) );
				} catch ( e2 ) {
					found = [];
				}
			}

			if ( found.length ) {
				return order( found, spec.stagger.from );
			}
		}

		return [ el ];
	}

	function engineFor( spec ) {
		return api.engines[ spec.engine ] || null;
	}

	function play( entry ) {
		var engine = engineFor( entry.spec );

		if ( ! engine || typeof engine.play !== 'function' ) {
			reveal( entry.el );
			return;
		}

		reveal( entry.el );
		log( 'tocando', entry.spec.preset, entry.el );
		engine.play( entry.el, entry.spec, entry.targets );
	}

	function reset( entry ) {
		var engine = engineFor( entry.spec );

		if ( engine && typeof engine.reset === 'function' ) {
			engine.reset( entry.el, entry.spec, entry.targets );
		}
	}

	function observe( entry ) {
		var spec = entry.spec;

		// Sem IntersectionObserver não existe "entrar na tela": mostrar na hora
		// é a única saída que respeita a regra de nunca esconder conteúdo.
		if ( ! ( 'IntersectionObserver' in window ) ) {
			play( entry );
			return;
		}

		var ratio = Math.max( 0, Math.min( 0.99, spec.viewport / 100 ) );

		// Elemento mais alto que a tela nunca alcança uma razão alta: nesse caso
		// vale qualquer pedaço visível, senão a animação não dispara nunca.
		if ( entry.el.offsetHeight > window.innerHeight * 0.9 ) {
			ratio = 0.01;
		}

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( item ) {
				var isIn = item.isIntersecting && item.intersectionRatio >= ratio - 0.001;
				var wants = 'scroll-out' === spec.trigger ? ! isIn : isIn;

				if ( ! wants ) {
					return;
				}

				if ( 'scroll-out' === spec.trigger && ! entry.seen ) {
					// Ainda não entrou na tela uma vez: sair não conta.
					return;
				}

				play( entry );

				if ( spec.once ) {
					observer.unobserve( item.target );
				}
			} );

			entries.forEach( function ( item ) {
				if ( item.isIntersecting ) {
					entry.seen = true;
				}
			} );
		}, { threshold: [ 0, ratio, 1 ] } );

		observer.observe( entry.el );
		entry.observer = observer;
	}

	function bindTrigger( entry ) {
		var spec = entry.spec;
		var engine = engineFor( entry.spec );

		if ( 'scroll-scrub' === spec.trigger ) {
			if ( engine && typeof engine.bind === 'function' ) {
				reveal( entry.el );
				engine.bind( entry.el, spec, entry.targets );
			} else {
				reveal( entry.el );
			}

			return;
		}

		if ( 'load' === spec.trigger ) {
			window.requestAnimationFrame( function () {
				play( entry );
			} );

			return;
		}

		if ( 'hover' === spec.trigger ) {
			entry.el.addEventListener( 'mouseenter', function () {
				play( entry );
			} );

			return;
		}

		if ( 'click' === spec.trigger ) {
			entry.el.addEventListener( 'click', function () {
				reset( entry );
				play( entry );
			} );

			return;
		}

		observe( entry );
	}

	function setup( el ) {
		if ( el.__wfan ) {
			return;
		}

		var raw = el.getAttribute( 'data-wfan' );

		if ( ! raw ) {
			return;
		}

		var parsed;

		try {
			parsed = JSON.parse( raw );
		} catch ( e ) {
			reveal( el );
			return;
		}

		var spec = normalize( parsed );

		// Efeito de estado: o CSS do elemento já faz tudo. Chega aqui porque o
		// contrato é o mesmo para todo preset, mas não há gatilho a amarrar nem
		// motor a chamar — e o motor nem conheceria este preset. A entrada fica
		// guardada mesmo assim: é dela que o ▶ Testar tira a duração.
		if ( spec.cssOnly ) {
			reveal( el );
			el.__wfan = { el: el, spec: spec, targets: [ el ], seen: false };
			log( 'resolvido em CSS, sem motor:', spec.preset, el );
			return;
		}

		if ( prefersReduced() ) {
			reveal( el );
			log( 'ignorado por preferência de menos movimento', el );
			return;
		}

		if ( ( cfg.offMobile || spec.offMobile ) && isSmallScreen() ) {
			reveal( el );
			log( 'ignorado no celular', el );
			return;
		}

		var entry = {
			el: el,
			spec: spec,
			targets: null,
			seen: false
		};

		entry.targets = targetsFor( el, spec );
		el.__wfan = entry;
		api.specs.push( entry );

		bindTrigger( entry );
	}

	function scan( root ) {
		var scope = root && root.querySelectorAll ? root : document;
		var nodes = scope.querySelectorAll( '.wfan[data-wfan]' );

		[].forEach.call( nodes, setup );

		log( 'elementos encontrados:', nodes.length );
	}

	/**
	 * Mostra o estado invertido por um instante. `:hover` não se simula, então
	 * o ▶ Testar liga a classe que o CSS do elemento também atende e a desliga
	 * depois — o tempo cobre a ida e a volta da transição.
	 */
	function showState( entry ) {
		var el = entry.el;

		el.classList.add( HOVER_ON );
		window.clearTimeout( el.__wfanHold );

		el.__wfanHold = window.setTimeout( function () {
			el.classList.remove( HOVER_ON );
		}, Math.max( 600, ( entry.spec.duration * 2 ) + 400 ) );
	}

	/**
	 * Usado pelo botão "▶ Testar" do painel: o editor chama esta função dentro
	 * do iframe de preview.
	 *
	 * O `spec` vem junto porque no canvas do editor o wrapper é do Backbone do
	 * Elementor e não carrega o `data-wfan` — quem monta o contrato continua
	 * sendo o servidor, que o painel consulta antes de chamar aqui. Sem ele, a
	 * função cai no atributo, que é o caso do front-end.
	 */
	window.wfanPlay = function ( id, spec ) {
		var el = document.querySelector( '.elementor-element[data-id="' + id + '"]' );

		if ( ! el ) {
			return;
		}

		if ( spec ) {
			try {
				el.setAttribute( 'data-wfan', JSON.stringify( spec ) );
			} catch ( e ) {
				return;
			}

			el.classList.add( 'wfan' );

			// O contrato pode ter mudado desde o último clique: a entrada
			// anterior descreve ajustes que não são mais os do painel.
			el.__wfan = null;

			// O editor re-renderiza o conteúdo do widget a cada mudança, então
			// os pedaços da quebra de texto guardados aqui podem ter saído do
			// documento — animá-los não moveria nada na tela. Só nesse caso a
			// quebra é refeita: refazer sempre aninharia span dentro de span.
			if ( el.__wfanSplit && el.__wfanSplit.length && ! document.contains( el.__wfanSplit[ 0 ] ) ) {
				el.__wfanSplit = null;
			}
		}

		if ( ! el.__wfan ) {
			setup( el );
		}

		var entry = el.__wfan;

		if ( ! entry ) {
			return;
		}

		if ( entry.spec.cssOnly ) {
			showState( entry );
			return;
		}

		reset( entry );
		window.requestAnimationFrame( function () {
			play( entry );
		} );
	};

	window.wfan = api;
	api.util = {
		cssEase: cssEase,
		order: order,
		splitText: splitText,
		targetsFor: targetsFor,
		reveal: reveal,
		log: log
	};

	function boot() {
		scan( document );

		// No editor o widget é re-renderizado por Ajax: sem este gancho a
		// animação para de existir depois da primeira edição.
		if ( window.elementorFrontend && elementorFrontend.hooks ) {
			elementorFrontend.hooks.addAction( 'frontend/element_ready/global', function ( $scope ) {
				if ( $scope && $scope[ 0 ] ) {
					setup( $scope[ 0 ] );
					scan( $scope[ 0 ] );
				}
			} );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
