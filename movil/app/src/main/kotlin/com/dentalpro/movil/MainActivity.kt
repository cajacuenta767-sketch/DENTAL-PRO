package com.dentalpro.movil

import android.Manifest
import android.annotation.SuppressLint
import android.app.DownloadManager
import android.content.ActivityNotFoundException
import android.content.Context
import android.content.Intent
import android.content.pm.ApplicationInfo
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.provider.MediaStore
import android.view.Menu
import android.view.MenuItem
import android.view.View
import android.webkit.CookieManager
import android.webkit.MimeTypeMap
import android.webkit.URLUtil
import android.webkit.ValueCallback
import android.webkit.WebChromeClient
import android.webkit.WebResourceError
import android.webkit.WebResourceRequest
import android.webkit.WebSettings
import android.webkit.WebView
import android.webkit.WebViewClient
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.activity.OnBackPressedCallback
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.core.content.FileProvider
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import com.google.android.material.appbar.MaterialToolbar
import com.google.android.material.button.MaterialButton
import java.io.File
import java.text.SimpleDateFormat
import java.util.Date
import java.util.Locale

/**
 * Pantalla principal: un WebView a pantalla completa que muestra el sistema
 * DENTAL-PRO alojado en el servidor configurado, con subida de archivos y
 * cámara, descargas, tirar para refrescar, navegación con el botón atrás y
 * una página de error cuando no hay conexión.
 */
class MainActivity : AppCompatActivity() {

    private lateinit var toolbar: MaterialToolbar
    private lateinit var swipe: SwipeRefreshLayout
    private lateinit var webView: WebView
    private lateinit var progreso: ProgressBar
    private lateinit var vistaError: View
    private lateinit var textoError: TextView

    private var servidor: String? = null
    private var errorEnCarga = false

    // Selector de archivos / cámara para <input type="file">.
    private var callbackArchivos: ValueCallback<Array<Uri>>? = null
    private var parametrosSelector: WebChromeClient.FileChooserParams? = null
    private var fotoPendiente: Uri? = null

    // Descarga a la espera del permiso de almacenamiento (Android 8 y 9).
    private var descargaPendiente: Descarga? = null

    private data class Descarga(
        val url: String,
        val userAgent: String?,
        val contentDisposition: String?,
        val mimeType: String?,
    )

    private val selectorArchivos = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { resultado ->
        val callback = callbackArchivos
        callbackArchivos = null
        val datos = resultado.data
        val uris: Array<Uri>? = if (resultado.resultCode != RESULT_OK) {
            null
        } else {
            val clip = datos?.clipData
            when {
                clip != null && clip.itemCount > 0 -> Array(clip.itemCount) { clip.getItemAt(it).uri }
                datos?.data != null -> arrayOf(datos.data!!)
                fotoPendiente != null -> arrayOf(fotoPendiente!!) // la cámara devuelve el resultado sin datos
                else -> null
            }
        }
        fotoPendiente = null
        callback?.onReceiveValue(uris)
    }

    private val permisoCamara = registerForActivityResult(ActivityResultContracts.RequestPermission()) { concedido ->
        val parametros = parametrosSelector
        parametrosSelector = null
        if (!concedido) aviso(getString(R.string.camara_denegada))
        lanzarSelector(parametros, conCamara = concedido)
    }

    private val permisoAlmacenamiento = registerForActivityResult(ActivityResultContracts.RequestPermission()) { concedido ->
        val descarga = descargaPendiente
        descargaPendiente = null
        if (descarga == null) return@registerForActivityResult
        if (concedido) encolarDescarga(descarga) else aviso(getString(R.string.descarga_sin_permiso))
    }

