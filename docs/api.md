# API REST de OdontoSuite (v1)

La API expone en JSON los módulos de pacientes, citas, agenda, catálogos,
presupuestos y pagos para integraciones externas (centrales telefónicas,
chatbots, sistemas contables, apps móviles). Se autentica con **tokens
personales de Laravel Sanctum** creados desde *Mi perfil* y hereda los
permisos del usuario que los creó.

- URL base: `https://tu-dominio/api/v1`
- Formato: JSON (envía siempre `Accept: application/json`)
- Zona horaria y formato de fechas: `YYYY-MM-DD`; horas `HH:MM`; marcas de
  tiempo en ISO 8601.

## 1. Autenticación

### Crear un token

1. Entra al panel con un usuario que tenga el permiso `api.usar` (los roles
   **SUPER ADMINISTRADOR** y **ADMINISTRADOR** lo tienen de fábrica).
2. Abre **Mi perfil → Tokens de API**, escribe un nombre (por ejemplo
   *Central telefónica*) y, si quieres, una fecha de expiración.
3. Copia el token que aparece en pantalla: **se muestra una sola vez**.

El token recibe como *capacidades* (abilities) los permisos que tenía el
usuario en ese momento. Una petición solo se atiende si el usuario **sigue
teniendo** el permiso *y* el token lo incluye; revocar un permiso al usuario
o revocar el token cierran el acceso de inmediato. Los usuarios con estado
inactivo reciben `403`.

### Usar el token

Cabecera `Authorization: Bearer <token>` en cada petición:

