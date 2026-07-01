import { initAnimations } from './animations.js';
import { initNavbar } from './navbar.js';

let animationsInitialized = false;

function triggerAnimations() {
    if (animationsInitialized) return;
    animationsInitialized = true;
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!prefersReducedMotion) {
        try { initAnimations(); } catch(e) { console.warn('animations init failed:', e); }
    }
}

// Cinematic GSAP Preloader Logic
const cinematicPreloader = document.getElementById('wg-cinematic-preloader');
if (cinematicPreloader && typeof gsap !== 'undefined') {
    document.body.style.overflow = 'hidden';
    
    const tl = gsap.timeline({
        onComplete: () => {
            cinematicPreloader.style.display = 'none';
            document.body.style.overflow = '';
            
            // Trigger hero animations after preloader finishes
            gsap.fromTo('.hero-content h1', 
                { y: 50, opacity: 0, filter: 'blur(10px)' }, 
                { y: 0, opacity: 1, filter: 'blur(0px)', duration: 1.2, ease: 'power4.out' }
            );
            gsap.fromTo('.hero-content p', 
                { y: 30, opacity: 0 }, 
                { y: 0, opacity: 1, duration: 1, delay: 0.2, ease: 'power3.out' }
            );
            
            // Initialize main scroll reveals after preloader finishes
            triggerAnimations();
        }
    });

    // 1. Progress line loads immediately
    tl.to('.cinematic-progress', { width: '100%', duration: 1.0, ease: 'power2.inOut' })
      // 2. Light bloom fades in
      .to('.cinematic-light-bloom', { opacity: 1, scale: 1.3, duration: 0.8, ease: 'power2.out' }, 0)
      // 3. Logo pops and fades in
      .to('.cinematic-logo-container', { opacity: 1, scale: 1, duration: 0.6, ease: 'power3.out' }, 0.2)
      // 4. Tagline slides up
      .to('.cinematic-tagline', { opacity: 1, y: 0, duration: 0.4, ease: 'power2.out' }, 0.4)
      // 5. Entire fullscreen container fades to transparent revealing website
      .to(cinematicPreloader, { opacity: 0, duration: 0.4, ease: 'power2.inOut', delay: 0.2 });
} else {
    // If no preloader or GSAP not present, trigger immediately
    if (cinematicPreloader) cinematicPreloader.style.display = 'none';
    triggerAnimations();
}

function initMain() {
    try { initNavbar(); } catch(e) { console.warn('navbar init failed:', e); }
    initCircles();

    // Highlight active navbar links (PRD BUG-004)
    try {
        const currentPath = window.location.pathname.split("/").pop();
        document.querySelectorAll('.nav-links li a').forEach(link => {
            const href = link.getAttribute('href');
            if (href === currentPath || (currentPath === "" && href === "index.html")) {
                link.classList.add('active');
            }
        });
    } catch(e) { console.warn('Active nav highlighting failed', e); }

    // Open default active FAQ item on load (PRD UX-007)
    document.querySelectorAll('.faq-item.active').forEach(item => {
        const answer = item.querySelector('.faq-answer');
        if(answer) answer.style.maxHeight = answer.scrollHeight + 'px';
    });

    // FAQ toggle
    document.querySelectorAll('.faq-question').forEach(btn => {
        btn.addEventListener('click', () => {
            const item = btn.parentElement;
            const answer = item.querySelector('.faq-answer');
            const isOpen = item.classList.contains('active');

            // Close all
            document.querySelectorAll('.faq-item.active').forEach(i => {
                i.classList.remove('active');
                i.querySelector('.faq-answer').style.maxHeight = null;
            });

            if (!isOpen) {
                item.classList.add('active');
                answer.style.maxHeight = answer.scrollHeight + 'px';
            }
        });
    });

    // FAQ window resize handler to dynamically recalculate active heights
    window.addEventListener('resize', () => {
        document.querySelectorAll('.faq-item.active').forEach(item => {
            const answer = item.querySelector('.faq-answer');
            if (answer) {
                answer.style.maxHeight = answer.scrollHeight + 'px';
            }
        });
    });

    // Back to top
    const backBtn = document.getElementById('backToTop');
    if (backBtn) {
        window.addEventListener('scroll', () => {
            backBtn.classList.toggle('visible', window.scrollY > 500);
        }, { passive: true });
        backBtn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
    }

    // Browser Mockup Carousel
    try { initMockupCarousel(); } catch(e) { console.warn('Mockup carousel init failed:', e); }

    // Premium Particle Canvas
    try { initParticles(); } catch(e) { console.warn('Particles init failed:', e); }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMain);
} else {
    initMain();
}

/* Generate organic floating circles (Jitter-style) */
function initCircles() {
    const container = document.getElementById('circlesVisual');
    if (!container) return;

    const count = 28;
    for (let i = 0; i < count; i++) {
        const c = document.createElement('div');
        c.className = 'jitter-circle';

        const size = Math.random() * 46 + 4;
        const x = Math.random() * 90 + 5;
        const y = Math.random() * 90 + 5;

        c.style.width  = size + 'px';
        c.style.height = size + 'px';
        c.style.left   = x + '%';
        c.style.top    = y + '%';

        const dx = (Math.random() - 0.5) * 30;
        const dy = (Math.random() - 0.5) * 30;
        c.style.setProperty('--dx', dx + 'px');
        c.style.setProperty('--dy', dy + 'px');

        c.style.animationDuration = (3 + Math.random() * 3) + 's';
        c.style.animationDelay = (Math.random() * -4) + 's';

        container.appendChild(c);
    }
}

