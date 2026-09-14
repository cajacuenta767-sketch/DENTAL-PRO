/* DENTAL-PRO · web de producto
   Lógica de la página: catálogo y precios desde CONTROL, pedidos, consulta de
   pedido, descargas desde GitHub Releases, galería y navegación. Sin dependencias. */
(function () {
  'use strict';

  var CFG = window.DENTAL_PRO_CONFIG || {};
  var CONTROL = String(CFG.CONTROL_URL || '').replace(/\/$/, '');
  var API = CONTROL + '/api/v1/publico';
  var PRODUCTO = 'dental-pro';
  var TIEMPO_ESPERA = 9000;

  var $ = function (sel, raiz) { return (raiz || document).querySelector(sel); };
  var $$ = function (sel, raiz) { return Array.prototype.slice.call((raiz || document).querySelectorAll(sel)); };

  /* ---------- Estado compartido ---------- */
  var estado = {
    catalogo: null,          // respuesta de /publico/catalogo o null si se usa el respaldo
    planes: [],              // planes del producto (con id si vienen de CONTROL)
    monedaBase: 'USD',
    tiposCambio: { USD: 1 },
    moneda: 'USD',
    pasarelas: {},
    extras: { sede_adicional: 0, mantenimiento_anual: 0 },
    ref: null,
    respaldo: false,
  };

  /* ---------- Utilidades ---------- */
  function fetchConTiempo(url, opciones, ms) {
    var ctrl = typeof AbortController === 'function' ? new AbortController() : null;
    var temporizador = ctrl ? setTimeout(function () { ctrl.abort(); }, ms || TIEMPO_ESPERA) : null;
    var opts = Object.assign({}, opciones || {});
    if (ctrl) opts.signal = ctrl.signal;
    return fetch(url, opts).finally(function () { if (temporizador) clearTimeout(temporizador); });
  }

  function formatearMoneda(valor, moneda) {
    var decimales = ['CLP', 'COP', 'ARS', 'PYG'].indexOf(moneda) >= 0 ? 0 : 2;
    try {
      return new Intl.NumberFormat('es', { style: 'currency', currency: moneda, minimumFractionDigits: decimales, maximumFractionDigits: decimales }).format(valor);
    } catch (e) {
      return valor.toFixed(decimales) + ' ' + moneda;
    }
  }

  function convertir(valorBase) {
    var tasa = Number(estado.tiposCambio[estado.moneda]) || 1;
    return valorBase * tasa;
  }

  function precio(valorBase) {
    return formatearMoneda(convertir(valorBase), estado.moneda);
  }

  function mostrarMensaje(el, tipo, html) {
    if (!el) return;
    el.hidden = false;
    el.setAttribute('data-tipo', tipo);
    el.innerHTML = html;
  }

  function ocultarMensaje(el) {
    if (!el) return;
    el.hidden = true;
    el.innerHTML = '';
  }

  function escapar(texto) {
    return String(texto == null ? '' : texto).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function textoErrorHttp(respuesta, cuerpo) {
    if (cuerpo && typeof cuerpo === 'object') {
      if (cuerpo.error) return cuerpo.error;
      if (cuerpo.mensaje) return cuerpo.mensaje;
      if (cuerpo.message) return cuerpo.message;
      if (Array.isArray(cuerpo.errores)) return cuerpo.errores.map(function (e) { return e.mensaje || e.message || JSON.stringify(e); }).join(' ');
    }
    switch (respuesta.status) {
      case 400: case 422: return 'Revisa los datos del formulario: el servidor rechazó la solicitud.';
      case 401: case 403: return 'El servidor no autorizó la solicitud.';
      case 404: return 'No se encontró el recurso solicitado.';
      case 429: return 'Demasiadas solicitudes en poco tiempo. Espera un momento y vuelve a intentarlo.';
      default: return 'El servidor respondió con un error (' + respuesta.status + ').';
    }
  }

  function leerJson(respuesta) {
    return respuesta.text().then(function (t) {
      try { return t ? JSON.parse(t) : null; } catch (e) { return null; }
    });
  }

  /* ---------- Enlaces y textos de configuración ---------- */
  function aplicarConfiguracion() {
    var enlaces = {
      demo: CFG.DEMO_URL || '#',
      portal: CONTROL ? CONTROL + '/portal' : '#',
      releases: CFG.RELEASES_URL || '#',
      whatsapp: CFG.WHATSAPP ? 'https://wa.me/' + String(CFG.WHATSAPP).replace(/\D/g, '') + '?text=' + encodeURIComponent('Hola, me interesa DENTAL-PRO para mi clínica.') : '#contacto',
      correo: CFG.CORREO ? 'mailto:' + CFG.CORREO + '?subject=' + encodeURIComponent('Consulta sobre DENTAL-PRO') : '#contacto',
    };
    $$('[data-enlace]').forEach(function (a) {
      var clave = a.getAttribute('data-enlace');
      var href = enlaces[clave];
      if (!href) return;
      a.setAttribute('href', href);
      if (/^https?:/.test(href)) { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener'); }
    });
    if (CFG.WHATSAPP) $$('[data-texto="whatsapp"]').forEach(function (s) { s.textContent = 'WhatsApp +' + String(CFG.WHATSAPP).replace(/\D/g, ''); });
    if (CFG.CORREO) $$('[data-texto="correo"]').forEach(function (s) { s.textContent = CFG.CORREO; });
    if (CFG.AGENCIA) $$('[data-texto="agencia"]').forEach(function (s) { s.textContent = CFG.AGENCIA; });
    $$('[data-texto="anio"]').forEach(function (s) { s.textContent = String(new Date().getFullYear()); });

    var lat = (CFG.RELEASES_URL || '').replace(/\/$/, '') + '/latest/download/';
    var enlacesDescarga = { windows: lat + 'DENTAL-PRO-Setup.exe', android: lat + 'DENTAL-PRO.apk' };
    $$('[data-descarga-enlace]').forEach(function (a) {
      var href = enlacesDescarga[a.getAttribute('data-descarga-enlace')];
      if (href) a.setAttribute('href', href);
    });
    // Los botones del hero saltan a la sección de descargas; el enlace directo queda en las tarjetas.

    var tabla = $('[data-cuentas-demo] tbody');
    if (tabla && Array.isArray(CFG.CUENTAS_DEMO)) {
      tabla.innerHTML = CFG.CUENTAS_DEMO.map(function (c) {
        return '<tr><td>' + escapar(c.rol) + '</td><td><code>' + escapar(c.correo) + '</code></td><td><code>' + escapar(c.clave) + '</code></td></tr>';
      }).join('');
    }
  }

  /* ---------- Menú móvil ---------- */
  function iniciarMenu() {
    var boton = $('[data-menu]');
    var nav = $('#navegacion');
    if (!boton || !nav) return;
    function cerrar() { nav.setAttribute('data-abierto', 'false'); boton.setAttribute('aria-expanded', 'false'); }
    boton.addEventListener('click', function () {
      var abierto = nav.getAttribute('data-abierto') === 'true';
      nav.setAttribute('data-abierto', abierto ? 'false' : 'true');
      boton.setAttribute('aria-expanded', abierto ? 'false' : 'true');
    });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) cerrar(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') cerrar(); });
  }

  /* ---------- Galería y lightbox ---------- */
  function iniciarGaleria() {
    var dialogo = $('[data-lightbox]');
    var botones = $$('[data-ampliar]');
    if (!dialogo || !botones.length || typeof dialogo.showModal !== 'function') return;
    var imagen = $('[data-lightbox-imagen]', dialogo);
    var titulo = $('[data-lightbox-titulo]', dialogo);
    var actual = 0;
    var ultimoFoco = null;

    function mostrar(i) {
      actual = (i + botones.length) % botones.length;
      var b = botones[actual];
      var img = $('img', b);
      imagen.src = b.getAttribute('data-ampliar');
      imagen.alt = img ? img.alt : '';
      var cap = b.parentNode.querySelector('figcaption strong');
      titulo.textContent = (cap ? cap.textContent : '') + ' (' + (actual + 1) + ' de ' + botones.length + ')';
    }
    botones.forEach(function (b, i) {
      b.addEventListener('click', function () {
        ultimoFoco = b;
        mostrar(i);
        dialogo.showModal();
        $('[data-lightbox-cerrar]', dialogo).focus();
      });
    });
    $('[data-lightbox-cerrar]', dialogo).addEventListener('click', function () { dialogo.close(); });
    $('[data-lightbox-anterior]', dialogo).addEventListener('click', function () { mostrar(actual - 1); });
    $('[data-lightbox-siguiente]', dialogo).addEventListener('click', function () { mostrar(actual + 1); });
    dialogo.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') mostrar(actual - 1);
      if (e.key === 'ArrowRight') mostrar(actual + 1);
    });
    dialogo.addEventListener('click', function (e) { if (e.target === dialogo) dialogo.close(); });
    dialogo.addEventListener('close', function () { imagen.src = ''; if (ultimoFoco) ultimoFoco.focus(); });
  }

  /* ---------- Precios ---------- */
  function planesDeRespaldo() {
    var r = CFG.PRECIOS_RESPALDO || { moneda: 'USD', planes: [] };
    estado.respaldo = true;
    estado.catalogo = null;
    estado.monedaBase = r.moneda || 'USD';
    estado.tiposCambio = { USD: 1 };
    estado.moneda = estado.monedaBase;
    estado.pasarelas = {};
    estado.extras = { sede_adicional: Number(r.sede_adicional) || 0, mantenimiento_anual: Number(r.mantenimiento_anual) || 0 };
    estado.planes = (r.planes || []).map(function (p) { return Object.assign({ id: null }, p); });
  }

  function planesDeCatalogo(cat) {
    var producto = (cat.productos || []).filter(function (p) { return p.codigo === PRODUCTO; })[0];
    if (!producto || !producto.planes || !producto.planes.length) throw new Error('El catálogo no incluye el producto ' + PRODUCTO);
    estado.respaldo = false;
    estado.catalogo = cat;
    estado.monedaBase = cat.moneda_base || 'USD';
    var tc = cat.tipos_cambio && typeof cat.tipos_cambio === 'object' ? cat.tipos_cambio : {};
    estado.tiposCambio = Object.assign({}, tc);
    estado.tiposCambio[estado.monedaBase] = 1;
    estado.moneda = estado.monedaBase;
    estado.pasarelas = cat.pasarelas || {};
    var r = CFG.PRECIOS_RESPALDO || {};
    estado.extras = { sede_adicional: Number(r.sede_adicional) || 0, mantenimiento_anual: Number(r.mantenimiento_anual) || 0 };
    var orden = { mensual: 1, anual: 2, vitalicio: 3 };
    estado.planes = producto.planes.slice().sort(function (a, b) { return (orden[a.tipo] || 9) - (orden[b.tipo] || 9); });
    estado.version = producto.version_actual || null;
  }

  function monedaDelVisitante() {
    var mapa = { PE: 'PEN', BO: 'BOB', CO: 'COP', CL: 'CLP', MX: 'MXN', AR: 'ARS', PY: 'PYG', UY: 'UYU', BR: 'BRL', EC: 'USD', SV: 'USD', PA: 'USD', GT: 'GTQ', HN: 'HNL', NI: 'NIO', CR: 'CRC', DO: 'DOP', ES: 'EUR', US: 'USD' };
    var idiomas = (navigator.languages && navigator.languages.length ? navigator.languages : [navigator.language || '']).slice();
    try {
      var zona = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
      var porZona = { 'America/Lima': 'PEN', 'America/La_Paz': 'BOB', 'America/Bogota': 'COP', 'America/Santiago': 'CLP', 'America/Mexico_City': 'MXN', 'America/Argentina/Buenos_Aires': 'ARS', 'America/Buenos_Aires': 'ARS', 'America/Asuncion': 'PYG', 'America/Montevideo': 'UYU', 'America/Sao_Paulo': 'BRL', 'Europe/Madrid': 'EUR' };
      if (porZona[zona] && estado.tiposCambio[porZona[zona]]) return porZona[zona];
    } catch (e) { /* sin zona horaria */ }
    for (var i = 0; i < idiomas.length; i++) {
      var region = (idiomas[i].split('-')[1] || '').toUpperCase();
      if (mapa[region] && estado.tiposCambio[mapa[region]]) return mapa[region];
    }
    return null;
  }

  function descripcionPlan(p) {
    var base = ['Todas las funciones en 1 instalación', 'Instalación y capacitación remota'];
    if (p.tipo === 'mensual') return base.concat(['Actualizaciones y soporte mientras pague', 'Sin permanencia: 7 días de gracia si se atrasa']);
    if (p.tipo === 'anual') return base.concat(['Actualizaciones y soporte durante el año', 'Ahorra dos meses y medio frente al mensual']);
    return base.concat(['Sin vencimiento: pago único', '12 meses de actualizaciones y soporte; luego mantenimiento opcional']);
  }

  function periodoPlan(p) {
    if (p.tipo === 'mensual') return '/mes';
    if (p.tipo === 'anual') return '/año';
    return ' una vez';
  }

  function pintarPlanes() {
    var cont = $('[data-planes]');
    if (!cont) return;
    cont.innerHTML = estado.planes.map(function (p) {
      var destacado = p.tipo === 'vitalicio';
      var equivalente = '';
      if (p.tipo === 'anual') equivalente = 'Equivale a ' + precio(p.precio / 12) + ' al mes';
      if (p.tipo === 'vitalicio') equivalente = 'Se paga en menos de dos años frente al mensual';
      if (p.tipo === 'mensual') equivalente = 'Entra sin compromiso; cancela cuando quieras';
      return '<article class="plan" data-destacado="' + destacado + '">' +
        (destacado ? '<span class="destacado">Más elegido</span>' : '') +
        '<h3>' + escapar(p.nombre || p.tipo) + '</h3>' +
        '<div class="precio">' + escapar(precio(p.precio)) + '<small>' + periodoPlan(p) + '</small></div>' +
        '<div class="equivalente">' + escapar(equivalente) + '</div>' +
        '<ul>' + descripcionPlan(p).map(function (t) { return '<li>' + escapar(t) + '</li>'; }).join('') + '</ul>' +
        '<a class="boton ' + (destacado ? 'boton-primario' : 'boton-secundario') + '" href="#comprar" data-elegir-plan="' + escapar(p.codigo || p.tipo) + '">Elegir ' + escapar((p.nombre || p.tipo).toLowerCase()) + '</a>' +
        '</article>';
    }).join('');

    var extras = $('[data-extras]');
    if (extras) {
      extras.innerHTML =
        '<div class="extra"><p><strong>Sede adicional</strong><br>Segunda instalación o sucursal de la misma clínica, con licencia vitalicia.</p><strong class="importe">' + escapar(precio(estado.extras.sede_adicional)) + '</strong></div>' +
        '<div class="extra"><p><strong>Mantenimiento anual</strong><br>Opcional a partir del segundo año del plan vitalicio: actualizaciones y soporte.</p><strong class="importe">' + escapar(precio(estado.extras.mantenimiento_anual)) + '</strong></div>';
    }

    var estadoEl = $('[data-estado-precios]');
    if (estadoEl) {
      if (estado.respaldo) {
        estadoEl.setAttribute('data-tipo', 'respaldo');
        estadoEl.textContent = 'Precios de lista de referencia (no se pudo conectar con el servidor de ventas).';
      } else {
        estadoEl.setAttribute('data-tipo', 'ok');
        estadoEl.textContent = 'Precios actualizados' + (estado.catalogo.agencia ? ' por ' + estado.catalogo.agencia : '') + (estado.version ? ' · versión ' + estado.version : '') + '.';
      }
    }

    var nota = $('[data-nota-precios]');
    if (nota) {
      nota.textContent = estado.moneda === estado.monedaBase
        ? 'Precios en ' + estado.monedaBase + ', sin impuestos. Se cobra en tu moneda al tipo de cambio vigente el día del pago.'
        : 'Importes convertidos desde ' + estado.monedaBase + ' con el tipo de cambio del servidor de ventas (' + estado.tiposCambio[estado.moneda] + ' ' + estado.moneda + ' por 1 ' + estado.monedaBase + '). Sin impuestos. El importe final lo fija el tipo de cambio vigente el día del pago.';
    }

    $$('[data-elegir-plan]').forEach(function (a) {
      a.addEventListener('click', function () {
        var sel = $('[data-pedido-plan]');
        if (sel) { sel.value = a.getAttribute('data-elegir-plan'); sel.dispatchEvent(new Event('change')); }
      });
    });
    pintarSelectorPlan();
    actualizarTotal();
  }

  function pintarSelectorMoneda() {
    var cont = $('[data-selector-moneda]');
    var sel = $('[data-moneda]');
    if (!cont || !sel) return;
    var monedas = Object.keys(estado.tiposCambio).filter(function (m) { return Number(estado.tiposCambio[m]) > 0; });
    if (monedas.length < 2) { cont.hidden = true; return; }
    monedas.sort(function (a, b) { return a === estado.monedaBase ? -1 : b === estado.monedaBase ? 1 : a.localeCompare(b); });
    sel.innerHTML = monedas.map(function (m) { return '<option value="' + escapar(m) + '">' + escapar(m) + '</option>'; }).join('');
    var local = monedaDelVisitante();
    estado.moneda = local && monedas.indexOf(local) >= 0 ? local : estado.monedaBase;
    sel.value = estado.moneda;
    cont.hidden = false;
    sel.addEventListener('change', function () { estado.moneda = sel.value; pintarPlanes(); });
  }

  function cargarCatalogo() {
    var estadoEl = $('[data-estado-precios]');
    if (!CONTROL) { planesDeRespaldo(); pintarSelectorMoneda(); pintarPlanes(); pintarPasarelas(); return Promise.resolve(); }
    return fetchConTiempo(API + '/catalogo', { headers: { Accept: 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (cat) { planesDeCatalogo(cat); })
      .catch(function (e) {
        if (window.console && console.info) console.info('DENTAL-PRO: se usan los precios de respaldo (' + (e && e.message ? e.message : e) + ').');
        planesDeRespaldo();
      })
      .then(function () {
        pintarSelectorMoneda();
        pintarPlanes();
        pintarPasarelas();
        if (estadoEl && estado.respaldo) estadoEl.setAttribute('data-tipo', 'respaldo');
      });
  }

  /* ---------- Formulario de pedido ---------- */
  function pintarSelectorPlan() {
    var sel = $('[data-pedido-plan]');
    if (!sel) return;
    var anterior = sel.value;
    sel.innerHTML = estado.planes.map(function (p) {
      return '<option value="' + escapar(p.codigo || p.tipo) + '">' + escapar(p.nombre || p.tipo) + ' · ' + escapar(precio(p.precio)) + periodoPlan(p) + '</option>';
    }).join('');
    var codigos = estado.planes.map(function (p) { return p.codigo || p.tipo; });
    sel.value = codigos.indexOf(anterior) >= 0 ? anterior : (codigos.indexOf('vitalicio') >= 0 ? 'vitalicio' : codigos[0] || '');
  }

  function pintarPasarelas() {
    var sel = $('[data-pedido-pasarela]');
    var ayuda = $('[data-ayuda-pasarela]');
    if (!sel) return;
    var nombres = { stripe: 'Tarjeta (Stripe)', paypal: 'PayPal', culqi: 'Culqi: Yape, tarjetas y PagoEfectivo (Perú)', demo: 'Pasarela de prueba' };
    var disponibles = Object.keys(estado.pasarelas).filter(function (k) { return estado.pasarelas[k] && nombres[k]; });
    sel.innerHTML = '<option value="">Coordinar con el equipo de ventas (transferencia, efectivo, cuotas)</option>' +
      disponibles.map(function (k) { return '<option value="' + escapar(k) + '">' + escapar(nombres[k]) + '</option>'; }).join('');
    if (ayuda) {
      ayuda.textContent = disponibles.length
        ? 'Con pago en línea recibes la clave de licencia en cuanto se confirma el cobro.'
        : (estado.respaldo ? 'Sin conexión con el servidor de ventas: el pedido se coordinará por correo o WhatsApp.' : 'El pago en línea no está habilitado; te contactaremos para coordinar el cobro.');
    }
  }

  function planSeleccionado() {
    var sel = $('[data-pedido-plan]');
    if (!sel) return null;
    return estado.planes.filter(function (p) { return (p.codigo || p.tipo) === sel.value; })[0] || null;
  }

  function actualizarTotal() {
    var total = $('[data-total-pedido]');
    var cantidad = Math.max(1, Math.min(20, parseInt(($('#pedido-cantidad') || {}).value, 10) || 1));
    var p = planSeleccionado();
    if (!total) return;
    if (!p) { total.textContent = '—'; return; }
    total.textContent = precio(p.precio * cantidad) + periodoPlan(p) + (cantidad > 1 ? ' · ' + cantidad + ' licencias' : '');
  }

  function validarPedido(form) {
    var ok = true;
    $$('[data-error-de]', form).forEach(function (e) { e.textContent = ''; });
    $$('[aria-invalid]', form).forEach(function (e) { e.removeAttribute('aria-invalid'); });
    var nombre = $('#pedido-nombre', form);
    var email = $('#pedido-email', form);
    var cantidad = $('#pedido-cantidad', form);
    if (!nombre.value.trim() || nombre.value.trim().length < 2) {
      $('[data-error-de="nombre"]', form).textContent = 'Escribe tu nombre (mínimo 2 caracteres).';
      nombre.setAttribute('aria-invalid', 'true'); ok = false;
    }
    if (!email.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) {
      $('[data-error-de="email"]', form).textContent = 'Escribe un correo válido: ahí llegará tu clave.';
      email.setAttribute('aria-invalid', 'true'); ok = false;
    }
    var n = parseInt(cantidad.value, 10);
    if (!(n >= 1 && n <= 20)) { cantidad.setAttribute('aria-invalid', 'true'); cantidad.value = '1'; ok = false; }
    if (!ok) {
      var primero = $('[aria-invalid="true"]', form);
      if (primero) primero.focus();
    }
    return ok;
  }

  function enlaceAlternativoContacto(resumen) {
    var texto = 'Hola, quiero pedir DENTAL-PRO. ' + resumen;
    var partes = [];
    if (CFG.WHATSAPP) partes.push('<a href="https://wa.me/' + escapar(String(CFG.WHATSAPP).replace(/\D/g, '')) + '?text=' + encodeURIComponent(texto) + '" target="_blank" rel="noopener">enviar el pedido por WhatsApp</a>');
    if (CFG.CORREO) partes.push('<a href="mailto:' + escapar(CFG.CORREO) + '?subject=' + encodeURIComponent('Pedido DENTAL-PRO') + '&body=' + encodeURIComponent(texto) + '">enviarlo por correo</a>');
    return partes.length ? ' Puedes ' + partes.join(' o ') + ' y lo registramos por ti.' : '';
  }

  function iniciarPedido() {
    var form = $('[data-formulario-pedido]');
    if (!form) return;
    var mensaje = $('[data-mensaje-pedido]', form);
    var boton = $('[data-enviar-pedido]', form);
    $('[data-pedido-plan]', form).addEventListener('change', actualizarTotal);
    $('#pedido-cantidad', form).addEventListener('input', actualizarTotal);

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      ocultarMensaje(mensaje);
      if (!validarPedido(form)) {
        mostrarMensaje(mensaje, 'error', '<p>Revisa los campos marcados antes de enviar el pedido.</p>');
        return;
      }
      var p = planSeleccionado();
      var cantidad = parseInt($('#pedido-cantidad', form).value, 10) || 1;
      var datos = {
        nombre: $('#pedido-nombre', form).value.trim(),
        empresa: $('#pedido-empresa', form).value.trim(),
        email: $('#pedido-email', form).value.trim(),
        telefono: $('#pedido-telefono', form).value.trim(),
        pais: $('#pedido-pais', form).value,
        notas: $('#pedido-notas', form).value.trim(),
        pasarela: $('[data-pedido-pasarela]', form).value,
      };
      var resumen = 'Plan ' + (p ? p.nombre : '') + ', ' + cantidad + ' licencia(s). Nombre: ' + datos.nombre + (datos.empresa ? ', ' + datos.empresa : '') + '. Correo: ' + datos.email + (datos.telefono ? '. Teléfono: ' + datos.telefono : '') + '.';

      if (!p || p.id == null || !CONTROL) {
        mostrarMensaje(mensaje, 'aviso', '<p>Ahora mismo no hay conexión con el servidor de ventas, así que el pedido no se pudo registrar automáticamente.' + enlaceAlternativoContacto(resumen) + '</p>');
        return;
      }

      var cuerpo = {
        plan_id: Number(p.id),
        cantidad: cantidad,
        cliente: { nombre: datos.nombre, email: datos.email },
      };
      if (datos.empresa) cuerpo.cliente.empresa = datos.empresa;
      if (datos.telefono) cuerpo.cliente.telefono = datos.telefono;
      if (datos.pais) cuerpo.cliente.pais = datos.pais;
      if (datos.notas) cuerpo.notas = datos.notas.slice(0, 300);
      if (datos.pasarela) cuerpo.pasarela = datos.pasarela;
      if (estado.ref) cuerpo.ref = estado.ref;
      if (estado.moneda && estado.moneda !== estado.monedaBase && estado.tiposCambio[estado.moneda]) cuerpo.moneda = estado.moneda;
      if (cantidad > 1) cuerpo.etiquetas = Array.apply(null, Array(cantidad)).map(function (_, i) { return 'Sede ' + (i + 1); });

      boton.disabled = true;
      boton.setAttribute('aria-busy', 'true');
      mostrarMensaje(mensaje, 'info', '<p>Enviando el pedido…</p>');

      fetchConTiempo(API + '/pedidos', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(cuerpo),
      }, 20000)
        .then(function (r) {
          return leerJson(r).then(function (json) {
            if (!r.ok) throw Object.assign(new Error(textoErrorHttp(r, json)), { http: r.status });
            return json;
          });
        })
        .then(function (res) {
          var venta = res && res.venta ? res.venta : {};
          var enlace = res && res.enlace_pago && res.enlace_pago.url ? res.enlace_pago.url : null;
          var html = '<p><strong>Pedido registrado.</strong> Tu número de pedido es <code>' + escapar(venta.numero || '') + '</code>' +
            (venta.total != null ? ' por ' + escapar(formatearMoneda(Number(venta.total), venta.moneda || estado.monedaBase)) : '') + '. Guárdalo junto con tu correo para consultar el estado.</p>';
          if (enlace) {
            html += '<p>Se abrió la pasarela de pago en una pestaña nueva. Si no aparece, <a href="' + escapar(enlace) + '" target="_blank" rel="noopener">pulsa aquí para pagar</a>. Al confirmarse el cobro recibirás la clave de licencia por correo.</p>';
          } else {
            html += '<p>Te contactaremos en breve para coordinar el pago. También puedes escribirnos por WhatsApp indicando el número de pedido.</p>';
          }
          mostrarMensaje(mensaje, 'exito', html);
          if (enlace) window.open(enlace, '_blank', 'noopener');
          var consultaNumero = $('#consulta-numero');
          var consultaEmail = $('#consulta-email');
          if (consultaNumero && venta.numero) consultaNumero.value = venta.numero;
          if (consultaEmail) consultaEmail.value = datos.email;
          form.reset();
          pintarSelectorPlan();
          actualizarTotal();
        })
        .catch(function (err) {
          var esRed = !err.http;
          var texto = esRed
            ? 'No se pudo conectar con el servidor de ventas. Comprueba tu conexión e inténtalo de nuevo.' + enlaceAlternativoContacto(resumen)
            : 'No se pudo registrar el pedido: ' + escapar(err.message) + (err.http === 429 ? '' : ' Corrige los datos y vuelve a intentarlo.');
          mostrarMensaje(mensaje, 'error', '<p>' + texto + '</p>');
        })
        .finally(function () {
          boton.disabled = false;
          boton.removeAttribute('aria-busy');
        });
    });
  }

  /* ---------- Referido (?ref=CODIGO) ---------- */
  function iniciarReferido() {
    var ref = '';
    try { ref = new URLSearchParams(window.location.search).get('ref') || ''; } catch (e) { ref = ''; }
    ref = ref.trim().toUpperCase().slice(0, 20);
    if (!ref) return;
    estado.ref = ref;
    var caja = $('[data-vendedor]');
    if (!caja) return;
    caja.hidden = false;
    caja.textContent = 'Pedido con código de asesor ' + ref + '.';
    if (!CONTROL) return;
    fetchConTiempo(API + '/vendedor/' + encodeURIComponent(ref), { headers: { Accept: 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (v) {
        var nombre = v && (v.marca || v.nombre);
        if (nombre) caja.textContent = 'Te atiende ' + nombre + ' (código ' + ref + '). El pedido quedará asignado a este asesor.';
      })
      .catch(function () {
        caja.textContent = 'Código de asesor ' + ref + ' (no se pudo verificar en este momento; se enviará igual con el pedido).';
      });
  }

  /* ---------- Consulta de pedido ---------- */
  function iniciarConsulta() {
    var form = $('[data-formulario-consulta]');
    if (!form) return;
    var mensaje = $('[data-mensaje-consulta]', form);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var numero = $('#consulta-numero', form).value.trim();
      var email = $('#consulta-email', form).value.trim();
      if (!numero || !email) {
        mostrarMensaje(mensaje, 'error', '<p>Indica el número de pedido y el correo con el que lo hiciste.</p>');
        ($('#consulta-numero', form).value ? $('#consulta-email', form) : $('#consulta-numero', form)).focus();
        return;
      }
      if (!CONTROL) { mostrarMensaje(mensaje, 'aviso', '<p>La consulta de pedidos no está configurada en esta página.</p>'); return; }
      mostrarMensaje(mensaje, 'info', '<p>Consultando…</p>');
      fetchConTiempo(API + '/pedidos/' + encodeURIComponent(numero) + '?email=' + encodeURIComponent(email), { headers: { Accept: 'application/json' } })
        .then(function (r) {
          return leerJson(r).then(function (json) {
            if (r.status === 404) throw Object.assign(new Error('No encontramos un pedido con ese número y ese correo. Revisa ambos datos.'), { http: 404 });
            if (!r.ok) throw Object.assign(new Error(textoErrorHttp(r, json)), { http: r.status });
            return json;
          });
        })
        .then(function (p) {
          var estados = { pendiente_pago: 'Pendiente de pago', pagada: 'Pagada', anulada: 'Anulada', vencida: 'Vencida' };
          var html = '<p><strong>Pedido ' + escapar(p.numero) + '</strong> · ' + escapar(p.producto || 'DENTAL-PRO') + (p.plan ? ' · ' + escapar(p.plan) : '') + '</p>' +
            '<p>Estado: <strong>' + escapar(estados[p.estado] || p.estado) + '</strong>. Total ' + escapar(formatearMoneda(Number(p.total || 0), p.moneda || estado.monedaBase)) +
            (p.pagado != null ? ', pagado ' + escapar(formatearMoneda(Number(p.pagado || 0), p.moneda || estado.monedaBase)) : '') + '.</p>';
          if (p.estado === 'pagada' && Array.isArray(p.licencias) && p.licencias.length) {
            html += '<p>Tus claves de licencia:</p><ul class="claves">' + p.licencias.map(function (l) {
              return '<li><span>' + escapar(l.clave) + '</span><small>' + escapar(l.etiqueta || '') + (l.estado ? ' · ' + escapar(l.estado) : '') + '</small></li>';
            }).join('') + '</ul>';
          } else if (p.enlace_pago) {
            html += '<p><a class="boton boton-primario boton-pequeno" href="' + escapar(p.enlace_pago) + '" target="_blank" rel="noopener">Completar el pago</a></p>';
          } else if (p.estado !== 'pagada') {
            html += '<p>Cuando se confirme el pago, aquí aparecerán tus claves de licencia.</p>';
          }
          mostrarMensaje(mensaje, p.estado === 'pagada' ? 'exito' : 'info', html);
        })
        .catch(function (err) {
          mostrarMensaje(mensaje, 'error', '<p>' + escapar(err.http ? err.message : 'No se pudo conectar con el servidor de ventas. Inténtalo de nuevo en unos minutos.') + '</p>');
        });
    });
  }

  /* ---------- Descargas desde GitHub Releases ---------- */
  function tamanoLegible(bytes) {
    if (!(bytes > 0)) return '';
    if (bytes > 1024 * 1024 * 1024) return (bytes / (1024 * 1024 * 1024)).toFixed(2) + ' GB';
    if (bytes > 1024 * 1024) return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    return Math.round(bytes / 1024) + ' KB';
  }

  function cargarReleases() {
    var metaWin = $('[data-meta="windows"]');
    var metaAnd = $('[data-meta="android"]');
    var sinDatos = function () {
      if (metaWin) metaWin.textContent = 'Última versión publicada en la página de releases.';
      if (metaAnd) metaAnd.textContent = 'Última versión publicada en la página de releases.';
      $$('[data-descarga-enlace]').forEach(function (a) { if (CFG.RELEASES_URL) a.setAttribute('href', CFG.RELEASES_URL.replace(/\/$/, '') + '/latest'); });
    };
    if (!CFG.GITHUB_REPO) { sinDatos(); return Promise.resolve(); }
    return fetchConTiempo('https://api.github.com/repos/' + CFG.GITHUB_REPO + '/releases/latest', { headers: { Accept: 'application/vnd.github+json' } })
      .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
      .then(function (rel) {
        var assets = Array.isArray(rel.assets) ? rel.assets : [];
        var exe = assets.filter(function (a) { return /\.exe$/i.test(a.name); })[0];
        var apk = assets.filter(function (a) { return /\.apk$/i.test(a.name); })[0];
        var version = rel.tag_name || rel.name || '';
        var fecha = rel.published_at ? new Date(rel.published_at).toLocaleDateString('es', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
        var enlaceWin = $('[data-descarga-enlace="windows"]');
        var enlaceAnd = $('[data-descarga-enlace="android"]');
        if (exe && enlaceWin) {
          enlaceWin.setAttribute('href', exe.browser_download_url);
          if (metaWin) metaWin.textContent = 'Versión ' + version + ' · ' + tamanoLegible(exe.size) + (fecha ? ' · ' + fecha : '');
        } else if (metaWin) {
          metaWin.textContent = version ? 'Versión ' + version + ': el instalador de Windows aún no está adjunto; revisa la página de releases.' : 'Consulta la página de releases.';
          if (enlaceWin) enlaceWin.setAttribute('href', rel.html_url || CFG.RELEASES_URL);
        }
        if (apk && enlaceAnd) {
          enlaceAnd.setAttribute('href', apk.browser_download_url);
          if (metaAnd) metaAnd.textContent = 'Versión ' + version + ' · ' + tamanoLegible(apk.size) + (fecha ? ' · ' + fecha : '');
        } else if (metaAnd) {
          metaAnd.textContent = version ? 'Versión ' + version + ': la app Android aún no está adjunta; revisa la página de releases.' : 'Consulta la página de releases.';
          if (enlaceAnd) enlaceAnd.setAttribute('href', rel.html_url || CFG.RELEASES_URL);
        }
      })
      .catch(function () { sinDatos(); });
  }

  /* ---------- Arranque ---------- */
  function iniciar() {
    aplicarConfiguracion();
    iniciarMenu();
    iniciarGaleria();
    iniciarPedido();
    iniciarConsulta();
    iniciarReferido();
    cargarCatalogo();
    cargarReleases();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();
})();