    private val ajustes = registerForActivityResult(ActivityResultContracts.StartActivityForResult()) { resultado ->
        val guardado = Servidor.obtener(this)
        when {
            resultado.resultCode == RESULT_OK && guardado != null -> {
                val cambio = guardado != servidor
                servidor = guardado
                actualizarSubtitulo()
                if (cambio || webView.url.isNullOrEmpty()) {
                    webView.clearHistory()
                    cargarInicio()
                }
            }
            guardado == null -> finish() // primera vez y el usuario canceló
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        // Sustituye el tema de splash por el tema normal antes de dibujar.
        setTheme(R.style.Theme_DentalPro)
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        toolbar = findViewById(R.id.toolbar)
        swipe = findViewById(R.id.swipe)
        webView = findViewById(R.id.webview)
        progreso = findViewById(R.id.progreso)
        vistaError = findViewById(R.id.vista_error)
        textoError = findViewById(R.id.texto_error)

        setSupportActionBar(toolbar)
        toolbar.setOnLongClickListener { abrirAjustes(); true }

        findViewById<MaterialButton>(R.id.btn_reintentar).setOnClickListener { reintentar() }
        findViewById<MaterialButton>(R.id.btn_cambiar_servidor).setOnClickListener { abrirAjustes() }

        configurarWebView()
        configurarSwipe()
        configurarBotonAtras()

        servidor = Servidor.obtener(this)
        actualizarSubtitulo()

        if (servidor == null) {
            abrirAjustes(primeraVez = true)
        } else if (savedInstanceState == null || webView.restoreState(savedInstanceState) == null) {
            cargarInicio()
        }
    }

    override fun onSaveInstanceState(outState: Bundle) {
        super.onSaveInstanceState(outState)
        webView.saveState(outState)
    }

    override fun onResume() {
        super.onResume()
        webView.onResume()
    }

    override fun onPause() {
        webView.onPause()
        super.onPause()
    }

    override fun onDestroy() {
        callbackArchivos?.onReceiveValue(null)
        callbackArchivos = null
        webView.destroy()
        super.onDestroy()
    }

    // ---------------------------------------------------------------- Menú

    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        menuInflater.inflate(R.menu.menu_principal, menu)
        return true
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean = when (item.itemId) {
        R.id.accion_recargar -> { reintentar(); true }
        R.id.accion_inicio -> { cargarInicio(); true }
        R.id.accion_navegador -> { webView.url?.let { abrirExterno(it) }; true }
        R.id.accion_ajustes -> { abrirAjustes(); true }
        else -> super.onOptionsItemSelected(item)
    }

    private fun abrirAjustes(primeraVez: Boolean = false) {
        ajustes.launch(Intent(this, AjustesActivity::class.java).putExtra(AjustesActivity.EXTRA_PRIMERA_VEZ, primeraVez))
    }

    private fun actualizarSubtitulo() {
        supportActionBar?.subtitle = servidor?.let { Uri.parse(it).host }
    }

    // ------------------------------------------------------------ WebView

    @SuppressLint("SetJavaScriptEnabled")
    private fun configurarWebView() {
        val esDepuracion = (applicationInfo.flags and ApplicationInfo.FLAG_DEBUGGABLE) != 0
        WebView.setWebContentsDebuggingEnabled(esDepuracion)

        with(webView.settings) {
            javaScriptEnabled = true
            domStorageEnabled = true
            allowFileAccess = false
            allowContentAccess = true
            setSupportZoom(false)
            builtInZoomControls = false
            displayZoomControls = false
            useWideViewPort = true
            loadWithOverviewMode = true
            javaScriptCanOpenWindowsAutomatically = false
            setSupportMultipleWindows(false)
            mediaPlaybackRequiresUserGesture = false
            cacheMode = WebSettings.LOAD_DEFAULT
            mixedContentMode = WebSettings.MIXED_CONTENT_NEVER_ALLOW
            userAgentString = "$userAgentString DentalProAndroid/2.0"
        }

        CookieManager.getInstance().apply {
            setAcceptCookie(true)
            setAcceptThirdPartyCookies(webView, false)
        }

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(view: WebView, request: WebResourceRequest): Boolean {
                val url = request.url
                return when (url.scheme?.lowercase()) {
                    "http", "https" -> {
                        if (Servidor.mismoServidor(url, servidor)) {
                            false
                        } else {
                            abrirExterno(url.toString())
                            true
                        }
                    }
                    // tel:, mailto:, sms:, whatsapp:, intent:, geo:…
                    else -> {
                        abrirExterno(url.toString())
                        true
                    }
                }
            }

            override fun onPageStarted(view: WebView, url: String?, favicon: android.graphics.Bitmap?) {
                errorEnCarga = false
                progreso.visibility = View.VISIBLE
            }

            override fun onPageFinished(view: WebView, url: String?) {
                swipe.isRefreshing = false
                progreso.visibility = View.GONE
                if (!errorEnCarga) ocultarError()
            }

            override fun onReceivedError(view: WebView, request: WebResourceRequest, error: WebResourceError) {
                if (!request.isForMainFrame) return
                errorEnCarga = true
                mostrarError(error.description?.toString())
            }
        }

        webView.webChromeClient = object : WebChromeClient() {
            override fun onProgressChanged(view: WebView, newProgress: Int) {
                progreso.progress = newProgress
                progreso.visibility = if (newProgress >= 100) View.GONE else View.VISIBLE
            }

            override fun onShowFileChooser(
                webView: WebView,
                filePathCallback: ValueCallback<Array<Uri>>,
                fileChooserParams: FileChooserParams,
            ): Boolean {
                callbackArchivos?.onReceiveValue(null)
                callbackArchivos = filePathCallback

                if (aceptaImagenes(fileChooserParams) && hayCamara()) {
                    if (ContextCompat.checkSelfPermission(this@MainActivity, Manifest.permission.CAMERA) == PackageManager.PERMISSION_GRANTED) {
                        lanzarSelector(fileChooserParams, conCamara = true)
                    } else {
                        parametrosSelector = fileChooserParams
                        permisoCamara.launch(Manifest.permission.CAMERA)
                    }
                } else {
                    lanzarSelector(fileChooserParams, conCamara = false)
                }
                return true
            }
        }

        webView.setDownloadListener { url, userAgent, contentDisposition, mimeType, _ ->
            val descarga = Descarga(url, userAgent, contentDisposition, mimeType)
            val necesitaPermiso = Build.VERSION.SDK_INT <= Build.VERSION_CODES.P &&
                ContextCompat.checkSelfPermission(this, Manifest.permission.WRITE_EXTERNAL_STORAGE) != PackageManager.PERMISSION_GRANTED
            if (necesitaPermiso) {
                descargaPendiente = descarga
                permisoAlmacenamiento.launch(Manifest.permission.WRITE_EXTERNAL_STORAGE)
            } else {
                encolarDescarga(descarga)
            }
        }
    }

