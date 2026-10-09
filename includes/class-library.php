<?php
/**
 * "Minhas animações": conjuntos de ajustes salvos com nome e reaplicáveis em
 * qualquer elemento. É a evolução do copiar/colar, que guarda só um de cada vez.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Library {

	const OPTION = 'wfan_library';
	const NONCE  = 'wfan_library';
	const MAX    = 100;

	public function __construct() {
		add_action( 'wp_ajax_wfan_save_preset', [ $this, 'ajax_save' ] );
		add_action( 'wp_ajax_wfan_delete_preset', [ $this, 'ajax_delete' ] );
	}

	/**
	 * @return array<int,array{id:string,label:string,settings:array}>
	 */
	public static function all() {
		$items = get_option( self::OPTION, [] );

		return is_array( $items ) ? array_values( $items ) : [];
	}

	/**
	 * Mantém só as chaves que são nossas — nunca grava ajuste alheio.
	 *
	 * Pública porque o ▶ Testar do editor passa pela mesma peneira antes de
	 * montar o contrato de preview (`WFAN_Editor::ajax_preview_spec()`).
	 *
	 * @param array $settings Pares chave/valor vindos do editor.
	 * @return array
	 */
	public static function sanitize_settings( $settings ) {
		$allowed = WFAN_Keys::all_ours();
		$clean   = [];

		foreach ( (array) $settings as $key => $value ) {
			if ( ! in_array( $key, $allowed, true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$clean[ $key ] = array_map( 'sanitize_text_field', array_filter( $value, 'is_scalar' ) );
				continue;
			}

			$clean[ $key ] = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
		}

		return $clean;
	}

	/**
	 * @return void
	 */
	public function ajax_save() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão.', 'wooflow-animations' ) ], 403 );
		}

		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';

		if ( '' === $label ) {
			wp_send_json_error( [ 'message' => __( 'Dê um nome para a animação.', 'wooflow-animations' ) ], 400 );
		}

		$raw      = isset( $_POST['settings'] ) ? json_decode( wp_unslash( $_POST['settings'] ), true ) : [];
		$settings = self::sanitize_settings( $raw );

		if ( empty( $settings[ WFAN_Keys::ours( 'preset' ) ] ) ) {
			wp_send_json_error( [ 'message' => __( 'Escolha uma animação antes de salvar.', 'wooflow-animations' ) ], 400 );
		}

		$items = self::all();

		if ( count( $items ) >= self::MAX ) {
			wp_send_json_error( [ 'message' => __( 'A biblioteca está cheia. Apague alguma antes de salvar outra.', 'wooflow-animations' ) ], 400 );
		}

		$items[] = [
			'id'       => uniqid( 'wfan_', false ),
			'label'    => $label,
			'settings' => $settings,
		];

		update_option( self::OPTION, $items, false );

		wp_send_json_success( [ 'items' => $items ] );
	}

	/**
	 * @return void
	 */
	public function ajax_delete() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Sem permissão.', 'wooflow-animations' ) ], 403 );
		}

		$id    = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$items = array_values(
			array_filter(
				self::all(),
				static function ( $item ) use ( $id ) {
					return $item['id'] !== $id;
				}
			)
		);

		update_option( self::OPTION, $items, false );

		wp_send_json_success( [ 'items' => $items ] );
	}
}
