/**
 * Teste do percurso da revelação travada no scroll: `node tests/scrub.js`.
 *
 * Carrega o engine-gsap.js de verdade com um GSAP de mentira e lê o que ele
 * entrega ao ScrollTrigger. É o único lugar onde se confere ONDE a animação
 * começa e termina — e era exatamente aí que o defeito morava: com o par
 * genérico "o elemento entrar na tela" até "o topo chegar ao topo", o texto
 * terminava de acender enquanto o bloco ainda subia, antes de dar para ler.
 *
 * A regra que este arquivo guarda: na revelação pedaço por pedaço, as duas
 * pontas do percurso têm de usar a MESMA altura de tela — `top <linha>` até
 * `bottom <linha>`. Só assim a frente de luz fica parada na tela enquanto o
 * texto passa por ela, e o percurso vale a altura do elemento.
 *
 * @package WFAN
 */

'use strict';

const fs = require( 'fs' );
const path = require( 'path' );
const vm = require( 'vm' );

const ENGINE = process.argv[ 2 ] || path.join( __dirname, '..', 'assets', 'js', 'frontend', 'engine-gsap.js' );

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
 * Sobe o motor com um GSAP de mentira e devolve o que o `bind()` montou.
 *
 * @param {Object} spec    Contrato já normalizado, como o core.js o entrega.
 * @param {number} pedacos Quantos alvos o texto picado tem.
 */
function amarrar( spec, pedacos, raio ) {
	const chamadas = [];
	const alvos = [];

	for ( let i = 0; i < pedacos; i++ ) {
		alvos.push( { __i: i } );
	}

	const engines = {};
	const fakeTween = { scrollTrigger: null, kill: function () {} };

	const sandbox = {
		window: null,
		console: console
	};

	sandbox.window = {
		gsap: {
			registerPlugin: function () {},
			fromTo: function ( targets, from, to ) {
				chamadas.push( { targets: targets, from: from, to: to } );
				return fakeTween;
			},
			from: function () {
				return fakeTween;
			},
			to: function () {
				return fakeTween;
			},
			set: function ( targets, vars ) {
				chamadas.push( { set: targets, vars: vars } );
			},
			killTweensOf: function () {}
		},
		ScrollTrigger: {
			config: function () {},
			create: function ( vars ) {
				chamadas.push( { create: vars } );
				return { kill: function () {} };
			},
			refresh: function () {},
			getAll: function () {
				return [];
			}
		},
		wfan: {
			register: function ( nome, motor ) {
				engines[ nome ] = motor;
			},
			util: {
				reveal: function () {},
				cssEase: function () {
					return 'linear';
				},
				log: function () {}
			}
		},
		getComputedStyle: function () {
			const r = ( raio || 0 ) + 'px';

			return { borderTopLeftRadius: r, borderTopRightRadius: r, borderBottomRightRadius: r, borderBottomLeftRadius: r };
		},
		addEventListener: function () {},
		clearTimeout: function () {},
		setTimeout: function () {
			return 0;
		}
	};

	sandbox.global = sandbox;

	vm.createContext( sandbox );
	vm.runInContext( fs.readFileSync( ENGINE, 'utf8' ), sandbox, { filename: ENGINE } );

	const el = { __wfanTween: null };

	engines.gsap.bind( el, spec, alvos );

	const tween = chamadas.filter( function ( c ) {
		return c.to;
	} ).pop();

	const posicaoFromTo = chamadas.indexOf( tween );
	const antes = chamadas.slice( 0, Math.max( 0, posicaoFromTo ) ).filter( function ( c ) {
		return c.set;
	} ).pop();

	return {
		fixado: antes || null,
		trigger: tween ? tween.to.scrollTrigger : null,
		alvo: tween ? tween.targets : null,
		from: tween ? tween.from : null,
		to: tween ? tween.to : null,
		el: el,
		alvos: alvos,
		chamadas: chamadas,
		motor: engines.gsap
	};
}

function spec( extra ) {
	return Object.assign(
		{
			preset: 'text-scrub-words',
			engine: 'gsap',
			trigger: 'scroll-scrub',
			start: 'enter',
			end: 'out',
			pct: 100,
			pin: false,
			dim: 0.2,
			line: 80,
			duration: 800,
			delay: 0,
			easing: 'power2.out',
			split: 'words',
			stagger: { sel: '> *', each: 80, from: 'start', kids: false }
		},
		extra
	);
}

console.log( '\nPercurso da revelação travada no scroll\n' );

/*
 * A linha fixa. Medida do topo da tela no painel, e do fundo pelo
 * ScrollTrigger: 80% do topo é o mesmo que 20% acima do fundo.
 */
const fixo = amarrar( spec(), 40 );

/**
 * O fim é uma distância a partir do início, calculada pelo ScrollTrigger a
 * cada refresh com o próprio elemento em mãos.
 *
 * @param {Object} amarrado Retorno de amarrar().
 * @param {number} altura   Altura do elemento no momento do cálculo.
 */
