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
    
    // --- Theme Toggle, Notifications, and Profile Widget Logic ---
    const themeToggle = document.getElementById('theme-toggle-checkbox');
    themeToggle.addEventListener('change', function() {
        document.body.classList.toggle('dark-theme', this.checked);
        localStorage.setItem('theme', this.checked ? 'dark-theme' : 'light-theme');
    });
    if (localStorage.getItem('theme') === 'dark-theme') {
        themeToggle.checked = true;
        document.body.classList.add('dark-theme');
    }
    // ... other existing logic for notifications, etc.

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
                    fetchCallbackRequests(); // Refresh the list
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
        const avatarUrl = user.avatar_url; // Use the pre-built URL from backend
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
        const avatarUrl = conv.other_user_avatar_url; // Use the pre-built URL from backend
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

        document.querySelectorAll('.conversation-item').forEach(el => {
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
            } else {
                 messagesHtml = `<p class="no-items-message">No messages yet. Say hello!</p>`;
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
            
            input.value = ''; // Clear input
            
            // If it was a new conversation, it now has an ID. Refresh everything.
            if (!activeConversationId) {
                activeConversationId = result.data.conversation_id;
                await fetchAndRenderConversations(); // Fetches all convos, including the new one
                await fetchAndRenderMessages(activeConversationId); // Fetches messages for the new convo
            } else {
                 // Otherwise, just append the new message for a smoother experience
                const container = document.getElementById('chat-messages-container');
                if (container.querySelector('.no-items-message')) container.innerHTML = '';
                
                // Check if the date separator is needed for the new message
                const lastMessageEl = container.querySelector('.message:last-child');
                const lastTimestamp = lastMessageEl ? lastMessageEl.querySelector('.message-timestamp').textContent : null;
                const lastDate = lastTimestamp ? new Date() : null; // simplified; for a real app, parse the timestamp
                const currentDateStr = new Date(result.data.created_at).toDateString();

                let lastDateStr = null;
                if(container.dataset.lastDate) {
                    lastDateStr = container.dataset.lastDate;
                } else if(lastMessageEl) {
                    // This part is complex without storing full date on element; simpler to just refetch
                }
                
                if(currentDateStr !== container.dataset.lastDate) {
                     container.insertAdjacentHTML('beforeend', `<div class="message-date-separator">${formatDateSeparator(result.data.created_at)}</div>`);
                     container.dataset.lastDate = currentDateStr;
                }

                container.insertAdjacentHTML('beforeend', renderMessageItem(result.data));
                container.scrollTop = container.scrollHeight;
                await fetchAndRenderConversations(); // Still update conversation list for "last message" text
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
        // Tab switching logic
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

        // Personal Info Form
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
                if (!response.ok) throw new Error(result.message);
                showFeedback(this, result.message, true);
                document.querySelector('.user-profile-widget strong').textContent = formData.get('name');
            } catch (error) {
                showFeedback(this, error.message, false);
            } finally {
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fas fa-save"></i> Save Changes';
            }
        });

        // Password Change Form
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
                if (!response.ok) throw new Error(result.message);
                showFeedback(this, result.message, true);
                this.reset();
            } catch (error) {
                showFeedback(this, error.message, false);
            } finally {
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fas fa-key"></i> Update Password';
            }
        });
        
        // Profile Picture Upload
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

        // Password Visibility Toggles
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
});