    private fun configurarSwipe() {
        swipe.setColorSchemeResources(R.color.marca)
        swipe.setOnRefreshListener { reintentar() }
        // Sólo tirar para refrescar cuando la página está en la parte superior.
        swipe.setOnChildScrollUpCallback { _, _ -> webView.scrollY > 0 }
    }

    private fun configurarBotonAtras() {
        onBackPressedDispatcher.addCallback(this, object : OnBackPressedCallback(true) {
            override fun handleOnBackPressed() {
                if (webView.canGoBack()) {
                    ocultarError()
                    webView.goBack()
                } else {
                    isEnabled = false
                    onBackPressedDispatcher.onBackPressed()
                }
            }
        })
    }

    private fun cargarInicio() {
        val base = servidor ?: return
        ocultarError()
        webView.loadUrl(Servidor.urlInicio(base))
    }

    private fun reintentar() {
        ocultarError()
        val actual = webView.url
        if (actual.isNullOrEmpty() || actual == "about:blank") cargarInicio() else webView.reload()
    }

    private fun mostrarError(detalle: String?) {
        swipe.isRefreshing = false
        progreso.visibility = View.GONE
        val host = servidor?.let { Uri.parse(it).host } ?: ""
        textoError.text = if (detalle.isNullOrBlank()) {
            getString(R.string.error_conexion_texto, host)
        } else {
            getString(R.string.error_conexion_texto, host) + "\n\n" + detalle
        }
        vistaError.visibility = View.VISIBLE
        webView.visibility = View.INVISIBLE
    }

    private fun ocultarError() {
        if (vistaError.visibility != View.VISIBLE) return
        vistaError.visibility = View.GONE
        webView.visibility = View.VISIBLE
    }

    // ------------------------------------------------- Enlaces externos

    private fun abrirExterno(url: String) {
        try {
            val intent = if (url.startsWith("intent:")) {
                Intent.parseUri(url, Intent.URI_INTENT_SCHEME)
            } else {
                Intent(Intent.ACTION_VIEW, Uri.parse(url))
            }
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            startActivity(intent)
        } catch (e: ActivityNotFoundException) {
            aviso(getString(R.string.sin_app_para_enlace))
        } catch (e: Exception) {
            aviso(getString(R.string.sin_app_para_enlace))
        }
    }

    // ------------------------------------------ Subida de archivos / cámara

    private fun hayCamara(): Boolean = packageManager.hasSystemFeature(PackageManager.FEATURE_CAMERA_ANY)

