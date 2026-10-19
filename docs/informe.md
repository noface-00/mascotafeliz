# MascotaFeliz

**Tienda en línea de alimento y accesorios para mascotas con recordatorio de recompra y WhatsApp**

| Rol | Integrante | Responsable de |
|---|---|---|
| A – Líder, tienda y diseño | Kevin Mora | WooCommerce, catálogo, pagos, envíos, tema, vista móvil, exportación |
| B – Correo y documentación | *[nombre]* | SMTP, plantillas, correo de ofertas, informe y manual |
| C – Reto técnico y WhatsApp | *[nombre]* | Plugin de recompra, botones de WhatsApp, API de WhatsApp Business |

## 1. Introducción

MascotaFeliz es una tienda de Cuenca (Ecuador) que vende alimento, accesorios, productos de higiene y juguetes para perros y gatos. Además de vender en línea, resuelve un problema concreto: **el alimento se acaba sin aviso**. La tienda calcula cuánto dura cada bolsa según el tamaño de la mascota y escribe al cliente unos días antes, con un botón para volver a comprar o pedir por WhatsApp.

**Objetivos**

1. Tienda WooCommerce con catálogo, pagos y envíos locales, usable desde el celular.
2. Correos transaccionales con la marca y correo de ofertas con consentimiento.
3. Reto técnico: recordatorio automático de recompra.
4. Atención por WhatsApp desde la tienda y los correos.

## 2. Arquitectura

```
Cliente (navegador / celular)
   │
   ▼
WordPress + WooCommerce + tema Kadence ──── Joinchat / botones wa.me ──► WhatsApp
   │            │
   │            └── Plugin MascotaFeliz Recompra ── Action Scheduler (cola de tareas)
   │                                                     │
   ▼                                                     ▼
MariaDB                                FluentSMTP ──► Mailpit (local) / Brevo (final)
```

Todo corre en Docker Compose con cuatro servicios: WordPress, MariaDB, Mailpit y un disparador de WP-Cron. La configuración se aplica con WP-CLI. El repositorio guarda el código (plugin, plantillas y script de configuración); la base de datos y las imágenes viajan en la exportación `.wpress`.

## 3. Desarrollo

Cada integrante documenta su parte en las secciones siguientes:

- **Tienda** (A): catálogo, pagos, envíos, diseño y revisión móvil.
- **Correo** (B): proveedor SMTP, plantillas y ofertas.
- **Reto y WhatsApp** (C): cálculo de recompra, botones y API de WhatsApp Business.
- **Manual de pedido**: guía paso a paso para el cliente.

## 4. Pruebas

| Prueba | Resultado |
|---|---|
| Compra completa desde el celular (390 px) | Pedido creado, sin desplazamiento horizontal ni errores de consola |
| Zona de envío por código postal | 010101 → $2 (Cuenca); 010501 → $4 (Azuay); Pichincha → $8 |
| Confirmación al cliente | Recibida, con enlace a WhatsApp |
| Copia al administrador | Recibida |
| Recordatorio de recompra (modo prueba) | 2 kg, perro mediano → 8 días → recibido a los 3 min, disparado solo por la cola |
| Ofertas | Solo a quienes marcaron el consentimiento |
| Cálculo de días | Autocomprobación `tests/dias.php` correcta |
| Tiempo del checkout | 0,4 s (antes 15 s, ver la revisión móvil) |
