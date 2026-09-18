<x-mail::message>
# Hola, {{ \Illuminate\Support\Str::before($usuario->nombre, ' ') }}

Alguien está iniciando sesión en **{{ $clinica->nombre }}** con tu cuenta. Si eres tú, escribe este código en la pantalla de acceso:

<x-mail::panel>
<span style="font-size: 28px; letter-spacing: 6px; font-weight: 700;">{{ $codigo }}</span>
</x-mail::panel>

El código vence en {{ $minutos }} minutos y solo sirve una vez. Si no intentaste entrar, ignora este correo y cambia tu contraseña.

Saludos,<br>
{{ $clinica->nombre }}
</x-mail::message>
