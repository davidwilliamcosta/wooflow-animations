/**
 * Teste da barra fixa: `node tests/header.js`.
 *
 * Carrega o header.js de verdade num DOM de mentira e dirige a rolagem à mão.
 * É o único lugar que confere o que nem o smoke test nem o olho alcançam: de
 * ONDE sai a cor da barra, e se o laço de amortecimento PARA.
 *
 * Os dois defeitos que este arquivo existe para pegar:
 *
 * 1. A cor é lida do próprio elemento, com a regra do script desligada pelo
 *    tempo da medição. Se essa regra continuasse valendo, a medição leria de
 *    volta o transparente que nós mesmos acabamos de escrever, e a barra
 *    nunca ganharia cor nenhuma — com a configuração perfeita na página.
 * 2. O amortecimento é um requestAnimationFrame que tem de se apagar sozinho
 *    ao chegar no alvo. Um laço eterno em toda página do site não aparece na
 *    tela: aparece na bateria.
 *
 * @package WFAN
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const SCRIPT = process.argv[ 2 ] || path.join( __dirname, '..', 'assets', 'js', 'frontend', 'header.js' );

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
 * Sobe o header.js num ambiente controlado.
 *
 * `fundo` é o que o getComputedStyle devolve enquanto a classe de leitura
 * está posta; `fundoLigado` é o que ele devolveria sem ela — é a diferença
 * entre as duas que o teste 1 persegue.
 */
function montar( opcoes ) {
	const o = Object.assign(
		{
			readyState: 'loading',
			barraNoDom: true,
			fundo: 'rgb(17, 24, 39)',
			fundoLigado: 'rgba(0, 0, 0, 0)',
			reduzir: false,
			cfg: {}
		},
		opcoes
	);

	const cfg = Object.assign(
		{ selector: '.wfan-header', distance: 280, smooth: 220, shadow: true, reduced: true, debug: false },
		o.cfg
	);

	const classes = new Set( [ 'wfan-header' ] );
	const props = {};
	const leituras = [];

	const barra = {
		classList: {
			add: ( c ) => classes.add( c ),
			remove: ( c ) => classes.delete( c ),
			contains: ( c ) => classes.has( c )
		},
		style: { setProperty: ( n, v ) => { props[ n ] = v; } }
	};

	const prontos = [];
	const ouvintes = {};
	const quadros = [];

	const sandbox = {
		Math: Math,
		isNaN: isNaN,
		parseInt: parseInt,
		console: { log: () => {} },
		pageYOffset: 0,
		matchMedia: () => ( { matches: o.reduzir } ),
		/*
		 * O coração do teste 1: a resposta MUDA conforme a classe de leitura
		 * esteja posta ou não, exatamente como o `:not()` do CSS faz.
		 */
		getComputedStyle: () => {
			const lendo = classes.has( 'wfan-header--off' );

			leituras.push( lendo );

			return { backgroundColor: lendo ? o.fundo : o.fundoLigado };
		},
		requestAnimationFrame: ( fn ) => {
			quadros.push( fn );
			return quadros.length;
		},
		addEventListener: ( tipo, fn ) => {
			ouvintes[ tipo ] = ( ouvintes[ tipo ] || [] ).concat( fn );
		},
		document: {
			readyState: o.readyState,
			documentElement: { scrollTop: 0 },
			addEventListener: ( tipo, fn ) => {
				if ( 'DOMContentLoaded' === tipo ) {
					prontos.push( fn );
				}
			},
			querySelector: ( sel ) => ( o.barraNoDom && cfg.selector === sel ? barra : null )
		},
		wfanHeader: cfg
	};

	sandbox.window = sandbox;
	vm.createContext( sandbox );
	vm.runInContext( fs.readFileSync( SCRIPT, 'utf8' ), sandbox, { filename: 'header.js' } );

	// Relógio de mentira: cada quadro avança 16 ms, como a 60 Hz.
	let agora = 0;

	function rodarQuadros( limite ) {
		let n = 0;

		while ( quadros.length && n < ( limite || 2000 ) ) {
			agora += 16;
			quadros.shift()( agora );
			n++;
		}

		return n;
	}

	return {
		domPronto: () => { prontos.splice( 0, prontos.length ).forEach( ( fn ) => fn() ); },
		rolarPara: ( v ) => {
			sandbox.pageYOffset = v;
			sandbox.document.documentElement.scrollTop = v;
			( ouvintes.scroll || [] ).forEach( ( fn ) => fn() );
		},
		quadros: rodarQuadros,
		quadrosPendentes: () => quadros.length,
		pronta: () => classes.has( 'is-ready' ),
		lendoDurante: () => leituras,
		desligouDepoisDeLer: () => ! classes.has( 'wfan-header--off' ),
		prop: ( n ) => props[ n ],
		alfa: () => {
			const v = props[ '--wfan-hdr-bg' ];

			return v ? Number( ( v.match( /[\d.]+/g ) || [] )[ 3 ] ) : null;
		},
		temOuvinteDeScroll: () => !! ( ouvintes.scroll || [] ).length,
		estado: () => sandbox.window.wfanHeaderApi.state()
	};
}

console.log( 'WooFlow Animations — barra fixa\n' );

