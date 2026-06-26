import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Filament/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: Object.fromEntries(
                    [50,100,200,300,400,500,600,700,800,900,950].map(shade => [
                        shade, `var(--primary-${shade})`,
                    ])
                ),
            },
        },
    },

    safelist: [
        'hidden',
        'sm:table-cell',
        'sm:hidden',
        'md:table-cell',
        'lg:table-cell',
        'cursor-pointer',
        'opacity-0',
        '-translate-x-2',
        { pattern: /brightness-110/, variants: ['hover'] },
        { pattern: /-translate-y-0\.5/, variants: ['hover'] },
    ],

    plugins: [forms],
};
