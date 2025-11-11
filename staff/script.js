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

    // This variable will hold our auto-refresh timer for the discharge page.
    let dischargeRefreshInterval = null;

    // The event listener for the discharge form is moved here to ensure it's always active
    // and correctly prevents the page from reloading on submission.
    const dischargeClearanceForm = document.getElementById('discharge-clearance-form');
    if (dischargeClearanceForm) {
        dischargeClearanceForm.addEventListener('submit', handleDischargeClearanceSubmit);
    }

    // --- DASHBOARD LOGIC ---
    let bedChartInstance = null;
    async function fetchDashboardData() {
        const dashboardPage = document.getElementById('dashboard-page');
        if (!dashboardPage.classList.contains('active')) return;

        const availableBedsEl = document.getElementById('stat-available-beds');
        const lowStockEl = document.getElementById('stat-low-stock');
        const pendingDischargesEl = document.getElementById('stat-pending-discharges');
        const activePatientsEl = document.getElementById('stat-active-patients');
        const dischargeTableBody = document.getElementById('pending-discharges-table-body');
        const activityFeedContainer = document.getElementById('activity-feed-container');
        const chartCanvas = document.getElementById('bedOccupancyChart');

        if (availableBedsEl.textContent !== '...') {
            availableBedsEl.textContent = '...';
            lowStockEl.textContent = '...';
            pendingDischargesEl.textContent = '...';
            activePatientsEl.textContent = '...';
            dischargeTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center;">Loading...</td></tr>`;
            activityFeedContainer.innerHTML = `<p class="no-items-message" style="text-align:center;">Loading...</p>`;
        }

        try {
            const response = await fetch('api.php?fetch=dashboard_stats');
            const result = await response.json();

            if (result.success) {
                const stats = result.data;
                
                availableBedsEl.textContent = stats.available_beds;
                lowStockEl.textContent = stats.low_stock_items;
                pendingDischargesEl.textContent = stats.pending_discharges;
                activePatientsEl.textContent = stats.active_patients;

                if (stats.discharge_table_data.length > 0) {
                    dischargeTableBody.innerHTML = stats.discharge_table_data.map(req => {
                        let statusText = '';
                        let statusClass = '';
                        switch(req.clearance_step) {
                            case 'nursing': statusText = 'Nursing'; statusClass = 'pending-nursing'; break;
                            case 'pharmacy': statusText = 'Pharmacy'; statusClass = 'pending-pharmacy'; break;
                            case 'billing': statusText = 'Billing'; statusClass = 'pending-billing'; break;
                        }
                        return `
                            <tr>
                                <td data-label="Patient">${req.patient_name}</td>
                                <td data-label="Location">${req.location || 'N/A'}</td>
                                <td data-label="Status"><span class="status ${statusClass}">${statusText}</span></td>
                                <td data-label="Action"><button class="action-btn btn-sm" onclick="navigateToDischargePage()">View</button></td>
                            </tr>
                        `;
                    }).join('');
                } else {
                    dischargeTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center;">No pending clearances.</td></tr>`;
                }
                
                if(stats.recent_activity.length > 0) {
                     activityFeedContainer.innerHTML = stats.recent_activity.map(log => `
                        <div style="padding-bottom: 10px; border-bottom: 1px solid var(--border-color); margin-bottom: 10px;">
                            <p style="margin:0; font-size: 0.9rem;"><strong>${log.user_name}</strong>: ${log.details}</p>
                            <small style="color: var(--text-muted);">${timeSince(new Date(log.created_at))}</small>
                        </div>
                    `).join('');
                } else {
                    activityFeedContainer.innerHTML = `<p class="no-items-message" style="text-align:center;">No recent activity.</p>`;
                }

                if (bedChartInstance) {
                    bedChartInstance.destroy();
                }
                const occupancyData = stats.occupancy_data;
                const ctx = chartCanvas.getContext('2d');
                const chartColors = {
                    available: '#1abc9c',
                    occupied: '#e74c3c',
                    cleaning: '#f39c12',
                    reserved: '#9b59b6'
                };
                
                bedChartInstance = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: occupancyData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
                        datasets: [{
                            data: occupancyData.map(d => d.count),
                            backgroundColor: occupancyData.map(d => chartColors[d.status] || '#bdc3c7'),
                            borderColor: getComputedStyle(document.body).getPropertyValue('--background-panel'),
                            borderWidth: 3
                        }]
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                position: 'bottom', 
                                labels: { 
                                    color: getComputedStyle(document.body).getPropertyValue('--text-dark'),
                                    padding: 15,
                                    font: { size: 12 }
                                } 
                            }
                        }
                    }
                });

            } else { throw new Error(result.message); }
        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
            dischargeTableBody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--danger-color);">Could not load data.</td></tr>`;
        }
    }
    
    window.navigateToDischargePage = function() {
        document.querySelector('.nav-link[data-page="discharge"]').click();
    };

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
            
            if (pageId !== 'discharge' && dischargeRefreshInterval) {
                clearInterval(dischargeRefreshInterval);
                dischargeRefreshInterval = null;
            }
            
            pages.forEach(page => page.classList.remove('active'));
            document.getElementById(pageId + '-page').classList.add('active');
            navLinks.forEach(navLink => navLink.classList.remove('active'));
            link.classList.add('active');

            const pageTitle = link.querySelector('span') ? link.querySelector('span').textContent.trim() : link.textContent.trim().replace(link.querySelector('i').textContent, '').trim();
            mainHeaderTitle.textContent = pageTitle;

            if (pageId === 'dashboard') fetchDashboardData();
            if (pageId === 'live-tokens') initializeLiveTokens();
            if (pageId === 'callbacks') fetchCallbackRequests();
            if (pageId === 'messenger') initializeMessenger();
            if (pageId === 'inventory') initializeInventory();
            if (pageId === 'user-management') fetchUsers();
            if (pageId === 'bed-management') initializeBedManagement();
            if (pageId === 'admissions') initializeAdmissions();
            if (pageId === 'labs') initializeLabOrders();
            if (pageId === 'notifications') fetchAndRenderNotifications(document.querySelector('#notifications-page .notification-list-container'));
            if (pageId === 'discharge') initializeDischarge();
            if (pageId === 'billing') initializeBilling();
            if (pageId === 'pharmacy') initializePharmacy();

            if (window.innerWidth <= 992) closeMenu();
        });
    });

    hamburgerBtn.addEventListener('click', (e) => { e.stopPropagation(); toggleMenu(); });
    overlay.addEventListener('click', closeMenu);
    
    const themeToggle = document.getElementById('theme-toggle-checkbox');
    themeToggle.addEventListener('change', function() {
        document.body.classList.toggle('dark-theme', this.checked);
        localStorage.setItem('theme', this.checked ? 'dark-theme' : 'light-theme');
        if (document.getElementById('dashboard-page').classList.contains('active')) {
             fetchDashboardData();
        }
    });
    if (localStorage.getItem('theme') === 'dark-theme') {
        themeToggle.checked = true;
        document.body.classList.add('dark-theme');
    }

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
    
    const notificationBell = document.getElementById('notification-bell');
    const notificationBadge = document.getElementById('notification-badge');
    const notificationPanel = document.getElementById('notification-panel');
    const notificationDropdownBody = notificationPanel.querySelector('.dropdown-body');
    const viewAllNotificationsLink = document.getElementById('view-all-notifications-link');

    const fetchUnreadNotificationCount = async () => {
        try {
            const response = await fetch('api.php?fetch=unread_notification_count');
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
            let senderRole = n.sender_role ? n.sender_role.charAt(0).toUpperCase() + n.sender_role.slice(1) : '';
            const senderDisplay = senderRole ? `${sender} (${senderRole})` : sender;
            const iconClass = 'fas fa-info-circle item-icon announcement';

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
            const response = await fetch(`api.php?fetch=notifications&limit=${limit}`);
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
        // Close notification panel
        if (!notificationPanel.contains(e.target) && !notificationBell.contains(e.target)) {
            notificationPanel.classList.remove('show');
        }
        // Close profile dropdown
        if (userProfileWidget && !userProfileWidget.contains(e.target)) {
            userProfileWidget.classList.remove('active');
        }
        // Close bed status dropdowns
        document.querySelectorAll('.bed-status-dropdown.active').forEach(dropdown => {
            if (!dropdown.previousElementSibling.contains(e.target)) {
                dropdown.classList.remove('active');
            }
        });
    });

    viewAllNotificationsLink.addEventListener('click', (e) => {
        e.preventDefault();
        notificationPanel.classList.remove('show');
        document.querySelector('.nav-link[data-page="notifications"]').click();
    });

    // --- PROFILE DROPDOWN TOGGLE ---
    const userProfileWidget = document.getElementById('user-profile-widget');
    const profileDropdown = document.getElementById('profile-dropdown');

    if (userProfileWidget && profileDropdown) {
        userProfileWidget.addEventListener('click', (e) => {
            e.stopPropagation();
            userProfileWidget.classList.toggle('active');
            // Close notification panel if open
            notificationPanel.classList.remove('show');
        });

        // Handle dropdown item clicks
        profileDropdown.querySelectorAll('.dropdown-item[data-page]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const pageId = item.dataset.page;
                const navLink = document.querySelector(`.nav-link[data-page="${pageId}"]`);
                if (navLink) {
                    navLink.click();
                }
                userProfileWidget.classList.remove('active');
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!userProfileWidget.contains(e.target)) {
                userProfileWidget.classList.remove('active');
            }
        });
    }

    const markAllReadBtn = document.getElementById('mark-all-read-btn');
    if(markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async () => {
             const formData = new FormData();
             formData.append('action', 'markNotificationsRead');
             formData.append('csrf_token', csrfToken);
             try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if(!result.success) throw new Error(result.message);

                await fetchUnreadNotificationCount();
                fetchAndRenderNotifications(document.querySelector('#notifications-page .notification-list-container'));
             } catch(error) {
                 alert('Error: ' + error.message);
             }
        });
    }

    document.getElementById('quick-action-admit')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelector('.nav-link[data-page="bed-management"]').click();
    });
    document.getElementById('quick-action-add-user')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelector('.nav-link[data-page="user-management"]').click();
        setTimeout(() => document.getElementById('add-new-user-btn')?.click(), 100);
    });
    document.getElementById('quick-action-update-inventory')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelector('.nav-link[data-page="inventory"]').click();
    });
    document.getElementById('quick-action-add-bed')?.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelector('.nav-link[data-page="bed-management"]').click();
        setTimeout(() => document.getElementById('add-new-bed-btn')?.click(), 100);
    });

    fetchUnreadNotificationCount();
    setInterval(fetchUnreadNotificationCount, 60000);
    fetchDashboardData();
    setInterval(fetchDashboardData, 90000);

    const callbacksTableBody = document.getElementById('callbacks-table-body');

    async function fetchCallbackRequests() {
        if (!callbacksTableBody) return;
        callbacksTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">Loading requests...</td></tr>`;
        try {
            const response = await fetch('api.php?fetch=callbacks');
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
                    const response = await fetch('api.php', { method: 'POST', body: formData });
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
            const response = await fetch('api.php', { method: 'POST', body: formData });
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
        const conversationData = user.conversation_id ? `data-conversation-id="${user.conversation_id}"` : '';
        const displayMessage = user.last_message || `${user.role} - ${user.display_user_id}`;

        return `
            <div class="search-result-item" data-user-id="${user.id}" data-user-name="${user.name}" data-user-avatar="${avatarUrl}" data-user-display-id="${user.role}" ${conversationData}>
                <img src="${avatarUrl}" alt="${user.name}" class="user-avatar">
                <div class="conversation-details">
                    <span class="user-name">${user.name}</span>
                    <span class="last-message">${displayMessage}</span>
                </div>
            </div>
        `;
    }

    async function fetchAndRenderConversations() {
        const listContainer = document.getElementById('conversation-list-items');
        listContainer.innerHTML = `<p class="no-items-message">Loading conversations...</p>`;

        try {
            const response = await fetch('api.php?fetch=conversations');
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
            const response = await fetch(`api.php?fetch=messages&conversation_id=${conversationId}`);
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
            const response = await fetch('api.php', { method: 'POST', body: formData });
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
                const response = await fetch('api.php?fetch=audit_log');
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

            tableBody.innerHTML = data.map(log => {
                const details = log.target_user_display_id
                    ? `Target: <strong>${log.target_user_name} (${log.target_user_display_id})</strong>. ${log.details}`
                    : log.details;

                return `
                    <tr>
                        <td data-label="Date & Time">${new Date(log.created_at).toLocaleString()}</td>
                        <td data-label="Action"><span class="log-action-update">${log.action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span></td>
                        <td data-label="Details">${details}</td>
                    </tr>
                `;
            }).join('');
        }

        const personalInfoForm = document.getElementById('personal-info-form');
        personalInfoForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const nameInput = document.getElementById('profile-name');
            const emailInput = document.getElementById('profile-email');
            const phoneInput = document.getElementById('profile-phone');
            const dobInput = document.getElementById('profile-dob');
            
            const emailError = document.getElementById('profile-email-error');
            const phoneError = document.getElementById('profile-phone-error');
            const dobError = document.getElementById('profile-dob-error');
            
            let isValid = true;

            [emailError, phoneError, dobError].forEach(el => el.textContent = '');
            [emailInput, phoneInput, dobInput, nameInput].forEach(el => el.classList.remove('is-invalid'));

            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailInput.value)) {
                emailError.textContent = 'Please enter a valid email address.';
                emailInput.classList.add('is-invalid');
                isValid = false;
            }
            
            const phoneRegex = /^\+91\d{10}$/;
            if (phoneInput.value && !phoneRegex.test(phoneInput.value)) {
                phoneError.textContent = 'Format must be +91 followed by 10 digits (e.g., +919876543210).';
                phoneInput.classList.add('is-invalid');
                isValid = false;
            }
            
            if (dobInput.value) {
                const year = new Date(dobInput.value).getFullYear();
                if (isNaN(year) || String(year).length > 4) {
                    dobError.textContent = 'The year in the date of birth is invalid.';
                    dobInput.classList.add('is-invalid');
                    isValid = false;
                } else if (year > new Date().getFullYear() || year < 1900) {
                    dobError.textContent = 'Please enter a realistic year.';
                    dobInput.classList.add('is-invalid');
                    isValid = false;
                }
            }

            if (!isValid) return;

            const formData = new FormData(this);
            formData.append('action', 'updatePersonalInfo');
            formData.append('csrf_token', csrfToken);
            
            const saveButton = this.querySelector('button[type="submit"]');
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
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
                const response = await fetch('api.php', { method: 'POST', body: formData });
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
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message);
                
                if (result.new_image_url) {
                    const newUrl = `${result.new_image_url}?v=${new Date().getTime()}`;
                    document.querySelectorAll('.profile-picture, .editable-profile-picture').forEach(img => img.src = newUrl);
                    // Show remove button since we now have a custom picture
                    removeProfilePictureBtn.style.display = 'flex';
                    showFeedback(personalInfoForm, result.message, true);
                }
            } catch (error) {
                showFeedback(personalInfoForm, error.message, false);
            }
        });

        // --- REMOVE PROFILE PICTURE ---
        const removeProfilePictureBtn = document.getElementById('remove-profile-picture-btn');
        if (removeProfilePictureBtn) {
            removeProfilePictureBtn.addEventListener('click', async () => {
                if (!confirm('Are you sure you want to remove your profile picture?')) return;

                const formData = new FormData();
                formData.append('action', 'removeProfilePicture');
                formData.append('csrf_token', csrfToken);

                try {
                    const response = await fetch('api.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    if (!response.ok || !result.success) throw new Error(result.message);

                    if (result.new_image_url) {
                        const newUrl = `${result.new_image_url}?v=${new Date().getTime()}`;
                        document.querySelectorAll('.profile-picture, .editable-profile-picture').forEach(img => img.src = newUrl);
                        // Hide remove button since we're back to default
                        removeProfilePictureBtn.style.display = 'none';
                        showFeedback(personalInfoForm, result.message, true);
                    }
                } catch (error) {
                    showFeedback(personalInfoForm, error.message, false);
                }
            });
        }

        // --- WEBCAM CAPTURE FUNCTIONALITY ---
        const webcamModal = document.getElementById('webcam-modal');
        const webcamVideo = document.getElementById('webcam-video');
        const webcamCanvas = document.getElementById('webcam-canvas');
        const webcamPreview = document.getElementById('webcam-preview');
        const webcamCapturedImage = document.getElementById('webcam-captured-image');
        const webcamStatus = document.getElementById('webcam-status');
        
        const openWebcamBtn = document.getElementById('open-webcam-btn');
        const closeWebcamModal = document.getElementById('close-webcam-modal');
        const webcamCancelBtn = document.getElementById('webcam-cancel-btn');
        const webcamCaptureBtn = document.getElementById('webcam-capture-btn');
        const webcamRetakeBtn = document.getElementById('webcam-retake-btn');
        const webcamUseBtn = document.getElementById('webcam-use-btn');
        
        let webcamStream = null;
        let capturedBlob = null;

        const updateWebcamStatus = (message, type = 'info') => {
            webcamStatus.className = `webcam-status ${type}`;
            const icon = type === 'error' ? 'fa-exclamation-circle' : type === 'success' ? 'fa-check-circle' : 'fa-info-circle';
            webcamStatus.innerHTML = `<i class="fas ${icon}"></i> <span>${message}</span>`;
        };

        const startWebcam = async () => {
            try {
                updateWebcamStatus('Starting camera...', 'info');
                webcamStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        facingMode: 'user'
                    } 
                });
                webcamVideo.srcObject = webcamStream;
                webcamVideo.style.display = 'block';
                webcamPreview.style.display = 'none';
                webcamCaptureBtn.style.display = 'inline-block';
                webcamRetakeBtn.style.display = 'none';
                webcamUseBtn.style.display = 'none';
                updateWebcamStatus('Camera ready! Click "Capture" to take a photo.', 'success');
            } catch (error) {
                console.error('Webcam error:', error);
                updateWebcamStatus('Unable to access camera. Please check permissions.', 'error');
            }
        };

        const stopWebcam = () => {
            if (webcamStream) {
                webcamStream.getTracks().forEach(track => track.stop());
                webcamStream = null;
                webcamVideo.srcObject = null;
            }
        };

        const capturePhoto = () => {
            const context = webcamCanvas.getContext('2d');
            webcamCanvas.width = webcamVideo.videoWidth;
            webcamCanvas.height = webcamVideo.videoHeight;
            context.drawImage(webcamVideo, 0, 0);
            
            webcamCanvas.toBlob((blob) => {
                capturedBlob = blob;
                const url = URL.createObjectURL(blob);
                webcamCapturedImage.src = url;
                webcamVideo.style.display = 'none';
                webcamPreview.style.display = 'flex';
                webcamCaptureBtn.style.display = 'none';
                webcamRetakeBtn.style.display = 'inline-block';
                webcamUseBtn.style.display = 'inline-block';
                updateWebcamStatus('Photo captured! Use it or retake.', 'success');
            }, 'image/jpeg', 0.9);
        };

        const retakePhoto = () => {
            webcamVideo.style.display = 'block';
            webcamPreview.style.display = 'none';
            webcamCaptureBtn.style.display = 'inline-block';
            webcamRetakeBtn.style.display = 'none';
            webcamUseBtn.style.display = 'none';
            capturedBlob = null;
            updateWebcamStatus('Camera ready! Click "Capture" to take a photo.', 'success');
        };

        const uploadCapturedPhoto = async () => {
            if (!capturedBlob) return;
            
            const formData = new FormData();
            formData.append('profile_picture', capturedBlob, 'webcam-capture.jpg');
            formData.append('action', 'updateProfilePicture');
            formData.append('csrf_token', csrfToken);

            try {
                updateWebcamStatus('Uploading photo...', 'info');
                webcamUseBtn.disabled = true;
                
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (!response.ok || !result.success) throw new Error(result.message);
                
                if (result.new_image_url) {
                    const newUrl = `${result.new_image_url}?v=${new Date().getTime()}`;
                    document.querySelectorAll('.profile-picture, .editable-profile-picture').forEach(img => img.src = newUrl);
                    // Show remove button since we now have a custom picture
                    if (removeProfilePictureBtn) removeProfilePictureBtn.style.display = 'flex';
                    showFeedback(personalInfoForm, 'Profile picture updated successfully!', true);
                }
                
                webcamModal.classList.remove('show');
                stopWebcam();
            } catch (error) {
                updateWebcamStatus(error.message, 'error');
                webcamUseBtn.disabled = false;
            }
        };

        const closeWebcam = () => {
            webcamModal.classList.remove('show');
            stopWebcam();
            capturedBlob = null;
        };

        // Event Listeners
        if (openWebcamBtn) {
            openWebcamBtn.addEventListener('click', () => {
                webcamModal.classList.add('show');
                startWebcam();
            });
        }

        if (closeWebcamModal) closeWebcamModal.addEventListener('click', closeWebcam);
        if (webcamCancelBtn) webcamCancelBtn.addEventListener('click', closeWebcam);
        if (webcamCaptureBtn) webcamCaptureBtn.addEventListener('click', capturePhoto);
        if (webcamRetakeBtn) webcamRetakeBtn.addEventListener('click', retakePhoto);
        if (webcamUseBtn) webcamUseBtn.addEventListener('click', uploadCapturedPhoto);

        // Close modal on outside click
        webcamModal?.addEventListener('click', (e) => {
            if (e.target === webcamModal) closeWebcam();
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
    
    const fetchSpecialities = async () => {
        try {
            const response = await fetch('api.php?fetch=specialities');
            const result = await response.json();
            if (result.success) {
                const specialtySelect = document.getElementById('doctor-specialty');
                specialtySelect.innerHTML = '<option value="">-- Select a Specialty --</option>';
                result.data.forEach(spec => {
                    specialtySelect.innerHTML += `<option value="${spec.id}">${spec.name}</option>`;
                });
            }
        } catch (error) {
            console.error('Failed to fetch specialties:', error);
        }
    };
    
    const renderUsers = (users) => {
        const userTableBody = document.getElementById('users-table')?.querySelector('tbody');
        if (!userTableBody) return;

        if (users.length === 0) {
            userTableBody.innerHTML = `<tr><td colspan="11" style="text-align: center;">No users found.</td></tr>`;
            return;
        }
        userTableBody.innerHTML = users.map(user => {
            // Format profile picture
            const profilePic = user.profile_picture && user.profile_picture !== 'default.png' 
                ? `../uploads/profile_pictures/${user.profile_picture}` 
                : '../uploads/profile_pictures/default.png';
            
            // Format gender with icon
            const genderIcon = user.gender === 'Male' ? '♂️' : user.gender === 'Female' ? '♀️' : '⚧';
            const genderDisplay = user.gender ? `${user.gender} ${genderIcon}` : 'N/A';
            
            // Format age and DOB
            const age = user.age || 'N/A';
            const dob = user.date_of_birth ? new Date(user.date_of_birth).toLocaleDateString('en-GB') : 'N/A';
            
            // Format phone
            const phone = user.phone || 'N/A';
            
            // Format last active
            let lastActive = 'Never';
            if (user.last_active) {
                const lastActiveDate = new Date(user.last_active);
                const now = new Date();
                const diffMs = now - lastActiveDate;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);
                
                if (diffMins < 60) {
                    lastActive = `${diffMins} min${diffMins !== 1 ? 's' : ''} ago`;
                } else if (diffHours < 24) {
                    lastActive = `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`;
                } else if (diffDays < 30) {
                    lastActive = `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`;
                } else {
                    lastActive = lastActiveDate.toLocaleDateString('en-GB');
                }
            }
            
            return `
            <tr data-user='${JSON.stringify(user)}'>
                <td data-label="Photo">
                    <img src="${profilePic}" alt="${user.name}" 
                         style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0;"
                         onerror="this.src='../uploads/profile_pictures/default.png'">
                </td>
                <td data-label="User ID">${user.display_user_id}</td>
                <td data-label="Name"><strong>${user.name}</strong></td>
                <td data-label="Gender">${genderDisplay}</td>
                <td data-label="Age/DOB">
                    ${age} yrs<br>
                    <small style="color: #666;">${dob}</small>
                </td>
                <td data-label="Phone">${phone}</td>
                <td data-label="Role">${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</td>
                <td data-label="Email">${user.email}</td>
                <td data-label="Status">
                    <span class="status ${user.active == 1 ? 'admitted' : 'unpaid'}">${user.active == 1 ? 'Active' : 'Inactive'}</span>
                </td>
                <td data-label="Last Active">
                    <small style="color: #666;">${lastActive}</small>
                </td>
                <td data-label="Actions">
                    <button class="action-btn edit-user-btn"><i class="fas fa-edit"></i> Edit</button> 
                    ${(user.role !== 'admin' && user.role !== 'staff') ? 
                        (user.active == 1
                            ? `<button class="action-btn danger remove-user-btn"><i class="fas fa-ban"></i> Deactivate</button>`
                            : `<button class="action-btn reactivate-user-btn"><i class="fas fa-check-circle"></i> Reactivate</button>`
                        ) :
                        `<span style="font-size: 0.85em; color: #999; font-style: italic;">Protected Account</span>`
                    }
                </td>
            </tr>
        `;
        }).join('');
    };

    const fetchUsers = async () => {
        const userTableBody = document.getElementById('users-table')?.querySelector('tbody');
        if (!userTableBody) return;

        const roleFilter = document.getElementById('user-role-filter');
        const statusFilter = document.getElementById('user-status-filter');
        const searchInput = document.getElementById('user-search');
        const role = roleFilter.value;
        const status = statusFilter.value;
        const search = searchInput.value;
        userTableBody.innerHTML = `<tr><td colspan="11" style="text-align: center;">Loading users...</td></tr>`;

        try {
            const response = await fetch(`api.php?fetch=get_users&role=${role}&status=${status}&search=${encodeURIComponent(search)}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderUsers(result.data);
        } catch (error) {
            userTableBody.innerHTML = `<tr><td colspan="11" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
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
        
        const openUserModal = async (mode, userData = {}) => {
            userForm.reset();
            userForm.querySelector('#user-username').disabled = false;
            userForm.querySelector('#user-email').disabled = false; // Re-enable for add mode
            userForm.querySelector('#user-role').disabled = false;
            doctorFields.style.display = 'none';
            activeGroup.style.display = 'none';
        
            if (mode === 'add') {
                userModalTitle.textContent = 'Add New User';
                userForm.querySelector('#user-form-action').value = 'addUser';
                passwordGroup.style.display = 'block';
                userForm.querySelector('#user-password').required = true;
                
                // Staff can only create patient and doctor accounts
                const roleSelect = userForm.querySelector('#user-role');
                roleSelect.innerHTML = `
                    <option value="">Select Role</option>
                    <option value="user">Patient</option>
                    <option value="doctor">Doctor</option>
                `;
            } else {
                userModalTitle.textContent = `Edit ${userData.name}`;
                userForm.querySelector('#user-form-action').value = 'updateUser';
                userForm.querySelector('#user-id').value = userData.id;
                userForm.querySelector('#user-name').value = userData.name;
                userForm.querySelector('#user-username').value = userData.username;
                userForm.querySelector('#user-username').disabled = true; // Username cannot be changed
                userForm.querySelector('#user-email').value = userData.email;
                // Email is now editable for staff
                userForm.querySelector('#user-phone').value = userData.phone || '';
                userForm.querySelector('#user-gender').value = userData.gender || '';
                userForm.querySelector('#user-dob').value = userData.date_of_birth || '';
                userForm.querySelector('#user-role').value = userData.role;
                userForm.querySelector('#user-role').disabled = true; // Role cannot be changed
                passwordGroup.style.display = 'none';
                userForm.querySelector('#user-password').required = false;
        
                if (userData.role === 'doctor') {
                    doctorFields.style.display = 'block';
                    await fetchSpecialities();
                    userForm.querySelector('#doctor-specialty').value = userData.specialty_id || '';
                }
                activeGroup.style.display = 'block';
                userForm.querySelector('#user-active').value = userData.active;
            }
            userModal.classList.add('show');
        };

        userForm.querySelector('#user-role').addEventListener('change', (e) => {
            if (e.target.value === 'doctor') {
                doctorFields.style.display = 'block';
                fetchSpecialities();
            } else {
                doctorFields.style.display = 'none';
            }
        });

        addUserBtn.addEventListener('click', () => openUserModal('add'));
        userModal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => userModal.classList.remove('show')));
        
        userTableBody.addEventListener('click', (e) => {
            if(e.target.closest('.edit-user-btn')) {
                const row = e.target.closest('tr');
                const userData = JSON.parse(row.dataset.user);
                
                // Check if user is allowed to edit this account type
                if (userData.role === 'admin' || userData.role === 'staff') {
                    showAlert('Permission Denied', `You are not authorized to edit ${userData.role} accounts. Only patients and doctors can be edited by staff.`, 'warning');
                    return;
                }
                
                openUserModal('edit', userData);
            }
            if(e.target.closest('.remove-user-btn')) {
                const row = e.target.closest('tr');
                const userData = JSON.parse(row.dataset.user);
                
                // Additional permission check
                if (userData.role === 'admin' || userData.role === 'staff') {
                    showAlert('Permission Denied', `You are not authorized to deactivate ${userData.role} accounts.`, 'warning');
                    return;
                }
                
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
            if(e.target.closest('.reactivate-user-btn')) {
                const row = e.target.closest('tr');
                const userData = JSON.parse(row.dataset.user);
                
                // Additional permission check
                if (userData.role === 'admin' || userData.role === 'staff') {
                    showAlert('Permission Denied', `You are not authorized to reactivate ${userData.role} accounts.`, 'warning');
                    return;
                }
                
                showConfirmation('Reactivate User', `Are you sure you want to reactivate ${userData.name}? Their account will become active.`)
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
            if(e.target.closest('.reactivate-user-btn')) {
                const row = e.target.closest('tr');
                const userData = JSON.parse(row.dataset.user);
                showConfirmation('Reactivate User', `Are you sure you want to reactivate ${userData.name}? Their account will become active.`)
                    .then(confirmed => {
                        if (confirmed) {
                           const formData = new FormData();
                           formData.append('action', 'reactivateUser');
                           formData.append('id', userData.id);
                           formData.append('csrf_token', csrfToken);
                           handleUserFormSubmit(formData);
                        }
                    });
            }
        });
        
        userForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const phoneInput = document.getElementById('user-phone');
            const dobInput = document.getElementById('user-dob');
            const phoneError = document.getElementById('user-phone-error');
            const dobError = document.getElementById('user-dob-error');
            let isValid = true;
            phoneInput.classList.remove('is-invalid');
            dobInput.classList.remove('is-invalid');
            phoneError.textContent = '';
            dobError.textContent = '';
            const phoneRegex = /^\+91\d{10}$/;
            if (phoneInput.value && !phoneRegex.test(phoneInput.value)) {
                phoneError.textContent = 'Format must be +91 followed by 10 digits.';
                phoneInput.classList.add('is-invalid');
                isValid = false;
            }
            if (dobInput.value) {
                const year = dobInput.value.split('-')[0];
                if (year.length > 4) {
                    dobError.textContent = 'The year in the date of birth is invalid.';
                    dobInput.classList.add('is-invalid');
                    isValid = false;
                }
            }
            if (!isValid) return;
            const formData = new FormData(userForm);
            formData.append('csrf_token', csrfToken);
            handleUserFormSubmit(formData);
        });

        const handleUserFormSubmit = async (formData) => {
            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
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
        
        // Add status filter event listener
        const statusFilter = document.getElementById('user-status-filter');
        if (statusFilter) {
            statusFilter.addEventListener('change', fetchUsers);
        }
        
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
                const response = await fetch(`api.php?fetch=medicines&search=${encodeURIComponent(search)}`);
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
                const response = await fetch('api.php?fetch=blood_inventory');
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
                const response = await fetch('api.php', { method: 'POST', body: formData });
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

    // --- BED MANAGEMENT LOGIC (REVISED) ---
    const bedManagementPage = document.getElementById('bed-management-page');
    let bedManagementInitialized = false;
    let bedManagementData = {};
    let bedSearchDebounce;
    let bedAutoRefreshInterval;
    let bedAutoRefreshEnabled = true;
    let currentBedFilter = 'all';

    async function initializeBedManagement() {
        if (bedManagementInitialized) {
            await fetchAndRenderBedData();
            return;
        }

        if (!bedManagementPage) return;

        await fetchAndRenderBedData();

        // Refresh button
        document.getElementById('refresh-beds-btn').addEventListener('click', () => {
            fetchAndRenderBedData();
        });

        // Auto-refresh toggle
        document.getElementById('toggle-bed-auto-refresh').addEventListener('click', (e) => {
            bedAutoRefreshEnabled = !bedAutoRefreshEnabled;
            e.target.classList.toggle('active', bedAutoRefreshEnabled);
            e.target.innerHTML = bedAutoRefreshEnabled 
                ? '<i class="fas fa-play"></i> Auto-refresh ON' 
                : '<i class="fas fa-pause"></i> Auto-refresh OFF';
            
            if (bedAutoRefreshEnabled) {
                bedAutoRefreshInterval = setInterval(() => fetchAndRenderBedData(true), 30000);
            } else {
                clearInterval(bedAutoRefreshInterval);
            }
        });

        // Start auto-refresh
        if (bedAutoRefreshEnabled) {
            bedAutoRefreshInterval = setInterval(() => fetchAndRenderBedData(true), 30000);
        }

        // Quick filter buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.bed-filter-btn')) {
                const filterBtn = e.target.closest('.bed-filter-btn');
                const filter = filterBtn.dataset.bedFilter;
                
                document.querySelectorAll('.bed-filter-btn').forEach(btn => btn.classList.remove('active'));
                filterBtn.classList.add('active');
                
                currentBedFilter = filter;
                applyBedFilter(filter);
            }
        });

        document.getElementById('add-new-bed-btn').addEventListener('click', () => openBedModal('add'));
        document.getElementById('bed-location-filter').addEventListener('change', filterBedGrid);
        document.getElementById('bed-search-filter').addEventListener('input', () => {
             clearTimeout(bedSearchDebounce);
             bedSearchDebounce = setTimeout(filterBedGrid, 300);
        });

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

        const bedGridContainer = document.getElementById('bed-grid-container');
        bedGridContainer.addEventListener('click', (e) => {
            const card = e.target.closest('.bed-card');
            if (!card) return;

            const entity = JSON.parse(card.dataset.entity);
            
            if (e.target.closest('.edit-bed-btn')) {
                openBedModal('edit', entity);
            } else if (e.target.closest('.manage-occupancy-btn')) {
                openAssignModal(entity);
            } else if (e.target.closest('.status-change-btn')) {
                e.stopPropagation();
                const dropdown = e.target.closest('.bed-actions').querySelector('.bed-status-dropdown');
                if (dropdown) dropdown.classList.toggle('active');
            } else if (e.target.closest('.status-option')) {
                const newStatus = e.target.closest('.status-option').dataset.status;
                handleDirectStatusChange(entity.id, entity.type, newStatus);
            }
        });

        bedManagementInitialized = true;
    }

    async function fetchAndRenderBedData(silent = false) {
        const gridContainer = document.getElementById('bed-grid-container');
        if (!silent) {
            gridContainer.innerHTML = '<p class="no-items-message">Loading bed data...</p>';
        }
        try {
            const response = await fetch('api.php?fetch=bed_management_data');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            bedManagementData = result.data;
            renderBedManagementPage(bedManagementData);
            updateBedStatistics(bedManagementData);
            applyBedFilter(currentBedFilter);
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
        
        const allAccommodations = [...data.beds, ...data.rooms].map(renderBedCard).join('');
        gridContainer.innerHTML = allAccommodations;
        filterBedGrid();
    }

    function renderBedCard(entity) {
        const isBed = entity.type === 'bed';
        const number = entity.number;
        const locationName = isBed ? entity.ward_name : 'Private Room';
        const locationFilterValue = isBed ? `ward-${entity.ward_id}` : 'rooms';

        const cleaningCheckbox = entity.status === 'cleaning'
            ? `<div class="bed-card-checkbox"><input type="checkbox" class="bed-selection-checkbox" data-id="${entity.id}"></div>`
            : '';

        // Status badge
        const statusText = entity.status.charAt(0).toUpperCase() + entity.status.slice(1);
        const statusBadge = `<span class="bed-status-badge ${entity.status}">${statusText}</span>`;

        let patientInfo = '';
        if (entity.status === 'occupied' && entity.patient_name) {
            let doctorDisplay = entity.doctor_name ? `<br><small style="color: var(--text-muted);"><i class="fas fa-user-md"></i> Dr. ${entity.doctor_name}</small>` : '';
            patientInfo = `<div class="patient-info" style="border-top: 1px solid var(--border-color); padding-top: 0.75rem; margin-top: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 600;">
                    <i class="fas fa-user-circle" style="color: var(--primary-color);"></i> 
                    ${entity.patient_name}
                </div>
                <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">${entity.patient_display_id}</small>
                ${doctorDisplay}
            </div>`;
        }

        const statusChangeDropdown = entity.status !== 'occupied' ? `
            <div class="bed-status-dropdown">
                ${entity.status !== 'available' ? `<div class="status-option" data-status="available"><i class="fas fa-check-circle"></i> Available</div>` : ''}
                ${entity.status !== 'cleaning' ? `<div class="status-option" data-status="cleaning"><i class="fas fa-broom"></i> Cleaning</div>` : ''}
                ${entity.status !== 'reserved' ? `<div class="status-option" data-status="reserved"><i class="fas fa-user-clock"></i> Reserved</div>` : ''}
            </div>
        ` : '';

        return `
            <div class="bed-card status-${entity.status}" 
                 data-status="${entity.status}" 
                 data-location="${locationFilterValue}"
                 data-entity='${JSON.stringify(entity)}'
                 data-search-terms="${number} ${entity.patient_name || ''} ${entity.doctor_name || ''}"
                 data-type="${entity.type}">
                
                ${cleaningCheckbox}
                <div class="bed-card-header">
                    <div class="bed-id">
                        <div style="font-size: 1.2rem; font-weight: 700;">${number}</div>
                        ${statusBadge}
                    </div>
                    <div class="bed-actions">
                        ${entity.status !== 'occupied' ? '<button class="action-btn-icon status-change-btn" title="Change Status"><i class="fas fa-exchange-alt"></i></button>' : ''}
                        ${statusChangeDropdown}
                        <button class="action-btn-icon manage-occupancy-btn" title="Manage Occupancy"><i class="fas fa-user-plus"></i></button>
                        <button class="action-btn-icon edit-bed-btn" title="Edit Details"><i class="fas fa-pencil-alt"></i></button>
                    </div>
                </div>
                <div class="bed-details" style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem;">
                    <i class="fas fa-map-marker-alt"></i> ${locationName}
                </div>
                ${patientInfo}
            </div>
        `;
    }

    function filterBedGrid() {
        const locationFilter = document.getElementById('bed-location-filter').value;
        const searchQuery = document.getElementById('bed-search-filter').value.toLowerCase();
        const cards = document.querySelectorAll('#bed-grid-container .bed-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const showLocation = (locationFilter === 'all') || (card.dataset.location === locationFilter);
            const showFilter = (currentBedFilter === 'all') || (card.dataset.status === currentBedFilter);
            const showSearch = (searchQuery === '') || (card.dataset.searchTerms.toLowerCase().includes(searchQuery));
            
            if (showLocation && showFilter && showSearch) {
                card.classList.remove('filtered-out');
                card.style.display = 'flex';
                visibleCount++;
            } else {
                card.classList.add('filtered-out');
                card.style.display = 'none';
            }
        });

        const gridContainer = document.getElementById('bed-grid-container');
        let noItemsMsg = gridContainer.querySelector('.no-items-message');
        if (noItemsMsg) noItemsMsg.remove();
        if (visibleCount === 0) {
            gridContainer.insertAdjacentHTML('beforeend', '<p class="no-items-message">No beds match the current filters.</p>');
        }
    }

    function applyBedFilter(filter) {
        const cards = document.querySelectorAll('#bed-grid-container .bed-card');
        
        cards.forEach(card => {
            if (filter === 'all') {
                card.classList.remove('filtered-out');
            } else {
                if (card.dataset.status === filter) {
                    card.classList.remove('filtered-out');
                } else {
                    card.classList.add('filtered-out');
                }
            }
        });
        
        filterBedGrid(); // Also apply search and location filters
    }

    function updateBedStatistics(data) {
        let total = 0;
        let available = 0;
        let occupied = 0;
        let cleaning = 0;
        let reserved = 0;
        
        data.beds.forEach(bed => {
            total++;
            if (bed.status === 'available') available++;
            else if (bed.status === 'occupied') occupied++;
            else if (bed.status === 'cleaning') cleaning++;
            else if (bed.status === 'reserved') reserved++;
        });
        
        data.rooms.forEach(room => {
            total++;
            if (room.status === 'available') available++;
            else if (room.status === 'occupied') occupied++;
            else if (room.status === 'cleaning') cleaning++;
            else if (room.status === 'reserved') reserved++;
        });
        
        const availablePercent = total > 0 ? ((available / total) * 100).toFixed(1) : 0;
        const occupiedPercent = total > 0 ? ((occupied / total) * 100).toFixed(1) : 0;
        const cleaningPercent = total > 0 ? ((cleaning / total) * 100).toFixed(1) : 0;
        const reservedPercent = total > 0 ? ((reserved / total) * 100).toFixed(1) : 0;
        
        document.getElementById('bed-stat-total').textContent = total;
        document.getElementById('bed-stat-available').textContent = available;
        document.getElementById('bed-stat-available-percent').textContent = availablePercent + '%';
        document.getElementById('bed-stat-occupied').textContent = occupied;
        document.getElementById('bed-stat-occupied-percent').textContent = occupiedPercent + '%';
        document.getElementById('bed-stat-cleaning').textContent = cleaning;
        document.getElementById('bed-stat-cleaning-percent').textContent = cleaningPercent + '%';
        document.getElementById('bed-stat-reserved').textContent = reserved;
        document.getElementById('bed-stat-reserved-percent').textContent = reservedPercent + '%';
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
        } else {
            const type = entity.type;
            const number = entity.number;
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
        
        if (form.querySelector('#bed-form-id').value) {
            const type = document.getElementById('bed-form-type').value;
            formData.append('type', type);
        }

        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
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

        const clearSelection = (type) => {
            document.getElementById(`bed-assign-selected-${type}`).style.display = 'none';
            document.getElementById(`bed-assign-${type}-search`).style.display = 'block';
            document.getElementById(`bed-assign-${type}-search`).value = '';
            document.getElementById(`bed-assign-${type}-id`).value = '';
        };

        clearSelection('patient');
        clearSelection('doctor');

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
        
        const patientSearchInput = document.getElementById('bed-assign-patient-search');
        const patientResultsContainer = document.getElementById('bed-assign-patient-results');
        let patientDebounce;

        const patientSearchHandler = () => {
            clearTimeout(patientDebounce);
            patientDebounce = setTimeout(async () => {
                const query = patientSearchInput.value;
                if (query.length < 2) {
                    patientResultsContainer.style.display = 'none';
                    return;
                }
                const response = await fetch(`api.php?fetch=search_patients&query=${encodeURIComponent(query)}`);
                const result = await response.json();
                if (result.success) {
                    patientResultsContainer.innerHTML = result.data.map(p =>
                        `<div class="search-result-item" data-id="${p.id}" data-name="${p.name} (${p.display_user_id})">${p.name} (${p.display_user_id})</div>`
                    ).join('');
                    patientResultsContainer.style.display = 'block';
                }
            }, 300);
        };
        patientSearchInput.addEventListener('input', patientSearchHandler);

        patientResultsContainer.addEventListener('click', (e) => {
            const item = e.target.closest('.search-result-item');
            if (item) {
                document.getElementById('bed-assign-patient-id').value = item.dataset.id;
                document.getElementById('bed-assign-selected-patient-name').textContent = item.dataset.name;
                document.getElementById('bed-assign-selected-patient').style.display = 'flex';
                patientSearchInput.style.display = 'none';
                patientResultsContainer.style.display = 'none';
            }
        });
        
        document.getElementById('bed-assign-clear-patient-btn').addEventListener('click', () => clearSelection('patient'));
        
        const doctorSearchInput = document.getElementById('bed-assign-doctor-search');
        const doctorResultsContainer = document.getElementById('bed-assign-doctor-results');
        let doctorDebounce;

        const doctorSearchHandler = () => {
            clearTimeout(doctorDebounce);
            doctorDebounce = setTimeout(async () => {
                const query = doctorSearchInput.value;
                if (query.length < 2) {
                    doctorResultsContainer.style.display = 'none';
                    return;
                }
                const response = await fetch(`api.php?fetch=active_doctors&search=${encodeURIComponent(query)}`);
                const result = await response.json();
                if (result.success) {
                    doctorResultsContainer.innerHTML = result.data.map(d =>
                        `<div class="search-result-item" data-id="${d.id}" data-name="${d.name} (${d.display_user_id})">${d.name} (${d.display_user_id})</div>`
                    ).join('');
                    doctorResultsContainer.style.display = 'block';
                }
            }, 300);
        };
        doctorSearchInput.addEventListener('input', doctorSearchHandler);

        doctorResultsContainer.addEventListener('click', (e) => {
            const item = e.target.closest('.search-result-item');
            if (item) {
                document.getElementById('bed-assign-doctor-id').value = item.dataset.id;
                document.getElementById('bed-assign-selected-doctor-name').textContent = item.dataset.name;
                document.getElementById('bed-assign-selected-doctor').style.display = 'flex';
                doctorSearchInput.style.display = 'none';
                doctorResultsContainer.style.display = 'none';
            }
        });

        document.getElementById('bed-assign-clear-doctor-btn').addEventListener('click', () => clearSelection('doctor'));

        if (entity.status === 'available' || entity.status === 'cleaning' || entity.status === 'reserved') {
            assignSection.style.display = 'block';
            dischargeSection.style.display = 'none';
            submitBtn.style.display = 'inline-flex';
            submitBtn.textContent = 'Assign Patient';
            dischargeBtn.style.display = 'none';
        } else if (entity.status === 'occupied') {
            assignSection.style.display = 'none';
            dischargeSection.style.display = 'block';
            submitBtn.style.display = 'none';
            dischargeBtn.style.display = 'inline-flex';
            document.getElementById('bed-assign-patient-name').textContent = `${entity.patient_name} (${entity.patient_display_id})`;
            document.getElementById('bed-assign-doctor-name').textContent = entity.doctor_name || 'N/A';
        }

        modal.classList.add('show');
    }
    
    async function handleDirectStatusChange(id, type, newStatus) {
        const formData = new FormData();
        formData.append('action', 'updateBedOrRoom');
        formData.append('id', id);
        formData.append('type', type);
        formData.append('status', newStatus);
        formData.append('csrf_token', csrfToken);
        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            await fetchAndRenderBedData();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    async function handleAssignFormSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const modal = form.closest('.modal-overlay');
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);
        formData.append('status', 'occupied');

        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
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
        modal.classList.remove('show');
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
        formData.append('patient_id', '');
        formData.append('doctor_id', '');
        formData.append('status', 'cleaning');

        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
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
            const response = await fetch(`api.php?fetch=admissions&search=${encodeURIComponent(search)}`);
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
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No admissions found matching your criteria.</td></tr>`;
            return;
        }

        tableBody.innerHTML = admissions.map(adm => {
            const status = adm.discharge_date 
                ? '<span class="status completed">Discharged</span>' 
                : '<span class="status admitted">Admitted</span>';
            
            const admissionDate = new Date(adm.admission_date).toLocaleDateString('en-CA');
            const dischargeDate = adm.discharge_date ? new Date(adm.discharge_date).toLocaleDateString('en-CA') : 'N/A';
            const location = adm.location ? `${adm.location_type} ${adm.location}` : 'N/A';

            return `
                <tr>
                    <td data-label="Adm. ID">ADM-${String(adm.id).padStart(4, '0')}</td>
                    <td data-label="Patient Name">${adm.patient_name} (${adm.patient_display_id})</td>
                    <td data-label="Location">${location}</td>
                    <td data-label="Admitted On">${admissionDate}</td>
                    <td data-label="Discharged On">${dischargeDate}</td> <td data-label="Status">${status}</td>
                </tr>
            `;
        }).join('');
    }
    // --- LAB WORKFLOW UPDATE ---
    const labsPage = document.getElementById('labs-page');
    let labOrdersInitialized = false;
    let labOrderSearchDebounce;
    let labPatientSearchDebounce;
    let labFormData = null;

    function initializeLabOrders() {
        if (labOrdersInitialized || !labsPage) return;
        
        const createFindingRow = (finding = { parameter: '', result: '', range: '' }) => {
            const row = document.createElement('div');
            row.className = 'form-grid finding-row';
            row.style.marginBottom = '10px';
            row.innerHTML = `
                <input type="text" class="finding-parameter" placeholder="Parameter" value="${finding.parameter || ''}">
                <input type="text" class="finding-result" placeholder="Result" value="${finding.result || ''}">
                <div style="display: flex; align-items: center;">
                    <input type="text" class="finding-range" placeholder="Reference Range" value="${finding.range || ''}" style="flex-grow:1;">
                    <button type="button" class="action-btn danger" onclick="this.closest('.finding-row').remove()" style="margin-left: 5px; padding: 0.5rem;">&times;</button>
                </div>
            `;
            document.getElementById('lab-findings-container').appendChild(row);
        };

        const searchInput = document.getElementById('lab-search');
        const statusFilter = document.getElementById('lab-status-filter');

        const triggerLabSearch = () => {
            clearTimeout(labOrderSearchDebounce);
            labOrderSearchDebounce = setTimeout(() => {
                fetchAndRenderLabOrders(searchInput.value, statusFilter.value);
            }, 300);
        };

        searchInput.addEventListener('input', triggerLabSearch);
        statusFilter.addEventListener('change', () => fetchAndRenderLabOrders(searchInput.value, statusFilter.value));
        
        document.getElementById('add-walkin-lab-order-btn').addEventListener('click', () => openLabOrderModal('add'));
        
        const modal = document.getElementById('lab-order-modal');
        modal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => modal.classList.remove('show')));
        document.getElementById('lab-order-form').addEventListener('submit', handleLabOrderFormSubmit);
        
        const tableBody = document.getElementById('lab-orders-table')?.querySelector('tbody');
        tableBody.addEventListener('click', e => {
            const row = e.target.closest('tr');
            if (!row) return;

            const labData = JSON.parse(row.dataset.labOrder);

            if (e.target.closest('.edit-lab-order-btn')) {
                openLabOrderModal('edit', labData);
            }
            if (e.target.closest('.remove-lab-order-btn')) {
                showConfirmation('Delete Lab Order', `Are you sure you want to delete the lab order for ${labData.patient_name} (Test: ${labData.test_name})? This is permanent.`)
                    .then(confirmed => {
                        if (confirmed) handleRemoveLabOrder(labData.id);
                    });
            }
        });
        
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
        document.getElementById('add-finding-btn').addEventListener('click', () => createFindingRow());
        
        const doctorSearchInput = document.getElementById('lab-doctor-search');
        const doctorSearchResults = document.getElementById('doctor-search-results');
        let labDoctorSearchDebounce;

        doctorSearchInput.addEventListener('input', () => {
             clearTimeout(labDoctorSearchDebounce);
             labDoctorSearchDebounce = setTimeout(() => handleLabDoctorSearch(doctorSearchInput.value), 300);
        });
        
        doctorSearchResults.addEventListener('click', (e) => {
            const item = e.target.closest('.search-result-item');
            if(item) {
                selectLabDoctor(item.dataset.id, item.dataset.name);
            }
        });

        document.getElementById('clear-selected-doctor-btn').addEventListener('click', clearSelectedLabDoctor);

        // Add file size validation for lab attachment
        const labAttachmentInput = document.getElementById('lab-attachment');
        if (labAttachmentInput) {
            labAttachmentInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    // Check file size (5MB max)
                    if (file.size > 5242880) {
                        alert('File is too large. Maximum size is 5MB.');
                        e.target.value = ''; // Clear the file input
                        return;
                    }
                    // Check file type
                    if (file.type !== 'application/pdf') {
                        alert('Invalid file type. Only PDF files are allowed.');
                        e.target.value = ''; // Clear the file input
                        return;
                    }
                }
            });
        }

        fetchAndRenderLabOrders(searchInput.value, statusFilter.value);
        labOrdersInitialized = true;
    }

    async function fetchAndRenderLabOrders(search = '', status = 'all') {
        const tableBody = document.getElementById('lab-orders-table')?.querySelector('tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center;">Loading lab orders...</td></tr>`;

        try {
            const response = await fetch(`api.php?fetch=lab_orders&search=${encodeURIComponent(search)}&status=${status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderLabOrders(result.data);
        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
        }
    }

    function renderLabOrders(data) {
        const tableBody = document.getElementById('lab-orders-table')?.querySelector('tbody');
        if (!tableBody) return;

        if (data.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="8" style="text-align: center;">No lab orders found.</td></tr>`;
            return;
        }

        tableBody.innerHTML = data.map(order => {
            let statusClass = order.status;
            const statusText = order.status.charAt(0).toUpperCase() + order.status.slice(1);
            const status = `<span class="status ${statusClass}">${statusText}</span>`;
            
            const reportLink = order.attachment_path
                ? `<a href="report/${order.attachment_path}" target="_blank" class="action-btn" download><i class="fas fa-download"></i> Download</a>`
                : '<span>N/A</span>';
            
            // Format patient age and DOB
            const age = order.patient_age || 'N/A';
            const dob = order.patient_dob ? new Date(order.patient_dob).toLocaleDateString('en-GB') : 'N/A';
            const gender = order.patient_gender || 'N/A';
            const genderIcon = gender === 'Male' ? '♂️' : gender === 'Female' ? '♀️' : '⚧';
            
            // Format phone number
            const phone = order.patient_phone ? `<br><small style="color: #666;"><i class="fas fa-phone"></i> ${order.patient_phone}</small>` : '';
            
            return `
                <tr data-lab-order='${JSON.stringify(order)}'>
                    <td data-label="Order ID">ORD-${String(order.id).padStart(5, '0')}</td>
                    <td data-label="Patient Info">
                        <strong>${order.patient_name}</strong><br>
                        <small style="color: #666;">ID: ${order.patient_display_id}</small>
                        ${phone}
                    </td>
                    <td data-label="Age/Gender">
                        ${age} yrs ${genderIcon}<br>
                        <small style="color: #666;">DOB: ${dob}</small>
                    </td>
                    <td data-label="Test">${order.test_name}</td>
                    <td data-label="Cost">₹${parseFloat(order.cost || 0).toFixed(2)}</td>
                    <td data-label="Status">${status}</td>
                    <td data-label="Report">${reportLink}</td>
                    <td data-label="Actions">
                        <button class="action-btn edit-lab-order-btn"><i class="fas fa-edit"></i> Edit</button>
                        <button class="action-btn danger remove-lab-order-btn"><i class="fas fa-trash-alt"></i></button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    async function openLabOrderModal(mode, data = {}) {
        const modal = document.getElementById('lab-order-modal');
        const form = document.getElementById('lab-order-form');
        const title = document.getElementById('lab-modal-title');
        const patientInfoDisplay = document.getElementById('patient-info-display');
        
        form.reset();
        clearSelectedPatient();
        clearSelectedLabDoctor();
        document.getElementById('current-attachment-info').innerHTML = '';
        document.getElementById('lab-findings-container').innerHTML = '';
        patientInfoDisplay.style.display = 'none'; // Hide patient info by default
    
        const createFindingRow = (finding = { parameter: '', result: '', range: '' }) => {
            const row = document.createElement('div');
            row.className = 'form-grid finding-row';
            row.style.marginBottom = '10px';
            row.innerHTML = `
                <input type="text" class="finding-parameter" placeholder="Parameter" value="${finding.parameter || ''}">
                <input type="text" class="finding-result" placeholder="Result" value="${finding.result || ''}">
                <div style="display: flex; align-items: center;">
                    <input type="text" class="finding-range" placeholder="Reference Range" value="${finding.range || ''}" style="flex-grow:1;">
                    <button type="button" class="action-btn danger" onclick="this.closest('.finding-row').remove()" style="margin-left: 5px; padding: 0.5rem;">&times;</button>
                </div>
            `;
            document.getElementById('lab-findings-container').appendChild(row);
        };
    
        if (mode === 'add') {
            title.textContent = 'Add Walk-in Lab Order';
            document.getElementById('lab-form-action').value = 'addLabOrder';
            document.getElementById('lab-order-id').value = '';
        } else {
            title.textContent = `Manage Lab Order for ${data.patient_name}`;
            document.getElementById('lab-form-action').value = 'updateLabOrder';
            document.getElementById('lab-order-id').value = data.id;
            
            // Display patient information
            if (data.patient_name) {
                patientInfoDisplay.style.display = 'block';
                document.getElementById('display-patient-name').textContent = data.patient_name || 'N/A';
                document.getElementById('display-patient-id').textContent = data.patient_display_id || 'N/A';
                document.getElementById('display-patient-age').textContent = data.patient_age ? `${data.patient_age} years` : 'N/A';
                document.getElementById('display-patient-gender').textContent = data.patient_gender || 'N/A';
                
                // Format DOB
                const dob = data.patient_dob ? new Date(data.patient_dob).toLocaleDateString('en-GB') : 'N/A';
                document.getElementById('display-patient-dob').textContent = dob;
                document.getElementById('display-patient-phone').textContent = data.patient_phone || 'N/A';
            }
            
            if(data.patient_id && data.patient_name) {
                selectPatient(data.patient_id, `${data.patient_name} (${data.patient_display_id})`);
            }
    
            if (data.doctor_id && data.doctor_name) {
                selectLabDoctor(data.doctor_id, data.doctor_name);
            }
    
            document.getElementById('lab-status').value = data.status || 'pending';
            document.getElementById('lab-test-name').value = data.test_name;
            document.getElementById('lab-test-date').value = data.test_date;
            document.getElementById('lab-cost').value = parseFloat(data.cost || 0).toFixed(2);
            
            document.getElementById('lab-summary').value = '';
    
            try {
                const details = JSON.parse(data.result_details);
                if (details && typeof details === 'object') {
                    document.getElementById('lab-summary').value = details.summary || '';
                    if (Array.isArray(details.findings)) {
                        details.findings.forEach(finding => createFindingRow(finding));
                    }
                } else {
                    document.getElementById('lab-summary').value = data.result_details || '';
                }
            } catch (e) {
                document.getElementById('lab-summary').value = data.result_details || '';
            }
    
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
            const response = await fetch(`api.php?fetch=search_patients&query=${encodeURIComponent(query)}`);
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

    async function handleLabDoctorSearch(query) {
        const resultsContainer = document.getElementById('doctor-search-results');
        if (query.length < 2) {
            resultsContainer.innerHTML = '';
            resultsContainer.style.display = 'none';
            return;
        }
    
        resultsContainer.innerHTML = '<div class="search-result-item">Searching...</div>';
        resultsContainer.style.display = 'block';
    
        try {
            const response = await fetch(`api.php?fetch=active_doctors&search=${encodeURIComponent(query)}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            if(result.data.length === 0) {
                 resultsContainer.innerHTML = '<div class="search-result-item">No doctors found.</div>';
            } else {
                resultsContainer.innerHTML = result.data.map(d => `
                    <div class="search-result-item" data-id="${d.id}" data-name="${d.name} (${d.display_user_id})">
                        ${d.name} (${d.display_user_id})
                    </div>
                `).join('');
            }
        } catch (error) {
             resultsContainer.innerHTML = `<div class="search-result-item" style="color:red">Search failed.</div>`;
        }
    }
    
    function selectLabDoctor(id, name) {
        document.getElementById('lab-doctor-id').value = id;
        document.getElementById('selected-doctor-name').textContent = name;
        document.getElementById('doctor-search-results').style.display = 'none';
        document.getElementById('lab-doctor-search').style.display = 'none';
        document.getElementById('selected-doctor-display').style.display = 'flex';
    }
    
    function clearSelectedLabDoctor() {
        document.getElementById('lab-doctor-id').value = '';
        document.getElementById('selected-doctor-name').textContent = '';
        document.getElementById('lab-doctor-search').value = '';
        document.getElementById('doctor-search-results').style.display = 'none';
        document.getElementById('lab-doctor-search').style.display = 'block';
        document.getElementById('selected-doctor-display').style.display = 'none';
    }

    async function handleLabOrderFormSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const modal = form.closest('.modal-overlay');
        
        const findings = [];
        document.querySelectorAll('#lab-findings-container .finding-row').forEach(row => {
            const parameter = row.querySelector('.finding-parameter').value.trim();
            const result = row.querySelector('.finding-result').value.trim();
            const range = row.querySelector('.finding-range').value.trim();
            if (parameter || result || range) {
                findings.push({ parameter, result, range });
            }
        });
        const summary = document.getElementById('lab-summary').value.trim();

        const resultDetailsInput = document.getElementById('lab-order-details');
        if (findings.length > 0 || summary) {
            resultDetailsInput.value = JSON.stringify({ findings, summary });
        } else {
            resultDetailsInput.value = '';
        }

        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'An unknown error occurred');

            modal.classList.remove('show');
            alert(result.message);
            fetchAndRenderLabOrders(document.getElementById('lab-search').value, document.getElementById('lab-status-filter').value);
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
    
    async function handleRemoveLabOrder(id) {
        const formData = new FormData();
        formData.append('action', 'removeLabOrder');
        formData.append('id', id);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            alert(result.message);
            fetchAndRenderLabOrders(document.getElementById('lab-search').value, document.getElementById('lab-status-filter').value);
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    // --- DISCHARGE MANAGEMENT LOGIC ---
    const dischargePage = document.getElementById('discharge-page');
    let dischargeInitialized = false;
    let dischargeSearchDebounce;

    function initializeDischarge() {
        if (!dischargePage) return;
    
        if (!dischargeInitialized) {
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
            
            const dischargeModal = document.getElementById('discharge-clearance-modal');
            dischargeModal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => dischargeModal.classList.remove('show')));
            
            dischargeInitialized = true;
        }
    
        if (dischargeRefreshInterval) {
            clearInterval(dischargeRefreshInterval);
        }
    
        fetchAndRenderDischarges(document.getElementById('discharge-search').value, document.getElementById('discharge-status-filter').value);
    
        dischargeRefreshInterval = setInterval(() => {
            fetchAndRenderDischarges(document.getElementById('discharge-search').value, document.getElementById('discharge-status-filter').value, true);
        }, 30000);
    }
    
    async function fetchAndRenderDischarges(search = '', status = 'all', silent = false) {
        const tableBody = document.getElementById('discharge-table')?.querySelector('tbody');
        if (!tableBody) return;
    
        if (!silent) {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">Loading discharge requests...</td></tr>`;
        }
    
        try {
            const response = await fetch(`api.php?fetch=discharge_requests&search=${encodeURIComponent(search)}&status=${status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderDischarges(result.data);
        } catch (error) {
            console.error(error);
            if (!silent) {
                tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
            }
        }
    }

    function renderDischarges(data) {
        const tableBody = document.getElementById('discharge-table')?.querySelector('tbody');
        if (!tableBody) return;

        if (data.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">No pending discharge requests found.</td></tr>`;
            return;
        }

        tableBody.innerHTML = data.map(req => {
            let statusText = '';
            let statusClass = '';
            switch(req.clearance_step) {
                case 'nursing': statusText = 'Pending Nursing'; statusClass = 'pending-nursing'; break;
                case 'pharmacy': statusText = 'Pending Pharmacy'; statusClass = 'pending-pharmacy'; break;
                case 'billing': statusText = 'Pending Billing'; statusClass = 'pending-billing'; break;
            }
            if (req.is_cleared == 1) {
                statusText = `Cleared by ${req.cleared_by_name || 'Staff'}`;
                statusClass = 'completed';
            }

            return `
                <tr>
                    <td data-label="Req. ID">D-${String(req.discharge_id).padStart(4, '0')}</td>
                    <td data-label="Patient">${req.patient_name} (${req.patient_display_id})</td>
                    <td data-label="Status"><span class="status ${statusClass}">${statusText}</span></td>
                    <td data-label="Doctor">${req.doctor_name || 'N/A'}</td>
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
            const response = await fetch('api.php', { method: 'POST', body: formData });
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

        const billingTable = document.getElementById('billing-table');
        billingTable.addEventListener('click', (e) => {
            const paymentBtn = e.target.closest('.process-payment-btn');
            if (paymentBtn) {
                const transactionId = paymentBtn.dataset.transactionId;
                const amount = paymentBtn.dataset.amount;
                openPaymentModal(transactionId, amount);
            }
        });
        
        const paymentModal = document.getElementById('process-payment-modal');
        paymentModal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => paymentModal.classList.remove('show')));
        document.getElementById('process-payment-form').addEventListener('submit', handlePaymentSubmit);

        billingInitialized = true;
    }

    async function fetchAndRenderInvoices(search = '') {
        const tableBody = document.getElementById('billing-table')?.querySelector('tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">Loading invoices...</td></tr>`;

        try {
            const response = await fetch(`api.php?fetch=invoices&search=${encodeURIComponent(search)}`);
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
            const isPaid = inv.status === 'paid';
            const statusClass = isPaid ? 'paid' : 'unpaid';
            const statusText = inv.status.charAt(0).toUpperCase() + inv.status.slice(1);
            
            const actions = isPaid
                ? `<button class="action-btn" disabled><i class="fas fa-check-circle"></i> Paid</button>`
                : `<button class="action-btn process-payment-btn" data-transaction-id="${inv.id}" data-amount="${inv.amount}"><i class="fas fa-money-check-alt"></i> Process Payment</button>`;

            return `
                <tr>
                    <td data-label="Invoice ID">INV-${String(inv.id).padStart(4, '0')}</td>
                    <td data-label="Patient Name">${inv.patient_name}</td>
                    <td data-label="Amount">₹${parseFloat(inv.amount).toFixed(2)}</td>
                    <td data-label="Date">${new Date(inv.created_at).toLocaleDateString()}</td>
                    <td data-label="Status"><span class="status ${statusClass}">${statusText}</span></td>
                    <td data-label="Actions">${actions}</td>
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
            const response = await fetch(`api.php?fetch=billable_patients&search=${encodeURIComponent(query)}`);
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
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'An unknown error occurred');

            modal.classList.remove('show');
            alert(result.message);
            fetchAndRenderInvoices();
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }

    function openPaymentModal(transactionId, amount) {
        const modal = document.getElementById('process-payment-modal');
        document.getElementById('payment-invoice-id').textContent = `INV-${String(transactionId).padStart(4, '0')}`;
        document.getElementById('payment-transaction-id').value = transactionId;
        document.getElementById('payment-amount').textContent = parseFloat(amount).toFixed(2);
        modal.classList.add('show');
    }

    async function handlePaymentSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const modal = form.closest('.modal-overlay');
        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);

        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message);
            
            alert(result.message);
            modal.classList.remove('show');
            fetchAndRenderInvoices(); // Refresh the billing table
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }


    // --- PHARMACY LOGIC ---
    const pharmacyPage = document.getElementById('pharmacy-page');
    let pharmacyInitialized = false;
    let pharmacySearchDebounce;

    function initializePharmacy() {
        if (pharmacyInitialized || !pharmacyPage) return;

        const searchInput = document.getElementById('pharmacy-search');
        const statusFilter = document.getElementById('pharmacy-status-filter');

        searchInput.addEventListener('input', () => {
            clearTimeout(pharmacySearchDebounce);
            pharmacySearchDebounce = setTimeout(() => fetchAndRenderPendingPrescriptions(searchInput.value, statusFilter.value), 300);
        });

        statusFilter.addEventListener('change', () => {
            fetchAndRenderPendingPrescriptions(searchInput.value, statusFilter.value);
        });

        const prescriptionsTable = document.getElementById('pharmacy-prescriptions-table');
        prescriptionsTable.addEventListener('click', e => {
            const createBillBtn = e.target.closest('.create-bill-btn');
            const viewBtn = e.target.closest('.view-prescription-btn');
            
            if (createBillBtn) {
                const row = createBillBtn.closest('tr');
                openBillingModal(row.dataset.prescriptionId, row.dataset.patientName, row.dataset.doctorName);
            }
            
            if (viewBtn) {
                const row = viewBtn.closest('tr');
                openViewPrescriptionModal(row.dataset.prescriptionId, row.dataset.patientName, row.dataset.doctorName);
            }
        });
        
        document.getElementById('pharmacy-billing-form').addEventListener('submit', handlePharmacyBillSubmit);
        document.getElementById('pharmacy-billing-modal').querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => document.getElementById('pharmacy-billing-modal').classList.remove('show')));

        fetchAndRenderPendingPrescriptions();
        pharmacyInitialized = true;
    }

    async function fetchAndRenderPendingPrescriptions(search = '', status = 'all') {
        const tableBody = document.getElementById('pharmacy-prescriptions-table')?.querySelector('tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">Loading...</td></tr>`;

        try {
            const response = await fetch(`api.php?fetch=pending_prescriptions&search=${encodeURIComponent(search)}&status=${status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            if (result.data.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No prescriptions found.</td></tr>`;
                return;
            }

            tableBody.innerHTML = result.data.map(p => {
                let statusClass = 'pending';
                if (p.status === 'dispensed') {
                    statusClass = 'completed';
                } else if (p.status === 'cancelled') {
                    statusClass = 'unpaid';
                }

                const actions = (p.status === 'pending' || p.status === 'partial')
                    ? `<button class="action-btn view-prescription-btn"><i class="fas fa-eye"></i> View</button>
                       <button class="action-btn create-bill-btn"><i class="fas fa-file-invoice"></i> Create Bill</button>`
                    : `<button class="action-btn view-prescription-btn"><i class="fas fa-eye"></i> View</button>`;

                return `
                    <tr data-prescription-id="${p.id}" data-patient-name="${p.patient_name} (${p.patient_display_id})" data-doctor-name="${p.doctor_name} (${p.doctor_display_id})">
                        <td data-label="Presc. ID">PRES-${String(p.id).padStart(4, '0')}</td>
                        <td data-label="Patient Name">${p.patient_name} (${p.patient_display_id})</td>
                        <td data-label="Doctor Name">${p.doctor_name} (${p.doctor_display_id})</td>
                        <td data-label="Date">${p.prescription_date}</td>
                        <td data-label="Status"><span class="status ${statusClass}">${p.status}</span></td>
                        <td data-label="Actions">${actions}</td>
                    </tr>
                `;
            }).join('');

        } catch (error) {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
        }
    }
    
    async function openViewPrescriptionModal(prescriptionId, patientName, doctorName) {
        const modal = document.getElementById('view-prescription-modal');
        const itemsTbody = document.getElementById('view-items-tbody');
        itemsTbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Loading...</td></tr>';
        
        document.getElementById('view-patient-name').textContent = patientName;
        document.getElementById('view-doctor-name').textContent = doctorName;
        
        modal.classList.add('show');
        modal.querySelectorAll('.modal-close-btn').forEach(btn => btn.addEventListener('click', () => modal.classList.remove('show'), { once: true }));

        try {
            const response = await fetch(`api.php?fetch=prescription_details&id=${prescriptionId}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            if (result.data.length > 0) {
                 itemsTbody.innerHTML = result.data.map(item => `
                    <tr>
                        <td data-label="Medicine">${item.medicine_name}</td>
                        <td data-label="Dosage">${item.dosage || 'N/A'}</td>
                        <td data-label="Frequency">${item.frequency || 'N/A'}</td>
                        <td data-label="Qty Prescribed">${item.quantity_prescribed}</td>
                        <td data-label="Qty Dispensed">${item.quantity_dispensed}</td>
                    </tr>
                `).join('');
            } else {
                 itemsTbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">No items found in this prescription.</td></tr>';
            }

        } catch (error) {
            itemsTbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
        }
    }


    async function openBillingModal(prescriptionId, patientName, doctorName) {
        const modal = document.getElementById('pharmacy-billing-modal');
        const itemsTbody = document.getElementById('billing-items-tbody');
        itemsTbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Loading prescription items...</td></tr>';
        
        document.getElementById('billing-prescription-id').value = prescriptionId;
        document.getElementById('billing-patient-name').textContent = patientName;
        document.getElementById('billing-doctor-name').textContent = doctorName;
        document.getElementById('billing-total-amount').textContent = '0.00';
        
        modal.classList.add('show');

        try {
            const response = await fetch(`api.php?fetch=prescription_details&id=${prescriptionId}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            
            itemsTbody.innerHTML = result.data.map(item => {
                const remainingToDispense = item.quantity_prescribed - item.quantity_dispensed;
                const maxDispensable = Math.min(remainingToDispense, item.stock_quantity);
                return `
                    <tr data-unit-price="${item.unit_price}">
                        <td data-label="Medicine">${item.medicine_name}</td>
                        <td data-label="Prescribed">${item.quantity_prescribed}</td>
                        <td data-label="In Stock">${item.stock_quantity}</td>
                        <td data-label="Dispense Qty">
                            <input type="number" class="dispense-qty-input" name="item_${item.medicine_id}" 
                                   data-medicine-id="${item.medicine_id}"
                                   value="${maxDispensable}" min="0" max="${maxDispensable}">
                        </td>
                        <td data-label="Unit Price">₹${parseFloat(item.unit_price).toFixed(2)}</td>
                        <td data-label="Subtotal" class="subtotal">₹0.00</td>
                    </tr>
                `;
            }).join('');
            
            itemsTbody.addEventListener('input', e => {
                if (e.target.classList.contains('dispense-qty-input')) {
                    updateBillingTotals();
                }
            });
            updateBillingTotals();

        } catch (error) {
            itemsTbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">${error.message}</td></tr>`;
        }
    }

    function updateBillingTotals() {
        let total = 0;
        document.querySelectorAll('#billing-items-tbody tr').forEach(row => {
            const qtyInput = row.querySelector('.dispense-qty-input');
            const quantity = parseInt(qtyInput.value, 10) || 0;
            const unitPrice = parseFloat(row.dataset.unitPrice);
            const subtotal = quantity * unitPrice;
            row.querySelector('.subtotal').textContent = `₹${subtotal.toFixed(2)}`;
            total += subtotal;
        });
        document.getElementById('billing-total-amount').textContent = total.toFixed(2);
    }

    async function handlePharmacyBillSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const modal = form.closest('.modal-overlay');
        const submitBtn = modal.querySelector('button[type="submit"]');

        const items = [];
        document.querySelectorAll('.dispense-qty-input').forEach(input => {
            const quantity = parseInt(input.value, 10);
            if (quantity > 0) {
                items.push({
                    medicine_id: input.dataset.medicineId,
                    quantity: quantity
                });
            }
        });

        const formData = new FormData();
        formData.append('action', 'create_pharmacy_bill');
        formData.append('csrf_token', csrfToken);
        formData.append('prescription_id', document.getElementById('billing-prescription-id').value);
        formData.append('payment_mode', document.getElementById('billing-payment-mode').value);
        formData.append('items', JSON.stringify(items));

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message);

            modal.classList.remove('show');
            const confirmed = await showConfirmation(
                'Success!', 
                `${result.message} Would you like to download the receipt now?`
            );
            
            if (confirmed) {
                window.open(`api.php?action=download_pharmacy_bill&id=${result.bill_id}`, '_blank');
            }

            const searchInput = document.getElementById('pharmacy-search');
            const statusFilter = document.getElementById('pharmacy-status-filter');
            fetchAndRenderPendingPrescriptions(searchInput.value, statusFilter.value);

        } catch (error) {
            alert('Error: ' + error.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Complete Payment & Dispense';
        }
    }

    // --- LIVE TOKEN LOGIC ---
    const liveTokensPage = document.getElementById('live-tokens-page');
    let liveTokensInitialized = false;
    let tokenRefreshInterval;
    let autoRefreshEnabled = true;
    let currentFilter = 'all';
    let currentDoctorData = null;

    function initializeLiveTokens() {
        if (liveTokensInitialized || !liveTokensPage) return;

        const searchInput = document.getElementById('token-doctor-search');
        const searchResults = document.getElementById('token-doctor-search-results');
        const hiddenInput = document.getElementById('token-doctor-id-hidden');
        const tokenContainer = document.getElementById('token-display-container');
        tokenContainer.addEventListener('click', async (e) => {
    const button = e.target.closest('.token-staff-actions .action-btn-icon');
    if (button) {
        const card = button.closest('.token-card');
        const appointmentId = card.dataset.appointmentId;
        const newStatus = button.dataset.action;

        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        button.disabled = true;

        await staffUpdateTokenStatus(appointmentId, newStatus);

        // Refresh tokens after action
        const doctorId = document.getElementById('token-doctor-id-hidden').value;
        if (doctorId) {
            fetchAndRenderTokens(doctorId);
        }
    }
});
        const refreshBtn = document.getElementById('refresh-tokens-btn');
        const toggleAutoRefreshBtn = document.getElementById('toggle-auto-refresh');
        const statsContainer = document.getElementById('token-stats-container');
        const filterContainer = document.getElementById('token-filter-container');
        const doctorInfoContainer = document.getElementById('selected-doctor-info');
        let searchDebounce;

        // Manual refresh button
        refreshBtn.addEventListener('click', () => {
            const doctorId = hiddenInput.value;
            if (doctorId) {
                fetchAndRenderTokens(doctorId);
            }
        });

        // Toggle auto-refresh
        toggleAutoRefreshBtn.addEventListener('click', () => {
            autoRefreshEnabled = !autoRefreshEnabled;
            toggleAutoRefreshBtn.classList.toggle('active', autoRefreshEnabled);
            toggleAutoRefreshBtn.innerHTML = autoRefreshEnabled 
                ? '<i class="fas fa-play"></i> Auto-refresh ON' 
                : '<i class="fas fa-pause"></i> Auto-refresh OFF';
            
            if (autoRefreshEnabled) {
                const doctorId = hiddenInput.value;
                if (doctorId) {
                    tokenRefreshInterval = setInterval(() => fetchAndRenderTokens(doctorId), 10000);
                }
            } else {
                clearInterval(tokenRefreshInterval);
            }
        });

        // Filter buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.filter-btn')) {
                const filterBtn = e.target.closest('.filter-btn');
                const filter = filterBtn.dataset.filter;
                
                document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                filterBtn.classList.add('active');
                
                currentFilter = filter;
                applyTokenFilter(filter);
            }
        });

        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            const query = searchInput.value.trim();
            hiddenInput.value = '';
            if (query.length === 0) {
                clearInterval(tokenRefreshInterval);
                tokenContainer.innerHTML = '<p class="no-items-message"><i class="fas fa-search"></i><br>Please select a doctor to see their live token queue for today.</p>';
                searchResults.style.display = 'none';
                statsContainer.style.display = 'none';
                filterContainer.style.display = 'none';
                doctorInfoContainer.style.display = 'none';
                return;
            }
            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }
            
            searchDebounce = setTimeout(async () => {
                try {
                    const response = await fetch(`api.php?fetch=active_doctors&search=${encodeURIComponent(query)}`);
                    const result = await response.json();
                    if (result.success && result.data.length > 0) {
                        searchResults.innerHTML = result.data.map(doc =>
                            `<div class="search-result-item" data-id="${doc.id}" data-name="${doc.name}" data-specialty="${doc.specialty || 'General Practice'}">${doc.name} - ${doc.specialty || 'General Practice'}</div>`
                        ).join('');
                        searchResults.style.display = 'block';
                    } else {
                        searchResults.innerHTML = `<div class="search-result-item">No doctors found.</div>`;
                        searchResults.style.display = 'block';
                    }
                } catch (error) {
                    console.error("Doctor search failed:", error);
                }
            }, 300);
        });

        searchResults.addEventListener('click', (e) => {
            const item = e.target.closest('.search-result-item');
            if (item && item.dataset.id) {
                const doctorId = item.dataset.id;
                const doctorName = item.dataset.name;
                const doctorSpecialty = item.dataset.specialty || 'General Practice';
                
                searchInput.value = doctorName; 
                hiddenInput.value = doctorId;
                searchResults.style.display = 'none';
                
                // Store doctor data
                currentDoctorData = {
                    id: doctorId,
                    name: doctorName,
                    specialty: doctorSpecialty
                };
                
                // Show doctor info card
                document.getElementById('doctor-name-display').textContent = doctorName;
                document.getElementById('doctor-specialty-display').textContent = doctorSpecialty;
                doctorInfoContainer.style.display = 'block';
                
                // Show stats and filters
                statsContainer.style.display = 'block';
                filterContainer.style.display = 'block';
                
                clearInterval(tokenRefreshInterval);
                fetchAndRenderTokens(doctorId);
                
                if (autoRefreshEnabled) {
                    tokenRefreshInterval = setInterval(() => fetchAndRenderTokens(doctorId), 10000);
                }
            }
        });
        
        document.addEventListener('click', (e) => {
            if (!searchInput.contains(e.target)) {
                searchResults.style.display = 'none';
            }
        });

        liveTokensInitialized = true;
    }

    function updateStatistics(tokenData) {
        let waiting = 0, inConsultation = 0, completed = 0, total = 0;
        
        for (const slotTitle in tokenData) {
            tokenData[slotTitle].forEach(token => {
                total++;
                if (token.token_status === 'waiting') waiting++;
                else if (token.token_status === 'in_consultation') inConsultation++;
                else if (token.token_status === 'completed') completed++;
            });
        }
        
        document.getElementById('stat-waiting-count').textContent = waiting;
        document.getElementById('stat-consultation-count').textContent = inConsultation;
        document.getElementById('stat-completed-count').textContent = completed;
        document.getElementById('stat-total-count').textContent = total;
    }

    function applyTokenFilter(filter) {
        const allCards = document.querySelectorAll('.token-card');
        
        allCards.forEach(card => {
            card.classList.remove('filtered-out');
            if (filter !== 'all') {
                const cardStatus = card.className.match(/status-([a-z_-]+)/)?.[1];
                if (cardStatus && cardStatus !== filter.replace('_', '-')) {
                    card.classList.add('filtered-out');
                }
            }
        });
    }

    async function fetchAndRenderTokens(doctorId) {
        const container = document.getElementById('token-display-container');
        
        try {
            // Show skeleton loader
            container.innerHTML = `
                <div class="token-grid-container">
                    ${Array(4).fill().map(() => `
                        <div class="token-skeleton skeleton-loader"></div>
                    `).join('')}
                </div>
            `;
            
            const response = await fetch(`api.php?fetch=fetch_tokens&doctor_id=${doctorId}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            const tokenData = result.data;
            
            // Update last updated time
            const now = new Date();
            document.getElementById('last-update-time').textContent = now.toLocaleTimeString('en-IN', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            if (Object.keys(tokenData).length === 0) {
                container.innerHTML = `<p class="no-items-message"><i class="fas fa-calendar-times"></i><br>No appointments or tokens found for this doctor today.</p>`;
                updateStatistics({});
                return;
            }

            // Update statistics
            updateStatistics(tokenData);

            let html = '';
            let globalPosition = 1;
            
            for (const slotTitle in tokenData) {
                html += `<h4 class="token-slot-header">
                    <span><i class="fas fa-clock"></i> ${slotTitle}</span>
                    <span class="slot-time-badge">${tokenData[slotTitle].length} patients</span>
                </h4>`;
                html += '<div class="token-grid-container">'; 
                
                tokenData[slotTitle].forEach(token => {
                    const showPosition = token.token_status === 'waiting';
                    html += `
                        <div class="token-card status-${token.token_status.replace('_', '-')}" data-status="${token.token_status}" data-appointment-id="${token.id}">
                            <div class="token-number">#${token.token_number || 'N/A'}</div>
                            <div class="token-patient-info">
                                <div class="patient-name">${token.patient_name}</div>
                                <div class="patient-id">${token.patient_display_id}</div>
                                <div class="appointment-time">
                                    <i class="fas fa-clock"></i> ${slotTitle}
                                </div>
                            ${showPosition ? `<div class="queue-position">Position: ${globalPosition}</div>` : ''}
                            </div>
                            <div class="token-status">${token.token_status.replace('_', ' ')}</div>

                            <div class="token-staff-actions">
                                ${token.token_status === 'waiting' ? `
                                    <button class="action-btn-icon" title="Call for Consultation" data-action="in_consultation"><i class="fas fa-play"></i></button>
                                    <button class="action-btn-icon danger" title="Skip Token" data-action="skipped"><i class="fas fa-forward"></i></button>
                                ` : token.token_status === 'in_consultation' ? `
                                    <button class="action-btn-icon" title="Mark Completed" data-action="completed" style="color: var(--secondary-color);"><i class="fas fa-check"></i></button>
                                ` : ''}
                            </div>
                            </div>
                    `;
                    if (token.token_status === 'waiting') globalPosition++;
                });

                html += '</div>';
            }
            container.innerHTML = html;
            
            // Reapply current filter
            applyTokenFilter(currentFilter);
        } catch (error) {
            console.error("Error fetching tokens:", error);
            container.innerHTML = `<p class="no-items-message" style="color: var(--danger-color);"><i class="fas fa-exclamation-triangle"></i><br>Error loading tokens. Please try again.</p>`;
        }
    }

    async function staffUpdateTokenStatus(appointmentId, status) {
    try {
        const formData = new FormData();
        formData.append('action', 'staff_update_token_status');
        formData.append('appointment_id', appointmentId);
        formData.append('token_status', status);
        formData.append('csrf_token', csrfToken); // Assumes csrfToken is available globally

        const response = await fetch('api.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (!result.success) {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Failed to update token status:', error);
        alert('An error occurred. Please try again.');
    }
}

});

// --- Global Utility Functions ---

/**
 * Toggle password visibility
 */
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.parentElement.querySelector('.toggle-password');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}