<?php
/**
 * Plugin Name: MascotaFeliz Recompra
 * Description: Recordatorio de recompra según peso de la bolsa y consumo diario, y botones de WhatsApp.
 * Version: 0.2.0
 * Requires Plugins: woocommerce
 * Author: Equipo MascotaFeliz
 * Text Domain: mascotafeliz-recompra
 */

defined( 'ABSPATH' ) || exit;

// Gramos diarios aproximados por especie y tamaño (≈2–3 % del peso corporal; ver docs del reto).
const MF_CONSUMO = array(
	'perro' => array( 'pequeno' => 100, 'mediano' => 250, 'grande' => 400 ),
	'gato'  => array( 'pequeno' => 40, 'mediano' => 60, 'grande' => 80 ),
);
const MF_AVISO_DIAS_ANTES = 5;

/** Días que dura la bolsa: peso / consumo diario. */
function mf_dias( $peso_kg, $gramos_dia ) {
	return (int) floor( $peso_kg * 1000 / $gramos_dia );
}

/** Número de WhatsApp sin «+» (lo fija scripts/configurar.php). */
function mf_whatsapp() {
	return get_option( 'mascotafeliz_whatsapp', '593999999999' );
}

function mf_wa_link( $texto ) {
	return 'https://wa.me/' . mf_whatsapp() . '?text=' . rawurlencode( $texto );
}

// Campos del checkout de bloques: tamaño de mascota y consentimiento de ofertas.
add_action(
	'woocommerce_init',
	function () {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'mascotafeliz/tamano',
				'label'    => 'Tamaño de tu mascota',
				'location' => 'order',
				'type'     => 'select',
				'required' => true,
				'options'  => array(
					array( 'value' => 'pequeno', 'label' => 'Pequeño' ),
					array( 'value' => 'mediano', 'label' => 'Mediano' ),
					array( 'value' => 'grande', 'label' => 'Grande' ),
				),
			)
		);
		woocommerce_register_additional_checkout_field(
			array(
				'id'       => 'mascotafeliz/ofertas',
				'label'    => 'Quiero recibir ofertas por correo (puedo darme de baja cuando quiera)',
				'location' => 'order',
				'type'     => 'checkbox',
			)
		);
	}
);

function mf_campo( $order, $id ) {
	return Automattic\WooCommerce\Blocks\Package::container()
		->get( Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields::class )
		->get_field_from_object( $id, $order, 'other' );
}

// Programa un recordatorio por cada bolsa de alimento al confirmarse el pedido.
add_action(
	'woocommerce_order_status_changed',
	function ( $order_id, $from, $to, $order ) {
		if ( ! in_array( $to, array( 'on-hold', 'processing', 'completed' ), true ) || $order->get_meta( '_mf_recordatorio' ) ) {
			return;
		}
		$tamano = mf_campo( $order, 'mascotafeliz/tamano' );
		$unidad = 'yes' === get_option( 'mascotafeliz_modo_prueba' ) ? MINUTE_IN_SECONDS : DAY_IN_SECONDS;

		foreach ( $order->get_items() as $item_id => $item ) {
			$producto = $item->get_product();
			if ( ! $producto || ! $producto->get_weight() || ! has_term( 'alimento', 'product_cat', $producto->get_parent_id() ?: $producto->get_id() ) ) {
				continue;
			}
			$especie = 'gato' === strtolower( (string) $producto->get_attribute( 'pa_especie' ) ) ? 'gato' : 'perro';
			$gramos  = MF_CONSUMO[ $especie ][ $tamano ] ?? MF_CONSUMO[ $especie ]['mediano'];
			$dias    = mf_dias( (float) $producto->get_weight() * $item->get_quantity(), $gramos );

			as_schedule_single_action(
				time() + max( $dias - MF_AVISO_DIAS_ANTES, 1 ) * $unidad,
				'mascotafeliz_recordatorio',
				array( $order_id, $item_id, $dias ),
				'mascotafeliz'
			);
		}
		$order->update_meta_data( '_mf_recordatorio', time() );
		$order->save();
	},
	10,
	4
);

