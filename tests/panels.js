/**
 * Teste dos painéis expansíveis: `node tests/panels.js`.
 *
 * Carrega o panels.js de verdade num DOM de mentira e dirige cliques, hover,
 * largura de tela e re-render do editor à mão. É o único lugar que confere o
 * que o smoke test não alcança, porque mora no navegador:
 *
 * 1. Quem é o container de painéis. A custom property `--wfan-pnl` é HERDADA
 *    por toda a árvore abaixo dele; se o script olhasse só o valor, cada
 *    painel viraria um container de painéis também, com faixas dentro de
 *    faixas.
 * 2. Que a faixa nasce uma vez por painel, e que um re-render do editor — que
 *    troca o conteúdo do painel e a leva junto — a traz de volta sem duplicar.
 * 3. Que um painel aberto continua aberto quando o container é refeito, e só
 *    muda quando o "Painel aberto de início" muda.
 * 4. Que desligar o toggle devolve o container exatamente como estava.
 *
 * @package WFAN
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = process.argv[ 2 ] || path.join( __dirname, '..', 'assets', 'js', 'frontend', 'panels.js' );

let falhas = 0;
let total = 0;

function ok( condicao, rotulo, detalhe ) {
	total++;

	if ( condicao ) {
		console.log( '  ok   ' + rotulo );
		return;
	}

	falhas++;
	console.log( '  FALHA ' + rotulo + ( undefined !== detalhe ? ' — ' + detalhe : '' ) );
}

// ------------------------------------------------------------- DOM de mentira

/** Seletor simples: `.a`, `h2`, `.a[data-id="x"]`, ou uma lista com vírgula. */
function casa( el, seletor ) {
	return seletor.split( ',' ).some( ( parte ) => {
		const s = parte.trim();
		const attr = s.match( /\[([a-z-]+)="([^"]*)"\]$/ );
		const base = attr ? s.slice( 0, attr.index ) : s;

		if ( attr && el.getAttribute( attr[ 1 ] ) !== attr[ 2 ] ) {
			return false;
		}

		if ( '.' === base[ 0 ] ) {
			return el.classSet.has( base.slice( 1 ) );
		}

		return el.tagName === base.toUpperCase();
	} );
}

class El {
	constructor( tag, o ) {
		o = o || {};
		this.tagName = tag.toUpperCase();
		this.nodeType = 1;
		this.children = [];
		this.parentElement = null;
		this.classSet = new Set( o.cls || [] );
		this.attrs = Object.assign( {}, o.attrs || {} );
		this.style = {
			setProperty: function ( n, v ) {
				this[ n ] = v;
			},
			removeProperty: function ( n ) {
				delete this[ n ];
			}
		};
		this.clientWidth = o.w || 0;
		this.vars = Object.assign( {}, o.vars || {} );
		this.ouvintes = {};
		this.textContent = o.text || '';
		this.position = o.position || 'relative';
		this.alturaFechado = o.h || 0;
		this.alturaConteudo = o.sh || 0;
		this.offsetWidth = 0;
		this.tabIndex = -1;
	}

	/** `auto` mede o conteúdo inteiro; qualquer outra altura, a barra. */
	get offsetHeight() {
		return 'auto' === this.style.height ? this.alturaConteudo : this.alturaFechado;
	}

	get classList() {
		const s = this.classSet;

		return {
			add: ( ...c ) => c.forEach( ( x ) => s.add( x ) ),
			remove: ( ...c ) => c.forEach( ( x ) => s.delete( x ) ),
			contains: ( c ) => s.has( c ),
			toggle: ( c, f ) => {
				const liga = undefined === f ? ! s.has( c ) : !! f;

				if ( liga ) {
					s.add( c );
				} else {
					s.delete( c );
				}

				return liga;
			}
		};
	}

	set className( v ) {
		this.classSet = new Set( String( v ).split( /\s+/ ).filter( Boolean ) );
	}

