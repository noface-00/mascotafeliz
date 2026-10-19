#!/bin/sh
# Compila el informe a PDF: sh docs/compilar.sh  → evidencias/informe.pdf
# Requiere md2html (md4c) y Chromium. Las capturas se leen de evidencias/ (fuera del repo).
set -e
cd "$(dirname "$0")"
salida=../evidencias/informe.pdf
html=$(mktemp --suffix=.html)
trap 'rm -f "$html"' EXIT

{
	cat <<EOF
<!doctype html><html lang="es"><head><meta charset="utf-8"><base href="file://$PWD/">
<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@600&family=Nunito:wght@400;700&display=swap" rel="stylesheet">
<style>
@page { size: A4; margin: 18mm 16mm; }
body { font: 11pt/1.5 Nunito, sans-serif; color: #292524; }
h1, h2, h3 { font-family: Fredoka, sans-serif; color: #1C1917; }
h1 { color: #C2410C; break-before: page; } h1:first-of-type { break-before: auto; }
table { border-collapse: collapse; width: 100%; margin: 8px 0; font-size: 10pt; }
th, td { border: 1px solid #FFEDD5; padding: 4px 8px; text-align: left; vertical-align: top; }
th { background: #FFEDD5; }
img { display: block; max-width: 100%; max-height: 200mm; margin: 8px auto; border: 1px solid #e7e5e4; }
pre { background: #FFF7ED; padding: 8px; font-size: 9pt; white-space: pre-wrap; }
a { color: #9A3412; }
</style></head><body>
EOF
	for f in informe.md tienda.md correo.md reto-whatsapp.md manual-pedido.md conclusiones.md; do md2html --github "$f"; done
	echo '</body></html>'
} > "$html"

chromium --headless --disable-gpu --allow-file-access-from-files --no-pdf-header-footer --virtual-time-budget=10000 --print-to-pdf="$salida" "file://$html" 2>/dev/null
echo "PDF: evidencias/informe.pdf"
