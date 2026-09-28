<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-GLhlTQ8iRABdZLl6O3oVMWSktQOp6b7In1Zl3/Jr59b6EGGoI1aFkw7cmDA6j6gD" crossorigin="anonymous">
    <script>
        (function() {
            function setColorMode(dark) {
                document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
            }
            // A theme chosen with the dashboard's switcher (stored per browser) wins over the system setting.
            function storedTheme() {
                try {
                    return localStorage.getItem('c4r-theme');
                } catch (e) {
                    return null;
                }
            }
            var systemDark = window.matchMedia("(prefers-color-scheme: dark)");
            var stored = storedTheme();
            setColorMode(stored ? stored === 'dark' : systemDark.matches);
            systemDark.addEventListener("change", function(e) {
                if (!storedTheme()) {
                    setColorMode(e.matches);
                }
            });
        })();
    </script>
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <link rel="shortcut icon" href="/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="C4R PDF" />
    <link rel="manifest" href="/site.webmanifest" />
    <title>PDF Usage Dashboard</title>
    @php(Vite::useHotFile(public_path('dashboard.hot'))->useBuildDirectory('build-dashboard'))
    @viteReactRefresh
    @vite(['resources/js/dashboard.jsx'])
    @inertiaHead
</head>

<body>
    @inertia
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-w76AqPfDkMBDXo30jS1Sgez6pr3x5MlQ1ZAGC+nuZB+EYdgRZgiwxhTBTkF7CXvN"
        crossorigin="anonymous"></script>
</body>

</html>
