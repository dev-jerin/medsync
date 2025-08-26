document.addEventListener("DOMContentLoaded", function() {
    // --- CSRF Token for AJAX ---
    const csrfToken = document.getElementById('csrf-token').value;
    const currentUserId = parseInt(document.getElementById('current-user-id').value, 10);

    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
    const pages = document.querySelectorAll('.main-content .page');
    const mainHeaderTitle = document.getElementById('main-header-title');

    // --- Sidebar Navigation & Menu Toggling ---
    function toggleMenu() {
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
    }

    function closeMenu() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    }

    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const pageId = link.getAttribute('data-page');
            
            // Fetch data for specific pages when their link is clicked
            if (pageId === 'callbacks') fetchCallbackRequests();
            if (pageId === 'messenger') initializeMessenger();
            if (pageId === 'inventory') initializeInventory();
            if (pageId === 'user-management') fetchUsers();
            if (pageId === 'bed-management') initializeBedManagement();
            if (pageId === 'admissions') initializeAdmissions();
            if (pageId === 'labs') initializeLabs();
            if (pageId === 'notifications') fetchAndRenderNotifications(document.querySelector('#notifications-page .notification-list-container'));
            if (pageId === 'discharge') initializeDischarge();
            if (pageId === 'billing') initializeBilling();


            const pageTitle = link.querySelector('span') ? link.querySelector('span').textContent.trim() : link.textContent.trim().replace(link.querySelector('i').textContent, '').trim();
            mainHeaderTitle.textContent = pageTitle;
            
            pages.forEach(page => page.classList.remove('active'));
            document.getElementById(pageId + '-page').classList.add('active');

            navLinks.forEach(navLink => navLink.classList.remove('active'));
            link.classList.add('active');

            if (window.innerWidth <= 992) closeMenu();
        });
    });

    hamburgerBtn.addEventListener('click', (e) => { e.stopPropagation(); toggleMenu(); });
    overlay.addEventListener('click', closeMenu);
    
    // --- Theme Toggle, and Profile Widget Logic ---
    const themeToggle = document.getElementById('theme-toggle-checkbox');
    themeToggle.addEventListener('change', function() {
        document.body.classList.toggle('dark-theme', this.checked);
        localStorage.setItem('theme', this.checked ? 'dark-theme' : 'light-theme');
    });
    if (localStorage.getItem('theme') === 'dark-theme') {
        themeToggle.checked = true;
        document.body.classList.add('dark-theme');
    }

    // --- Helper function for showing alerts/messages ---
    function showFeedback(form, message, isSuccess) {
        let feedbackEl = form.querySelector('.form-feedback');
        if (!feedbackEl) {
            feedbackEl = document.createElement('div');
            feedbackEl.className = 'form-feedback';
            form.prepend(feedbackEl);
        }
        feedbackEl.textContent = message;
        feedbackEl.style.color = isSuccess ? 'var(--secondary-color)' : 'var(--danger-color)';
        feedbackEl.style.marginBottom = '1rem';
        setTimeout(() => { feedbackEl.textContent = ''; feedbackEl.style.marginBottom = '0'; }, 5000);
    }

    // --- Confirmation Dialog Helper ---
    const confirmDialog = document.getElementById('confirmation-dialog');
    const showConfirmation = (title, message) => {
        document.getElementById('confirm-title').textContent = title;
        document.getElementById('confirm-message').textContent = message;
        confirmDialog.classList.add('show');
        return new Promise((resolve) => {
            document.getElementById('confirm-ok-btn').onclick = () => {
                confirmDialog.classList.remove('show');
                resolve(true);
            };
            document.getElementById('confirm-cancel-btn').onclick = () => {
                confirmDialog.classList.remove('show');
                resolve(false);
            };
            document.getElementById('confirm-close-btn').onclick = () => {
                confirmDialog.classList.remove('show');
                resolve(false);
            };
        });
    };
    // --- NOTIFICATION WIDGET LOGIC ---
    const notificationBell = document.getElementById('notification-bell');
    const notificationBadge = document.getElementById('notification-badge');
    const notificationPanel = document.getElementById('notification-panel');
    const notificationDropdownBody = notificationPanel.querySelector('.dropdown-body');
    const viewAllNotificationsLink = document.getElementById('view-all-notifications-link');

    const fetchUnreadNotificationCount = async () => {
        try {
            const response = await fetch('staff.php?fetch=unread_notification_count');
            const result = await response.json();
            if (result.success && result.data.count > 0) {
                notificationBadge.textContent = result.data.count > 9 ? '9+' : result.data.count;
                notificationBadge.style.display = 'flex';
            } else {
                notificationBadge.style.display = 'none';
            }
        } catch (error) {
            console.error('Failed to fetch notification count:', error);
        }
    };
    
    function timeSince(date) {
        const seconds = Math.floor((new Date() - date) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " minutes ago";
        return Math.floor(seconds) + " seconds ago";
    }


    const renderNotifications = (notifications, container) => {
        if (!container) return;
        if (notifications.length === 0) {
            container.innerHTML = '<p class="no-items-message">No notifications found.</p>';
            return;
        }

        container.innerHTML = notifications.map(n => {
            const isReadClass = n.is_read == 1 ? 'read' : 'unread';
            let sender = n.sender_name || 'System';
            // Capitalize the first letter of the role
            let senderRole = n.sender_role ? n.sender_role.charAt(0).toUpperCase() + n.sender_role.slice(1) : '';

            // Combine sender and role
            const senderDisplay = senderRole ? `${sender} (${senderRole})` : sender;
            
            const iconClass = 'fas fa-info-circle item-icon announcement'; // Default icon

            return `
                <div class="notification-list-item ${isReadClass}" data-id="${n.id}">
                    <div class="item-icon-wrapper"><i class="${iconClass}"></i></div>
                    <div class="item-content">
                        <p><strong>${senderDisplay}:</strong> ${n.message}</p>
                        <small>${timeSince(new Date(n.created_at))}</small>
                    </div>
                </div>
            `;
        }).join('');
    };
    
    const fetchAndRenderNotifications = async (container, limit = 50) => {
        if (!container) return;
        container.innerHTML = '<p class="no-items-message">Loading...</p>';
        try {
            const response = await fetch(`staff.php?fetch=notifications&limit=${limit}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderNotifications(result.data, container);
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
            container.innerHTML = `<p class="no-items-message" style="color: var(--danger-color)">Failed to load notifications.</p>`;
        }
    };
    
    notificationBell.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationPanel.classList.toggle('show');
        if (notificationPanel.classList.contains('show')) {
            fetchAndRenderNotifications(notificationDropdownBody, 5);
        }
    });

    document.addEventListener('click', (e) => {
        if (!notificationPanel.contains(e.target) && !notificationBell.contains(e.target)) {
            notificationPanel.classList.remove('show');
        }
    });

    viewAllNotificationsLink.addEventListener('click', (e) => {
        e.preventDefault();
        notificationPanel.classList.remove('show');
        document.querySelector('.nav-link[data-page="notifications"]').click();
    });

    const markAllReadBtn = document.getElementById('mark-all-read-btn');
    if(markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async () => {
             const formData = new FormData();
             formData.append('action', 'markNotificationsRead');
             formData.append('csrf_token', csrfToken);
             try {
                const response = await fetch('staff.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(!result.success) throw new Error(result.message);

                await fetchUnreadNotificationCount();
                fetchAndRenderNotifications(document.querySelector('#notifications-page .notification-list-container'));
             } catch(error) {
                 alert('Error: ' + error.message);
             }
        });
    }

    // Initial and periodic fetch for notification count
    fetchUnreadNotificationCount();
    setInterval(fetchUnreadNotificationCount, 60000); // every 60 seconds
    
    // --- CALLBACK REQUESTS LOGIC ---
    const callbacksTableBody = document.getElementById('callbacks-table-body');

    async function fetchCallbackRequests() {
        if (!callbacksTableBody) return;
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
        if (!callbacksTableBody) return;
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

    if (callbacksTableBody) {
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
                    const response = await fetch('staff.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message);
                    fetchCallbackRequests();
                } catch (error) {
                    alert('Failed to update status: ' + error.message);
                    button.disabled = false;
                    button.innerHTML = '<i class="fas fa-check"></i> Mark as Contacted';
                }
            }
        });
    }

    // --- MESSENGER LOGIC ---
    const messengerPage = document.getElementById('messenger-page');
    let messengerInitialized = false;
    let activeConversationId = null;
    let activeReceiverId = null; 
    let searchDebounceTimer;
    
    function initializeMessenger() {
        if (messengerInitialized || !messengerPage) return;
        
        fetchAndRenderConversations();
        
        const searchInput = document.getElementById('messenger-user-search');
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => handleSearch(searchInput.value), 300);
        });

        const listContainer = document.getElementById('conversation-list-items');
        listContainer.addEventListener('click', handleListItemClick);
        
        const messageForm = document.getElementById('message-form');
        messageForm.addEventListener('submit', handleSendMessage);
        
        messengerInitialized = true;
    }

    async function handleSearch(query) {
        const listContainer = document.getElementById('conversation-list-items');
        query = query.trim();
        if (query.length < 2) {
            await fetchAndRenderConversations();
            return;
        }
        
        listContainer.innerHTML = `<p class="no-items-message">Searching...</p>`;
        
        const formData = new FormData();
        formData.append('action', 'searchUsers');
        formData.append('query', query);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('staff.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            if(result.data.length === 0) {
                 listContainer.innerHTML = `<p class="no-items-message">No users found.</p>`;
                 return;
            }
            listContainer.innerHTML = result.data.map(renderSearchResultItem).join('');
        } catch (error) {
            console.error("Search error:", error);
            listContainer.innerHTML = `<p class="no-items-message" style="color: var(--danger-color)">Search failed.</p>`;
        }
    }
    
    function renderSearchResultItem(user) {
        const avatarUrl = user.avatar_url;
        return `
            <div class="search-result-item" data-user-id="${user.id}" data-user-name="${user.name}" data-user-avatar="${avatarUrl}" data-user-display-id="${user.role}">
                <img src="${avatarUrl}" alt="${user.name}" class="user-avatar">
                <div class="conversation-details">
                    <span class="user-name">${user.name}</span>
                    <span class="last-message">${user.role} - ${user.display_user_id}</span>
                </div>
            </div>
        `;
    }

    async function fetchAndRenderConversations() {
        const listContainer = document.getElementById('conversation-list-items');
        listContainer.innerHTML = `<p class="no-items-message">Loading conversations...</p>`;

        try {
            const response = await fetch('staff.php?fetch=conversations');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            if (result.data.length === 0) {
                listContainer.innerHTML = `<p class="no-items-message">No conversations yet. Search for a user to start chatting.</p>`;
                return;
            }
            listContainer.innerHTML = result.data.map(renderConversationItem).join('');

        } catch (error) {
            console.error("Failed to fetch conversations:", error);
            listContainer.innerHTML = `<p class="no-items-message" style="color: var(--danger-color)">Could not load conversations.</p>`;
        }
    }
    
    function formatMessageTime(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function renderConversationItem(conv) {
        const avatarUrl = conv.other_user_avatar_url;
        return `
            <div class="conversation-item ${conv.conversation_id === activeConversationId ? 'active' : ''}" data-conversation-id="${conv.conversation_id}" data-user-id="${conv.other_user_id}" data-user-name="${conv.other_user_name}" data-user-avatar="${avatarUrl}" data-user-display-id="${conv.other_user_role}">
                <img src="${avatarUrl}" alt="${conv.other_user_name}" class="user-avatar">
                <div class="conversation-details">
                    <span class="user-name">${conv.other_user_name}</span>
                    <span class="last-message">${conv.last_message || 'No messages yet'}</span>
                </div>
                <div class="conversation-meta">
                    <span class="message-time">${formatMessageTime(conv.last_message_time)}</span>
                    ${conv.unread_count > 0 ? `<div class="unread-indicator">${conv.unread_count}</div>` : ''}
                </div>
            </div>`;
    }

    function handleListItemClick(e) {
        const item = e.target.closest('.conversation-item, .search-result-item');
        if (!item) return;

        const searchInput = document.getElementById('messenger-user-search');
        if(searchInput.value) {
            searchInput.value = '';
            fetchAndRenderConversations();
        }
        
        const conversationId = item.dataset.conversationId ? parseInt(item.dataset.conversationId, 10) : null;
        const userId = parseInt(item.dataset.userId, 10);
        const userName = item.dataset.userName;
        const userAvatar = item.dataset.userAvatar;
        const userDisplayId = item.dataset.userDisplayId;
        
        selectConversation(conversationId, userId, userName, userAvatar, userDisplayId);
    }
    
    function selectConversation(conversationId, userId, userName, userAvatar, userDisplayId) {
        activeConversationId = conversationId;
        activeReceiverId = userId;

        document.querySelectorAll('#conversation-list-items .conversation-item').forEach(el => {
            el.classList.toggle('active', parseInt(el.dataset.conversationId, 10) === conversationId);
        });
        
        document.getElementById('no-chat-placeholder').style.display = 'none';
        const chatWindow = document.getElementById('chat-window');
        chatWindow.style.display = 'flex';
        
        document.getElementById('chat-header-avatar').src = userAvatar;
        document.getElementById('chat-with-user-name').textContent = userName;
        document.getElementById('chat-with-user-id').textContent = userDisplayId;
        
        const messageInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('message-form').querySelector('.send-btn');
        messageInput.disabled = false;
        sendBtn.disabled = false;
        
        if(conversationId) {
            fetchAndRenderMessages(conversationId);
        } else {
            document.getElementById('chat-messages-container').innerHTML = `<p class="no-items-message">Send a message to start the conversation with ${userName}.</p>`;
        }
    }

    function formatDateSeparator(dateString) {
        const date = new Date(dateString);
        const today = new Date();
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);

        const options = { month: 'long', day: 'numeric', year: 'numeric' };

        if (date.toDateString() === today.toDateString()) return 'Today';
        if (date.toDateString() === yesterday.toDateString()) return 'Yesterday';
        return date.toLocaleDateString('en-US', options);
    }

    async function fetchAndRenderMessages(conversationId) {
        const container = document.getElementById('chat-messages-container');
        container.innerHTML = '<p class="no-items-message">Loading messages...</p>';
        try {
            const response = await fetch(`staff.php?fetch=messages&conversation_id=${conversationId}`);
            const result = await response.json();
            if(!result.success) throw new Error(result.message);
            
            let messagesHtml = '';
            let lastMessageDateStr = null;

            if (result.data.length > 0) {
                result.data.forEach(message => {
                    const currentMessageDateStr = new Date(message.created_at).toDateString();
                    if (currentMessageDateStr !== lastMessageDateStr) {
                        messagesHtml += `<div class="message-date-separator">${formatDateSeparator(message.created_at)}</div>`;
                        lastMessageDateStr = currentMessageDateStr;
                    }
                    messagesHtml += renderMessageItem(message);
                });
                 container.dataset.lastDate = lastMessageDateStr;
            } else {
                 messagesHtml = `<p class="no-items-message">No messages yet. Say hello!</p>`;
                 delete container.dataset.lastDate;
            }
            
            container.innerHTML = messagesHtml;
            container.scrollTop = container.scrollHeight;

        } catch(error) {
            console.error("Failed to fetch messages:", error);
            container.innerHTML = `<p class="no-items-message" style="color: var(--danger-color)">Could not load messages.</p>`;
        }
    }
    
    function renderMessageItem(message) {
        const sentOrReceived = message.sender_id === currentUserId ? 'sent' : 'received';
        return `
            <div class="message ${sentOrReceived}">
                <div class="message-content">
                    <p>${message.message_text}</p>
                </div>
                <span class="message-timestamp">${formatMessageTime(message.created_at)}</span>
            </div>
        `;
    }

    async function handleSendMessage(e) {
        e.preventDefault();
        const form = e.target;
        const input = form.querySelector('#message-input');
        const button = form.querySelector('.send-btn');
        const messageText = input.value.trim();

        if (!messageText || !activeReceiverId) return;

        input.disabled = true;
        button.disabled = true;

        const formData = new FormData();
        formData.append('action', 'sendMessage');
        formData.append('receiver_id', activeReceiverId);
        formData.append('message_text', messageText);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('staff.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            input.value = '';
            
            if (!activeConversationId) {
                activeConversationId = result.data.conversation_id;
                await fetchAndRenderConversations();
                await fetchAndRenderMessages(activeConversationId);
            } else {
                const container = document.getElementById('chat-messages-container');
                if (container.querySelector('.no-items-message')) container.innerHTML = '';
                
                const currentDateStr = new Date(result.data.created_at).toDateString();
                if(currentDateStr !== container.dataset.lastDate) {
                     container.insertAdjacentHTML('beforeend', `<div class="message-date-separator">${formatDateSeparator(result.data.created_at)}</div>`);
                     container.dataset.lastDate = currentDateStr;
                }

                container.insertAdjacentHTML('beforeend', renderMessageItem(result.data));
                container.scrollTop = container.scrollHeight;
                fetchAndRenderConversations();
            }

        } catch (error) {
            console.error("Send message failed:", error);
            alert("Could not send message. Please try again.");
        } finally {
            input.disabled = false;
            button.disabled = false;
            input.focus();
        }
    }


    // --- PROFILE SETTINGS PAGE LOGIC ---
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

        const auditLogTab = profilePage.querySelector('.profile-tab-link[data-tab="audit-log"]');
        if (auditLogTab) {
            auditLogTab.addEventListener('click', fetchAuditLog, { once: true });
        }

        async function fetchAuditLog() {
            const tableBody = document.getElementById('audit-log-table')?.querySelector('tbody');
            if (!tableBody) return;
            tableBody.innerHTML = `<tr><td colspan="3" style="text-align: center;">Loading activity...</td></tr>`;

            try {
                const response = await fetch('staff.php?fetch=audit_log');
                const result = await response.json();

                if (result.success) {
                    renderAuditLog(result.data, tableBody);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                console.error('Fetch error:', error);
                tableBody.innerHTML = `<tr><td colspan="3" style="text-align: center; color: var(--danger-color);">Failed to load activity log.</td></tr>`;
            }
        }

        function renderAuditLog(data, tableBody) {
            if (data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="3" style="text-align: center;">No recent activity found.</td></tr>`;
                return;
            }

            tableBody.innerHTML = data.map(log => `
                <tr>
                    <td data-label="Date & Time">${new Date(log.created_at).toLocaleString()}</td>
                    <td data-label="Action"><span class="log-action-update">${log.action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span></td>
                    <td data-label="Details">${log.details}</td>
                </tr>
            `).join('');
        }

        const personalInfoForm = document.getElementById('personal-info-form');
        personalInfoForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'updatePersonalInfo');
            formData.append('csrf_token', csrfToken);
            
            const saveButton = this.querySelector('button[type="submit"]');
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            try {
                const response = await fetch('staff.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
                showFeedback(this, result.message, true);
                document.querySelector('.user-profile-widget strong').textContent = formData.get('name');
            } catch (error) {
                showFeedback(this, error.message, false);
            } finally {
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            }
        });

        const securityForm = document.getElementById('security-form');
        securityForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'updatePassword');
            formData.append('csrf_token', csrfToken);
            
            const saveButton = this.querySelector('button[type="submit"]');
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';

            try {
                const response = await fetch('staff.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
                showFeedback(this, result.message, true);
                this.reset();
            } catch (error) {
                showFeedback(this, error.message, false);
            } finally {
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fas fa-key"></i> Update Password';
            }
        });
        
        document.getElementById('profile-picture-upload').addEventListener('change', async function() {
            if (this.files.length === 0) return;
            const formData = new FormData();
            formData.append('profile_picture', this.files[0]);
            formData.append('action', 'updateProfilePicture');
            formData.append('csrf_token', csrfToken);

            try {
                const response = await fetch('staff.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message);
                
                if (result.new_image_url) {
                    const newUrl = `${result.new_image_url}?v=${new Date().getTime()}`;
                    document.querySelectorAll('.profile-picture, .editable-profile-picture').forEach(img => img.src = newUrl);
                    showFeedback(personalInfoForm, result.message, true);
                }
            } catch (error) {
                showFeedback(personalInfoForm, error.message, false);
            }
        });

        profilePage.querySelectorAll('.toggle-password').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const passwordInput = toggle.previousElementSibling;
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                toggle.classList.toggle('fa-eye');
                toggle.classList.toggle('fa-eye-slash');
            });
        });
    }

    // --- USER MANAGEMENT LOGIC ---
    const userManagementPage = document.getElementById('user-management-page');
    let userFetchDebounce;
    
    // Moved renderUsers function to the outer scope
    const renderUsers = (users) => {
        const userTableBody = document.getElementById('users-table')?.querySelector('tbody');
        if (!userTableBody) return;

        if (users.length === 0) {
            userTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No users found.</td></tr>`;
            return;
        }
        userTableBody.innerHTML = users.map(user => `
            <tr data-user='${JSON.stringify(user)}'>
                <td data-label="User ID">${user.display_user_id}</td>
                <td data-label="Name">${user.name}</td>
                <td data-label="Role">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</td>
                <td data-label="Email">${user.email}</td>
                <td data-label="Status">
                    <span class="status ${user.active == 1 ? 'admitted' : 'unpaid'}">${user.active == 1 ? 'Active' : 'Inactive'}</span>
                </td>
                <td data-label="Actions">
                    <button class="action-btn edit-user-btn"><i class="fas fa-edit"></i> Edit</button> 
                    <button class="action-btn danger remove-user-btn"><i class="fas fa-trash-alt"></i> Deactivate</button>
                </td>
            </tr>
        `).join('');
    };

    // Moved fetchUsers function to the outer scope to make it accessible
    const fetchUsers = async () => {
        const userTableBody = document.getElementById('users-table')?.querySelector('tbody');
        if (!userTableBody) return; // Exit if the table body isn't on the page

        const roleFilter = document.getElementById('user-role-filter');
        const searchInput = document.getElementById('user-search');
        const role = roleFilter.value;
        const search = searchInput.value;
        userTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">Loading users...</td></tr>`;

        try {
            const response = await fetch(`staff.php?fetch=get_users&role=${role}&search=${search}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderUsers(result.data);
        } catch (error) {
            userTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
        }
    };

    if (userManagementPage) {
        const userTableBody = document.getElementById('users-table')?.querySelector('tbody');
        const roleFilter = document.getElementById('user-role-filter');
        const searchInput = document.getElementById('user-search');
        const addUserBtn = document.getElementById('add-new-user-btn');
        const userModal = document.getElementById('user-management-modal');
        const userModalTitle = document.getElementById('user-modal-title');
        const userForm = document.getElementById('user-management-form');
        const doctorFields = document.getElementById('doctor-fields');
        const passwordGroup = document.getElementById('password-group');
        const activeGroup = userForm.querySelector('#active-group');
        const dobInput = userForm.querySelector('#user-dob');

        if (dobInput) {
            dobInput.addEventListener('input', function () {
                if (this.value) {
                    const parts = this.value.split('-');
                    if (parts[0].length > 4) {
                        parts[0] = parts[0].slice(0, 4);
                        this.value = parts.join('-');
                    }
                }
            });
        }
        
        const openUserModal = (mode, userData = {}) => {
            userForm.reset();
            userForm.querySelector('#user-username').disabled = false;
            userForm.querySelector('#user-role').disabled = false;
            doctorFields.style.display = 'none';
            activeGroup.style.display = 'none';

            if (mode === 'add') {
                userModalTitle.textContent = 'Add New User';
                userForm.querySelector('#user-form-action').value = 'addUser';
                passwordGroup.style.display = 'block';
                userForm.querySelector('#user-password').required = true;
            } else {
                userModalTitle.textContent = `Edit ${userData.name}`;
                userForm.querySelector('#user-form-action').value = 'updateUser';
                userForm.querySelector('#user-id').value = userData.id;
                userForm.querySelector('#user-name').value = userData.name;
                userForm.querySelector('#user-username').value = userData.username;
                userForm.querySelector('#user-username').disabled = true;
                userForm.querySelector('#user-email').value = userData.email;
                userForm.querySelector('#user-phone').value = userData.phone || '';
                userForm.querySelector('#user-dob').value = userData.date_of_birth || '';
                userForm.querySelector('#user-role').value = userData.role;
                userForm.querySelector('#user-role').disabled = true;
                passwordGroup.style.display = 'none';
                userForm.querySelector('#user-password').required = false;

                if(userData.role === 'doctor') {
                    doctorFields.style.display = 'block';
                    userForm.querySelector('#doctor-specialty').value = userData.specialty || '';
                }
                activeGroup.style.display = 'block';
                userForm.querySelector('#user-active').value = userData.active;
            }
            userModal.classList.add('show');
        };

        userForm.querySelector('#user-role').addEventListener('change', (e) => {
            doctorFields.style.display = e.target.value === 'doctor' ? 'block' : 'none';
        });

        addUserBtn.addEventListener('click', () => openUserModal('add'));
        userModal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => userModal.classList.remove('show')));
        
        userTableBody.addEventListener('click', (e) => {
            if(e.target.closest('.edit-user-btn')) {
                const row = e.target.closest('tr');
                const userData = JSON.parse(row.dataset.user);
                openUserModal('edit', userData);
            }
            if(e.target.closest('.remove-user-btn')) {
                const row = e.target.closest('tr');
                const userData = JSON.parse(row.dataset.user);
                showConfirmation('Deactivate User', `Are you sure you want to deactivate ${userData.name}? This will make their account inactive.`)
                    .then(confirmed => {
                        if (confirmed) {
                           const formData = new FormData();
                           formData.append('action', 'removeUser');
                           formData.append('id', userData.id);
                           formData.append('csrf_token', csrfToken);
                           handleUserFormSubmit(formData);
                        }
                    });
            }
        });
        
        userForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(userForm);
            formData.append('csrf_token', csrfToken);
            handleUserFormSubmit(formData);
        });

        const handleUserFormSubmit = async (formData) => {
            try {
                const response = await fetch('staff.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(!result.success) throw new Error(result.message);
                alert(result.message);
                userModal.classList.remove('show');
                fetchUsers();
            } catch (error) {
                alert(`Error: ${error.message}`);
            }
        }

        roleFilter.addEventListener('change', fetchUsers);
        searchInput.addEventListener('input', () => {
            clearTimeout(userFetchDebounce);
            userFetchDebounce = setTimeout(fetchUsers, 300);
        });
    }
    
    // --- INVENTORY MANAGEMENT LOGIC ---
    const inventoryPage = document.getElementById('inventory-page');
    let inventoryInitialized = false;
    let medicineSearchDebounce;

    function initializeInventory() {
        if (inventoryInitialized || !inventoryPage) return;

        const medicinesTab = inventoryPage.querySelector('.tab-link[data-tab="medicines"]');
        const bloodTab = inventoryPage.querySelector('.tab-link[data-tab="blood"]');
        const medicinesTableBody = document.getElementById('medicines-table')?.querySelector('tbody');
        const bloodTableBody = document.getElementById('blood-table-body');
        const medicineModal = document.getElementById('medicine-stock-modal');
        const bloodModal = document.getElementById('blood-stock-modal');
        const medicineSearchInput = document.getElementById('medicine-search');

        const fetchAndRenderMedicines = async (search = '') => {
            medicinesTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center;">Loading medicines...</td></tr>`;
            try {
                const response = await fetch(`staff.php?fetch=medicines&search=${encodeURIComponent(search)}`);
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
                renderMedicines(result.data);
            } catch (error) {
                medicinesTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
            }
        };

        const renderMedicines = (medicines) => {
            if (medicines.length === 0) {
                medicinesTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center;">No medicines found in inventory.</td></tr>`;
                return;
            }
            medicinesTableBody.innerHTML = medicines.map(med => {
                let statusClass, statusText;
                if (med.quantity <= 0) {
                    statusClass = 'out-of-stock';
                    statusText = 'Out of Stock';
                } else if (med.quantity <= med.low_stock_threshold) {
                    statusClass = 'low-stock';
                    statusText = 'Low Stock';
                } else {
                    statusClass = 'in-stock';
                    statusText = 'In Stock';
                }
                return `
                    <tr data-id="${med.id}" data-name="${med.name}" data-quantity="${med.quantity}">
                        <td data-label="Name">${med.name}</td>
                        <td data-label="Stock">${med.quantity}</td>
                        <td data-label="Status"><span class="status ${statusClass}">${statusText}</span></td>
                        <td data-label="Action"><button class="action-btn edit-medicine-btn"><i class="fas fa-edit"></i> Edit</button></td>
                    </tr>
                `;
            }).join('');
        };
        
        medicineSearchInput.addEventListener('input', () => {
            clearTimeout(medicineSearchDebounce);
            medicineSearchDebounce = setTimeout(() => {
                fetchAndRenderMedicines(medicineSearchInput.value);
            }, 300);
        });

        const fetchAndRenderBlood = async () => {
            bloodTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center;">Loading blood stock...</td></tr>`;
            try {
                const response = await fetch('staff.php?fetch=blood_inventory');
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
                renderBlood(result.data);
            } catch (error) {
                bloodTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
            }
        };

        const renderBlood = (blood) => {
            if (blood.length === 0) {
                bloodTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center;">No blood stock data found.</td></tr>`;
                return;
            }
            bloodTableBody.innerHTML = blood.map(b => {
                let statusClass, statusText;
                if (b.quantity_ml <= 0) {
                    statusClass = 'out-of-stock';
                    statusText = 'Out of Stock';
                } else if (b.quantity_ml <= b.low_stock_threshold_ml) {
                    statusClass = 'low-stock';
                    statusText = 'Low Stock';
                } else {
                    statusClass = 'in-stock';
                    statusText = 'In Stock';
                }
                return `
                    <tr data-group="${b.blood_group}" data-quantity="${b.quantity_ml}">
                        <td data-label="Blood Type">${b.blood_group}</td>
                        <td data-label="Units Available">${b.quantity_ml} ml</td>
                        <td data-label="Status"><span class="status ${statusClass}">${statusText}</span></td>
                        <td data-label="Action"><button class="action-btn edit-blood-btn"><i class="fas fa-edit"></i> Edit</button></td>
                    </tr>
                `;
            }).join('');
        };

        medicinesTab.addEventListener('click', (e) => {
            document.getElementById('blood-tab').classList.remove('active');
            document.getElementById('medicines-tab').classList.add('active');
            bloodTab.classList.remove('active');
            medicinesTab.classList.add('active');
            fetchAndRenderMedicines(medicineSearchInput.value);
        });

        bloodTab.addEventListener('click', (e) => {
            document.getElementById('medicines-tab').classList.remove('active');
            document.getElementById('blood-tab').classList.add('active');
            medicinesTab.classList.remove('active');
            bloodTab.classList.add('active');
            fetchAndRenderBlood();
        });

        medicinesTableBody.addEventListener('click', (e) => {
            const btn = e.target.closest('.edit-medicine-btn');
            if (btn) {
                const row = btn.closest('tr');
                const form = medicineModal.querySelector('form');
                form.reset();
                form.querySelector('#medicine-stock-id').value = row.dataset.id;
                form.querySelector('#medicine-stock-name').value = row.dataset.name;
                form.querySelector('#medicine-stock-quantity').value = row.dataset.quantity;
                medicineModal.classList.add('show');
            }
        });

        bloodTableBody.addEventListener('click', (e) => {
            const btn = e.target.closest('.edit-blood-btn');
            if (btn) {
                const row = btn.closest('tr');
                const form = bloodModal.querySelector('form');
                form.reset();
                form.querySelector('#blood-stock-group').value = row.dataset.group;
                form.querySelector('#blood-stock-quantity').value = row.dataset.quantity;
                bloodModal.classList.add('show');
            }
        });

        const handleStockUpdate = async (form, fetcher, searchVal = '') => {
            const formData = new FormData(form);
            formData.append('csrf_token', csrfToken);
            try {
                const response = await fetch('staff.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
                alert(result.message);
                form.closest('.modal-overlay').classList.remove('show');
                if (form.id === 'medicine-stock-form') {
                    fetchAndRenderMedicines(medicineSearchInput.value);
                } else {
                    fetchAndRenderBlood();
                }
            } catch (error) {
                alert(`Error: ${error.message}`);
            }
        };

        medicineModal.querySelector('form').addEventListener('submit', (e) => {
            e.preventDefault();
            handleStockUpdate(e.target);
        });

        bloodModal.querySelector('form').addEventListener('submit', (e) => {
            e.preventDefault();
            handleStockUpdate(e.target);
        });

        medicineModal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => medicineModal.classList.remove('show')));
        bloodModal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => bloodModal.classList.remove('show')));

        fetchAndRenderMedicines();
        inventoryInitialized = true;
    }

    // --- BED MANAGEMENT LOGIC ---
    const bedManagementPage = document.getElementById('bed-management-page');
    let bedManagementInitialized = false;
    let bedManagementData = {}; // To store fetched data

    async function initializeBedManagement() {
        if (bedManagementInitialized) {
            await fetchAndRenderBedData(); // Always refresh data on tab click
            return;
        }

        if (!bedManagementPage) return;

        await fetchAndRenderBedData();

        document.getElementById('add-new-bed-btn').addEventListener('click', () => openBedModal('add'));
        document.getElementById('bed-location-filter').addEventListener('change', filterBedGrid);
        document.getElementById('bed-status-filter').addEventListener('change', filterBedGrid);

        const bedModal = document.getElementById('bed-management-modal');
        bedModal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => bedModal.classList.remove('show')));
        document.getElementById('bed-management-form').addEventListener('submit', handleBedFormSubmit);
        document.getElementById('bed-form-type').addEventListener('change', (e) => {
            document.getElementById('bed-form-ward-group').style.display = e.target.value === 'bed' ? 'block' : 'none';
        });

        const assignModal = document.getElementById('bed-assign-modal');
        assignModal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => assignModal.classList.remove('show')));
        document.getElementById('bed-assign-form').addEventListener('submit', handleAssignFormSubmit);
        document.getElementById('bed-discharge-btn').addEventListener('click', handleDischarge);

        document.getElementById('bed-grid-container').addEventListener('click', (e) => {
            const card = e.target.closest('.bed-card');
            if (!card) return;

            const entity = JSON.parse(card.dataset.entity);
            
            if (e.target.closest('.edit-bed-btn')) {
                openBedModal('edit', entity);
            } else if (e.target.closest('.manage-occupancy-btn')) {
                openAssignModal(entity);
            }
        });

        bedManagementInitialized = true;
    }

    async function fetchAndRenderBedData() {
        const gridContainer = document.getElementById('bed-grid-container');
        gridContainer.innerHTML = '<p class="no-items-message">Loading bed data...</p>';

        try {
            const response = await fetch('staff.php?fetch=bed_management_data');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            bedManagementData = result.data;
            renderBedManagementPage(bedManagementData);

        } catch (error) {
            console.error("Fetch error:", error);
            gridContainer.innerHTML = `<p class="no-items-message" style="color:var(--danger-color)">Failed to load bed data.</p>`;
        }
    }

    function renderBedManagementPage(data) {
        const locationFilter = document.getElementById('bed-location-filter');
        const currentVal = locationFilter.value;
        locationFilter.innerHTML = '<option value="all">All Wards & Rooms</option>';
        data.wards.forEach(ward => {
            locationFilter.innerHTML += `<option value="ward-${ward.id}">${ward.name}</option>`;
        });
        locationFilter.innerHTML += `<option value="rooms">Private Rooms</option>`;
        locationFilter.value = currentVal;


        const gridContainer = document.getElementById('bed-grid-container');
        gridContainer.innerHTML = '';
        if (data.beds.length === 0 && data.rooms.length === 0) {
            gridContainer.innerHTML = '<p class="no-items-message">No beds or rooms found. Add one to get started.</p>';
            return;
        }
        
        const allAccommodations = [...data.beds, ...data.rooms].map(entity => {
            return renderBedCard(entity);
        }).join('');
        
        gridContainer.innerHTML = allAccommodations;

        filterBedGrid();
    }

    function renderBedCard(entity) {
        const isBed = entity.type === 'bed';
        const number = entity.number; // **FIXED**: Using the correct 'number' key
        const locationName = isBed ? entity.ward_name : 'Private Room';
        const locationFilterValue = isBed ? `ward-${entity.ward_id}` : 'rooms';

        let patientInfo = '';
        if (entity.status === 'occupied' && entity.patient_name) {
            let tooltip = `Patient: ${entity.patient_name} (${entity.patient_display_id})`;
            if(entity.doctor_name) {
                tooltip += `\nDoctor: ${entity.doctor_name}`;
            }
            patientInfo = `<div class="patient-info" title="${tooltip}">
                <i class="fas fa-user-circle"></i> ${entity.patient_name}
            </div>`;
        }

        return `
            <div class="bed-card status-${entity.status}" 
                 data-status="${entity.status}" 
                 data-location="${locationFilterValue}"
                 data-entity='${JSON.stringify(entity)}'
                 data-type="${entity.type}">
                
                <div class="bed-card-header">
                    <div class="bed-id">${number}</div>
                    <div class="bed-actions">
                        <button class="action-btn-icon manage-occupancy-btn" title="Manage Occupancy"><i class="fas fa-user-plus"></i></button>
                        <button class="action-btn-icon edit-bed-btn" title="Edit Details"><i class="fas fa-pencil-alt"></i></button>
                    </div>
                </div>
                <div class="bed-details">${locationName}</div>
                ${patientInfo}
            </div>
        `;
    }

    function filterBedGrid() {
        const locationFilter = document.getElementById('bed-location-filter').value;
        const statusFilter = document.getElementById('bed-status-filter').value;
        const cards = document.querySelectorAll('#bed-grid-container .bed-card');
        let visibleCount = 0;
        cards.forEach(card => {
            const showLocation = (locationFilter === 'all') || (card.dataset.location === locationFilter);
            const showStatus = (statusFilter === 'all') || (card.dataset.status === statusFilter);
            if (showLocation && showStatus) {
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        if (visibleCount === 0) {
            const gridContainer = document.getElementById('bed-grid-container');
            if(!gridContainer.querySelector('.no-items-message')) {
                 gridContainer.insertAdjacentHTML('beforeend', '<p class="no-items-message">No beds match the current filters.</p>');
            }
        } else {
            const noItemsMsg = document.querySelector('#bed-grid-container .no-items-message');
            if(noItemsMsg) noItemsMsg.remove();
        }
    }


    function openBedModal(mode, entity = {}) {
        const modal = document.getElementById('bed-management-modal');
        const form = document.getElementById('bed-management-form');
        const title = document.getElementById('bed-modal-title');
        const wardSelect = document.getElementById('bed-form-ward');
        const typeSelect = document.getElementById('bed-form-type');
        const wardGroup = document.getElementById('bed-form-ward-group');

        form.reset();
        
        wardSelect.innerHTML = '<option value="">-- Select a Ward --</option>';
        bedManagementData.wards.forEach(ward => {
            wardSelect.innerHTML += `<option value="${ward.id}">${ward.name}</option>`;
        });

        if (mode === 'add') {
            title.textContent = 'Add New Bed / Room';
            form.querySelector('#bed-form-action').value = 'addBedOrRoom';
            form.querySelector('#bed-form-id').value = '';
            typeSelect.disabled = false;
        } else { // 'edit' mode
            const type = entity.type;
            const number = entity.number; // **FIXED**: Using the correct 'number' key
            title.textContent = `Edit ${type.charAt(0).toUpperCase() + type.slice(1)} ${number}`;
            form.querySelector('#bed-form-action').value = 'updateBedOrRoom';
            form.querySelector('#bed-form-id').value = entity.id;
            
            typeSelect.value = type;
            typeSelect.disabled = true;

            document.getElementById('bed-form-number').value = number;
            document.getElementById('bed-form-price').value = parseFloat(entity.price_per_day).toFixed(2);
            if (type === 'bed') {
                wardSelect.value = entity.ward_id;
            }
        }
        
        wardGroup.style.display = typeSelect.value === 'bed' ? 'block' : 'none';
        modal.classList.add('show');
    }

    async function handleBedFormSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const modal = form.closest('.modal-overlay');
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);
        
        // When updating, we need to explicitly send the type, as the field is disabled.
        if (form.querySelector('#bed-form-id').value) {
            const type = document.getElementById('bed-form-type').value;
            formData.append('type', type);
        }


        try {
            const response = await fetch('staff.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            modal.classList.remove('show');
            alert(result.message);
            await fetchAndRenderBedData();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    
    function openAssignModal(entity) {
        const modal = document.getElementById('bed-assign-modal');
        const form = document.getElementById('bed-assign-form');
        form.reset();

        const type = entity.type;
        const number = entity.number;
        
        document.getElementById('bed-assign-modal-title').textContent = `Manage Occupancy for ${type.charAt(0).toUpperCase() + type.slice(1)} ${number}`;
        document.getElementById('bed-assign-id').value = entity.id;
        document.getElementById('bed-assign-type').value = type;
        document.getElementById('bed-assign-current-status').textContent = entity.status.charAt(0).toUpperCase() + entity.status.slice(1);
        
        const assignSection = document.getElementById('assign-patient-section');
        const dischargeSection = document.getElementById('discharge-patient-section');
        const submitBtn = document.getElementById('bed-assign-submit-btn');
        const dischargeBtn = document.getElementById('bed-discharge-btn');

        if (entity.status === 'available' || entity.status === 'cleaning') {
            assignSection.style.display = 'block';
            dischargeSection.style.display = 'none';
            submitBtn.style.display = 'inline-flex';
            submitBtn.textContent = 'Assign Patient';
            dischargeBtn.style.display = 'none';

            const patientSelect = document.getElementById('bed-assign-patient-id');
            patientSelect.innerHTML = '<option value="">-- Select Patient --</option>';
            // Add currently available patients
            bedManagementData.available_patients.forEach(p => {
                patientSelect.innerHTML += `<option value="${p.id}">${p.name} (${p.display_user_id})</option>`;
            });

            const doctorSelect = document.getElementById('bed-assign-doctor-id');
            doctorSelect.innerHTML = '<option value="">-- Select Doctor --</option>';
            bedManagementData.available_doctors.forEach(d => {
                doctorSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
            });

        } else if (entity.status === 'occupied') {
            assignSection.style.display = 'none';
            dischargeSection.style.display = 'block';
            submitBtn.style.display = 'none';
            dischargeBtn.style.display = 'inline-flex';
            
            document.getElementById('bed-assign-patient-name').textContent = `${entity.patient_name} (${entity.patient_display_id})`;
            document.getElementById('bed-assign-doctor-name').textContent = entity.doctor_name || 'N/A';
        } else { // Reserved, etc.
            assignSection.style.display = 'none';
            dischargeSection.style.display = 'none';
            submitBtn.style.display = 'none';
            dischargeBtn.style.display = 'none';
        }

        modal.classList.add('show');
    }

    async function handleAssignFormSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const modal = form.closest('.modal-overlay');
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);
        formData.append('status', 'occupied');

        try {
            const response = await fetch('staff.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            modal.classList.remove('show');
            alert(result.message);
            await fetchAndRenderBedData();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    async function handleDischarge(e) {
        const modal = e.target.closest('.modal-overlay');
        const id = document.getElementById('bed-assign-id').value;
        const type = document.getElementById('bed-assign-type').value;

        const confirmed = await showConfirmation(
            'Confirm Discharge', 
            `Are you sure you want to discharge the patient from this ${type}? The status will be set to 'Cleaning'.`
        );
        if (!confirmed) return;
        
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);
        formData.append('action', 'updateBedOrRoom');
        formData.append('id', id);
        formData.append('type', type);
        formData.append('patient_id', ''); // Empty patient ID signifies discharge
        formData.append('doctor_id', ''); // Clear doctor as well
        formData.append('status', 'cleaning');

        try {
            const response = await fetch('staff.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            modal.classList.remove('show');
            alert(result.message);
            await fetchAndRenderBedData();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    
    // --- ADMISSIONS MANAGEMENT LOGIC ---
    const admissionsPage = document.getElementById('admissions-page');
    let admissionsInitialized = false;
    let admissionsSearchDebounce;

    function initializeAdmissions() {
        if (admissionsInitialized || !admissionsPage) return;

        const searchInput = document.getElementById('admissions-search');
        searchInput.addEventListener('input', () => {
            clearTimeout(admissionsSearchDebounce);
            admissionsSearchDebounce = setTimeout(() => {
                fetchAndRenderAdmissions(searchInput.value);
            }, 300);
        });
        
        const admitPatientBtn = document.getElementById('admit-patient-btn');
        admitPatientBtn.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelector('.nav-link[data-page="bed-management"]').click();
        });

        fetchAndRenderAdmissions();
        admissionsInitialized = true;
    }

    async function fetchAndRenderAdmissions(search = '') {
        const tableBody = document.getElementById('admissions-table')?.querySelector('tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">Loading admissions...</td></tr>`;

        try {
            const response = await fetch(`staff.php?fetch=admissions&search=${encodeURIComponent(search)}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderAdmissions(result.data);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
        }
    }

    function renderAdmissions(admissions) {
        const tableBody = document.getElementById('admissions-table')?.querySelector('tbody');
        if (!tableBody) return;

        if (admissions.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">No admissions found matching your criteria.</td></tr>`;
            return;
        }

        tableBody.innerHTML = admissions.map(adm => {
            const status = adm.discharge_date 
                ? '<span class="status completed">Discharged</span>' 
                : '<span class="status admitted">Admitted</span>';
            
            const admissionDate = new Date(adm.admission_date).toLocaleDateString('en-CA');
            const location = adm.location ? `${adm.location_type} ${adm.location}` : 'N/A';

            return `
                <tr>
                    <td data-label="Adm. ID">ADM-${String(adm.id).padStart(4, '0')}</td>
                    <td data-label="Patient Name">${adm.patient_name} (${adm.patient_display_id})</td>
                    <td data-label="Location">${location}</td>
                    <td data-label="Admitted On">${admissionDate}</td>
                    <td data-label="Status">${status}</td>
                </tr>
            `;
        }).join('');
    }

    // --- LAB RESULTS LOGIC ---
    const labsPage = document.getElementById('labs-page');
    let labsInitialized = false;
    let labSearchDebounce;
    let labPatientSearchDebounce;
    let labFormData = null; // Cache for doctors

    function initializeLabs() {
        if (labsInitialized || !labsPage) return;

        fetchAndRenderLabs();

        const searchInput = document.getElementById('lab-search');
        searchInput.addEventListener('input', () => {
            clearTimeout(labSearchDebounce);
            labSearchDebounce = setTimeout(() => {
                fetchAndRenderLabs(searchInput.value);
            }, 300);
        });
        
        document.getElementById('add-lab-result-btn').addEventListener('click', () => openLabModal('add'));
        
        const modal = document.getElementById('lab-result-modal');
        modal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => modal.classList.remove('show')));
        document.getElementById('lab-result-form').addEventListener('submit', handleLabFormSubmit);
        
        const tableBody = document.getElementById('lab-results-table')?.querySelector('tbody');
        tableBody.addEventListener('click', e => {
            const row = e.target.closest('tr');
            if (!row) return;

            const labData = JSON.parse(row.dataset.labResult);

            if (e.target.closest('.edit-lab-btn')) {
                openLabModal('edit', labData);
            }
            if (e.target.closest('.remove-lab-btn')) {
                showConfirmation('Delete Lab Result', `Are you sure you want to delete the lab result for ${labData.patient_name} (Test: ${labData.test_name})? This action is permanent and will delete the associated report file.`)
                    .then(confirmed => {
                        if (confirmed) handleRemoveLabResult(labData.id);
                    });
            }
        });
        
        // Event listeners for the new patient search in modal
        const patientSearchInput = document.getElementById('lab-patient-search');
        const patientSearchResults = document.getElementById('patient-search-results');
        patientSearchInput.addEventListener('input', () => {
             clearTimeout(labPatientSearchDebounce);
             labPatientSearchDebounce = setTimeout(() => handlePatientSearch(patientSearchInput.value), 300);
        });
        
        patientSearchResults.addEventListener('click', (e) => {
            const item = e.target.closest('.search-result-item');
            if(item) {
                selectPatient(item.dataset.id, item.dataset.name);
            }
        });

        document.getElementById('clear-selected-patient-btn').addEventListener('click', clearSelectedPatient);

        labsInitialized = true;
    }

    async function fetchAndRenderLabs(search = '') {
        const tableBody = document.getElementById('lab-results-table')?.querySelector('tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">Loading lab results...</td></tr>`;

        try {
            const response = await fetch(`staff.php?fetch=lab_results&search=${encodeURIComponent(search)}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderLabs(result.data);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
        }
    }

    function renderLabs(data) {
        const tableBody = document.getElementById('lab-results-table')?.querySelector('tbody');
        if (!tableBody) return;

        if (data.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No lab results found.</td></tr>`;
            return;
        }

        tableBody.innerHTML = data.map(result => {
            const status = result.result_details 
                ? '<span class="status completed">Completed</span>' 
                : '<span class="status pending">Pending</span>';
            
            const reportLink = result.attachment_path
                ? `<a href="report/${result.attachment_path}" target="_blank" class="action-btn" download><i class="fas fa-download"></i> Download</a>`
                : '<span>N/A</span>';
            
            return `
                <tr data-lab-result='${JSON.stringify(result)}'>
                    <td data-label="Report ID">REP-${String(result.id).padStart(5, '0')}</td>
                    <td data-label="Patient">${result.patient_name} (${result.patient_display_id})</td>
                    <td data-label="Test">${result.test_name}</td>
                    <td data-label="Status">${status}</td>
                    <td data-label="Report">${reportLink}</td>
                    <td data-label="Actions">
                        <button class="action-btn edit-lab-btn"><i class="fas fa-edit"></i> Edit</button>
                        <button class="action-btn danger remove-lab-btn"><i class="fas fa-trash-alt"></i></button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    async function openLabModal(mode, data = {}) {
        const modal = document.getElementById('lab-result-modal');
        const form = document.getElementById('lab-result-form');
        const title = document.getElementById('lab-modal-title');
        form.reset();
        clearSelectedPatient();
        document.getElementById('current-attachment-info').innerHTML = '';


        // Fetch doctor data if not already cached
        if (!labFormData || !labFormData.doctors) {
            try {
                const response = await fetch('staff.php?fetch=lab_form_data');
                const result = await response.json();
                if (!result.success) throw new Error(result.message);
                labFormData = result.data;
            } catch (error) {
                alert('Failed to load doctor list: ' + error.message);
                return;
            }
        }
        
        const doctorSelect = document.getElementById('lab-doctor-id');
        doctorSelect.innerHTML = '<option value="">-- Select Doctor (Optional) --</option>';
        labFormData.doctors.forEach(d => {
            doctorSelect.innerHTML += `<option value="${d.id}">${d.name}</option>`;
        });
        
        if (mode === 'add') {
            title.textContent = 'Add Lab Result';
            document.getElementById('lab-form-action').value = 'addLabResult';
            document.getElementById('lab-result-id').value = '';
        } else { // 'edit'
            title.textContent = `Edit Lab Result for ${data.patient_name}`;
            document.getElementById('lab-form-action').value = 'updateLabResult';
            document.getElementById('lab-result-id').value = data.id;
            
            if(data.patient_id && data.patient_name) {
                selectPatient(data.patient_id, data.patient_name);
            }

            doctorSelect.value = data.doctor_id || '';
            document.getElementById('lab-test-name').value = data.test_name;
            document.getElementById('lab-test-date').value = data.test_date;
            document.getElementById('lab-result-details').value = data.result_details || '';

            if (data.attachment_path) {
                document.getElementById('current-attachment-info').innerHTML = 
                `Current Report: <a href="report/${data.attachment_path}" target="_blank">View Report</a>`;
            }
        }

        modal.classList.add('show');
    }
    
    async function handlePatientSearch(query) {
        const resultsContainer = document.getElementById('patient-search-results');
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
            return;
        }

        resultsContainer.innerHTML = '<div class="search-result-item">Searching...</div>';
        resultsContainer.style.display = 'block';

        try {
            const response = await fetch(`staff.php?fetch=search_patients&query=${encodeURIComponent(query)}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            if(result.data.length === 0) {
                 resultsContainer.innerHTML = '<div class="search-result-item">No patients found.</div>';
            } else {
                resultsContainer.innerHTML = result.data.map(p => `
                    <div class="search-result-item" data-id="${p.id}" data-name="${p.name} (${p.display_user_id})">
                        ${p.name} (${p.display_user_id})
                    </div>
                `).join('');
            }

        } catch (error) {
             resultsContainer.innerHTML = `<div class="search-result-item" style="color:red">Search failed.</div>`;
        }
    }
    
    function selectPatient(id, name) {
        document.getElementById('lab-patient-id').value = id;
        document.getElementById('selected-patient-name').textContent = name;
        
        document.getElementById('patient-search-results').style.display = 'none';
        document.getElementById('lab-patient-search').style.display = 'none';
        document.getElementById('selected-patient-display').style.display = 'flex';
    }

    function clearSelectedPatient() {
        document.getElementById('lab-patient-id').value = '';
        document.getElementById('selected-patient-name').textContent = '';
        document.getElementById('lab-patient-search').value = '';
        
        document.getElementById('patient-search-results').style.display = 'none';
        document.getElementById('lab-patient-search').style.display = 'block';
        document.getElementById('selected-patient-display').style.display = 'none';
    }


    async function handleLabFormSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const modal = form.closest('.modal-overlay');
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('staff.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'An unknown error occurred');

            modal.classList.remove('show');
            alert(result.message);
            fetchAndRenderLabs();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    
    async function handleRemoveLabResult(id) {
        const formData = new FormData();
        formData.append('action', 'removeLabResult');
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('staff.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            alert(result.message);
            fetchAndRenderLabs();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    // --- DISCHARGE MANAGEMENT LOGIC ---
    const dischargePage = document.getElementById('discharge-page');
    let dischargeInitialized = false;
    let dischargeSearchDebounce;

    function initializeDischarge() {
        if (dischargeInitialized || !dischargePage) return;

        const searchInput = document.getElementById('discharge-search');
        searchInput.addEventListener('input', () => {
            clearTimeout(dischargeSearchDebounce);
            dischargeSearchDebounce = setTimeout(() => {
                fetchAndRenderDischarges(searchInput.value, document.getElementById('discharge-status-filter').value);
            }, 300);
        });

        const statusFilter = document.getElementById('discharge-status-filter');
        statusFilter.addEventListener('change', () => {
            fetchAndRenderDischarges(searchInput.value, statusFilter.value);
        });

        document.getElementById('discharge-table').addEventListener('click', (e) => {
            const btn = e.target.closest('.process-clearance-btn');
            if (btn) {
                const dischargeId = btn.dataset.id;
                openDischargeClearanceModal(dischargeId);
            }
        });

        document.getElementById('discharge-clearance-form').addEventListener('submit', handleDischargeClearanceSubmit);

        fetchAndRenderDischarges();
        dischargeInitialized = true;
    }

    async function fetchAndRenderDischarges(search = '', status = 'all') {
        const tableBody = document.getElementById('discharge-table')?.querySelector('tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">Loading discharge requests...</td></tr>`;

        try {
            const response = await fetch(`staff.php?fetch=discharge_requests&search=${encodeURIComponent(search)}&status=${status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderDischarges(result.data);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
        }
    }

    function renderDischarges(data) {
        const tableBody = document.getElementById('discharge-table')?.querySelector('tbody');
        if (!tableBody) return;

        if (data.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">No discharge requests found.</td></tr>`;
            return;
        }

        tableBody.innerHTML = data.map(req => {
            let statusText = '';
            let statusClass = '';
            switch(req.clearance_step) {
                case 'nursing':
                    statusText = 'Pending Nursing';
                    statusClass = 'pending-nursing';
                    break;
                case 'pharmacy':
                    statusText = 'Pending Pharmacy';
                    statusClass = 'pending-pharmacy';
                    break;
                case 'billing':
                    statusText = 'Pending Billing';
                    statusClass = 'pending-billing';
                    break;
            }
            if (req.is_cleared == 1) {
                statusText = `Cleared by ${req.cleared_by_name}`;
                statusClass = 'completed';
            }

            return `
                <tr>
                    <td data-label="Req. ID">D-${String(req.discharge_id).padStart(4, '0')}</td>
                    <td data-label="Patient">${req.patient_name} (${req.patient_display_id})</td>
                    <td data-label="Status"><span class="status ${statusClass}">${statusText}</span></td>
                    <td data-label="Doctor">${req.doctor_name}</td>
                    <td data-label="Action">
                        ${req.is_cleared == 0 ? `<button class="action-btn process-clearance-btn" data-id="${req.discharge_id}"><i class="fas fa-check"></i> Process</button>` : `<button class="action-btn" disabled><i class="fas fa-check-double"></i> Done</button>`}
                    </td>
                </tr>
            `;
        }).join('');
    }

    function openDischargeClearanceModal(dischargeId) {
        const modal = document.getElementById('discharge-clearance-modal');
        document.getElementById('discharge-id').value = dischargeId;
        document.getElementById('discharge-notes').value = '';
        modal.classList.add('show');
    }

    async function handleDischargeClearanceSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const modal = form.closest('.modal-overlay');
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('staff.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            alert(result.message);
            modal.classList.remove('show');
            fetchAndRenderDischarges(document.getElementById('discharge-search').value, document.getElementById('discharge-status-filter').value);
        } catch (error) {
            alert(`Error: ${error.message}`);
        }
    }

    // --- BILLING MANAGEMENT LOGIC ---
    const billingPage = document.getElementById('billing-page');
    let billingInitialized = false;
    let invoicePatientSearchDebounce;
    let billingSearchDebounce;


    function initializeBilling() {
        if (billingInitialized) return;

        fetchAndRenderInvoices();

        document.getElementById('create-invoice-btn').addEventListener('click', openCreateInvoiceModal);
        
        const searchInput = document.getElementById('billing-search');
        searchInput.addEventListener('input', () => {
            clearTimeout(billingSearchDebounce);
            billingSearchDebounce = setTimeout(() => {
                fetchAndRenderInvoices(searchInput.value);
            }, 300);
        });

        billingInitialized = true;
    }

    async function fetchAndRenderInvoices(search = '') {
        const tableBody = document.getElementById('billing-table')?.querySelector('tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">Loading invoices...</td></tr>`;

        try {
            const response = await fetch(`staff.php?fetch=invoices&search=${encodeURIComponent(search)}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderInvoices(result.data);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
        }
    }

    function renderInvoices(invoices) {
        const tableBody = document.getElementById('billing-table')?.querySelector('tbody');
        if (!tableBody) return;

        if (invoices.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No invoices found.</td></tr>`;
            return;
        }

        tableBody.innerHTML = invoices.map(inv => {
            const statusClass = inv.status === 'paid' ? 'paid' : (inv.status === 'pending' ? 'unpaid' : 'unpaid');
            const statusText = inv.status.charAt(0).toUpperCase() + inv.status.slice(1);

            return `
                <tr>
                    <td data-label="Invoice ID">INV-${String(inv.id).padStart(4, '0')}</td>
                    <td data-label="Patient Name">${inv.patient_name}</td>
                    <td data-label="Amount">₹${parseFloat(inv.amount).toFixed(2)}</td>
                    <td data-label="Date">${new Date(inv.created_at).toLocaleDateString()}</td>
                    <td data-label="Status"><span class="status ${statusClass}">${statusText}</span></td>
                    <td data-label="Actions">
                        <button class="action-btn">View</button>
                        <button class="action-btn">Print</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function openCreateInvoiceModal() {
        const modal = document.getElementById('create-invoice-modal');
        const form = document.getElementById('create-invoice-form');
        form.reset();
        clearSelectedBillablePatient();
        modal.classList.add('show');

        // Attach event listeners for this specific modal instance
        const searchInput = document.getElementById('invoice-patient-search');
        const resultsContainer = document.getElementById('invoice-patient-search-results');
        const clearBtn = document.getElementById('invoice-clear-selected-patient-btn');
        
        const searchHandler = () => {
            clearTimeout(invoicePatientSearchDebounce);
            invoicePatientSearchDebounce = setTimeout(() => handleBillablePatientSearch(searchInput.value), 300);
        };

        const resultClickHandler = (e) => {
            const item = e.target.closest('.search-result-item');
            if (item) {
                selectBillablePatient(item.dataset.id, item.dataset.name);
            }
        };
        
        const formSubmitHandler = (e) => {
             e.preventDefault();
             handleInvoiceFormSubmit(form);
        };

        searchInput.addEventListener('input', searchHandler);
        resultsContainer.addEventListener('click', resultClickHandler);
        clearBtn.addEventListener('click', clearSelectedBillablePatient);
        form.addEventListener('submit', formSubmitHandler);

        // Cleanup listeners when modal is closed
        modal.querySelectorAll('.modal-close-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                modal.classList.remove('show');
                searchInput.removeEventListener('input', searchHandler);
                resultsContainer.removeEventListener('click', resultClickHandler);
                form.removeEventListener('submit', formSubmitHandler);
            }, { once: true });
        });
    }
    
    async function handleBillablePatientSearch(query) {
        const resultsContainer = document.getElementById('invoice-patient-search-results');
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
            return;
        }

        resultsContainer.innerHTML = '<div class="search-result-item">Searching...</div>';
        resultsContainer.style.display = 'block';

        try {
            const response = await fetch(`staff.php?fetch=billable_patients&search=${encodeURIComponent(query)}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            if(result.data.length === 0) {
                 resultsContainer.innerHTML = '<div class="search-result-item">No billable patients found.</div>';
            } else {
                resultsContainer.innerHTML = result.data.map(p => `
                    <div class="search-result-item" data-id="${p.admission_id}" data-name="${p.patient_name} (Adm ID: ${p.admission_id})">
                        ${p.patient_name} (${p.patient_display_id})
                    </div>
                `).join('');
            }

        } catch (error) {
             resultsContainer.innerHTML = `<div class="search-result-item" style="color:var(--danger-color)">Search failed.</div>`;
        }
    }

    function selectBillablePatient(admissionId, name) {
        document.getElementById('invoice-admission-id').value = admissionId;
        document.getElementById('invoice-selected-patient-name').textContent = name;
        
        document.getElementById('invoice-patient-search-results').style.display = 'none';
        document.getElementById('invoice-patient-search').style.display = 'none';
        document.getElementById('invoice-selected-patient-display').style.display = 'flex';
    }

    function clearSelectedBillablePatient() {
        document.getElementById('invoice-admission-id').value = '';
        document.getElementById('invoice-selected-patient-name').textContent = '';
        document.getElementById('invoice-patient-search').value = '';
        
        document.getElementById('invoice-patient-search-results').style.display = 'none';
        document.getElementById('invoice-patient-search').style.display = 'block';
        document.getElementById('invoice-selected-patient-display').style.display = 'none';
    }

    async function handleInvoiceFormSubmit(form) {
        const modal = form.closest('.modal-overlay');
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);
        
        if (!formData.get('admission_id')) {
            alert('Please select a patient admission before generating an invoice.');
            return;
        }

        try {
            const response = await fetch('staff.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'An unknown error occurred');

            modal.classList.remove('show');
            alert(result.message);
            fetchAndRenderInvoices();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

});