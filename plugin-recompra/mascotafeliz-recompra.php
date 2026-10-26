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
			$antes   = max( 1, min( MF_AVISO_DIAS_ANTES, $dias - 1 ) ); // Bolsas que duran ≤5 días: avisa al día 1 con lo que de verdad queda.

			as_schedule_single_action(
				time() + max( $dias - $antes, 1 ) * $unidad,
				'mascotafeliz_recordatorio',
				array( $order_id, $item_id, $dias, $antes ),
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
	function ( $order_id, $item_id, $dias, $antes = MF_AVISO_DIAS_ANTES ) { // Las acciones viejas llegan con 3 argumentos.
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
			$antes,
			esc_url( $comprar ),
			$boton,
			esc_url( $wa ),
			$boton
		);
		$mailer = WC()->mailer();
		$mailer->send( $order->get_billing_email(), 'Se está acabando el alimento de tu mascota', $mailer->wrap_message( '¿Reponemos el alimento?', $cuerpo ) );
	},
	10,
	4
);

// Bloque de consulta wa.me en la ficha: un enlace por especie, con el producto y la raza para completar.
add_action(
	'woocommerce_single_product_summary',
	function () {
		global $product;
		$especies = array_filter( array_map( 'trim', explode( ',', (string) $product->get_attribute( 'pa_especie' ) ) ) ) ?: array( '' );
		$enlaces  = '';
		foreach ( $especies as $especie ) {
			$texto     = 'Hola, quiero información sobre ' . $product->get_name() . ( $especie ? ' para mi ' . strtolower( $especie ) : '' ) . '. Raza: ';
			$etiqueta  = $especie ? 'Para ' . strtolower( $especie ) : 'Abrir chat';
			$enlaces  .= sprintf(
				'<a class="mf-whatsapp" href="%s" target="_blank" rel="noopener" aria-label="%s">%s</a>',
				esc_url( mf_wa_link( $texto ) ),
				esc_attr( 'Consultar por WhatsApp' . ( $especie ? ' para ' . strtolower( $especie ) : '' ) ),
				esc_html( $etiqueta )
			);
		}
		$icono = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.3-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.4.8 3.2.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3z"/></svg>';
		printf(
			'<div class="mf-consulta"><span class="mf-consulta__icono">%s</span><div class="mf-consulta__texto"><p class="mf-consulta__titulo">Consultar por WhatsApp</p><p>¿Dudas sobre la raza o la ración? Se abre el chat con el producto ya escrito.</p><div class="mf-consulta__opciones">%s</div></div></div>',
			$icono, // SVG fijo, sin datos del usuario.
			$enlaces // Ya escapado arriba.
		);
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
			$html     = file_get_contents( WP_CONTENT_DIR . '/plantillas-correo/ofertas.html' );
			$bajas    = (array) get_option( 'mascotafeliz_bajas', array() );
			$vistos   = array();
			$enviados = 0;
			foreach ( wc_get_orders( array( 'limit' => -1 ) ) as $order ) { // Del más reciente al más antiguo.
				$correo = strtolower( $order->get_billing_email() );
				if ( ! $correo || isset( $vistos[ $correo ] ) ) {
					continue;
				}
				$vistos[ $correo ] = true; // Decide solo el último pedido: si desmarcó la casilla, ya no recibe.
				if ( mf_campo( $order, 'mascotafeliz/ofertas' ) && ! in_array( $correo, $bajas, true ) ) {
					$enviados += (int) wp_mail( $correo, 'Ofertas de la semana en MascotaFeliz', $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
				}
			}
			WP_CLI::success( "$enviados correos de ofertas enviados." );
		}
	);

	// Quien responde «BAJA» deja de recibir ofertas: `wp mascotafeliz baja correo@ejemplo.com`.
	WP_CLI::add_command(
		'mascotafeliz baja',
		function ( $args ) {
			$correo = strtolower( sanitize_email( $args[0] ?? '' ) );
			if ( ! is_email( $correo ) ) {
				WP_CLI::error( 'Correo no válido.' );
			}
			$bajas = (array) get_option( 'mascotafeliz_bajas', array() );
			update_option( 'mascotafeliz_bajas', array_values( array_unique( array_merge( $bajas, array( $correo ) ) ) ), false );
			WP_CLI::success( "$correo dado de baja de las ofertas." );
		}
	);
}
