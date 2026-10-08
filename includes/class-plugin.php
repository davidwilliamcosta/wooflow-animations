<?php
/**
 * Singleton que liga os módulos do plugin.
 *
 * @package DW_Anim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DW_Anim_Plugin {

	/**
	 * @var DW_Anim_Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var DW_Anim_Assets
	 */
	public $assets;

	/**
	 * @var DW_Anim_Settings
	 */
	public $settings;

	/**
	 * @var DW_Anim_Library
	 */
	public $library;

	/**
	 * @return DW_Anim_Plugin
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
			'class-controls',
			'class-control-picker',
			'class-editor',
			'class-render',
			'class-lenis',
			'class-lottie',
		];

		foreach ( $files as $file ) {
			require_once DWANIM_DIR . 'includes/' . $file . '.php';
		}
	}

	/**
	 * @return void
	 */
	private function wire() {
		$this->settings = new DW_Anim_Settings();
		$this->library  = new DW_Anim_Library();
		$this->assets   = new DW_Anim_Assets();

		new DW_Anim_Controls();
		new DW_Anim_Editor();
		new DW_Anim_Render();
		new DW_Anim_Lenis();
		new DW_Anim_Lottie();

		add_action( 'elementor/controls/register', [ $this, 'register_controls' ] );
	}

	/**
	 * Registra o controle visual de presets.
	 *
	 * @param \Elementor\Controls_Manager $manager Gerenciador de controles.
	 * @return void
	 */
	public function register_controls( $manager ) {
		$manager->register( new DW_Anim_Control_Picker() );
	}

	/**
	 * Atalho para o registry de assets.
	 *
	 * @return DW_Anim_Assets
	 */
	public static function assets() {
		return self::instance()->assets;
	}

	/**
	 * Atalho para os ajustes.
	 *
	 * @return DW_Anim_Settings
	 */
	public static function settings() {
		return self::instance()->settings;
	}

	/**
	 * Atalho para a biblioteca de presets salvos.
	 *
	 * @return DW_Anim_Library
	 */
	public static function library() {
		return self::instance()->library;
	}
}
