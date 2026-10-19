# Conclusiones

- La tienda cumple el flujo completo: catálogo, compra desde el celular, pago, envío por zona, correos con la marca y recordatorio de recompra automático.
- El recordatorio se apoya en piezas que WooCommerce ya trae (peso de la variación, Action Scheduler y el envío de correo), así que el plugin es corto y fácil de mantener.
- Para enviar el recordatorio por WhatsApp hace falta la API de WhatsApp Business, con plantillas aprobadas y un costo por mensaje. Para este alcance basta el correo con un botón a WhatsApp.
- La revisión en el celular encontró cinco errores que en el computador no se veían; el más grave dejaba el checkout en 15 segundos. Revisar en el dispositivo real es imprescindible.
- Tener la configuración en un script (`scripts/configurar.sh`) permitió que los tres integrantes trabajaran sobre la misma tienda sin pasarse capturas de ajustes.

**Trabajo futuro:** fotos propias de los productos, pasarela de pago real (PayPhone o Kushki en Ecuador), baja de ofertas con un enlace en vez de responder al correo y recordatorio por WhatsApp con la API si el volumen lo justifica.
