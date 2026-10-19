/**
 * Botão "voltar ao topo".
 *
 * Não passa pelo core.js de propósito: não é animação de elemento, não tem
 * gatilho de tela e não esconde conteúdo. Depender do core custaria ~6 KB em
 * toda página do site só para ler um scrollY. Ver CLAUDE.md, regra 5.
 */
( function () {
	'use strict';

	var cfg = window.wfanBackToTop || {};

	// O nome da classe tem de bater com WFAN_BackToTop::CLASS_NAME e
	// ::VISIBLE_CLASS: é o PHP que imprime uma e o CSS que estiliza a outra.
	var SELECTOR = '.wfan-btt';
	var VISIBLE = 'is-visible';

	var threshold = parseInt( cfg.threshold, 10 ) || 0;
	var duration = parseInt( cfg.duration, 10 ) || 800;
	var ring = !! cfg.ring;

	var btn = null;
	var ticking = false;
	var shown = null;
	var frame = 0;

	function log() {
		if ( cfg.debug && window.console ) {
			window.console.log.apply( window.console, [ '[wfan-btt]' ].concat( [].slice.call( arguments ) ) );
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
	 * Salto seco, sem o `scroll-behavior:smooth` que vários temas põem no
	 * <html>: com ele, cada passo do tween abaixo seria suavizado de novo pelo
	 * navegador, e a subida ou vira melado ou não chega ao topo.
	 */
	function jumpTo( y ) {
		try {
			window.scrollTo( { top: y, left: 0, behavior: 'instant' } );
		} catch ( e ) {
			window.scrollTo( 0, y );
		}
	}

	function easeOutCubic( t ) {
		return 1 - Math.pow( 1 - t, 3 );
	}

	function update() {
		ticking = false;

		var y = scrollY();
		var want = y > threshold;

		// Só na travessia: mexer no classList a cada frame de scroll é
		// trabalho de estilo jogado fora, e é o que faz a rolagem engasgar em
		// aparelho fraco. `shown` nasce null para a primeira decisão valer nos
		// dois sentidos.
		if ( want !== shown ) {
			shown = want;

			if ( want ) {
				btn.classList.add( VISIBLE );
			} else {
				btn.classList.remove( VISIBLE );
			}
		}

		if ( ring ) {
			/*
			 * Lidos a cada frame, nunca memorizados: a altura do documento
			 * muda com resize, com fonte carregando, com acordeão abrindo e
			 * com conteúdo entrando por lazy-load. Guardar o máximo exigiria
			 * resize + ResizeObserver + MutationObserver para ficar menos
			 * correto — e é por isso que este arquivo não ouve resize.
			 */
			var max = ( document.documentElement.scrollHeight || 0 ) - window.innerHeight;
			var p = max > 0 ? y / max : 0;

			btn.style.setProperty( '--wfan-btt-p', ( p < 0 ? 0 : ( p > 1 ? 1 : p ) ).toFixed( 4 ) );
		}
	}

	function onScroll() {
		if ( ticking ) {
			return;
		}

		ticking = true;
		window.requestAnimationFrame( update );
	}

	function stop() {
		if ( frame ) {
			window.cancelAnimationFrame( frame );
			frame = 0;
		}

		window.removeEventListener( 'wheel', stop );
		window.removeEventListener( 'touchstart', stop );
		window.removeEventListener( 'keydown', stop );
	}

	/**
	 * Vinte linhas de tween, só porque o botão tem campo de duração.
	 * easeOutCubic porque a desaceleração no fim é onde o olho acompanha a
	 * parada — e é a mesma curva entregue ao Lenis, para as duas vias baterem.
	 */
	function rise() {
		var from = scrollY();
		var start = 0;

		// Rolar durante a subida é o usuário dizendo que mudou de ideia.
		window.addEventListener( 'wheel', stop, { passive: true } );
		window.addEventListener( 'touchstart', stop, { passive: true } );
		window.addEventListener( 'keydown', stop );

		function step( now ) {
			if ( ! start ) {
				start = now;
			}

			var t = Math.min( 1, ( now - start ) / duration );

			jumpTo( from * ( 1 - easeOutCubic( t ) ) );

			if ( t < 1 ) {
				frame = window.requestAnimationFrame( step );

				return;
			}

			stop();
		}

		frame = window.requestAnimationFrame( step );
	}

	/**
	 * O foco sai ANTES da subida, e por um motivo concreto: o botão esconde a
	 * si mesmo ao cruzar o limiar de volta, e `visibility:hidden` num elemento
	 * focado devolve o foco ao <body> sem avisar ninguém — o leitor de tela
	 * fica mudo e o próximo Tab recomeça do topo do documento. Mandando o foco
	 * para o começo do conteúdo, a ida ao topo é também uma ida no teclado,
	 * que é o que o botão promete.
	 */
	function focusTop() {
		var top = document.querySelector( 'main, [role="main"], #main, #content, #page' ) || document.body;

		if ( top.hasAttribute && ! top.hasAttribute( 'tabindex' ) ) {
			top.setAttribute( 'tabindex', '-1' );
		}

		try {
			top.focus( { preventScroll: true } );
		} catch ( e ) {
			top.focus();
		}
	}

	function onClick() {
		focusTop();

		if ( reduced() ) {
			log( 'salto seco: reduzir movimento' );
			jumpTo( 0 );

			return;
		}

		var lenis = window.wfanLenisInstance;

		/*
		 * Com o Lenis na página a subida TEM de passar por ele: ele é dono do
		 * scroll e desfaria um window.scrollTo nosso no raf seguinte — a
		 * página subiria e voltaria sozinha.
		 */
		if ( lenis && lenis.scrollTo ) {
			log( 'subindo pelo Lenis' );
			lenis.scrollTo( 0, { duration: duration / 1000, easing: easeOutCubic } );

			return;
		}

		log( 'subindo pelo tween próprio' );
		rise();
	}

	function start() {
		btn = document.querySelector( SELECTOR );

		if ( ! btn ) {
			log( 'nenhum botão na página' );

			return;
		}

		btn.addEventListener( 'click', onClick );
		window.addEventListener( 'scroll', onScroll, { passive: true } );

		// A página pode nascer rolada: âncora na URL, restauração do navegador
		// no voltar, ou o próprio Lenis reposicionando. Sem esta primeira
		// decisão o botão só apareceria no primeiro scroll.
		update();
	}

	/*
	 * Esperar o DOM não é precaução: é obrigatório aqui. O <button> é impresso
	 * no `wp_footer` com prioridade 100, e os scripts de rodapé do WordPress
	 * saem na 20 — ou seja, este arquivo roda ANTES de o botão existir. Um
	 * querySelector no topo do arquivo devolvia null, o clique nunca era
	 * amarrado e o botão nascia morto em todo site. Cobre também o contrário:
	 * um otimizador que mova o script para o fim da página cai no `else`.
	 */
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
}() );
