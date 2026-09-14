package com.dentalpro.movil

import android.os.Bundle
import android.view.MenuItem
import android.view.inputmethod.EditorInfo
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.webkit.WebViewCompat
import com.google.android.material.appbar.MaterialToolbar
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import com.google.android.material.textfield.TextInputLayout

/**
 * Pantalla para indicar (o cambiar) la dirección del servidor DENTAL-PRO.
 * Se abre sola la primera vez y después desde el menú o manteniendo pulsada
 * la barra superior.
 */
class AjustesActivity : AppCompatActivity() {

    companion object {
        const val EXTRA_PRIMERA_VEZ = "primera_vez"
    }

    private lateinit var campoLayout: TextInputLayout
    private lateinit var campo: TextInputEditText
    private lateinit var estado: TextView
    private lateinit var botonProbar: MaterialButton
    private lateinit var botonGuardar: MaterialButton

    private var primeraVez = false

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_ajustes)

        primeraVez = intent.getBooleanExtra(EXTRA_PRIMERA_VEZ, false)

        val toolbar = findViewById<MaterialToolbar>(R.id.toolbar_ajustes)
        setSupportActionBar(toolbar)
        supportActionBar?.setDisplayHomeAsUpEnabled(!primeraVez)

        campoLayout = findViewById(R.id.campo_servidor_layout)
        campo = findViewById(R.id.campo_servidor)
        estado = findViewById(R.id.texto_estado)
        botonProbar = findViewById(R.id.btn_probar)
        botonGuardar = findViewById(R.id.btn_guardar)

        findViewById<TextView>(R.id.texto_intro).text = getString(
            if (primeraVez) R.string.ajustes_intro_primera_vez else R.string.ajustes_intro,
        )

        if (savedInstanceState == null) {
            campo.setText(Servidor.obtener(this) ?: "")
        }

        campo.setOnEditorActionListener { _, accion, _ ->
            if (accion == EditorInfo.IME_ACTION_DONE) {
                guardar()
                true
            } else {
                false
            }
        }
        campo.addTextChangedListener(object : android.text.TextWatcher {
            override fun beforeTextChanged(s: CharSequence?, start: Int, count: Int, after: Int) = Unit
            override fun onTextChanged(s: CharSequence?, start: Int, before: Int, count: Int) {
                campoLayout.error = null
            }
            override fun afterTextChanged(s: android.text.Editable?) = Unit
        })

        botonProbar.setOnClickListener { probar() }
        botonGuardar.setOnClickListener { guardar() }

        val versionWebView = WebViewCompat.getCurrentWebViewPackage(this)?.versionName
        findViewById<TextView>(R.id.texto_version).text = getString(
            R.string.ajustes_version,
            "2.0.0",
            versionWebView ?: getString(R.string.desconocido),
        )
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        if (item.itemId == android.R.id.home) {
            finish()
            return true
        }
        return super.onOptionsItemSelected(item)
    }

    private fun direccionNormalizada(): String? {
        val url = Servidor.normalizar(campo.text?.toString() ?: "")
        if (url == null) {
            campoLayout.error = getString(R.string.ajustes_direccion_invalida)
        }
        return url
    }

    private fun guardar() {
        val url = direccionNormalizada() ?: return
        Servidor.guardar(this, url)
        setResult(RESULT_OK)
        finish()
    }

    private fun probar() {
        val url = direccionNormalizada() ?: return
        campo.setText(url)
        campo.setSelection(url.length)
        estado.text = getString(R.string.ajustes_probando, url)
        botonProbar.isEnabled = false

        Thread {
            val resultado = Servidor.comprobar(url)
            runOnUiThread {
                if (isFinishing || isDestroyed) return@runOnUiThread
                botonProbar.isEnabled = true
                estado.text = resultado.mensaje
                estado.setTextColor(getColor(if (resultado.correcto) R.color.exito else R.color.error))
            }
        }.start()
    }
}
