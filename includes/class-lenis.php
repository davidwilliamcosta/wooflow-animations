<?php
/**
 * Scroll suave global com Lenis.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Lenis {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
	}

	/**
	 * @return bool
	 */
	public static function active() {
		if ( ! WFAN_Settings::is_on( 'lenis_enable' ) ) {
			return false;
		}

		// Nunca no editor: o Lenis briga com o canvas do Elementor.
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
			return false;
		}

		/**
		 * Permite desligar o scroll suave em contextos específicos.
		 *
		 * @param bool $active Se o Lenis deve rodar.
		 */
		return (bool) apply_filters( 'wfan_lenis_active', true );
	}

	/**
	 * @return void
	 */
	public function enqueue() {
		if ( ! self::active() ) {
			return;
		}

		wp_enqueue_script( 'wfan-lenis-boot' );

		wp_localize_script(
			'wfan-lenis-boot',
			'wfanLenis',
			[
				'lerp'       => (float) WFAN_Settings::get( 'lenis_lerp', 0.1 ),
				'duration'   => (float) WFAN_Settings::get( 'lenis_duration', 1.2 ),
				'smoothWheel' => WFAN_Settings::is_on( 'lenis_wheel' ),
				'syncTouch'  => WFAN_Settings::is_on( 'lenis_touch' ),
				'reduced'    => WFAN_Settings::is_on( 'respect_reduced' ),
				'adminBar'   => is_admin_bar_showing() ? 32 : 0,
			]
		);
	}
}
