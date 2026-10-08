<?php
/**
 * Scroll suave global com Lenis.
 *
 * @package DW_Anim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DW_Anim_Lenis {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ], 20 );
	}

	/**
	 * @return bool
	 */
	public static function active() {
		if ( ! DW_Anim_Settings::is_on( 'lenis_enable' ) ) {
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
		return (bool) apply_filters( 'dw_anim_lenis_active', true );
	}

	/**
	 * @return void
	 */
	public function enqueue() {
		if ( ! self::active() ) {
			return;
		}

		wp_enqueue_script( 'dw-anim-lenis-boot' );

		wp_localize_script(
			'dw-anim-lenis-boot',
			'dwAnimLenis',
			[
				'lerp'       => (float) DW_Anim_Settings::get( 'lenis_lerp', 0.1 ),
				'duration'   => (float) DW_Anim_Settings::get( 'lenis_duration', 1.2 ),
				'smoothWheel' => DW_Anim_Settings::is_on( 'lenis_wheel' ),
				'syncTouch'  => DW_Anim_Settings::is_on( 'lenis_touch' ),
				'reduced'    => DW_Anim_Settings::is_on( 'respect_reduced' ),
				'adminBar'   => is_admin_bar_showing() ? 32 : 0,
			]
		);
	}
}
