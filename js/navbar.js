export function initNavbar() {
    const navbar = document.querySelector('.navbar');
    const hamburger = document.getElementById('hamburger');
    const mobileOverlay = document.getElementById('mobileOverlay');

    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }, { passive: true });

    if (hamburger && mobileOverlay) {
        hamburger.addEventListener('click', () => {
            const isExpanded = hamburger.getAttribute('aria-expanded') === 'true';
            hamburger.setAttribute('aria-expanded', !isExpanded);
            hamburger.classList.toggle('active');
            mobileOverlay.classList.toggle('active');
            
            if (!isExpanded) {
                // Stagger in links
                const links = mobileOverlay.querySelectorAll('.nav-links li');
                links.forEach((link, idx) => {
                    link.style.animationDelay = `${idx * 60}ms`;
                });
            }
        });
    }
}