```bash
export TOKEN="1|AbCdEf..."
curl -s https://tu-dominio/api/v1/yo \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

```json
{
  "id": 1,
  "nombre": "Ana Administradora",
  "email": "ana@clinica.test",
  "roles": ["ADMINISTRADOR"],
  "permisos": ["agenda.ver", "citas.crear", "citas.ver", "pacientes.ver", "..."]
}
```

### Revocar un token

Desde **Mi perfil → Tokens de API → Revocar**. Solo el dueño del token puede
revocarlo; cada creación y revocación queda en la auditoría.

## 2. Convenciones

### Paginación

Todos los listados devuelven páginas de 15 elementos. Parámetros:

| Parámetro  | Descripción                          |
|------------|--------------------------------------|
| `page`     | Número de página (desde 1)           |
| `per_page` | Elementos por página, **máximo 100** |

Estructura de la respuesta:

```json
{
  "data": [ ... ],
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." },
  "meta": { "current_page": 1, "from": 1, "last_page": 4, "per_page": 15, "to": 15, "total": 52, "path": "...", "links": [ ... ] }
}
```

Los recursos individuales vienen envueltos en `data`.

### Códigos de estado

| Código | Significado                                                                 |
|--------|-----------------------------------------------------------------------------|
| `200`  | Correcto                                                                    |
| `201`  | Recurso creado                                                              |
| `401`  | Sin token, token inválido, revocado o expirado                              |
| `403`  | El usuario no tiene el permiso, el token no lo incluye o la cuenta está inactiva |
| `404`  | El recurso no existe (o fue eliminado)                                      |
| `422`  | Error de validación o regla de negocio (cupo ocupado, hora fuera de turno…) |
| `429`  | Se superó el límite de peticiones                                           |

Formato de error (`401`, `403`, `404`):

```json
{ "message": "No tienes el permiso pagos.ver." }
```

Formato de error de validación (`422`):

```json
{
  "message": "Ese horario se cruza con la cita 8F3KQ2ZP1M7L (09:00, 30 min). Elige otro.",
  "errors": { "hora": ["Ese horario se cruza con la cita 8F3KQ2ZP1M7L (09:00, 30 min). Elige otro."] }
}
```

### Límite de peticiones

**120 peticiones por minuto** por token (cabeceras `X-RateLimit-Limit` y
`X-RateLimit-Remaining`). Al superarlo la API responde `429` con
`Retry-After` en segundos.

## 3. Endpoints

| Método  | Ruta                          | Permiso             | Descripción                                |
|---------|-------------------------------|---------------------|--------------------------------------------|
| `GET`   | `/yo`                         | — (token válido)    | Usuario, roles y permisos del token        |
| `GET`   | `/pacientes`                  | `pacientes.ver`     | Listar y buscar pacientes                  |
| `POST`  | `/pacientes`                  | `pacientes.crear`   | Registrar un paciente                      |
| `GET`   | `/pacientes/{id}`             | `pacientes.ver`     | Ficha de un paciente                       |
| `GET`   | `/citas`                      | `citas.ver`         | Listar citas con filtros                   |
| `POST`  | `/citas`                      | `citas.crear`       | Agendar una cita                           |
| `GET`   | `/citas/{id}`                 | `citas.ver`         | Detalle de una cita                        |
| `PATCH` | `/citas/{id}/estado`          | `citas.editar`      | Cambiar el estado de una cita              |
| `GET`   | `/agenda/horas`               | `citas.ver`         | Horas libres de un doctor en una fecha     |
| `GET`   | `/doctores`                   | `doctores.ver`      | Catálogo de doctores                       |
| `GET`   | `/especialidades`             | `especialidades.ver`| Catálogo de especialidades                 |
| `GET`   | `/tratamientos`               | `tratamientos.ver`  | Catálogo de tratamientos                   |
| `GET`   | `/presupuestos`               | `presupuestos.ver`  | Listar presupuestos                        |
| `GET`   | `/presupuestos/{id}`          | `presupuestos.ver`  | Presupuesto con sus líneas                 |
| `GET`   | `/pagos`                      | `pagos.ver`         | Listar recibos                             |
| `GET`   | `/pagos/{id}`                 | `pagos.ver`         | Recibo con sus líneas                      |

En los ejemplos siguientes se asume `-H "Authorization: Bearer $TOKEN" -H "Accept: application/json"`
(abreviado como `$AUTH`):

```bash
export AUTH=(-H "Authorization: Bearer $TOKEN" -H "Accept: application/json")
```

### 3.1 Pacientes

#### `GET /pacientes`

| Parámetro  | Tipo    | Descripción                                                              |
|------------|---------|--------------------------------------------------------------------------|
| `q`        | string  | Busca por nombres, apellidos, número de documento, teléfono o correo     |
| `activo`   | 0/1     | Solo activos (`1`) o solo inactivos (`0`); sin el parámetro devuelve todos |
| `page`, `per_page` | | Paginación                                                        |

```bash
curl -s "https://tu-dominio/api/v1/pacientes?q=perez&per_page=5" "${AUTH[@]}"
```

```json
{
  "data": [
    {
      "id": 12,
      "nombres": "Juan",
      "apellidos": "Pérez",
      "nombre_completo": "Juan Pérez",
      "tipo_documento": "CI",
      "numero_documento": "7654321",
      "fecha_nacimiento": "1990-05-20",
      "edad": 36,
      "genero": "M",
      "telefono": "70011223",
      "email": "juan.perez@correo.test",
      "direccion": null,
      "grupo_sanguineo": "O+",
      "alergias": null,
      "enfermedades": null,
      "medicamentos": null,
      "habitos": null,
      "antecedentes": null,
      "contacto_emergencia": null,
      "telefono_emergencia": null,
      "observaciones": null,
      "numero_afiliado": null,
      "aseguradora": null,
      "activo": true,
      "creado_en": "2026-03-01T10:15:00-04:00",
      "actualizado_en": "2026-03-01T10:15:00-04:00"
    }
  ],
  "links": { "...": "..." },
  "meta": { "current_page": 1, "per_page": 5, "total": 1, "...": "..." }
}
```

#### `POST /pacientes`

Mismas reglas que el formulario del panel (sin fotografía).

| Campo                 | Reglas                                              |
|-----------------------|-----------------------------------------------------|
| `nombres`             | obligatorio, máx. 150                               |
| `apellidos`           | obligatorio, máx. 150                               |
| `tipo_documento`      | obligatorio: `CI`, `DNI`, `PASAPORTE`, `CE`         |
| `numero_documento`    | obligatorio, máx. 20, único                         |
| `genero`              | obligatorio: `M`, `F`, `O`                          |
| `fecha_nacimiento`    | opcional, fecha no futura                           |
| `aseguradora_id`      | opcional, id existente                              |
| `numero_afiliado`     | opcional, máx. 60                                   |
| `telefono`, `email`, `direccion` | opcionales                               |
| `grupo_sanguineo`     | opcional: `A+ A- B+ B- AB+ AB- O+ O-`               |
| `alergias`, `enfermedades`, `medicamentos`, `habitos`, `antecedentes`, `observaciones` | opcionales, máx. 1000 |
| `contacto_emergencia`, `telefono_emergencia` | opcionales                   |
| `activo`              | opcional, booleano (por defecto `true`)             |

```bash
curl -s -X POST https://tu-dominio/api/v1/pacientes "${AUTH[@]}" \
  -H "Content-Type: application/json" \
  -d '{
    "nombres": "Lucía",
    "apellidos": "Mamani",
    "tipo_documento": "CI",
    "numero_documento": "5551234",
    "genero": "F",
    "telefono": "70011223",
    "email": "lucia@correo.test"
  }'
