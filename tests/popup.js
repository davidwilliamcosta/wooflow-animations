/**
 * Teste do popup circular: `node tests/popup.js`.
 *
 * Carrega o popup.js de verdade num DOM de mentira e dirige o clique, o
 * evento do Elementor e os quadros à mão. É o único lugar que confere o que
 * nem o smoke test nem o olho alcançam: de ONDE sai a origem do círculo, e
 * QUANDO a caixa é medida.
 *
 * Os defeitos que este arquivo existe para pegar:
 *
 * 1. A origem é o último clique, lido na fase de captura. Na borbulha o ponto
 *    chegaria depois de o Elementor já ter aberto o popup, e o círculo nasceria
 *    no lugar da abertura anterior — um popup atrasado em relação ao dedo.
 * 2. A medida espera DOIS quadros. Na primeira abertura o Elementor ainda está
 *    posicionando o popup quando o evento chega: medir no quadro seguinte pega
 *    a caixa do layout em construção, e o círculo nasce fora do ícone — só na
 *    primeira vez, que é o que faz o defeito parecer fantasma.
 * 3. Clique de teclado chega com coordenada 0,0. Não é um ponto na tela: usá-lo
 *    abriria o círculo no canto superior esquerdo em vez de na reserva.
 *
 * @package WFAN
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = process.argv[ 2 ] || path.join( __dirname, '..', 'assets', 'js', 'frontend', 'popup.js' );

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
 * Sobe o popup.js num ambiente controlado.
 *
 * `caixaTardia` finge a primeira abertura do Elementor: a caixa só tem a
 * posição final a partir do segundo quadro.
 */
function montar( opcoes ) {
	const o = Object.assign(
		{ reduzir: false, caixaTardia: false, itens: 0, cfg: {} },
		opcoes
	);

	const cfg = Object.assign(
		{ ids: [], open: 850, offMobile: false, mobileBp: 767, reduced: true, debug: false },
		o.cfg
	);

	const classesModal = new Set();
	const propsModal = {};
	const propsItens = [];
	const quadros = [];
	const capturas = [];
	const borbulhas = [];
	const eventos = {};

	let quadro = 0;

	const itens = [];

	for ( let i = 0; i < o.itens; i++ ) {
		const p = {};

		propsItens.push( p );
		itens.push( { style: { setProperty: ( n, v ) => { p[ n ] = v; } } } );
	}

	const caixa = {
		className: '',
		style: {},
		offsetWidth: 600,
		// A primeira abertura do Elementor: a caixa só se acomoda no 2º quadro.
		getBoundingClientRect: () => (
			o.caixaTardia && quadro < 2
				? { left: 0, top: 0, width: 0, height: 0 }
				: { left: 200, top: 100, width: 600, height: 400 }
		),
		querySelectorAll: () => itens,
		classList: { add: () => {}, remove: () => {}, contains: () => false }
	};

	const modal = {
		classList: {
			add: ( c ) => classesModal.add( c ),
			remove: ( c ) => classesModal.delete( c ),
			contains: ( c ) => classesModal.has( c )
		},
		style: { setProperty: ( n, v ) => { propsModal[ n ] = v; } },
		querySelector: ( sel ) => ( '.dialog-widget-content' === sel ? caixa : null )
	};

	const sandbox = {
		Math: Math,
		Array: Array,
		parseInt: parseInt,
		console: { log: () => {} },
		innerWidth: 1440,
		innerHeight: 900,
		matchMedia: () => ( { matches: o.reduzir } ),
		requestAnimationFrame: ( fn ) => {
			quadros.push( fn );
			return quadros.length;
		},
		MutationObserver: function ( cb ) {
			this.cb = cb;
			this.observe = () => {};
			this.disconnect = () => {};
			sandbox.__observer = this;
		},
		document: {
			readyState: 'complete',
			addEventListener: ( tipo, fn, captura ) => {
				if ( 'click' !== tipo ) {
					return;
				}

				( captura ? capturas : borbulhas ).push( fn );
			},
			getElementById: ( id ) => ( 'elementor-popup-modal-482' === id ? modal : null )
		},
		jQuery: ( alvo ) => {
			void alvo;

			const api = {
				on: ( nome, fn ) => {
					eventos[ nome ] = fn;
					return api;
				}
			};

			return api;
		},
		wfanPopup: cfg
	};

	sandbox.window = sandbox;
	vm.createContext( sandbox );
	vm.runInContext( fs.readFileSync( SCRIPT, 'utf8' ), sandbox, { filename: 'popup.js' } );

	return {
		clicar: ( x, y ) => {
			capturas.forEach( ( fn ) => fn( { clientX: x, clientY: y } ) );
		},
		borbulhas: () => borbulhas.length,
		abrir: ( id ) => {
			if ( eventos[ 'elementor/popup/show' ] ) {
				eventos[ 'elementor/popup/show' ]( {}, id );
			}
		},
		fechar: ( id ) => {
			if ( eventos[ 'elementor/popup/hide' ] ) {
				eventos[ 'elementor/popup/hide' ]( {}, id );
			}
		},
		// Roda um quadro por vez: é a granularidade que o defeito 2 exige.
		quadro: () => {
			quadro++;

			const fila = quadros.splice( 0, quadros.length );

			fila.forEach( ( fn ) => fn( quadro * 16 ) );

			return fila.length;
		},
		quadros: () => {
			let n = 0;

			while ( quadros.length && n < 20 ) {
				quadro++;
				quadros.splice( 0, quadros.length ).forEach( ( fn ) => fn( quadro * 16 ) );
				n++;
			}

			return n;
		},
		aberto: () => classesModal.has( 'wfan-pop-open' ),
		raiz: () => classesModal.has( 'wfan-pop' ),
		prop: ( n ) => propsModal[ n ],
		item: ( i ) => propsItens[ i ],
		caixa: caixa,
		fecharPeloElementor: () => {
			caixa.className = 'dialog-widget-content animated fadeOut';

			if ( sandbox.__observer ) {
				sandbox.__observer.cb();
			}
		},
		raioDe: ( box, x, y ) => sandbox.window.wfanPopupApi.radiusFor( box, x, y ),
		ponto: () => sandbox.window.wfanPopupApi.point()
	};
}