console.log( 'De onde sai a cor' );

const normal = montar( {} );

normal.domPronto();

ok( normal.lendoDurante().length > 0, 'a cor é medida na partida' );
ok(
	normal.lendoDurante().every( ( lendo ) => lendo ),
	'e sempre com a regra do script fora do caminho — senão ela leria o próprio transparente'
);
ok( normal.desligouDepoisDeLer(), 'a classe de leitura sai logo depois da medição' );
ok( normal.pronta(), 'só então a barra passa a ser pintada pelo script' );
ok( normal.temOuvinteDeScroll(), 'e o ouvinte de scroll é amarrado' );

const rgbLido = normal.estado();

ok(
	rgbLido.rgb && 17 === rgbLido.rgb[ 0 ] && 24 === rgbLido.rgb[ 1 ] && 39 === rgbLido.rgb[ 2 ],
	'o RGB guardado é o do elemento, não um ajuste nosso',
	JSON.stringify( rgbLido.rgb )
);

console.log( '\nCor que o script não entende' );

const degrade = montar( { fundo: 'linear-gradient(90deg, #000, #fff)' } );

degrade.domPronto();

ok( ! degrade.pronta(), 'com um degradê no fundo, o script não assume a barra' );
ok( ! degrade.temOuvinteDeScroll(), 'e não amarra ouvinte nenhum' );
ok( undefined === degrade.prop( '--wfan-hdr-bg' ), 'nenhuma variável é escrita: o fundo original fica de pé' );

const semBarra = montar( { barraNoDom: false } );

semBarra.domPronto();

ok( ! semBarra.temOuvinteDeScroll(), 'sem barra na página, nada é amarrado' );

console.log( '\nProgresso' );

const p = montar( {} );

p.domPronto();

ok( 0 === p.alfa(), 'no topo da página a barra está transparente', String( p.alfa() ) );

p.rolarPara( 140 );
p.quadros();

const meio = p.alfa();

ok( meio > 0.4 && meio < 0.6, 'na metade da distância a cor está pela metade', String( meio ) );

p.rolarPara( 280 );
p.quadros();

ok( 1 === p.alfa(), 'na distância cheia a cor chega ao alfa do elemento', String( p.alfa() ) );

p.rolarPara( 99999 );
p.quadros();

ok( 1 === p.alfa(), 'e não passa disso por mais que se role', String( p.alfa() ) );

p.rolarPara( -50 );
p.quadros();

ok( 0 === p.alfa(), 'rolagem negativa (elástico do iOS) trava no zero', String( p.alfa() ) );

console.log( '\nO laço de amortecimento' );

const laco = montar( {} );

laco.domPronto();
laco.rolarPara( 280 );

const gastos = laco.quadros();

ok( gastos > 1, 'o amortecimento leva mais de um quadro', String( gastos ) );
ok( 0 === laco.quadrosPendentes(), 'e o laço se apaga ao chegar no alvo, em vez de girar para sempre' );
ok( ! laco.estado().running, 'o estado concorda que ele parou' );

console.log( '\nAlfa do próprio elemento' );

const translucida = montar( { fundo: 'rgba(255, 255, 255, 0.8)' } );

translucida.domPronto();
translucida.rolarPara( 280 );
translucida.quadros();

ok(
	0.8 === translucida.alfa(),
	'barra meio translúcida no Elementor chega até a transparência dela, não até 1',
	String( translucida.alfa() )
);

console.log( '\nMovimento reduzido' );

const reduz = montar( { reduzir: true } );

reduz.domPronto();
reduz.rolarPara( 280 );

ok( 1 === reduz.alfa(), 'com a preferência do sistema, a cor chega ao alvo sem amortecimento', String( reduz.alfa() ) );
ok( 0 === reduz.quadrosPendentes(), 'e sem pedir quadro nenhum' );

const reduzDesligado = montar( { reduzir: true, cfg: { reduced: false } } );

reduzDesligado.domPronto();
reduzDesligado.rolarPara( 280 );

ok( reduzDesligado.quadrosPendentes() > 0, 'com o ajuste desligado, o amortecimento continua' );

console.log( '\nSombra' );

const semSombra = montar( { cfg: { shadow: false } } );

semSombra.domPronto();
semSombra.rolarPara( 280 );
semSombra.quadros();

ok( '0' === semSombra.prop( '--wfan-hdr-p' ), 'com a sombra desligada, a intensidade dela fica em zero' );
ok( 1 === semSombra.alfa(), 'mas o fundo continua funcionando' );

console.log( '\nQuando o script roda' );

const cedo = montar( { readyState: 'loading' } );

ok( ! cedo.temOuvinteDeScroll(), 'com o DOM ainda montando, nada é amarrado na hora' );

cedo.domPronto();

ok( cedo.temOuvinteDeScroll(), 'o script espera o DOM: a barra é markup da página' );

const tarde = montar( { readyState: 'complete' } );

ok( tarde.temOuvinteDeScroll(), 'com o DOM já pronto, amarra na hora' );

console.log( '\n------------------------------------------------' );

if ( falhas ) {
	console.log( falhas + ' de ' + total + ' verificações falharam' );
	process.exit( 1 );
}

console.log( 'todas as ' + total + ' verificações passaram' );
