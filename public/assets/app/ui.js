/*
 | The app shell's behaviour: sidebar, mobile drawer, dark mode, fullscreen.
 |
 | Replaces Velzon's own app.js and layout.js. Those are written for the theme's demo pages: they
 | bind to a customizer panel, notification dropdown and layout switcher this app does not have,
 | throw on each missing element, and keep the theme in sessionStorage with a reload-on-mismatch
 | check. This file does the four things the app actually uses, and the theme preference itself
 | is applied by the inline script in the <head>, before first paint.
 |
 | Sidebar sizes are Velzon's: "lg" is the full menu (and the phone drawer), "sm" the icon rail.
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var body = document.body;
    var PHONE = 767;
    var TABLET = 1024;

    function store(key, value) {
        try { localStorage.setItem(key, value); } catch (e) { /* storage blocked */ }
    }

    function stored(key) {
        try { return localStorage.getItem(key); } catch (e) { return null; }
    }

    /* ---- Sidebar ----------------------------------------------------------------------- */

    function applyResponsiveSize() {
        var width = window.innerWidth;

        if (width <= PHONE) {
            // Off-canvas drawer: always full size; visibility is the body class.
            root.setAttribute('data-sidebar-size', 'lg');
        } else {
            body.classList.remove('vertical-sidebar-enable');
            root.setAttribute('data-sidebar-size', width <= TABLET ? 'sm' : (stored('zi-sidebar-size') || 'lg'));
        }
    }

    var hamburger = document.getElementById('topnav-hamburger-icon');

    if (hamburger) {
        hamburger.addEventListener('click', function () {
            if (window.innerWidth <= PHONE) {
                body.classList.toggle('vertical-sidebar-enable');
            } else {
                var next = root.getAttribute('data-sidebar-size') === 'lg' ? 'sm' : 'lg';
                root.setAttribute('data-sidebar-size', next);
                // Only the desktop choice is a preference; the tablet rail is responsive.
                if (window.innerWidth > TABLET) { store('zi-sidebar-size', next); }
            }
            hamburger.classList.toggle('open');
        });
    }

    var overlay = document.querySelector('.vertical-overlay');

    if (overlay) {
        overlay.addEventListener('click', function () {
            body.classList.remove('vertical-sidebar-enable');
            if (hamburger) { hamburger.classList.remove('open'); }
        });
    }

    window.addEventListener('resize', applyResponsiveSize, { passive: true });
    applyResponsiveSize();

    /* ---- Dark mode --------------------------------------------------------------------- */

    var icon = function (theme) {
        document.querySelectorAll('.light-dark-mode i').forEach(function (i) {
            i.className = (theme === 'dark' ? 'bx bx-sun' : 'bx bx-moon') + ' fs-22';
        });
    };

    icon(root.getAttribute('data-bs-theme'));

    document.querySelectorAll('.light-dark-mode').forEach(function (button) {
        button.addEventListener('click', function () {
            var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-bs-theme', next);
            // Velzon colours the sidebar independently; keep it in step or it stays white.
            root.setAttribute('data-sidebar', next);
            store('zi-theme', next);
            icon(next);
        });
    });

    /* ---- Fullscreen -------------------------------------------------------------------- */

    document.querySelectorAll('[data-toggle="fullscreen"]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen && document.documentElement.requestFullscreen();
            } else {
                document.exitFullscreen && document.exitFullscreen();
            }
        });
    });

    /* ---- Velzon's ripple on buttons ---------------------------------------------------- */
    if (window.Waves) { window.Waves.init(); }
})();
