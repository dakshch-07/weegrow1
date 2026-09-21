export function initNavbar() {
    const navbar = document.querySelector('.navbar');
    const hamburger = document.getElementById('hamburger');
    const mobileOverlay = document.getElementById('mobileOverlay');
    const mobileOverlayClose = document.getElementById('mobileOverlayClose');

    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar?.classList.add('scrolled');
        } else {
            navbar?.classList.remove('scrolled');
        }
    }, { passive: true });

    function openMobileMenu() {
        if (!mobileOverlay) return;
        hamburger?.setAttribute('aria-expanded', 'true');
        hamburger?.classList.add('active');
        mobileOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        if (window.lenis) window.lenis.stop();

        const links = mobileOverlay.querySelectorAll('.nav-links li');
        links.forEach((link, idx) => {
            link.style.animationDelay = `${idx * 60}ms`;
        });
    }

    function closeMobileMenu() {
        if (!mobileOverlay) return;
        hamburger?.setAttribute('aria-expanded', 'false');
        hamburger?.classList.remove('active');
        mobileOverlay.classList.remove('active');
        document.body.style.overflow = '';
        if (window.lenis) window.lenis.start();
    }

    if (hamburger) {
        hamburger.addEventListener('click', (e) => {
            e.stopPropagation();
            if (mobileOverlay?.classList.contains('active')) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        });
    }

    if (mobileOverlayClose) {
        mobileOverlayClose.addEventListener('click', (e) => {
            e.stopPropagation();
            closeMobileMenu();
        });
    }

    if (mobileOverlay) {
        mobileOverlay.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                closeMobileMenu();
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && mobileOverlay.classList.contains('active')) {
                closeMobileMenu();
            }
        });
    }
}
