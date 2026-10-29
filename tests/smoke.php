<?php
/**
 * Smoke test sem WordPress: `php tests/smoke.php`.
 *
 * Cobre o que quebra em silêncio e só aparece no editor ou no front-end:
 * nomes de chave, condições apontando para controle que não existe, motor
 * resolvido errado, biblioteca enfileirada quando não devia e o contrato
 * data-wfan desalinhado entre o PHP e o core.js.
 *
 * @package WFAN
 */

// phpcs:disable

require __DIR__ . '/stubs.php';

define( 'ABSPATH', __DIR__ );
define( 'WFAN_VER', '2.0.0' );
define( 'WFAN_FILE', dirname( __DIR__ ) . '/wooflow-animations.php' );
define( 'WFAN_DIR', dirname( __DIR__ ) . '/' );
define( 'WFAN_URL', 'https://exemplo.test/wp-content/plugins/wooflow-animations/' );
define( 'WFAN_BASENAME', 'wooflow-animations/wooflow-animations.php' );
define( 'WFAN_TD', 'wooflow-animations' );

require WFAN_DIR . 'includes/class-requirements.php';
require WFAN_DIR . 'includes/class-plugin.php';

$failures = 0;
$checks   = 0;

function ok( $condition, $label, $detail = '' ) {
	global $failures, $checks;

	$checks++;

	if ( $condition ) {
		echo "  ok   $label\n";
		return true;
	}

	$failures++;
	echo "  FALHA $label" . ( $detail ? " — $detail" : '' ) . "\n";

	return false;
}

function section( $title ) {
	echo "\n$title\n";
}

/**
 * Elemento falso do Elementor, o bastante para receber controles e render.
 */
class WFAN_Fake_Element {

	public $name;
	public $type;
	public $settings;
	public $controls = [];
	public $sections = [];
	public $attributes = [];
	public $current_section = '';

	public function __construct( $name, $type, $settings = [] ) {
		$this->name     = $name;
		$this->type     = $type;
		$this->settings = $settings;
	}

	public function get_name() { return $this->name; }
	public function get_unique_name() { return $this->name; }
	public function get_type() { return $this->type; }
	public function get_settings() { return $this->settings; }
	public function get_settings_for_display() { return $this->settings; }

	public function start_controls_section( $id, $args = [] ) {
		$this->current_section = $id;
		$this->sections[ $id ] = $args;
		$this->to_stack( $id, $args );
	}

	public function end_controls_section() {
		$this->current_section = '';
	}

	public function add_control( $id, $args = [] ) {
		$this->controls[ $id ] = $args;
		$this->to_stack( $id, $args );
	}

	public function add_responsive_control( $id, $args = [] ) {
		$this->controls[ $id ] = $args;
		$this->to_stack( $id, $args );
	}

	// Seção também é controle no stack do Elementor, com o mesmo id.
	private function to_stack( $id, $args ) {
		\Elementor\Plugin::$instance->controls_manager->stacks[ $this->name ][ $id ] = $args;
	}

	public function add_render_attribute( $key, $attrs = [] ) {
		foreach ( $attrs as $name => $value ) {
			if ( 'class' === $name ) {
				$this->attributes[ $key ]['class'] = array_merge(
					$this->attributes[ $key ]['class'] ?? [],
					(array) $value
				);
				continue;
			}

			$this->attributes[ $key ][ $name ] = $value;
		}
	}

	public function set_settings( $key, $value = null ) {
		$this->settings[ $key ] = $value;
	}

	public function payload() {
		$raw = $this->attributes['_wrapper']['data-wfan'] ?? null;

		return $raw ? json_decode( $raw, true ) : null;
	}

	public function classes() {
		return $this->attributes['_wrapper']['class'] ?? [];
	}
}

/** Elemento atômico do Elementor 4: reconhecido pelo get_props_schema(). */
class WFAN_Fake_Atomic extends WFAN_Fake_Element {
	public function get_props_schema() { return []; }
}

echo "WooFlow Animations — smoke test\n";

// ------------------------------------------------------------------ boot

section( '1. Boot' );

ok( WFAN_Requirements::met(), 'requisitos atendidos com os dublês' );

$plugin = WFAN_Plugin::instance();

ok( $plugin instanceof WFAN_Plugin, 'plugin instanciado' );
ok( $plugin->assets instanceof WFAN_Assets, 'registry de assets presente' );

// ------------------------------------------------------- ganchos de controle

section( '2. Ganchos de injeção de controles' );

foreach ( WFAN_Controls::WIDGET_STACKS as $stack ) {
	$hook = "elementor/element/{$stack}/section_effects/after_section_end";

	ok( isset( WFAN_Test_Hooks::$actions[ $hook ] ), "gancho de widget registrado: {$stack}" );
}

foreach ( WFAN_Controls::BLOCK_FIRST_SECTION as $type => $first ) {
	$hook = "elementor/element/{$type}/{$first}/before_section_start";

	ok( isset( WFAN_Test_Hooks::$actions[ $hook ] ), "gancho de bloco registrado: {$type} (antes de {$first})" );
}

ok(
	in_array( 'common-optimized', WFAN_Controls::WIDGET_STACKS, true ),
	'common-optimized incluído (regra 2 do CLAUDE.md)'
);

// ------------------------------------------------------------------ controles

section( '3. Controles injetados' );

$widget = new WFAN_Fake_Element( 'common', 'widget' );
do_action( 'elementor/element/common/section_effects/after_section_end', $widget, [] );

$block = new WFAN_Fake_Element( 'container', 'container' );
do_action( 'elementor/element/container/section_layout_container/before_section_start', $block, [] );

$k = static function ( $suffix ) {
	return WFAN_Keys::ours( $suffix );
};

ok( count( $widget->controls ) > 15, 'controles criados', count( $widget->controls ) . ' controles' );
ok( isset( $widget->controls[ $k( 'preset' ) ] ), 'controle de preset existe' );
ok(
	'wfan-picker' === ( $widget->controls[ $k( 'preset' ) ]['type'] ?? '' ),
	'preset usa o controle visual'
);
ok( isset( $widget->sections[ $k( 'section' ) ] ), 'seção própria criada' );

// A seção tem de cair na PRIMEIRA aba de cada tipo: Conteúdo no widget, Layout
// no bloco. Era a aba Avançado até a 2.0.0.
ok(
	'content' === ( $widget->sections[ $k( 'section' ) ]['tab'] ?? '' ),
	'widget: seção na aba Conteúdo',
	$widget->sections[ $k( 'section' ) ]['tab'] ?? '(sem aba)'
);
ok(
	'layout' === ( $block->sections[ $k( 'section' ) ]['tab'] ?? '' ),
	'bloco: seção na aba Layout',
	$block->sections[ $k( 'section' ) ]['tab'] ?? '(sem aba)'
);
ok(
	$k( 'section' ) === array_key_first( $block->sections ),
	'bloco: nossa seção é a primeira registrada'
);

// Com e_optimized_markup ligado o mesmo stack chega duas vezes. O guard por
// nome impede registrar os controles em dobro.
$twice = new WFAN_Fake_Element( 'common-optimized', 'widget' );
do_action( 'elementor/element/common-optimized/section_effects/after_section_end', $twice, [] );
$first_count = count( $twice->controls );
do_action( 'elementor/element/common/section_effects/after_section_end', $twice, [] );

ok( $first_count === count( $twice->controls ), 'stack repetido não registra controles em dobro' );

// O Elementor apaga e remonta o stack de um tipo na MESMA requisição quando a
// geração do CSS pede um responsive_control_duplication_mode mais alto que o
// do render (Controls_Manager::should_clean_stack()). A remontagem tem de
// receber a seção de novo — senão o post-<id>.css nasce sem regra nossa
// nenhuma e a inversão no hover some do site publicado. Ver regra 2.
unset( \Elementor\Plugin::$instance->controls_manager->stacks['container'] );
$rebuilt = new WFAN_Fake_Element( 'container', 'container' );
do_action( 'elementor/element/container/section_layout_container/before_section_start', $rebuilt, [] );

ok(
	count( $rebuilt->controls ) === count( $block->controls ) && isset( $rebuilt->controls[ $k( 'hover_dur' ) ] ),
	'stack remontado na mesma requisição recebe a seção de novo',
	count( $rebuilt->controls ) . ' de ' . count( $block->controls ) . ' controles'
);
ok(
	false === strpos( file_get_contents( WFAN_DIR . 'includes/class-controls.php' ), 'self::$done' ),
	'guard lê o stack, não uma lista de nomes já atendidos'
);

// Toda condição precisa apontar para um controle que existe — condição órfã é
// um controle que nunca aparece no painel, e isso não dá erro nenhum.
$native_keys = array_merge(
	array_values( WFAN_Keys::NATIVE_WIDGET ),
	array_values( WFAN_Keys::NATIVE_BLOCK )
);

$orphans   = [];
$bad_ids   = [];
$preset_ids = array_keys( WFAN_Presets::all() );

foreach ( $widget->controls as $id => $args ) {
	$terms = [];

	foreach ( $args['condition'] ?? [] as $name => $value ) {
		$terms[] = [ 'name' => $name, 'value' => $value ];
	}

	foreach ( $args['conditions']['terms'] ?? [] as $term ) {
		$terms[] = $term;
	}

	foreach ( $terms as $term ) {
		$name = rtrim( $term['name'], '!' );

		if ( ! isset( $widget->controls[ $name ] ) && ! in_array( $name, $native_keys, true ) ) {
			$orphans[] = "$id → $name";
		}

		// Ids de preset citados em condição precisam existir no catálogo.
		if ( $name === $k( 'preset' ) && is_array( $term['value'] ) ) {
			foreach ( $term['value'] as $candidate ) {
				if ( '' !== $candidate && ! in_array( $candidate, $preset_ids, true ) ) {
					$bad_ids[] = "$id → $candidate";
				}
			}
		}
	}
}

ok( ! $orphans, 'nenhuma condição órfã', implode( '; ', $orphans ) );
ok( ! $bad_ids, 'nenhum preset inexistente citado em condição', implode( '; ', $bad_ids ) );

// Chave de dado usada nos controles tem de estar declarada em Keys::OURS,
// senão copiar/colar e a biblioteca ignoram o controle em silêncio.
$ui_only = [ 'section', 'actions', 'native_warning', 'native_note' ];
$undeclared = [];

foreach ( array_keys( $widget->controls ) as $id ) {
	$suffix = substr( $id, strlen( WFAN_Keys::P ) );

	if ( in_array( $suffix, $ui_only, true ) ) {
		continue;
	}

	if ( ! in_array( $suffix, WFAN_Keys::OURS, true ) ) {
		$undeclared[] = $id;
	}
}

ok( ! $undeclared, 'toda chave de dado está declarada em Keys::OURS', implode( ', ', $undeclared ) );

// ------------------------------------------------------------------- render

section( '4. Render do contrato data-wfan' );

$render = new WFAN_Render();

function render_with( $element ) {
	do_action( 'elementor/frontend/before_render', $element );

	return $element;
}

WFAN_Test_Hooks::$actions['elementor/frontend/before_render'] = [ [ new WFAN_Render(), 'before_render' ] ];

$fade = render_with( new WFAN_Fake_Element( 'common', 'widget', [
	WFAN_Keys::ours( 'preset' )   => 'fade-up',
	WFAN_Keys::ours( 'trigger' )  => 'scroll-in',
	WFAN_Keys::ours( 'duration' ) => [ 'unit' => 'px', 'size' => 600 ],
	WFAN_Keys::ours( 'distance' ) => [ 'unit' => 'px', 'size' => 55 ],
	WFAN_Keys::ours( 'once' )     => 'yes',
] ) );

$p = $fade->payload();

ok( is_array( $p ), 'payload escrito no wrapper' );
ok( 'fade-up' === ( $p['p'] ?? '' ), 'preset no payload' );
ok( 'css' === ( $p['eng'] ?? '' ), 'motor resolvido para css' );
ok( 600 === ( $p['d'] ?? 0 ), 'duração lida do controle slider' );
ok( 55 === ( $p['dist'] ?? 0 ), 'distância lida do controle slider' );
ok( 1 === ( $p['tr']['once'] ?? 0 ), 'once ligado' );
ok( in_array( 'wfan-pending', $fade->classes(), true ), 'classe de pré-esconde aplicada' );

// Gatilho hover não esconde nada: o elemento tem de estar visível para receber
// o mouse.
$hover = render_with( new WFAN_Fake_Element( 'common', 'widget', [
	WFAN_Keys::ours( 'preset' )  => 'fade-up',
	WFAN_Keys::ours( 'trigger' ) => 'hover',
] ) );

ok( ! in_array( 'wfan-pending', $hover->classes(), true ), 'hover não esconde o elemento' );

// Preset de scroll só aceita scroll-scrub: um gatilho inválido é corrigido.
$scrub = render_with( new WFAN_Fake_Element( 'container', 'container', [
	WFAN_Keys::ours( 'preset' )  => 'parallax-y',
	WFAN_Keys::ours( 'trigger' ) => 'hover',
] ) );

$sp = $scrub->payload();

ok( 'gsap' === ( $sp['eng'] ?? '' ), 'parallax exige gsap' );
ok( 'scroll-scrub' === ( $sp['tr']['t'] ?? '' ), 'gatilho inválido cai no aceito pelo preset' );
ok( ! in_array( 'wfan-pending', $scrub->classes(), true ), 'preset de scroll não usa pré-esconde' );
ok( 0 === ( $sp['rev'] ?? null ), 'parallax sai no sentido de fábrica' );

$reversed = render_with( new WFAN_Fake_Element( 'container', 'container', [
	WFAN_Keys::ours( 'preset' )  => 'parallax-y',
	WFAN_Keys::ours( 'reverse' ) => 'yes',
] ) );

ok( 1 === ( $reversed->payload()['rev'] ?? null ), 'parallax invertido chega ao motor' );

// Elemento atômico do Elementor 4 fica de fora (regra 7).
$atomic = render_with( new WFAN_Fake_Atomic( 'e-heading', 'widget', [
	WFAN_Keys::ours( 'preset' ) => 'fade-up',
] ) );

ok( null === $atomic->payload(), 'elemento atômico é ignorado' );

// Sem preset, nada é escrito.
$plain = render_with( new WFAN_Fake_Element( 'common', 'widget', [] ) );

ok( null === $plain->payload(), 'elemento sem preset não recebe atributo' );

// Biblioteca bloqueada nos ajustes: preset que depende dela não anima.
WFAN_Test_Hooks::$options[ WFAN_Settings::OPTION ] = array_merge(
	WFAN_Settings::defaults(),
	[ 'lib_gsap' => '0' ]
);

( new ReflectionProperty( 'WFAN_Settings', 'cache' ) )->setValue( null, null );

$blocked = render_with( new WFAN_Fake_Element( 'container', 'container', [
	WFAN_Keys::ours( 'preset' ) => 'parallax-y',
] ) );

ok( null === $blocked->payload(), 'preset de gsap não sai com a lib bloqueada' );

WFAN_Test_Hooks::$options = [];
( new ReflectionProperty( 'WFAN_Settings', 'cache' ) )->setValue( null, null );

// ------------------------------------------------------------------- assets

section( '5. Carregamento sob demanda' );

$assets = new WFAN_Assets();
$assets->register();

WFAN_Test_Hooks::$enqueued = [];
$assets->enqueue_needed();

ok( ! WFAN_Test_Hooks::$enqueued, 'página sem animação não enfileira nada' );

$assets->require_engine( 'css' );
$assets->enqueue_needed();

ok( in_array( 'wfan-core', WFAN_Test_Hooks::$enqueued, true ), 'core entra quando há animação' );
ok( in_array( 'wfan-engine-css', WFAN_Test_Hooks::$enqueued, true ), 'motor próprio entra' );
ok( ! in_array( 'wfan-gsap', WFAN_Test_Hooks::$enqueued, true ), 'gsap NÃO entra em página só de css' );
ok( ! in_array( 'wfan-anime', WFAN_Test_Hooks::$enqueued, true ), 'anime NÃO entra em página só de css' );

