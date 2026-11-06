/**
 * Painéis expansíveis — a seção "WooFlow — Painéis expansíveis" do container.
 * Não depende do core.js nem de biblioteca nenhuma.
 *
 * Os filhos diretos do container viram painéis: um aberto, ocupando o que
 * sobra da largura, e os outros reduzidos a uma faixa com número, título na
 * vertical e um botão "+". Abaixo do ponto de quebra eles empilham e viram um
 * accordion vertical.
 *
 * O contrato inteiro chega em custom properties que o Elementor escreve a
 * partir dos `selectors` dos controles (ver WFAN_Panels e a regra 33). Nada vem
 * em atributo: no canvas do editor o wrapper é reconstruído pelo Backbone e
 * perderia qualquer atributo do PHP — o CSS por elemento, não.
 *
 * Sem este arquivo nada fica escondido: os filhos continuam lado a lado, como
 * o Elementor os desenhou. É aqui que os fechados perdem o conteúdo de vista,
 * e sempre ganhando a faixa no lugar.
 */
( function () {
	'use strict';

	var cfg = window.wfanPanelsConfig || {};

	var VARS = cfg.vars || {
		on: '--wfan-pnl',
		first: '--wfan-pnl-first',
		trigger: '--wfan-pnl-tr',
		closed: '--wfan-pnl-min',
		bar: '--wfan-pnl-bar',
		stack: '--wfan-pnl-bp',
		number: '--wfan-pnl-num',
		dur: '--wfan-pnl-dur'
	};

	var HOST_CLASS = cfg.hostClass || 'wfan-pnl';
	var MEASURE = cfg.measure || '--wfan-pnl-cw';

	// Estados do container, todos postos e tirados por este arquivo.
	var READY = HOST_CLASS + '--ready';
	var STACK = HOST_CLASS + '--stack';
	var STILL = HOST_CLASS + '--still';
	var INIT = HOST_CLASS + '--init';
	var NO_NUMBER = HOST_CLASS + '--nonum';

	var ROOT = 'wfan-pnl-root';
	var ITEM = 'wfan-pnl-item';
	var ITEM_ROW = 'wfan-pnl-item--row';
	var OPEN = 'is-open';
	var RAIL = 'wfan-pnl-rail';

	var DEFAULT_CLOSED = '110px';
	var DEFAULT_BAR = '72px';
	var DEFAULT_STACK = 767;
	var DEFAULT_DUR = 700;

	// Intenção do hover: passar o mouse por cima de três painéis a caminho de
	// outro lugar não pode abrir os três.
	var HOVER_INTENT = 90;

	// Propriedades inline que este arquivo escreve, para devolver tudo como
	// estava quando o toggle é desligado no editor.
	var ITEM_PROPS = [ 'flex', 'width', 'minWidth', 'maxWidth', 'height' ];
	var ROOT_PROPS = [ 'display', 'flexDirection', 'flexWrap', 'alignItems' ];

	function log() {
		if ( cfg.debug && window.console ) {
			window.console.log.apply( window.console, [ '[WooFlow Animations/painéis]' ].concat( [].slice.call( arguments ) ) );
		}
	}

	// ------------------------------------------------------------------ leitura

	function value( el, name ) {
		return String( window.getComputedStyle( el ).getPropertyValue( name ) ).trim();
	}

	function int( raw, fallback ) {
		var parsed = parseInt( raw, 10 );

		return isFinite( parsed ) ? parsed : fallback;
	}

	/** `700ms` ou `0.7s`, como o Elementor escreveu, em milissegundos. */
	function ms( raw, fallback ) {
		var parsed = parseFloat( raw );

		if ( ! isFinite( parsed ) ) {
			return fallback;
		}

		return /[^m]s$/.test( raw ) ? parsed * 1000 : parsed;
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

	/**
	 * Container com os painéis ligados. A custom property é herdada por toda a
	 * árvore abaixo dele — os próprios painéis dizem "1" também —, então quem
	 * conta é o elemento em que ela COMEÇA: o pai não pode ter o mesmo valor.
	 */
	function isOn( el ) {
		if ( ! el || 1 !== el.nodeType ) {
			return false;
		}

		if ( '1' !== value( el, VARS.on ) ) {
			return false;
		}

		return ! el.parentElement || '1' !== value( el.parentElement, VARS.on );
	}

	/**
	 * No front-end a classe do PHP basta. No editor ela fica da carga inicial
	 * mesmo depois de o toggle ser desligado, e quem decide é o CSS.
	 */
	function isHost( el ) {
		if ( ! el || 1 !== el.nodeType ) {
			return false;
		}

		if ( ! cfg.editor && el.classList.contains( HOST_CLASS ) ) {
			return true;
		}

		return isOn( el );
	}

	function rootOf( host ) {
		var children = host.children;

		for ( var i = 0; i < children.length; i++ ) {
			if ( children[ i ].classList.contains( 'e-con-inner' ) ) {
				return children[ i ];
			}
		}

		return host;
	}

	/**
	 * O título da faixa: o atributo `data-wfan-label` (Avançado > Atributos, no
	 * Elementor Pro) ou o primeiro Título de dentro do painel.
	 */
	function labelOf( item ) {
		var own = item.getAttribute( 'data-wfan-label' );

		if ( own ) {
			return own.trim();
		}

		var heading = item.querySelector( '.elementor-heading-title, h1, h2, h3, h4, h5, h6' );

		return heading ? String( heading.textContent ).replace( /\s+/g, ' ' ).trim() : '';
	}

	function pad( n ) {
		return ( n < 10 ? '0' : '' ) + n;
	}

	// ------------------------------------------------------------------ instância

	function Panels( host ) {
		var self = this;

		this.host = host;
		this.items = [];
		this.current = -1;
		this.first = null;
		this.stacked = false;
		this.timer = null;

		this.onClick = function ( event ) {
			self.click( event );
		};

		this.onOver = function ( event ) {
			self.over( event );
		};

		this.onOut = function ( event ) {
			if ( ! self.host.contains( event.relatedTarget ) ) {
				window.clearTimeout( self.timer );
			}
		};

		host.addEventListener( 'click', this.onClick );
		host.addEventListener( 'pointerover', this.onOver );
		host.addEventListener( 'pointerout', this.onOut );

		this.refresh();

		log( 'montado', host );
	}

	Panels.prototype.read = function () {
		var host = this.host;
		var stack = value( host, VARS.stack );

		this.firstWanted = Math.max( 1, int( value( host, VARS.first ), 1 ) );
		this.trigger = 'hover' === value( host, VARS.trigger ) ? 'hover' : 'click';
		this.numbered = '1' === value( host, VARS.number );
		this.breakpoint = '' === stack ? DEFAULT_STACK : int( stack, DEFAULT_STACK );
		this.duration = ms( value( host, VARS.dur ), DEFAULT_DUR );
	};

	/**
	 * Refaz tudo a partir do DOM e do CSS de agora. É chamado no boot, no
	 * resize e — no editor — a cada re-render do container ou de um painel,
	 * que troca os nós e leva as faixas junto. O painel aberto é mantido; só
	 * muda quando o "Painel aberto de início" muda.
	 */
	Panels.prototype.refresh = function () {
		var host = this.host;
		var root = rootOf( host );

		this.read();

		if ( this.root && this.root !== root ) {
			this.clearRoot();
		}

		this.root = root;
		this.items = [].filter.call( root.children, function ( child ) {
			return child.classList.contains( 'elementor-element' );
		} );

		if ( this.first !== this.firstWanted || this.current < 0 ) {
			this.first = this.firstWanted;
			this.current = this.first - 1;
		}

		if ( this.current >= this.items.length ) {
			this.current = Math.max( 0, this.items.length - 1 );
		}

		this.stacked = this.breakpoint > 0 && window.innerWidth <= this.breakpoint;

		host.classList.add( READY );
		host.classList.toggle( STACK, this.stacked );
		host.classList.toggle( NO_NUMBER, ! this.numbered );
		host.classList.toggle( STILL, prefersReduced() || ( !! cfg.offMobile && isSmallScreen() ) );

		root.classList.add( ROOT );

		// Grade do Elementor (Layout > Grade, `--display: grid`, classe
		// `.e-grid`) ignora o flex dos filhos: as colunas ficam iguais e o
		// painel aberto não cresce. O inline passa por cima de
		// `display: var(--display)` sem `!important`. Sem o JS, a grade
		// continua como o Elementor desenhou.
		root.style.display = 'flex';
		root.style.flexDirection = this.stacked ? 'column' : 'row';
		root.style.flexWrap = 'nowrap';
		root.style.alignItems = 'stretch';

		this.items.forEach( this.rail, this );

		this.apply( false );
	};

	/** A faixa do painel fechado. Criada uma vez por nó, reescrita sempre. */
	Panels.prototype.rail = function ( item, index ) {
		var rail = null;

		for ( var i = 0; i < item.children.length; i++ ) {
			if ( item.children[ i ].classList.contains( RAIL ) ) {
				rail = item.children[ i ];
			}
		}

		if ( ! rail ) {
			rail = document.createElement( 'button' );
			rail.type = 'button';
			rail.className = RAIL;
			rail.innerHTML = '<span class="wfan-pnl-n"></span><span class="wfan-pnl-label"></span><span class="wfan-pnl-plus" aria-hidden="true"></span>';
			item.appendChild( rail );
		}

		item.classList.add( ITEM );

		// Painel com os filhos lado a lado: dar a cada filho a largura do
		// painel aberto empurraria os outros para fora. Esse fica sem a
		// largura congelada, e requebra enquanto abre.
		item.classList.toggle( ITEM_ROW, 0 === String( window.getComputedStyle( item ).flexDirection ).indexOf( 'row' ) );

		if ( 'static' === window.getComputedStyle( item ).position ) {
			item.style.position = 'relative';
			item.__wfanPositioned = true;
		}

		rail.children[ 0 ].textContent = pad( index + 1 );
		rail.children[ 1 ].textContent = labelOf( item );
	};

	/**
	 * Escreve o estado de cada painel. A largura vai inline: o Elementor põe
	 * `width: var(--width)` e o `flex` dele em todo container, e só o inline
	 * passa por cima disso sem `!important`.
	 */
	Panels.prototype.apply = function ( animate ) {
		var self = this;
		var host = this.host;

		if ( ! animate ) {
			host.classList.add( INIT );
		}

		this.items.forEach( function ( item, index ) {
			var open = index === self.current;
			var rail = item.querySelector( '.' + RAIL );

			item.classList.toggle( OPEN, open );

			if ( rail ) {
				rail.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
				rail.tabIndex = open ? -1 : 0;
			}

			if ( self.stacked ) {
				item.style.flex = '0 0 auto';
				item.style.width = '100%';
				item.style.minWidth = '0';
				item.style.maxWidth = 'none';
				self.height( item, open, animate );
				return;
			}

			item.style.flex = open ? '1 1 0%' : '0 0 var(' + VARS.closed + ', ' + DEFAULT_CLOSED + ')';
			item.style.width = 'auto';
			item.style.minWidth = '0';
			item.style.maxWidth = 'none';
			item.style.height = '';
		} );

		if ( ! animate ) {
			this.measure();

			// Força o layout já no estado final e só então devolve as
			// transições: sem isso a página nasceria animando o fechamento.
			void host.offsetWidth;
			window.requestAnimationFrame( function () {
				host.classList.remove( INIT );
			} );
		}
	};

	/**
	 * A largura em que o conteúdo de todo painel é diagramado: a do conteúdo
	 * do painel aberto, já no tamanho final. Todos os fechados têm a mesma
	 * largura, então ela não muda ao trocar de painel — só no resize e no
	 * re-render, que passam por aqui sem animação.
	 */
	Panels.prototype.measure = function () {
		var item = this.items[ this.current ];

		if ( this.stacked || ! item ) {
			this.host.style.removeProperty( MEASURE );
			return;
		}

		// Mede sem a largura congelada: com ela, o conteúdo de um painel
		// aberto mais estreito que a medida anterior ficaria largo demais.
		this.host.style.removeProperty( MEASURE );

		var style = window.getComputedStyle( item );
		var width = item.clientWidth - ( parseFloat( style.paddingLeft ) || 0 ) - ( parseFloat( style.paddingRight ) || 0 );

		if ( width > 0 ) {
			this.host.style.setProperty( MEASURE, Math.floor( width ) + 'px' );
		}
	};

	/**
	 * Empilhado, a altura de um painel aberto é a do conteúdo, e `auto` não se
	 * anima. Então a abertura vai até a medida em px e só no fim volta a
	 * `auto` — senão um texto que quebra diferente depois cortaria o painel.
	 */
	Panels.prototype.height = function ( item, open, animate ) {
		var bar = 'var(' + VARS.bar + ', ' + DEFAULT_BAR + ')';

		window.clearTimeout( item.__wfanHeight );

		if ( ! animate || this.host.classList.contains( STILL ) ) {
			item.style.height = open ? 'auto' : bar;
			return;
		}

		var from = item.offsetHeight;

		if ( ! open ) {
			item.style.height = from + 'px';
			void item.offsetHeight;
			item.style.height = bar;
			return;
		}

		// A medida do conteúdo sai com `auto` por um instante, e não do
		// `scrollHeight`: num painel com o conteúdo alinhado embaixo — o
		// comum, numa foto com o título no pé —, o que transborda vai para
		// cima, e o `scrollHeight` não conta transbordo para cima.
		item.style.height = 'auto';
		var to = item.offsetHeight;

		item.style.height = from + 'px';
		void item.offsetHeight;
		item.style.height = to + 'px';

		// Um timer e não o `transitionend`: com duração zero, ou com o
		// painel fora da tela, o evento pode nunca chegar.
		item.__wfanHeight = window.setTimeout( function () {
			if ( item.classList.contains( OPEN ) ) {
				item.style.height = 'auto';
			}
		}, this.duration + 50 );
	};

	Panels.prototype.open = function ( index, focus ) {
		if ( index === this.current || index < 0 || index >= this.items.length ) {
			return;
		}

		this.current = index;
		this.apply( true );

		// Aberto pelo teclado, o foco não pode ficar na faixa, que some: o
		// navegador o jogaria para o topo da página.
		if ( focus ) {
			var item = this.items[ index ];

			if ( ! item.hasAttribute( 'tabindex' ) ) {
				item.setAttribute( 'tabindex', '-1' );
			}

			item.focus( { preventScroll: true } );
		}

		log( 'aberto', index + 1, this.host );
	};

	Panels.prototype.indexOf = function ( node ) {
		while ( node && node !== this.host ) {
			if ( node.parentElement === this.root ) {
				return this.items.indexOf( node );
			}

			node = node.parentElement;
		}

		return -1;
	};

	Panels.prototype.click = function ( event ) {
		var target = event.target;
		var rail = target && target.closest ? target.closest( '.' + RAIL ) : null;

		if ( ! rail || ! this.host.contains( rail ) ) {
			return;
		}

		var index = this.items.indexOf( rail.parentElement );

		if ( index < 0 ) {
			return;
		}

		event.preventDefault();

		// `detail` zero: o clique veio do Enter ou do Espaço, não do mouse.
		this.open( index, 0 === event.detail );
	};

	Panels.prototype.over = function ( event ) {
		var self = this;

		if ( 'hover' !== this.trigger || this.stacked || 'mouse' !== event.pointerType ) {
			return;
		}

		var index = this.indexOf( event.target );

		window.clearTimeout( this.timer );

		if ( index < 0 || index === this.current ) {
			return;
		}

		this.timer = window.setTimeout( function () {
			self.open( index, false );
		}, HOVER_INTENT );
	};

	Panels.prototype.clearRoot = function () {
		var root = this.root;

		root.classList.remove( ROOT );

		ROOT_PROPS.forEach( function ( prop ) {
			root.style[ prop ] = '';
		} );
	};

	Panels.prototype.destroy = function () {
		var host = this.host;

		window.clearTimeout( this.timer );

		host.removeEventListener( 'click', this.onClick );
		host.removeEventListener( 'pointerover', this.onOver );
		host.removeEventListener( 'pointerout', this.onOut );

		this.items.forEach( function ( item ) {
			var rail = item.querySelector( '.' + RAIL );

			if ( rail && rail.parentElement === item ) {
				item.removeChild( rail );
			}

			window.clearTimeout( item.__wfanHeight );
			item.classList.remove( ITEM, ITEM_ROW, OPEN );

			ITEM_PROPS.forEach( function ( prop ) {
				item.style[ prop ] = '';
			} );

			if ( item.__wfanPositioned ) {
				item.style.position = '';
				item.__wfanPositioned = false;
			}
		} );

		if ( this.root ) {
			this.clearRoot();
		}

		host.classList.remove( READY, STACK, STILL, INIT, NO_NUMBER );
		host.style.removeProperty( MEASURE );
		host.__wfanPanels = null;

		log( 'desmontado', host );
	};

	// -------------------------------------------------------------------- boot

	var instances = [];

	/**
	 * Decide pelo que está no CSS (no editor) ou pela classe (no front-end) e
	 * monta, refaz ou desmonta.
	 */
	function sync( host ) {
		if ( ! host || 1 !== host.nodeType ) {
			return;
		}

		var current = host.__wfanPanels;

		if ( ! isHost( host ) ) {
			if ( current ) {
				instances.splice( instances.indexOf( current ), 1 );
				current.destroy();
			}

			return;
		}

		if ( current ) {
			current.refresh();
			return;
		}

		host.__wfanPanels = new Panels( host );
		instances.push( host.__wfanPanels );
	}

	/**
	 * No editor o wrapper pode não ter a classe — o toggle acabou de ser
	 * ligado, ou o Backbone redesenhou o container —, então ali todo container
	 * é candidato e quem decide é a custom property.
	 */
	function scan( root ) {
		var scope = root && root.querySelectorAll ? root : document;
		var selector = '.' + HOST_CLASS + ( cfg.editor ? ', .e-con' : '' );

		[].forEach.call( scope.querySelectorAll( selector ), sync );
	}

	/** O container de painéis que contém o nó, se houver. */
	function hostOf( node ) {
		while ( node && 1 === node.nodeType ) {
			if ( node.__wfanPanels || isOn( node ) ) {
				return node;
			}

			node = node.parentElement;
		}

		return null;
	}

	function byId( id ) {
		if ( ! id || ! /^[A-Za-z0-9_-]+$/.test( String( id ) ) ) {
			return null;
		}

		return document.querySelector( '.elementor-element[data-id="' + id + '"]' );
	}

	var resizing;

	function onResize() {
		window.clearTimeout( resizing );

		resizing = window.setTimeout( function () {
			instances.slice().forEach( function ( instance ) {
				instance.refresh();
			} );
		}, 150 );
	}

	/**
	 * No editor, um re-render troca o conteúdo do container — ou só o de um
	 * painel — e leva as faixas junto. O container a refazer pode ser o próprio
	 * elemento ou um ancestral dele, e o toggle pode ter acabado de ser ligado,
	 * sem classe nenhuma no wrapper.
	 */
	function onElementReady( $scope ) {
		var el = $scope && $scope[ 0 ];

		if ( ! el ) {
			return;
		}

		if ( cfg.editor ) {
			var host = hostOf( el );

			if ( host ) {
				sync( host );
			}
		} else if ( el.classList.contains( HOST_CLASS ) ) {
			sync( el );
		}

		scan( el );
	}

	function boot() {
		scan( document );

		window.addEventListener( 'resize', onResize );

		if ( window.elementorFrontend && window.elementorFrontend.hooks ) {
			window.elementorFrontend.hooks.addAction( 'frontend/element_ready/global', onElementReady );
		}
	}

	/**
	 * Usado pelo painel do editor quando um controle dos painéis muda. Mudar
	 * um controle só reescreve o CSS do container, sem re-render: sem este
	 * empurrão, ligar o toggle não mostraria nada até a próxima edição.
	 */
	function syncId( id ) {
		sync( byId( id ) );
	}

	/**
	 * Usado pelo painel do editor quando um elemento é escolhido para edição:
	 * se ele mora num painel fechado, o painel abre, senão o usuário editaria
	 * às cegas um conteúdo que não está à vista.
	 */
	function reveal( id ) {
		var el = byId( id );

		if ( ! el ) {
			return;
		}

		instances.forEach( function ( instance ) {
			var index = instance.indexOf( el );

			if ( index > -1 ) {
				instance.open( index, false );
			}
		} );
	}

	window.wfanPanels = {
		sync: sync,
		syncId: syncId,
		reveal: reveal,
		refresh: scan,
		instances: instances
	};

	if ( 'loading' === document.readyState ) {
		// O script sai no rodapé, depois de todo o conteúdo do Elementor: dá
		// para fechar os painéis já, sem esperar o resto da página, e encurtar
		// o quadro em que eles aparecem todos abertos. O boot refaz no
		// DOMContentLoaded, para o que tiver chegado depois.
		scan( document );
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
