# Reglas de R8 para DENTAL-PRO móvil.
# La app es un contenedor WebView sin reflexión propia, así que basta con
# conservar la interfaz JavaScript (por si se añaden puentes) y los nombres
# de línea para leer las trazas de error.

-keepattributes SourceFile,LineNumberTable
-renamesourcefileattribute SourceFile

-keepclassmembers class * {
    @android.webkit.JavascriptInterface <methods>;
}

# Los WebView con proveedores actualizables no necesitan reglas extra.
-dontwarn org.chromium.**
