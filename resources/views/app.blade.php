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
                        // Migration EkoDoc -> BMAD Démo : reprend la préférence sauvegardée
                        // sous l'ancienne clé pour ne pas la perdre au premier chargement.
                        var legacy = localStorage.getItem('ekodoc-theme');
                        if (legacy) {
                            stored = legacy;
                            localStorage.setItem('bmad-demo-theme', legacy);
                        }
                    }
                    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    if (stored === 'dark' || (!stored && prefersDark)) {
                        document.documentElement.classList.add('dark');
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
