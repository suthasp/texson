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
            colors: {
                // โทนสีตามแบรนด์ TEXSON (spec 7)
                navy: {
                    50: '#f2f5fa',
                    100: '#e2e8f2',
                    200: '#c6d1e5',
                    300: '#9aaed0',
                    400: '#6883b5',
                    500: '#46639c',
                    600: '#334c7f',
                    700: '#2a3e67',
                    800: '#1f2f4d',
                    900: '#1B2A4A',
                    950: '#111b30',
                },
                aqua: {
                    50: '#eefafd',
                    100: '#d3f2f9',
                    200: '#ade5f3',
                    300: '#74d2ea',
                    400: '#29B6D8',
                    500: '#1799bc',
                    600: '#157b9e',
                    700: '#186380',
                    800: '#1d5269',
                    900: '#1c455a',
                },
            },
            fontFamily: {
                // Prompt โหลดจาก Google Fonts ใน layout · Sarabun เป็นตัวสำรองสำหรับเครื่องที่ต่อเน็ตไม่ได้
                sans: ['Prompt', 'Sarabun', ...defaultTheme.fontFamily.sans],
                // ใช้กับรหัส เลขที่เอกสาร และ IP ซึ่งเป็น ASCII ล้วน จึงใช้ stack มาตรฐานพอ
                mono: [...defaultTheme.fontFamily.mono],
            },
        },
    },

    plugins: [forms],
};
