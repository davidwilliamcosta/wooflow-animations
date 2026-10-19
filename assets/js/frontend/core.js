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

	// Elemento entregue à animação de entrada do Elementor. O nome tem de
	// bater com o WFAN_Controls::NATIVE_CLASS, que é o que prende a duração e
	// a curva exatas a este elemento.
	var NATIVE = 'wfan-native';

	// Elementos cujo texto foi agrupado por linha, e que por isso precisam
	// ser reagrupados quando a largura muda.
	var lineWatch = [];

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
		var card = raw.card || null;

		return {
			version: raw.v || 1,
			preset: raw.p || '',
			engine: raw.eng || 'css',
			trigger: trigger.t || 'scroll-in',
			viewport: typeof trigger.vp === 'number' ? trigger.vp : 20,
			once: !! trigger.once,
			start: trigger.start || 'enter',
			end: trigger.end || 'out',
			pct: typeof trigger.pct === 'number' ? trigger.pct : 100,
			pin: !! trigger.pin,
			duration: typeof raw.d === 'number' ? raw.d : 800,
			delay: typeof raw.dl === 'number' ? raw.dl : 0,
			easing: raw.e || 'power2.out',
			dist: typeof raw.dist === 'number' ? raw.dist : 40,
			scale: typeof raw.scale === 'number' ? raw.scale : 0.85,
			blur: typeof raw.blur === 'number' ? raw.blur : 10,
			dim: typeof raw.dim === 'number' ? raw.dim : 0.2,
			line: typeof raw.line === 'number' ? raw.line : 0,
			speed: typeof raw.speed === 'number' && raw.speed > 0 ? raw.speed : 65,
			rot: typeof raw.rot === 'number' ? raw.rot : 12,
			to: typeof raw.to === 'number' ? raw.to : 100,
			loop: !! raw.loop,
			split: raw.sp || '',
			native: raw.nat || '',
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
			} : null,
			card: card ? {
				state: 'in' === card.st ? 'in' : 'out',
				scale: typeof card.sc === 'number' ? card.sc : 0.9,
				radius: typeof card.r === 'number' ? card.r : 54,
				origin: card.o || 'bottom center',
				length: typeof card.len === 'number' ? card.len : 150
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
		var words = [];

		nodes.forEach( function ( textNode ) {
			var fragment = document.createDocumentFragment();

			textNode.nodeValue.split( /(\s+)/ ).forEach( function ( part ) {
				if ( '' === part ) {
					return;
				}

				if ( /^\s+$/.test( part ) ) {
					fragment.appendChild( document.createTextNode( part ) );
					return;
				}

				/*
				 * O texto é sempre picado em palavras primeiro, mesmo quando
				 * o alvo é a letra. O invólucro de palavra faz duas coisas
				 * que nada mais faz: é o que o agrupamento por linha mede, e
				 * é o `white-space:nowrap` que segura as letras de uma
				 * palavra na mesma linha — sem ele, um texto picado em letras
				 * quebra onde o navegador quiser, no meio da palavra.
				 */
				var word = document.createElement( 'span' );

				fragment.appendChild( word );
				words.push( word );

				if ( 'chars' !== mode ) {
					word.className = 'wfan-word wfan-part';
					word.textContent = part;
					pieces.push( word );
					return;
				}

				word.className = 'wfan-word';

				part.split( '' ).forEach( function ( character ) {
					var span = document.createElement( 'span' );

					span.className = 'wfan-part';
					span.textContent = character;
					word.appendChild( span );
					pieces.push( span );
				} );
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

		// As palavras ficam guardadas mesmo quando não são o alvo: é a
		// partir delas que o agrupamento por linha é refeito depois, sem
		// repicar o texto.
		el.__wfanWords = words;

		if ( 'lines' === mode ) {
			pieces = groupLines( words );
			watchLines( el );
		}

		el.__wfanSplit = pieces;

		return pieces;
	}

	/**
	 * Agrupa as palavras por linha visual.
	 *
	 * Todas as medidas saem ANTES de qualquer palavra mudar de lugar. O
	 * invólucro de linha é `display:block`, então fechar a primeira linha
	 * reflui tudo o que vem depois: medindo enquanto move, o agrupamento
	 * saía errado a partir da segunda linha.
	 */
	function groupLines( words ) {
		var tops = words.map( function ( word ) {
			return word.offsetTop;
		} );
		var lines = [];
		var current = null;
		var lastTop = null;

		words.forEach( function ( word, index ) {
			if ( null === lastTop || Math.abs( tops[ index ] - lastTop ) > 2 ) {
				current = document.createElement( 'span' );
				current.className = 'wfan-line';
				word.parentNode.insertBefore( current, word );
				lines.push( current );
				lastTop = tops[ index ];
			}

			current.appendChild( word );
		} );

		return lines;
	}

	/**
	 * Desfaz os invólucros de linha — e o da cortina, que vive dentro
	 * deles —, devolvendo as palavras ao fluxo natural, que é o único
	 * estado em que medir uma linha faz sentido.
	 */
	function unwrapLines( el ) {
		[ 'wfan-mask-inner', 'wfan-line' ].forEach( function ( className ) {
			[].slice.call( el.querySelectorAll( '.' + className ) ).forEach( function ( wrap ) {
				var parent = wrap.parentNode;

				if ( ! parent ) {
					return;
				}

				while ( wrap.firstChild ) {
					parent.insertBefore( wrap.firstChild, wrap );
				}

				parent.removeChild( wrap );
			} );
		} );
	}

	/**
	 * Refaz o agrupamento por linha. As palavras são as mesmas: só trocam de
	 * invólucro. Repicar o texto aqui aninharia span dentro de span.
	 */
	function regroupLines( el ) {
		var words = el.__wfanWords;

		if ( ! words || ! words.length || ! document.contains( words[ 0 ] ) ) {
			return false;
		}

		unwrapLines( el );

		el.__wfanSplit = groupLines( words );

		// A entrada guarda os alvos resolvidos uma vez só, no setup: sem
		// isto a próxima reprodução animaria linhas que já saíram do
		// documento, e nada se moveria na tela.
		if ( el.__wfan ) {
			el.__wfan.targets = el.__wfanSplit;
		}

		return true;
	}

	function watchLines( el ) {
		if ( -1 === lineWatch.indexOf( el ) ) {
			el.__wfanWidth = el.clientWidth;
			lineWatch.push( el );
		}
	}

	/**
	 * Linha agrupada na largura de ontem continua agrupada errada depois de
	 * girar o celular, de redimensionar a janela ou de a webfont chegar — e a
	 * webfont chega depois da primeira medida com frequência, porque o texto
	 * é pintado antes com a fonte de reserva.
	 *
	 * @param {boolean} force Reagrupa mesmo com a largura igual (webfont).
	 */
	function refreshLines( force ) {
		lineWatch = lineWatch.filter( function ( el ) {
			return document.contains( el );
		} );

		lineWatch.forEach( function ( el ) {
			var width = el.clientWidth;

			if ( ! force && el.__wfanWidth === width ) {
				return;
			}

			el.__wfanWidth = width;

			if ( regroupLines( el ) ) {
				log( 'linhas reagrupadas', el );
			}
		} );
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

		entry.inside = false;

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( item ) {
				var isIn = item.isIntersecting && item.intersectionRatio >= ratio - 0.001;

				// Só a MUDANÇA de estado conta — entrou agora, saiu agora.
				//
				// Sem isto, qualquer cruzamento do limiar reproduzia de novo, e
				// a própria animação é um deles: um zoom de 0.85 ou um deslize
				// mudam a caixa do elemento, a razão de visibilidade oscila em
				// torno do limiar e a animação se realimenta — o efeito repetia
				// sem parar, com a página até parada. Com scroll suave (Lenis)
				// é pior ainda, porque o scroll chega em frações de pixel.
				if ( isIn === entry.inside ) {
					return;
				}

				entry.inside = isIn;

				if ( isIn ) {
					entry.seen = true;
				}

				var wants = 'scroll-out' === spec.trigger ? ! isIn : isIn;

				if ( ! wants ) {
					return;
				}

				if ( 'scroll-out' === spec.trigger && ! entry.seen ) {
					// Ainda não entrou na tela uma vez: sair não conta.
					return;
				}

				// Enquanto a animação roda, o elemento se mexe: uma saída e um
				// retorno dentro dessa janela são efeito dela, não do usuário.
				if ( entry.busy ) {
					return;
				}

				hold( entry );
				play( entry );

				if ( spec.once ) {
					observer.unobserve( item.target );
					entry.observer = null;
				}
			} );
		}, { threshold: [ 0, ratio, 1 ] } );

		observer.observe( entry.el );
		entry.observer = observer;
	}

	/**
	 * Segura novos disparos pelo tempo que esta reprodução leva: espera, mais
	 * duração, mais a cascata, mais uma folga para o último quadro.
	 */
	function hold( entry ) {
		var spec = entry.spec;
		var pieces = entry.targets ? entry.targets.length : 1;
		var cascade = spec.stagger && pieces > 1 ? spec.stagger.each * ( pieces - 1 ) : 0;

		entry.busy = true;
		window.clearTimeout( entry.busyTimer );

		entry.busyTimer = window.setTimeout( function () {
			entry.busy = false;
		}, spec.delay + spec.duration + cascade + 120 );
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

		// Animação do próprio Elementor: no front-end nem chega aqui (o
		// elemento sai sem data-wfan e sem este arquivo). No editor chega,
		// porque é o ▶ Testar quem monta o atributo — e aí quem toca é o
		// playNative, já que o canvas não tem a animação nativa amarrada.
		if ( spec.native ) {
			reveal( el );
			el.__wfan = { el: el, spec: spec, targets: [ el ], seen: false };
			log( 'entregue à animação nativa do Elementor:', spec.native, el );
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
	 * Reproduz a animação nativa do Elementor no canvas do editor.
	 *
	 * Faz o mesmo que o `GlobalHandler` do Elementor faz no front-end — tira o
	 * `elementor-invisible`, espera o delay e põe `animated <nome>` —, porque
	 * no canvas o wrapper é reconstruído pelo Backbone e não tem a animação
	 * nativa amarrada a ele (regra 19). O nome vem do servidor; o teste de
	 * formato está aqui só para que um catálogo adulterado por filtro não vire
	 * uma exceção do classList.
	 */
	function playNative( entry ) {
		var el = entry.el;
		var name = entry.spec.native;

		if ( ! /^[A-Za-z][A-Za-z0-9_-]*$/.test( name ) ) {
			return;
		}

		window.clearTimeout( el.__wfanNative );

		el.classList.add( NATIVE );
		el.classList.remove( 'animated' );
		el.classList.remove( name );

		// Trocar de animação sem tirar a classe anterior deixaria as duas
		// regras valendo, e quem ganha é a que vier depois na folha do
		// Elementor — não a que acabou de ser escolhida.
		if ( el.__wfanNativeName && el.__wfanNativeName !== name ) {
			el.classList.remove( el.__wfanNativeName );
		}

		el.__wfanNativeName = name;

		// Sem ler uma propriedade de layout aqui, o navegador agrupa a saída e
		// a entrada da classe no mesmo quadro e a animação não recomeça.
		void el.offsetWidth;

		el.__wfanNative = window.setTimeout( function () {
			el.classList.remove( 'elementor-invisible' );
			el.classList.add( 'animated' );
			el.classList.add( name );
		}, Math.max( 0, entry.spec.delay ) );
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

		if ( entry.spec.native ) {
			playNative( entry );
			return;
		}

		reset( entry );
		window.requestAnimationFrame( function () {
			play( entry );

			// Scroll travado: o `play` é só a amostra, e ele desamarra o
			// gatilho para poder tocar. Sem religar depois, testar uma vez
			// deixava o elemento morto no scroll até a próxima edição.
			if ( 'scroll-scrub' === entry.spec.trigger ) {
				window.clearTimeout( el.__wfanRebind );

				el.__wfanRebind = window.setTimeout( function () {
					reset( entry );
					bindTrigger( entry );
				}, entry.spec.duration + entry.spec.delay + 200 );
			}
		} );
	};

	window.wfan = api;
	api.util = {
		cssEase: cssEase,
		order: order,
		splitText: splitText,
		groupLines: groupLines,
		regroupLines: regroupLines,
		refreshLines: refreshLines,
		targetsFor: targetsFor,
		reveal: reveal,
		log: log
	};

	function boot() {
		scan( document );

		var lineTimer = 0;

		window.addEventListener( 'resize', function () {
			window.clearTimeout( lineTimer );
			lineTimer = window.setTimeout( refreshLines, 150 );
		}, { passive: true } );

		// A webfont costuma chegar depois da primeira medida: o texto é
		// pintado antes com a fonte de reserva, que tem outra largura.
		if ( document.fonts && document.fonts.ready && document.fonts.ready.then ) {
			document.fonts.ready.then( function () {
				refreshLines( true );
			} );
		}

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
