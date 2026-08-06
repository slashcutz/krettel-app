import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './node_modules/flowbite/**/*.js'
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: '#EF4444',
                secondary: '#111827',
                background: '#0B0B0B',
                card: '#18181B',
                border: '#27272A',
                text: '#FFFFFF',
                muted: '#9CA3AF',
                success: '#22C55E',
                warning: '#FACC15',
                danger: '#EF4444',
            },
            boxShadow: {
                'soft': '0 10px 40px -10px rgba(0,0,0,0.5)',
            }
        },
    },

    plugins: [
        forms,
        require('flowbite/plugin')
    ],
};
