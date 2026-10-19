<?php
/**
 * Aba Popup circular.
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
	<h2 class="wooflow-admin-card__title"><?php esc_html_e( 'Popup circular', 'wooflow-animations' ); ?></h2>
	<p class="wooflow-admin-card__intro">
		<?php esc_html_e( 'O popup deixa de aparecer de uma vez e passa a nascer de um círculo que cresce a partir do ponto em que foi aberto — o ícone do mega menu, em geral. O site atrás escurece e desfoca junto, com o degradê partindo do mesmo ponto.', 'wooflow-animations' ); ?>
	</p>

	<?php if ( ! WFAN_Popup::available() ) : ?>
		<p class="wooflow-admin-card__intro">
			<?php esc_html_e( 'Este efeito precisa do Elementor Pro: ele se pendura nos eventos de popup, que são de lá. Sem o Pro não há popup na página, e nada é carregado.', 'wooflow-animations' ); ?>
		</p>
	<?php else : ?>
		<p class="wooflow-admin-card__intro">
			<?php
			printf(
				/* translators: %s: nome da classe CSS. */
				esc_html__( 'Vale para todos os popups do site. A origem do círculo não precisa ser configurada: é o ponto do clique que abriu o popup. Para os itens do menu entrarem escalonados depois do círculo, ponha a classe %s neles, em Avançado > Classes CSS.', 'wooflow-animations' ),
				'<code>' . esc_html( WFAN_Popup::ITEM_CLASS ) . '</code>'
			);
			?>
		</p>
	<?php endif; ?>

	<div class="wooflow-toggle-block">
		<label class="wooflow-check">
			<input type="checkbox" data-wooflow-toggle name="<?php echo esc_attr( $wfan_name ); ?>[pop_enable]" value="1" <?php checked( WFAN_Settings::is_on( 'pop_enable' ) ); ?>>
			<span class="wooflow-check__text">
				<?php esc_html_e( 'Ligar a abertura circular nos popups', 'wooflow-animations' ); ?>
				<span class="wooflow-description"><?php esc_html_e( 'Fica desligada dentro do editor do Elementor, para o popup continuar editável.', 'wooflow-animations' ); ?></span>
			</span>
		</label>

		<div class="wooflow-toggle-block__deps">
			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-popup-open"><?php esc_html_e( 'Duração da abertura (ms)', 'wooflow-animations' ); ?></label>
				<input id="wfan-popup-open" class="wooflow-input wooflow-input--sm" type="number" step="10" min="100" max="3000" name="<?php echo esc_attr( $wfan_name ); ?>[pop_open]" value="<?php echo esc_attr( $wfan_settings['pop_open'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'A entrada dos itens do menu começa com a abertura já em 35%.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-popup-close"><?php esc_html_e( 'Duração do fechamento (ms)', 'wooflow-animations' ); ?></label>
				<input id="wfan-popup-close" class="wooflow-input wooflow-input--sm" type="number" step="10" min="100" max="3000" name="<?php echo esc_attr( $wfan_name ); ?>[pop_close]" value="<?php echo esc_attr( $wfan_settings['pop_close'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Mais curto que a abertura de propósito: sair tem de ser rápido.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field wooflow-field--inline">
				<label class="wooflow-label" for="wfan-popup-overlay"><?php esc_html_e( 'Cor da sobreposição', 'wooflow-animations' ); ?></label>
				<input id="wfan-popup-overlay" class="wooflow-input wooflow-input--color" type="color" name="<?php echo esc_attr( $wfan_name ); ?>[pop_overlay]" value="<?php echo esc_attr( $wfan_settings['pop_overlay'] ); ?>">
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-popup-near"><?php esc_html_e( 'Opacidade perto do ícone (%)', 'wooflow-animations' ); ?></label>
				<input id="wfan-popup-near" class="wooflow-input wooflow-input--sm" type="number" step="1" min="0" max="100" name="<?php echo esc_attr( $wfan_name ); ?>[pop_near]" value="<?php echo esc_attr( $wfan_settings['pop_near'] ); ?>">
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-popup-far"><?php esc_html_e( 'Opacidade nas bordas (%)', 'wooflow-animations' ); ?></label>
				<input id="wfan-popup-far" class="wooflow-input wooflow-input--sm" type="number" step="1" min="0" max="100" name="<?php echo esc_attr( $wfan_name ); ?>[pop_far]" value="<?php echo esc_attr( $wfan_settings['pop_far'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Mais opaca nas bordas que no centro: é o que dá a sensação de que a luz sai do ícone.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-popup-blur"><?php esc_html_e( 'Desfoque do site atrás (px)', 'wooflow-animations' ); ?></label>
				<input id="wfan-popup-blur" class="wooflow-input wooflow-input--sm" type="number" step="1" min="0" max="40" name="<?php echo esc_attr( $wfan_name ); ?>[pop_blur]" value="<?php echo esc_attr( $wfan_settings['pop_blur'] ); ?>">
			</div>

			<div class="wooflow-field">
				<label class="wooflow-check">
					<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[pop_blur_on]" value="1" <?php checked( WFAN_Settings::is_on( 'pop_blur_on' ) ); ?>>
					<span class="wooflow-check__text">
						<?php esc_html_e( 'Desfocar o site atrás do popup', 'wooflow-animations' ); ?>
						<span class="wooflow-description"><?php esc_html_e( 'Desligue se a abertura engasgar: o desfoque é recalculado a cada quadro, sobre a página inteira.', 'wooflow-animations' ); ?></span>
					</span>
				</label>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-check">
					<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[pop_off_mobile]" value="1" <?php checked( WFAN_Settings::is_on( 'pop_off_mobile' ) ); ?>>
					<span class="wooflow-check__text">
						<?php esc_html_e( 'Sem desfoque em telas pequenas', 'wooflow-animations' ); ?>
						<span class="wooflow-description"><?php esc_html_e( 'O círculo e a sobreposição continuam; só o desfoque sai, que é a parte cara. Usa a mesma largura limite da aba Comportamento.', 'wooflow-animations' ); ?></span>
					</span>
				</label>
			</div>
		</div>
	</div>
</div>
