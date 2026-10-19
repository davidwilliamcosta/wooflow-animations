<?php
/**
 * Aba Voltar ao topo.
 *
 * O <form>, o nonce e o botão Salvar são da casca
 * (templates/admin/settings-page.php). Aqui entram só os campos.
 *
 * As listas dos <select> saem dos mapas do WFAN_BackToTop, que são os mesmos
 * que o WFAN_Settings::selects() aceita no sanitize — opção oferecida aqui e
 * recusada lá seria campo que volta sozinho ao salvar, e o smoke test reprova.
 *
 * @package WFAN
 *
 * @var array  $wfan_settings Valores já mesclados com os padrões.
 * @var string $wfan_name     Nome da option.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wfan_btt_selects = [
	'btt_pos'   => [
		'label'   => __( 'Posição', 'wooflow-animations' ),
		'options' => WFAN_BackToTop::positions(),
		'help'    => '',
	],
	'btt_shape' => [
		'label'   => __( 'Forma', 'wooflow-animations' ),
		'options' => WFAN_BackToTop::shapes(),
		'help'    => __( 'O anel de progresso só existe na forma círculo: a máscara dele é redonda.', 'wooflow-animations' ),
	],
	'btt_icon'  => [
		'label'   => __( 'Ícone', 'wooflow-animations' ),
		'options' => WFAN_BackToTop::icons(),
		'help'    => __( 'SVG inline, em traço: nenhuma requisição e nenhuma fonte de ícones.', 'wooflow-animations' ),
	],
	'btt_anim'  => [
		'label'   => __( 'Entrada do botão', 'wooflow-animations' ),
		'options' => WFAN_BackToTop::entrances(),
		'help'    => '',
	],
];

$wfan_btt_colors = [
	'btt_bg'       => __( 'Cor do fundo', 'wooflow-animations' ),
	'btt_fg'       => __( 'Cor do ícone', 'wooflow-animations' ),
	'btt_bg_hover' => __( 'Cor do fundo no hover', 'wooflow-animations' ),
];
?>
<div class="wooflow-admin-card">
	<h2 class="wooflow-admin-card__title"><?php esc_html_e( 'Voltar ao topo', 'wooflow-animations' ); ?></h2>
	<p class="wooflow-admin-card__intro">
		<?php esc_html_e( 'Botão fixo que aparece depois de uma certa rolagem e volta ao topo da página. É um controle de verdade: focável pelo teclado e com nome lido por leitor de tela. Quando o scroll suave está ligado, a subida passa por ele.', 'wooflow-animations' ); ?>
	</p>
	<p class="wooflow-admin-card__intro">
		<?php esc_html_e( 'O Elementor Pro tem um widget "Scroll to top" próprio: ligar os dois deixa dois botões na tela, e não há como detectar isso daqui.', 'wooflow-animations' ); ?>
	</p>

	<div class="wooflow-toggle-block">
		<label class="wooflow-check">
			<input type="checkbox" data-wooflow-toggle name="<?php echo esc_attr( $wfan_name ); ?>[btt_enable]" value="1" <?php checked( WFAN_Settings::is_on( 'btt_enable' ) ); ?>>
			<span class="wooflow-check__text">
				<?php esc_html_e( 'Ligar o botão no site inteiro', 'wooflow-animations' ); ?>
				<span class="wooflow-description"><?php esc_html_e( 'Fica desligado dentro do editor do Elementor, para não cobrir a alça de um elemento.', 'wooflow-animations' ); ?></span>
			</span>
		</label>

		<div class="wooflow-toggle-block__deps">
			<?php foreach ( $wfan_btt_selects as $wfan_btt_key => $wfan_btt_field ) : ?>
				<div class="wooflow-field">
					<label class="wooflow-label" for="wfan-<?php echo esc_attr( $wfan_btt_key ); ?>"><?php echo esc_html( $wfan_btt_field['label'] ); ?></label>
					<select id="wfan-<?php echo esc_attr( $wfan_btt_key ); ?>" class="wooflow-input" name="<?php echo esc_attr( $wfan_name ); ?>[<?php echo esc_attr( $wfan_btt_key ); ?>]">
						<?php foreach ( $wfan_btt_field['options'] as $wfan_btt_value => $wfan_btt_label ) : ?>
							<option value="<?php echo esc_attr( $wfan_btt_value ); ?>" <?php selected( $wfan_settings[ $wfan_btt_key ], $wfan_btt_value ); ?>><?php echo esc_html( $wfan_btt_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( '' !== $wfan_btt_field['help'] ) : ?>
						<p class="wooflow-description"><?php echo esc_html( $wfan_btt_field['help'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-btt-size"><?php esc_html_e( 'Tamanho (px)', 'wooflow-animations' ); ?></label>
				<input id="wfan-btt-size" class="wooflow-input wooflow-input--sm" type="number" step="1" min="28" max="96" name="<?php echo esc_attr( $wfan_name ); ?>[btt_size]" value="<?php echo esc_attr( $wfan_settings['btt_size'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( '48 é o padrão. O ícone acompanha: ele ocupa metade do botão.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-btt-x"><?php esc_html_e( 'Afastamento lateral (px)', 'wooflow-animations' ); ?></label>
				<input id="wfan-btt-x" class="wooflow-input wooflow-input--sm" type="number" step="1" min="0" max="200" name="<?php echo esc_attr( $wfan_name ); ?>[btt_x]" value="<?php echo esc_attr( $wfan_settings['btt_x'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Não vale na posição centralizada, que fica no meio da tela.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-btt-y"><?php esc_html_e( 'Afastamento da base (px)', 'wooflow-animations' ); ?></label>
				<input id="wfan-btt-y" class="wooflow-input wooflow-input--sm" type="number" step="1" min="0" max="200" name="<?php echo esc_attr( $wfan_name ); ?>[btt_y]" value="<?php echo esc_attr( $wfan_settings['btt_y'] ); ?>">
			</div>

			<?php foreach ( $wfan_btt_colors as $wfan_btt_key => $wfan_btt_label ) : ?>
				<div class="wooflow-field wooflow-field--inline">
					<label class="wooflow-label" for="wfan-<?php echo esc_attr( $wfan_btt_key ); ?>"><?php echo esc_html( $wfan_btt_label ); ?></label>
					<input id="wfan-<?php echo esc_attr( $wfan_btt_key ); ?>" class="wooflow-input wooflow-input--color" type="color" name="<?php echo esc_attr( $wfan_name ); ?>[<?php echo esc_attr( $wfan_btt_key ); ?>]" value="<?php echo esc_attr( $wfan_settings[ $wfan_btt_key ] ); ?>">
				</div>
			<?php endforeach; ?>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-btt-threshold"><?php esc_html_e( 'Aparecer depois de (px de rolagem)', 'wooflow-animations' ); ?></label>
				<input id="wfan-btt-threshold" class="wooflow-input wooflow-input--sm" type="number" step="10" min="0" max="20000" name="<?php echo esc_attr( $wfan_name ); ?>[btt_threshold]" value="<?php echo esc_attr( $wfan_settings['btt_threshold'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( '0 deixa o botão sempre visível.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-btt-duration"><?php esc_html_e( 'Duração da subida (s)', 'wooflow-animations' ); ?></label>
				<input id="wfan-btt-duration" class="wooflow-input wooflow-input--sm" type="number" step="0.1" min="0.1" max="3" name="<?php echo esc_attr( $wfan_name ); ?>[btt_duration]" value="<?php echo esc_attr( $wfan_settings['btt_duration'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Rolar no meio da subida interrompe ela: é o usuário dizendo que mudou de ideia. Com "reduzir movimento" no sistema, a ida ao topo é um salto.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-label" for="wfan-btt-z"><?php esc_html_e( 'Camada (z-index)', 'wooflow-animations' ); ?></label>
				<input id="wfan-btt-z" class="wooflow-input wooflow-input--sm" type="number" step="1" min="0" max="999999" name="<?php echo esc_attr( $wfan_name ); ?>[btt_z]" value="<?php echo esc_attr( $wfan_settings['btt_z'] ); ?>">
				<p class="wooflow-description"><?php esc_html_e( 'Começa em 1000, uma camada acima do blur progressivo — é o que deixa o botão nítido sobre a faixa. Se você mexer na camada do blur, confira esta também.', 'wooflow-animations' ); ?></p>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-check">
					<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[btt_ring]" value="1" <?php checked( WFAN_Settings::is_on( 'btt_ring' ) ); ?>>
					<span class="wooflow-check__text">
						<?php esc_html_e( 'Mostrar o anel de progresso', 'wooflow-animations' ); ?>
						<span class="wooflow-description"><?php esc_html_e( 'Um anel em volta do botão que acompanha quanto da página já foi rolada. Usa a cor do ícone.', 'wooflow-animations' ); ?></span>
					</span>
				</label>
			</div>

			<div class="wooflow-field">
				<label class="wooflow-check">
					<input type="checkbox" name="<?php echo esc_attr( $wfan_name ); ?>[btt_off_mobile]" value="1" <?php checked( WFAN_Settings::is_on( 'btt_off_mobile' ) ); ?>>
					<span class="wooflow-check__text">
						<?php esc_html_e( 'Desligar em telas pequenas', 'wooflow-animations' ); ?>
						<span class="wooflow-description"><?php esc_html_e( 'Usa a mesma largura limite da aba Comportamento.', 'wooflow-animations' ); ?></span>
					</span>
				</label>
			</div>
		</div>
	</div>
</div>