$gsapAssets = new WFAN_Assets();
$gsapAssets->register();
WFAN_Test_Hooks::$enqueued = [];
$gsapAssets->require_engine( 'gsap' );
$gsapAssets->enqueue_needed();

ok( in_array( 'wfan-engine-gsap', WFAN_Test_Hooks::$enqueued, true ), 'motor gsap entra quando pedido' );
ok(
	in_array( 'wfan-gsap', WFAN_Test_Hooks::$scripts['wfan-engine-gsap']['deps'] ?? [], true )
		|| in_array( 'wfan-scrolltrigger', WFAN_Test_Hooks::$scripts['wfan-engine-gsap']['deps'] ?? [], true ),
	'motor gsap depende da biblioteca'
);

// Lottie do Pro é reusado em vez de duplicado (regra 6).
wp_register_script( 'lottie', 'https://exemplo.test/pro/lottie.min.js' );

$lottieAssets = new WFAN_Assets();
$lottieAssets->register();
WFAN_Test_Hooks::$enqueued = [];
$lottieAssets->require_engine( 'lottie' );
$lottieAssets->enqueue_needed();

ok( in_array( 'lottie', WFAN_Test_Hooks::$enqueued, true ), 'usa o lottie do Elementor Pro' );
ok( ! in_array( 'wfan-lottie-lib', WFAN_Test_Hooks::$enqueued, true ), 'não enfileira a nossa cópia do lottie' );

// -------------------------------------------------------------------- ajustes

section( '6. Ajustes' );

$settings = new WFAN_Settings();
$clean    = $settings->sanitize( [ 'lenis_lerp' => '9', 'mobile_bp' => '99999' ] );

ok( '0' === $clean['lib_gsap'], 'checkbox ausente grava "0", não false' );
ok( 1.0 === (float) $clean['lenis_lerp'], 'lerp é limitado a 1' );
ok( 1920 === (int) $clean['mobile_bp'], 'largura limite é limitada' );
ok( WFAN_Settings::is_on( 'respect_reduced' ), 'reduced-motion vem ligado de fábrica' );

// --------------------------------------------- contrato PHP x core.js

section( '7. Contrato entre o PHP e o core.js' );

$core = file_get_contents( WFAN_DIR . 'assets/js/frontend/core.js' );

preg_match_all( '/raw\.([a-zA-Z]+)/', $core, $matches );
$js_keys = array_unique( $matches[1] );

$php_keys = [];
$spec_of  = new WFAN_Render();

foreach ( WFAN_Presets::all() as $id => $preset ) {
	$case = [
		WFAN_Keys::ours( 'preset' )     => $id,
		WFAN_Keys::ours( 'lottie_url' ) => 'https://exemplo.test/a.json',
		WFAN_Keys::ours( 'stagger' )    => 'yes',
		WFAN_Keys::ours( 'off_mobile' ) => 'yes',
	];

	// Pelo spec(), e não pelo atributo: o preset nativo não escreve data-wfan
	// no front-end (quem anima é o Elementor), mas o contrato existe do mesmo
	// jeito — é dele que o ▶ Testar vive no editor.
	$spec    = $spec_of->spec( $case );
	$payload = is_array( $spec ) ? $spec['payload'] : null;

	ok( is_array( $payload ), "preset $id gera payload" );

	if ( is_array( $payload ) ) {
		$php_keys = array_merge( $php_keys, array_keys( $payload ) );
	}
}

$php_keys = array_unique( $php_keys );
$unread   = array_diff( $php_keys, $js_keys );
$unsent   = array_diff( $js_keys, $php_keys );

ok( ! $unread, 'toda chave enviada pelo PHP é lida pelo core.js', implode( ', ', $unread ) );
ok( ! $unsent, 'toda chave lida pelo core.js é enviada pelo PHP', implode( ', ', $unsent ) );

// Regra 17: o core.js testa reduced/offMobile/debug por veracidade, e "0" é
// verdadeiro no JavaScript. Toggle desligado que chegue como string liga o
// comportamento contrário — foi assim que a animação sumiu do celular inteiro.
$localizers = [];

foreach ( glob( WFAN_DIR . 'includes/*.php' ) as $file ) {
	// Pelos tokens, e não por strpos: o comentário que explica a regra cita a
	// função e daria falso positivo.
	foreach ( token_get_all( (string) file_get_contents( $file ) ) as $token ) {
		if ( is_array( $token ) && T_STRING === $token[0] && 'wp_localize_script' === $token[1] ) {
			$localizers[] = basename( $file );
			break;
		}
	}
}

ok( ! $localizers, 'nenhuma configuração sai por wp_localize_script', implode( ', ', $localizers ) );

$typed = new WFAN_Assets();
WFAN_Test_Hooks::$inline_js = [];
$typed->register();

$core_js = WFAN_Test_Hooks::$inline_js['wfan-core'] ?? '';

ok( '' !== $core_js, 'a configuração do core vai como script inline' );

$cfg = json_decode( (string) preg_replace( '/^var wfanConfig = |;$/', '', trim( $core_js ) ), true );

ok( is_array( $cfg ), 'a configuração do core é JSON válido', $core_js );

foreach ( [ 'reduced', 'offMobile', 'debug', 'editor' ] as $flag ) {
	ok(
		is_bool( $cfg[ $flag ] ?? null ),
		"wfanConfig.$flag chega como booleano, nunca \"0\"",
		var_export( $cfg[ $flag ] ?? null, true )
	);
}

// off_mobile vem desligado de fábrica: se chegasse como "0" o core.js pularia
// toda animação em tela estreita.
ok( false === ( $cfg['offMobile'] ?? null ), 'toggle desligado chega desligado no navegador' );
ok( is_int( $cfg['mobileBp'] ?? null ), 'a largura limite chega como número' );

section( '8. Padrão de UI administrativa WooFlow' );

$admin_css  = (string) file_get_contents( WFAN_DIR . 'assets/css/admin.css' );
$templates  = glob( WFAN_DIR . 'templates/admin/{,tabs/}*.php', GLOB_BRACE );
$tpl_source = '';

foreach ( (array) $templates as $tpl ) {
	$tpl_source .= (string) file_get_contents( $tpl );
}

ok( count( (array) $templates ) >= 2, 'a casca e a seção existem como templates' );

// Regra 2 do padrão: todo o CSS do painel vive em admin.css.
ok(
	! preg_match( '/<style[\s>]/i', $tpl_source ) && ! preg_match( '/\sstyle\s*=\s*["\']/', $tpl_source ),
	'nenhum <style> ou style="" nos templates de admin'
);

/*
 * Regra 3 do padrão: nenhum valor de cor fora das variáveis --wf-*.
 * O bloco de tokens é a única exceção, então ele é recortado antes
 * da checagem. O documento erra aqui na própria §18, que crava a
 * paleta de avisos em hex; os valores viraram tokens --wf-notice-*.
 */
$tokens_end  = strpos( $admin_css, '--wf-transition:' );
$css_after   = false === $tokens_end ? $admin_css : substr( $admin_css, $tokens_end );
$stray_hex   = [];

if ( preg_match_all( '/#[0-9a-fA-F]{3,8}\b/', $css_after, $m ) ) {
	$stray_hex = array_unique( $m[0] );
}

ok( ! $stray_hex, 'nenhuma cor em hex fora do bloco de tokens', implode( ', ', $stray_hex ) );

// Regra 4 do padrão: prefixo de classe único .wooflow-.
$bad_prefix = [];

if ( preg_match_all( '/\.(?:dw|wf)-[a-z0-9_-]+/i', $admin_css . $tpl_source, $m ) ) {
	$bad_prefix = array_unique( $m[0] );
}

ok( ! $bad_prefix, 'nenhuma classe .dw-* ou .wf-* remanescente', implode( ', ', $bad_prefix ) );

// A body class própria é o que sustenta a supressão de notices em
// qualquer menu-pai. Precisa existir nas duas pontas.
ok(
	false !== strpos( (string) file_get_contents( WFAN_DIR . 'includes/class-settings.php' ), 'wooflow-admin-screen' )
	&& false !== strpos( $admin_css, 'body.wooflow-admin-screen' ),
	'a body class wooflow-admin-screen é escrita no PHP e usada no CSS'
);

// O aviso de "Ajustes salvos." do settings_errors() não pode cair na
// regra que esconde notices de terceiros.
ok(
	false !== strpos( $admin_css, '.notice:not(.settings-error)' ),
	'a supressão de notices poupa as mensagens do settings_errors()'
);

// Toda classe .wooflow-* usada nos templates precisa existir no CSS:
// classe sem regra é componente que não aparece.
$used    = [];
$missing = [];

/*
 * O PHP embutido sai antes: um atributo que mistura classe literal e
 * interpolação renderiza só a classe literal, e o scanner não deve
 * enxergar a interpolação. (Este comentário é de bloco de propósito:
 * a sequência que fecha o modo PHP encerraria um comentário de linha
 * e jogaria o resto do arquivo para a saída como HTML cru.)
 */
$tpl_markup = (string) preg_replace( '/<\?php.*?(\?>|$)/s', '', $tpl_source );

if ( preg_match_all( '/class="([^"]*)"/', $tpl_markup, $m ) ) {
	foreach ( $m[1] as $attr ) {
		foreach ( preg_split( '/\s+/', trim( $attr ) ) as $cls ) {
			if ( 0 === strpos( (string) $cls, 'wooflow-' ) ) {
				$used[] = $cls;
			}
		}
	}
}

foreach ( array_unique( $used ) as $cls ) {
	if ( false === strpos( $admin_css, '.' . $cls ) ) {
		$missing[] = $cls;
	}
}

ok( ! $missing, 'toda classe .wooflow-* dos templates tem regra no admin.css', implode( ', ', $missing ) );

/*
 * A tela não pode exigir capability do WooCommerce: o plugin depende
 * só do Elementor, e num site sem Woo ninguém tem `manage_woocommerce`
 * — a página ficaria inacessível. A busca é pela forma entre aspas,
 * para não casar com a menção em comentário que explica isto.
 */
$settings_src = (string) file_get_contents( WFAN_DIR . 'includes/class-settings.php' );

ok(
	false === strpos( $settings_src, "'manage_woocommerce'" )
	&& false !== strpos( $settings_src, "'manage_options'" ),
	'a tela exige manage_options, não manage_woocommerce'
);

section( '9. Blur progressivo' );

$blur_clean = $settings->sanitize( [ 'blur_pos' => 'esquerda', 'blur_height' => '999', 'blur_strength' => '0' ] );

ok( 'bottom' === $blur_clean['blur_pos'], 'borda fora da lista cai no padrão' );
ok( 100 === (int) $blur_clean['blur_height'], 'altura é limitada a 100' );
ok( 1 === (int) $blur_clean['blur_strength'], 'desfoque mínimo é 1px' );
ok( '0' === $blur_clean['blur_enable'], 'checkbox ausente desliga o blur, não o deixa como estava' );

ok( ! WFAN_Blur::active(), 'desligado de fábrica' );

$blur_css = (string) file_get_contents( WFAN_DIR . 'assets/css/blur.css' );
$blur_min = (string) preg_replace( '/\s+/', '', $blur_css );

$blur_on = static function ( array $over = [] ) {
	update_option( WFAN_Settings::OPTION, array_merge( WFAN_Settings::defaults(), [ 'blur_enable' => '1' ], $over ) );
	WFAN_Settings::flush();
};

$blur_on( [ 'blur_pos' => 'both', 'blur_strength' => '14' ] );

ok( WFAN_Blur::active(), 'liga pelo ajuste' );
ok( [ 'top', 'bottom' ] === WFAN_Blur::edges(), 'a opção "topo e rodapé" imprime as duas faixas' );

$blurAssets = new WFAN_Assets();
$blurAssets->register();

WFAN_Test_Hooks::$enqueued = [];
WFAN_Test_Hooks::$inline   = [];

$blur = new WFAN_Blur();
$blur->enqueue();

ok( isset( WFAN_Test_Hooks::$styles['wfan-blur'] ), 'o CSS do blur é registrado no WFAN_Assets (regra 5)' );
ok( in_array( 'wfan-blur', WFAN_Test_Hooks::$enqueued, true ), 'o CSS do blur entra quando o efeito está ligado' );
ok( ! in_array( 'wfan-core', WFAN_Test_Hooks::$enqueued, true ), 'o blur não arrasta o core.js nem motor nenhum' );

$blur_inline = WFAN_Test_Hooks::$inline['wfan-blur'] ?? '';

ok( false !== strpos( $blur_inline, '--wfan-blur-3:14px' ), 'o desfoque escolhido vai para a camada mais forte', $blur_inline );
ok( false !== strpos( $blur_inline, '--wfan-blur-1:2px' ), 'as outras camadas seguem na mesma proporção', $blur_inline );

ob_start();
$blur->render();
$blur_markup = (string) ob_get_clean();

ok( 2 === substr_count( $blur_markup, 'class="wfan-blur ' ), 'duas faixas em "topo e rodapé"' );
ok( 6 === substr_count( $blur_markup, 'wfan-blur__layer--' ), 'três camadas por faixa' );
ok( 2 === substr_count( $blur_markup, 'aria-hidden="true"' ), 'a faixa é decoração: sai com aria-hidden' );

/*
 * Mesmo contrato da regra 15, uma camada abaixo: variável escrita no PHP e
 * não lida pelo CSS é ajuste que não faz nada, e classe no markup sem regra
 * no CSS é faixa que não aparece.
 */
$blur_unread = [];

if ( preg_match_all( '/(--wfan-blur-[a-z0-9]+)\s*:/i', $blur_inline, $m ) ) {
	foreach ( array_unique( $m[1] ) as $var ) {
		if ( false === strpos( $blur_min, 'var(' . $var ) ) {
			$blur_unread[] = $var;
		}
	}
}

ok( ! $blur_unread, 'toda variável escrita inline é lida pelo blur.css', implode( ', ', $blur_unread ) );

$blur_missing = [];

if ( preg_match_all( '/class="([^"]+)"/', $blur_markup, $m ) ) {
	foreach ( $m[1] as $attr ) {
		foreach ( preg_split( '/\s+/', trim( $attr ) ) as $cls ) {
			if ( '' !== (string) $cls && false === strpos( $blur_css, '.' . $cls ) ) {
				$blur_missing[] = $cls;
			}
		}
	}
}

ok( ! $blur_missing, 'toda classe da faixa tem regra no blur.css', implode( ', ', array_unique( $blur_missing ) ) );

$blur_on();

ok( [ 'bottom' ] === WFAN_Blur::edges(), 'o rodapé é a borda padrão' );

$blur_on( [ 'blur_off_mobile' => '1', 'mobile_bp' => '600' ] );

ok(
	false !== strpos( WFAN_Blur::inline_css(), '@media (max-width:600px){.wfan-blur{display:none}}' ),
	'desligar em telas pequenas usa a largura limite dos Ajustes',
	WFAN_Blur::inline_css()
);

section( '10. Abas da tela de ajustes' );

$tabs = WFAN_Settings::tabs();

ok( count( $tabs ) > 1, 'a tela tem mais de uma aba' );

$tab_missing = [];

foreach ( array_keys( $tabs ) as $tab_key ) {
	if ( ! file_exists( WFAN_DIR . 'templates/admin/tabs/tab-' . $tab_key . '.php' ) ) {
		$tab_missing[] = $tab_key;
	}
}

ok( ! $tab_missing, 'toda aba tem o seu template', implode( ', ', $tab_missing ) );

/*
 * Toggle fora de qualquer aba nunca é gravado pelo formulário, e toggle em
 * duas abas é gravado por uma e apagado pela outra. Os toggles se
 * reconhecem nos padrões por serem os únicos com valor '0' ou '1'.
 */
$flags = array_keys(
	array_filter(
		WFAN_Settings::defaults(),
		static function ( $value ) {
			return in_array( (string) $value, [ '0', '1' ], true );
		}
	)
);

$mapped  = WFAN_Settings::toggles();
$orphans = array_diff( $flags, $mapped );
$ghosts  = array_diff( $mapped, $flags );

ok( ! $orphans, 'todo toggle dos padrões pertence a uma aba', implode( ', ', $orphans ) );
ok( ! $ghosts, 'toda aba só reivindica toggle que existe nos padrões', implode( ', ', $ghosts ) );
ok( count( $mapped ) === count( array_unique( $mapped ) ), 'nenhum toggle aparece em duas abas' );

