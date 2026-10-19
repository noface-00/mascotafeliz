# MascotaFeliz

Tienda WooCommerce de alimento y accesorios para mascotas, con recordatorio de recompra y WhatsApp.

> Marca, logo, paleta y número de WhatsApp de prueba: **pendiente de acordar en Fase 0** (issue de Fase 0).

## Levantar el entorno local

Requiere Docker.

```bash
cp .env.example .env      # cambia las contraseñas
docker compose up -d
```

- WordPress: http://localhost:8080 (la primera vez muestra el instalador)
- Mailpit (bandeja de correo de pruebas): http://localhost:8025 — en FluentSMTP usar host `mailpit`, puerto `1025`, sin autenticación. El SMTP real (Brevo/Gmail) solo en el sitio final.
- El plugin `plugin-recompra/` ya aparece en *Plugins* (se monta desde el repo; requiere WooCommerce activo).

Para compartir el sitio base: A exporta con All-in-One WP Migration (`.wpress`) y lo deja en la carpeta compartida; los demás lo importan. Los `.wpress`, `.sql` y `uploads/` no van al repo.

## Estructura

| Carpeta | Contenido | Responsable |
|---|---|---|
| `plugin-recompra/` | Plugin de recompra y botones de WhatsApp | C |
| `plantillas-correo/` | Plantillas de WooCommerce y correo de ofertas (HTML) | B |
| `docs/` | Informe, manual de pedido, documentación de cada parte | B (compila), todos |

## Roles

| Rol | Responsable de |
|---|---|
| **A** – Líder, tienda y diseño | WooCommerce, catálogo, pagos, envíos, tema, vista móvil, exportación del sitio |
| **B** – Correo y documentación | SMTP, plantillas, correo de ofertas, informe PDF y manual |
| **C** – Reto técnico y WhatsApp | Plugin de recompra, botones de WhatsApp, API de WhatsApp Business |

Las tareas están en [Issues](../../issues), agrupadas por [milestone](../../milestones) (una por fase) y con la etiqueta de cada rol. Prioridad si hay atraso: WooCommerce, SMTP y WhatsApp (etiqueta `prioridad`).

## Reglas

- **Credenciales nunca en el repo**: van en `.env` / `wp-config.php` (ignorados). Ocultarlas también en las capturas.
- Evidencias (capturas) en la carpeta compartida, una subcarpeta por integrante; no en el repo.
- Ramas por rol (`a/…`, `b/…`, `c/…`) y Pull Request a `main`. Cerrar el issue desde el PR (`Closes #n`).