	/** Só o que o panels.js escreve: três <span class="…"> vazios. */
	set innerHTML( html ) {
		this.children = [];

		const re = /<span class="([^"]+)"( aria-hidden="true")?><\/span>/g;
		let m;

		while ( ( m = re.exec( html ) ) ) {
			this.appendChild( new El( 'span', { cls: m[ 1 ].split( ' ' ), attrs: m[ 2 ] ? { 'aria-hidden': 'true' } : {} } ) );
		}
	}

	appendChild( c ) {
		if ( c.parentElement ) {
			c.parentElement.removeChild( c );
		}

		c.parentElement = this;
		this.children.push( c );

		return c;
	}

	removeChild( c ) {
		this.children.splice( this.children.indexOf( c ), 1 );
		c.parentElement = null;

		return c;
	}

	contains( n ) {
		while ( n ) {
			if ( n === this ) {
				return true;
			}

			n = n.parentElement;
		}

		return false;
	}

	closest( sel ) {
		let n = this;

		while ( n ) {
			if ( casa( n, sel ) ) {
				return n;
			}

			n = n.parentElement;
		}

		return null;
	}

	querySelectorAll( sel ) {
		const achados = [];
		const andar = ( el ) => {
			el.children.forEach( ( c ) => {
				if ( casa( c, sel ) ) {
					achados.push( c );
				}

				andar( c );
			} );
		};

		andar( this );

		return achados;
	}

	querySelector( sel ) {
		return this.querySelectorAll( sel )[ 0 ] || null;
	}

	getAttribute( n ) {
		return Object.prototype.hasOwnProperty.call( this.attrs, n ) ? this.attrs[ n ] : null;
	}

	setAttribute( n, v ) {
		this.attrs[ n ] = String( v );
	}

	hasAttribute( n ) {
		return Object.prototype.hasOwnProperty.call( this.attrs, n );
	}

	addEventListener( tipo, fn ) {
		( this.ouvintes[ tipo ] = this.ouvintes[ tipo ] || [] ).push( fn );
	}

	removeEventListener( tipo, fn ) {
		this.ouvintes[ tipo ] = ( this.ouvintes[ tipo ] || [] ).filter( ( f ) => f !== fn );
	}

	/** Borbulha até a raiz, como o evento de verdade. */
	disparar( tipo, extra ) {
		const evento = Object.assign( { type: tipo, target: this, preventDefault: () => {} }, extra || {} );
		let n = this;

		while ( n ) {
			( n.ouvintes[ tipo ] || [] ).slice().forEach( ( fn ) => fn( evento ) );
			n = n.parentElement;
		}

		return evento;
	}

	focus() {
		El.foco = this;
	}
}

/**
 * Monta a página: um container de painéis com N filhos, cada um com um Título.
 *
 * `boxed` põe o `.e-con-inner` do conteúdo em caixa no meio, como o
 * Elementor faz; sem ele os filhos são diretos do container.
 */
