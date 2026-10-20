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

	// Quanto do texto fica em degradê entre o aceso e o apagado, na revelação
	// travada no scroll. Ver o uso, em bind().
	var SOFT_EDGE = 0.25;

	// A velocidade em que o percurso vale a altura do elemento.
	var REFERENCE_SPEED = 65;

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

	/**
	 * A linha da tela em que a revelação acontece, escrita como âncora do
	 * ScrollTrigger. `spec.line` é medido do topo (80 = oito décimos abaixo),
	 * e o ScrollTrigger mede do fundo — daí a subtração.
	 */
	function lineAnchor( spec ) {
		return 'bottom-=' + Math.min( 90, Math.max( 0, 100 - spec.line ) ) + '%';
	}

	/**
	 * Fim do percurso da revelação: uma distância a partir do início, e não
	 * uma segunda âncora. Em `speed` 65 vale exatamente a altura do elemento —
	 * o mesmo que `bottom <linha>` — e daí a velocidade só escala essa conta:
	 * mais rápido, menos rolagem; mais devagar, mais. É função, e não número,
	 * porque a altura muda com a largura da tela e o ScrollTrigger refaz a
	 * conta a cada refresh (`invalidateOnRefresh`).
	 */
	function lineSpan( spec ) {
		var factor = REFERENCE_SPEED / Math.max( 10, spec.speed || REFERENCE_SPEED );

		return function ( self ) {
			var height = self && self.trigger ? self.trigger.offsetHeight : 0;

			return '+=' + Math.max( 40, Math.round( height * factor ) );
		};
	}

	/**
	 * Ponto em que a linha do tempo começa a obedecer ao scroll.
	 *
	 * Com `spec.line`, o par de âncoras usa a MESMA altura de tela nas duas
	 * pontas: `top <linha>` até `bottom <linha>`. Só assim a frente de luz
	 * fica parada na tela enquanto o texto passa por ela, que é o efeito da
	 * referência — e o percurso passa a valer exatamente a altura do
	 * elemento, nem mais nem menos. Com o par genérico ("entrar na tela" até
	 * "o topo chegar ao topo") o texto terminava de acender enquanto o bloco
	 * ainda subia, antes de dar para ler.
	 */
	function startFor( spec ) {
		if ( spec.line ) {
			return 'top ' + lineAnchor( spec );
		}

		if ( 'center' === spec.start ) {
			return 'center center';
		}

		if ( 'top' === spec.start ) {
			return 'top top';
		}

		if ( 'quarter' === spec.start ) {
			return 'top bottom-=25%';
		}

		return 'top bottom';
	}

	/**
	 * Ponto em que ela termina. O `+=N%` do ScrollTrigger é uma distância a
	 * partir do início, medida em porcentagem da altura da tela — é com ele
	 * que "terminar depois de rolar uma tela" vira uma conta só.
	 */
	function endFor( spec ) {
		if ( spec.line ) {
			return lineSpan( spec );
		}

		var end = 'bottom top';

		if ( 'pct' === spec.end ) {
			end = '+=' + Math.max( 10, spec.pct ) + '%';
		} else if ( 'center' === spec.end ) {
			end = 'center center';
		} else if ( 'top' === spec.end ) {
			end = 'top top';
		}

		/*
		 * Fim igual ao início é percurso de comprimento zero: o ScrollTrigger
		 * empurra o fim um pixel adiante e a animação inteira acontece nesse
		 * pixel — na tela, tudo de uma vez, sem rolagem nenhuma. O painel
		 * deixa escolher as duas pontas livremente, e dois pares batem
		 * ("chegar ao centro" nas duas, "o topo chegar ao topo" nas duas).
		 */
		return end === startFor( spec ) ? 'bottom top' : end;
	}

	/**
	 * Percurso do "Virar cartão no scroll", o da animação de linha do Uncode:
	 * ao sair, começa quando o topo do bloco encosta no topo da tela; ao
	 * entrar, quando ele aparece no pé dela. O fim é uma fração da altura da
	 * TELA (`+=N%`), não uma âncora do elemento — numa seção de três telas de
	 * altura, "até sair" esticaria o efeito por três telas de rolagem.
	 */
	function cardTrigger( spec ) {
		return {
			start: 'in' === spec.card.state ? 'top bottom' : 'top top',
			end: '+=' + Math.max( 20, spec.card.length ) + '%'
		};
	}

	/**
	 * Os cantos que o elemento já tem, como ponto de partida do cartão: um
	 * container com 12px de raio não pode perder o raio quando o efeito
	 * começa. Um valor por canto, nos quatro, para o GSAP interpolar número
	 * com número até o raio do cartão.
	 */
	function cornersOf( el ) {
		if ( ! window.getComputedStyle ) {
			return '0px 0px 0px 0px';
		}

		var style = window.getComputedStyle( el );

		return [ 'TopLeft', 'TopRight', 'BottomRight', 'BottomLeft' ].map( function ( corner ) {
			return ( parseFloat( style[ 'border' + corner + 'Radius' ] ) || 0 ) + 'px';
		} ).join( ' ' );
	}

	/**
	 * Os dois estados do cartão. O raio vai em `border-radius` E em
	 * `--border-radius`: o container do Elementor arredonda o próprio fundo
	 * por `border-radius: var(--border-radius)`, mas a sobreposição, o vídeo e
	 * o slideshow de fundo são filhos que leem só a variável. Sem ela os
	 * cantos do fundo arredondavam e a sobreposição continuava quadrada por
	 * cima — e isso sem `overflow:hidden`, que cortaria submenu e sombra.
	 */
	function cardStates( el, spec ) {
		var rest = cornersOf( el );
		var round = [ 0, 1, 2, 3 ].map( function () {
			return spec.card.radius + 'px';
		} ).join( ' ' );

		var open = { scale: 1, borderRadius: rest, '--border-radius': rest };
		var card = { scale: spec.card.scale, borderRadius: round, '--border-radius': round };

		return 'in' === spec.card.state ? { from: card, to: open } : { from: open, to: card };
	}

	// O que o cartão escreve no elemento. `clearProps: 'all'` apagaria também
	// o `isolation` que o fundo animado põe inline (regra 20).
	var CARD_PROPS = 'transform,transformOrigin,borderRadius,--border-radius';

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

			// A amostra do ▶ Testar parte da mesma opacidade do efeito: o
			// contrário mostrava um texto nascendo do nada, que não é o que
			// a rolagem faz — ela acende o que já está lá, apagado.
			case 'text-scrub-words':
			case 'text-scrub-chars': return { opacity: spec.dim };

			// A amostra do ▶ Testar mostra o cartão se abrindo, nos dois
			// estados: é o mesmo movimento, e o de saída tocado ao contrário
			// deixaria o elemento encolhido no fim da amostra.
			case 'scrub-card': return spec.card ? {
				scale: spec.card.scale,
				borderRadius: spec.card.radius + 'px',
				'--border-radius': spec.card.radius + 'px'
			} : { scale: 0.9 };

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

	/**
	 * Guarda o ScrollTrigger criado para este elemento. O GSAP não desfaz isso
	 * sozinho: `tween.kill()` mata a tween e deixa o gatilho vivo, segurando
	 * pin, espaçador e cálculo de posição.
	 */
	function remember( el, instance ) {
		if ( ! instance ) {
			return;
		}

		el.__wfanST = el.__wfanST || [];
		el.__wfanST.push( instance );
	}

	function killTriggers( el ) {
		if ( ! el.__wfanST ) {
			return;
		}

		el.__wfanST.forEach( function ( instance ) {
			if ( instance && typeof instance.kill === 'function' ) {
				instance.kill( true );
			}
		} );

		el.__wfanST = null;
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

			var card = 'scrub-card' === spec.preset;

			// A origem é fixada antes, e não posta no `from`: lá ela seria
			// animada de volta até o centro durante a amostra.
			if ( card && spec.card ) {
				gsap.set( targets, { transformOrigin: spec.card.origin } );
			}

			var tween = gsap.from( targets, assign( {}, fromVars( spec ), {
				duration: Math.max( 0.01, spec.duration / 1000 ),
				delay: spec.delay / 1000,
				ease: spec.easing,
				stagger: spec.stagger ? spec.stagger.each / 1000 : 0,
				clearProps: card ? CARD_PROPS : 'all'
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

			// Amarrar de novo sem desamarrar o de antes é o que fazia o ▶
			// Testar empilhar ScrollTrigger a cada clique: matar a tween não
			// mata o gatilho dela, e os antigos continuavam disputando o
			// mesmo elemento a cada rolagem.
			killTriggers( el );

			var trigger = {
				trigger: el,
				start: startFor( spec ),
				end: endFor( spec ),
				scrub: true,
				pin: spec.pin ? el : false,
				invalidateOnRefresh: true
			};

			if ( 'pin' === spec.preset ) {
				remember( el, ScrollTrigger.create( {
					trigger: el,
					start: 'top top',
					end: endFor( spec ),
					pin: true,
					pinSpacing: true,
					invalidateOnRefresh: true
				} ) );

				return;
			}

			/*
			 * O cartão anima o próprio elemento, nunca os filhos: a cascata
			 * encolheria cada coluna para o seu lado, e o efeito é a seção
			 * inteira virando um bloco só.
			 */
			if ( 'scrub-card' === spec.preset ) {
				if ( ! spec.card ) {
					return;
				}

				var states = cardStates( el, spec );
				var path = cardTrigger( spec );

				trigger.start = path.start;
				trigger.end = path.end;

				gsap.set( el, { transformOrigin: spec.card.origin } );

				el.__wfanTween = gsap.fromTo( el, states.from, assign( {}, states.to, {
					ease: 'none',
					scrollTrigger: trigger
				} ) );

				if ( el.__wfanTween.scrollTrigger ) {
					remember( el, el.__wfanTween.scrollTrigger );
				}

				return;
			}

			var from = {};
			var to = {};

			/*
			 * Fatia relativa de cada alvo, quando o preset revela pedaço por
			 * pedaço. Relativa porque o ScrollTrigger normaliza a linha do
			 * tempo inteira sobre a faixa de rolagem: com `each` igual à
			 * duração, cada pedaço ocupa uma fatia igual do percurso, que é
			 * o que os efeitos originais faziam à mão com floor(progresso).
			 */
			var spread = 0;

			// Invertido, o parallax anda contra a rolagem: mesma faixa, sinais trocados.
			var sign = spec.reverse ? -1 : 1;

			switch ( spec.preset ) {
				case 'parallax-y':
					from = { y: -spec.dist * sign };
					to = { y: spec.dist * sign };
					break;

				case 'parallax-x':
					from = { x: -spec.dist * sign };
					to = { x: spec.dist * sign };
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

				case 'text-scrub-words':
				case 'text-scrub-chars':
					from = { opacity: spec.dim };
					to = { opacity: 1 };
					spread = 1;
					break;

				case 'lottie-scrub':
					return;

				default:
					from = { opacity: 0 };
					to = { opacity: 1 };
			}

			to.ease = 'none';
			to.scrollTrigger = trigger;

			if ( spread && targets.length > 1 ) {
				/*
				 * A borda da frente de luz. Com a duração igual ao passo, só
				 * um pedaço está em transição por vez e a frente fica dura,
				 * como um interruptor andando pelo texto; a referência mantém
				 * umas três linhas em degradê. Em fração do texto, e não num
				 * número fixo de palavras, para o degradê ocupar a mesma parte
				 * da tela num título de seis palavras e num parágrafo de cem.
				 */
				to.duration = Math.max( 1, Math.round( targets.length * SOFT_EDGE ) );
				to.stagger = spread;
			}

			/*
			 * O estado de partida de TODOS os pedaços, antes da tween. O
			 * `fromTo` com `stagger` só aplica o valor inicial de cada filho
			 * quando o início DELE chega: até lá, quem vem depois da frente de
			 * luz fica no estado natural — aceso — e só cai para o apagado
			 * quando a frente o alcança. Medido num Chrome real, com a página
			 * parada antes do percurso: só a primeira palavra em 0.20, as
			 * outras 101 em 1.00. Era o final do texto aparecendo antes da
			 * hora, e mais ainda numa descida rápida, em que a frente salta.
			 */
			if ( spread && targets.length > 1 ) {
				gsap.set( targets, from );
			}

			el.__wfanTween = gsap.fromTo( targets, from, to );

			if ( el.__wfanTween.scrollTrigger ) {
				remember( el, el.__wfanTween.scrollTrigger );
			}
		},

		reset: function ( el, spec, targets ) {
			if ( el.__wfanTween ) {
				el.__wfanTween.kill();
				el.__wfanTween = null;
			}

			killTriggers( el );

			if ( 'scrub-card' === spec.preset ) {
				gsap.set( [ el ].concat( targets ), { clearProps: CARD_PROPS } );
				return;
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
