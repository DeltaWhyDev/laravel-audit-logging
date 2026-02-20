const mix = require('laravel-mix');

mix.js('resources/js/field.js', 'dist/js')
   .vue({ version: 3 })
   .postCss('resources/css/field.css', 'dist/css', [
       require('tailwindcss'),
   ])
   .setPublicPath('dist');