function montar( opcoes ) {
	const o = Object.assign(
		{
			n: 3,
			boxed: true,
			vars: {},
			classeHost: true,
			largura: 1440,
			reduzir: false,
			cfg: {}
		},
		opcoes
	);

	const body = new El( 'body' );
	const host = new El( 'div', {
		cls: [ 'elementor-element', 'e-con' ].concat( o.classeHost ? [ 'wfan-pnl' ] : [] ),
		attrs: { 'data-id': 'host1' },
		vars: Object.assign( { '--wfan-pnl': '1' }, o.vars )
	} );
	const root = o.boxed ? host.appendChild( new El( 'div', { cls: [ 'e-con-inner' ] } ) ) : host;
	const itens = [];

	body.appendChild( host );

	for ( let i = 0; i < o.n; i++ ) {
		const item = new El( 'div', {
			cls: [ 'elementor-element', 'e-con' ],
			attrs: { 'data-id': 'item' + ( i + 1 ) },
			w: 834,
			h: 72,
			sh: 400
		} );
		const titulo = item.appendChild( new El( 'div', { cls: [ 'elementor-element', 'elementor-widget-heading' ], attrs: { 'data-id': 'titulo' + ( i + 1 ) } } ) );

		titulo.appendChild( new El( 'h3', { cls: [ 'elementor-heading-title' ], text: '  Título\n   ' + ( i + 1 ) + ' ' } ) );
		root.appendChild( item );
		itens.push( item );
	}

	// O contorno do editor é filho direto também, e não é painel.
	if ( o.editor ) {
		root.appendChild( new El( 'div', { cls: [ 'elementor-element-overlay' ] } ) );
	}

	const quadros = [];
	const timers = [];
	let relogio = 0;
	const prontos = [];
	const acoes = {};

	const cfg = Object.assign(
		{ reduced: true, offMobile: false, mobileBp: 767, debug: false, editor: !! o.editor, hostClass: 'wfan-pnl' },
		o.cfg
	);

	const sandbox = {
		Math: Math,
		parseInt: parseInt,
		parseFloat: parseFloat,
		isFinite: isFinite,
		String: String,
		console: console,
		innerWidth: o.largura,
		matchMedia: () => ( { matches: o.reduzir } ),
		getComputedStyle: ( el ) => ( {
			position: el.position,
			flexDirection: el.direcao || 'column',
			paddingLeft: '36px',
			paddingRight: '36px',
			getPropertyValue: ( nome ) => {
				// Herança: o valor sobe pela árvore até alguém o declarar.
				let n = el;

				while ( n ) {
					if ( Object.prototype.hasOwnProperty.call( n.vars, nome ) ) {
						return ' ' + n.vars[ nome ];
					}

					n = n.parentElement;
				}

				return '';
			}
		} ),
		requestAnimationFrame: ( fn ) => quadros.push( fn ),
		setTimeout: ( fn, ms ) => {
			timers.push( { fn: fn, em: relogio + ( ms || 0 ) } );
			return timers.length;
		},
		clearTimeout: ( id ) => {
			if ( timers[ id - 1 ] ) {
				timers[ id - 1 ].fn = null;
			}
		},
		addEventListener: () => {},
		document: {
			readyState: o.readyState || 'complete',
			addEventListener: ( tipo, fn ) => {
				if ( 'DOMContentLoaded' === tipo ) {
					prontos.push( fn );
				}
			},
			createElement: ( tag ) => new El( tag ),
			querySelectorAll: ( sel ) => body.querySelectorAll( sel ),
			querySelector: ( sel ) => body.querySelector( sel )
		},
		elementorFrontend: {
			hooks: {
				addAction: ( nome, fn ) => {
					acoes[ nome ] = fn;
				}
			}
		},
		wfanPanelsConfig: cfg
	};

	sandbox.window = sandbox;
	vm.createContext( sandbox );
	vm.runInContext( fs.readFileSync( SCRIPT, 'utf8' ), sandbox, { filename: 'panels.js' } );

	return {
		host: host,
		root: root,
		itens: itens,
		api: sandbox.wfanPanels,
		sandbox: sandbox,
		quadros: quadros,
		prontos: prontos,
		pronto: ( el ) => acoes[ 'frontend/element_ready/global' ]( [ el ] ),
		trilho: ( i ) => itens[ i ].children.filter( ( c ) => c.classSet.has( 'wfan-pnl-rail' ) ),
		abertos: () => itens.map( ( it, i ) => ( it.classSet.has( 'is-open' ) ? i : -1 ) ).filter( ( i ) => i > -1 ),
		avancar: ( ms ) => {
			relogio += ms;
			timers.forEach( ( t ) => {
				if ( t.fn && t.em <= relogio ) {
					const fn = t.fn;

					t.fn = null;
					fn();
				}
			} );
		},
		rodarQuadros: () => {
			while ( quadros.length ) {
				quadros.shift()( 0 );
			}
		}
	};
}