// O que o formulário por aba quebraria se o recorte do sanitize() sumisse.
update_option( WFAN_Settings::OPTION, array_merge( WFAN_Settings::defaults(), [ 'lib_gsap' => '1', 'blur_enable' => '1', 'debug' => '1' ] ) );
WFAN_Settings::flush();

$saved_blur = $settings->sanitize( [ '_tab' => 'blur', 'blur_height' => '30' ] );

ok( '0' === $saved_blur['blur_enable'], 'checkbox desmarcado na aba enviada é desligado (regra 12)' );
ok( '1' === $saved_blur['lib_gsap'], 'salvar a aba do blur não desliga as bibliotecas' );
ok( '1' === $saved_blur['debug'], 'salvar a aba do blur não mexe no comportamento' );
ok( '30' === $saved_blur['blur_height'], 'o campo enviado é gravado' );
ok( '767' === $saved_blur['mobile_bp'], 'campo de outra aba mantém o valor gravado' );
ok( ! isset( $saved_blur['_tab'] ), 'o campo de controle _tab não vai para o banco' );

$shell = (string) file_get_contents( WFAN_DIR . 'templates/admin/settings-page.php' );

ok( false !== strpos( $shell, '[_tab]' ), 'a casca imprime o campo oculto que identifica a aba' );
ok( false !== strpos( $shell, 'wooflow-main-nav' ), 'a casca imprime a navegação' );

// Regra 16: o ícone de aviso depende da fonte de glifos do WordPress, e o
// reset de fonte do painel a derruba se não houver resgate explícito.
ok(
	false !== strpos( $admin_css, 'font-family: dashicons' ),
	'o reset de fonte do painel poupa os Dashicons'
);

section( '11. Preset resolvido só em CSS' );

/**
 * Condição simples do Elementor, do jeito que o is_control_visible() avalia:
 * chave terminada em `!` nega, e valor em array vira "está na lista".
 */
function wfan_visible( $control, $values ) {
	foreach ( $control['condition'] ?? [] as $key => $expected ) {
		$negative = '!' === substr( $key, -1 );
		$name     = rtrim( $key, '!' );
		$value    = $values[ $name ] ?? '';
		$hit      = is_array( $expected ) ? in_array( $value, $expected, true ) : $value === $expected;

		if ( $negative === $hit ) {
			return false;
		}
	}

	return true;
}

ok( in_array( 'hover-invert', WFAN_Presets::ids_css_only(), true ), 'hover-invert é declarado css_only' );

// Motor herdado de um copiar/colar não pode arrastar uma biblioteca para a
// página: preset de estado não tem motor para trocar.
$invert = render_with( new WFAN_Fake_Element( 'container', 'container', [
	WFAN_Keys::ours( 'preset' )  => 'hover-invert',
	WFAN_Keys::ours( 'engine' )  => 'gsap',
	WFAN_Keys::ours( 'trigger' ) => 'scroll-in',
] ) );

$ip = $invert->payload();

ok( 'css' === ( $ip['eng'] ?? '' ), 'motor forçado é ignorado no preset de estado' );
ok( 1 === ( $ip['co'] ?? 0 ), 'o payload avisa o core.js que já está resolvido em CSS' );
ok( 'hover' === ( $ip['tr']['t'] ?? '' ), 'gatilho inválido cai no hover' );
ok( ! in_array( 'wfan-pending', $invert->classes(), true ), 'preset de estado não esconde o elemento' );

$cssOnlyAssets = new WFAN_Assets();
$cssOnlyAssets->register();
WFAN_Test_Hooks::$enqueued = [];
$cssOnlyAssets->require_style();
$cssOnlyAssets->enqueue_needed();

ok( in_array( 'wfan-frontend', WFAN_Test_Hooks::$enqueued, true ), 'a folha do front-end entra (rede do reduced-motion)' );
ok( ! in_array( 'wfan-core', WFAN_Test_Hooks::$enqueued, true ), 'nenhum byte de JS entra por um preset de estado' );

// A cor precisa estar presa ao hover e ao elemento: seletor solto pintaria o
// bloco o tempo todo, e sem o {{WRAPPER}}:hover a regra perde em especificidade
// para a cor que o Elementor escreve em cada widget.
$loose   = [];
$no_test = [];

foreach ( [ 'hover_bg', 'hover_title', 'hover_accent', 'hover_text', 'hover_icon' ] as $slot ) {
	$control = $widget->controls[ $k( $slot ) ] ?? null;

	foreach ( ( $control['selectors'] ?? [] ) as $selector => $css ) {
		if ( false === strpos( $selector, '{{WRAPPER}}:hover' ) || false === strpos( $css, '{{VALUE}}' ) ) {
			$loose[] = $slot;
		}

		// Sem o estado por classe o ▶ Testar não teria o que ligar: `:hover`
		// não se simula, e o botão só mostraria o toast.
		if ( false === strpos( $selector, '{{WRAPPER}}.' . WFAN_Controls::HOVER_PREVIEW_CLASS ) ) {
			$no_test[] = $slot;
		}
	}

	if ( ! $control || empty( $control['selectors'] ) ) {
		$loose[] = $slot . ' (sem selectors)';
	}
}

ok( ! $loose, 'toda cor sai de um seletor {{WRAPPER}}:hover', implode( ', ', array_unique( $loose ) ) );
ok( ! $no_test, 'toda cor também responde à classe do ▶ Testar', implode( ', ', array_unique( $no_test ) ) );

/*
 * Estar preso ao `:hover` não basta: a nossa regra tem de GANHAR da que o
 * Elementor escreve para o mesmo elemento, e empate aqui é decidido pela ordem
 * no arquivo — que muda de contexto. No front-end o Elementor desvia todo
 * controle com `selectors` para um balde à parte e o anexa no fim, então as
 * nossas regras saem por último e qualquer empate passa despercebido; no
 * editor e na requisição que salva a página elas saem primeiro, e o empate
 * vira defeito. Foi assim que o fundo deixou de inverter em todo quadro, com o
 * título invertendo: branco sobre branco.
 *
 * Então o teste não olha a ordem, olha o peso: cada ramo tem de pesar mais que
 * a regra mais pesada que o Elementor escreve nestes alvos.
 */
$weigh = static function ( $selector ) {
	$selector = str_replace( '{{WRAPPER}}', '.elementor-9 .elementor-element.elementor-element-abc', $selector );

	// `:not(X)` não conta por si: vale exatamente o que está dentro dele.
	$selector = preg_replace( '/:not\(([^)]*)\)/', '$1', $selector );

	return [
		preg_match_all( '/#[\w-]+/', $selector ),
		preg_match_all( '/\.[\w-]+|\[[^\]]*\]|:[\w-]+(?:\([^)]*\))?/', $selector ),
		preg_match_all( '/(?<![\w.#:[-])[a-z][\w-]*/i', preg_replace( '/\.[\w-]+|\[[^\]]*\]|:[\w-]+(?:\([^)]*\))?|#[\w-]+/', ' ', $selector ) ),
	];
};

// A mais pesada que o Elementor 4.3.4 escreve por elemento nestes alvos: a cor
// do ícone, em `…-{id}.elementor-view-default .elementor-icon`.
$rival = $weigh( '{{WRAPPER}}.elementor-view-default .elementor-icon' );

// E, no estado normal, a transição do próprio elemento: `{{WRAPPER}}` puro.
$rival_plain = $weigh( '{{WRAPPER}}' );

$light = [];

foreach ( [ 'hover_bg', 'hover_title', 'hover_accent', 'hover_text', 'hover_icon', 'hover_dur' ] as $slot ) {
	$control = $widget->controls[ $k( $slot ) ] ?? null;
	$against = 'hover_dur' === $slot ? $rival_plain : $rival;

	foreach ( array_keys( $control['selectors'] ?? [] ) as $selector ) {
		foreach ( explode( ',', $selector ) as $branch ) {
			if ( $weigh( trim( $branch ) ) <= $against ) {
				$light[] = $slot . ': ' . trim( $branch );
			}
		}
	}
}

ok( ! $light, 'toda regra da inversão pesa mais que a do Elementor, sem depender da ordem', implode( ' | ', $light ) );

/*
 * Ícone de fonte obedece à cor herdada, mas SVG com `fill` no próprio arquivo
 * ignora `color`, e um `fill` no `<svg>` não alcança um `<path fill="…">` —
 * presentation attribute ganha da herança. Sem as duas coisas, o ícone fica
 * parado enquanto o resto do bloco inverte.
 */
$icon_control = $widget->controls[ $k( 'hover_icon' ) ]['selectors'] ?? [];
$icon_css     = implode( ' ', $icon_control );
$icon_sel     = (string) array_key_first( $icon_control ? $icon_control : [ '' => '' ] );

ok( false !== strpos( $icon_css, 'fill: {{VALUE}}' ), 'a cor do ícone escreve fill, não só color', $icon_css );
ok( false !== strpos( $icon_sel, 'svg *' ), 'a cor do ícone alcança os nós internos do SVG', $icon_sel );

// Classe escrita no PHP e procurada no JS: se os dois nomes divergirem, o
// botão não faz nada e ninguém vê erro nenhum.
ok(
	false !== strpos( $core, "'" . WFAN_Controls::HOVER_PREVIEW_CLASS . "'" ),
	'o core.js conhece a classe de estado pelo mesmo nome',
	WFAN_Controls::HOVER_PREVIEW_CLASS
);

// A duração que o ▶ Testar usa para segurar o estado tem de ser a da
// transição, não os 800 ms do controle genérico — que neste preset está
// escondido e chega como null.
$timed_invert = render_with( new WFAN_Fake_Element( 'container', 'container', [
	WFAN_Keys::ours( 'preset' )    => 'hover-invert',
	WFAN_Keys::ours( 'hover_dur' ) => [ 'unit' => 'px', 'size' => 500 ],
	WFAN_Keys::ours( 'duration' )  => null,
] ) );

ok( 500 === ( $timed_invert->payload()['d'] ?? 0 ), 'a duração do payload é a da transição' );

$speed = $widget->controls[ $k( 'hover_dur' ) ]['selectors'] ?? [];
$speed_css = implode( ' ', $speed );

ok(
	false !== strpos( $speed_css, 'transition-duration: {{SIZE}}ms' ),
	'a velocidade escreve a transição',
	$speed_css
);
ok(
	false === strpos( (string) array_key_first( $speed ), ':hover' ),
	'a transição é declarada fora do hover, senão não há volta suave'
);
ok(
	false !== strpos( $speed_css, 'fill' ) && false !== strpos( (string) array_key_first( $speed ), 'svg' ),
	'a transição cobre o fill e alcança o SVG, senão o ícone troca de cor de uma vez',
	$speed_css
);

// Controle que só o JS obedece não pode aparecer num preset sem JS, e o
// contrário também vale: cor de hover não aparece em preset de entrada.
$as_invert = [ $k( 'preset' ) => 'hover-invert', $k( 'trigger' ) => 'hover' ];
$as_fade   = [ $k( 'preset' ) => 'fade-up', $k( 'trigger' ) => 'scroll-in' ];

$leaked = [];

foreach ( [ 'duration', 'delay', 'easing', 'off_mobile', 'engine', 'native_warning' ] as $slot ) {
	$values = $as_invert;

	if ( 'native_warning' === $slot ) {
		$values[ WFAN_Keys::NATIVE_WIDGET['name'] ] = 'fadeIn';
	}

	if ( wfan_visible( $widget->controls[ $k( $slot ) ], $values ) ) {
		$leaked[] = $slot;
	}
}

ok( ! $leaked, 'o preset de estado esconde o que só o JS obedece', implode( ', ', $leaked ) );

// Contraprova: num preset de entrada esses mesmos controles continuam lá.
$hidden_too_much = [];

foreach ( [ 'duration', 'delay', 'easing', 'off_mobile', 'engine' ] as $slot ) {
	if ( ! wfan_visible( $widget->controls[ $k( $slot ) ], $as_fade ) ) {
		$hidden_too_much[] = $slot;
	}
}

ok( ! $hidden_too_much, 'a exclusão não alcança os presets com motor', implode( ', ', $hidden_too_much ) );

$missing_color = [];
$extra_color   = [];

foreach ( [ 'hover_dur', 'hover_bg', 'hover_title', 'hover_accent', 'hover_text', 'hover_icon' ] as $slot ) {
	if ( ! wfan_visible( $widget->controls[ $k( $slot ) ], $as_invert ) ) {
		$missing_color[] = $slot;
	}

	if ( wfan_visible( $widget->controls[ $k( $slot ) ], $as_fade ) ) {
		$extra_color[] = $slot;
	}
}

ok( ! $missing_color, 'o preset de estado mostra os controles de cor', implode( ', ', $missing_color ) );
ok( ! $extra_color, 'preset de entrada não mostra controle de cor de hover', implode( ', ', $extra_color ) );

section( '12. Contrato do ▶ Testar' );

/*
 * No canvas do editor o wrapper é construído pelo Backbone do Elementor
 * (`BaseElementView.attributes()`), que não reproduz os atributos do `_wrapper`
 * do PHP: o `print_element()` só roda no front-end e na carga inicial do
 * preview. Por isso o painel pede o contrato ao servidor antes de mandar tocar
 * — e é o mesmo contrato, não uma segunda implementação em JavaScript.
 */
$preview_settings = [
	WFAN_Keys::ours( 'preset' )   => 'fade-up',
	WFAN_Keys::ours( 'trigger' )  => 'scroll-in',
	WFAN_Keys::ours( 'duration' ) => [ 'unit' => 'px', 'size' => 600 ],
	WFAN_Keys::ours( 'distance' ) => [ 'unit' => 'px', 'size' => 55 ],
];

$rendered = render_with( new WFAN_Fake_Element( 'common', 'widget', $preview_settings ) );
$from_api = ( new WFAN_Render() )->spec( $preview_settings, false );

ok( is_array( $from_api ), 'o contrato sai sem elemento nenhum, só com os ajustes' );
ok(
	$rendered->payload() === ( $from_api['payload'] ?? null ),
	'o ▶ Testar recebe exatamente o payload que o front-end receberia'
);
ok(
	! in_array( 'wfan-pending', $from_api['classes'] ?? [], true ),
	'no editor o elemento nunca começa escondido'
);
ok(
	in_array( 'wfan-pending', $rendered->classes(), true ),
	'e no front-end o pré-esconde continua valendo'
);

ok(
	null === ( new WFAN_Render() )->spec( [], false ),
	'elemento sem preset não gera contrato de preview'
);

// Ação ajax registrada e com o mesmo nome dos dois lados: divergir aqui é
// botão que responde "Não foi possível concluir" sem explicação.
ok(
	isset( WFAN_Test_Hooks::$actions[ 'wp_ajax_' . WFAN_Editor::PREVIEW_ACTION ] ),
	'a ação ajax do ▶ Testar está registrada'
);

$panel_js = (string) file_get_contents( WFAN_DIR . 'assets/js/editor/panel.js' );

ok(
	false !== strpos( $panel_js, 'cfg.previewAction' )
	&& false !== strpos( (string) file_get_contents( WFAN_DIR . 'includes/class-editor.php' ), "'previewAction'" ),
	'o painel usa o nome da ação que o PHP publica, nunca um literal solto'
);

// O preview entrega o contrato pelo argumento; sem isso o core.js cairia no
// data-wfan, que no editor não existe.
ok(
	false !== strpos( $core, 'window.wfanPlay = function ( id, spec )' ),
	'o core.js aceita o contrato pronto no ▶ Testar'
);
ok(
	false !== strpos( $panel_js, 'win.wfanPlay( item.id, item.spec )' ),
	'o painel entrega o contrato ao preview'
);

section( '13. Preview de cada preset no painel' );

/*
 * Regra 14: o card entra sozinho na grade, mas sem a regra de preview ele fica
 * parado no hover — que é justamente o motivo de o painel existir.
 */
$editor_css = (string) file_get_contents( WFAN_DIR . 'assets/css/editor.css' );
$no_preview = [];

