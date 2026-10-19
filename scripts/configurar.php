<?php
/**
 * Configura la tienda local. Se ejecuta con scripts/configurar.sh; es idempotente.
 */

$whatsapp = getenv( 'MF_WHATSAPP' ) ?: '593999999999';
update_option( 'mascotafeliz_whatsapp', $whatsapp );
update_option( 'mascotafeliz_modo_prueba', 'yes' ); // Recordatorio en minutos; poner 'no' en el sitio final.

// Pagos (#8). No hay pasarela de prueba en el núcleo: se usa «cheque» como pago simulado.
$pagos = array(
	'bacs'   => array( 'title' => 'Transferencia bancaria', 'description' => 'Transfiere a la cuenta indicada y envía el comprobante por WhatsApp. Tu pedido se despacha al confirmar el pago.' ),
	'cod'    => array( 'title' => 'Pago contra entrega', 'description' => 'Paga en efectivo al recibir tu pedido.' ),
	'cheque' => array( 'title' => 'Pago de prueba (simulado)', 'description' => 'Solo para pruebas: no se cobra nada.' ),
);
foreach ( $pagos as $id => $ajustes ) {
	update_option( "woocommerce_{$id}_settings", array_merge( get_option( "woocommerce_{$id}_settings", array() ), $ajustes, array( 'enabled' => 'yes' ) ) );
}
update_option(
	'woocommerce_bacs_accounts',
	array( array( 'account_name' => 'MascotaFeliz', 'account_number' => '0000000000', 'bank_name' => 'Banco de prueba', 'sort_code' => '', 'iban' => '', 'bic' => '' ) )
);

// Envíos (#8): la primera zona que coincide gana, por eso Cuenca va antes que Azuay.
$zonas = array(
	array( 'Cuenca', array( array( 'EC:EC-A', 'state' ), array( '0101*', 'postcode' ) ), '2.00' ),
	array( 'Resto de Azuay', array( array( 'EC:EC-A', 'state' ) ), '4.00' ),
	array( 'Ecuador', array( array( 'EC', 'country' ) ), '8.00' ),
);
foreach ( $zonas as $orden => list( $nombre, $ubicaciones, $costo ) ) {
	$zona = null;
	foreach ( WC_Shipping_Zones::get_zones() as $z ) {
		if ( $z['zone_name'] === $nombre ) {
			$zona = new WC_Shipping_Zone( $z['id'] );
		}
	}
	if ( $zona ) {
		continue;
	}
	$zona = new WC_Shipping_Zone();
	$zona->set_zone_name( $nombre );
	$zona->set_zone_order( $orden );
	foreach ( $ubicaciones as list( $codigo, $tipo ) ) {
		$zona->add_location( $codigo, $tipo );
	}
	$zona->save();
	$instancia = $zona->add_shipping_method( 'flat_rate' );
	update_option( "woocommerce_flat_rate_{$instancia}_settings", array( 'title' => 'Envío a domicilio', 'tax_status' => 'none', 'cost' => $costo ) );
}

// Retiro en tienda (#8) con el método de recogida del checkout de bloques.
update_option( 'woocommerce_pickup_location_settings', array( 'enabled' => 'yes', 'title' => 'Retiro en tienda', 'cost' => '', 'tax_status' => 'none' ) );
update_option(
	'pickup_location_pickup_locations',
	array(
		array(
			'name'    => 'MascotaFeliz Cuenca',
			'address' => array( 'address_1' => 'Av. Solano y 12 de Abril', 'city' => 'Cuenca', 'state' => 'EC-A', 'postcode' => '010107', 'country' => 'EC' ),
			'details' => 'Lunes a sábado de 9:00 a 18:00.',
			'enabled' => true,
		),
	)
);

// Páginas legales y de inicio (#9).
foreach ( array( 'Política de privacidad', 'Política de devoluciones' ) as $titulo ) {
	foreach ( get_posts( array( 'post_type' => 'page', 'title' => $titulo, 'post_status' => 'draft' ) ) as $p ) {
		wp_update_post( array( 'ID' => $p->ID, 'post_status' => 'publish' ) );
	}
}
$inicio = get_page_by_path( 'inicio' );
if ( ! $inicio ) {
	$inicio_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Inicio',
			'post_name'    => 'inicio',
			'post_content' => '<!-- wp:cover {"customOverlayColor":"#FFEDD5","isUserOverlayColor":true,"minHeight":320,"isDark":false} -->
<div class="wp-block-cover is-light" style="min-height:320px"><span aria-hidden="true" class="wp-block-cover__background has-background-dim-100 has-background-dim" style="background-color:#FFEDD5"></span><div class="wp-block-cover__inner-container"><!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">Todo para tu mascota, a la puerta de tu casa</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Alimento, accesorios e higiene con envío en Cuenca y todo Ecuador. Te avisamos antes de que se acabe la bolsa.</p>
<!-- /wp:paragraph -->

<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/tienda/">Ver la tienda</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div></div>
<!-- /wp:cover -->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Categorías</h2>
<!-- /wp:heading -->

<!-- wp:woocommerce/product-categories {"isDropdown":false,"hasCount":false,"hasImage":true} /-->

<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Lo más pedido</h2>
<!-- /wp:heading -->

<!-- wp:shortcode -->
[products limit="4" columns="4" orderby="popularity"]
<!-- /wp:shortcode -->',
		)
	);
} else {
	$inicio_id = $inicio->ID;
}
update_post_meta( $inicio_id, '_kad_post_title', 'hide' ); // El hero ya lleva el único h1.
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $inicio_id );

