<?php
/**
 * Checagem de requisitos. Nunca fatal: sem Elementor o plugin só avisa e sai.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Requirements {

	const MIN_PHP        = '7.4';
	const MIN_WP         = '6.0';
	const MIN_ELEMENTOR  = '3.5.0';

	/**
	 * Motivo da reprovação, quando houver.
	 *
	 * @var string
	 */
	private static $reason = '';

	/**
	 * @return bool
	 */
	public static function met() {
		if ( version_compare( PHP_VERSION, self::MIN_PHP, '<' ) ) {
			self::$reason = sprintf(
				/* translators: 1: versão exigida, 2: versão instalada */
				__( 'exige PHP %1$s ou superior (esta instalação usa %2$s)', 'wooflow-animations' ),
				self::MIN_PHP,
				PHP_VERSION
			);
			return false;
		}

		if ( version_compare( get_bloginfo( 'version' ), self::MIN_WP, '<' ) ) {
			self::$reason = sprintf(
				/* translators: %s: versão exigida do WordPress */
				__( 'exige WordPress %s ou superior', 'wooflow-animations' ),
				self::MIN_WP
			);
			return false;
		}

		if ( ! did_action( 'elementor/loaded' ) ) {
			self::$reason = __( 'precisa do Elementor instalado e ativo', 'wooflow-animations' );
			return false;
		}

		if ( defined( 'ELEMENTOR_VERSION' ) && version_compare( ELEMENTOR_VERSION, self::MIN_ELEMENTOR, '<' ) ) {
			self::$reason = sprintf(
				/* translators: %s: versão exigida do Elementor */
				__( 'exige Elementor %s ou superior', 'wooflow-animations' ),
				self::MIN_ELEMENTOR
			);
			return false;
		}

		return true;
	}

	/**
	 * @return void
	 */
	public static function hook_notice() {
		add_action( 'admin_notices', [ __CLASS__, 'notice' ] );
	}

	/**
	 * @return void
	 */
	public static function notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s.</p></div>',
			esc_html__( 'WooFlow Animations for Elementor', 'wooflow-animations' ),
			esc_html( self::$reason )
		);
	}
}
