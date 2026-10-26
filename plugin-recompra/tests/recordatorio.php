<?php
// Self-check de la programación del recordatorio. Ejecutar: wp eval-file wp-content/plugins/mascotafeliz-recompra/tests/recordatorio.php
// Crea un pedido de prueba (no envía correos: pre_wp_mail los corta) y lo borra al terminar.
use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Package;

function mf_check( $ok, $mensaje ) {
	if ( ! $ok ) {
		throw new Exception( $mensaje );
	}
}

$avisos = 0;
add_filter(
	'pre_wp_mail',
	function ( $r, $atts ) use ( &$avisos ) {
		$avisos += (int) str_contains( $atts['subject'], 'Se está acabando' );
		return true; // Ningún correo sale de verdad.
	},
	10,
	2
);

$variacion = function ( $especie ) {
	$padre = wc_get_product( get_page_by_path( 'alimento-balanceado-adulto', OBJECT, 'product' ) );
	foreach ( $padre->get_children() as $id ) {
		$v = wc_get_product( $id );
		if ( $especie === $v->get_attribute( 'pa_especie' ) && 2.0 === (float) $v->get_weight() ) {
			return $v;
		}
	}
	throw new Exception( "No hay variación de 2 kg para $especie" );
};

$order = wc_create_order();
try {
	$gato  = $order->add_product( $variacion( 'Gato' ) );
	$perro = $order->add_product( $variacion( 'Perro' ) );
	$order->set_billing_email( 'prueba@example.com' );
	Package::container()->get( CheckoutFields::class )->persist_field_for_order( 'mascotafeliz/tamano', 'grande', $order, 'other' );
	$order->save();
	$order->update_status( 'processing' );

	$args = array();
	foreach ( as_get_scheduled_actions( array( 'hook' => 'mascotafeliz_recordatorio', 'group' => 'mascotafeliz', 'status' => 'pending', 'per_page' => -1 ) ) as $accion ) {
		$a = $accion->get_args();
		if ( (int) $a[0] === $order->get_id() ) {
			$args[ $a[1] ] = $a;
		}
	}
	mf_check( 2 === count( $args ), 'Se esperaban 2 recordatorios, hay ' . count( $args ) );
	// Gato grande: 2000 g / 80 g = 25 días, avisa 5 antes. Perro grande: 2000 / 400 = 5 días, avisa al día 1 (quedan 4).
	mf_check( array( 25, 5 ) === array_slice( $args[ $gato ], 2 ), 'Gato 2 kg grande: ' . wp_json_encode( $args[ $gato ] ) );
	mf_check( array( 5, 4 ) === array_slice( $args[ $perro ], 2 ), 'Perro 2 kg grande: ' . wp_json_encode( $args[ $perro ] ) );

	do_action_ref_array( 'mascotafeliz_recordatorio', $args[ $gato ] );
	mf_check( 1 === $avisos, 'Pedido pagado: debía salir 1 aviso, salieron ' . $avisos );

	$order->update_status( 'cancelled' );
	do_action_ref_array( 'mascotafeliz_recordatorio', $args[ $gato ] );
	mf_check( 1 === $avisos, 'Pedido cancelado: no debía salir aviso' );
} finally {
	foreach ( $args ?? array() as $a ) {
		as_unschedule_all_actions( 'mascotafeliz_recordatorio', $a, 'mascotafeliz' );
	}
	$order->delete( true );
}
echo "OK: recordatorio\n";
