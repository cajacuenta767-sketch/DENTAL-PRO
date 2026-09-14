'use strict';

// Puente mínimo entre la ventana de carga y el proceso principal.
// La ventana principal (la app Laravel) NO usa este preload: no tiene acceso a Node.
const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('escritorio', {
    info: () => ipcRenderer.invoke('info'),
    onProgreso: (cb) => ipcRenderer.on('progreso', (_evento, datos) => cb(datos)),
    onError: (cb) => ipcRenderer.on('error', (_evento, datos) => cb(datos)),
    abrirRegistros: () => ipcRenderer.send('abrir-registros'),
    abrirCarpetaDatos: () => ipcRenderer.send('abrir-carpeta-datos'),
    reintentar: () => ipcRenderer.send('reintentar'),
    salir: () => ipcRenderer.send('salir'),
});
