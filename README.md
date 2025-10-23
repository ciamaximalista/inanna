![Inanna banner](inanna-banner.png)

# Inanna

Inanna es un entorno web para construir presentaciones panorámicas a partir de contenido en Markdown. Permite definir la estética de cada proyecto, gestionar un banco de recursos multimedia, ajustar imágenes directamente en el navegador y exportar un PDF listo para proyectar o imprimir.

## Funcionalidades principales
- Autenticación integrada: el primer usuario registrado actúa como administrador único y las sesiones se gestionan vía `data/users.json`.
- Edición en Markdown: separa diapositivas con `---`, obtén previsualizaciones HTML al vuelo mediante Parsedown y reutiliza el contenido con guardados en XML.
- Composición visual: distribuye las diapositivas con plantillas prediseñadas, asigna recursos desde una galería y controla el orden mediante miniaturas.
- Estética personalizable: define tipografías, paletas de color y cajas destacadas; opcionalmente sincroniza fuentes de Google Fonts desde la pestaña Configuración.
- Banco de recursos: sube archivos, edita imágenes (recorte, brillo, contraste) antes de almacenarlas y elimina elementos obsoletos con un clic.
- Archivo y exportación: guarda presentaciones (`data/archivo/*.xml`), recupéralas cuando lo necesites y genera PDFs de alta fidelidad con `wkhtmltopdf`.

## Requisitos previos
- PHP 8.1 o superior con extensiones `json`, `mbstring`, `gd`, `dom` y `simplexml` habilitadas.
- Composer para instalar las dependencias (`erusev/parsedown`, `dompdf/dompdf`).
- Un servidor web (Apache, Nginx, Caddy) o el servidor embebido de PHP (`php -S`).
- `wkhtmltopdf` 0.12.x o superior disponible en la línea de comandos.
- Acceso de escritura para el usuario del servidor sobre `data/` y `recursos/`.

## Instalación paso a paso
1. Clona el repositorio y entra al directorio del proyecto:
   ```sh
   git clone <url-del-repositorio> inanna
   cd inanna
   ```
2. Instala las dependencias de PHP:
   ```sh
   composer install
   ```
3. Asegura la existencia (y permisos de escritura) de los directorios que guardan los datos de la aplicación:
   ```sh
   mkdir -p data/archivo data/fonts recursos
   chmod -R 775 data recursos
   ```
4. Si recibes una copia preconfigurada, borra o renombra `data/users.json` para poder registrar al primer usuario, y revisa `data/config.json` antes de poner la aplicación en producción.
5. Instala `wkhtmltopdf`. En Debian/Ubuntu:
   ```sh
   sudo apt-get update
   sudo apt-get install wkhtmltopdf
   ```
   Para otras plataformas consulta <https://wkhtmltopdf.org/downloads.html>.

## Puesta en marcha
- Arranca el servidor embebido (útil en desarrollo):
  ```sh
  php -S 0.0.0.0:8000 -t .
  ```
- Abre `http://localhost:8000/inanna.php` en tu navegador.
- Si no existen usuarios, el sistema te pedirá crear el administrador inicial. A partir de entonces, usa la pantalla de inicio de sesión.

## Flujo de trabajo recomendado
- **Texto**: redacta el contenido en Markdown y divide las diapositivas con `---`.
- **Estética**: ajusta tipografías y colores; si introduces una clave de Google Fonts, pulsa “Actualizar lista de fuentes” para sincronizar el catálogo (`data/google_fonts.json`).
- **Recursos**: sube imágenes o vídeos y, en el caso de imágenes, edítalas con recorte y ajustes básicos antes de guardarlas en `recursos/`.
- **Composición**: selecciona plantillas, asigna recursos a cada diapositiva, reorganiza el orden y visualiza el resultado en tiempo real.
- **Configuración**: guarda la clave de la API de Google Fonts y otras opciones persistentes en `data/config.json`.
- **Archivo**: guarda presentaciones en XML, vuelve a cargarlas para modificarlas y bórralas cuando ya no las necesites.

## Generación de PDF
- La exportación usa `wkhtmltopdf` con soporte de fuentes incrustadas; si la herramienta no está disponible, se devuelve un error HTTP 500.
- Durante la exportación se cachean las fuentes descargadas en `data/fonts/` y se embeben en el PDF para mantener la maquetación.
- Asegúrate de que `allow_url_fopen` esté habilitado en PHP si planeas descargar fuentes de Google.

## Estructura de datos
- `data/styles.json`: estilo activo (tipografías y colores).
- `data/config.json`: clave de la API de Google Fonts y ajustes generales.
- `data/google_fonts.json`: caché del catálogo importado desde la API.
- `data/archivo/`: presentaciones guardadas en formato XML.
- `data/fonts/`: fuentes descargadas para incrustar en PDFs.
- `recursos/`: biblioteca de archivos multimedia asociados a las presentaciones.

## Mantenimiento y buenas prácticas
- Realiza copias de seguridad periódicas de `data/` y `recursos/` antes de actualizar o reinstalar.
- Limpia las fuentes descargadas en `data/fonts/` si reduces el conjunto de tipografías para mantener el repositorio ligero.
- Tras actualizar dependencias vía Composer, prueba la exportación a PDF para validar que `wkhtmltopdf` sigue funcionando correctamente.
