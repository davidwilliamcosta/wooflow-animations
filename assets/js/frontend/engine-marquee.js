/**
 * Motor do marquee: o conteúdo do elemento corre em fila infinita, sozinho
 * (`marquee-auto`) ou empurrado pela rolagem (`marquee-scroll`).
 *
 * Sem biblioteca nenhuma. Quem o chama é o `bindTrigger()` do core.js, que
 * entrega o elemento ao `bind()` e não amarra gatilho — o efeito não tem
 * começo nem fim. Ver CLAUDE.md, regra 31.
 *
 * O que ele faz com o DOM: pega os filhos do "miolo" do elemento (o
 * `.elementor-widget-container` de um widget, o `.e-con-inner` de um
 * container), põe todos num grupo dentro de uma esteira e, no modo de borda a
 * borda, clona o grupo até a esteira cobrir a largura. A esteira anda por
 * `transform`, e a posição é uma função só do deslocamento `u`:
 *
 *   u = (auto: acumulado pelo tempo) | (scroll: lido da rolagem) + (arrasto)
 *
 * O sentido do movimento fica no `pos()`, não no `u` — assim o arrasto, a
 * inércia e a rolagem somam no mesmo eixo, qualquer que seja a direção.
 */
( function () {
	'use strict';

	if ( ! window.wfan ) {
		return;
	}

	// Nomes que têm de bater com o frontend.css.
	var HOST = 'wfan-mq';
	var TRACK = 'wfan-mq-track';
	var GROUP = 'wfan-mq-group';
	var DRAGGABLE = 'wfan-mq--drag';
	var DRAGGING = 'wfan-mq--dragging';

	// Fração da velocidade que sobra com o mouse em cima.
	var SLOW = 0.2;

	// Onde fica o miolo de cada tipo de elemento do Elementor, em ordem.
	var CORES = [ 'elementor-widget-container', 'e-con-inner', 'elementor-container', 'elementor-widget-wrap' ];

	function coreOf( el ) {
		var kids = el.children;

		for ( var i = 0; i < kids.length; i++ ) {
			for ( var j = 0; j < CORES.length; j++ ) {
				if ( kids[ i ].classList.contains( CORES[ j ] ) ) {
					return kids[ i ];
				}
			}
		}

		return el;
	}

	function mod( n, base ) {
		return ( ( n % base ) + base ) % base;
	}

	function clamp( n, low, high ) {
		return Math.max( low, Math.min( high, n ) );
	}

	/**
	 * Cópia da esteira para preencher a largura. Não pode repetir `id`, não
	 * pode entrar na ordem de tabulação nem ser lida duas vezes por leitor de
	 * tela, e o pré-esconde de um filho animado não pode esconder a cópia.
	 */
	function cloneGroup( group ) {
		var copy = group.cloneNode( true );

		copy.setAttribute( 'aria-hidden', 'true' );
		copy.setAttribute( 'inert', '' );

		[].forEach.call( copy.querySelectorAll( '[id]' ), function ( node ) {
			node.removeAttribute( 'id' );
		} );

		[].forEach.call( copy.querySelectorAll( '.wfan-pending' ), function ( node ) {
			node.classList.remove( 'wfan-pending' );
		} );

		return copy;
	}

	/** Deslocamento total, em px de percurso. */
	function offset( m ) {
		return m.ua + m.us + m.ud;
	}

	/** Volta do percurso: uma largura de grupo, ou o grupo mais a vitrine. */
	function lap( m ) {
		return m.cfg.edge ? m.W : m.W + m.C;
	}

	/** Onde a esteira está, em px, a partir do canto esquerdo da vitrine. */
	function pos( m ) {
		var u = mod( offset( m ), lap( m ) );

		if ( m.cfg.edge ) {
			return m.cfg.dir < 0 ? -u : u - m.W;
		}

		return m.cfg.dir < 0 ? m.C - u : -m.W + u;
	}

	function draw( m ) {
		m.track.style.transform = 'translate3d(' + pos( m ).toFixed( 2 ) + 'px,0,0)';
	}

	/**
	 * O deslocamento que a rolagem pede agora.
	 *
	 * Borda a borda: zero com o centro do elemento no centro da tela, e cresce
	 * conforme ele sobe — o marquee anda um pixel por pixel de rolagem (vezes a
	 * intensidade). Sem borda a borda: o texto cruza a vitrine uma vez, do
	 * momento em que o elemento aparece no pé da tela até sair pelo topo.
	 */
	function scrollTarget( m ) {
		var rect = m.host.getBoundingClientRect();
		var vh = window.innerHeight || document.documentElement.clientHeight;

		if ( m.cfg.edge ) {
			return ( vh / 2 - ( rect.top + rect.height / 2 ) ) * m.cfg.force;
		}

		return clamp( ( vh - rect.top ) / ( vh + rect.height ), 0, 0.9999 ) * lap( m );
	}

	/**
	 * Mede a vitrine e o grupo e ajusta o número de cópias. Devolve falso se
	 * ainda não há o que medir (elemento escondido, fonte por chegar).
	 */
	function layout( m ) {
		var C = m.host.clientWidth;
		var W = m.group.getBoundingClientRect().width;

		if ( ! C || ! W ) {
			return false;
		}

		m.C = C;
		m.W = W;

		var wanted = m.cfg.edge ? Math.ceil( C / W ) + 1 : 1;

		while ( m.copies.length + 1 < wanted ) {
			var copy = cloneGroup( m.group );

			m.track.appendChild( copy );
			m.copies.push( copy );
		}

		while ( m.copies.length + 1 > wanted ) {
			m.track.removeChild( m.copies.pop() );
		}

		return true;
	}

	function schedule( m ) {
		if ( ! m.raf && ! m.dead ) {
			m.raf = window.requestAnimationFrame( function ( now ) {
				tick( m, now );
			} );
		}
	}

	/** Acorda o laço. Ele se apaga sozinho quando não há mais o que mover. */
	function wake( m ) {
		if ( m.visible ) {
			schedule( m );
		}
	}

	function tick( m, now ) {
		m.raf = 0;

		if ( m.dead || ! m.ready ) {
			return;
		}

		// O passo é limitado: a aba que volta do segundo plano trazia um dt de
		// segundos, e o marquee saltava a esteira inteira de uma vez.
		var dt = m.last ? Math.min( 64, now - m.last ) / 1000 : 1 / 60;
		var again = false;

		m.last = now;

		var aim = m.hover && m.cfg.slow ? SLOW : 1;

		if ( Math.abs( m.mul - aim ) > 0.002 ) {
			m.mul += ( aim - m.mul ) * ( 1 - Math.exp( -dt / 0.25 ) );
			again = true;
		} else {
			m.mul = aim;
		}

		if ( 'auto' === m.cfg.mode ) {
			if ( ! m.cfg.freeze && ! m.dragging ) {
				m.ua += m.cfg.speed * m.mul * dt;
				again = true;
			}
		} else if ( ! m.dragging ) {
			var goal = scrollTarget( m );

			// Voltando de fora da tela, ou no primeiro quadro, o marquee
			// parte direto do ponto certo: amortecer desde o valor velho
			// varreria a esteira à vista.
			if ( m.snap ) {
				m.us = goal;
				m.snap = false;
			} else if ( Math.abs( goal - m.us ) > 0.2 ) {
				m.us += ( goal - m.us ) * ( 1 - Math.exp( -dt / 0.12 ) );
				again = true;
			} else {
				m.us = goal;
			}
		}

		// Inércia do arrasto: o que sobrou de velocidade na soltura, até zerar.
		if ( ! m.dragging && Math.abs( m.vel ) > 5 ) {
			m.ud += m.vel * dt;
			m.vel *= Math.exp( -dt / 0.45 );
			again = true;
		} else if ( ! m.dragging ) {
			m.vel = 0;
		}

		draw( m );

		if ( again && m.visible ) {
			schedule( m );
		} else {
			m.last = 0;
		}
	}

	function listen( m, target, type, fn, opts ) {
		target.addEventListener( type, fn, opts );

		m.undo.push( function () {
			target.removeEventListener( type, fn, opts );
		} );
	}

	function bindDrag( m ) {
		var host = m.host;
		var down = null;

		host.classList.add( DRAGGABLE );

		listen( m, host, 'pointerdown', function ( e ) {
			if ( 'mouse' === e.pointerType && 0 !== e.button ) {
				return;
			}

			down = { id: e.pointerId, x: e.clientX, lastX: e.clientX, lastT: e.timeStamp, moved: false };
			m.vel = 0;
		} );

		listen( m, host, 'pointermove', function ( e ) {
			if ( ! down || e.pointerId !== down.id ) {
				return;
			}

			if ( ! down.moved ) {
				// Abaixo do limiar ainda é um clique: capturar o ponteiro
				// agora mandaria o clique para o elemento errado e mataria
				// os links do marquee.
				if ( Math.abs( e.clientX - down.x ) < 5 ) {
					return;
				}

				down.moved = true;
				m.dragging = true;
				host.classList.add( DRAGGING );

				try {
					host.setPointerCapture( e.pointerId );
				} catch ( err ) {
					// Ponteiro já liberado; o arrasto segue sem captura.
				}
			}

			var dx = e.clientX - down.lastX;
			var span = Math.max( 1, e.timeStamp - down.lastT );

			// Arrastar o conteúdo um pixel para a direita é um pixel de
			// percurso no sentido oposto ao do movimento à esquerda.
			m.ud += dx * m.cfg.dir;
			m.vel = m.vel * 0.5 + ( dx * m.cfg.dir / span * 1000 ) * 0.5;

			down.lastX = e.clientX;
			down.lastT = e.timeStamp;

			draw( m );
		} );

		function release( e ) {
			if ( ! down || e.pointerId !== down.id ) {
				return;
			}

			if ( down.moved ) {
				// O clique que o navegador dispara logo após a soltura não
				// pode abrir o link sobre o qual o arrasto terminou.
				m.suppress = true;
				window.setTimeout( function () {
					m.suppress = false;
				}, 0 );

				// Parou antes de soltar: não há inércia a herdar.
				if ( e.timeStamp - down.lastT > 100 ) {
					m.vel = 0;
				}
			}

			m.dragging = false;
			host.classList.remove( DRAGGING );
			down = null;
			wake( m );
		}

		listen( m, host, 'pointerup', release );
		listen( m, host, 'pointercancel', release );

		listen( m, host, 'click', function ( e ) {
			if ( m.suppress ) {
				e.preventDefault();
				e.stopPropagation();
			}
		}, true );

		// Imagem e link têm arrasto nativo, que roubaria o gesto.
		listen( m, host, 'dragstart', function ( e ) {
			e.preventDefault();
		} );
	}

	function unmount( el ) {
		var m = el.__wfanMq;

		if ( ! m ) {
			return;
		}

		m.dead = true;
		window.cancelAnimationFrame( m.raf );
		m.undo.forEach( function ( fn ) {
			fn();
		} );

		// Os nós originais voltam para onde estavam: o editor re-renderiza o
		// widget a cada mexida, mas o ▶ Testar desmonta e remonta o mesmo.
		if ( m.track.parentNode === m.host ) {
			while ( m.group.firstChild ) {
				m.host.insertBefore( m.group.firstChild, m.track );
			}

			m.host.removeChild( m.track );
		}

		m.host.classList.remove( HOST, DRAGGABLE, DRAGGING );
		m.host.style.removeProperty( '--wfan-mq-gap' );
		el.__wfanMq = null;
	}

	function mount( el, spec ) {
		unmount( el );

		var host = coreOf( el );
		var nodes = [].slice.call( host.childNodes );

		// Sem nada para correr (só espaço em branco) não há marquee.
		if ( ! nodes.some( function ( n ) {
			return 1 === n.nodeType || ( 3 === n.nodeType && n.nodeValue.trim() );
		} ) ) {
			return;
		}

		var track = document.createElement( 'div' );
		var group = document.createElement( 'div' );

		track.className = TRACK;
		group.className = GROUP;

		nodes.forEach( function ( node ) {
			group.appendChild( node );
		} );

		track.appendChild( group );
		host.appendChild( track );
		host.classList.add( HOST );
		host.style.setProperty( '--wfan-mq-gap', spec.marquee.gap + 'px' );

		var m = {
			el: el,
			host: host,
			track: track,
			group: group,
			cfg: spec.marquee,
			copies: [],
			undo: [],
			C: 0,
			W: 0,
			ua: 0,
			us: 0,
			ud: 0,
			vel: 0,
			mul: 1,
			hover: false,
			dragging: false,
			suppress: false,
			visible: true,
			snap: true,
			ready: false,
			dead: false,
			raf: 0,
			last: 0
		};

		el.__wfanMq = m;

		function measure() {
			var was = m.ready;

			m.ready = layout( m );

			if ( ! m.ready ) {
				return;
			}

			// Sem borda a borda o texto nasce dentro da vitrine, e não à
			// espera fora dela: um marquee que abre vazio parece quebrado.
			if ( ! was && 'auto' === m.cfg.mode && ! m.cfg.edge ) {
				m.ua = m.cfg.dir < 0 ? m.C : m.W;
			}

			if ( 'scroll' === m.cfg.mode ) {
				m.snap = true;
			}

			draw( m );
			wake( m );
		}

		measure();

		// A largura muda por razões que o marquee não vê: webfont chegando,
		// imagem carregando, girar o celular. O observador pega as três.
		if ( window.ResizeObserver ) {
			var watcher = new ResizeObserver( function () {
				window.requestAnimationFrame( function () {
					if ( ! m.dead ) {
						measure();
					}
				} );
			} );

			watcher.observe( host );
			watcher.observe( group );
			m.undo.push( function () {
				watcher.disconnect();
			} );
		} else {
			listen( m, window, 'resize', measure, { passive: true } );
		}

		// Fora da tela não se gasta quadro: é a diferença entre um marquee e
		// uma bateria descarregada.
		if ( window.IntersectionObserver ) {
			var seen = new IntersectionObserver( function ( entries ) {
				var now = entries[ entries.length - 1 ].isIntersecting;

				if ( now && ! m.visible ) {
					m.snap = true;
					m.last = 0;
				}

				m.visible = now;
				wake( m );
			} );

			seen.observe( host );
			m.undo.push( function () {
				seen.disconnect();
			} );
		}

		if ( 'scroll' === m.cfg.mode ) {
			listen( m, window, 'scroll', function () {
				wake( m );
			}, { passive: true } );
		}

		if ( m.cfg.slow ) {
			listen( m, host, 'pointerenter', function ( e ) {
				if ( 'mouse' === e.pointerType ) {
					m.hover = true;
					wake( m );
				}
			} );

			listen( m, host, 'pointerleave', function () {
				m.hover = false;
				wake( m );
			} );
		}

		if ( m.cfg.drag ) {
			bindDrag( m );
		}

		wake( m );
	}

	window.wfan.register( 'marquee', {
		bind: function ( el, spec ) {
			mount( el, spec );
		},

		// O ▶ Testar chama `reset` e depois `play`: desmontar e montar de
		// novo é o que "tocar outra vez" quer dizer aqui.
		play: function ( el, spec ) {
			mount( el, spec );
		},

		reset: function ( el ) {
			unmount( el );
		}
	} );
}() );
