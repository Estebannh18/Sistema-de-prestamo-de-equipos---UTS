import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
                sans: ['"Hanken Grotesk"', ...defaultTheme.fontFamily.sans],
                display: ['Raleway', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                marca: {
                    noche: '#12331f',
                    bosque: '#1f4a2c',
                    oliva: '#38591c',
                    lima: '#c2d62b',
                    crema: '#faf7f1',
                    linea: '#e4dfd3',
                    tinta: '#23291f',
                    suave: '#5d6459',
                    morado: '#4c1d87',
                },
            },
        },
    },

    plugins: [forms],
};
