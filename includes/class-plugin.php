<?php
/**
 * Singleton que liga os módulos do plugin.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Plugin {

	/**
	 * @var WFAN_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var WFAN_Assets
	 */
	public $assets;

	/**
	 * @var WFAN_Settings
	 */
	public $settings;

	/**
	 * @var WFAN_Library
	 */
	public $library;

	/**
	 * @return WFAN_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		$this->load();
		$this->wire();
	}

	/**
	 * @return void
	 */
	private function load() {
		$files = [
			'class-keys',
			'class-presets',
			'class-settings',
			'class-library',
			'class-assets',
			'class-overlay',
			'class-controls',
			'class-control-picker',
			'class-editor',
			'class-render',
			'class-lenis',
			'class-blur',
			'class-lottie',
		];

		foreach ( $files as $file ) {
			require_once WFAN_DIR . 'includes/' . $file . '.php';
		}
	}

	/**
	 * @return void
	 */
	private function wire() {
		$this->settings = new WFAN_Settings();
		$this->library  = new WFAN_Library();
		$this->assets   = new WFAN_Assets();

		new WFAN_Controls();
		new WFAN_Editor();
		new WFAN_Render();
		new WFAN_Lenis();
		new WFAN_Blur();
		new WFAN_Lottie();

		add_action( 'elementor/controls/register', [ $this, 'register_controls' ] );
	}

	/**
	 * Registra o controle visual de presets.
	 *
	 * @param \Elementor\Controls_Manager $manager Gerenciador de controles.
	 * @return void
	 */
	public function register_controls( $manager ) {
		$manager->register( new WFAN_Control_Picker() );
	}

	/**
	 * Atalho para o registry de assets.
	 *
	 * @return WFAN_Assets
	 */
	public static function assets() {
		return self::instance()->assets;
	}

	/**
	 * Atalho para os ajustes.
	 *
	 * @return WFAN_Settings
	 */
	public static function settings() {
		return self::instance()->settings;
	}

	/**
	 * Atalho para a biblioteca de presets salvos.
	 *
	 * @return WFAN_Library
	 */
	public static function library() {
		return self::instance()->library;
	}
}
