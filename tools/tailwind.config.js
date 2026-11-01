// Rebuild assets/vendor/tailwind.css after changing page markup:
//   npx tailwindcss@3.4.17 -c tools/tailwind.config.js -i tools/tailwind.in.css -o assets/vendor/tailwind.css --minify
module.exports = {
  content: ['./*.php', './partials/*.php', './assets/*.js'],
  theme: { extend: {
    fontFamily: { display: ['Fredoka', 'system-ui', 'sans-serif'], sans: ['Inter', 'system-ui', 'sans-serif'] },
    colors: { ink: '#f5f7ff', muted: '#a8b1d8' },
  } },
};