foreach ( array_keys( WFAN_Presets::all() ) as $preset_id ) {
	if ( false === strpos( $editor_css, '.wfan-pv-' . $preset_id ) ) {
		$no_preview[] = $preset_id;
	}
}

ok( ! $no_preview, 'todo preset tem regra de preview no editor.css', implode( ', ', $no_preview ) );

/*
 * As nativas são a exceção da regra abaixo: o preview delas usa os @keyframes
 * do próprio Elementor, enfileirados no painel — copiá-los para cá seria uma
 * segunda cópia envelhecendo sozinha. O que o teste cobra é o contrário: que o
 * nome citado no CSS exista no catálogo nativo, e que alguém enfileire a folha
 * que o define.
 */
$native_names = array_keys( WFAN_Presets::native_catalog() );
$no_keyframes = [];

if ( preg_match_all( '/animation-name:\s*([a-zA-Z0-9-]+)/', $editor_css, $m ) ) {
	foreach ( array_unique( $m[1] ) as $name ) {
		if ( in_array( $name, $native_names, true ) ) {
			continue;
		}

		if ( false === strpos( $editor_css, '@keyframes ' . $name ) ) {
			$no_keyframes[] = $name;
		}
	}
}

ok( ! $no_keyframes, 'toda animação de preview tem @keyframes', implode( ', ', $no_keyframes ) );

$assets_php = (string) file_get_contents( WFAN_DIR . 'includes/class-assets.php' );

ok(
	false !== strpos( $assets_php, 'animations.min.css' )
	&& false !== strpos( $assets_php, 'self::NATIVE_STYLE . $name' ),
	'o registry enfileira os @keyframes das nativas, com as duas saídas'
);
ok(
	false !== strpos( (string) file_get_contents( WFAN_DIR . 'includes/class-editor.php' ), 'WFAN_Assets::enqueue_native_keyframes' ),
	'e o painel pede ao registry, nunca enfileira por conta (regra 5)'
);

$pv_native = [];

foreach ( $native_names as $name ) {
	if ( false === strpos( $editor_css, 'animation-name: ' . $name . ';' ) ) {
		$pv_native[] = $name;
	}
}

ok( ! $pv_native, 'todo card nativo aponta para a animação do Elementor', implode( ', ', $pv_native ) );

section( '14. Fundo animado por elemento' );

/*
 * O fundo animado não é preset: tem seção própria, convive com uma animação de
 * entrada no mesmo elemento e não passa pelo data-wfan. Todo parâmetro dele
 * viaja em custom property escrita pelo Elementor a partir dos `selectors` —
 * é o que o mantém vivo depois do re-render do editor, que apaga os atributos
 * do PHP (regra 19).
 */
$ovl_section = WFAN_Keys::P . WFAN_Overlay::SECTION;

ok( isset( $widget->sections[ $ovl_section ] ), 'a seção do fundo animado existe' );
ok(
	'content' === ( $widget->sections[ $ovl_section ]['tab'] ?? '' ),
	'widget: fundo animado na aba Conteúdo',
	$widget->sections[ $ovl_section ]['tab'] ?? '(sem aba)'
);
ok(
	'layout' === ( $block->sections[ $ovl_section ]['tab'] ?? '' ),
	'bloco: fundo animado na aba Layout',
	$block->sections[ $ovl_section ]['tab'] ?? '(sem aba)'
);

// Os controles do fundo só aparecem com o toggle ligado, e o toggle aparece
// sempre — inclusive sem animação escolhida, que é o ponto do módulo.
$ovl_slots = [ 'ovl_c1', 'ovl_c2', 'ovl_speed', 'ovl_size', 'ovl_opacity', 'ovl_blend' ];
$ovl_loose = [];
$ovl_stuck = [];

foreach ( $ovl_slots as $slot ) {
	if ( wfan_visible( $widget->controls[ $k( $slot ) ] ?? [], [] ) ) {
		$ovl_loose[] = $slot;
	}

	if ( ! wfan_visible( $widget->controls[ $k( $slot ) ] ?? [], [ $k( 'ovl' ) => 'yes' ] ) ) {
		$ovl_stuck[] = $slot;
	}
}

ok( ! $ovl_loose, 'nenhum controle do fundo aparece com o toggle desligado', implode( ', ', $ovl_loose ) );
ok( ! $ovl_stuck, 'todos aparecem com o toggle ligado', implode( ', ', $ovl_stuck ) );
ok( empty( $widget->controls[ $k( 'ovl' ) ]['condition'] ), 'o toggle não depende de preset nenhum' );

/*
 * Uma declaração por controle, sempre com o nome da propriedade antes do
 * primeiro `:`. Com uma cor global escolhida, o Elementor troca tudo depois
 * desse `:` pelo valor global — uma segunda declaração vizinha viraria lixo.
 * Ver regra 18.
 */
$ovl_controls = [
	'ovl'         => 'on',
	'ovl_c1'      => 'c1',
	'ovl_c2'      => 'c2',
	'ovl_speed'   => 'speed',
	'ovl_size'    => 'size',
	'ovl_opacity' => 'opacity',
	'ovl_blend'   => 'blend',
];

$ovl_bad = [];

foreach ( $ovl_controls as $slot => $var ) {
	$selectors = $widget->controls[ $k( $slot ) ]['selectors'] ?? [];

	if ( 1 !== count( $selectors ) || '{{WRAPPER}}' !== array_key_first( $selectors ) ) {
		$ovl_bad[] = $slot . ' (seletor)';
		continue;
	}

	$css = (string) reset( $selectors );

	if ( 0 !== strpos( $css, WFAN_Overlay::VARS[ $var ] . ': ' ) ) {
		$ovl_bad[] = $slot . ' (propriedade)';
	}

	if ( 1 !== substr_count( $css, ';' ) ) {
		$ovl_bad[] = $slot . ' (mais de uma declaração)';
	}
}

ok( ! $ovl_bad, 'cada controle do fundo escreve uma só custom property no {{WRAPPER}}', implode( ', ', $ovl_bad ) );

// Valor de opção que pareça inteiro vira int na chave do array e sai sem as
// casas decimais no CSS.
$ovl_ints = [];

foreach ( array_merge( array_keys( WFAN_Overlay::speeds() ), array_keys( WFAN_Overlay::sizes() ) ) as $value ) {
	if ( is_int( $value ) ) {
		$ovl_ints[] = (string) $value;
	}
}

ok( ! $ovl_ints, 'nenhum valor de velocidade ou tamanho vira inteiro', implode( ', ', $ovl_ints ) );
ok(
	isset( WFAN_Overlay::speeds()[ WFAN_Overlay::DEFAULTS['speed'] ] )
	&& isset( WFAN_Overlay::sizes()[ WFAN_Overlay::DEFAULTS['size'] ] )
	&& isset( WFAN_Overlay::blends()[ WFAN_Overlay::DEFAULTS['blend'] ] ),
	'os padrões existem entre as opções'
);

/*
 * Contrato nas três pontas. As cores são o caso especial: lidas direto da
 * custom property, viriam como o texto cru — com uma cor global do Elementor,
 * literalmente "var(--e-global-color-x)". Por isso o CSS as aplica a `color` e
 * `outline-color` do canvas e o JS lê de lá, já resolvidas.
 */
$ovl_js  = (string) file_get_contents( WFAN_DIR . 'assets/js/frontend/overlay.js' );
$ovl_css = (string) file_get_contents( WFAN_DIR . 'assets/css/frontend.css' );
$ovl_both = $ovl_js . $ovl_css;
$ovl_unread = [];

foreach ( WFAN_Overlay::VARS as $slot => $var ) {
	if ( false === strpos( $ovl_both, $var ) ) {
		$ovl_unread[] = $slot;
	}
}

ok( ! $ovl_unread, 'toda custom property do fundo é lida no JS ou no CSS', implode( ', ', $ovl_unread ) );

$ovl_unsent = [];

if ( preg_match_all( '/--wfan-ovl[a-z0-9-]*/', $ovl_both, $m ) ) {
	$ovl_unsent = array_diff( array_unique( $m[0] ), array_values( WFAN_Overlay::VARS ) );
}

ok( ! $ovl_unsent, 'nenhuma custom property do fundo sem dono no PHP', implode( ', ', $ovl_unsent ) );

ok(
	false !== strpos( $ovl_css, 'color: var( ' . WFAN_Overlay::VARS['c1'] )
	&& false !== strpos( $ovl_css, 'outline-color: var( ' . WFAN_Overlay::VARS['c2'] ),
	'o CSS resolve as duas cores em propriedades de cor de verdade'
);
ok(
	false !== strpos( $ovl_js, 'own.color' ) && false !== strpos( $ovl_js, 'own.outlineColor' ),
	'o JS lê as cores já resolvidas, nunca o texto cru da custom property'
);
ok(
	false === strpos( $ovl_js, WFAN_Overlay::VARS['c1'] ) && false === strpos( $ovl_js, WFAN_Overlay::VARS['c2'] ),
	'nenhuma cor é lida direto da custom property no JS'
);

// Nome de classe é contrato entre as três pontas: divergir aqui é um fundo que
// nunca aparece, sem erro nenhum.
ok(
	false !== strpos( $ovl_css, '.' . WFAN_Overlay::CANVAS_CLASS )
	&& false !== strpos( $ovl_js, "'" . WFAN_Overlay::CANVAS_CLASS . "'" ),
	'a classe do canvas tem o mesmo nome no CSS e no JS',
	WFAN_Overlay::CANVAS_CLASS
);
ok(
	false !== strpos( $ovl_js, "'" . WFAN_Overlay::HOST_CLASS . "'" ),
	'a classe do elemento tem o mesmo nome no PHP e no JS',
	WFAN_Overlay::HOST_CLASS
);

// Render: o fundo existe sem animação nenhuma, e um elemento sem o toggle não
// ganha classe nem script.
$only_bg = render_with( new WFAN_Fake_Element( 'container', 'container', [
	WFAN_Keys::ours( 'ovl' )    => 'yes',
	WFAN_Keys::ours( 'ovl_c1' ) => '#112233',
] ) );

ok( in_array( WFAN_Overlay::HOST_CLASS, $only_bg->classes(), true ), 'o fundo marca o elemento sem preset nenhum' );
ok( null === $only_bg->payload(), 'e não inventa um contrato de animação' );

$both = render_with( new WFAN_Fake_Element( 'common', 'widget', [
	WFAN_Keys::ours( 'preset' ) => 'fade-up',
	WFAN_Keys::ours( 'ovl' )    => 'yes',
] ) );

ok(
	in_array( WFAN_Overlay::HOST_CLASS, $both->classes(), true ) && 'fade-up' === ( $both->payload()['p'] ?? '' ),
	'animação de entrada e fundo animado convivem no mesmo elemento'
);

$no_bg = render_with( new WFAN_Fake_Element( 'common', 'widget', [ WFAN_Keys::ours( 'preset' ) => 'fade-up' ] ) );

ok( ! in_array( WFAN_Overlay::HOST_CLASS, $no_bg->classes(), true ), 'elemento sem o toggle não ganha a classe' );

$ovlAssets = new WFAN_Assets();
$ovlAssets->register();
WFAN_Test_Hooks::$enqueued = [];
$ovlAssets->require_overlay();
$ovlAssets->enqueue_needed();

ok( in_array( 'wfan-overlay', WFAN_Test_Hooks::$enqueued, true ), 'o fundo enfileira o próprio script' );
ok( in_array( 'wfan-frontend', WFAN_Test_Hooks::$enqueued, true ), 'e a folha que posiciona o canvas' );
ok( ! in_array( 'wfan-core', WFAN_Test_Hooks::$enqueued, true ), 'sem arrastar o core nem motor nenhum' );
ok( [] === ( WFAN_Test_Hooks::$scripts['wfan-overlay']['deps'] ?? null ), 'o script do fundo não depende de biblioteca' );

$quiet = new WFAN_Assets();
$quiet->register();
WFAN_Test_Hooks::$enqueued = [];
$quiet->enqueue_needed();

ok( ! in_array( 'wfan-overlay', WFAN_Test_Hooks::$enqueued, true ), 'página sem fundo animado não carrega o script' );

// Regra 17 também aqui: o overlay.js testa cfg.offMobile e cfg.reduced por
// veracidade, e "0" é verdadeiro no JavaScript.
WFAN_Test_Hooks::$inline_js = [];
( new WFAN_Assets() )->register();

$ovl_cfg_raw = WFAN_Test_Hooks::$inline_js['wfan-overlay'] ?? '';
$ovl_cfg     = json_decode( (string) preg_replace( '/^var wfanOverlayConfig = |;$/', '', trim( $ovl_cfg_raw ) ), true );

ok( is_array( $ovl_cfg ), 'a configuração do fundo é JSON válido', $ovl_cfg_raw );

foreach ( [ 'reduced', 'offMobile', 'debug', 'editor' ] as $flag ) {
	ok(
		is_bool( $ovl_cfg[ $flag ] ?? null ),
		"wfanOverlayConfig.$flag chega como booleano, nunca \"0\"",
		var_export( $ovl_cfg[ $flag ] ?? null, true )
	);
}

section( '15. Animação de entrada do Elementor' );

/*
 * Regra 21: o preset do grupo "Nativas" não escreve data-wfan, não pede motor
 * e não põe um byte de JavaScript nosso na página — ele preenche a chave de
 * animação do Elementor antes de o wrapper ser montado, e sai de cena.
 */
$nat_id = WFAN_Presets::NATIVE_PREFIX . 'fadeInUp';

ok( null !== WFAN_Presets::get( $nat_id ), 'o catálogo tem o grupo das nativas' );
ok( 'fadeInUp' === WFAN_Presets::native_name( $nat_id ), 'o id carrega o nome da animação do Elementor' );
ok( in_array( $nat_id, WFAN_Presets::ids_native(), true ), 'e ele é listado como nativo' );

$nat_widget = render_with( new WFAN_Fake_Element( 'common', 'widget', [
	WFAN_Keys::ours( 'preset' ) => $nat_id,
	WFAN_Keys::ours( 'delay' )  => [ 'unit' => 'px', 'size' => 300 ],
] ) );

$nat_settings = $nat_widget->get_settings();

ok( null === $nat_widget->payload(), 'preset nativo não escreve data-wfan no front-end' );
ok(
	'fadeInUp' === ( $nat_settings[ WFAN_Keys::NATIVE_WIDGET['name'] ] ?? '' ),
	'widget: a animação vai para a chave nativa do Elementor'
);
ok(
	300 === ( $nat_settings[ WFAN_Keys::NATIVE_WIDGET['delay'] ] ?? 0 ),
	'a espera vai junto, em milissegundos'
);
ok(
	in_array( WFAN_Controls::NATIVE_CLASS, $nat_widget->classes(), true ),
	'a classe que ancora duração e curva está no wrapper'
);
ok(
	! in_array( 'wfan-pending', $nat_widget->classes(), true ),
	'sem pré-esconde: quem esconde é o elementor-invisible'
);

$nat_block = render_with( new WFAN_Fake_Element( 'container', 'container', [
	WFAN_Keys::ours( 'preset' ) => WFAN_Presets::NATIVE_PREFIX . 'zoomIn',
] ) );

ok(
	'zoomIn' === ( $nat_block->get_settings()[ WFAN_Keys::NATIVE_BLOCK['name'] ] ?? '' ),
	'bloco: a chave nativa é a sem underscore (regra 1)'
);

$nat_mobile = render_with( new WFAN_Fake_Element( 'common', 'widget', [
	WFAN_Keys::ours( 'preset' )     => $nat_id,
	WFAN_Keys::ours( 'off_mobile' ) => 'yes',
] ) );

ok(
	'none' === ( $nat_mobile->get_settings()[ WFAN_Keys::NATIVE_WIDGET['mobile'] ] ?? '' ),
	'"desligar no celular" vira a versão responsiva da chave nativa'
);

// O ponto da regra: nenhum asset nosso entra por causa de uma nativa.
$nat_assets = new WFAN_Assets();
$nat_assets->register();
WFAN_Test_Hooks::$enqueued = [];
$nat_assets->require_native( 'fadeInUp' );
$nat_assets->enqueue_needed();

