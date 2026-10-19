<?php
/**
 * Confirmación de pedido al cliente (override de WooCommerce emails/customer-processing-order.php).
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>Hola <?php echo esc_html( $order->get_billing_first_name() ); ?>,</p>
<p>¡Gracias por comprar en MascotaFeliz! Recibimos tu pedido #<?php echo esc_html( $order->get_order_number() ); ?> y ya lo estamos preparando.</p>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
?>

<?php if ( function_exists( 'mf_wa_link' ) ) : ?>
<p>¿Dudas sobre tu pedido? <a href="<?php echo esc_url( mf_wa_link( 'Hola, tengo una consulta sobre mi pedido #' . $order->get_order_number() ) ); ?>">Escríbenos por WhatsApp</a>.</p>
<?php endif; ?>

<?php
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
