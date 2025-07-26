// user/script.js
document.addEventListener('DOMContentLoaded', () => {
    // --- Element Selections ---
    const navTriggers = document.querySelectorAll('.nav-link, .quick-actions-grid .action-box, .action-link, .notification-icon, .dropdown-item, .timeline-link');
    const pages = document.querySelectorAll('.main-content .page');
    const headerTitle = document.getElementById('header-title');
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const profileAvatar = document.getElementById('profile-avatar');
    const profileDropdown = document.getElementById('profile-dropdown');
    const avatarUploadInput = document.getElementById('avatar-upload');
    const profilePageAvatar = document.getElementById('profile-page-avatar');
    const themeToggle = document.getElementById('theme-checkbox');

    // --- Page Navigation Logic ---
    const showPage = (pageId) => {
        pages.forEach(page => page.classList.remove('active'));
        const targetPage = document.getElementById(`${pageId}-page`);
        if (targetPage) targetPage.classList.add('active');
        
        const activeLink = document.querySelector(`.nav-link[data-page="${pageId}"]`);
        if (activeLink && activeLink.querySelector('span')) {
            headerTitle.textContent = activeLink.querySelector('span').textContent;
        } else if (pageId === 'dashboard') {
            headerTitle.textContent = 'Dashboard';
        }
    };

    const updateActiveLink = (pageId) => {
        document.querySelectorAll('.sidebar-nav .nav-link').forEach(nav => nav.classList.remove('active'));
        const sidebarLink = document.querySelector(`.sidebar-nav .nav-link[data-page="${pageId}"]`);
        if (sidebarLink) sidebarLink.classList.add('active');
    };

    const navigateToPage = (pageId) => {
        if (!pageId) return;
        updateActiveLink(pageId);
        showPage(pageId);
        if (window.innerWidth <= 992 && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
        }
    };

    // --- Theme Toggling Logic ---
    const applyTheme = (theme) => {
        if (theme === 'dark') {
            document.body.classList.add('dark-mode');
            themeToggle.checked = true;
        } else {
            document.body.classList.remove('dark-mode');
            themeToggle.checked = false;
        }
    };

    themeToggle.addEventListener('change', () => {
        const selectedTheme = themeToggle.checked ? 'dark' : 'light';
        localStorage.setItem('theme', selectedTheme);
        applyTheme(selectedTheme);
    });

    // --- Event Listeners ---
    navTriggers.forEach(link => {
        link.addEventListener('click', (e) => {
            if (link.getAttribute('href') === 'logout.php') return;
            e.preventDefault();
            navigateToPage(link.dataset.page);
        });
    });
    
    if (profileAvatar) {
        profileAvatar.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
    }

    if (avatarUploadInput && profilePageAvatar) {
        avatarUploadInput.addEventListener('change', () => {
            const file = avatarUploadInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    profilePageAvatar.src = e.target.result;
                    if (profileAvatar) profileAvatar.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });
    }
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 992 && sidebar.classList.contains('show')) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        }
        if (profileDropdown && profileDropdown.classList.contains('show')) {
            if (!profileDropdown.contains(e.target) && e.target !== profileAvatar) {
                profileDropdown.classList.remove('show');
            }
        }
    });

    // --- Initial Page Load ---
    // Set initial theme
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (savedTheme) {
        applyTheme(savedTheme);
    } else if (prefersDark) {
        applyTheme('dark');
    } else {
        applyTheme('light');
    }

    // Set initial page
    navigateToPage('dashboard');
});