function fim( amarrado, altura ) {
	const end = ( amarrado.trigger || {} ).end;

	return 'function' === typeof end ? end( { trigger: { offsetHeight: altura } } ) : end;
}

ok( !! fixo.trigger, 'o motor amarra um ScrollTrigger' );
ok( 'top bottom-=20%' === ( fixo.trigger || {} ).start, 'começa quando o topo do texto cruza a linha', ( fixo.trigger || {} ).start );

// O defeito que originou o arquivo: terminar numa âncora do topo do elemento
// faz o texto acabar de acender enquanto o bloco ainda sobe. O fim tem de ser
// uma distância a partir do início, nunca uma âncora própria.
ok(
	'function' === typeof ( fixo.trigger || {} ).end,
	'o fim é uma distância a partir do início, não uma âncora do elemento',
	String( ( fixo.trigger || {} ).end )
);

// Na velocidade de referência o percurso vale a altura do elemento: a frente
// de luz fica parada na linha e o texto passa por ela de ponta a ponta.
ok( '+=400' === fim( fixo, 400 ), 'na velocidade de referência o percurso vale a altura do elemento', fim( fixo, 400 ) );
ok( '+=900' === fim( fixo, 900 ), 'e acompanha a altura quando ela muda (refresh)', fim( fixo, 900 ) );

const outraLinha = amarrar( spec( { line: 50 } ), 40 );

ok( 'top bottom-=50%' === ( outraLinha.trigger || {} ).start, 'a linha do painel chega ao percurso', ( outraLinha.trigger || {} ).start );

/*
 * Velocidade: mais rápido é MENOS rolagem para acender tudo, e mais devagar é
 * mais. Escala a mesma conta, sem mexer no ponto de partida.
 */
const rapido = amarrar( spec( { speed: 100 } ), 40 );
const lento = amarrar( spec( { speed: 30 } ), 40 );
const medio = amarrar( spec( { speed: 65 } ), 40 );

ok( parseInt( fim( rapido, 400 ).slice( 2 ), 10 ) < parseInt( fim( medio, 400 ).slice( 2 ), 10 ), 'mais rápido pede menos rolagem', fim( rapido, 400 ) );
ok( parseInt( fim( lento, 400 ).slice( 2 ), 10 ) > parseInt( fim( medio, 400 ).slice( 2 ), 10 ), 'mais devagar pede mais', fim( lento, 400 ) );
ok( ( rapido.trigger || {} ).start === ( lento.trigger || {} ).start, 'a velocidade não mexe no ponto de partida' );

// Elemento sem altura medida (ainda em layout) não pode dar percurso zero: o
// ScrollTrigger empurraria o fim um pixel adiante e tudo acenderia de uma vez.
ok( parseInt( fim( fixo, 0 ).slice( 2 ), 10 ) > 0, 'sem altura medida o percurso nunca zera', fim( fixo, 0 ) );

/*
 * O estado de partida de TODOS os pedaços tem de ser fixado antes da tween.
 * O `fromTo` com `stagger` só aplica o valor inicial de cada filho quando o
 * início DELE chega, então quem vem depois da frente de luz ficava aceso — e
 * só caía para o apagado quando a frente o alcançava. Medido num Chrome real,
 * com a página parada antes do percurso: só a primeira palavra em 0.20, as
 * outras 101 em 1.00. O fake não reproduz o GSAP, então o que se confere aqui
 * é a causa: o `set` com todos os alvos, antes do `fromTo`.
 */
ok( !! fixo.fixado, 'o estado de partida é fixado antes da tween' );
ok(
	fixo.fixado && fixo.fixado.set.length === 40 && 0.2 === fixo.fixado.vars.opacity,
	'em todos os pedaços, na opacidade de partida',
	fixo.fixado ? fixo.fixado.set.length + ' alvos, opacity ' + fixo.fixado.vars.opacity : 'sem set'
);

// Opacidade de partida: o texto nasce visível e apagado, nunca em zero — este
// preset não pré-esconde nada.
ok( 0.2 === ( fixo.from || {} ).opacity, 'parte da opacidade escolhida', JSON.stringify( fixo.from ) );
ok( 1 === ( fixo.to || {} ).opacity, 'chega opaco' );
ok( 'none' === ( fixo.to || {} ).ease, 'sem curva: quem manda no tempo é a rolagem' );

/*
 * A borda do degradê. Com a duração igual ao passo, só um pedaço está em
 * transição por vez e a frente de luz vira um interruptor andando pelo texto;
 * a referência mantém algumas linhas em degradê.
 */
ok( 1 === ( fixo.to || {} ).stagger, 'cada pedaço ocupa uma fatia igual do percurso', String( ( fixo.to || {} ).stagger ) );
ok( ( fixo.to || {} ).duration > ( fixo.to || {} ).stagger, 'a transição de um pedaço cobre vários passos, senão não há degradê', String( ( fixo.to || {} ).duration ) );

const curto = amarrar( spec(), 6 );

ok( ( curto.to || {} ).duration >= 1, 'num texto curto a borda nunca zera', String( ( curto.to || {} ).duration ) );

