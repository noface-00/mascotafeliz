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
			'post_content' => '', // Se rellena al final con scripts/inicio.html, cuando ya hay fotos.
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
		if ( ! $cat ) {
			continue;
		}
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

// Fotos de producto (#34): cada archivo de scripts/fotos se llama como el slug del producto. Créditos en fotos/CREDITOS.md.
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
foreach ( glob( '/scripts/fotos/*.jpg' ) as $foto ) {
	$producto = get_page_by_path( basename( $foto, '.jpg' ), OBJECT, 'product' );
	if ( ! $producto || has_post_thumbnail( $producto ) ) {
		continue;
	}
	$tmp = wp_tempnam( $foto ); // media_handle_sideload mueve el archivo; /scripts es de solo lectura.
	copy( $foto, $tmp );
	$imagen = media_handle_sideload( array( 'name' => basename( $foto ), 'tmp_name' => $tmp ), $producto->ID, $producto->post_title );
	if ( ! is_wp_error( $imagen ) ) {
		set_post_thumbnail( $producto, $imagen );
	}
}
// Cada categoría usa la foto de uno de sus productos.
$portadas = array( 'alimento' => 'alimento-balanceado-adulto', 'accesorios' => 'collar-ajustable', 'higiene' => 'shampoo-neutro', 'juguetes' => 'cuerda-para-morder' );
foreach ( $portadas as $categoria => $slug ) {
	$cat      = get_term_by( 'slug', $categoria, 'product_cat' );
	$producto = get_page_by_path( $slug, OBJECT, 'product' );
	if ( $cat && $producto ) {
		update_term_meta( $cat->term_id, 'thumbnail_id', get_post_thumbnail_id( $producto ) );
	}
}

set_theme_mod( 'product_archive_mobile_columns', 'twocolumn' ); // En móvil, una columna obligaba a hacer mucho scroll.
set_theme_mod( 'footer_html_content', '© {year} MascotaFeliz · Cuenca, Ecuador' );

// Diseño del inicio, la cabecera y el pie (maqueta «Inicio (desktop)» con la paleta de la marca).
$enlaces = array(
	'{{tienda}}'   => get_permalink( wc_get_page_id( 'shop' ) ),
	'{{whatsapp}}' => 'https://wa.me/' . $whatsapp,
);
foreach ( array( 'alimento', 'accesorios', 'higiene', 'juguetes' ) as $slug ) {
	$enlaces[ "{{{$slug}}}" ] = get_term_link( $slug, 'product_cat' );
}
foreach ( array( 'foto_hero' => 'cama-acolchada', 'foto_perro' => 'pelota-de-goma', 'foto_snack' => 'snacks-dentales' ) as $clave => $slug ) {
	$enlaces[ "{{{$clave}}}" ] = get_the_post_thumbnail_url( get_page_by_path( $slug, OBJECT, 'product' ), 'large' );
}
$enlaces = array_filter( $enlaces, 'is_string' ); // Una categoría o foto que falte devuelve WP_Error/false y tumbaba strtr.
wp_update_post( array( 'ID' => $inicio_id, 'post_content' => strtr( file_get_contents( '/scripts/inicio.html' ), $enlaces ) ) );
update_post_meta( $inicio_id, '_kad_post_layout', 'fullwidth' );
update_post_meta( $inicio_id, '_kad_post_content_style', 'unboxed' );
update_post_meta( $inicio_id, '_kad_post_vertical_padding', 'hide' );
wp_update_custom_css_post( file_get_contents( '/scripts/estilos.css' ) );

$cabecera = get_theme_mod( 'header_desktop_items', Kadence\kadence()->default( 'header_desktop_items' ) );
$cabecera['top']['top_center']   = array( 'html' );
$cabecera['main']['main_left']   = array( 'logo' );
$cabecera['main']['main_center'] = array( 'navigation' );
$cabecera['main']['main_right']  = array( 'search', 'cart' );
set_theme_mod( 'header_desktop_items', $cabecera );
$movil = get_theme_mod( 'header_mobile_items', Kadence\kadence()->default( 'header_mobile_items' ) );
$movil['top']['top_center']  = array( 'mobile-html' );
$movil['main']['main_right'] = array( 'mobile-cart', 'popup-toggle' );
set_theme_mod( 'header_mobile_items', $movil );
set_theme_mod( 'header_top_height', array( 'size' => array( 'mobile' => 36, 'tablet' => 36, 'desktop' => 40 ), 'unit' => array( 'mobile' => 'px', 'tablet' => 'px', 'desktop' => 'px' ) ) );
set_theme_mod( 'header_top_background', array( 'desktop' => array( 'color' => 'palette3' ) ) );
set_theme_mod( 'header_main_bottom_border', array( 'desktop' => array( 'width' => 1, 'unit' => 'px', 'style' => 'solid', 'color' => '#F1E3D3' ) ) );
$wa = '<a href="https://wa.me/' . $whatsapp . '">WhatsApp</a>';
set_theme_mod( 'header_html_content', 'Envío a domicilio en Cuenca y todo Ecuador<span class="mf-sep" aria-hidden="true">·</span>Retiro gratis en tienda<span class="mf-sep" aria-hidden="true">·</span>Atención por ' . $wa );
set_theme_mod( 'mobile_html_content', 'Envío desde $2 en Cuenca · ' . $wa );