// ---------------------------------------------------------------------- testes

console.log( '\n1. Quem é o container de painéis' );

{
	const t = montar();

	ok( 1 === t.api.instances.length, 'um container, uma instância', t.api.instances.length );
	ok( t.host.classSet.has( 'wfan-pnl--ready' ), 'o container fica pronto' );
	ok( t.itens.every( ( it ) => ! it.__wfanPanels ), 'nenhum painel vira container de painéis, apesar da herança' );
	ok( t.root.classSet.has( 'wfan-pnl-root' ), 'a caixa do conteúdo (.e-con-inner) é a raiz' );
	ok( 'flex' === t.root.style.display, 'a raiz vira flex, mesmo quando o Elementor a desenhou em grade' );
	ok( 'row' === t.root.style.flexDirection && 'nowrap' === t.root.style.flexWrap, 'painéis lado a lado, sem quebrar linha' );
}

{
	const t = montar( { boxed: false } );

	ok( t.root === t.host && t.host.classSet.has( 'wfan-pnl-root' ), 'sem .e-con-inner, o próprio container é a raiz' );
	ok( 3 === t.api.instances[ 0 ].items.length, 'e os filhos diretos dele são os painéis' );
}

{
	const t = montar( { editor: true, classeHost: false } );

	ok( 1 === t.api.instances.length, 'no editor o container é achado pela custom property, sem classe' );
	ok( 3 === t.api.instances[ 0 ].items.length, 'o contorno do editor não conta como painel' );
}

console.log( '\n2. Estado inicial' );

{
	const t = montar( { vars: { '--wfan-pnl-first': '2' } } );

	ok( '[1]' === JSON.stringify( t.abertos() ), 'abre o painel pedido em "aberto de início"', JSON.stringify( t.abertos() ) );
	ok( '1 1 0%' === t.itens[ 1 ].style.flex, 'o aberto ocupa o que sobra', t.itens[ 1 ].style.flex );
	ok(
		'0 0 var(--wfan-pnl-min, 110px)' === t.itens[ 0 ].style.flex,
		'o fechado tem a largura do controle, lida pelo CSS',
		t.itens[ 0 ].style.flex
	);
	ok( 'auto' === t.itens[ 0 ].style.width && '0' === t.itens[ 0 ].style.minWidth, 'a largura do Elementor é neutralizada' );
	ok( t.host.classSet.has( 'wfan-pnl--init' ), 'o primeiro desenho sai sem transição' );
	t.rodarQuadros();
	ok( ! t.host.classSet.has( 'wfan-pnl--init' ), 'e a transição volta no quadro seguinte' );
}

{
	const t = montar( { vars: { '--wfan-pnl-first': '9' } } );

	ok( '[2]' === JSON.stringify( t.abertos() ), 'pedido maior que o número de painéis abre o último', JSON.stringify( t.abertos() ) );
}

{
	const t = montar( { vars: { '--wfan-pnl-num': '1' } } );
	const sem = montar();

	ok( ! t.host.classSet.has( 'wfan-pnl--nonum' ), 'número ligado: aparece' );
	ok( sem.host.classSet.has( 'wfan-pnl--nonum' ), 'sem a variável o número some (switcher desligado não escreve CSS)' );
}

{
	const t = montar();

	ok( '762px' === t.host.style[ '--wfan-pnl-cw' ], 'mede a largura do conteúdo do painel aberto, sem o padding', t.host.style[ '--wfan-pnl-cw' ] );
	ok( t.itens.every( ( it ) => ! it.classSet.has( 'wfan-pnl-item--row' ) ), 'painel em coluna tem a largura congelada' );

	const empilhado = montar( { largura: 390 } );

	ok( undefined === empilhado.host.style[ '--wfan-pnl-cw' ], 'empilhados, não há largura a congelar' );
}