/*
 * Percurso de comprimento zero: o ScrollTrigger empurra o fim um pixel adiante
 * e a animação inteira acontece nesse pixel — tudo de uma vez, sem rolagem.
 * O painel deixa escolher as duas pontas livremente nos outros presets de
 * scroll, e dois pares batem.
 */
[ 'center', 'top' ].forEach( function ( ponta ) {
	const degenerado = amarrar( spec( { preset: 'scrub-fade', line: 0, start: ponta, end: ponta } ), 1 );

	ok(
		( degenerado.trigger || {} ).start !== ( degenerado.trigger || {} ).end,
		'início e fim iguais ("' + ponta + '") não viram percurso de comprimento zero',
		( degenerado.trigger || {} ).start + ' → ' + ( degenerado.trigger || {} ).end
	);
} );

// Preset de scroll que não é de texto continua com o par genérico.
const generico = amarrar( spec( { preset: 'scrub-fade', line: 0, start: 'enter', end: 'out' } ), 1 );

ok( 'top bottom' === ( generico.trigger || {} ).start, 'sem linha fixa, o início genérico continua valendo', ( generico.trigger || {} ).start );
ok( 'bottom top' === ( generico.trigger || {} ).end, 'e o fim genérico também', ( generico.trigger || {} ).end );

/*
 * "Virar cartão no scroll", a animação de linha do Uncode com estado "End":
 * percurso próprio, que começa quando o topo encosta no topo da tela e dura
 * uma fração da altura da TELA — não uma âncora do elemento, que numa seção
 * alta esticaria o efeito por várias telas.
 */
console.log( '\nVirar cartão no scroll\n' );

function cartao( extra, raio ) {
	return amarrar(
		spec( {
			preset: 'scrub-card',
			line: 0,
			split: '',
			stagger: null,
			card: Object.assign( { state: 'out', scale: 0.9, radius: 54, origin: 'bottom center', length: 150 }, extra )
		} ),
		3,
		raio
	);
}

const sai = cartao();

ok( 'top top' === ( sai.trigger || {} ).start, 'ao sair, começa com o topo do bloco no topo da tela', ( sai.trigger || {} ).start );
ok( '+=150%' === ( sai.trigger || {} ).end, 'e dura a fração da tela escolhida', ( sai.trigger || {} ).end );
ok( sai.alvo === sai.el, 'anima o próprio elemento, nunca os filhos um a um' );
ok( 1 === ( sai.from || {} ).scale && 0.9 === ( sai.to || {} ).scale, 'parte inteiro e chega na escala do cartão', JSON.stringify( [ sai.from, sai.to ] ) );
ok( '54px 54px 54px 54px' === ( sai.to || {} ).borderRadius, 'chega com os cantos do cartão', ( sai.to || {} ).borderRadius );
ok(
	( sai.to || {} ).borderRadius === ( sai.to || {} )[ '--border-radius' ],
	'o raio vai também na variável que a sobreposição e o vídeo de fundo do container leem'
);
ok( 'none' === ( sai.to || {} ).ease, 'sem curva: quem manda no tempo é a rolagem' );
ok(
	sai.chamadas.some( function ( c ) {
		return c.set === sai.el && 'bottom center' === c.vars.transformOrigin;
	} ),
	'a origem é fixada no elemento antes da tween'
);

const comRaio = cartao( {}, 12 );

ok( '12px 12px 12px 12px' === ( comRaio.from || {} ).borderRadius, 'o raio que o elemento já tinha é o ponto de partida', ( comRaio.from || {} ).borderRadius );

const entra = cartao( { state: 'in', length: 100 } );

ok( 'top bottom' === ( entra.trigger || {} ).start, 'ao entrar, começa quando o bloco aparece no pé da tela', ( entra.trigger || {} ).start );
ok( '+=100%' === ( entra.trigger || {} ).end, 'com o percurso escolhido', ( entra.trigger || {} ).end );
ok( 0.9 === ( entra.from || {} ).scale && 1 === ( entra.to || {} ).scale, 'e faz o caminho inverso: chega cartão e se abre' );

// O reset não pode passar `clearProps: 'all'`: apagaria o `isolation` que o
// fundo animado põe inline no mesmo elemento (regra 20).
sai.chamadas.length = 0;
sai.motor.reset( sai.el, spec( { preset: 'scrub-card' } ), sai.alvos );

const limpeza = sai.chamadas.filter( function ( c ) {
	return c.set && c.vars.clearProps;
} );

ok(
	limpeza.length > 0 && limpeza.every( function ( c ) {
		return 'all' !== c.vars.clearProps && -1 !== c.vars.clearProps.indexOf( '--border-radius' );
	} ),
	'o reset limpa só o que o cartão escreveu',
	JSON.stringify( limpeza.map( function ( c ) {
		return c.vars.clearProps;
	} ) )
);

console.log( '\n------------------------------------------------' );

if ( falhas ) {
	console.log( falhas + ' de ' + total + ' verificações falharam' );
	process.exit( 1 );
}

console.log( 'todas as ' + total + ' verificações passaram' );
