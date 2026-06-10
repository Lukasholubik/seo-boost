/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './includes/**/*.php',
    './templates/**/*.php',
    './assets/admin/js/**/*.js',
  ],
  // Tailwind utility třídy se prefixují, aby nekolidovaly s WP admin CSS.
  prefix: 'seob-',
  important: '.seob-wrap',
  theme: {
    extend: {},
  },
  plugins: [],
};