{
	const t = montar();

	t.itens[ 1 ].direcao = 'row';
	t.api.sync( t.host );
	ok( t.itens[ 1 ].classSet.has( 'wfan-pnl-item--row' ), 'painel com os filhos lado a lado fica de fora: congelar cada filho empurraria os outros' );
}

console.log( '\n3. A faixa' );

{
	const t = montar();
	const faixa = t.trilho( 0 )[ 0 ];

	ok( t.itens.every( ( it, i ) => 1 === t.trilho( i ).length ), 'uma faixa por painel' );
	ok( 'BUTTON' === faixa.tagName && 'button' === faixa.type, 'a faixa é um botão de verdade' );
	ok( '01' === faixa.children[ 0 ].textContent, 'número com dois dígitos', faixa.children[ 0 ].textContent );
	ok( 'Título 1' === faixa.children[ 1 ].textContent, 'título vem do primeiro Título, sem espaços sobrando', JSON.stringify( faixa.children[ 1 ].textContent ) );
	ok( faixa.children[ 2 ].classSet.has( 'wfan-pnl-plus' ) && 'true' === faixa.children[ 2 ].getAttribute( 'aria-hidden' ), 'o + é decorativo: o leitor de tela lê o número e o título' );
	ok( 'true' === t.trilho( 0 )[ 0 ].getAttribute( 'aria-expanded' ), 'aberto: aria-expanded verdadeiro' );
	ok( 'false' === t.trilho( 1 )[ 0 ].getAttribute( 'aria-expanded' ), 'fechado: aria-expanded falso' );

	t.itens[ 2 ].setAttribute( 'data-wfan-label', 'Rótulo próprio' );
	t.api.sync( t.host );
	ok( 'Rótulo próprio' === t.trilho( 2 )[ 0 ].children[ 1 ].textContent, 'data-wfan-label passa na frente do Título' );
	ok( t.itens.every( ( it, i ) => 1 === t.trilho( i ).length ), 'refazer não duplica a faixa' );
}

console.log( '\n4. Abrir e fechar' );

{
	const t = montar();

	t.rodarQuadros();
	t.trilho( 2 )[ 0 ].children[ 1 ].disparar( 'click', { detail: 1 } );
	ok( '[2]' === JSON.stringify( t.abertos() ), 'clicar na faixa (até no título dentro dela) abre o painel', JSON.stringify( t.abertos() ) );
	ok( '0 0 var(--wfan-pnl-min, 110px)' === t.itens[ 0 ].style.flex, 'e fecha o anterior' );
	ok( ! t.host.classSet.has( 'wfan-pnl--init' ), 'com transição' );
	ok( El.foco !== t.itens[ 2 ], 'clique de mouse não mexe no foco' );

	t.trilho( 1 )[ 0 ].disparar( 'click', { detail: 0 } );
	ok( El.foco === t.itens[ 1 ] && '-1' === t.itens[ 1 ].getAttribute( 'tabindex' ), 'aberto pelo teclado, o foco vai para o painel' );

	t.itens[ 0 ].children[ 0 ].disparar( 'click', { detail: 1 } );
	ok( '[1]' === JSON.stringify( t.abertos() ), 'clique fora da faixa não abre nada' );
}

{
	const t = montar( { vars: { '--wfan-pnl-tr': 'hover' } } );

	t.itens[ 2 ].children[ 0 ].disparar( 'pointerover', { pointerType: 'mouse' } );
	ok( '[0]' === JSON.stringify( t.abertos() ), 'hover espera a intenção antes de abrir' );
	t.avancar( 100 );
	ok( '[2]' === JSON.stringify( t.abertos() ), 'e abre o painel sob o mouse', JSON.stringify( t.abertos() ) );

	t.itens[ 1 ].disparar( 'pointerover', { pointerType: 'mouse' } );
	t.itens[ 1 ].disparar( 'pointerout', { relatedTarget: null } );
	t.avancar( 100 );
	ok( '[2]' === JSON.stringify( t.abertos() ), 'passar de raspão e sair não abre' );

	t.itens[ 0 ].disparar( 'pointerover', { pointerType: 'touch' } );
	t.avancar( 100 );
	ok( '[2]' === JSON.stringify( t.abertos() ), 'toque não conta como hover' );
}

