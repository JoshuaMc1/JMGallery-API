/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "./node_modules/flowbite/**/*.js"
    ],
    theme: {
        container: {
            center: true
        },
        extend: {},
    },
    plugins: [
        require('flowbite/plugin')
    ],
}

