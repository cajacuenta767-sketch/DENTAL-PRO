# DENTAL-PRO en el móvil: app Android y PWA

DENTAL-PRO se usa desde el teléfono de dos maneras complementarias:

| | App Android (`DENTAL-PRO.apk`) | PWA (instalación desde el navegador) |
|---|---|---|
| Plataformas | Android 8.0 (API 26) o superior | Android, iOS/iPadOS, Windows, macOS, Linux |
| Requiere HTTPS | No: acepta `http://` para servidores de la red local | Sí (salvo `localhost`) |
| Cámara para radiografías/fotos | Sí, integrada en el selector de archivos | Sí, mediante el navegador |
| Descargas (recibos PDF, respaldos) | Notificación del sistema, carpeta *Descargas* | Descarga normal del navegador |
| Servidor configurable | Pantalla de ajustes dentro de la app | La dirección que abriste al instalar |

Ambas cargan el mismo sistema web; no hay funciones exclusivas de una u otra.

## 1. App Android

### Instalación de la APK

1. Descarga `DENTAL-PRO-2.0.0.apk` desde la release de GitHub (o desde el
   artefacto del workflow *App Android*).
2. Ábrela en el teléfono. Android pedirá permitir la instalación de apps de
   origen desconocido para el navegador o gestor de archivos que la abrió:
   acéptalo sólo para esa instalación.
3. Si Play Protect avisa de "app desconocida" (normal en apps que no vienen de
   Play Store), elige *Instalar de todos modos*.

Para actualizar, instala la APK nueva encima: se conservan la dirección del
servidor y la sesión. **Nota:** una APK firmada con un keystore distinto al de
la instalación previa no se puede instalar encima; hay que desinstalar antes
(ver *Firma* más abajo).

### Primer arranque: apuntar al servidor

La primera vez la app pide la **dirección del servidor**. Es la misma que se
usa en el navegador:

- En la nube: `https://clinica.midominio.com`
- En la red local de la clínica (instalador de escritorio): la dirección que
  muestra el instalador, por ejemplo `http://192.168.1.10:8181`. El teléfono
  debe estar en la misma red Wi-Fi que el servidor.

Reglas al escribirla:

- Si no se indica esquema, la app usa `http://` para IPs, `localhost` y
  dominios `*.local`, y `https://` para el resto. Puedes escribirlo tú.
- Se ignoran las barras finales; se admite una subruta (`https://dominio/clinica`).
- *Probar conexión* consulta `/up` (la comprobación de salud de Laravel) y
  muestra el resultado antes de guardar.

La dirección queda guardada en las preferencias de la app
(`SharedPreferences`). Para cambiarla: menú de tres puntos → **Servidor…**, o
mantener pulsada la barra superior con el logo. La pantalla de error de
conexión también tiene el botón *Cambiar servidor*.

### Permisos

| Permiso | Cuándo se pide | Para qué |
|---|---|---|
| Internet | Siempre (no requiere confirmación) | Cargar el sistema |
| Cámara | La primera vez que se pulsa un campo de archivo que admite imágenes | Tomar la foto o radiografía directamente. Si se deniega, el selector sigue funcionando con archivos existentes |
| Almacenamiento (sólo Android 8 y 9) | Al descargar un archivo | Guardar en la carpeta *Descargas* |

No se pide ubicación, contactos ni notificaciones (las notificaciones de
descarga las emite el sistema).

### Qué hace la app

- WebView con JavaScript, almacenamiento DOM y cookies; zoom deshabilitado;
  `User-Agent` con el sufijo ` DentalProAndroid/2.0` (útil para detectar la
  app desde el servidor o en la analítica).
- Subida de archivos (`<input type="file">`) con selector del sistema, varios
  archivos a la vez y cámara (`FileProvider`, foto temporal en la caché de la
  app).
- Descargas con `DownloadManager`: mismas cookies de sesión, notificación y
  archivo en *Descargas*.
- Tirar hacia abajo para recargar; barra de progreso de carga.
- Botón *atrás* navega por el historial; en la primera página cierra la app.
- Página *Sin conexión con el servidor* con *Reintentar* y *Cambiar servidor*.
- `usesCleartextTraffic="true"`: permite servidores `http://` de red local.
  El contenido mixto (recursos `http` dentro de una página `https`) sigue bloqueado.
- Enlaces externos: `wa.me` / `whatsapp:`, `tel:`, `mailto:`, `sms:`, `intent:`
  y cualquier dominio distinto del servidor se abren con la app correspondiente
  (WhatsApp, teléfono, correo, navegador). Por eso el inicio de sesión con Google
  y las pasarelas de pago se abren en el navegador del teléfono, no dentro de la app.
- Icono adaptativo y splash con el color de marca `#0d9488`, generados desde
  `public/favicon.svg`.

### Compilar la APK

En GitHub Actions (`.github/workflows/movil.yml`) se compila automáticamente
en cada push que toque `movil/`, manualmente (*Run workflow*) y al crear un tag
`v*`, que además adjunta la APK a la release. El artefacto se llama
`DENTAL-PRO-<versión>.apk` e incluye su `.sha256`.

En local hace falta Android SDK (platform 34 y build-tools 34.0.0) y JDK 17:

```bash
cd movil
./gradlew assembleRelease        # app/build/outputs/apk/release/
./gradlew assembleDebug          # firma de depuración, applicationId .debug
./gradlew lint                   # informe en app/build/reports/
```

El proyecto usa Gradle 8.7 (wrapper incluido), AGP 8.5.2, Kotlin 1.9.24,
`minSdk 26`, `targetSdk 34`, `versionName 2.0.0`, `versionCode 20000`.
Al publicar una versión nueva, sube `versionCode` y `versionName` en
`movil/app/build.gradle.kts` y `VERSION_APP` en el workflow.

