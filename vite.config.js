import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/apexcharts')) {
                        return 'vendor_apexcharts';
                    }
                    if (id.includes('node_modules/@tabler/core')) {
                        return 'vendor_tabler';
                    }
                    if (id.includes('node_modules/tom-select')) {
                        return 'vendor_tomselect';
                    }
                    if (id.includes('node_modules/axios')) {
                        return 'vendor_axios';
                    }
                },
            },
        },
        chunkSizeWarningLimit: 1000,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
