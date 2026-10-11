/**
 * TUSHPA - Main JavaScript
 * Senior Developer Implementation
 */

// Animaciones (AOS). Si la librería no carga, se muestra todo sin animación (antes quedaba invisible)
if (window.AOS) {
    AOS.init({ duration: 1000, easing: 'ease-in-out', once: true, mirror: false });
} else {
    document.querySelectorAll('[data-aos]').forEach((el) => el.removeAttribute('data-aos'));
}

// Navbar Scroll Effect
window.addEventListener('scroll', function() {
    const navbar = document.getElementById('mainNav');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Smooth Scrolling for Anchor Links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            const offsetTop = target.offsetTop - 80;
            window.scrollTo({
                top: offsetTop,
                behavior: 'smooth'
            });
            
            // Close mobile menu if open
            const navbarCollapse = document.querySelector('.navbar-collapse');
            if (navbarCollapse.classList.contains('show')) {
                navbarCollapse.classList.remove('show');
            }
        }
    });
});

// Active Navigation Link on Scroll
const sections = document.querySelectorAll('section[id]');
const navLinks = document.querySelectorAll('.nav-link');

window.addEventListener('scroll', () => {
    let current = '';
    sections.forEach(section => {
        const sectionTop = section.offsetTop;
        const sectionHeight = section.clientHeight;
        if (scrollY >= (sectionTop - 200)) {
            current = section.getAttribute('id');
        }
    });

    navLinks.forEach(link => {
        link.classList.remove('active');
        if (link.getAttribute('href') === `#${current}`) {
            link.classList.add('active');
        }
    });
});

const escapar = (t) => { const d = document.createElement('div'); d.textContent = t || ''; return d.innerHTML; };

// Contact Form Submission
const contactForm = document.getElementById('contactForm');
if (contactForm) {
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const formMessage = document.getElementById('formMessage');
        const submitButton = this.querySelector('button[type="submit"]');
        
        // Disable submit button
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
        
        fetch(this.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Laravel responde los datos faltantes como { message, errors }
            if (!data.success && data.errors) data.message = Object.values(data.errors).flat().join(' ');
            if (data.success) {
                formMessage.innerHTML = `
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i>${escapar(data.message)}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
                contactForm.reset();
            } else {
                formMessage.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i>${escapar(data.message)}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                `;
            }
        })
        .catch(error => {
            formMessage.innerHTML = `
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>Ocurrió un error. Por favor, inténtalo de nuevo.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            console.error('Error:', error);
        })
        .finally(() => {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar Mensaje';
        });
    });
}

// Counter Animation for Stats
const animateCounter = (element) => {
    const target = parseInt(element.getAttribute('data-target'));
    const duration = 2000; // 2 seconds
    const increment = target / (duration / 16); // 60fps
    
    let current = 0;
    const updateCounter = () => {
        current += increment;
        if (current < target) {
            element.textContent = Math.floor(current) + '+';
            requestAnimationFrame(updateCounter);
        } else {
            element.textContent = target + '+';
        }
    };
    
    updateCounter();
};

// Intersection Observer for Counter Animation
const observerOptions = {
    threshold: 0.5
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const counter = entry.target.querySelector('.stat-number');
            if (counter && !counter.classList.contains('counted')) {
                animateCounter(counter);
                counter.classList.add('counted');
            }
        }
    });
}, observerOptions);

// Observe hero stats
const heroStats = document.querySelector('.hero-stats');
if (heroStats) {
    observer.observe(heroStats);
}

// Lazy Loading Images
document.addEventListener('DOMContentLoaded', () => {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.add('loaded');
                observer.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
});

// Add parallax effect to hero section
window.addEventListener('scroll', () => {
    const scrolled = window.pageYOffset;
    const heroSection = document.querySelector('.hero-section');
    if (heroSection) {
        heroSection.style.transform = `translateY(${scrolled * 0.5}px)`;
    }
});

console.log('%c TUSHPA.APP ', 'background: #2563eb; color: #fff; font-size: 20px; padding: 10px; border-radius: 5px;');
console.log('%c Desarrollado con ❤️ para impulsar tu negocio ', 'color: #64748b; font-size: 12px;');