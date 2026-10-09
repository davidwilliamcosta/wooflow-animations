<?php
/**
 * Aba Blur progressivo.
 *
 * O <form>, o nonce e o botão Salvar são da casca
 * (templates/admin/settings-page.php). Aqui entram só os campos.
 *
 * @package WFAN
 *
 * @var array  $wfan_settings Valores já mesclados com os padrões.
 * @var string $wfan_name     Nome da option.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wfan_blur_edges = [
	'bottom' => __( 'Rodapé da tela', 'wooflow-animations' ),
	'top'    => __( 'Topo da tela', 'wooflow-animations' ),
	'both'   => __( 'Topo e rodapé', 'wooflow-animations' ),
];
?>
<div class="wooflow-admin-card">
	<h2 class="wooflow-admin-card__title"><?php esc_html_e( 'Blur progressivo', 'wooflow-animations' ); ?></h2>
	<p class="wooflow-admin-card__intro">
		<?php esc_html_e( 'Faixa fixa na borda da tela em que o desfoque cresce aos poucos, sem a linha dura de um desfoque único. Fica sobre o conteúdo, não recebe clique e não precisa de HTML nenhum na página.', 'wooflow-animations' ); ?>
	</p>

	<div class="wooflow-toggle-block">
		<label class="wooflow-check">
			<input type="checkbox" data-wooflow-toggle name="<?php echo esc_attr( $wfan_name ); ?>[blur_enable]" value="1" <?php checked( WFAN_Settings::is_on( 'blur_enable' ) ); ?>>
			<span class="wooflow-check__text">
				<?php esc_html_e( 'Ligar o blur progressivo no site inteiro', 'wooflow-animations' ); ?>
				<span class="wooflow-description"><?php esc_html_e( 'Fica desligado dentro do editor do Elementor, para não desfocar o que se está editando.', 'wooflow-animations' ); ?></span>
			</span>
		</label>

		<div class="wooflow-toggle-block__deps">
			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-blur-pos"><?php esc_html_e( 'Borda', 'wooflow-animations' ); ?></label>
				<select id="wfan-blur-pos" class="wooflow-input" name="<?php echo esc_attr( $wfan_name ); ?>[blur_pos]">
					<?php foreach ( $wfan_blur_edges as $wfan_edge => $wfan_edge_label ) : ?>
						<option value="<?php echo esc_attr( $wfan_edge ); ?>" <?php selected( $wfan_settings['blur_pos'], $wfan_edge ); ?>><?php echo esc_html( $wfan_edge_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-blur-height"><?php esc_html_e( 'Altura (% da altura da tela)', 'wooflow-animations' ); ?></label>
				<input id="wfan-blur-height" class="wooflow-input wooflow-input--sm" type="number" step="1" min="1" max="100" name="<?php echo esc_attr( $wfan_name ); ?>[blur_height]" value="<?php echo esc_attr( $wfan_settings['blur_height'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( '20 é o padrão: um quinto da tela.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-blur-strength"><?php esc_html_e( 'Desfoque máximo (px)', 'wooflow-animations' ); ?></label>
				<input id="wfan-blur-strength" class="wooflow-input wooflow-input--sm" type="number" step="1" min="1" max="40" name="<?php echo esc_attr( $wfan_name ); ?>[blur_strength]" value="<?php echo esc_attr( $wfan_settings['blur_strength'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Valor da camada mais forte, na beirada. As outras duas acompanham na mesma proporção.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-blur-z"><?php esc_html_e( 'Camada (z-index)', 'wooflow-animations' ); ?></label>
				<input id="wfan-blur-z" class="wooflow-input wooflow-input--sm" type="number" step="1" min="0" max="999999" name="<?php echo esc_attr( $wfan_name ); ?>[blur_z]" value="<?php echo esc_attr( $wfan_settings['blur_z'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Aumente se um cabeçalho fixo ou um menu ficar na frente da faixa; diminua para que a faixa passe por baixo deles.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-check">
					<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[blur_off_mobile]" value="1" <?php checked( WFAN_Settings::is_on( 'blur_off_mobile' ) ); ?>>
					<span class="wooflow-check__text">
						<?php esc_html_e( 'Desligar em telas pequenas', 'wooflow-animations' ); ?>
						<span class="wooflow-description"><?php esc_html_e( 'Usa a mesma largura limite da aba Comportamento. Desfoque em tela cheia é caro justamente nos aparelhos mais fracos.', 'wooflow-animations' ); ?></span>
					</span>
				</label>
			</div>
		</div>
	</div>
</div>
