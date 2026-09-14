/*
 * Configuración de la web de producto de DENTAL-PRO.
 *
 * Este archivo se carga antes de app.js y es el ÚNICO que hay que editar para
 * apuntar la web a tu propia infraestructura. No requiere recompilar nada:
 * cambia los valores, vuelve a publicar la carpeta `sitio/` y listo.
 *
 *  - CONTROL_URL : URL pública del panel CONTROL (ventas y licencias). De ahí se
 *                  leen el catálogo y los precios (GET /api/v1/publico/catalogo),
 *                  se registran los pedidos (POST /api/v1/publico/pedidos) y se
 *                  consulta su estado. El portal de clientes vive en CONTROL_URL/portal.
 *                  Debe servirse por HTTPS y permitir el origen de esta web en
 *                  la variable ORIGENES del backend de CONTROL (CORS).
 *  - DEMO_URL    : instalación de demostración de DENTAL-PRO (con datos de ejemplo).
 *  - RELEASES_URL: página de releases de GitHub desde la que se descargan el
 *                  instalador de Windows y la app Android.
 *  - GITHUB_REPO : "propietario/repositorio", para consultar la API de releases
 *                  y mostrar versión y tamaño de cada archivo.
 *  - WHATSAPP    : número en formato internacional sin "+" ni espacios (wa.me).
 *  - CORREO      : correo de ventas y soporte.
 *  - AGENCIA     : nombre comercial que aparece en el pie y en los textos.
 *  - SITIO_URL   : URL pública de esta web (Open Graph, sitemap).
 *  - PRECIOS_RESPALDO: tabla estática que se muestra si CONTROL no responde.
 *                  Debe coincidir con la lista de precios del catálogo.
 */
window.DENTAL_PRO_CONFIG = {
  CONTROL_URL: 'https://control.tuagencia.com',
  DEMO_URL: 'https://demo.dental-pro.app',
  RELEASES_URL: 'https://github.com/cajacuenta767-sketch/DENTAL-PRO/releases',
  GITHUB_REPO: 'cajacuenta767-sketch/DENTAL-PRO',
  WHATSAPP: '59170000000',
  CORREO: 'ventas@tuagencia.com',
  AGENCIA: 'Tu Agencia',
  SITIO_URL: 'https://cajacuenta767-sketch.github.io/DENTAL-PRO/',

  /* Cuentas de la instalación de demostración (las crea el seeder). */
  CUENTAS_DEMO: [
    { rol: 'Super administrador', correo: 'admin@admin.com', clave: 'admin123' },
    { rol: 'Administrador', correo: 'admin@clinica.com', clave: 'admin123' },
    { rol: 'Recepción', correo: 'secretaria@clinica.com', clave: 'recepcion123' },
    { rol: 'Doctor', correo: 'sofia.arancibia@clinica.com', clave: 'doctor123' },
  ],

  /* Precios de lista en USD (nivel 4 · Profesional regulado). */
  PRECIOS_RESPALDO: {
    moneda: 'USD',
    planes: [
      { codigo: 'mensual', nombre: 'Mensual', tipo: 'mensual', precio: 39, duracion_dias: 30, max_activaciones: 1 },
      { codigo: 'anual', nombre: 'Anual', tipo: 'anual', precio: 349, duracion_dias: 365, max_activaciones: 1 },
      { codigo: 'vitalicio', nombre: 'Vitalicio', tipo: 'vitalicio', precio: 790, duracion_dias: null, max_activaciones: 1 },
    ],
    sede_adicional: 319,
    mantenimiento_anual: 158,
  },
};