ok( ! in_array( 'wfan-core', WFAN_Test_Hooks::$enqueued, true ), 'nativa não carrega o core.js' );
ok( ! in_array( 'wfan-frontend', WFAN_Test_Hooks::$enqueued, true ), 'nem a folha do front-end' );
ok( ! in_array( 'wfan-engine-css', WFAN_Test_Hooks::$enqueued, true ), 'nem motor nenhum' );

// Duração e curva exatas: o painel nativo só oferece lento/normal/rápido, e a
// nossa regra tem de ganhar da do Elementor sem !important — daí a classe
// junto do {{WRAPPER}}.
$dur_selectors = $widget->controls[ WFAN_Keys::ours( 'duration' ) ]['selectors'] ?? [];
$ease_control  = $widget->controls[ WFAN_Keys::ours( 'easing' ) ] ?? [];
$dur_selector  = (string) array_key_first( $dur_selectors );

ok(
	false !== strpos( $dur_selector, '{{WRAPPER}}.' . WFAN_Controls::NATIVE_CLASS . '.animated' ),
	'a duração é escrita presa ao preset nativo, nunca ao elemento inteiro',
	$dur_selector
);
ok(
	isset( $ease_control['selectors_dictionary']['power2.out'] ),
	'a curva tem dicionário: o nome GSAP não diz nada ao CSS'
);

// Classe escrita no PHP e procurada no JS. Se divergirem, o ▶ Testar de uma
// nativa não reproduz nada e ninguém vê erro.
ok(
	false !== strpos( $core, "var NATIVE = '" . WFAN_Controls::NATIVE_CLASS . "'" ),
	'o core.js usa o mesmo nome de classe do PHP'
);
ok( false !== strpos( $core, 'function playNative' ), 'o core.js sabe tocar uma nativa no editor' );

// Preset nativo não aceita gatilho que o Elementor não tem.
ok(
	[ 'scroll-in' ] === ( WFAN_Presets::get( $nat_id )['triggers'] ?? [] ),
	'nativa só aceita "ao entrar na tela"'
);

$hidden_for_native = [ 'viewport', 'once' ];

foreach ( $hidden_for_native as $suffix ) {
	$excluded = $widget->controls[ WFAN_Keys::ours( $suffix ) ]['condition'][ WFAN_Keys::ours( 'preset' ) . '!' ] ?? [];

	ok(
		in_array( $nat_id, (array) $excluded, true ),
		"o controle $suffix some na nativa, que não o obedece"
	);
}

section( '16. Scroll travado: início e fim' );

$scrub = ( new WFAN_Render() )->spec( [
	WFAN_Keys::ours( 'preset' )      => 'scrub-reveal',
	WFAN_Keys::ours( 'trigger' )     => 'scroll-scrub',
	WFAN_Keys::ours( 'scrub_start' ) => 'center',
	WFAN_Keys::ours( 'scrub_end' )   => 'pct',
	WFAN_Keys::ours( 'scrub_pct' )   => [ 'unit' => '%', 'size' => 150 ],
] );

ok( 'center' === ( $scrub['payload']['tr']['start'] ?? '' ), 'o ponto de início viaja no contrato' );
ok( 'pct' === ( $scrub['payload']['tr']['end'] ?? '' ), 'o fim por porcentagem também' );
ok( 150 === ( $scrub['payload']['tr']['pct'] ?? 0 ), 'e a porcentagem escolhida' );

$gsap_js = (string) file_get_contents( WFAN_DIR . 'assets/js/frontend/engine-gsap.js' );

ok( false !== strpos( $gsap_js, 'function startFor' ), 'o motor do scroll lê o ponto de início' );
ok( false !== strpos( $gsap_js, "'+=' + Math.max( 10, spec.pct ) + '%'" ), 'e transforma a porcentagem em distância de rolagem' );
ok( false !== strpos( $gsap_js, 'killTriggers' ), 'e desamarra o gatilho anterior antes de criar outro' );

$scrub_options = $widget->controls[ WFAN_Keys::ours( 'scrub_end' ) ]['options'] ?? [];

ok( isset( $scrub_options['pct'] ), 'a opção de porcentagem está no painel' );
ok( isset( $widget->controls[ WFAN_Keys::ours( 'scrub_pct' ) ] ), 'com o campo que diz quanto' );

section( '17. Painel ao vivo' );

/*
 * Opção escondida deixa estado no DOM, e o DOM do painel sobrevive à troca de
 * elemento: era isso que fazia sumirem opções do "Gatilho" até recarregar o
 * editor. A lista é reescrita inteira a cada passada.
 */
ok(
	false === strpos( $panel_js, "prop( 'hidden'" ),
	'a lista de gatilhos não esconde opção: é reescrita'
);
ok(
	false !== strpos( $panel_js, "data-wfan-triggers" ),
	'e a reescrita é idempotente, marcada no próprio select'
);
ok(
	false !== strpos( $panel_js, 'function schedulePreview' ),
	'mexer num controle nosso reproduz a animação sozinho'
);
ok(
	false !== strpos( $panel_js, 'function scheduleOverlay' )
	&& false !== strpos( (string) file_get_contents( WFAN_DIR . 'assets/js/frontend/overlay.js' ), 'function syncId' ),
	'ligar o fundo animado mostra o fundo na hora, sem ida ao servidor'
);
ok(
	false !== strpos( $panel_js, 'specs[ key ]' ),
	'o contrato já pedido não é pedido de novo'
);

/*
 * Controle sem `render_type` faz o Elementor re-renderizar o widget inteiro
 * por Ajax a cada mexida — era isso que deixava o painel lento e piscava em
 * cima do preview. Nenhum controle nosso muda o HTML do elemento.
 */
$heavy = [];

foreach ( $widget->controls as $id => $args ) {
	$render_type = $args['render_type'] ?? '';
	$expected    = isset( $args['selectors'] ) ? 'ui' : 'none';

	if ( 'raw_html' === ( $args['type'] ?? '' ) ) {
		continue;
	}

	if ( $render_type !== $expected ) {
		$heavy[] = "$id (" . ( $render_type ? $render_type : 'sem render_type' ) . ", esperado $expected)";
	}
}

ok( ! $heavy, 'nenhum controle nosso re-renderiza o elemento à toa', implode( '; ', $heavy ) );

section( '18. Cache de elementos do Elementor' );

/*
 * Regra 24: o Elementor guarda o HTML do documento em meta do post e, junto,
 * a lista de handles que entraram na fila ENQUANTO aquele HTML era montado.
 * No cache hit ele devolve o HTML pronto — com o nosso data-wfan dentro — e
 * reenfileira só aqueles handles, sem disparar before_render. Quem só
 * enfileira no wp_footer nunca entra nessa lista: a página sai com os
 * atributos e sem o core.js, e o conteúdo escondido só aparece pelos 3 s de
 * segurança. Medido em produção.
 */
$cached = new WFAN_Assets();
$cached->register();
WFAN_Test_Hooks::$enqueued = [];

// Sem wp_footer nenhum: é o que o Elementor observa durante o render.
$cached->require_engine( 'css' );

ok(
	in_array( 'wfan-core', WFAN_Test_Hooks::$enqueued, true ),
	'o motor entra na fila durante o render, não só no rodapé'
);
ok(
	in_array( 'wfan-engine-css', WFAN_Test_Hooks::$enqueued, true ),
	'e o motor pedido vem junto'
);

WFAN_Test_Hooks::$enqueued = [];
$cached_ovl = new WFAN_Assets();
$cached_ovl->register();
WFAN_Test_Hooks::$enqueued = [];
$cached_ovl->require_overlay();

ok(
	in_array( 'wfan-overlay', WFAN_Test_Hooks::$enqueued, true ),
	'o fundo animado também entra na fila durante o render'
);

$cached_css = new WFAN_Assets();
$cached_css->register();
WFAN_Test_Hooks::$enqueued = [];
$cached_css->require_style();

ok(
	in_array( 'wfan-frontend', WFAN_Test_Hooks::$enqueued, true ),
	'e a folha do preset de estado também'
);

// Chamar duas vezes não pode duplicar nem quebrar: o flush roda por elemento.
$before = count( WFAN_Test_Hooks::$enqueued );
$cached_css->require_style();
$cached_css->enqueue_needed();

ok(
	count( array_unique( WFAN_Test_Hooks::$enqueued ) ) === count( array_unique( array_slice( WFAN_Test_Hooks::$enqueued, 0, $before ) ) ),
	'enfileirar de novo não acrescenta handle novo'
);

$assets_src = (string) file_get_contents( WFAN_DIR . 'includes/class-assets.php' );

ok(
	false !== strpos( $assets_src, "did_action( 'wp_enqueue_scripts' )" ),
	'o flush espera o registry existir antes de enfileirar'
);

section( '19. Gatilho de tela' );

/*
 * Regra 25: quem conta quantas vezes a animação toca é o tests/trigger.js, que
 * dirige o IntersectionObserver num DOM de mentira. Aqui fica só o que a suíte
 * de PHP consegue garantir: que o guarda continua no lugar e que o teste existe
 * para ser rodado.
 */
ok(
	false !== strpos( $core, 'if ( isIn === entry.inside )' ),
	'o observador só reage à mudança de estado, não a cada cruzamento'
);
ok(
	false !== strpos( $core, 'function hold( entry )' ) && false !== strpos( $core, 'entry.busy' ),
	'e segura novos disparos enquanto a animação roda'
);
ok(
	file_exists( WFAN_DIR . 'tests/trigger.js' ),
	'o teste do gatilho existe'
);
ok(
	false !== strpos( (string) file_get_contents( WFAN_DIR . 'build.sh' ), 'tests/trigger.js' ),
	'e o build não empacota sem rodá-lo'
);

/*
 * Revelação travada no scroll: o percurso dela não é o par genérico
 * "começar/terminar quando", e sim uma linha fixa na tela que o texto
 * atravessa — `top <linha>` até `bottom <linha>`, as duas pontas na mesma
 * altura. Com o par genérico o texto terminava de acender enquanto o bloco
 * ainda subia, antes de dar para ler. Quem confere a conta é o
 * tests/scrub.js, que lê o que o motor entrega ao ScrollTrigger; aqui fica o
 * que o PHP garante: que o controle existe, que o par genérico sai do painel
 * (controle que não é obedecido é configuração que não faz nada) e que o
 * teste existe para ser rodado.
 */
$line_presets = WFAN_Presets::ids_with_param( 'line' );

ok( in_array( 'text-scrub-words', $line_presets, true ), 'a revelação por palavra tem linha de acendimento' );
ok( in_array( 'text-scrub-chars', $line_presets, true ), 'a revelação por letra também' );

$line_control = $widget->controls[ $k( 'scrub_line' ) ] ?? null;

ok( null !== $line_control, 'o controle da linha existe no painel' );
ok(
	isset( $widget->controls[ $k( 'scrub_speed' ) ] )
		&& wfan_visible( $widget->controls[ $k( 'scrub_speed' ) ], [ $k( 'preset' ) => 'text-scrub-words' ] ),
	'a velocidade da revelação existe e aparece no preset que a obedece'
);
ok(
	$line_control && wfan_visible( $line_control, [ $k( 'preset' ) => 'text-scrub-words' ] ),
	'e aparece no preset que a obedece'
);

$generic_leak = [];

foreach ( [ 'scrub_start', 'scrub_end' ] as $slot ) {
	foreach ( $line_presets as $id ) {
		if ( wfan_visible( $widget->controls[ $k( $slot ) ], [ $k( 'preset' ) => $id, $k( 'trigger' ) => 'scroll-scrub' ] ) ) {
			$generic_leak[] = $slot . ' em ' . $id;
		}
	}
}

ok( ! $generic_leak, 'e o par genérico de início e fim sai do painel, porque o motor o ignora', implode( ', ', $generic_leak ) );

ok(
	false !== strpos( $gsap_js, "'top ' + lineAnchor( spec )" ) && false !== strpos( $gsap_js, 'return lineSpan( spec )' ),
	'o início sai da linha e o fim é a distância escalada pela velocidade'
);
ok(
	false !== strpos( $gsap_js, 'gsap.set( targets, from )' ),
	'o estado de partida de todos os pedaços é fixado, senão o fim do texto aparece antes da hora'
);
ok(
	file_exists( WFAN_DIR . 'tests/scrub.js' ),
	'o teste do percurso travado no scroll existe'
);
ok(
	false !== strpos( (string) file_get_contents( WFAN_DIR . 'build.sh' ), 'tests/scrub.js' ),
	'e o build não empacota sem rodá-lo'
);

section( '20. Onde a tela de ajustes aparece' );

/*
 * Regra 16: a tela é SEMPRE um submenu do menu WooFlow — com o hub da família
 * no ar ou sem ele. Quando ninguém da linha criou o pai, é este plugin que o
 * cria, como faz o wooflow-update-product-view. Duas coisas que já quebraram:
 *
 *  - pendurar no menu do Elementor (até a 2.4.2) parou de funcionar no 4.x,
 *    que esconde o item de topo dele inteiro e ainda tira de lá todo submenu
 *    de terceiro: "sem o WooFlow Admin, o Animations não aparece no menu";
 *  - registrar na prioridade 10 é chegar antes dos satélites da linha, que
 *    criam o pai na 20: a checagem não via pai nenhum e o plugin ia parar num
 *    menu só dele, do lado do WooFlow em vez de dentro dele.
 */
$menu_settings = new WFAN_Settings();

ok(
	in_array( WFAN_Settings::MENU_PRIORITY, WFAN_Test_Hooks::$priorities['admin_menu'] ?? [], true ),
	'o menu é registrado na prioridade da linha WooFlow',
	'prioridades vistas: ' . implode( ', ', WFAN_Test_Hooks::$priorities['admin_menu'] ?? [] )
);
ok( 20 === WFAN_Settings::MENU_PRIORITY, 'que é a 20, a mesma do Monitor' );

// 1. Com o hub no ar: só o submenu, e o primeiro item do pai fica intacto.
global $admin_page_hooks;
$admin_page_hooks = [ 'wooflow' => 'toplevel_page_wooflow' ];
WFAN_Test_Hooks::$menus = [];
$menu_settings->menu();

$com_hub = WFAN_Test_Hooks::$menus;

ok( 'wooflow' === WFAN_Settings::parent_slug(), 'com o hub, o pai é o menu da família' );
ok( 1 === count( $com_hub ), 'e só o submenu é registrado', count( $com_hub ) . ' registros' );
ok( 'submenu' === ( $com_hub[0]['tipo'] ?? '' ), 'como submenu' );
ok( 'wooflow' === ( $com_hub[0]['parent'] ?? '' ), 'pendurado no hub' );
ok( WFAN_Settings::SLUG === ( $com_hub[0]['slug'] ?? '' ), 'com o slug da tela' );
ok( 'Animations' === ( $com_hub[0]['label'] ?? '' ), 'com o rótulo curto, porque o nome já está no pai' );

// 2. Sem ninguém da família: o pai é criado aqui, e a tela entra dentro dele.
$admin_page_hooks = [];
WFAN_Test_Hooks::$menus = [];
$menu_settings->menu();

$sozinho = WFAN_Test_Hooks::$menus;
$tipos   = array_column( $sozinho, 'tipo' );

ok( '' === WFAN_Settings::parent_slug(), 'sem a família, não há pai para achar' );
ok( [ 'topo', 'submenu', 'remove' ] === $tipos, 'o pai é criado, a tela entra nele e o item-fantasma sai', implode( ', ', $tipos ) );
ok( 'wooflow' === ( $sozinho[0]['slug'] ?? '' ), 'o pai criado é o menu da família, não um menu nosso' );
ok( 'WooFlow' === ( $sozinho[0]['label'] ?? '' ), 'com o rótulo da família', (string) ( $sozinho[0]['label'] ?? '' ) );
ok( WFAN_Settings::PARENT_ICON === ( $sozinho[0]['icon'] ?? '' ), 'com o mesmo ícone que o Monitor usa' );
ok( WFAN_Settings::PARENT_POSITION === ( $sozinho[0]['pos'] ?? null ), 'na mesma posição que o Monitor usa' );
ok( 'wooflow' === ( $sozinho[1]['parent'] ?? '' ), 'a tela fica dentro do menu da família' );
ok( WFAN_Settings::SLUG === ( $sozinho[1]['slug'] ?? '' ), 'com o mesmo slug de sempre, para a URL não mudar' );
ok(
	'wooflow' === ( $sozinho[2]['parent'] ?? '' ) && 'wooflow' === ( $sozinho[2]['slug'] ?? '' ),
	'e o primeiro item do pai, que não leva a lugar nenhum, é removido'
);

