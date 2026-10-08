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
	public function get_type() { return $this->type; }
	public function get_settings_for_display() { return $this->settings; }

	public function start_controls_section( $id, $args = [] ) {
		$this->current_section = $id;
		$this->sections[ $id ] = $args;
	}

	public function end_controls_section() {
		$this->current_section = '';
	}

	public function add_control( $id, $args = [] ) {
		$this->controls[ $id ] = $args;
	}

	public function add_responsive_control( $id, $args = [] ) {
		$this->controls[ $id ] = $args;
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
$ui_only = [ 'section', 'actions', 'native_warning' ];
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

foreach ( WFAN_Presets::all() as $id => $preset ) {
	$element = render_with( new WFAN_Fake_Element( 'common', 'widget', [
		WFAN_Keys::ours( 'preset' )     => $id,
		WFAN_Keys::ours( 'lottie_url' ) => 'https://exemplo.test/a.json',
		WFAN_Keys::ours( 'stagger' )    => 'yes',
		WFAN_Keys::ours( 'off_mobile' ) => 'yes',
	] ) );

	$payload = $element->payload();

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

// ------------------------------------------------------------------ resultado

echo "\n" . str_repeat( '-', 48 ) . "\n";
echo $failures
	? "$failures de $checks verificações falharam\n"
	: "todas as $checks verificações passaram\n";

exit( $failures ? 1 : 0 );
