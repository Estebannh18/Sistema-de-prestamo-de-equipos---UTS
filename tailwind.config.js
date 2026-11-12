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
                sans: ['"Nunito Sans"', ...defaultTheme.fontFamily.sans],
                display: ['Montserrat', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Paleta inspirada en el portal institucional UTS:
                // verde institucional + lima del logo (#c7d309) + gris del logotipo (#808286)
                marca: {
                    noche: '#0b3d2a',
                    bosque: '#0f5c3a',
                    oliva: '#1a7a46',
                    hoja: '#3fa45b',
                    lima: '#c7d309',
                    limaclaro: '#eef4b5',
                    crema: '#f4f7ef',
                    linea: '#dde5d6',
                    tinta: '#1d2a21',
                    suave: '#5c6a60',
                    gris: '#808286',
                    morado: '#0f5c3a',
                },
            },
            borderRadius: {
                '4xl': '2rem',
            },
        },
    },

    plugins: [forms],
};
