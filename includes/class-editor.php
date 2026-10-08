<?php
/**
 * Assets e dados do editor.
 *
 * O script antigo era injetado inline em `elementor/editor/footer` e esperava o
 * editor num setInterval. Agora é arquivo enfileirado com cache-bust, e quem
 * avisa que o editor subiu é o evento `elementor/init`.
 *
 * @package DW_Anim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DW_Anim_Editor {

	public function __construct() {
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'scripts' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'styles' ] );
	}

	/**
	 * @return void
	 */
	public function styles() {
		wp_enqueue_style(
			'dw-anim-editor',
			DW_Anim_Assets::url( 'assets/css/editor.css' ),
			[],
			DW_Anim_Assets::ver( 'assets/css/editor.css' )
		);
	}

	/**
	 * @return void
	 */
	public function scripts() {
		$files = [
			'dw-anim-copy-paste'    => 'assets/js/editor/copy-paste.js',
			'dw-anim-control-picker' => 'assets/js/editor/control-picker.js',
			'dw-anim-panel'         => 'assets/js/editor/panel.js',
		];

		$previous = [ 'jquery' ];

		foreach ( $files as $handle => $rel ) {
			wp_enqueue_script(
				$handle,
				DW_Anim_Assets::url( $rel ),
				$previous,
				DW_Anim_Assets::ver( $rel ),
				true
			);

			$previous = [ $handle ];
		}

		wp_localize_script( 'dw-anim-copy-paste', 'dwAnimEditor', $this->data() );
	}

	/**
	 * @return array
	 */
	private function data() {
		return [
			'presets'  => DW_Anim_Presets::for_js(),
			'groups'   => DW_Anim_Presets::groups(),
			'triggers' => DW_Anim_Presets::triggers(),
			'library'  => DW_Anim_Library::all(),
			'debug'    => DW_Anim_Settings::is_on( 'debug' ) ? 1 : 0,
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( DW_Anim_Library::NONCE ),
			'keys'     => [
				'prefix'       => DW_Anim_Keys::P,
				'ours'         => DW_Anim_Keys::all_ours(),
				'preset'       => DW_Anim_Keys::ours( 'preset' ),
				'trigger'      => DW_Anim_Keys::ours( 'trigger' ),
				'nativeWidget' => DW_Anim_Keys::NATIVE_WIDGET,
				'nativeBlock'  => DW_Anim_Keys::NATIVE_BLOCK,
				'version'      => DW_Anim_Keys::PAYLOAD_VERSION,
			],
			'i18n'     => [
				'copyTitle'    => __( 'Copiar animação', 'dw-copiar-animacao' ),
				'pasteTitle'   => __( 'Colar animação', 'dw-copiar-animacao' ),
				'copied'       => __( 'Animação copiada.', 'dw-copiar-animacao' ),
				'copiedEmpty'  => __( 'Elemento sem animação copiado — colar vai limpar a animação do destino.', 'dw-copiar-animacao' ),
				'nothingYet'   => __( 'Nenhuma animação copiada ainda.', 'dw-copiar-animacao' ),
				/* translators: %d: quantidade de elementos */
				'pastedOne'    => __( 'Animação colada em %d elemento.', 'dw-copiar-animacao' ),
				/* translators: %d: quantidade de elementos */
				'pastedMany'   => __( 'Animação colada em %d elementos.', 'dw-copiar-animacao' ),
				'historyPaste' => __( 'Colar animação', 'dw-copiar-animacao' ),
				'askName'      => __( 'Nome para esta animação:', 'dw-copiar-animacao' ),
				'saved'        => __( 'Animação salva na biblioteca.', 'dw-copiar-animacao' ),
				'savedTitle'   => __( 'Minhas animações', 'dw-copiar-animacao' ),
				'deleteOne'    => __( 'Apagar', 'dw-copiar-animacao' ),
				'noPreset'     => __( 'Escolha uma animação primeiro.', 'dw-copiar-animacao' ),
				'selectOne'    => __( 'Selecione um elemento primeiro.', 'dw-copiar-animacao' ),
				'playing'      => __( 'Reproduzindo no preview…', 'dw-copiar-animacao' ),
				'error'        => __( 'Não foi possível concluir.', 'dw-copiar-animacao' ),
			],
		];
	}
}
