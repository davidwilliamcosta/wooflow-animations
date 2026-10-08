<?php
/**
 * Dublês do WordPress e do Elementor, só o que o plugin realmente usa.
 *
 * Existe para que o smoke test rode com `php tests/smoke.php`, sem WordPress,
 * sem banco e sem Elementor instalado.
 *
 * @package DW_Anim
 */

// phpcs:disable

namespace {


class DW_Test_Hooks {
	public static $actions = [];
	public static $filters = [];
	public static $scripts = [];
	public static $styles  = [];
	public static $enqueued = [];
	public static $localized = [];
	public static $options = [];

	public static function reset() {
		self::$actions = self::$filters = self::$scripts = self::$styles = [];
		self::$enqueued = self::$localized = self::$options = [];
	}
}

function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
	DW_Test_Hooks::$actions[ $hook ][] = $callback;
	return true;
}

function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
	DW_Test_Hooks::$filters[ $hook ][] = $callback;
	return true;
}

function do_action( $hook, ...$args ) {
	foreach ( DW_Test_Hooks::$actions[ $hook ] ?? [] as $callback ) {
		call_user_func_array( $callback, $args );
	}
}

function apply_filters( $hook, $value, ...$args ) {
	foreach ( DW_Test_Hooks::$filters[ $hook ] ?? [] as $callback ) {
		$value = call_user_func_array( $callback, array_merge( [ $value ], $args ) );
	}
	return $value;
}

function did_action( $hook ) {
	return 1;
}

function wp_parse_args( $args, $defaults = [] ) {
	return array_merge( $defaults, is_array( $args ) ? $args : [] );
}

function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function esc_attr__( $text, $domain = '' ) { return $text; }
function esc_html_e( $text, $domain = '' ) { echo $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }
function esc_url( $url ) { return $url; }
function esc_url_raw( $url ) { return $url; }
function sanitize_text_field( $text ) { return trim( strip_tags( (string) $text ) ); }
function wp_unslash( $value ) { return $value; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function get_bloginfo( $what = '' ) { return '6.8'; }
function admin_url( $path = '' ) { return 'https://exemplo.test/wp-admin/' . $path; }
function home_url( $path = '' ) { return 'https://exemplo.test' . $path; }
function is_ssl() { return true; }
function set_url_scheme( $url, $scheme = 'https' ) {
	return preg_replace( '#^https?://#', $scheme . '://', $url );
}
function is_admin_bar_showing() { return false; }
function current_user_can( $cap ) { return true; }
function wp_create_nonce( $action ) { return 'nonce-' . $action; }
function check_ajax_referer( $action, $field = false, $die = true ) { return true; }
function wp_send_json_success( $data = null, $status = 200 ) { throw new RuntimeException( 'json-success' ); }
function wp_send_json_error( $data = null, $status = 400 ) { throw new RuntimeException( 'json-error' ); }
function load_plugin_textdomain( $domain, $abs = false, $rel = '' ) { return true; }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://exemplo.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/'; }
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }

function get_option( $name, $default = false ) {
	return DW_Test_Hooks::$options[ $name ] ?? $default;
}

function update_option( $name, $value, $autoload = null ) {
	DW_Test_Hooks::$options[ $name ] = $value;
	return true;
}

function register_setting( $group, $name, $args = [] ) { return true; }
function add_submenu_page( ...$args ) { return 'hook'; }
function settings_fields( $group ) {}
function submit_button( $text = null ) {}
function checked( $checked, $current = true, $echo = true ) {
	$out = (string) $checked === (string) $current ? ' checked' : '';
	if ( $echo ) { echo $out; }
	return $out;
}

function wp_register_script( $handle, $src = '', $deps = [], $ver = false, $footer = false ) {
	DW_Test_Hooks::$scripts[ $handle ] = [ 'src' => $src, 'deps' => $deps, 'ver' => $ver ];
	return true;
}

function wp_register_style( $handle, $src = '', $deps = [], $ver = false ) {
	DW_Test_Hooks::$styles[ $handle ] = [ 'src' => $src, 'deps' => $deps ];
	return true;
}

function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $footer = false ) {
	DW_Test_Hooks::$enqueued[] = $handle;
	return true;
}

function wp_enqueue_style( $handle, ...$rest ) {
	DW_Test_Hooks::$enqueued[] = $handle;
	return true;
}

function wp_script_is( $handle, $list = 'enqueued' ) {
	if ( 'registered' === $list ) {
		return isset( DW_Test_Hooks::$scripts[ $handle ] );
	}
	return in_array( $handle, DW_Test_Hooks::$enqueued, true );
}

function wp_localize_script( $handle, $object, $data ) {
	DW_Test_Hooks::$localized[ $object ] = $data;
	return true;
}
}

// ---------------------------------------------------------------- Elementor

namespace Elementor {

	class Controls_Manager {
		const TAB_ADVANCED = 'advanced';
		const TAB_CONTENT  = 'content';
		const TAB_LAYOUT   = 'layout';
		const RAW_HTML     = 'raw_html';
		const SELECT       = 'select';
		const SWITCHER     = 'switcher';
		const SLIDER       = 'slider';
		const NUMBER       = 'number';
		const TEXT         = 'text';

		public $controls = [];

		public function register( $control ) {
			$this->controls[ $control->get_type() ] = $control;
			return true;
		}
	}

	abstract class Base_Data_Control {
		abstract public function get_type();
		protected function get_default_settings() { return []; }
		public function get_default_value() { return ''; }
		public function content_template() {}
	}

	class Preview_Stub {
		public function is_preview_mode() { return false; }
	}

	class Plugin {
		public static $instance;
		public $preview;
		public $controls_manager;

		public function __construct() {
			$this->preview = new Preview_Stub();
			$this->controls_manager = new Controls_Manager();
		}
	}

	Plugin::$instance = new Plugin();
}