```

Responde `201` con la ficha creada en `data`. Documento repetido → `422` con
`errors.numero_documento`.

#### `GET /pacientes/{id}`

```bash
curl -s https://tu-dominio/api/v1/pacientes/12 "${AUTH[@]}"
```

### 3.2 Citas

#### `GET /citas`

Ordenadas por fecha y hora ascendente. Incluye `paciente`, `doctor`
(con `especialidad`) y `tratamiento`.

| Parámetro     | Tipo       | Descripción                                             |
|---------------|------------|---------------------------------------------------------|
| `fecha`       | YYYY-MM-DD | Citas de un día exacto                                  |
| `desde`       | YYYY-MM-DD | Desde esta fecha (inclusive)                            |
| `hasta`       | YYYY-MM-DD | Hasta esta fecha (inclusive, ≥ `desde`)                 |
| `doctor_id`   | int        | Solo de este doctor                                     |
| `paciente_id` | int        | Solo de este paciente                                   |
| `estado`      | string     | `PENDIENTE`, `CONFIRMADA`, `EN_CURSO`, `COMPLETADA`, `CANCELADA` |

```bash
curl -s "https://tu-dominio/api/v1/citas?desde=2026-09-15&hasta=2026-09-19&doctor_id=3&estado=CONFIRMADA" "${AUTH[@]}"
```

```json
{
  "data": [
    {
      "id": 240,
      "token": "8F3KQ2ZP1M7L",
      "fecha": "2026-09-16",
      "hora": "09:00",
      "duracion_minutos": 30,
      "estado": "CONFIRMADA",
      "estado_legible": "Confirmada",
      "origen": "RECEPCION",
      "motivo": "Control semestral",
      "observacion": null,
      "paciente_id": 12,
      "doctor_id": 3,
      "tratamiento_id": 7,
      "sucursal_id": null,
      "serie_id": null,
      "confirmada_en": "2026-09-14T18:02:11-04:00",
      "paciente": { "id": 12, "nombre_completo": "Juan Pérez", "...": "..." },
      "doctor": { "id": 3, "nombre_profesional": "Dra. Sofía Arancibia", "especialidad": { "id": 1, "nombre": "ODONTOLOGÍA GENERAL", "...": "..." }, "...": "..." },
      "tratamiento": { "id": 7, "nombre": "PROFILAXIS DENTAL", "precio": 180, "duracion": 30, "...": "..." },
      "creado_en": "2026-09-10T11:30:00-04:00",
      "actualizado_en": "2026-09-14T18:02:11-04:00"
    }
  ],
  "links": { "...": "..." },
  "meta": { "...": "..." }
}
```

#### `POST /citas`

Aplica las mismas reglas de agenda del panel: la hora debe caer en un turno
activo del doctor **y** la cita completa (según la duración del tratamiento)
debe caber en él sin cruzarse con otra cita vigente. Si el paciente tiene
correo se le envía la confirmación.

| Campo            | Reglas                                                              |
|------------------|---------------------------------------------------------------------|
| `paciente_id`    | obligatorio, id existente                                           |
| `doctor_id`      | obligatorio, id existente                                           |
| `tratamiento_id` | obligatorio, id existente (define la duración)                      |
| `fecha`          | obligatorio, `YYYY-MM-DD`                                           |
| `hora`           | obligatorio, `HH:MM`                                                |
| `estado`         | opcional, uno de los estados válidos (por defecto `PENDIENTE`)      |
| `motivo`         | opcional, máx. 1000                                                 |
| `observacion`    | opcional, máx. 1000                                                 |

```bash
curl -s -X POST https://tu-dominio/api/v1/citas "${AUTH[@]}" \
  -H "Content-Type: application/json" \
  -d '{
    "paciente_id": 12,
    "doctor_id": 3,
    "tratamiento_id": 7,
    "fecha": "2026-09-16",
    "hora": "09:00",
    "motivo": "Control semestral"
  }'