// A capability é a mesma em tudo (regra 16): o plugin não depende do Woo.
foreach ( array_merge( $com_hub, $sozinho ) as $registro ) {
	if ( 'remove' === $registro['tipo'] ) {
		continue;
	}

	ok(
		'manage_options' === ( $registro['cap'] ?? '' ),
		'capability manage_options em "' . $registro['slug'] . '"',
		(string) ( $registro['cap'] ?? '' )
	);
}

// Guarda da regressão: o menu do Elementor não é mais pai de nada nosso.
$elementor_parent = array_filter(
	array_merge( $com_hub, $sozinho ),
	static function ( $registro ) {
		return 'elementor' === ( $registro['parent'] ?? '' );
	}
);

ok( ! $elementor_parent, 'nada é pendurado no menu do Elementor' );
ok(
	false === strpos( $settings_src, "'elementor'" ),
	'e o slug dele nem é citado como pai no código'
);

/*
 * Trocar de pai troca o hook da tela — `wooflow_page_…` vira
 * `toplevel_page_…`. Quem casa por hook fixo perde o CSS e a supressão de
 * notices justamente no caminho novo, e a tela aparece crua.
 */
foreach ( [ 'wooflow_page_' . WFAN_Settings::SLUG, 'toplevel_page_' . WFAN_Settings::SLUG ] as $hook ) {
	WFAN_Test_Hooks::$enqueued = [];
	$menu_settings->enqueue( $hook );

	ok(
		in_array( 'wfan-admin', WFAN_Test_Hooks::$enqueued, true ),
		"o CSS do painel carrega no hook \"$hook\""
	);
}

WFAN_Test_Hooks::$enqueued = [];
$menu_settings->enqueue( 'edit.php' );

ok( ! WFAN_Test_Hooks::$enqueued, 'e não carrega em tela que não é nossa' );

section( '21. Botão voltar ao topo' );

/*
 * Mesmo desenho do blur (seção 9): efeito global, módulo próprio, nada de
 * preset — com uma diferença que vale a seção inteira. A faixa de blur é
 * decoração `aria-hidden`; este é um controle. Então aqui também se confere o
 * que faz dele um controle: <button> de verdade, focável, com nome acessível e
 * sem aria-hidden.
 */

// Parte dos padrões: as seções 9 e 10 deixaram a option mexida.
update_option( WFAN_Settings::OPTION, WFAN_Settings::defaults() );
WFAN_Settings::flush();

// ------------------------------------------------------------------- sanitize

$btt_clean = $settings->sanitize(
	[
		'_tab'          => 'topo',
		'btt_size'      => '999',
		'btt_y'         => '-10',
		'btt_duration'  => '9',
		'btt_threshold' => '',
		'btt_pos'       => 'diagonal',
		'btt_shape'     => 'triangulo',
		'btt_icon'      => 'foguete',
		'btt_anim'      => 'girar',
		'btt_bg'        => 'azul',
		'btt_fg'        => '#FFF',
		'btt_bg_hover'  => '#fff; background:url(javascript:0)',
	]
);

ok( 96 === (int) $btt_clean['btt_size'], 'o tamanho é limitado ao máximo', $btt_clean['btt_size'] );
ok( 0 === (int) $btt_clean['btt_y'], 'afastamento negativo cai no mínimo', $btt_clean['btt_y'] );
ok( 3.0 === (float) $btt_clean['btt_duration'], 'a duração da subida é limitada', $btt_clean['btt_duration'] );
ok( '400' === $btt_clean['btt_threshold'], 'campo apagado volta ao padrão, não desaba para o mínimo', $btt_clean['btt_threshold'] );
ok( 'right' === $btt_clean['btt_pos'], 'posição fora da lista cai no padrão', $btt_clean['btt_pos'] );
ok( 'circle' === $btt_clean['btt_shape'], 'forma fora da lista cai no padrão', $btt_clean['btt_shape'] );
ok( 'arrow' === $btt_clean['btt_icon'], 'ícone fora da lista cai no padrão', $btt_clean['btt_icon'] );
ok( 'fade' === $btt_clean['btt_anim'], 'entrada fora da lista cai no padrão', $btt_clean['btt_anim'] );
ok( '#FFF' === $btt_clean['btt_fg'], 'cor de três dígitos passa como está, sem minúsculas', $btt_clean['btt_fg'] );

/*
 * Cor inválida volta ao PADRÃO, nunca a vazio: `--wfan-btt-bg:;` invalida a
 * declaração e o botão sairia transparente, com o ícone solto no ar.
 */
ok( '#111827' === $btt_clean['btt_bg'], 'cor inválida volta ao padrão, não a vazio', $btt_clean['btt_bg'] );
ok( '#7c3aed' === $btt_clean['btt_bg_hover'], 'cor com CSS pendurado volta ao padrão', $btt_clean['btt_bg_hover'] );
ok( '0' === $btt_clean['btt_enable'], 'checkbox ausente desliga o botão (regra 12)' );
ok( 'bottom' === $btt_clean['blur_pos'], 'o recorte por aba não mexe na escolha de outra aba', $btt_clean['blur_pos'] );

// -------------------------------------------------------------------- padrões

/*
 * A seção 10 reconhece toggle por valor '0'/'1' nos padrões. Um afastamento ou
 * um limiar com padrão 0 — que é a primeira coisa que se escreve — viraria
 * toggle fantasma sem aba, e a falha apareceria lá, longe daqui.
 */
$btt_defaults = array_filter(
	WFAN_Settings::defaults(),
	static function ( $key ) {
		return 0 === strpos( (string) $key, 'btt_' );
	},
	ARRAY_FILTER_USE_KEY
);

$btt_toggles    = WFAN_Settings::tabs()['topo']['toggles'] ?? [];
$btt_fake_flags = [];

foreach ( $btt_defaults as $btt_key => $btt_value ) {
	if ( in_array( (string) $btt_value, [ '0', '1' ], true ) && ! in_array( $btt_key, $btt_toggles, true ) ) {
		$btt_fake_flags[] = $btt_key;
	}
}

ok( ! $btt_fake_flags, 'nenhum campo escalar do botão parece toggle nos padrões', implode( ', ', $btt_fake_flags ) );
ok( isset( WFAN_Settings::tabs()['topo'] ), 'a aba existe no mapa único das abas' );
ok( file_exists( WFAN_DIR . 'templates/admin/tabs/tab-topo.php' ), 'e tem o seu template' );

// Padrão fora da lista aceita faria o primeiro save legítimo "corrigir" o
// valor para outra coisa, sem o usuário ter tocado no campo.
$btt_bad_default = [];

foreach ( WFAN_Settings::selects() as $btt_key => $btt_allowed ) {
	if ( ! in_array( (string) WFAN_Settings::defaults()[ $btt_key ], $btt_allowed, true ) ) {
		$btt_bad_default[] = $btt_key;
	}
}

ok( ! $btt_bad_default, 'o padrão de cada campo de escolha está entre os valores válidos', implode( ', ', $btt_bad_default ) );

$btt_bad_color = [];

foreach ( WFAN_Settings::colors() as $btt_key ) {
	if ( ! sanitize_hex_color( (string) WFAN_Settings::defaults()[ $btt_key ] ) ) {
		$btt_bad_color[] = $btt_key;
	}
}

ok( ! $btt_bad_color, 'e o padrão de cada cor é um hex válido', implode( ', ', $btt_bad_color ) );

// A lista que o painel oferece e a que o sanitize aceita são a mesma coisa em
// dois lugares: opção oferecida e recusada é campo que volta sozinho.
$btt_maps = [
	'btt_pos'   => WFAN_BackToTop::positions(),
	'btt_shape' => WFAN_BackToTop::shapes(),
	'btt_icon'  => WFAN_BackToTop::icons(),
	'btt_anim'  => WFAN_BackToTop::entrances(),
];

$btt_drift = [];

foreach ( $btt_maps as $btt_key => $btt_map ) {
	if ( array_keys( $btt_map ) !== WFAN_Settings::selects()[ $btt_key ] ) {
		$btt_drift[] = $btt_key;
	}
}

ok( ! $btt_drift, 'a lista do painel bate com a lista aceita pelo sanitize', implode( ', ', $btt_drift ) );
ok(
	array_keys( WFAN_BackToTop::RADIUS ) === WFAN_Settings::selects()['btt_shape'],
	'toda forma oferecida tem raio de borda'
);

// Regra 14, uma camada abaixo: ícone oferecido sem SVG é opção que apaga o botão.
$btt_no_svg = [];

foreach ( array_keys( WFAN_BackToTop::icons() ) as $btt_icon ) {
	if ( false === strpos( WFAN_BackToTop::icon_svg( $btt_icon ), '<svg' ) ) {
		$btt_no_svg[] = $btt_icon;
	}
}

ok( ! $btt_no_svg, 'todo ícone oferecido no painel tem SVG', implode( ', ', $btt_no_svg ) );

// --------------------------------------------------------------------- assets

ok( ! WFAN_BackToTop::active(), 'desligado de fábrica' );

$btt_on = static function ( array $over = [] ) {
	update_option( WFAN_Settings::OPTION, array_merge( WFAN_Settings::defaults(), [ 'btt_enable' => '1' ], $over ) );
	WFAN_Settings::flush();
};

$btt_on( [ 'btt_size' => '56', 'btt_bg' => '#0a0a0a', 'btt_ring' => '1' ] );

ok( WFAN_BackToTop::active(), 'liga pelo ajuste' );

$bttAssets = new WFAN_Assets();
$bttAssets->register();

WFAN_Test_Hooks::$enqueued  = [];
WFAN_Test_Hooks::$inline    = [];
WFAN_Test_Hooks::$inline_js = [];

$btt = new WFAN_BackToTop();
$btt->enqueue();

ok( isset( WFAN_Test_Hooks::$styles['wfan-backtotop'] ), 'o CSS do botão é registrado no WFAN_Assets (regra 5)' );
ok( isset( WFAN_Test_Hooks::$scripts['wfan-backtotop'] ), 'e o JS também' );
ok( [] === ( WFAN_Test_Hooks::$scripts['wfan-backtotop']['deps'] ?? null ), 'o botão não depende de biblioteca nenhuma' );
ok( in_array( 'wfan-backtotop', WFAN_Test_Hooks::$enqueued, true ), 'entra quando o botão está ligado' );

/*
 * O botão são ~2 KB. Arrastar o core ou um motor atrás dele seria de 6 KB a
 * 120 KB em toda página do site, por um scrollY.
 */
foreach ( [ 'wfan-core', 'wfan-frontend', 'wfan-engine-css', 'wfan-gsap', 'wfan-anime', 'wfan-lenis', 'wfan-overlay' ] as $btt_stray ) {
	ok( ! in_array( $btt_stray, WFAN_Test_Hooks::$enqueued, true ), "$btt_stray NÃO entra por causa do botão" );
}

$btt_quiet = new WFAN_BackToTop();
$btt_on( [ 'btt_enable' => '0' ] );
WFAN_Test_Hooks::$enqueued = [];
$btt_quiet->enqueue();

ok( ! WFAN_Test_Hooks::$enqueued, 'desligado, não enfileira nada' );

// ----------------------------------------------------------------- CSS inline

$btt_on( [ 'btt_size' => '56', 'btt_bg' => '#0a0a0a', 'btt_ring' => '1' ] );

$btt_inline = WFAN_BackToTop::inline_css();

ok( false !== strpos( $btt_inline, '--wfan-btt-size:56px' ), 'o tamanho escolhido vai para a variável', $btt_inline );
ok( false !== strpos( $btt_inline, '--wfan-btt-bg:#0a0a0a' ), 'a cor de fundo vai já sanitizada', $btt_inline );
ok( false !== strpos( $btt_inline, '--wfan-btt-r:50%' ), 'a forma circular vira raio de 50%', $btt_inline );
ok( false === strpos( $btt_inline, '--wfan-btt-p' ), 'a fração do scroll NÃO sai do servidor: ela muda 60 vezes por segundo' );

$btt_on( [ 'btt_off_mobile' => '1', 'mobile_bp' => '600' ] );

ok(
	false !== strpos( WFAN_BackToTop::inline_css(), '@media (max-width:600px){.wfan-btt{display:none}}' ),
	'desligar em telas pequenas usa a largura limite dos Ajustes',
	WFAN_BackToTop::inline_css()
);

// --------------------------------------------------------------------- markup

$btt_on( [ 'btt_ring' => '1', 'btt_pos' => 'left', 'btt_anim' => 'zoom', 'btt_icon' => 'double' ] );

ob_start();
( new WFAN_BackToTop() )->render();
$btt_markup = (string) ob_get_clean();

preg_match( '/<button[^>]*>/', $btt_markup, $btt_m );
$btt_tag = $btt_m[0] ?? '';

ok( '' !== $btt_tag, 'o botão é um <button>, não uma <div> com onclick', $btt_markup );
ok( 1 === substr_count( $btt_markup, '<button' ), 'um botão por página' );
ok( false !== strpos( $btt_tag, 'type="button"' ), 'com type="button": no rodapé pode haver <form> de tema aberto' );
ok( (bool) preg_match( '/aria-label="[^"]+"/', $btt_tag ), 'com nome acessível, que é a única coisa que o leitor de tela tem para ler' );
ok( false === strpos( $btt_tag, 'aria-hidden' ), 'e SEM aria-hidden: ao contrário da faixa de blur, este é um controle' );
ok( false === strpos( $btt_tag, 'tabindex' ), 'sem tabindex: <button> já é focável na ordem natural' );
ok( false === strpos( $btt_markup, 'is-visible' ), 'nasce sem a classe de visível: quem decide é o scroll' );
ok( false === strpos( $btt_markup, 'style=' ), 'nenhum style="" no markup: as medidas são variáveis' );
ok( false !== strpos( $btt_markup, '<svg' ), 'o ícone sai inline, sem requisição e sem fonte de ícones' );
ok( false !== strpos( $btt_markup, 'focusable="false"' ), 'o SVG sai com focusable="false": senão vira um segundo ponto de parada' );
ok( false !== strpos( $btt_markup, 'wfan-btt__ring' ), 'o anel de progresso sai quando ligado' );
ok( false !== strpos( $btt_markup, 'wfan-btt--left' ), 'a posição escolhida vira modificador de classe' );
ok( false !== strpos( $btt_markup, 'wfan-btt--zoom' ), 'e a entrada escolhida também' );

// A máscara do anel é redonda: numa forma quadrada ele sairia torto. Então não sai.
$btt_on( [ 'btt_ring' => '1', 'btt_shape' => 'square' ] );

ob_start();
( new WFAN_BackToTop() )->render();
$btt_square = (string) ob_get_clean();

ok( false === strpos( $btt_square, 'wfan-btt__ring' ), 'o anel não sai em forma quadrada' );
ok( false === strpos( $btt_square, 'wfan-btt--ring' ), 'nem o modificador dele' );
ok( false !== strpos( WFAN_BackToTop::inline_css(), '--wfan-btt-r:0' ), 'a forma quadrada zera o raio' );

$btt_off = new WFAN_BackToTop();
$btt_on( [ 'btt_enable' => '0' ] );

ob_start();
$btt_off->render();

ok( '' === (string) ob_get_clean(), 'desligado, não imprime markup nenhum' );

// ------------------------------------------------------- variável x CSS x JS

$btt_css = (string) file_get_contents( WFAN_DIR . 'assets/css/backtotop.css' );
$btt_min = (string) preg_replace( '/\s+/', '', $btt_css );
$btt_js  = (string) file_get_contents( WFAN_DIR . 'assets/js/frontend/backtotop.js' );

$btt_on( [ 'btt_ring' => '1' ] );
$btt_vars_css = WFAN_BackToTop::inline_css();

