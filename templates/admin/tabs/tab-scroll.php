<?php
/**
 * Aba Scroll suave (Lenis).
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
	<h2 class="wooflow-admin-card__title"><?php esc_html_e( 'Scroll suave (Lenis)', 'wooflow-animations' ); ?></h2>
	<p class="wooflow-admin-card__intro">
		<?php esc_html_e( 'Substitui o scroll do navegador por um deslize com inércia, no site inteiro.', 'wooflow-animations' ); ?>
	</p>

	<div class="wooflow-toggle-block">
		<label class="wooflow-check">
			<input type="checkbox" data-wooflow-toggle name="<?php echo esc_attr( $wfan_name ); ?>[lenis_enable]" value="1" <?php checked( WFAN_Settings::is_on( 'lenis_enable' ) ); ?>>
			<span class="wooflow-check__text">
				<?php esc_html_e( 'Ligar o scroll suave no site inteiro', 'wooflow-animations' ); ?>
				<span class="wooflow-description"><?php esc_html_e( 'Fica desligado no editor do Elementor e quando o visitante pede menos movimento no sistema.', 'wooflow-animations' ); ?></span>
			</span>
		</label>

		<div class="wooflow-toggle-block__deps">
			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-lerp"><?php esc_html_e( 'Suavidade (lerp)', 'wooflow-animations' ); ?></label>
				<input id="wfan-lerp" class="wooflow-input wooflow-input--sm" type="number" step="0.01" min="0.01" max="1" name="<?php echo esc_attr( $wfan_name ); ?>[lenis_lerp]" value="<?php echo esc_attr( $wfan_settings['lenis_lerp'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Quanto menor, mais longo o deslize. 0,1 é o padrão.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-lenis-duration"><?php esc_html_e( 'Duração (s)', 'wooflow-animations' ); ?></label>
				<input id="wfan-lenis-duration" class="wooflow-input wooflow-input--sm" type="number" step="0.1" min="0.1" max="5" name="<?php echo esc_attr( $wfan_name ); ?>[lenis_duration]" value="<?php echo esc_attr( $wfan_settings['lenis_duration'] ); ?>">
			</div>

			<div class="wooflow-field">
				<span class="wooflow-label"><?php esc_html_e( 'Entradas', 'wooflow-animations' ); ?></span>

				<label class="wooflow-check">
					<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[lenis_wheel]" value="1" <?php checked( WFAN_Settings::is_on( 'lenis_wheel' ) ); ?>>
					<span class="wooflow-check__text"><?php esc_html_e( 'Suavizar a roda do mouse', 'wooflow-animations' ); ?></span>
				</label>

				<label class="wooflow-check">
					<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[lenis_touch]" value="1" <?php checked( WFAN_Settings::is_on( 'lenis_touch' ) ); ?>>
					<span class="wooflow-check__text"><?php esc_html_e( 'Suavizar o toque no celular (não recomendado)', 'wooflow-animations' ); ?></span>
				</label>
			</div>
		</div>
	</div>
</div>
