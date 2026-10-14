/**
 * Teste do gatilho de tela: `node tests/trigger.js`.
 *
 * Carrega o core.js de verdade num DOM de mentira e dirige o
 * IntersectionObserver à mão, porque é exatamente aí que mora a classe de
 * defeito que o smoke test não alcança: quantas vezes a animação toca.
 *
 * O caso que originou este arquivo: com "Animar só uma vez" desligado, o
 * efeito repetia sem parar — inclusive com a página parada. A animação mexe a
 * caixa do elemento (um zoom de 0.85, um deslize), a razão de visibilidade
 * oscila em torno do limiar e cada cruzamento reproduzia de novo. Medido em
 * produção, em /barbas-strategy/: 20 reproduções onde cabiam 2.
 *
 * @package WFAN
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const CORE = process.argv[ 2 ] || path.join( __dirname, '..', 'assets', 'js', 'frontend', 'core.js' );

let falhas = 0;
let total = 0;

function ok( condicao, rotulo, detalhe ) {
	total++;

	if ( condicao ) {
		console.log( '  ok   ' + rotulo );
		return;
	}

	falhas++;
	console.log( '  FALHA ' + rotulo + ( detalhe ? ' — ' + detalhe : '' ) );
}

/**
 * Sobe o core.js num ambiente controlado e devolve o controle do observador.
 *
 * @param {Object} tr Bloco de gatilho do contrato (t, vp, once…).
 */
function montar( tr ) {
	const spec = {
		v: 1,
		p: 'zoom-in',
		eng: 'css',
		tr: Object.assign( { t: 'scroll-in', vp: 20, once: 0, start: 'enter', end: 'out', pct: 100, pin: 0 }, tr ),
		d: 300,
		dl: 0,
		e: 'power2.out',
		scale: 0.85
	};

	const classes = new Set( [ 'wfan', 'wfan-pending' ] );
	const el = {
		offsetHeight: 300,
		textContent: 'texto',
		classList: {
			add: ( c ) => classes.add( c ),
			remove: ( c ) => classes.delete( c ),
			contains: ( c ) => classes.has( c )
		},
		getAttribute: ( n ) => ( 'data-wfan' === n ? JSON.stringify( spec ) : null ),
		setAttribute: () => {},
		addEventListener: () => {},
		querySelectorAll: () => []
	};

	let callback = null;
	let tocou = 0;
	const timers = [];

	const sandbox = {
		console: { log: () => {} },
		NodeFilter: { SHOW_TEXT: 4 },
		setTimeout: ( fn, ms ) => {
			const t = { fn, at: ms };
			timers.push( t );
			return t;
		},
		clearTimeout: ( t ) => {
			const i = timers.indexOf( t );

			if ( i > -1 ) {
				timers.splice( i, 1 );
			}
		},
		requestAnimationFrame: ( fn ) => fn( 0 ),
		matchMedia: () => ( { matches: false } ),
		innerWidth: 1440,
		innerHeight: 900,
		IntersectionObserver: function ( cb ) {
			callback = cb;
			this.observe = () => {};
			this.unobserve = () => {
				callback = null;
			};
			this.disconnect = () => {};
		},
		document: {
			readyState: 'complete',
			querySelectorAll: () => [ el ],
			addEventListener: () => {},
			createElement: () => ( { style: {}, classList: { add: () => {} }, appendChild: () => {} } ),
			createTreeWalker: () => ( { nextNode: () => null } ),
			contains: () => true
		},
		wfanConfig: {
			reduced: false,
			offMobile: false,
			mobileBp: 767,
			debug: false,
			editor: false,
			bezier: {},
			failsafe: 3000
		}
	};

	sandbox.window = sandbox;
	vm.createContext( sandbox );
	vm.runInContext( fs.readFileSync( CORE, 'utf8' ), sandbox, { filename: 'core.js' } );

	sandbox.window.wfan.register( 'css', {
		play: () => {
			tocou++;
		},
		reset: () => {}
	} );

	sandbox.window.wfan.refresh( sandbox.document );

	return {
		// Uma leitura do observador, com a razão de visibilidade informada.
		ver: ( ratio ) => {
			if ( callback ) {
				callback( [ { target: el, isIntersecting: ratio > 0, intersectionRatio: ratio } ] );
			}
		},
		// Deixa passar o tempo da animação.
		esperar: () => {
			timers.splice( 0, timers.length ).forEach( ( t ) => t.fn() );
		},
		tocou: () => tocou
	};
}

console.log( 'WooFlow Animations — gatilho de tela\n' );

console.log( 'Ao entrar na tela, animando sempre (once = 0)' );

const sempre = montar( { once: 0 } );

sempre.ver( 0.5 );
ok( 1 === sempre.tocou(), 'entrar na tela reproduz uma vez', 'tocou ' + sempre.tocou() + 'x' );

for ( let i = 0; i < 12; i++ ) {
	sempre.ver( 0.19 );
	sempre.ver( 0.21 );
}

ok( 1 === sempre.tocou(), 'oscilar no limiar não reproduz de novo', 'tocou ' + sempre.tocou() + 'x' );

[ 0.4, 0.6, 0.8, 1, 0.8, 0.6 ].forEach( sempre.ver );
ok( 1 === sempre.tocou(), 'rolar com o elemento sempre visível não reproduz', 'tocou ' + sempre.tocou() + 'x' );

sempre.esperar();
sempre.ver( 0 );
sempre.ver( 0.5 );
ok( 2 === sempre.tocou(), 'sair da tela e voltar reproduz outra vez', 'tocou ' + sempre.tocou() + 'x' );

console.log( '\nAo entrar na tela, só uma vez (once = 1)' );

const umaVez = montar( { once: 1 } );

umaVez.ver( 0.5 );
umaVez.esperar();
umaVez.ver( 0 );
umaVez.ver( 0.6 );
umaVez.ver( 0 );
umaVez.ver( 0.9 );

ok( 1 === umaVez.tocou(), 'entrar, sair e voltar reproduz uma vez só', 'tocou ' + umaVez.tocou() + 'x' );

console.log( '\nAo sair da tela' );

const saindo = montar( { t: 'scroll-out', once: 0 } );

saindo.ver( 0.5 );
ok( 0 === saindo.tocou(), 'entrar na tela não dispara o gatilho de saída' );

saindo.ver( 0 );
ok( 1 === saindo.tocou(), 'sair da tela reproduz', 'tocou ' + saindo.tocou() + 'x' );

saindo.esperar();
saindo.ver( 0.5 );
saindo.ver( 0 );
ok( 2 === saindo.tocou(), 'voltar e sair de novo reproduz outra vez', 'tocou ' + saindo.tocou() + 'x' );

console.log( '\n' + '-'.repeat( 48 ) );
console.log( falhas ? falhas + ' de ' + total + ' verificações falharam' : 'todas as ' + total + ' verificações passaram' );

process.exit( falhas ? 1 : 0 );
