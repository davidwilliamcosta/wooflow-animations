<?php
/**
 * Controle visual de presets: grade de cards com preview no hover.
 *
 * O template é montado aqui porque o catálogo é igual para todos os elementos;
 * o JS (assets/js/editor/control-picker.js) só cuida de seleção, busca e grupos.
 *
 * @package DW_Anim
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DW_Anim_Control_Picker extends \Elementor\Base_Data_Control {

	/**
	 * @return string
	 */
	public function get_type() {
		return 'dw-anim-picker';
	}

	/**
	 * @return array
	 */
	protected function get_default_settings() {
		return [
			'label_block' => true,
			'show_label'  => true,
		];
	}

	/**
	 * @return string
	 */
	public function get_default_value() {
		return '';
	}

	/**
	 * @return void
	 */
	public function content_template() {
		$groups  = DW_Anim_Presets::groups();
		$presets = DW_Anim_Presets::all();
		?>
		<div class="elementor-control-field dw-anim-picker">
			<# if ( data.show_label ) { #>
				<label class="elementor-control-title">{{{ data.label }}}</label>
			<# } #>
			<div class="elementor-control-input-wrapper">
				<input type="hidden" class="dw-anim-picker__value" data-setting="{{ data.name }}">

				<div class="dw-anim-picker__search">
					<input type="search" class="dw-anim-picker__q" placeholder="<?php echo esc_attr__( 'Buscar animação…', 'dw-copiar-animacao' ); ?>" autocomplete="off">
				</div>

				<div class="dw-anim-picker__tabs">
					<button type="button" class="dw-anim-tab is-active" data-group="*"><?php echo esc_html__( 'Todas', 'dw-copiar-animacao' ); ?></button>
					<?php foreach ( $groups as $slug => $label ) : ?>
						<button type="button" class="dw-anim-tab" data-group="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $label ); ?></button>
					<?php endforeach; ?>
				</div>

				<div class="dw-anim-picker__grid">
					<button type="button" class="dw-anim-card dw-anim-card--none" data-value="" data-group="*" data-label="<?php echo esc_attr__( 'Nenhuma', 'dw-copiar-animacao' ); ?>">
						<span class="dw-anim-card__stage"><span class="dw-anim-card__box"></span></span>
						<span class="dw-anim-card__label"><?php echo esc_html__( 'Nenhuma', 'dw-copiar-animacao' ); ?></span>
					</button>

					<?php foreach ( $presets as $id => $preset ) : ?>
						<button type="button"
							class="dw-anim-card"
							data-value="<?php echo esc_attr( $id ); ?>"
							data-group="<?php echo esc_attr( $preset['group'] ); ?>"
							data-engine="<?php echo esc_attr( $preset['engine'] ); ?>"
							data-label="<?php echo esc_attr( $preset['label'] ); ?>"
							title="<?php echo esc_attr( $preset['label'] ); ?>">
							<span class="dw-anim-card__stage">
								<span class="dw-anim-card__box dw-pv-<?php echo esc_attr( $id ); ?>"></span>
							</span>
							<span class="dw-anim-card__label"><?php echo esc_html( $preset['label'] ); ?></span>
							<?php if ( 'css' !== $preset['engine'] ) : ?>
								<span class="dw-anim-card__engine"><?php echo esc_html( $preset['engine'] ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="dw-anim-picker__saved" data-dw-saved></div>

				<# if ( data.description ) { #>
					<div class="elementor-control-field-description">{{{ data.description }}}</div>
				<# } #>
			</div>
		</div>
		<?php
	}
}