{
	const t = montar();

	t.itens[ 2 ].disparar( 'pointerover', { pointerType: 'mouse' } );
	t.avancar( 100 );
	ok( '[0]' === JSON.stringify( t.abertos() ), 'no modo clique, hover não abre' );
}

console.log( '\n5. Empilhados' );

{
	const t = montar( { largura: 390 } );

	ok( t.host.classSet.has( 'wfan-pnl--stack' ), 'abaixo do ponto de quebra, empilha' );
	ok( 'column' === t.root.style.flexDirection, 'um embaixo do outro' );
	ok( 'var(--wfan-pnl-bar, 72px)' === t.itens[ 1 ].style.height, 'o fechado tem a altura da barra', t.itens[ 1 ].style.height );
	ok( 'auto' === t.itens[ 0 ].style.height, 'o aberto tem a altura do conteúdo' );

	t.trilho( 1 )[ 0 ].disparar( 'click', { detail: 1 } );
	ok( '400px' === t.itens[ 1 ].style.height, 'abrindo, vai até a medida do conteúdo (medida com auto, não scrollHeight)', t.itens[ 1 ].style.height );
	t.avancar( 800 );
	ok( 'auto' === t.itens[ 1 ].style.height, 'e volta a auto no fim, para o texto poder quebrar diferente' );
	ok( 'var(--wfan-pnl-bar, 72px)' === t.itens[ 0 ].style.height, 'o anterior volta a ser barra' );

}

{
	const t = montar( { largura: 900 } );
	const tablet = montar( { largura: 900, vars: { '--wfan-pnl-bp': '1024' } } );
	const nunca = montar( { largura: 390, vars: { '--wfan-pnl-bp': '0' } } );

	ok( ! t.host.classSet.has( 'wfan-pnl--stack' ), 'acima do ponto de quebra padrão (767), lado a lado' );
	ok( tablet.host.classSet.has( 'wfan-pnl--stack' ), '"no tablet e no celular" empilha em 900 px' );
	ok( ! nunca.host.classSet.has( 'wfan-pnl--stack' ), '"nunca" não empilha nem em 390 px' );
}

{
	const t = montar( { largura: 390, vars: { '--wfan-pnl-tr': 'hover' } } );

	t.itens[ 2 ].disparar( 'pointerover', { pointerType: 'mouse' } );
	t.avancar( 100 );
	ok( '[0]' === JSON.stringify( t.abertos() ), 'empilhado, é sempre o clique' );
}

console.log( '\n6. Movimento reduzido' );

{
	const t = montar( { reduzir: true } );
	const ignora = montar( { reduzir: true, cfg: { reduced: false } } );
	const celular = montar( { largura: 390, cfg: { offMobile: true } } );

	ok( t.host.classSet.has( 'wfan-pnl--still' ), 'prefers-reduced-motion desliga as transições' );
	ok( ! ignora.host.classSet.has( 'wfan-pnl--still' ), 'a não ser que a opção de respeitá-lo esteja desligada' );
	ok( celular.host.classSet.has( 'wfan-pnl--still' ), '"desligar no celular" também' );

	t.trilho( 1 )[ 0 ].disparar( 'click', { detail: 1 } );
	ok( '[1]' === JSON.stringify( t.abertos() ), 'e os painéis continuam abrindo' );
}

console.log( '\n7. Editor' );

