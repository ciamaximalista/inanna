# Generación de PDF

- `generar_pdf.php` ejecuta `wkhtmltopdf --orientation Landscape --page-size A4 --enable-local-file-access` para convertir el HTML en PDF conservando los estilos existentes.
- Si `wkhtmltopdf` no está disponible o falla, se devuelve un error HTTP 500; no hay generación alternativa.

### Instalación rápida (Debian/Ubuntu)
```sh
sudo apt-get update
sudo apt-get install wkhtmltopdf
```

Para otras plataformas, instala el binario oficial desde <https://wkhtmltopdf.org/downloads.html>.
