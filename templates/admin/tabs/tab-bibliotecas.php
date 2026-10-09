<?php
/**
 * Aba Bibliotecas.
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
	<h2 class="wooflow-admin-card__title"><?php esc_html_e( 'Bibliotecas', 'wooflow-animations' ); ?></h2>
	<p class="wooflow-admin-card__intro">
		<?php esc_html_e( 'Nenhuma biblioteca é carregada por padrão: cada uma entra apenas nas páginas que usam um preset que a exige. Desmarcar aqui bloqueia o carregamento mesmo nessas páginas.', 'wooflow-animations' ); ?>
	</p>

	<label class="wooflow-check">
		<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[lib_gsap]" value="1" <?php checked( WFAN_Settings::is_on( 'lib_gsap' ) ); ?>>
		<span class="wooflow-check__text">
			<?php esc_html_e( 'GSAP + ScrollTrigger', 'wooflow-animations' ); ?>
			<span class="wooflow-description"><?php esc_html_e( 'Presets de scroll travado, pin, parallax e contador.', 'wooflow-animations' ); ?></span>
		</span>
	</label>

	<label class="wooflow-check">
		<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[lib_anime]" value="1" <?php checked( WFAN_Settings::is_on( 'lib_anime' ) ); ?>>
		<span class="wooflow-check__text">
			<?php esc_html_e( 'Anime.js', 'wooflow-animations' ); ?>
			<span class="wooflow-description"><?php esc_html_e( 'Presets de SVG.', 'wooflow-animations' ); ?></span>
		</span>
	</label>

	<label class="wooflow-check">
		<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[lib_lottie]" value="1" <?php checked( WFAN_Settings::is_on( 'lib_lottie' ) ); ?>>
		<span class="wooflow-check__text">
			<?php esc_html_e( 'Lottie', 'wooflow-animations' ); ?>
			<span class="wooflow-description"><?php esc_html_e( 'Presets de Lottie. Reusa a biblioteca do Elementor Pro quando ele estiver ativo.', 'wooflow-animations' ); ?></span>
		</span>
	</label>
</div>