// Pie: cuatro columnas de bloques HTML. Ids altos para no pisar los widgets que traiga el tema.
$pagina = function ( $titulo ) {
	$p = get_posts( array( 'post_type' => 'page', 'title' => $titulo, 'post_status' => 'publish' ) );
	return $p ? get_permalink( $p[0] ) : '';
};
$tienda   = '';
foreach ( array( 'alimento' => 'Alimento', 'accesorios' => 'Accesorios', 'higiene' => 'Higiene', 'juguetes' => 'Juguetes' ) as $slug => $nombre ) {
	$url = get_term_link( $slug, 'product_cat' );
	if ( ! is_wp_error( $url ) ) {
		$tienda .= '<li><a href="' . esc_url( $url ) . '">' . $nombre . '</a></li>';
	}
}
$columnas = array(
	'footer1' => '<p class="mf-pie__marca">MascotaFeliz</p><p>Todo para el bienestar de tu perro o gato, con atención cercana y envío a casa.</p><p>Av. Solano y 12 de Abril, Cuenca</p>',
	'footer2' => '<h2 class="mf-pie__titulo">Tienda</h2><ul>' . $tienda . '</ul>',
	'footer3' => '<h2 class="mf-pie__titulo">Ayuda</h2><ul><li><a href="' . esc_url( get_permalink( wc_get_page_id( 'myaccount' ) ) ) . '">Mi cuenta</a></li><li><a href="' . esc_url( $pagina( 'Política de devoluciones' ) ) . '">Cambios y devoluciones</a></li><li><a href="' . esc_url( $pagina( 'Política de privacidad' ) ) . '">Privacidad</a></li><li>' . $wa . '</li></ul>',
	'footer4' => '<h2 class="mf-pie__titulo">Envíos y pagos</h2><ul><li>Cuenca $2 · Azuay $4 · Ecuador $8</li><li>Retiro en tienda gratis</li><li>Transferencia bancaria</li><li>Pago contra entrega</li></ul>',
);
$bloques  = (array) get_option( 'widget_block', array() );
$sidebars = (array) get_option( 'sidebars_widgets', array() );
$id       = 900;
foreach ( $columnas as $zona => $html ) {
	$bloques[ $id ]    = array( 'content' => "<!-- wp:html -->\n$html\n<!-- /wp:html -->" );
	$sidebars[ $zona ] = array( "block-$id" );
	$id++;
}
$bloques['_multiwidget'] = 1;
update_option( 'widget_block', $bloques );
update_option( 'sidebars_widgets', $sidebars );
$pie = get_theme_mod( 'footer_items', Kadence\kadence()->default( 'footer_items' ) );
$pie['middle'] = array( 'middle_1' => array( 'footer-widget1' ), 'middle_2' => array( 'footer-widget2' ), 'middle_3' => array( 'footer-widget3' ), 'middle_4' => array( 'footer-widget4' ), 'middle_5' => array() );
set_theme_mod( 'footer_items', $pie );
set_theme_mod( 'footer_middle_columns', '4' );
set_theme_mod( 'footer_middle_layout', array( 'mobile' => 'row', 'tablet' => 'equal', 'desktop' => 'left-forty' ) );
set_theme_mod( 'footer_wrap_background', array( 'desktop' => array( 'color' => 'palette3' ) ) );

// Resto de módulos con el mismo lenguaje: fondo blanco, título sobre crema alineado a la izquierda, sin cajas con sombra.
set_theme_mod( 'site_background', array( 'desktop' => array( 'color' => 'palette9' ) ) );
foreach ( array( 'page', 'product_archive' ) as $tipo ) {
	set_theme_mod( "{$tipo}_content_style", 'unboxed' );
	set_theme_mod( "{$tipo}_title_background", array( 'desktop' => array( 'color' => 'palette8' ) ) );
	set_theme_mod( "{$tipo}_title_align", array( 'mobile' => 'left', 'tablet' => 'left', 'desktop' => 'left' ) );
	set_theme_mod( "{$tipo}_title_height", array( 'size' => array( 'mobile' => 120, 'tablet' => 160, 'desktop' => 180 ), 'unit' => array( 'mobile' => 'px', 'tablet' => 'px', 'desktop' => 'px' ) ) );
}
set_theme_mod( 'product_archive_style', 'action-visible' ); // En el celular no hay hover: el botón debe verse siempre.
set_theme_mod( 'product_archive_button_style', 'button' );
set_theme_mod( 'product_archive_button_align', true );
set_theme_mod( 'buttons_border_radius', array( 'size' => array( 'mobile' => 999, 'tablet' => 999, 'desktop' => 999 ), 'unit' => array( 'mobile' => 'px', 'tablet' => 'px', 'desktop' => 'px' ) ) );

WP_CLI::success( 'Tienda configurada.' );