```

Respuestas:

- `201` con la cita en `data` (incluye el `token` de 12 caracteres).
- `422` con `errors.hora` cuando el doctor no atiende a esa hora, el
  tratamiento no cabe en el turno o el cupo ya está tomado:

```json
{
  "message": "El doctor no atiende ese día a esa hora o el tratamiento (30 min) no cabe en su turno. Revisa sus horarios configurados.",
  "errors": { "hora": ["El doctor no atiende ese día a esa hora o el tratamiento (30 min) no cabe en su turno. Revisa sus horarios configurados."] }
}
```

#### `GET /citas/{id}`

```bash
curl -s https://tu-dominio/api/v1/citas/240 "${AUTH[@]}"
```

#### `PATCH /citas/{id}/estado`

| Campo    | Reglas                                                                    |
|----------|---------------------------------------------------------------------------|
| `estado` | obligatorio: `PENDIENTE`, `CONFIRMADA`, `EN_CURSO`, `COMPLETADA`, `CANCELADA` |

Reglas de negocio (`422`): una cita **COMPLETADA** ya no cambia de estado, y
reactivar una **CANCELADA** exige que su cupo siga libre.

```bash
curl -s -X PATCH https://tu-dominio/api/v1/citas/240/estado "${AUTH[@]}" \
  -H "Content-Type: application/json" \
  -d '{"estado": "CONFIRMADA"}'
