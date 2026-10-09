<?php
/**
 * Aba Comportamento.
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
?>
<div class="wooflow-admin-card">
	<h2 class="wooflow-admin-card__title"><?php esc_html_e( 'Comportamento', 'wooflow-animations' ); ?></h2>

	<div class="wooflow-field">
		<span class="wooflow-label"><?php esc_html_e( 'Acessibilidade', 'wooflow-animations' ); ?></span>
		<label class="wooflow-check">
			<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[respect_reduced]" value="1" <?php checked( WFAN_Settings::is_on( 'respect_reduced' ) ); ?>>
			<span class="wooflow-check__text">
				<?php esc_html_e( 'Respeitar "reduzir movimento" do sistema do visitante', 'wooflow-animations' ); ?>
				<span class="wooflow-description"><?php esc_html_e( 'Recomendado manter ligado. O conteúdo aparece normalmente, só sem movimento.', 'wooflow-animations' ); ?></span>
			</span>
		</label>
	</div>

	<div class="wooflow-field">
		<span class="wooflow-label"><?php esc_html_e( 'Celular', 'wooflow-animations' ); ?></span>

		<div class="wooflow-toggle-block">
			<label class="wooflow-check">
				<input type="checkbox" data-wooflow-toggle name="<?php echo esc_attr( $wfan_name ); ?>[off_mobile]" value="1" <?php checked( WFAN_Settings::is_on( 'off_mobile' ) ); ?>>
				<span class="wooflow-check__text"><?php esc_html_e( 'Desligar todas as animações em telas pequenas', 'wooflow-animations' ); ?></span>
			</label>

			<div class="wooflow-toggle-block__deps">
				<div class="wooflow-field wooflow-field--inline">
					<label class="wooflow-label" for="wfan-bp"><?php esc_html_e( 'Largura limite (px)', 'wooflow-animations' ); ?></label>
					<input id="wfan-bp" class="wooflow-input wooflow-input--sm" type="number" step="1" min="320" max="1920" name="<?php echo esc_attr( $wfan_name ); ?>[mobile_bp]" value="<?php echo esc_attr( $wfan_settings['mobile_bp'] ); ?>">
				</div>
			</div>
		</div>

		<p class="wooflow-description"><?php esc_html_e( 'A mesma largura vale para o blur progressivo, quando ele estiver configurado para sair em telas pequenas.', 'wooflow-animations' ); ?></p>
	</div>

	<div class="wooflow-field">
		<span class="wooflow-label"><?php esc_html_e( 'Animação nativa', 'wooflow-animations' ); ?></span>
		<label class="wooflow-check">
			<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[disable_native]" value="1" <?php checked( WFAN_Settings::is_on( 'disable_native' ) ); ?>>
			<span class="wooflow-check__text">
				<?php esc_html_e( 'Desligar a animação de entrada do Elementor quando o mesmo elemento tiver uma animação WooFlow', 'wooflow-animations' ); ?>
				<span class="wooflow-description"><?php esc_html_e( 'Evita as duas animações rodando juntas no mesmo elemento.', 'wooflow-animations' ); ?></span>
			</span>
		</label>
	</div>

	<div class="wooflow-field">
		<span class="wooflow-label"><?php esc_html_e( 'Diagnóstico', 'wooflow-animations' ); ?></span>
		<label class="wooflow-check">
			<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[debug]" value="1" <?php checked( WFAN_Settings::is_on( 'debug' ) ); ?>>
			<span class="wooflow-check__text"><?php esc_html_e( 'Registrar no console do navegador cada animação registrada e disparada', 'wooflow-animations' ); ?></span>
		</label>
	</div>
</div>