/*
 * Mesmo contrato da regra 15, nos dois sentidos: variável escrita e não lida é
 * ajuste que não faz nada, e variável lida e escrita por ninguém é regra presa
 * ao valor de reserva para sempre.
 *
 * O nome é fechado com `,` ou `)` de propósito: no CSS minificado
 * `var(--wfan-btt-bg` é prefixo de `var(--wfan-btt-bgh`, e um strpos cru
 * deixaria passar uma variável órfã só porque a vizinha é lida.
 */
$btt_unread = [];

if ( preg_match_all( '/(--wfan-btt-[a-z0-9-]+)\s*:/i', $btt_vars_css, $btt_m ) ) {
	foreach ( array_unique( $btt_m[1] ) as $btt_var ) {
		if ( false === strpos( $btt_min, 'var(' . $btt_var . ',' ) && false === strpos( $btt_min, 'var(' . $btt_var . ')' ) ) {
			$btt_unread[] = $btt_var;
		}
	}
}

ok( ! $btt_unread, 'toda variável escrita inline é lida pelo backtotop.css', implode( ', ', $btt_unread ) );

$btt_orphan = [];

if ( preg_match_all( '/var\(\s*(--wfan-btt-[a-z0-9-]+)/i', $btt_css, $btt_m ) ) {
	foreach ( array_unique( $btt_m[1] ) as $btt_var ) {
		$btt_written = false !== strpos( $btt_vars_css, $btt_var . ':' )
			|| (bool) preg_match( '/' . preg_quote( $btt_var, '/' ) . ':/', $btt_min )
			|| false !== strpos( $btt_js, $btt_var );

		if ( ! $btt_written ) {
			$btt_orphan[] = $btt_var;
		}
	}
}

ok( ! $btt_orphan, 'toda variável lida pelo CSS é escrita pelo PHP, pelo próprio CSS ou pelo JS', implode( ', ', $btt_orphan ) );

// O anel é o único valor que o JS escreve, e ele existe nas duas pontas.
ok(
	false !== strpos( $btt_js, '--wfan-btt-p' ) && false !== strpos( $btt_min, 'var(--wfan-btt-p' ),
	'o anel: o JS escreve a fração do scroll e o CSS a lê'
);

/*
 * Classe sem regra é botão que não aparece. A varredura passa por TODAS as
 * combinações de posição e entrada, senão um modificador novo entra sem estilo
 * e ninguém vê.
 */
$btt_variants = $btt_markup . $btt_square;

foreach ( array_keys( WFAN_BackToTop::positions() ) as $btt_pos ) {
	foreach ( array_keys( WFAN_BackToTop::entrances() ) as $btt_anim ) {
		$btt_on( [ 'btt_pos' => $btt_pos, 'btt_anim' => $btt_anim, 'btt_ring' => '1' ] );

		ob_start();
		( new WFAN_BackToTop() )->render();
		$btt_variants .= (string) ob_get_clean();
	}
}

$btt_missing = [];

if ( preg_match_all( '/class="([^"]+)"/', $btt_variants, $btt_m ) ) {
	foreach ( $btt_m[1] as $btt_attr ) {
		foreach ( preg_split( '/\s+/', trim( $btt_attr ) ) as $btt_cls ) {
			if ( '' !== (string) $btt_cls && false === strpos( $btt_css, '.' . $btt_cls ) ) {
				$btt_missing[] = $btt_cls;
			}
		}
	}
}

ok( ! $btt_missing, 'toda classe do botão tem regra no backtotop.css', implode( ', ', array_unique( $btt_missing ) ) );

/*
 * O botão nasce escondido pelo arquivo, não por style inline nem por JS: a
 * folha está no <head> e o markup sai no rodapé, então o estado escondido vale
 * desde a primeira pintura. Um botão que aparece e desaparece no carregamento é
 * o defeito clássico deste componente.
 */
ok( (bool) preg_match( '/\.wfan-btt\{[^}]*opacity:0;/', $btt_min ), 'o estado de partida escondido está no CSS' );
ok( (bool) preg_match( '/\.wfan-btt\{[^}]*visibility:hidden/', $btt_min ), 'e com visibility, para o botão invisível não ser um ponto de parada' );
ok(
	false !== strpos( $btt_js, 'is-visible' ) && false !== strpos( $btt_min, '.wfan-btt.is-visible' ),
	'a classe que revela o botão existe no JS e no CSS, com o mesmo nome'
);

// A subida tem de passar pelo Lenis quando ele existe: ele é dono do scroll e
// desfaria um window.scrollTo nosso no raf seguinte.
/*
 * O <button> é impresso no `wp_footer` com prioridade 100, e os scripts de
 * rodapé do WordPress saem na 20: este arquivo roda ANTES de o botão existir no
 * DOM. Um querySelector no corpo do script devolvia null, o clique nunca era
 * amarrado e o botão nascia morto em todo site — com o HTML, o CSS e a
 * configuração perfeitos na página. Quem conta o resto é o tests/backtotop.js,
 * que dirige o scroll e o clique num DOM de mentira; aqui fica só o guarda.
 */
ok(
	false !== strpos( $btt_js, 'DOMContentLoaded' ) && false !== strpos( $btt_js, 'readyState' ),
	'o script espera o DOM: o botão é impresso depois dele'
);
ok(
	file_exists( WFAN_DIR . 'tests/backtotop.js' ),
	'o teste do botão existe'
);
ok(
	false !== strpos( (string) file_get_contents( WFAN_DIR . 'build.sh' ), 'tests/backtotop.js' ),
	'e o build não empacota sem rodá-lo'
);
ok( false !== strpos( $btt_js, 'wfanLenisInstance' ), 'a subida usa a instância do Lenis quando ela existe' );
ok( false !== strpos( $btt_js, '{ passive: true }' ), 'o ouvinte de scroll é passivo' );
ok( false !== strpos( $btt_js, 'requestAnimationFrame' ), 'e vem com freio de requestAnimationFrame' );
ok( false !== strpos( $btt_min, '@media(prefers-reduced-motion:reduce)' ), 'o CSS tem a rede de reduzir movimento' );

// ---------------------------------------------------------- configuração tipada

/*
 * Regra 17 de novo. `ring` e `reduced` são testados por veracidade no
 * backtotop.js, e "0" é verdadeiro no JavaScript: o anel desligado chegaria
 * ligado, com um setProperty por frame em toda página do site.
 */
$btt_cfg_raw = WFAN_Test_Hooks::$inline_js['wfan-backtotop'] ?? '';
$btt_cfg     = json_decode( (string) preg_replace( '/^var wfanBackToTop = |;$/', '', trim( $btt_cfg_raw ) ), true );

ok( '' !== $btt_cfg_raw, 'a configuração do botão vai como script inline' );
ok( is_array( $btt_cfg ), 'e é JSON válido', $btt_cfg_raw );

foreach ( [ 'ring', 'reduced', 'debug' ] as $btt_flag ) {
	ok(
		is_bool( $btt_cfg[ $btt_flag ] ?? null ),
		"wfanBackToTop.$btt_flag chega como booleano, nunca \"0\"",
		var_export( $btt_cfg[ $btt_flag ] ?? null, true )
	);
}

ok( is_int( $btt_cfg['threshold'] ?? null ), 'o limiar chega como número', var_export( $btt_cfg['threshold'] ?? null, true ) );

/*
 * Em milissegundos e inteira, e não em segundos: 0,8 não é representável em
 * binário, e um float sairia do json_encode como 0.80000000000000004 no
 * código-fonte de toda página do site.
 */
ok( 800 === ( $btt_cfg['duration'] ?? null ), 'a duração da subida chega inteira, em milissegundos', var_export( $btt_cfg['duration'] ?? null, true ) );

section( '22. Presets de texto' );

/*
 * Os quatro presets que vieram dos efeitos colados à mão no Custom Code. O
 * que esta seção pega e as genéricas não: que o preset travado no scroll NÃO
 * ganhou pré-esconde (ele precisa estar na tela para aparecer apagado), e que
 * a quebra por letra põe cada palavra num invólucro — sem ele o navegador
 * quebra a linha no meio da palavra.
 */

update_option( WFAN_Settings::OPTION, WFAN_Settings::defaults() );
WFAN_Settings::flush();

$txt_render = new WFAN_Render();

$txt_splits = [
	'text-blur-words'  => [ 'words', 'css' ],
	'text-blur-chars'  => [ 'chars', 'css' ],
	'text-scrub-words' => [ 'words', 'gsap' ],
	'text-scrub-chars' => [ 'chars', 'gsap' ],
];

foreach ( $txt_splits as $txt_id => $txt_spec ) {
	$txt_preset = WFAN_Presets::get( $txt_id );

	ok( is_array( $txt_preset ), "o preset $txt_id existe no catálogo" );
	ok( $txt_preset && $txt_spec[0] === $txt_preset['split'], "e quebra o texto por {$txt_spec[0]}" );
	ok( $txt_preset && $txt_spec[1] === $txt_preset['engine'], "e pede o motor {$txt_spec[1]}" );
	ok( $txt_preset && 'texto' === $txt_preset['group'], "e mora no grupo Texto" );
}

// O travado no scroll não pode nascer escondido: o efeito É o texto apagado
// na tela, acendendo conforme a rolagem. Pré-esconder deixaria um vazio.
foreach ( [ 'text-scrub-words', 'text-scrub-chars' ] as $txt_id ) {
	$txt_result = $txt_render->spec( [ WFAN_Keys::ours( 'preset' ) => $txt_id ] );

	ok(
		$txt_result && ! in_array( 'wfan-pending', $txt_result['classes'], true ),
		"$txt_id não pré-esconde o elemento",
		implode( ' ', $txt_result ? $txt_result['classes'] : [] )
	);
	ok(
		$txt_result && 'scroll-scrub' === ( $txt_result['payload']['tr']['t'] ?? '' ),
		"$txt_id trava o gatilho no scroll"
	);
	ok(
		$txt_result && isset( $txt_result['payload']['dim'] ),
		"$txt_id manda a opacidade de partida no payload"
	);
}

// A opacidade de partida é a do painel, não um número preso no motor.
$txt_dim = $txt_render->spec(
	[
		WFAN_Keys::ours( 'preset' ) => 'text-scrub-words',
		WFAN_Keys::ours( 'dim' )    => [ 'unit' => '', 'size' => 0.45 ],
	]
);

ok( 0.45 === ( $txt_dim['payload']['dim'] ?? null ), 'a opacidade de partida vem do controle', var_export( $txt_dim['payload']['dim'] ?? null, true ) );

// E só aparece em quem a usa: controle que o motor ignora é configuração que
// não faz nada.
$txt_sem_dim = $txt_render->spec( [ WFAN_Keys::ours( 'preset' ) => 'fade-up' ] );

ok( ! isset( $txt_sem_dim['payload']['dim'] ), 'e não vai no payload de quem não a usa' );

// ------------------------------------------------------------- quebra de texto

$txt_core = file_get_contents( WFAN_DIR . 'assets/js/frontend/core.js' );
$txt_css  = file_get_contents( WFAN_DIR . 'assets/css/frontend.css' );

ok( false !== strpos( $txt_core, "'wfan-word'" ), 'o core.js põe cada palavra num invólucro próprio' );
ok(
	(bool) preg_match( '/\.wfan-word\s*\{[^}]*white-space:\s*nowrap/s', $txt_css ),
	'e o invólucro é nowrap: sem isso, a quebra por letra parte a palavra no meio'
);

/*
 * O agrupamento por linha mede TODAS as palavras antes de mover qualquer uma.
 * O invólucro de linha é display:block: fechar a primeira linha reflui o
 * resto, e medir enquanto move dava agrupamento errado da segunda em diante.
 */
ok(
	(bool) preg_match( '/function groupLines\([^)]*\)\s*\{\s*var tops = words\.map/s', $txt_core ),
	'as linhas são medidas em duas passadas, nunca enquanto as palavras se mexem'
);

ok( false !== strpos( $txt_core, 'function regroupLines' ), 'e há como reagrupar as linhas depois' );
ok( false !== strpos( $txt_core, 'el.__wfanWords' ), 'reagrupando a partir das palavras guardadas, sem repicar o texto' );
ok( false !== strpos( $txt_core, "document.fonts.ready" ), 'a webfont, que chega depois da primeira medida, dispara o reagrupamento' );
ok(
	(bool) preg_match( "/addEventListener\(\s*'resize'/", $txt_core ),
	'e o resize também'
);

// A folga da cortina: o overflow inline do motor come o rabo do g, do j e do p.
$txt_engine = file_get_contents( WFAN_DIR . 'assets/js/frontend/engine-css.js' );

ok( false !== strpos( $txt_engine, 'wfan-line--mask' ), 'a linha sob cortina é marcada com a própria classe' );
ok(
	(bool) preg_match( '/\.wfan-line\.wfan-line--mask\s*\{[^}]*padding[^}]*margin/s', $txt_css ),
	'e a folga do recorte existe, com a margem negativa que devolve a medida'
);

section( '23. Barra fixa' );

/*
 * O que separa este módulo dos outros efeitos globais está todo aqui: a cor
 * NÃO é um ajuste nosso. Ela é lida do próprio elemento, e a tela de ajustes
 * não pode ter um campo de cor que diga o contrário.
 */

$hdr_colors = array_filter(
	WFAN_Settings::colors(),
	function ( $key ) {
		return 0 === strpos( (string) $key, 'hdr_' );
	}
);

ok( ! $hdr_colors, 'a barra fixa não tem campo de cor: ela lê a do elemento', implode( ', ', $hdr_colors ) );

$hdr_js = file_get_contents( WFAN_DIR . 'assets/js/frontend/header.js' );
$hdr_css = file_get_contents( WFAN_DIR . 'assets/css/header.css' );

ok( false !== strpos( $hdr_js, 'getComputedStyle' ), 'e o script lê a cor computada do elemento' );
ok( false !== strpos( $hdr_js, WFAN_Header::OFF_CLASS ), 'com a classe de leitura, pelo nome que o PHP declara' );

/*
 * A classe de leitura só pode TIRAR a regra do caminho. Se ela pintasse
 * qualquer fundo — mesmo `initial` —, a medição leria de volta o que nós
 * acabamos de escrever, e a barra nunca ganharia cor.
 */
ok(
	(bool) preg_match( '/:not\(\s*\.' . preg_quote( WFAN_Header::OFF_CLASS, '/' ) . '\s*\)/', $hdr_css ),
	'o CSS sai do caminho por :not(), em vez de pintar por cima'
);
ok(
	! preg_match( '/\.' . preg_quote( WFAN_Header::OFF_CLASS, '/' ) . '\s*\{[^}]*background/s', $hdr_css ),
	'e a classe de leitura não pinta fundo nenhum'
);

// Cor não reconhecida desiste; barra sem efeito é melhor que barra invisível.
ok( false !== strpos( $hdr_js, "indexOf( 'rgb' )" ), 'cor que não é rgb() faz o script desistir' );

ok( false !== strpos( $hdr_js, 'Math.exp' ), 'o amortecimento é exponencial, independente da taxa de quadros' );
ok( false !== strpos( $hdr_js, 'running = false' ), 'e o laço de quadros se apaga ao chegar no alvo' );
ok( (bool) preg_match( "/addEventListener\(\s*'scroll',[^)]*passive: true/s", $hdr_js ), 'o ouvinte de scroll é passivo' );
ok( false !== strpos( $hdr_js, 'DOMContentLoaded' ), 'o script espera o DOM: a barra é markup da página' );

ok( file_exists( WFAN_DIR . 'tests/header.js' ), 'o teste da barra existe' );
ok(
	false !== strpos( file_get_contents( WFAN_DIR . 'build.sh' ), 'tests/header.js' ),
	'e o build não empacota sem rodá-lo'
);

// Regra 17: tipado, nunca wp_localize_script.
$hdr_settings = WFAN_Settings::defaults();
$hdr_settings['hdr_enable'] = '1';
update_option( WFAN_Settings::OPTION, $hdr_settings );
WFAN_Settings::flush();

WFAN_Test_Hooks::$inline_js['wfan-header'] = '';
( new WFAN_Header() )->enqueue();