    private fun aceptaImagenes(parametros: WebChromeClient.FileChooserParams): Boolean {
        val tipos = parametros.acceptTypes.orEmpty().filter { it.isNotBlank() }
        if (tipos.isEmpty()) return true
        return tipos.any { it.startsWith("image/") || it == "*/*" || it.equals(".jpg", true) || it.equals(".jpeg", true) || it.equals(".png", true) }
    }

    private fun mime(tipo: String): String {
        if (!tipo.startsWith(".")) return tipo
        return MimeTypeMap.getSingleton().getMimeTypeFromExtension(tipo.substring(1).lowercase()) ?: "*/*"
    }

    private fun lanzarSelector(parametros: WebChromeClient.FileChooserParams?, conCamara: Boolean) {
        val tipos = parametros?.acceptTypes.orEmpty().filter { it.isNotBlank() }.map { mime(it) }.distinct()

        val contenido = Intent(Intent.ACTION_GET_CONTENT).apply {
            addCategory(Intent.CATEGORY_OPENABLE)
            type = if (tipos.size == 1) tipos[0] else "*/*"
            if (tipos.size > 1) putExtra(Intent.EXTRA_MIME_TYPES, tipos.toTypedArray())
            if (parametros?.mode == WebChromeClient.FileChooserParams.MODE_OPEN_MULTIPLE) {
                putExtra(Intent.EXTRA_ALLOW_MULTIPLE, true)
            }
        }

        val extras = ArrayList<Intent>()
        if (conCamara) crearIntentCamara()?.let { extras.add(it) }

        val selector = Intent(Intent.ACTION_CHOOSER).apply {
            putExtra(Intent.EXTRA_INTENT, contenido)
            putExtra(Intent.EXTRA_TITLE, getString(R.string.selector_titulo))
            if (extras.isNotEmpty()) putExtra(Intent.EXTRA_INITIAL_INTENTS, extras.toTypedArray())
        }

        try {
            selectorArchivos.launch(selector)
        } catch (e: ActivityNotFoundException) {
            fotoPendiente = null
            callbackArchivos?.onReceiveValue(null)
            callbackArchivos = null
            aviso(getString(R.string.sin_app_para_archivos))
        }
    }

    private fun crearIntentCamara(): Intent? {
        return try {
            val carpeta = File(cacheDir, "fotos").apply { mkdirs() }
            val nombre = "foto_" + SimpleDateFormat("yyyyMMdd_HHmmss", Locale.US).format(Date()) + ".jpg"
            val uri = FileProvider.getUriForFile(this, "$packageName.fileprovider", File(carpeta, nombre))
            fotoPendiente = uri
            Intent(MediaStore.ACTION_IMAGE_CAPTURE).apply {
                putExtra(MediaStore.EXTRA_OUTPUT, uri)
                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION or Intent.FLAG_GRANT_WRITE_URI_PERMISSION)
            }
        } catch (e: Exception) {
            fotoPendiente = null
            null
        }
    }

    // ------------------------------------------------------- Descargas

    @Suppress("DEPRECATION")
    private fun encolarDescarga(descarga: Descarga) {
        if (!descarga.url.startsWith("http", ignoreCase = true)) {
            // blob: y data: no los gestiona DownloadManager; se delega al sistema.
            abrirExterno(descarga.url)
            return
        }

        val nombre = URLUtil.guessFileName(descarga.url, descarga.contentDisposition, descarga.mimeType)
        try {
            val peticion = DownloadManager.Request(Uri.parse(descarga.url)).apply {
                descarga.mimeType?.let { setMimeType(it) }
                CookieManager.getInstance().getCookie(descarga.url)?.let { addRequestHeader("Cookie", it) }
                descarga.userAgent?.let { addRequestHeader("User-Agent", it) }
                setTitle(nombre)
                setDescription(getString(R.string.descarga_descripcion))
                setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED)
                setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, nombre)
            }
            (getSystemService(Context.DOWNLOAD_SERVICE) as DownloadManager).enqueue(peticion)
            aviso(getString(R.string.descarga_iniciada, nombre))
        } catch (e: Exception) {
            aviso(getString(R.string.descarga_error))
        }
    }

    private fun aviso(texto: String) {
        Toast.makeText(this, texto, Toast.LENGTH_LONG).show()
    }
}
