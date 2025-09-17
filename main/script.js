(function () {
    'use strict';

    /**
     * Helper function to run scripts when the DOM is ready.
     * @param {function} fn The function to execute.
     */
    function onReady(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    /**
     * Sets up the interactive mobile navigation menu.
     */
    function setupMobileNav() {
        const hamburger = document.querySelector('.hamburger');
        const mobileNav = document.querySelector('.mobile-nav');
        const closeBtn = document.querySelector('.close-btn');
        const mobileNavLinks = mobileNav.querySelectorAll('.nav-links a');

        const toggleNav = () => mobileNav.classList.toggle('active');

        if (hamburger && mobileNav && closeBtn) {
            hamburger.addEventListener('click', toggleNav);
            closeBtn.addEventListener('click', toggleNav);

            // Close nav when a link is clicked (for single-page navigation)
            mobileNavLinks.forEach(link => {
                link.addEventListener('click', toggleNav);
            });
        }
    }

    /**
     * Sets up the FAQ accordion functionality.
     */
    function setupFaqAccordion() {
        const faqItems = document.querySelectorAll('.faq-item');

        faqItems.forEach(item => {
            const question = item.querySelector('.faq-question');
            question.addEventListener('click', () => {
                // Close other open items
                faqItems.forEach(otherItem => {
                    if (otherItem !== item && otherItem.classList.contains('active')) {
                        otherItem.classList.remove('active');
                    }
                });
                // Toggle the clicked item
                item.classList.toggle('active');
            });
        });
    }

    /**
     * Handles the phone number input validation for the contact form.
     */
    function setupPhoneValidation() {
        const phoneInput = document.getElementById('phone');

        if (phoneInput) {
            // Automatically add '+91' when the user starts typing
            phoneInput.addEventListener('input', function (e) {
                let value = e.target.value;

                // Remove any characters that are not digits or '+'
                value = value.replace(/[^\d+]/g, '');

                // Ensure '+91' is at the start
                if (value.length > 0 && !value.startsWith('+91')) {
                    // If user types numbers without '+91', add it for them
                    value = '+91' + value.replace(/\D/g, '');
                }
                
                // Re-apply the cleaned and formatted value
                e.target.value = value;
            });

            // Add '+91' when the user clicks into the empty field
            phoneInput.addEventListener('focus', function (e) {
                if (e.target.value === '') {
                    e.target.value = '+91';
                }
            });

            // Prevent the user from deleting the '+91' prefix
            phoneInput.addEventListener('keydown', function (e) {
                // If the user tries to backspace when only '+91' is left, prevent it
                if (e.key === 'Backspace' && e.target.value === '+91') {
                    e.preventDefault();
                }
            });
        }
    }

    /**
     * Initializes all GSAP animations for the page.
     */
    function setupGsapAnimations() {
        gsap.registerPlugin(ScrollTrigger);

        // Animate elements with class 'anim-fade-up'
        gsap.utils.toArray('.anim-fade-up').forEach(elem => {
            gsap.fromTo(elem, 
                { autoAlpha: 0, y: 50 },
                { 
                    autoAlpha: 1, 
                    y: 0,
                    duration: 0.8,
                    ease: 'power2.out',
                    scrollTrigger: {
                        trigger: elem,
                        start: 'top 85%',
                        toggleActions: 'play none none none'
                    },
                    // Use the CSS variable for delay if it exists
                    delay: parseFloat(getComputedStyle(elem).getPropertyValue('--delay')) || 0
                }
            );
        });
    }

    // Run all setup functions when the DOM is ready
    onReady(() => {
        setupMobileNav();
        setupFaqAccordion();
        setupPhoneValidation();
        setupGsapAnimations();
    });

})();