```

Responde `200` con la cita actualizada en `data`.

### 3.3 Agenda

#### `GET /agenda/horas`

Horas de inicio en las que cabe una cita del tratamiento indicado
(o de un cupo de agenda si no se indica) con ese doctor y en esa fecha.

| Parámetro        | Tipo       | Descripción                                        |
|------------------|------------|----------------------------------------------------|
| `doctor_id`      | int        | obligatorio                                        |
| `fecha`          | YYYY-MM-DD | obligatorio                                        |
| `tratamiento_id` | int        | opcional; usa su duración para calcular los cupos  |

```bash
curl -s "https://tu-dominio/api/v1/agenda/horas?doctor_id=3&fecha=2026-09-16&tratamiento_id=7" "${AUTH[@]}"
```

```json
{
  "fecha": "2026-09-16",
  "dia": "Miércoles",
  "intervalo": 30,
  "duracion": 30,
  "horas": ["08:00", "08:30", "09:30", "10:00", "10:30", "11:00", "11:30"]
}
```

`horas` vacío significa que el doctor no atiende ese día o no le quedan cupos.

### 3.4 Catálogos

Devuelven solo registros activos salvo que se pase `activo=0`.

#### `GET /doctores`

| Parámetro         | Descripción                              |
|-------------------|------------------------------------------|
| `q`               | Busca por nombres o apellidos            |
| `especialidad_id` | Solo de esa especialidad                 |
| `activo`          | `1` (por defecto) o `0`                  |

```bash
curl -s "https://tu-dominio/api/v1/doctores" "${AUTH[@]}"
```

```json
{
  "data": [
    {
      "id": 3,
      "nombres": "Sofía",
      "apellidos": "Arancibia",
      "nombre_completo": "Sofía Arancibia",
      "nombre_profesional": "Dra. Sofía Arancibia",
      "genero": "F",
      "telefono": null,
      "email": null,
      "colegiatura": null,
      "especialidad_id": 1,
      "especialidad": { "id": 1, "nombre": "ODONTOLOGÍA GENERAL", "descripcion": null, "color": null, "activo": true },
      "activo": true
    }
  ],
  "links": { "...": "..." },
  "meta": { "...": "..." }
}
```

#### `GET /especialidades`

```bash
curl -s "https://tu-dominio/api/v1/especialidades" "${AUTH[@]}"
```

#### `GET /tratamientos`

| Parámetro         | Descripción                              |
|-------------------|------------------------------------------|
| `q`               | Busca por nombre                         |
| `especialidad_id` | Solo de esa especialidad                 |
| `activo`          | `1` (por defecto) o `0`                  |

```bash
curl -s "https://tu-dominio/api/v1/tratamientos?q=profilaxis" "${AUTH[@]}"
```

```json
{
  "data": [
    { "id": 7, "nombre": "PROFILAXIS DENTAL", "descripcion": null, "precio": 180, "duracion": 30, "especialidad_id": 1, "especialidad": { "...": "..." }, "activo": true }
  ],
  "links": { "...": "..." },
  "meta": { "...": "..." }
}
```

### 3.5 Presupuestos (solo lectura)

#### `GET /presupuestos`

| Parámetro     | Descripción                                                                       |
|---------------|-----------------------------------------------------------------------------------|
| `paciente_id` | Solo de ese paciente                                                              |
| `estado`      | `BORRADOR`, `PRESENTADO`, `APROBADO`, `EN_EJECUCION`, `COMPLETADO`, `RECHAZADO`   |

```bash
curl -s "https://tu-dominio/api/v1/presupuestos?paciente_id=12" "${AUTH[@]}"
```

#### `GET /presupuestos/{id}`

```bash
curl -s https://tu-dominio/api/v1/presupuestos/45 "${AUTH[@]}"
```

```json
{
  "data": {
    "id": 45,
    "codigo": "PRE-2026-00045",
    "fecha": "2026-09-01",
    "vence_el": "2026-10-01",
    "validez_dias": 30,
    "estado": "APROBADO",
    "estado_legible": "Aprobado",
    "subtotal": 1200,
    "descuento": 0,
    "cobertura_seguro": 600,
    "porcentaje_cobertura": 50,
    "total": 600,
    "notas": null,
    "paciente_id": 12,
    "doctor_id": 3,
    "aseguradora_id": 2,
    "paciente": { "...": "..." },
    "doctor": { "...": "..." },
    "avance": 50,
    "detalles": [
      {
        "id": 301,
        "tratamiento_id": 9,
        "tratamiento": "ENDODONCIA",
        "descripcion": "Endodoncia pieza 16",
        "pieza_dental": "16",
        "cara": null,
        "ubicacion": "Pieza 16",
        "cantidad": 1,
        "precio_unitario": 900,
        "subtotal": 900,
        "estado": "EJECUTADO",
        "sesion": 1,
        "orden": 1,
        "fecha_ejecucion": "2026-09-10",
        "cita_id": 240,
        "pago_id": 88
      }
    ],
    "creado_en": "2026-09-01T09:00:00-04:00",
    "actualizado_en": "2026-09-10T12:00:00-04:00"
  }
}
```

### 3.6 Pagos (solo lectura)

#### `GET /pagos`

| Parámetro     | Descripción                                                 |
|---------------|-------------------------------------------------------------|
| `paciente_id` | Solo de ese paciente                                        |
| `desde`       | Fecha de pago desde (`YYYY-MM-DD`)                          |
| `hasta`       | Fecha de pago hasta (`YYYY-MM-DD`, ≥ `desde`)               |
| `estado`      | `PENDIENTE`, `PARCIAL`, `COMPLETADO`, `ANULADO`             |

```bash
curl -s "https://tu-dominio/api/v1/pagos?desde=2026-09-01&hasta=2026-09-30&estado=COMPLETADO" "${AUTH[@]}"
```

#### `GET /pagos/{id}`

```bash
curl -s https://tu-dominio/api/v1/pagos/88 "${AUTH[@]}"
```

```json
{
  "data": {
    "id": 88,
    "codigo_recibo": "REC-2026-00088",
    "fecha_pago": "2026-09-10T12:00:00-04:00",
    "estado": "COMPLETADO",
    "metodo_pago": "TARJETA",
    "monto_total": 600,
    "monto_pagado": 600,
    "monto_saldo": 0,
    "notas": null,
    "paciente_id": 12,
    "doctor_id": 3,
    "cita_id": 240,
    "presupuesto_id": 45,
    "sucursal_id": null,
    "paciente": { "...": "..." },
    "doctor": { "...": "..." },
    "detalles": [
      { "id": 410, "tratamiento_id": 9, "tratamiento": "ENDODONCIA", "descripcion": "Endodoncia pieza 16", "cantidad": 1, "precio_unitario": 600, "subtotal": 600 }
    ],
    "creado_en": "2026-09-10T12:00:00-04:00",
    "actualizado_en": "2026-09-10T12:00:00-04:00"
  }
}
```

## 4. Buenas prácticas

- Guarda el token en un gestor de secretos; nunca lo incluyas en el código
  fuente ni en URLs.
- Crea **un token por integración** con un nombre reconocible y fecha de
  expiración; así puedes revocar uno sin afectar a los demás.
- Usa un usuario con los permisos mínimos necesarios (por ejemplo, un
  usuario de recepción sin `pagos.ver` para un chatbot de agendamiento).
- Ante un `429`, espera los segundos indicados en `Retry-After`.
- Los registros eliminados (pacientes, presupuestos, pagos) responden `404`;
  las citas canceladas siguen visibles con `estado = CANCELADA`.
