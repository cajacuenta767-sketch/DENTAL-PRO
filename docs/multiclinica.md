# Clínicas, activaciones e invitaciones

## Alta de un comprador

1. El Super Administrador abre **Clínicas y activaciones**.
2. Indica el correo, plan y vigencia.
3. El sistema envía un enlace y muestra el código completo una sola vez.
4. El comprador se registra con el código e indica el nombre de su clínica.
5. Se crea la clínica y su cuenta recibe el rol `ADMINISTRADOR`.

## Alta de equipo y pacientes

1. El Administrador abre **Invitaciones**.
2. Selecciona `DOCTOR`, `RECEPCION` o `PACIENTE`.
3. Puede restringir el código a un correo y definir su vencimiento.
4. Al registrarse con el código, la cuenta queda vinculada a esa clínica.
5. Sin código, el registro siempre crea un paciente sin clínica vinculada.

## Seguridad

- Los códigos se validan con SHA-256 y su copia consultable se guarda cifrada con la clave de la aplicación.
- Solo usuarios autorizados pueden revelar y copiar el código desde su panel.
- Cada código tiene vencimiento, límite de usos, revocación y trazabilidad.
- Un Administrador de clínica no puede conceder `ADMINISTRADOR` ni `SUPER ADMINISTRADOR`.
- Los datos clínicos se etiquetan y filtran por `clinica_id`.
- Una clínica suspendida o con licencia vencida queda bloqueada.
