document.addEventListener("DOMContentLoaded", function() {
    // --- CSRF Token for AJAX ---
    const csrfToken = document.getElementById('csrf-token').value;

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
            
            // Fetch data for the specific page when it's clicked
            if (pageId === 'callbacks') {
                fetchCallbackRequests();
            }

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
    
    // --- CALLBACK REQUESTS LOGIC ---
    const callbacksTableBody = document.getElementById('callbacks-table-body');

    async function fetchCallbackRequests() {
        callbacksTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">Loading requests...</td></tr>`;
        try {
            const response = await fetch('staff.php?fetch=callbacks');
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            const result = await response.json();

            if (result.success) {
                renderCallbackRequests(result.data);
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            callbacksTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger-color);">Failed to load requests.</td></tr>`;
        }
    }

    function renderCallbackRequests(data) {
        if (data.length === 0) {
            callbacksTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">No pending callback requests found.</td></tr>`;
            return;
        }

        callbacksTableBody.innerHTML = data.map(req => `
            <tr data-request-id="${req.id}">
                <td data-label="Name">${req.name}</td>
                <td data-label="Phone Number">${req.phone}</td>
                <td data-label="Requested At">${new Date(req.created_at).toLocaleString()}</td>
                <td data-label="Status">
                    ${req.is_contacted == 1 
                        ? '<span class="status completed">Contacted</span>' 
                        : '<span class="status pending">Pending</span>'
                    }
                </td>
                <td data-label="Action">
                    ${req.is_contacted == 0 
                        ? `<button class="action-btn mark-contacted-btn" data-id="${req.id}"><i class="fas fa-check"></i> Mark as Contacted</button>`
                        : `<button class="action-btn" disabled><i class="fas fa-check-double"></i> Done</button>`
                    }
                </td>
            </tr>
        `).join('');
    }

    callbacksTableBody.addEventListener('click', async function(e) {
        const button = e.target.closest('.mark-contacted-btn');
        if (button) {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            const requestId = button.dataset.id;
            const formData = new FormData();
            formData.append('action', 'markCallbackContacted');
            formData.append('id', requestId);
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('staff.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    // Refresh the list to show the updated status
                    fetchCallbackRequests();
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Update error:', error);
                alert('Failed to update request status. Please try again.');
                button.disabled = false;
                button.innerHTML = '<i class="fas fa-check"></i> Mark as Contacted';
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