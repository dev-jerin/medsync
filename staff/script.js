document.addEventListener("DOMContentLoaded", function() {
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
    const pages = document.querySelectorAll('.main-content .page');
    const mainHeaderTitle = document.getElementById('main-header-title');

    function toggleMenu() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    function closeMenu() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }

    // --- Sidebar Navigation ---
    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const pageId = link.getAttribute('data-page');
            const pageTitleLink = link.querySelector('i').nextSibling;
            const pageTitle = pageTitleLink ? pageTitleLink.textContent.trim() : 'Dashboard';
            mainHeaderTitle.textContent = pageTitle;
            
            pages.forEach(page => {
                page.classList.toggle('active', page.id === pageId + '-page');
            });

            navLinks.forEach(navLink => navLink.classList.remove('active'));
            link.classList.add('active');

            if (window.innerWidth <= 992) {
                closeMenu();
            }
        });
    });

    // --- Hamburger & Overlay Logic ---
    hamburgerBtn.addEventListener('click', (e) => { e.stopPropagation(); toggleMenu(); });
    overlay.addEventListener('click', closeMenu);
    
    // --- Theme Toggle Logic ---
    const themeToggle = document.getElementById('theme-toggle-checkbox');
    const currentTheme = localStorage.getItem('theme');
    if (currentTheme) {
        document.body.classList.add(currentTheme);
        if (currentTheme === 'dark-theme') {
            themeToggle.checked = true;
        }
    }
    themeToggle.addEventListener('change', function() {
        if (this.checked) {
            document.body.classList.add('dark-theme');
            localStorage.setItem('theme', 'dark-theme');
        } else {
            document.body.classList.remove('dark-theme');
            localStorage.setItem('theme', 'light-theme');
        }
    });

    // --- Notification Dropdown Logic ---
    const notificationBell = document.getElementById('notification-bell');
    const notificationPanel = document.getElementById('notification-panel');
    const viewAllNotificationsLink = document.getElementById('view-all-notifications-link');

    if (notificationBell) {
        notificationBell.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationPanel.classList.toggle('show');
        });
    }

    if(viewAllNotificationsLink) {
        viewAllNotificationsLink.addEventListener('click', function(e){
            e.preventDefault();
            const notificationsSidebarLink = document.querySelector('.nav-link[data-page="notifications"]');
            if(notificationsSidebarLink){
                notificationsSidebarLink.click();
            }
            notificationPanel.classList.remove('show');
        });
    }

    // --- User Profile Widget Navigation ---
    const userProfileWidget = document.getElementById('user-profile-widget');
    if (userProfileWidget) {
        userProfileWidget.addEventListener('click', () => {
            const profileSidebarLink = document.querySelector('.nav-link[data-page="profile"]');
            if(profileSidebarLink){
                profileSidebarLink.click();
            }
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (notificationPanel && !notificationBell.contains(e.target) && !notificationPanel.contains(e.target)) {
            notificationPanel.classList.remove('show');
        }
        if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                closeMenu();
            }
        }
    });
    
    // --- Modal Logic ---
    function setupModal(openBtnId, modalId) {
        const modal = document.getElementById(modalId);
        const openBtn = document.getElementById(openBtnId);
        const closeBtns = document.querySelectorAll(`.modal-close-btn[data-modal-id="${modalId}"]`);

        if (openBtn) {
            openBtn.addEventListener('click', () => {
                if (modal) modal.classList.add('show');
            });
        }

        closeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                 if (modal) modal.classList.remove('show');
            });
        });

        if (modal) {
             modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        }
    }

    setupModal('add-new-bed-btn', 'add-bed-modal-overlay');
    setupModal('create-invoice-btn', 'create-invoice-modal-overlay');
    
    // --- Messenger Page Logic ---
    const messengerPage = document.getElementById('messenger-page');
    if (messengerPage) {
        const conversationItems = messengerPage.querySelectorAll('.conversation-item');
        const chatHeader = messengerPage.querySelector('#chat-with-user');
        const messageForm = messengerPage.querySelector('#message-form');
        const messageInput = messengerPage.querySelector('#message-input');
        const messagesContainer = messengerPage.querySelector('#chat-messages-container');

        conversationItems.forEach(item => {
            item.addEventListener('click', () => {
                messengerPage.querySelector('.conversation-item.active').classList.remove('active');
                item.classList.add('active');
                chatHeader.textContent = item.dataset.userName;
            });
        });

        messageForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const messageText = messageInput.value.trim();
            if (messageText) {
                const messageElement = document.createElement('div');
                messageElement.classList.add('message', 'sent');
                messageElement.innerHTML = `
                    <div class="message-content">
                        <p>${messageText}</p>
                        <span class="message-timestamp">Now</span>
                    </div>`;
                messagesContainer.appendChild(messageElement);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
                messageInput.value = '';
            }
        });
    }

    // --- All Other Page Logic ---
    // (Filtering logic for beds, inventory, users, etc. remains unchanged)

    // Profile Page Logic
    const profilePage = document.getElementById('profile-page');
    if (profilePage) {
        const profileTabLinks = profilePage.querySelectorAll('.profile-tab-link');
        const profileTabContents = profilePage.querySelectorAll('.profile-tab-content');

        profileTabLinks.forEach(link => {
            link.addEventListener('click', () => {
                const tabId = link.getAttribute('data-tab');
                profileTabLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                profileTabContents.forEach(content => {
                    content.classList.toggle('active', content.id === tabId + '-tab');
                });
            });
        });

        const passwordToggles = profilePage.querySelectorAll('.toggle-password');
        passwordToggles.forEach(toggle => {
            toggle.addEventListener('click', () => {
                const passwordInput = toggle.previousElementSibling;
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                toggle.classList.toggle('fa-eye');
                toggle.classList.toggle('fa-eye-slash');
            });
        });
    }
});