$hdr_cfg_raw = WFAN_Test_Hooks::$inline_js['wfan-header'] ?? '';
$hdr_cfg     = json_decode( (string) preg_replace( '/^var wfanHeader = |;$/', '', trim( $hdr_cfg_raw ) ), true );

ok( '' !== $hdr_cfg_raw, 'a configuração da barra vai como script inline' );
ok( is_array( $hdr_cfg ), 'e é JSON válido', $hdr_cfg_raw );

foreach ( [ 'shadow', 'reduced', 'debug' ] as $hdr_flag ) {
	ok(
		is_bool( $hdr_cfg[ $hdr_flag ] ?? null ),
		"wfanHeader.$hdr_flag chega como booleano, nunca \"0\"",
		var_export( $hdr_cfg[ $hdr_flag ] ?? null, true )
	);
}

ok( is_int( $hdr_cfg['distance'] ?? null ), 'a distância chega como número', var_export( $hdr_cfg['distance'] ?? null, true ) );
ok( is_int( $hdr_cfg['smooth'] ?? null ), 'o amortecimento chega como número', var_export( $hdr_cfg['smooth'] ?? null, true ) );

section( '24. Popup circular' );

update_option( WFAN_Settings::OPTION, WFAN_Settings::defaults() );
WFAN_Settings::flush();

$pop_js  = file_get_contents( WFAN_DIR . 'assets/js/frontend/popup.js' );
$pop_css = file_get_contents( WFAN_DIR . 'assets/css/popup.css' );

ok( false !== strpos( $pop_js, 'elementor/popup/show' ), 'o efeito se pendura no evento de abertura do Pro' );
ok( false !== strpos( $pop_js, 'elementor/popup/hide' ), 'e no de fechamento' );

/*
 * A origem do círculo é o último clique, capturado na FASE DE CAPTURA: na
 * borbulha o ponto chegaria depois de o Elementor já ter aberto o popup.
 */
ok( (bool) preg_match( "/addEventListener\(\s*'click',.*?\},\s*true\s*\)/s", $pop_js ), 'o ponto do clique é lido na fase de captura' );
ok( false !== strpos( $pop_js, 'lastPoint' ), 'e é ele que vira a origem do círculo' );

// Dois requestAnimationFrame: na primeira abertura o Elementor ainda está
// posicionando o popup quando o evento chega.
ok(
	(bool) preg_match( '/requestAnimationFrame\(\s*function\s*\(\)\s*\{\s*window\.requestAnimationFrame/s', $pop_js ),
	'a medida espera dois quadros, para a primeira abertura não sair torta'
);

ok( false !== strpos( $pop_js, 'MutationObserver' ), 'o fechamento é percebido pela classe que o Elementor põe' );
ok( false !== strpos( $pop_js, WFAN_Popup::OPEN_CLASS ), 'a classe de aberto é a que o PHP declara' );
ok( false !== strpos( $pop_css, WFAN_Popup::OPEN_CLASS ), 'e o CSS usa o mesmo nome' );
ok( false !== strpos( $pop_css, 'prefers-reduced-motion' ), 'com movimento reduzido o círculo sai e sobra um fade' );
ok( false !== strpos( $pop_js, 'clientX' ), 'a coordenada vem do evento, não de um ajuste' );

// Todo parâmetro viaja em custom property, como no fundo animado (regra 20).
foreach ( [ '--wfan-pop-open', '--wfan-pop-close', '--wfan-pop-near', '--wfan-pop-far', '--wfan-pop-blur' ] as $pop_var ) {
	ok( false !== strpos( $pop_css, $pop_var ), "o CSS lê $pop_var" );
	ok( false !== strpos( WFAN_Popup::inline_css(), $pop_var ), "e o PHP escreve $pop_var" );
}

foreach ( [ '--wfan-pop-x', '--wfan-pop-y', '--wfan-pop-r', '--wfan-pop-vx', '--wfan-pop-vy', '--wfan-pop-i' ] as $pop_var ) {
	ok( false !== strpos( $pop_css, $pop_var ), "o CSS lê $pop_var" );
	ok( false !== strpos( $pop_js, $pop_var ), "e o JS escreve $pop_var" );
}

// A cor sai em rgba, montada do hex dos ajustes com a opacidade de cada ponta.
ok( (bool) preg_match( '/--wfan-pop-near:rgba\(14,16,22,0?\.3\)/', WFAN_Popup::inline_css() ), 'o hex dos ajustes vira rgba com a opacidade de perto', WFAN_Popup::inline_css() );
ok( (bool) preg_match( '/--wfan-pop-far:rgba\(14,16,22,0?\.68\)/', WFAN_Popup::inline_css() ), 'e com a de longe' );

ok( file_exists( WFAN_DIR . 'tests/popup.js' ), 'o teste do popup existe' );
ok(
	false !== strpos( file_get_contents( WFAN_DIR . 'build.sh' ), 'tests/popup.js' ),
	'e o build não empacota sem rodá-lo'
);

// Sem o Elementor Pro não há evento nenhum: nada é carregado.
ok( ! WFAN_Popup::available(), 'sem o Elementor Pro o módulo se declara indisponível' );
ok( ! WFAN_Popup::active(), 'e não se liga nem com o ajuste ligado' );

section( '25. Marquee' );

/*
 * Regra 31. Dois presets, motor próprio, gatilho fixo em `load`, e um bloco `mq`
 * no payload que o core.js normaliza e o engine-marquee.js lê.
 */

update_option( WFAN_Settings::OPTION, WFAN_Settings::defaults() );
WFAN_Settings::flush();

$mq_render = new WFAN_Render();
$mq_ids    = WFAN_Presets::ids_marquee();

ok( [ 'marquee-auto', 'marquee-scroll' ] === $mq_ids, 'os dois presets de marquee existem', implode( ', ', $mq_ids ) );
ok( isset( WFAN_Presets::groups()['marquee'] ), 'e têm grupo próprio no painel' );

foreach ( $mq_ids as $mq_id ) {
	$mq_result = $mq_render->spec( [ WFAN_Keys::ours( 'preset' ) => $mq_id ] );

	ok( $mq_result && 'marquee' === $mq_result['engine'], "$mq_id pede o motor marquee" );
	ok( $mq_result && 'load' === ( $mq_result['payload']['tr']['t'] ?? '' ), "$mq_id fica com o gatilho em load, qualquer que seja o gravado" );
	ok( $mq_result && ! in_array( 'wfan-pending', $mq_result['classes'], true ), "$mq_id não pré-esconde: o conteúdo é o próprio marquee" );
	ok( $mq_result && isset( $mq_result['payload']['mq'] ), "$mq_id manda o bloco mq no payload" );
}

// Cada preset recebe só o modo dele, e o payload traz os valores do painel.
$mq_auto = $mq_render->spec(
	[
		WFAN_Keys::ours( 'preset' )    => 'marquee-auto',
		WFAN_Keys::ours( 'mq_dir' )    => 'right',
		WFAN_Keys::ours( 'mq_speed' )  => [ 'unit' => 'px', 'size' => 250 ],
		WFAN_Keys::ours( 'mq_space' )  => [ 'unit' => 'px', 'size' => 12 ],
		WFAN_Keys::ours( 'mq_edge' )   => 'yes',
		WFAN_Keys::ours( 'mq_slow' )   => 'yes',
		WFAN_Keys::ours( 'mq_freeze' ) => '',
		WFAN_Keys::ours( 'mq_drag' )   => 'yes',
	]
);

$mq_block = $mq_auto['payload']['mq'] ?? [];

ok( 'auto' === ( $mq_block['m'] ?? '' ), 'marquee-auto manda o modo auto' );
ok( 'r' === ( $mq_block['dir'] ?? '' ), 'a direção do painel chega no payload' );
ok( 250 === ( $mq_block['v'] ?? 0 ), 'e a velocidade' );
ok( 12 === ( $mq_block['g'] ?? -1 ), 'e o espaço entre repetições' );
ok( 1 === ( $mq_block['e'] ?? 0 ) && 1 === ( $mq_block['sl'] ?? 0 ) && 1 === ( $mq_block['dr'] ?? 0 ), 'e as chaves liga/desliga' );
ok( 0 === ( $mq_block['fz'] ?? 1 ), 'desligado vai como 0, não como ausente nem como "yes"' );

$mq_scroll = $mq_render->spec( [ WFAN_Keys::ours( 'preset' ) => 'marquee-scroll' ] );

ok( 'scroll' === ( $mq_scroll['payload']['mq']['m'] ?? '' ), 'marquee-scroll manda o modo scroll' );

// Velocidade absurda não passa: 0 pararia o efeito, 99999 o tornaria ilegível.
$mq_wild = $mq_render->spec(
	[
		WFAN_Keys::ours( 'preset' )   => 'marquee-auto',
		WFAN_Keys::ours( 'mq_speed' ) => [ 'unit' => 'px', 'size' => 99999 ],
		WFAN_Keys::ours( 'mq_dir' )   => '<script>',
	]
);

ok( 600 === ( $mq_wild['payload']['mq']['v'] ?? 0 ), 'a velocidade tem teto' );
ok( 'l' === ( $mq_wild['payload']['mq']['dir'] ?? '' ), 'direção fora da lista cai na padrão' );

// Quem não é marquee não carrega o bloco.
$mq_none = $mq_render->spec( [ WFAN_Keys::ours( 'preset' ) => 'fade-up' ] );

ok( ! isset( $mq_none['payload']['mq'] ), 'preset comum não leva o bloco mq' );

// ------------------------------------------------------------------ painel

$mq_keys = [ 'mq_dir', 'mq_space', 'mq_edge', 'mq_drag' ];

foreach ( $mq_keys as $mq_key ) {
	ok( isset( $widget->controls[ WFAN_Keys::ours( $mq_key ) ] ), "o controle $mq_key existe" );
	ok( in_array( $mq_key, WFAN_Keys::OURS, true ), "e $mq_key está em WFAN_Keys::OURS (copiar/colar e biblioteca)" );
}

$mq_on = [ WFAN_Keys::ours( 'preset' ) => 'marquee-auto' ];

ok( wfan_visible( $widget->controls[ WFAN_Keys::ours( 'mq_speed' ) ], $mq_on ), 'a velocidade aparece no automático' );
ok( ! wfan_visible( $widget->controls[ WFAN_Keys::ours( 'mq_speed' ) ], [ WFAN_Keys::ours( 'preset' ) => 'marquee-scroll' ] ), 'e não no marquee pela rolagem' );
ok( wfan_visible( $widget->controls[ WFAN_Keys::ours( 'mq_force' ) ], [ WFAN_Keys::ours( 'preset' ) => 'marquee-scroll' ] ), 'a intensidade aparece na rolagem' );
ok( ! wfan_visible( $widget->controls[ WFAN_Keys::ours( 'mq_force' ) ], $mq_on ), 'e não no automático' );
ok( ! wfan_visible( $widget->controls[ WFAN_Keys::ours( 'mq_dir' ) ], [ WFAN_Keys::ours( 'preset' ) => 'fade-up' ] ), 'a direção do marquee não aparece em outro preset' );

// Controle que o motor não obedece é configuração que não faz nada.
foreach ( [ 'duration', 'delay', 'easing' ] as $mq_dead ) {
	ok(
		! wfan_visible( $widget->controls[ WFAN_Keys::ours( $mq_dead ) ], $mq_on + [ WFAN_Keys::ours( 'trigger' ) => 'scroll-in' ] ),
		"$mq_dead sai do painel no marquee (efeito contínuo não tem linha do tempo)"
	);
}

foreach ( [ 'viewport', 'once', 'trigger' ] as $mq_dead ) {
	ok(
		! wfan_visible( $widget->controls[ WFAN_Keys::ours( $mq_dead ) ], $mq_on + [ WFAN_Keys::ours( 'trigger' ) => 'scroll-in' ] ),
		"$mq_dead sai do painel no marquee, mesmo com o gatilho gravado em scroll-in"
	);
}

ok( wfan_visible( $widget->controls[ WFAN_Keys::ours( 'off_mobile' ) ], $mq_on ), '"Desligar no celular" continua valendo: quem obedece é o core.js' );

// Regra 22: nenhum controle do marquee re-renderiza o widget por Ajax.
foreach ( [ 'mq_dir', 'mq_speed', 'mq_force', 'mq_space', 'mq_edge', 'mq_slow', 'mq_freeze', 'mq_drag' ] as $mq_key ) {
	$mq_rt = $widget->controls[ WFAN_Keys::ours( $mq_key ) ]['render_type'] ?? '';

	ok( 'none' === $mq_rt, "$mq_key declara render_type none", (string) $mq_rt );
}

// ------------------------------------------------------------- arquivos e motor

$mq_js   = (string) file_get_contents( WFAN_DIR . 'assets/js/frontend/engine-marquee.js' );
$mq_css  = (string) file_get_contents( WFAN_DIR . 'assets/css/frontend.css' );
$mq_core = (string) file_get_contents( WFAN_DIR . 'assets/js/frontend/core.js' );

// Regra 15: o contrato é conferido nos dois sentidos.
foreach ( [ "mq.m", "mq.dir", "mq.v", "mq.k", "mq.g", "mq.e", "mq.sl", "mq.fz", "mq.dr" ] as $mq_read ) {
	ok( false !== strpos( $mq_core, $mq_read ), "o core.js lê $mq_read do payload" );
}

// As classes que o JS escreve são as que o CSS conhece.
foreach ( [ 'wfan-mq', 'wfan-mq-track', 'wfan-mq-group', 'wfan-mq--drag', 'wfan-mq--dragging', '--wfan-mq-gap' ] as $mq_name ) {
	ok( false !== strpos( $mq_js, $mq_name ), "o motor usa $mq_name" );
	ok( false !== strpos( $mq_css, $mq_name ), "e o CSS declara $mq_name" );
}

ok( false !== strpos( $mq_js, 'IntersectionObserver' ), 'o laço só roda com o elemento na tela' );
ok( false !== strpos( $mq_js, 'Math.min( 64' ), 'e o passo é limitado (aba que volta do segundo plano não salta a esteira)' );
ok( false !== strpos( $mq_js, "aria-hidden" ), 'a cópia sai da leitura de tela' );
ok( false === strpos( $mq_js, 'gsap' ) && false === strpos( $mq_js, 'ScrollTrigger' ), 'o motor não depende de GSAP: o marquee não custa 115 KB' );

// O motor é registrado, sem dependência de biblioteca, e carregado no preview.
$mq_assets = (string) file_get_contents( WFAN_DIR . 'includes/class-assets.php' );

ok( (bool) preg_match( "/'marquee'\s*=>\s*\[\s*'assets\/js\/frontend\/engine-marquee\.js',\s*\[\s*'wfan-core'\s*\]/", $mq_assets ), 'o motor marquee depende só do core' );
ok( false !== strpos( $mq_assets, "wp_enqueue_script( 'wfan-engine-marquee' )" ), 'e o preview do editor o enfileira (o ▶ Testar precisa dele)' );

ok( file_exists( WFAN_DIR . 'tests/marquee.js' ), 'o teste do marquee existe' );
ok( false !== strpos( file_get_contents( WFAN_DIR . 'build.sh' ), 'tests/marquee.js' ), 'e o build não empacota sem rodá-lo' );

// A inversão no hover alcança a lista de ícones e as Informações da Postagem:
// o texto e o ícone deles não são `.elementor-heading-title` nem `.elementor-icon`.
$hv_targets = WFAN_Controls::HOVER_TARGETS;

ok( in_array( '.elementor-icon-list-text', $hv_targets['text'], true ), 'o hover inverte o texto da lista de ícones / Informações da Postagem' );
ok( in_array( '.elementor-icon-list-text a', $hv_targets['text'], true ), 'e os links dentro dele (a cor do widget é escrita nos dois)' );
ok( in_array( '.elementor-icon-list-icon i', $hv_targets['icon'], true ), 'e o ícone de fonte da lista' );

// ------------------------------------------------------------------ resultado

echo "\n" . str_repeat( '-', 48 ) . "\n";
echo $failures
	? "$failures de $checks verificações falharam\n"
	: "todas as $checks verificações passaram\n";

exit( $failures ? 1 : 0 );
