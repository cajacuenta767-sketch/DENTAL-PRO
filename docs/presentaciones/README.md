# Presentaciones de OdontoSuite

Piezas promocionales de los módulos principales, al estilo "mockup de laptop + móvil"
sobre el fondo de marca del sistema.

- `index.html` — página responsive con las 9 piezas. Cada pieza escala con el ancho
  disponible, así que se ve igual en móvil, tablet o pantalla grande. Desde la barra
  superior se cambia marca, WhatsApp y formato (1:1, 4:5 y 9:16); los
  cambios se aplican a todas las piezas a la vez.
- `img/` — exportaciones listas para publicar (1080 px de ancho, JPG):
  `post-1x1/`, `feed-4x5/` y `historia-9x16/`.
- `exportar.mjs` — regenera las imágenes con tus datos:

```bash
npm i -D playwright && npx playwright install chromium
node docs/presentaciones/exportar.mjs --marca "Mi Clínica" --wa "+57 300 000 0000"
```

Piezas incluidas: portada del sistema, panel de control, citas y agenda, odontograma,
historia clínica, caja y facturación, reportes, turnos online con QR, y presupuestos
e inventario.
