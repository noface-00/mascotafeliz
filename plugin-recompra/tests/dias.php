<?php
// Self-check del cálculo de días. Ejecutar: wp eval-file wp-content/plugins/mascotafeliz-recompra/tests/dias.php
foreach ( array( array( 2, 250, 8 ), array( 15, 400, 37 ), array( 7, 60, 116 ), array( 0.5, 100, 5 ) ) as list( $kg, $g, $esperado ) ) {
	if ( mf_dias( $kg, $g ) !== $esperado ) {
		throw new Exception( "mf_dias($kg, $g) = " . mf_dias( $kg, $g ) . ", esperado $esperado" );
	}
}
echo "OK: mf_dias\n";
