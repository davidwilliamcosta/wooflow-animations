<?php
/**
 * Único dono dos nomes de chave de configuração e das versões de payload.
 *
 * Por que uma classe só para isso: as chaves nativas do Elementor são assimétricas
 * (widget usa `_animation` mas `animation_duration`, sem underscore) e esse detalhe
 * já custou caro. Ver CLAUDE.md, regra 1.
 *
 * @package WFAN
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WFAN_Keys {

	/** Prefixo dos nossos controles — igual em todo tipo de elemento, de propósito. */
	const P = '_wfan_';

	/** Versão do payload gravado no localStorage pelo copiar/colar. */
	const PAYLOAD_VERSION = 2;

	/** Versão do JSON escrito em data-wfan. */
	const ATTR_VERSION = 1;

	/**
	 * Animação de entrada nativa — widgets (elemento `common`).
	 * `animation_duration` não tem underscore. Confirmado em
	 * elementor/includes/widgets/common-base.php.
	 *
	 * @var array<string,string>
	 */
	const NATIVE_WIDGET = [
		'name'     => '_animation',
		'tablet'   => '_animation_tablet',
		'mobile'   => '_animation_mobile',
		'duration' => 'animation_duration',
		'delay'    => '_animation_delay',
	];

	/**
	 * Animação de entrada nativa — container, section e column.
	 * Confirmado em elementor/includes/elements/container.php.
	 *
	 * @var array<string,string>
	 */
	const NATIVE_BLOCK = [
		'name'     => 'animation',
		'tablet'   => 'animation_tablet',
		'mobile'   => 'animation_mobile',
		'duration' => 'animation_duration',
		'delay'    => 'animation_delay',
	];

	/**
	 * Sufixos dos nossos controles, sem o prefixo.
	 *
	 * @var string[]
	 */
	const OURS = [
		'preset',
		'engine',
		'trigger',
		'viewport',
		'once',
		'duration',
		'delay',
		'easing',
		'distance',
		'scale_from',
		'blur',
		'rotate',
		'split',
		'scrub_end',
		'pin',
		'loop',
		'stagger',
		'stagger_target',
		'stagger_each',
		'stagger_from',
		'lottie_url',
		'lottie_loop',
		'lottie_speed',
		'counter_to',
		'off_mobile',
	];

	/**
	 * Nome completo de um dos nossos controles.
	 *
	 * @param string $suffix Sufixo listado em OURS.
	 * @return string
	 */
	public static function ours( $suffix ) {
		return self::P . $suffix;
	}

	/**
	 * Todas as nossas chaves, já prefixadas.
	 *
	 * @return string[]
	 */
	public static function all_ours() {
		return array_map( [ __CLASS__, 'ours' ], self::OURS );
	}

	/**
	 * Mapa de chaves nativas do tipo de elemento informado.
	 *
	 * @param string $el_type Valor de `elType` (widget, container, section, column).
	 * @return array<string,string>
	 */
	public static function native_for( $el_type ) {
		return 'widget' === $el_type ? self::NATIVE_WIDGET : self::NATIVE_BLOCK;
	}
}
