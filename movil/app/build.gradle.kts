import java.util.Properties

plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

/*
 * Firma de la APK release.
 *
 * - En CI (.github/workflows/movil.yml) las variables ANDROID_KEYSTORE_FILE,
 *   ANDROID_KEYSTORE_PASSWORD, ANDROID_KEY_ALIAS y ANDROID_KEY_PASSWORD apuntan
 *   al keystore de los secretos del repositorio o, si no existen, a uno temporal
 *   creado con keytool para que la APK sea instalable.
 * - En local se leen las mismas claves desde movil/keystore.properties si existe.
 * - Sin nada de lo anterior, la release se firma con la clave de depuración
 *   (instalable, pero NO válida para tiendas: ver docs/movil.md).
 */
val propiedadesFirma = Properties().apply {
    val archivo = rootProject.file("keystore.properties")
    if (archivo.exists()) archivo.inputStream().use { load(it) }
}

fun firma(clave: String): String? =
    System.getenv("ANDROID_$clave")?.takeIf { it.isNotBlank() }
        ?: propiedadesFirma.getProperty(clave)?.takeIf { it.isNotBlank() }

val keystoreRuta = firma("KEYSTORE_FILE")?.let { ruta ->
    val f = File(ruta)
    if (f.isAbsolute) f else rootProject.file(ruta)
}
val hayKeystore = keystoreRuta?.exists() == true

android {
    namespace = "com.dentalpro.movil"
    compileSdk = 34

    defaultConfig {
        applicationId = "com.dentalpro.movil"
        minSdk = 26
        targetSdk = 34
        versionCode = 20000
        versionName = "2.0.0"

        // Solo se distribuyen recursos en español (y el inglés por defecto de las bibliotecas).
        resourceConfigurations += listOf("es", "en")
        vectorDrawables.useSupportLibrary = true
    }

    signingConfigs {
        if (hayKeystore) {
            create("release") {
                storeFile = keystoreRuta
                storePassword = firma("KEYSTORE_PASSWORD")
                keyAlias = firma("KEY_ALIAS")
                keyPassword = firma("KEY_PASSWORD")
            }
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
            signingConfig = if (hayKeystore) signingConfigs.getByName("release") else signingConfigs.getByName("debug")
        }
        debug {
            applicationIdSuffix = ".debug"
            versionNameSuffix = "-debug"
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    lint {
        abortOnError = false
        checkReleaseBuilds = false
        warningsAsErrors = false
        xmlReport = true
        htmlReport = true
    }

    packaging {
        resources.excludes += setOf("META-INF/AL2.0", "META-INF/LGPL2.1")
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("androidx.swiperefreshlayout:swiperefreshlayout:1.1.0")
    implementation("androidx.webkit:webkit:1.11.0")
    implementation("com.google.android.material:material:1.12.0")
}
