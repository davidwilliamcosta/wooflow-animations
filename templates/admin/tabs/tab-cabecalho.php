<?php
/**
 * Aba Barra fixa.
 *
 * O <form>, o nonce e o botão Salvar são da casca
 * (templates/admin/settings-page.php). Aqui entram só os campos.
 *
 * Não há campo de cor nesta aba de propósito: a cor da barra é a que o
 * usuário escolheu no Elementor, em Estilo > Fundo, e o script a lê de lá.
 * Ver CLAUDE.md, regra 27.
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
	<h2 class="wooflow-admin-card__title"><?php esc_html_e( 'Barra fixa', 'wooflow-animations' ); ?></h2>
	<p class="wooflow-admin-card__intro">
		<?php esc_html_e( 'O cabeçalho começa transparente e vai ganhando fundo e sombra conforme a página desce, com amortecimento. É o efeito de barra que aparece ao rolar, sem o salto seco de ligar e desligar uma classe.', 'wooflow-animations' ); ?>
	</p>
	<p class="wooflow-admin-card__intro">
		<?php
		printf(
			/* translators: %s: nome da classe CSS. */
			esc_html__( 'Para escolher a barra, abra o container do cabeçalho e ponha a classe %s em Avançado > Classes CSS. A cor é a que você já definiu em Estilo > Fundo, do tipo Clássico e sólida — o efeito só varia a transparência dela. Com um degradê no fundo, o script não mexe em nada.', 'wooflow-animations' ),
			'<code>' . esc_html( WFAN_Header::CLASS_NAME ) . '</code>'
		);
		?>
	</p>

	<div class="wooflow-toggle-block">
		<label class="wooflow-check">
			<input type="checkbox" data-wooflow-toggle name="<?php echo esc_attr( $wfan_name ); ?>[hdr_enable]" value="1" <?php checked( WFAN_Settings::is_on( 'hdr_enable' ) ); ?>>
			<span class="wooflow-check__text">
				<?php esc_html_e( 'Ligar a barra fixa no site inteiro', 'wooflow-animations' ); ?>
				<span class="wooflow-description"><?php esc_html_e( 'Fica desligada dentro do editor do Elementor, para a barra não cobrir a alça do container.', 'wooflow-animations' ); ?></span>
			</span>
		</label>

		<div class="wooflow-toggle-block__deps">
			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-hdr-distance"><?php esc_html_e( 'Rolagem até o fundo cheio (px)', 'wooflow-animations' ); ?></label>
				<input id="wfan-hdr-distance" class="wooflow-input wooflow-input--sm" type="number" step="10" min="40" max="2000" name="<?php echo esc_attr( $wfan_name ); ?>[hdr_distance]" value="<?php echo esc_attr( $wfan_settings['hdr_distance'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Quantos pixels de rolagem levam a cor de transparente até a opacidade cheia. 280 é o padrão.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-hdr-smooth"><?php esc_html_e( 'Amortecimento (ms)', 'wooflow-animations' ); ?></label>
				<input id="wfan-hdr-smooth" class="wooflow-input wooflow-input--sm" type="number" step="10" min="0" max="1000" name="<?php echo esc_attr( $wfan_name ); ?>[hdr_smooth]" value="<?php echo esc_attr( $wfan_settings['hdr_smooth'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Quanto maior, mais macia a mudança. 0 faz a cor acompanhar a rolagem quadro a quadro. Com "reduzir movimento" no sistema, o amortecimento não entra.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-hdr-shadow-op"><?php esc_html_e( 'Força da sombra (%)', 'wooflow-animations' ); ?></label>
				<input id="wfan-hdr-shadow-op" class="wooflow-input wooflow-input--sm" type="number" step="1" min="0" max="100" name="<?php echo esc_attr( $wfan_name ); ?>[hdr_shadow_op]" value="<?php echo esc_attr( $wfan_settings['hdr_shadow_op'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Opacidade do preto na sombra, quando o fundo está cheio. 6% é discreto de propósito.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-hdr-z"><?php esc_html_e( 'Camada (z-index)', 'wooflow-animations' ); ?></label>
				<input id="wfan-hdr-z" class="wooflow-input wooflow-input--sm" type="number" step="1" min="0" max="999999" name="<?php echo esc_attr( $wfan_name ); ?>[hdr_z]" value="<?php echo esc_attr( $wfan_settings['hdr_z'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Só vale com o posicionamento abaixo ligado. 999 deixa a barra abaixo do botão de voltar ao topo.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-check">
					<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[hdr_shadow]" value="1" <?php checked( WFAN_Settings::is_on( 'hdr_shadow' ) ); ?>>
					<span class="wooflow-check__text">
						<?php esc_html_e( 'Acender a sombra junto com o fundo', 'wooflow-animations' ); ?>
						<span class="wooflow-description"><?php esc_html_e( 'A sombra cresce na mesma curva da cor, o que separa a barra do conteúdo que passa por baixo.', 'wooflow-animations' ); ?></span>
					</span>
				</label>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-check">
					<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[hdr_fixed]" value="1" <?php checked( WFAN_Settings::is_on( 'hdr_fixed' ) ); ?>>
					<span class="wooflow-check__text">
						<?php esc_html_e( 'Prender a barra no topo', 'wooflow-animations' ); ?>
						<span class="wooflow-description"><?php esc_html_e( 'Desligue se o Sticky do Elementor já cuida disso nessa barra: os dois juntos disputam a mesma posição. Ligado, desligue o Sticky lá.', 'wooflow-animations' ); ?></span>
					</span>
				</label>
			</div>
		</div>
	</div>
</div>
