import {
    defineConfig
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";
import react from "@vitejs/plugin-react";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // React island runtime for the interactive learning layer
                // (mistake hub · solutions · notes · widgets · AI tutor).
                'resources/js/widgets/index.jsx',
                // Learning Studio — the full React/Inertia learning section.
                'resources/js/learn/app.jsx',
            ],
            refresh: [`resources/views/**/*`],
        }),
        react({
            // Compile the React trees (widget islands + the Inertia learning
            // studio); leave the Livewire/Alpine side of the app untouched.
            include: [
                '**/resources/js/widgets/**/*.{jsx,tsx}',
                '**/resources/js/learn/**/*.{jsx,tsx}',
            ],
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
    },
});
