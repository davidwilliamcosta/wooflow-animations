/**
 * Teste do botão "voltar ao topo": `node tests/backtotop.js`.
 *
 * Carrega o backtotop.js de verdade num DOM de mentira e dirige o scroll e o
 * clique à mão, porque é aí que mora a classe de defeito que nem o smoke test
 * nem o olho alcançam: *quando* o script acha o botão, e *por onde* a subida
 * acontece.
 *
 * O caso que originou este arquivo: o botão nascia morto em todo site. O
 * <button> é impresso no `wp_footer` com prioridade 100 e os scripts de rodapé
 * do WordPress saem na 20 — o script rodava antes de o botão existir, o
 * querySelector do topo do arquivo devolvia null e nada era amarrado. O HTML
 * saía perfeito, com markup, CSS e configuração no lugar; só não acontecia
 * nada. Medido em teste.local: 4329 bytes de HTML entre o <script> e o
 * <button>.
 *
 * @package WFAN
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = process.argv[ 2 ] || path.join( __dirname, '..', 'assets', 'js', 'frontend', 'backtotop.js' );

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
 * Sobe o backtotop.js num ambiente controlado.
 *
 * @param {Object} opcoes readyState inicial, config, se há Lenis, se o
 *                        aparelho pede menos movimento, e se o botão já está
 *                        no DOM quando o script roda.
 */
function montar( opcoes ) {
	const o = Object.assign(
		{
			readyState: 'loading',
			botaoNoDom: false,
			lenis: false,
			reduzir: false,
			cfg: {}
		},
		opcoes
	);

	const cfg = Object.assign(
		{ threshold: 400, duration: 800, ring: true, reduced: true, debug: false },
		o.cfg
	);

	const classes = new Set( [ 'wfan-btt', 'wfan-btt--right', 'wfan-btt--fade' ] );
	const mexidas = { add: 0, remove: 0 };
	const props = {};
	const cliques = [];

	const botao = {
		classList: {
			add: ( c ) => {
				mexidas.add++;
				classes.add( c );
			},
			remove: ( c ) => {
				mexidas.remove++;
				classes.delete( c );
			},
			contains: ( c ) => classes.has( c )
		},
		style: { setProperty: ( n, v ) => { props[ n ] = v; } },
		addEventListener: ( tipo, fn ) => {
			if ( 'click' === tipo ) {
				cliques.push( fn );
			}
		},
		hasAttribute: () => true,
		setAttribute: () => {},
		focus: () => { foco++; }
	};

	let noDom = o.botaoNoDom;
	let foco = 0;
	let y = 0;

	const prontos = [];
	const ouvintes = {};
	const quadros = [];
	const saltos = [];
	const lenisChamadas = [];

	const sandbox = {
		Math: Math,
		console: { log: () => {} },
		pageYOffset: 0,
		innerHeight: 900,
		matchMedia: () => ( { matches: o.reduzir } ),
		requestAnimationFrame: ( fn ) => {
			quadros.push( fn );
			return quadros.length;
		},
		cancelAnimationFrame: () => {},
		addEventListener: ( tipo, fn ) => {
			ouvintes[ tipo ] = ( ouvintes[ tipo ] || [] ).concat( fn );
		},
		removeEventListener: () => {},
		scrollTo: ( arg ) => {
			saltos.push( 'object' === typeof arg ? arg.top : arg );
		},
		document: {
			readyState: o.readyState,
			documentElement: { scrollTop: 0, scrollHeight: 5000 },
			body: botao,
			addEventListener: ( tipo, fn ) => {
				if ( 'DOMContentLoaded' === tipo ) {
					prontos.push( fn );
				}
			},
			querySelector: ( sel ) => {
				if ( '.wfan-btt' === sel ) {
					return noDom ? botao : null;
				}

				// O alvo do foco: o começo do conteúdo.
				return botao;
			}
		},
		wfanBackToTop: cfg
	};

	if ( o.lenis ) {
		sandbox.wfanLenisInstance = {
			scrollTo: ( alvo, opts ) => {
				lenisChamadas.push( { alvo, opts } );
			}
		};
	}

	sandbox.window = sandbox;
	vm.createContext( sandbox );
	vm.runInContext( fs.readFileSync( SCRIPT, 'utf8' ), sandbox, { filename: 'backtotop.js' } );

	function rodarQuadros( limite ) {
		let n = 0;

		while ( quadros.length && n < ( limite || 500 ) ) {
			quadros.shift()( n * 100 );
			n++;
		}

		return n;
	}

	return {
		// O botão entra no DOM depois do script, como no rodapé de verdade.
		imprimirBotao: () => { noDom = true; },
		// O navegador termina de montar o documento.
		domPronto: () => { prontos.splice( 0, prontos.length ).forEach( ( fn ) => fn() ); },
		rolarPara: ( v ) => {
			y = v;
			sandbox.pageYOffset = v;
			sandbox.document.documentElement.scrollTop = v;
			( ouvintes.scroll || [] ).forEach( ( fn ) => fn() );
			rodarQuadros( 1 );
		},
		clicar: () => {
			cliques.forEach( ( fn ) => fn() );
			rodarQuadros();
		},
		visivel: () => classes.has( 'is-visible' ),
		mexidas: () => mexidas.add + mexidas.remove,
		prop: ( n ) => props[ n ],
		saltos: () => saltos,
		lenis: () => lenisChamadas,
		foco: () => foco,
		temOuvinteDeScroll: () => !! ( ouvintes.scroll || [] ).length,
		y: () => y
	};
}