{
	const t = montar( { editor: true } );

	t.trilho( 2 )[ 0 ].disparar( 'click', { detail: 1 } );

	// Re-render de um painel: o Elementor troca o conteúdo e a faixa vai junto.
	t.itens[ 1 ].children = t.itens[ 1 ].children.filter( ( c ) => ! c.classSet.has( 'wfan-pnl-rail' ) );
	t.pronto( t.itens[ 1 ].children[ 0 ] );
	ok( 1 === t.trilho( 1 ).length, 're-render de um painel traz a faixa de volta, pelo ancestral' );
	ok( '[2]' === JSON.stringify( t.abertos() ), 'e o painel aberto continua o mesmo' );

	t.host.vars[ '--wfan-pnl-first' ] = '2';
	t.api.syncId( 'host1' );
	ok( '[1]' === JSON.stringify( t.abertos() ), 'mudar o "aberto de início" abre o novo, na hora' );

	t.api.syncId( 'host1' );
	t.trilho( 0 )[ 0 ].disparar( 'click', { detail: 1 } );
	t.api.syncId( 'host1' );
	ok( '[0]' === JSON.stringify( t.abertos() ), 'reler sem mudar o controle não fecha o que o usuário abriu' );

	t.api.reveal( 'titulo3' );
	ok( '[2]' === JSON.stringify( t.abertos() ), 'escolher para edição um elemento de painel fechado abre o painel' );

	t.api.reveal( 'host1' );
	ok( '[2]' === JSON.stringify( t.abertos() ), 'escolher o próprio container não muda nada' );

	// Toggle desligado: o Elementor reescreve o CSS sem a variável.
	delete t.host.vars[ '--wfan-pnl' ];
	t.api.syncId( 'host1' );
	ok( 0 === t.api.instances.length, 'desligar o toggle desmonta' );
	ok( t.itens.every( ( it, i ) => 0 === t.trilho( i ).length ), 'as faixas saem' );
	ok(
		t.itens.every( ( it ) => '' === it.style.flex && '' === it.style.width && '' === it.style.height && ! it.classSet.has( 'wfan-pnl-item' ) ),
		'e cada painel volta a ser o container que o Elementor desenhou'
	);
	ok( '' === t.root.style.display && '' === t.root.style.flexDirection && ! t.host.classSet.has( 'wfan-pnl--ready' ), 'a raiz também' );
	ok( undefined === t.host.style[ '--wfan-pnl-cw' ], 'e a largura medida sai do container' );

	t.host.vars[ '--wfan-pnl' ] = '1';
	t.api.syncId( 'host1' );
	ok( 1 === t.api.instances.length && t.itens.every( ( it, i ) => 1 === t.trilho( i ).length ), 'e religar monta de novo, uma faixa por painel' );
}

{
	// Carga inicial do preview: a classe do PHP está no wrapper, mas o toggle
	// já foi desligado e o CSS não tem mais a variável.
	const t = montar( { editor: true, vars: { '--wfan-pnl': '0' } } );

	ok( 0 === t.api.instances.length, 'no editor a classe velha não liga nada: quem manda é o CSS' );
}

{
	const t = montar( { vars: { '--wfan-pnl': '0' } } );

	ok( 1 === t.api.instances.length, 'no front-end a classe do PHP basta' );
}

console.log( '\n8. Boot' );

{
	const t = montar( { readyState: 'loading' } );

	ok( 1 === t.api.instances.length, 'saindo no rodapé, monta antes do DOMContentLoaded' );
	t.prontos.forEach( ( fn ) => fn() );
	ok( 1 === t.api.instances.length && t.itens.every( ( it, i ) => 1 === t.trilho( i ).length ), 'e o boot refaz sem duplicar nada' );
}

console.log( '\n' + ( falhas ? falhas + ' de ' + total + ' verificações FALHARAM' : 'todas as ' + total + ' verificações passaram' ) );
process.exit( falhas ? 1 : 0 );
