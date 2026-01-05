/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./Modules/**/*.blade.php",
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Vazirmatn', 'sans-serif'],
            },
        },
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
}
