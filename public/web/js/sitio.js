/* TUSHPA · web pública: menú, rubros, aparecer al bajar y formulario de contacto. Sin librerías extra. */
(function () {
    document.documentElement.classList.add('js');

    // Menú con sombra al bajar y opción activa según la sección visible
    const menu = document.querySelector('.menu');
    const enlaces = [...document.querySelectorAll('.menu .nav-link[href^="#"]')];
    const alBajar = () => {
        menu?.classList.toggle('con-sombra', window.scrollY > 10);
        let actual = '';
        document.querySelectorAll('section[id]').forEach((s) => { if (window.scrollY >= s.offsetTop - 120) actual = s.id; });
        enlaces.forEach((a) => a.classList.toggle('activo', a.getAttribute('href') === '#' + actual));
    };
    window.addEventListener('scroll', alBajar, { passive: true });
    alBajar();

    // En el celular, el menú se cierra al elegir una opción (no al abrir un submenú)
    document.querySelectorAll('.menu .navbar-collapse a:not([data-bs-toggle="dropdown"])').forEach((a) => a.addEventListener('click', () => {
        const abierto = document.querySelector('.menu .navbar-collapse.show');
        if (abierto && window.bootstrap) bootstrap.Collapse.getOrCreateInstance(abierto).hide();
    }));

    // Rubros: una pestaña por tipo de negocio
    document.querySelectorAll('[data-rubro]').forEach((boton) => boton.addEventListener('click', () => {
        document.querySelectorAll('[data-rubro]').forEach((b) => b.classList.toggle('activo', b === boton));
        document.querySelectorAll('.rubro-panel').forEach((p) => p.classList.toggle('activo', p.id === 'rubro-' + boton.dataset.rubro));
    }));

    // Aparecer al bajar
    const revelar = document.querySelectorAll('.revelar');
    if ('IntersectionObserver' in window) {
        const obs = new IntersectionObserver((entradas) => entradas.forEach((e) => {
            if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
        }), { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        revelar.forEach((el) => obs.observe(el));
    } else {
        revelar.forEach((el) => el.classList.add('visible'));
    }

    // Formulario de contacto (queda en el panel admin)
    const escapar = (t) => { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; };
    const form = document.getElementById('contactForm');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const boton = form.querySelector('button[type="submit"]');
            const aviso = document.getElementById('formMessage');
            boton.disabled = true;
            boton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enviando...';
            fetch(form.action, { method: 'POST', headers: { Accept: 'application/json' }, body: new FormData(form) })
                .then((r) => r.json())
                .then((d) => {
                    if (!d.success && d.errors) d.message = Object.values(d.errors).flat().join(' ');
                    aviso.innerHTML = `<div class="alert ${d.success ? 'alert-success' : 'alert-warning'} rounded-4 mb-0">${escapar(d.message)}</div>`;
                    if (d.success) form.reset();
                })
                .catch(() => { aviso.innerHTML = '<div class="alert alert-warning rounded-4 mb-0">No se pudo enviar. Escríbenos por WhatsApp.</div>'; })
                .finally(() => { boton.disabled = false; boton.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar mensaje'; });
        });
    }
})();