console.log( 'WooFlow Animations — popup circular\n' );

console.log( 'De onde sai a origem do círculo' );

const p = montar( {} );

ok( 0 === p.borbulhas(), 'o clique NÃO é escutado na borbulha: ali o ponto chega depois da abertura' );

p.clicar( 1300, 60 );

const ponto = p.ponto();

ok( ponto && 1300 === ponto.x && 60 === ponto.y, 'o ponto do clique é guardado na captura', JSON.stringify( ponto ) );

p.abrir( 482 );

ok( p.raiz(), 'o modal recebe a classe raiz na hora' );
ok( ! p.aberto(), 'mas não abre antes de a caixa ser medida' );

p.quadros();

ok( p.aberto(), 'depois dos quadros, abre' );

// A caixa está em (200,100): a origem relativa é 1300-200 e 60-100.
ok( '1100px' === p.prop( '--wfan-pop-x' ), 'a origem é relativa à caixa, não à tela', String( p.prop( '--wfan-pop-x' ) ) );
ok( '-40px' === p.prop( '--wfan-pop-y' ), 'inclusive quando o ícone está acima da caixa', String( p.prop( '--wfan-pop-y' ) ) );
ok( '1300px' === p.prop( '--wfan-pop-vx' ), 'o degradê da sobreposição parte do ponto na tela' );
ok( '60px' === p.prop( '--wfan-pop-vy' ), 'nas duas coordenadas' );

console.log( '\nClique de teclado' );

const teclado = montar( {} );

teclado.clicar( 400, 300 );
teclado.clicar( 0, 0 );

ok( null === teclado.ponto(), 'coordenada 0,0 não é um ponto na tela: o ponto anterior é descartado' );

teclado.abrir( 482 );
teclado.quadros();

// A reserva é o canto superior direito: 1440-40 = 1400, 40.
ok( '1400px' === teclado.prop( '--wfan-pop-vx' ), 'e a reserva é o canto superior direito', String( teclado.prop( '--wfan-pop-vx' ) ) );