// Menú principal, también en móvil (#9).
$menu = wp_get_nav_menu_object( 'Principal' );
if ( ! $menu ) {
	$menu_id = wp_create_nav_menu( 'Principal' );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-title' => 'Inicio', 'menu-item-object' => 'page', 'menu-item-object-id' => $inicio_id, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-object' => 'page', 'menu-item-object-id' => wc_get_page_id( 'shop' ), 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
	foreach ( array( 'alimento', 'accesorios', 'higiene', 'juguetes' ) as $slug ) {
		$cat = get_term_by( 'slug', $slug, 'product_cat' );
		wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-object' => 'product_cat', 'menu-item-object-id' => $cat->term_id, 'menu-item-type' => 'taxonomy', 'menu-item-status' => 'publish' ) );
	}
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-object' => 'page', 'menu-item-object-id' => wc_get_page_id( 'myaccount' ), 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
} else {
	$menu_id = $menu->term_id;
}
set_theme_mod( 'nav_menu_locations', array_merge( (array) get_theme_mod( 'nav_menu_locations' ), array( 'primary' => $menu_id, 'mobile' => $menu_id ) ) );

// Marca en los correos de WooCommerce (#10).
update_option( 'woocommerce_email_from_name', 'MascotaFeliz' );
update_option( 'woocommerce_email_header_image', wp_get_attachment_url( get_theme_mod( 'custom_logo' ) ) );
update_option( 'woocommerce_email_base_color', '#C2410C' );
update_option( 'woocommerce_email_background_color', '#FFF7ED' );
update_option( 'woocommerce_email_body_background_color', '#FFFFFF' );
update_option( 'woocommerce_email_text_color', '#292524' );
update_option( 'woocommerce_email_footer_text', 'MascotaFeliz · Cuenca, Ecuador · WhatsApp +' . $whatsapp );

// Joinchat (#7). El horario de atención es de la versión de pago; se deja el número y el mensaje.
update_option(
	'joinchat',
	array_merge(
		(array) get_option( 'joinchat', array() ),
		array(
			'telephone'     => $whatsapp,
			'message_text'  => '¡Hola! 🐾 ¿En qué podemos ayudarte?',
			'message_send'  => 'Hola, quiero información sobre {TITLE}',
			'button_tip'    => '¿Te ayudamos?',
			'message_start' => 'Abrir chat',
			'message_delay' => -1, // Sin apertura automática: en móvil tapaba el checkout.
		)
	)
);

// FluentSMTP hacia Mailpit (#5). Solo para local: el SMTP real se configura a mano en el sitio final.
$remitente = get_option( 'woocommerce_email_from_address' );
update_option(
	'fluentmail-settings',
	array(
		'connections' => array(
			'mailpit' => array(
				'title'             => 'Mailpit (local)',
				'provider_settings' => array(
					'sender_name'      => 'MascotaFeliz',
					'sender_email'     => $remitente,
					'force_from_name'  => 'no',
					'force_from_email' => 'yes',
					'return_path'      => 'yes',
					'host'             => 'mailpit',
					'port'             => '1025',
					'auth'             => 'no',
					'username'         => '',
					'password'         => '',
					'auto_tls'         => 'no',
					'encryption'       => 'none',
					'key_store'        => 'db',
					'provider'         => 'smtp',
				),
			),
		),
		'mappings'    => array( $remitente => 'mailpit' ),
		'misc'        => array( 'log_emails' => 'yes', 'default_connection' => 'mailpit', 'fallback_connection' => '', 'log_saved_interval_days' => '14', 'disable_fluentcrm_logs' => 'no' ),
	)
);

set_theme_mod( 'product_archive_mobile_columns', 'twocolumn' ); // En móvil, una columna obligaba a hacer mucho scroll.
set_theme_mod( 'footer_html_content', '© {year} MascotaFeliz · Cuenca, Ecuador' );

WP_CLI::success( 'Tienda configurada.' );
