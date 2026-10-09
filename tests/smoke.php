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

foreach ( [ 'hover_bg', 'hover_title', 'hover_accent', 'hover_text' ] as $slot ) {
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

foreach ( [ 'hover_dur', 'hover_bg', 'hover_title', 'hover_accent', 'hover_text' ] as $slot ) {
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

$no_keyframes = [];

if ( preg_match_all( '/animation-name:\s*([a-z0-9-]+)/i', $editor_css, $m ) ) {
	foreach ( array_unique( $m[1] ) as $name ) {
		if ( false === strpos( $editor_css, '@keyframes ' . $name ) ) {
			$no_keyframes[] = $name;
		}
	}
}

ok( ! $no_keyframes, 'toda animação de preview tem @keyframes', implode( ', ', $no_keyframes ) );

// ------------------------------------------------------------------ resultado

echo "\n" . str_repeat( '-', 48 ) . "\n";
echo $failures
	? "$failures de $checks verificações falharam\n"
	: "todas as $checks verificações passaram\n";

exit( $failures ? 1 : 0 );