console.log( '\nQuando a caixa é medida (a primeira abertura)' );

const tardio = montar( { caixaTardia: true } );

tardio.clicar( 1300, 60 );
tardio.abrir( 482 );

tardio.quadro();

ok( ! tardio.aberto(), 'um quadro não basta: a caixa ainda está se acomodando' );
ok( undefined === tardio.prop( '--wfan-pop-x' ), 'e nada é medido ainda' );

tardio.quadros();

ok( tardio.aberto(), 'no segundo quadro a medida sai e o círculo abre' );
ok( '1100px' === tardio.prop( '--wfan-pop-x' ), 'com a caixa já no lugar certo', String( tardio.prop( '--wfan-pop-x' ) ) );

console.log( '\nO raio' );

const r = montar( {} );
const caixa = { width: 600, height: 400 };

// Origem no canto superior esquerdo: o canto oposto fica a hypot(600,400).
ok(
	Math.abs( r.raioDe( caixa, 0, 0 ) - ( Math.hypot( 600, 400 ) + 80 ) ) < 0.01,
	'o raio cobre o canto mais distante, com folga para a sombra',
	String( r.raioDe( caixa, 0, 0 ) )
);

// Origem fora da caixa: o raio tem de crescer, não encolher.
ok(
	r.raioDe( caixa, -400, -300 ) > r.raioDe( caixa, 300, 200 ),
	'origem fora da caixa pede raio maior que origem no centro'
);

console.log( '\nA transição durante a medida' );

const t = montar( {} );

t.clicar( 1300, 60 );
t.abrir( 482 );
t.quadros();

ok(
	'' === t.caixa.style.transition,
	'a transição é devolvida depois de posicionar a origem',
	JSON.stringify( t.caixa.style.transition )
);

console.log( '\nFechamento' );

const f = montar( {} );

f.clicar( 1300, 60 );
f.abrir( 482 );
f.quadros();

ok( f.aberto(), 'aberto' );

f.fecharPeloElementor();

ok( ! f.aberto(), 'a classe de saída do Elementor reverte o círculo' );

f.abrir( 482 );
f.quadros();
f.fechar( 482 );

ok( ! f.aberto(), 'e o evento de fechamento também' );

console.log( '\nRestrição por id' );

const so482 = montar( { cfg: { ids: [ 482 ] } } );

so482.clicar( 1300, 60 );
so482.abrir( 999 );
so482.quadros();

ok( ! so482.raiz(), 'popup fora da lista não é tocado' );

so482.abrir( 482 );
so482.quadros();

ok( so482.aberto(), 'e o da lista é' );

console.log( '\nItens escalonados' );

const itens = montar( { itens: 3 } );

itens.clicar( 1300, 60 );
itens.abrir( 482 );
itens.quadros();

ok( 0 === itens.item( 0 )[ '--wfan-pop-i' ], 'cada item recebe o índice da cascata', String( itens.item( 0 )[ '--wfan-pop-i' ] ) );
ok( 2 === itens.item( 2 )[ '--wfan-pop-i' ], 'na ordem em que estão no popup' );

console.log( '\nMovimento reduzido' );

const reduz = montar( { reduzir: true } );

reduz.clicar( 1300, 60 );
reduz.abrir( 482 );

ok( reduz.aberto(), 'abre na hora, sem esperar quadro nenhum' );
ok( undefined === reduz.prop( '--wfan-pop-r' ), 'e sem círculo: o CSS cai no fade' );

const reduzDesligado = montar( { reduzir: true, cfg: { reduced: false } } );

reduzDesligado.clicar( 1300, 60 );
reduzDesligado.abrir( 482 );

ok( ! reduzDesligado.aberto(), 'com o ajuste desligado, o círculo continua' );

console.log( '\n------------------------------------------------' );

if ( falhas ) {
	console.log( falhas + ' de ' + total + ' verificações falharam' );
	process.exit( 1 );
}

console.log( 'todas as ' + total + ' verificações passaram' );
