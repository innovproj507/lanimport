        </main>
    </div>
</div>
<script>
(function () {
    // Menu lateral: en escritorio se colapsa, en movil se abre encima del contenido.
    var sidebar = document.getElementById('sidebar');
    var backdrop = document.getElementById('sidebar-backdrop');
    var esEscritorio = function () { return window.matchMedia('(min-width: 1024px)').matches; };

    document.getElementById('btn-sidebar').addEventListener('click', function () {
        if (esEscritorio()) {
            sidebar.classList.toggle('lg:flex');
            try { localStorage.setItem('sidebar_colapsado', sidebar.classList.contains('lg:flex') ? '0' : '1'); } catch (e) {}
        } else {
            sidebar.classList.toggle('hidden');
            sidebar.classList.toggle('flex');
            backdrop.classList.toggle('hidden');
        }
    });

    backdrop.addEventListener('click', function () {
        sidebar.classList.add('hidden');
        sidebar.classList.remove('flex');
        backdrop.classList.add('hidden');
    });

    try {
        if (localStorage.getItem('sidebar_colapsado') === '1') {
            sidebar.classList.remove('lg:flex');
        }
    } catch (e) {}

    // Menus desplegables de la barra superior (notificaciones, usuario).
    document.querySelectorAll('[data-dropdown]').forEach(function (dropdown) {
        var menu = dropdown.querySelector('[data-dropdown-menu]');

        dropdown.querySelector('[data-dropdown-toggle]').addEventListener('click', function (event) {
            event.stopPropagation();
            document.querySelectorAll('[data-dropdown-menu]').forEach(function (otro) {
                if (otro !== menu) { otro.classList.add('hidden'); }
            });
            menu.classList.toggle('hidden');
        });
    });

    document.addEventListener('click', function (event) {
        document.querySelectorAll('[data-dropdown]').forEach(function (dropdown) {
            if (!dropdown.contains(event.target)) {
                dropdown.querySelector('[data-dropdown-menu]').classList.add('hidden');
            }
        });
    });

    var btnFullscreen = document.getElementById('btn-fullscreen');

    if (btnFullscreen) {
        btnFullscreen.addEventListener('click', function () {
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else {
                document.documentElement.requestFullscreen();
            }
        });
    }
})();
</script>
<script src="/assets/js/app.js?v=<?= filemtime(FCPATH . 'assets/js/app.js') ?>"></script>
</body>
</html>