let carouselInterval = null;
let carouselCurrentIndex = 0;

/**
 * Rotates active sites inside the hero browser mockup window
 */
function initMockupCarousel() {
    const mockup = document.getElementById('browserMockup');
    const slides = document.querySelectorAll('.mockup-slide');
    const urlDisplay = document.getElementById('browserMockupUrl');
    if (!slides.length || !urlDisplay || !mockup) return;

    const themes = {
        'dark-saas': '0 40px 80px rgba(0,0,0,0.5), 0 0 60px rgba(91, 95, 255, 0.08)',
        'urban-light': '0 40px 80px rgba(0,0,0,0.6), 0 0 60px rgba(0, 0, 0, 0.1)',
        'rose-gold': '0 40px 80px rgba(0,0,0,0.5), 0 0 60px rgba(255, 180, 180, 0.15)'
    };

    // Initial URL display transition styling
    urlDisplay.style.transition = 'all 0.3s ease';

    function startCarousel() {
        if (carouselInterval) return;
        carouselInterval = setInterval(() => {
            // Fade out current
            slides[carouselCurrentIndex].classList.remove('active');
            
            carouselCurrentIndex = (carouselCurrentIndex + 1) % slides.length;
            const nextSlide = slides[carouselCurrentIndex];
            const newUrl = nextSlide.getAttribute('data-url');
            const nextTheme = nextSlide.getAttribute('data-theme');
            
            // Animate URL update
            urlDisplay.style.opacity = 0;
            urlDisplay.style.transform = 'translateY(3px)';
            
            setTimeout(() => {
                urlDisplay.textContent = newUrl;
                urlDisplay.style.opacity = 1;
                urlDisplay.style.transform = 'translateY(0)';
                
                // Reveal next slide
                nextSlide.classList.add('active');
                
                // Dynamically adjust container glow
                if(themes[nextTheme]) {
                    mockup.style.boxShadow = themes[nextTheme];
                }
            }, 400);
        }, 5000);
    }

    function stopCarousel() {
        if (carouselInterval) {
            clearInterval(carouselInterval);
            carouselInterval = null;
        }
    }

    // Start carousel initially
    startCarousel();

    // Listen to visibilitychange on document to save resources when tab is hidden
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopCarousel();
        } else {
            startCarousel();
        }
    });
}

/**
 * Premium Interactive Particles Canvas
 */
function initParticles() {
    const canvas = document.getElementById('particles-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let animationFrameId = null;
    let isVisible = true;

    // Sizing
    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Particle class
    class Particle {
        constructor() {
            this.x = Math.random() * canvas.width;
            this.y = Math.random() * canvas.height;
            this.radius = Math.random() * 2 + 1;
            this.vx = (Math.random() - 0.5) * 0.4;
            this.vy = (Math.random() - 0.5) * 0.4;
            this.color = Math.random() > 0.5 ? 'rgba(0, 240, 255, 0.4)' : 'rgba(255, 0, 128, 0.4)'; // Cyan / Pink glow
        }

        update(mouse) {
            this.x += this.vx;
            this.y += this.vy;

            // Bounce off boundaries
            if (this.x < 0 || this.x > canvas.width) this.vx = -this.vx;
            if (this.y < 0 || this.y > canvas.height) this.vy = -this.vy;

            // Interactive mouse attraction/drag
            if (mouse.x !== null && mouse.y !== null) {
                const dx = mouse.x - this.x;
                const dy = mouse.y - this.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                if (dist < 120) {
                    this.x += dx * 0.01;
                    this.y += dy * 0.01;
                }
            }
        }

        draw() {
            ctx.beginPath();
            ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
            ctx.fillStyle = this.color;
            ctx.fill();
        }
    }

    // Generate particles
    const particles = [];
    const count = 60;
    for (let i = 0; i < count; i++) {
        particles.push(new Particle());
    }

    // Mouse tracking
    const mouse = { x: null, y: null };
    window.addEventListener('mousemove', (e) => {
        const rect = canvas.getBoundingClientRect();
        mouse.x = e.clientX - rect.left;
        mouse.y = e.clientY - rect.top;
    });

    window.addEventListener('mouseleave', () => {
        mouse.x = null;
        mouse.y = null;
    });

    // Render loop
    function animate() {
        if (!isVisible) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Draw connections
        for (let i = 0; i < particles.length; i++) {
            particles[i].update(mouse);
            particles[i].draw();

            for (let j = i + 1; j < particles.length; j++) {
                const dx = particles[i].x - particles[j].x;
                const dy = particles[i].y - particles[j].y;
                const dist = Math.sqrt(dx * dx + dy * dy);

                if (dist < 90) {
                    ctx.beginPath();
                    ctx.moveTo(particles[i].x, particles[i].y);
                    ctx.lineTo(particles[j].x, particles[j].y);
                    const alpha = (1 - dist / 90) * 0.15;
                    ctx.strokeStyle = `rgba(0, 240, 255, ${alpha})`;
                    ctx.lineWidth = 0.8;
                    ctx.stroke();
                }
            }
        }

        animationFrameId = requestAnimationFrame(animate);
    }

    // Pause rendering if reduced motion is preferred by user
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        return;
    }

    // IntersectionObserver to pause loop when out of view
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            isVisible = entry.isIntersecting;
            if (isVisible) {
                if (!animationFrameId) animate();
            } else {
                if (animationFrameId) {
                    cancelAnimationFrame(animationFrameId);
                    animationFrameId = null;
                }
            }
        });
    }, { threshold: 0.05 });

    observer.observe(canvas);
}