add_action(
	'mascotafeliz_recordatorio',
	function ( $order_id, $item_id, $dias ) {
		$order = wc_get_order( $order_id );
		$item  = $order ? $order->get_item( $item_id ) : null;
		// Se programa ya en «en espera» (transferencia); si nunca se pagó o se canceló, no se avisa.
		if ( ! $item || ! $item->get_product() || ! $order->has_status( array( 'processing', 'completed' ) ) ) {
			return;
		}
		$nombre  = $item->get_name();
		$comprar = add_query_arg( 'add-to-cart', $item->get_variation_id() ?: $item->get_product_id(), wc_get_cart_url() );
		$wa      = mf_wa_link( "Hola, quiero volver a pedir: $nombre" );
		$boton   = 'display:inline-block;padding:12px 20px;margin:4px 8px 4px 0;border-radius:8px;font-weight:700;text-decoration:none;';

		$cuerpo = sprintf(
			'<p>Hola %s,</p><p>Calculamos que tu <strong>%s</strong> dura unos %d días y está por terminarse en %d días. ¿Te enviamos otra?</p><p><a href="%s" style="%sbackground:#C2410C;color:#FFFFFF">Volver a comprar</a><a href="%s" style="%sbackground:#25D366;color:#1C1917">Pedir por WhatsApp</a></p>',
			esc_html( $order->get_billing_first_name() ),
			esc_html( $nombre ),
			$dias,
			MF_AVISO_DIAS_ANTES,
			esc_url( $comprar ),
			$boton,
			esc_url( $wa ),
			$boton
		);
		$mailer = WC()->mailer();
		$mailer->send( $order->get_billing_email(), 'Se está acabando el alimento de tu mascota', $mailer->wrap_message( '¿Reponemos el alimento?', $cuerpo ) );
	},
	10,
	3
);

// Botón wa.me en la ficha: uno por especie, con el producto y la raza para completar.
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;
		$especies = array_filter( array_map( 'trim', explode( ',', (string) $product->get_attribute( 'pa_especie' ) ) ) ) ?: array( '' );
		foreach ( $especies as $especie ) {
			$texto = 'Hola, quiero información sobre ' . $product->get_name() . ( $especie ? ' para mi ' . strtolower( $especie ) : '' ) . '. Raza: ';
			printf(
				'<p><a class="mf-whatsapp" href="%s" target="_blank" rel="noopener" style="display:inline-block;padding:12px 20px;border-radius:8px;background:#25D366;color:#1C1917;font-weight:700;text-decoration:none">Consultar por WhatsApp%s</a></p>',
				esc_url( mf_wa_link( $texto ) ),
				$especie ? ' (' . esc_html( $especie ) . ')' : ''
			);
		}
	},
	35
);

// Plantillas de correo de WooCommerce sobrescritas desde plantillas-correo/ (montada en wp-content).
add_filter(
	'woocommerce_locate_template',
	function ( $template, $name ) {
		$propia = WP_CONTENT_DIR . '/plantillas-correo/' . $name;
		return file_exists( $propia ) ? $propia : $template;
	},
	10,
	2
);

// Correo de ofertas solo a clientes que marcaron el consentimiento: `wp mascotafeliz ofertas`.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'mascotafeliz ofertas',
		function () {
			$html = file_get_contents( WP_CONTENT_DIR . '/plantillas-correo/ofertas.html' );
			$enviados = array();
			foreach ( wc_get_orders( array( 'limit' => -1 ) ) as $order ) {
				$correo = $order->get_billing_email();
				if ( $correo && ! isset( $enviados[ $correo ] ) && mf_campo( $order, 'mascotafeliz/ofertas' ) ) {
					$enviados[ $correo ] = wp_mail( $correo, 'Ofertas de la semana en MascotaFeliz', $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
				}
			}
			WP_CLI::success( count( $enviados ) . ' correos de ofertas enviados.' );
		}
	);
}
