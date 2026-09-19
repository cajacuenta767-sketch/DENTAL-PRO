# CLAUDE.md

La guía de este repositorio vive en **[AGENTS.md](AGENTS.md)**: arranque en un
comando, mapa del código, convenciones y las trampas que ya costaron un fallo.
Léela antes de tocar nada.

Lo mínimo:

```bash
./scripts/instalar.sh       # clonar → funcionando
./scripts/verificar.sh      # comprobar una instalación existente

php artisan test            # 130 pruebas, todas en verde
./vendor/bin/pint           # formato, obligatorio antes de commitear
```

El proyecto está **íntegramente en español** (tablas, modelos, columnas,
métodos, interfaz). Mantén esa convención.

Necesita **PostgreSQL**: los reportes usan sintaxis propia del motor.
