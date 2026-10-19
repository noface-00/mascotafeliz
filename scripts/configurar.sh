#!/bin/sh
# Configura la tienda local con WP-CLI (pagos, envíos, inicio, menú, correos, Joinchat, FluentSMTP).
# Uso: docker compose up -d && sh scripts/configurar.sh   (opcional: MF_WHATSAPP=5939XXXXXXXX)
set -e
cd "$(dirname "$0")/.."
. ./.env

wp() {
	docker run --rm -i --memory=512m --user 33:33 \
		--network "$(basename "$PWD")_default" --volumes-from "$(docker compose ps -q wordpress)" \
		-v "$PWD/scripts:/scripts:ro" \
		-e WORDPRESS_DB_HOST=db -e WORDPRESS_DB_NAME=wordpress -e WORDPRESS_DB_USER=wordpress \
		-e WORDPRESS_DB_PASSWORD="$DB_PASSWORD" -e WP_CLI_CACHE_DIR=/tmp -e MF_WHATSAPP="${MF_WHATSAPP:-}" \
		wordpress:cli wp "$@"
}

wp language core install es_ES --activate # la traducción es_EC de WooCommerce deja textos del checkout en inglés
wp language plugin install woocommerce es_ES
wp plugin install creame-whatsapp-me fluent-smtp --activate
wp plugin activate mascotafeliz-recompra
wp eval-file /scripts/configurar.php
