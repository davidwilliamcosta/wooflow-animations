<?php
/**
 * Lottie.
 *
 * O Elementor Pro já registra o lottie-web sob o handle `lottie`. Reusar não é
 * elegância, é obrigação: são ~250 KB duplicados na mesma página.
 * Ver CLAUDE.md, regra 6.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Lottie {

	public function __construct() {
		add_filter( 'wfan_payload', [ $this, 'guard_url' ], 10, 3 );
	}

	/**
	 * Handle da biblioteca a usar.
	 *
	 * @return string
	 */
	public static function handle() {
		$handle = wp_script_is( 'lottie', 'registered' ) ? 'lottie' : 'wfan-lottie-lib';

		/**
		 * Permite apontar para outra cópia do lottie-web já presente no site.
		 *
		 * @param string $handle Handle registrado.
		 */
		return (string) apply_filters( 'wfan_lottie_handle', $handle );
	}

	/**
	 * Um .json de Lottie carregado por http numa página https é bloqueado pelo
	 * navegador sem erro visível. Normaliza o que der e descarta o que não der.
	 *
	 * @param array  $payload   Contrato do elemento.
	 * @param string $preset_id Id do preset.
	 * @param array  $settings  Ajustes.
	 * @return array
	 */
	public function guard_url( $payload, $preset_id, $settings ) {
		unset( $preset_id, $settings );

		if ( empty( $payload['lt']['url'] ) ) {
			return $payload;
		}

		$url = $payload['lt']['url'];

		if ( is_ssl() && 0 === strpos( $url, 'http://' ) ) {
			$home = home_url();

			// Só reescreve o que é do próprio site; URL externa em http fica de fora.
			if ( 0 === strpos( $url, set_url_scheme( $home, 'http' ) ) ) {
				$payload['lt']['url'] = set_url_scheme( $url, 'https' );
			} else {
				$payload['lt']['url'] = '';
			}
		}

		return $payload;
	}
}
