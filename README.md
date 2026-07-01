# WeeGROW Agency Website

This repository contains the complete, production-ready website for WeeGROW, a digital growth agency based in Mumbai, India, founded by Harshad Chopra & Daksh Chopra.

## Project Overview
The WeeGROW website is designed to convert local Mumbai business owners (restaurants, salons, retail shops) into leads either through direct WhatsApp messages or via the integrated PHP backend contact form.

The entire site features a custom "Trust Navy" and "Accent Green" palette (Option A) that signals professionalism while keeping the promise of an affordable, budget-friendly digital agency realistic. 

## Technology Stack
- **Frontend:** HTML5, CSS3, Vanilla JavaScript (ES6 Modules)
- **Backend:** PHP 8+ (PDO), MySQL
- **Design:** Pure CSS grid/flexbox, Custom CSS variables, no external libraries or frameworks (no Tailwind/Bootstrap/jQuery).
- **Typography:** Plus Jakarta Sans (loaded via Google Fonts)

## Features Included
1. **Fully Responsive:** Mobile-first approach scaling perfectly up to 1440px desktop displays.
2. **Scroll Revels:** A pure IntersectionObserver-based `.reveal` engine for smooth, staggered fade-in animations as the user scrolls down.
3. **Interactive UI:** Smooth transitions, sticky navbar, mobile overlay hamburger menu, and a pure CSS infinite marquee.
4. **WhatsApp Integration:** Floating pulse button, CTA buttons configured to pre-fill messages in WhatsApp.
5. **Backend Contact Form:** Robust PHP backend that sanitizes input, validates data, inserts the lead into a MySQL database, sends an email notification, and returns a JSON response to the vanilla JS fetch handler on the frontend.
6. **SEO Optimized:** Meta tags (Open Graph, standard SEO), semantic HTML, and correct heading structures implemented across all 5 pages.
7. **Accessibility:** Focus rings, `aria-labels`, reduced motion media queries, semantic `<label>` bindings.

## Directory Structure
```
/webgrowth/
  index.html          - Home page (Hero, Services, Packages, Portfolio, Testimonials, FAQ, Contact)
  services.html       - Detailed breakdown of all services
  packages.html       - Pricing cards + comprehensive feature comparison table
  about.html          - Founders, Mission, Vision, Values, Team
  contact.html        - Dedicated full-page contact form
  README.md           - Documentation
  /css/
    style.css         - Core layout, color tokens, responsive components
    animations.css    - Keyframes, scroll reveals, pulse animations
  /js/
    main.js           - Navbar toggle, scroll-reveal observer, counter animation, smooth scrolling
    form.js           - Contact form validation and fetch API submission logic
  /php/
    db.php            - PDO database connection setup
    contact.php       - Endpoint to process form submissions
  /database/
    schema.sql        - SQL tables creation definitions
  /assets/
    (Directory created for future assets/images)
```

## Setup & Deployment Guide
1. **Database:** Create a database named `webgrowth_db` in MySQL. Run the `schema.sql` found in `/database/schema.sql` to initialize the `webgrowth_leads` table.
2. **PHP Configuration:** In `/php/db.php`, update the `DB_USER` and `DB_PASS` variables to reflect your production database credentials.
3. **Email Configuration:** Open `/php/contact.php` and update the `$to = "hello@weegrow.in";` line with the actual destination email. Note: Email relies on the basic `mail()` function, ensure your server (cPanel/Hostinger) allows it.
4. **WhatsApp Numbers:** Search the codebase for `91XXXXXXXXXX` (in `index.html`, `services.html`, `packages.html`, `about.html`, `contact.html` and header/footer templates) and swap it with the founders' actual WhatsApp number.

## Developer Notes
- There are no libraries. To modify the fade-in animation, edit the IntersectionObserver options inside `/js/main.js` or the CSS inside `/css/animations.css`.
- The floating WhatsApp button lives in the HTML body of all pages. To modify the SVG or color, locate `.floating-wa` in `style.css`.
- Form errors display as toast notifications powered by vanilla JS and CSS keyframes.

Built strictly according to project specifications for maximum conversion rates.
