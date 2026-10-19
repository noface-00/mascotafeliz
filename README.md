# MascotaFeliz

Tienda WooCommerce de alimento y accesorios para mascotas, con recordatorio de recompra y WhatsApp.

> Marca, logo, paleta y tipografía: [`docs/marca/`](docs/marca/). El número de WhatsApp de prueba no se publica en el repo (es público): se pasa con `MF_WHATSAPP` al configurar.

## Levantar el entorno local

Requiere Docker.

```bash
cp .env.example .env      # cambia las contraseñas
docker compose up -d
```

- WordPress: http://localhost:8080 (la primera vez muestra el instalador)
- Mailpit (bandeja de correo de pruebas): http://localhost:8025 — en FluentSMTP usar host `mailpit`, puerto `1025`, sin autenticación. El SMTP real (Brevo/Gmail) solo en el sitio final.
- El plugin `plugin-recompra/` ya aparece en *Plugins* (se monta desde el repo; requiere WooCommerce activo).
- Si cambias de rama y el plugin o las plantillas dejan de funcionar: `docker compose restart wordpress` (git recrea las carpetas y el contenedor sigue viendo las antiguas).
- `sh scripts/configurar.sh` deja la tienda configurada con WP-CLI: idioma, pagos, zonas de envío, retiro en tienda, inicio, menú, marca de los correos, Joinchat y FluentSMTP→Mailpit. Se puede repetir sin duplicar nada. Número de WhatsApp: `MF_WHATSAPP=5939XXXXXXXX sh scripts/configurar.sh`.
- El recordatorio de recompra queda en **modo prueba** (minutos en vez de días). En el sitio final: `wp option update mascotafeliz_modo_prueba no`.
- Correo de ofertas (solo a quienes marcaron el consentimiento en el checkout): `wp mascotafeliz ofertas`.

Para compartir el sitio: A exporta con All-in-One WP Migration (`.wpress`) y lo pasa al equipo por fuera del repo (pesa ~150 MB y lleva la base de datos); los demás lo importan. Los `.wpress`, `.sql` y `uploads/` no van al repo.

## Estructura

| Carpeta | Contenido | Responsable |
|---|---|---|
| `plugin-recompra/` | Plugin de recompra y botones de WhatsApp | C |
| `plantillas-correo/` | Overrides de plantillas de WooCommerce (`emails/…`) y correo de ofertas (HTML); se monta en `wp-content/plantillas-correo` | B |
| `scripts/` | Configuración de la tienda con WP-CLI | A |
| `docs/` | Informe, manual de pedido, documentación de cada parte | B (compila), todos |
| `evidencias/` | Capturas, una subcarpeta por integrante | Todos |

## Evidencias

| Integrante | Capturas | Dónde se explican |
|---|---|---|
| A | [`evidencias/A/`](evidencias/A/) (escritorio) y [`evidencias/A/movil/`](evidencias/A/movil/) (390 px) | [Tienda](docs/tienda.md), [manual de pedido](docs/manual-pedido.md) |
| B | [`evidencias/B/`](evidencias/B/) | [Correo](docs/correo.md) |
| C | [`evidencias/C/`](evidencias/C/) | [Reto y WhatsApp](docs/reto-whatsapp.md) |

## Roles

| Rol | Responsable de |
|---|---|
| **A** – Líder, tienda y diseño | WooCommerce, catálogo, pagos, envíos, tema, vista móvil, exportación del sitio |
| **B** – Correo y documentación | SMTP, plantillas, correo de ofertas, informe PDF y manual |
| **C** – Reto técnico y WhatsApp | Plugin de recompra, botones de WhatsApp, API de WhatsApp Business |

Las tareas están en [Issues](../../issues), agrupadas por [milestone](../../milestones) (una por fase) y con la etiqueta de cada rol. Prioridad si hay atraso: WooCommerce, SMTP y WhatsApp (etiqueta `prioridad`).

## Reglas

- **Credenciales nunca en el repo**: van en `.env` / `wp-config.php` (ignorados). Ocultarlas también en las capturas.
- Evidencias (capturas) en `evidencias/<rol>/`. El repo es público: tapar correos personales, teléfonos y credenciales antes de subirlas.
- Ramas por rol (`a/…`, `b/…`, `c/…`) y Pull Request a `main`. Cerrar el issue desde el PR (`Closes #n`).
