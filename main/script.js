document.addEventListener("DOMContentLoaded", function() {
    // --- Header Scroll Effect ---
    const header = document.getElementById('header');
    if (header) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }

    // --- Mobile Navigation Logic ---
    const hamburger = document.querySelector('.hamburger');
    const mobileNav = document.querySelector('.mobile-nav');
    if (hamburger && mobileNav) {
        const closeBtn = document.querySelector('.mobile-nav .close-btn');
        const mobileNavLinks = document.querySelectorAll('.mobile-nav a');
        const closeMobileNav = () => mobileNav.classList.remove('active');
        hamburger.addEventListener('click', () => mobileNav.classList.add('active'));
        closeBtn?.addEventListener('click', closeMobileNav);
        mobileNavLinks.forEach(link => link.addEventListener('click', closeMobileNav));
    }
    
    // --- Landing Page Scripts ---
    const faqQuestions = document.querySelectorAll('.faq-question');
    if (faqQuestions.length > 0) {
        handleFaqAccordion(faqQuestions);
    }

    // Check if GSAP is loaded before using it
    if (typeof gsap !== 'undefined') {
        gsap.registerPlugin(ScrollTrigger);
        
        const fadeUpElements = document.querySelectorAll('.anim-fade-up');
        if (fadeUpElements.length > 0) {
            handleGsapFadeUp(fadeUpElements);
        }

        const counters = document.querySelectorAll('.counter');
        if (counters.length > 0) {
            handleAnimatedCounters(counters);
        }
    }
});

/**
 * Handles FAQ accordion logic.
 */
function handleFaqAccordion(faqQuestions) {
    faqQuestions.forEach(question => {
        question.addEventListener('click', () => {
            const answer = question.nextElementSibling;
            const isActive = question.classList.contains('active');

            // Close all other active questions
            document.querySelectorAll('.faq-question.active').forEach(activeQ => {
                if (activeQ !== question) {
                    activeQ.classList.remove('active');
                    activeQ.nextElementSibling.style.maxHeight = null;
                }
            });

            // Toggle current question
            question.classList.toggle('active');
            if (question.classList.contains('active')) {
                answer.style.maxHeight = answer.scrollHeight + "px";
            } else {
                answer.style.maxHeight = null;
            }
        });
    });
}

/**
 * Handles GSAP fade-up animations.
 */
function handleGsapFadeUp(elements) {
    elements.forEach(el => {
        gsap.from(el, {
            scrollTrigger: { trigger: el, start: "top 90%", toggleActions: "play none none none" },
            y: 50, opacity: 0, duration: 0.8, ease: "power3.out",
            delay: parseFloat(el.style.getPropertyValue('--delay')) || 0
        });
    });
}

/**
 * Handles animated counters.
 */
function handleAnimatedCounters(counters) {
    counters.forEach(counter => {
        const target = +counter.getAttribute('data-target');
        const suffix = counter.getAttribute('data-suffix') || '';
        ScrollTrigger.create({
            trigger: counter, start: "top 90%", once: true,
            onEnter: () => {
                let obj = { val: 0 };
                gsap.to(obj, {
                    val: target, duration: 2, ease: "power2.out",
                    onUpdate: () => { counter.textContent = Math.round(obj.val).toLocaleString(); },
                    onComplete: () => { counter.textContent = Math.round(target).toLocaleString() + suffix; }
                });
            }
        });
    });
}