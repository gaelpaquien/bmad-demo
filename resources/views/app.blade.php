<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'BMAD Démo') }}</title>

        <script>
            (function () {
                try {
                    var stored = localStorage.getItem('bmad-demo-theme');
                    if (!stored) {
                        // Migration bmad-demo -> BMAD Démo : reprend la préférence sauvegardée
                        // sous l'ancienne clé pour ne pas la perdre au premier chargement.
                        var legacy = localStorage.getItem('bmad-demo-theme');
                        if (legacy) {
                            stored = legacy;
                            localStorage.setItem('bmad-demo-theme', legacy);
                        }
                    }
                    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    var root = document.documentElement;
                    var customTheme = null;
                    var tokens = ['background', 'surface', 'surface-alt', 'border', 'foreground', 'muted', 'primary', 'primary-hover', 'primary-foreground'];
                    if (stored === 'custom') {
                        // Thème personnalisé : même validation stricte que useTheme.js (base connue
                        // et les 9 couleurs en hex valide). Sinon, retombe sur la préférence système.
                        try {
                            var custom = JSON.parse(localStorage.getItem('bmad-demo-theme-custom'));
                            var isValid = (custom.base === 'light' || custom.base === 'dark') && tokens.every(function (key) {
                                return typeof custom.colors[key] === 'string' && /^#[0-9a-f]{6}$/i.test(custom.colors[key]);
                            });
                            if (isValid) {
                                customTheme = custom;
                            }
                        } catch (e) {}
                    }
                    if (customTheme) {
                        if (customTheme.base === 'dark') {
                            root.classList.add('dark');
                        }
                        tokens.forEach(function (key) {
                            root.style.setProperty('--color-' + key, customTheme.colors[key]);
                        });
                    } else if (stored === 'dark' || (stored !== 'light' && prefersDark)) {
                        root.classList.add('dark');
                    }
                } catch (e) {}
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body class="antialiased bg-background text-foreground">
        @inertia
    </body>
</html>
