module.exports = {
    purge: {
        content: [
            "./storage/framework/views/*.php",
            "./resources/**/*.blade.php",
            "./resources/**/*.js",
            "./resources/**/*.vue",
        ],
        options: {
            safelist: [
                "type", // [type='checkbox'], etc..
            ],
        },
    },
    darkMode: false, // or 'media' or 'class'
    theme: {
        extend: {},
    },
    variants: {
        extend: {},
    },
    plugins: [require("@tailwindcss/forms")],
};