console.log( 'WooFlow Animations — botão voltar ao topo\n' );

console.log( 'Quando o script roda antes de o botão existir (o caso real)' );

const tarde = montar( { readyState: 'loading', botaoNoDom: false } );

ok( ! tarde.temOuvinteDeScroll(), 'com o DOM ainda montando, nada é amarrado na hora' );

tarde.imprimirBotao();
tarde.domPronto();

ok( tarde.temOuvinteDeScroll(), 'o botão é achado quando o DOM fica pronto' );

tarde.rolarPara( 900 );
ok( tarde.visivel(), 'e passa a aparecer ao cruzar o limiar' );

console.log( '\nQuando o script roda com o botão já no DOM' );

const cedo = montar( { readyState: 'complete', botaoNoDom: true } );

ok( cedo.temOuvinteDeScroll(), 'amarra na hora, sem esperar DOMContentLoaded' );
ok( ! cedo.visivel(), 'página no topo: o botão não aparece' );

console.log( '\nLimiar' );

const limiar = montar( { readyState: 'complete', botaoNoDom: true } );

limiar.rolarPara( 399 );
ok( ! limiar.visivel(), 'um pixel antes do limiar, ainda escondido' );

limiar.rolarPara( 401 );
ok( limiar.visivel(), 'um pixel depois, aparece' );

const antes = limiar.mexidas();

for ( let i = 0; i < 30; i++ ) {
	limiar.rolarPara( 1000 + i );
}

ok( antes === limiar.mexidas(), 'rolar com o botão visível não mexe mais na classe', limiar.mexidas() + ' mexidas' );

limiar.rolarPara( 0 );
ok( ! limiar.visivel(), 'voltar ao topo esconde o botão de novo' );

console.log( '\nPágina que nasce rolada' );

const rolada = montar( { readyState: 'loading', botaoNoDom: false } );

rolada.imprimirBotao();
rolada.rolarPara( 2000 );
rolada.domPronto();

ok( rolada.visivel(), 'o botão já nasce visível, sem esperar o primeiro scroll' );

console.log( '\nAnel de progresso' );

const anel = montar( { readyState: 'complete', botaoNoDom: true } );

anel.rolarPara( 2050 );
ok( '0.5000' === anel.prop( '--wfan-btt-p' ), 'metade da página é metade do anel', String( anel.prop( '--wfan-btt-p' ) ) );

anel.rolarPara( 99999 );
ok( '1.0000' === anel.prop( '--wfan-btt-p' ), 'o anel fecha no fim e não passa de 1', String( anel.prop( '--wfan-btt-p' ) ) );

const semAnel = montar( { readyState: 'complete', botaoNoDom: true, cfg: { ring: false } } );

semAnel.rolarPara( 2050 );
ok( undefined === semAnel.prop( '--wfan-btt-p' ), 'desligado, o anel não escreve nada por frame de scroll' );

console.log( '\nA subida' );

const tween = montar( { readyState: 'complete', botaoNoDom: true, cfg: { reduced: false } } );

tween.rolarPara( 3000 );
tween.clicar();

const chegada = tween.saltos()[ tween.saltos().length - 1 ];

ok( tween.saltos().length > 1, 'sem Lenis, a subida é um tween de vários quadros', tween.saltos().length + ' quadros' );
ok( 0 === chegada, 'e termina exatamente no topo', String( chegada ) );
ok( 1 === tween.foco(), 'o foco vai para o começo do conteúdo, uma vez' );

const comLenis = montar( { readyState: 'complete', botaoNoDom: true, lenis: true, cfg: { reduced: false } } );

comLenis.rolarPara( 3000 );
comLenis.clicar();

ok( 1 === comLenis.lenis().length, 'com o Lenis na página, a subida passa por ele' );
ok( 0 === comLenis.lenis()[ 0 ].alvo, 'pedindo o topo' );
ok( 0.8 === comLenis.lenis()[ 0 ].opts.duration, 'com a duração dos Ajustes, em segundos', String( comLenis.lenis()[ 0 ].opts.duration ) );
ok( 'function' === typeof comLenis.lenis()[ 0 ].opts.easing, 'e com a mesma curva do tween, para as duas vias baterem' );
ok( ! comLenis.saltos().length, 'e nenhum window.scrollTo nosso: o Lenis desfaria no quadro seguinte' );

const parado = montar( { readyState: 'complete', botaoNoDom: true, reduzir: true, cfg: { reduced: true } } );

parado.rolarPara( 3000 );
parado.clicar();

ok( 1 === parado.saltos().length, 'com "reduzir movimento", a ida ao topo é um salto só', parado.saltos().length + ' saltos' );
ok( 0 === parado.saltos()[ 0 ], 'e o salto é para o topo' );

const ignorando = montar( { readyState: 'complete', botaoNoDom: true, reduzir: true, cfg: { reduced: false } } );

ignorando.rolarPara( 3000 );
ignorando.clicar();

ok( ignorando.saltos().length > 1, 'com o ajuste desligado, o movimento continua mesmo com a preferência do sistema' );

console.log( '\n' + '-'.repeat( 48 ) );
console.log( falhas ? falhas + ' de ' + total + ' verificações falharam' : 'todas as ' + total + ' verificações passaram' );

process.exit( falhas ? 1 : 0 );
