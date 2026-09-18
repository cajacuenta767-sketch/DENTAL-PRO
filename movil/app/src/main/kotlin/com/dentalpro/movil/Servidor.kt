package com.dentalpro.movil

import android.content.Context
import android.net.Uri
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL

/**
 * Dirección del servidor DENTAL-PRO (en la nube o en la red local de la
 * clínica) guardada en SharedPreferences, más las utilidades para
 * validarla y comprobar que responde.
 */
object Servidor {

    private const val PREFERENCIAS = "dentalpro"
    private const val CLAVE_URL = "servidor"

    /** Ruta con la que arranca la app: si ya hay sesión, Laravel redirige al panel o al portal. */
    const val RUTA_INICIO = "/login"

    private val IPV4 = Regex("""^\d{1,3}(\.\d{1,3}){3}$""")

    fun obtener(context: Context): String? =
        context.getSharedPreferences(PREFERENCIAS, Context.MODE_PRIVATE)
            .getString(CLAVE_URL, null)
            ?.takeIf { it.isNotBlank() }

    fun guardar(context: Context, url: String) {
        context.getSharedPreferences(PREFERENCIAS, Context.MODE_PRIVATE)
            .edit()
            .putString(CLAVE_URL, url)
            .apply()
    }

    fun urlInicio(servidor: String): String = servidor + RUTA_INICIO

    /**
     * Normaliza lo que escribe el usuario: quita espacios y barras finales y,
     * si no indicó esquema, asume http:// para direcciones de red local
     * (IP, localhost, *.local) y https:// para dominios. Devuelve null si no
     * es una dirección válida.
     */
    fun normalizar(texto: String): String? {
        var t = texto.trim().trimEnd('/')
        if (t.isEmpty()) return null

        if (!t.contains("://")) {
            val host = t.substringBefore('/').substringBefore(':')
            t = (if (esRedLocal(host)) "http://" else "https://") + t
        }

        val uri = Uri.parse(t)
        val esquema = uri.scheme?.lowercase() ?: return null
        if (esquema != "http" && esquema != "https") return null

        val host = uri.host?.takeIf { it.isNotBlank() } ?: return null
        val puerto = if (uri.port > 0) ":${uri.port}" else ""
        val ruta = (uri.path ?: "").trimEnd('/')

        return "$esquema://${host.lowercase()}$puerto$ruta"
    }

    private fun esRedLocal(host: String): Boolean {
        val h = host.lowercase()
        return h == "localhost" || h.endsWith(".local") || IPV4.matches(h)
    }

    /**
     * Verdadero si la URL apunta al mismo servidor configurado: mismo host y,
     * cuando ambos indican puerto, el mismo puerto. El esquema no importa para
     * que una redirección http → https siga abriéndose dentro de la app.
     */
    fun mismoServidor(url: Uri, servidor: String?): Boolean {
        if (servidor == null) return false
        val base = Uri.parse(servidor)
        val hostUrl = url.host?.lowercase() ?: return false
        if (hostUrl != base.host?.lowercase()) return false
        return url.port <= 0 || base.port <= 0 || url.port == base.port
    }

    /** Resultado de una comprobación de conexión. */
    data class Comprobacion(val correcto: Boolean, val mensaje: String)

    /**
     * Comprueba que el servidor responde consultando /up (comprobación de
     * salud de Laravel). Debe ejecutarse fuera del hilo principal.
     */
    fun comprobar(servidor: String): Comprobacion {
        var conexion: HttpURLConnection? = null
        return try {
            conexion = (URL("$servidor/up").openConnection() as HttpURLConnection).apply {
                connectTimeout = 8000
                readTimeout = 8000
                instanceFollowRedirects = true
                setRequestProperty("Accept", "text/html")
                setRequestProperty("User-Agent", "DentalProAndroid/2.0 (comprobacion)")
            }
            val codigo = conexion.responseCode
            when {
                codigo in 200..299 -> Comprobacion(true, "Servidor DENTAL-PRO encontrado (HTTP $codigo).")
                codigo in 300..399 -> Comprobacion(true, "El servidor responde (redirección HTTP $codigo).")
                codigo == 404 -> Comprobacion(false, "El servidor responde pero no parece ser DENTAL-PRO (HTTP 404). Revisa la dirección.")
                else -> Comprobacion(false, "El servidor devolvió el error HTTP $codigo.")
            }
        } catch (e: javax.net.ssl.SSLException) {
            Comprobacion(false, "Error de certificado HTTPS: ${e.message ?: "certificado no válido"}. Si es un servidor local, prueba con http://.")
        } catch (e: IOException) {
            Comprobacion(false, "No se pudo conectar: ${e.message ?: "sin respuesta"}. Comprueba la dirección, el puerto y que estés en la misma red.")
        } catch (e: Exception) {
            Comprobacion(false, "Dirección no válida: ${e.message ?: e.javaClass.simpleName}")
        } finally {
            conexion?.disconnect()
        }
    }
}
