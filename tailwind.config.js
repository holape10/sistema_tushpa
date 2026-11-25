import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

// Mantenimiento › Apariencia: el color principal del sistema ("indigo") sale de variables que la empresa elige
// (App\Support\Apariencia). Si no eligió nada, cada tono cae en el índigo de siempre.
const indigo = { 50: '238 242 255', 100: '224 231 255', 200: '199 210 254', 300: '165 180 252', 400: '129 140 248', 500: '99 102 241',
    600: '79 70 229', 700: '67 56 202', 800: '55 48 163', 900: '49 46 129', 950: '30 27 75' };
const principal = Object.fromEntries(Object.entries(indigo).map(([tono, rgb]) => [tono, `rgb(var(--ap-${tono}, ${rgb}) / <alpha-value>)`]));

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                indigo: principal,
            },
        },
    },

    plugins: [forms],
};