### Firma y publicación en Play Store

La APK release se firma así, por orden de prioridad:

1. **Keystore propio** (recomendado y obligatorio para tiendas): en
   *Settings → Secrets and variables → Actions* del repositorio define
   `ANDROID_KEYSTORE_BASE64` (el `.jks`/`.p12` en base64:
   `base64 -w0 dentalpro.keystore`), `ANDROID_KEYSTORE_PASSWORD`,
   `ANDROID_KEY_ALIAS` y `ANDROID_KEY_PASSWORD`. En local, crea
   `movil/keystore.properties` (ignorado por git):

   ```properties
   KEYSTORE_FILE=/ruta/segura/dentalpro.keystore
   KEYSTORE_PASSWORD=...
   KEY_ALIAS=dentalpro
   KEY_PASSWORD=...
   ```

   o exporta las variables `ANDROID_KEYSTORE_FILE`, `ANDROID_KEYSTORE_PASSWORD`,
   `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`.
2. **Keystore temporal**: si no existen los secretos, el workflow genera uno
   con `keytool` para cada ejecución. La APK resultante se instala sin problema,
   pero **cada compilación tiene una firma distinta**: no se puede actualizar
   una instalación anterior sin desinstalar y no sirve para Google Play.
3. **Clave de depuración**: compilando en local sin keystore, la release se firma
   con la clave `debug` del SDK (sólo para pruebas).

Para crear el keystore definitivo (guárdalo fuera del repositorio y haz copia
de seguridad: si se pierde no se podrá actualizar la app en Play):

```bash
keytool -genkeypair -v -keystore dentalpro.keystore -storetype PKCS12 \
  -alias dentalpro -keyalg RSA -keysize 2048 -validity 10000
```

Para Google Play se recomienda además generar un *App Bundle*
(`./gradlew bundleRelease`, salida en `app/build/outputs/bundle/release/`) y
activar *Play App Signing*. El icono de 512 px para la ficha está en
`movil/ic_launcher-playstore.png`.

### Problemas frecuentes

- **"Sin conexión con el servidor" en la red local**: comprueba que el
  teléfono esté en la misma Wi-Fi, que el puerto sea el del instalador y que el
  cortafuegos del servidor permita conexiones entrantes.
- **Error de certificado en HTTPS**: el certificado debe ser válido para el
  dominio (Let's Encrypt, por ejemplo). Los certificados autofirmados no se
  aceptan; usa `http://` en la red local o un certificado válido.
- **La cámara no aparece en el selector**: se denegó el permiso. Actívalo en
  *Ajustes de Android → Aplicaciones → DENTAL-PRO → Permisos*.
- **No se puede instalar la actualización**: la APK nueva está firmada con
  otro keystore (ver *Firma*). Desinstala la anterior e instala la nueva.

## 2. PWA (instalar desde el navegador)

La versión web es una *Progressive Web App*: se puede añadir a la pantalla de
inicio y se abre a pantalla completa, con icono y color propios. Requiere
HTTPS (o `localhost`).

- **Android (Chrome, Edge, Samsung Internet)**: abre la dirección del sistema,
  inicia sesión y elige *Instalar aplicación* en el aviso o en el menú ⋮ →
  *Añadir a la pantalla de inicio*.
- **iPhone / iPad (Safari)**: botón *Compartir* → *Añadir a pantalla de inicio*.
- **Escritorio (Chrome, Edge)**: icono de instalación en la barra de
  direcciones → *Instalar*.

Componentes:

- `public/manifest.webmanifest`: nombre *DENTAL-PRO*, `start_url` `/admin/home`,
  `display: standalone`, `theme_color #0d9488`, iconos 192/512 normales y
  *maskable* en `public/iconos/`, accesos directos a Agenda, Pacientes y Portal.
- `public/sw.js`: service worker. Precachea `offline.html` y los iconos; las
  navegaciones van siempre a la red y, si falla, muestra `offline.html`; los
  recursos de `/build/*` e iconos se sirven desde caché; nunca se cachean
  respuestas de `/admin/*`, `/api/*`, `/portal/*`, `/archivos/*` ni peticiones
  que no sean `GET`. La caché lleva versión (`dentalpro-v2.0.0`) y las
  anteriores se borran al activarse una nueva: al cambiar el SW, sube la
  constante `VERSION`.
- `public/offline.html`: página sin conexión con botón *Reintentar*; se recarga
  sola cuando vuelve la red.
- El registro del SW está al final de `resources/js/app.js` y las etiquetas
  (`manifest`, `apple-touch-icon`, `theme-color`, `apple-mobile-web-app-capable`)
  en los layouts `admin`, `portal` y `auth`.
- `bootstrap/app.php` registra rutas de respaldo para `/manifest.webmanifest`,
  `/sw.js` y `/offline.html` con el *content-type* correcto; en producción el
  servidor web sirve los archivos de `public/` directamente sin pasar por ellas.

Prueba automatizada: `php artisan test --filter=PwaTest`.

### Regenerar los iconos

Los PNG de `public/iconos/` y de `movil/app/src/main/res/mipmap-*` se dibujan
con PHP GD a partir de la muela del favicon. Si cambias el logo, sustituye los
PNG (192, 512, *maskable* con margen del 20 % y `apple-touch-icon` 180 px; para
Android, `ic_launcher` 48 dp, `ic_launcher_round` y `ic_launcher_foreground`
108 dp con la muela dentro de la zona segura central de 66 dp) manteniendo los
mismos nombres.
