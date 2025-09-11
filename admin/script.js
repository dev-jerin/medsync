document.addEventListener("DOMContentLoaded", function () {
    // --- CORE UI ELEMENTS & STATE ---
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const currentUserId = parseInt(document.getElementById('current-user-id').value, 10);
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const navLinks = document.querySelectorAll('.nav-link');
    const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');
    const panelTitle = document.getElementById('panel-title');
    const welcomeMessage = document.getElementById('welcome-message');
    const userSearchInput = document.getElementById('user-search-input');

    let currentRole = 'user';
    let currentAccommodationType = 'bed';
    let userRolesChart = null;
    let messengerInitialized = false;
    let activeConversationId = null;
    let activeReceiverId = null;
    let searchDebounceTimer;

    // --- HELPER FUNCTIONS ---
    const showNotification = (message, type = 'success') => {
        const container = document.getElementById('notification-container');
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.textContent = message;
        container.appendChild(notification);
        setTimeout(() => {
            notification.remove();
        }, 5000);
    };

    const convert24hTo12h = (timeString) => {
        if (!timeString) return { hour: 9, minute: '00', period: 'AM' };
        const [hours24, minutes] = timeString.split(':');
        const period = parseInt(hours24, 10) >= 12 ? 'PM' : 'AM';
        let hours12 = parseInt(hours24, 10) % 12;
        if (hours12 === 0) hours12 = 12;
        return { hour: hours12, minute: minutes, period };
    };

    const convert12hTo24h = (hour, minute, period) => {
        hour = parseInt(hour, 10);
        if (period === 'PM' && hour !== 12) {
            hour += 12;
        }
        if (period === 'AM' && hour === 12) {
            hour = 0;
        }
        return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
    };

    const showConfirmation = (title, message) => {
        return new Promise((resolve) => {
            const dialog = document.getElementById('confirm-dialog');
            document.getElementById('confirm-title').textContent = title;
            document.getElementById('confirm-message').textContent = message;
            dialog.classList.add('show');

            const cancelBtn = document.getElementById('confirm-btn-cancel');
            const okBtn = document.getElementById('confirm-btn-ok');

            const cleanup = (result) => {
                dialog.classList.remove('show');
                resolve(result);
            };

            okBtn.onclick = () => cleanup(true);
            cancelBtn.onclick = () => cleanup(false);
        });
    };
    
    userSearchInput.addEventListener('keyup', () => {
        setTimeout(() => {
            fetchUsers(currentRole, userSearchInput.value.trim());
        }, 300);
    });

    // --- REAL-TIME VALIDATION LOGIC ---
    const validateField = (field, ignoreRequired = false) => {
        const errorContainer = field.nextElementSibling;
        let errorMessage = '';

        if (!ignoreRequired && field.required && field.value.trim() === '') {
            errorMessage = 'This field is required.';
        } 
        else if (field.value.trim() !== '' && field.minLength > 0 && field.value.length < field.minLength) {
            errorMessage = `Must be at least ${field.minLength} characters long.`;
        }
        else if (field.type === 'email' && field.value.trim() !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value)) {
            errorMessage = 'Please enter a valid email address.';
        }
        else if (field.type === 'tel' && field.pattern && !new RegExp(field.pattern).test(field.value)) {
             errorMessage = 'Please use the format +CountryCodeNumber (e.g., +919876543210).';
        }

        if (errorMessage) {
            field.classList.add('invalid');
            if (errorContainer) errorContainer.textContent = errorMessage;
            return false;
        } else {
            field.classList.remove('invalid');
            if (errorContainer) errorContainer.textContent = '';
            return true;
        }
    };

    // --- GENERIC HELPER: REUSABLE MODAL HANDLER ---
    const setupModal = (config) => {
        const { modalId, openBtnId, formId, onOpen } = config;
        const modal = document.getElementById(modalId);
        const openBtn = document.getElementById(openBtnId);
        const form = document.getElementById(formId);

        if (!modal) return null;

        const open = (mode, data = {}) => {
            if (form) {
                form.reset();
                form.querySelectorAll('.invalid').forEach(el => el.classList.remove('invalid'));
                form.querySelectorAll('.error-message').forEach(el => el.textContent = '');
            }
            if (onOpen) onOpen(mode, data);
            modal.classList.add('show');
        };

        if (openBtn) openBtn.addEventListener('click', () => open('add'));
        
        modal.querySelector('.modal-close-btn')?.addEventListener('click', () => modal.classList.remove('show'));
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.remove('show');
        });

        return open;
    };

    // --- GENERIC HELPER: REUSABLE DATA FETCHER & RENDERER ---
    const fetchAndRender = async (config) => {
        const { endpoint, target, renderRow, columns, emptyMessage } = config;
        if (target) {
            target.innerHTML = `<tr><td colspan="${columns}" class="loading-cell"><div class="spinner"></div><span>Loading...</span></td></tr>`;
        }

        try {
            const response = await fetch(endpoint);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            if (target) {
                if (result.data.length > 0) {
                    target.innerHTML = result.data.map(renderRow).join('');
                } else {
                    target.innerHTML = `<tr><td colspan="${columns}" style="text-align:center;">${emptyMessage}</td></tr>`;
                }
            }
            return result.data; // Return data for non-rendering fetches
        } catch (error) {
            console.error('Fetch error:', error);
            if (target) {
                target.innerHTML = `<tr><td colspan="${columns}" style="text-align:center;">Failed to load data: ${error.message}</td></tr>`;
            }
            return null; // Return null on error
        }
    };
    
    // --- THEME TOGGLE ---
    const themeToggle = document.getElementById('theme-toggle');
    const applyTheme = (theme) => {
        document.body.className = theme;
        themeToggle.checked = theme === 'dark-mode';
        if (userRolesChart) {
            updateChartAppearance();
        }
    };

    themeToggle.addEventListener('change', () => {
        const newTheme = themeToggle.checked ? 'dark-mode' : 'light-mode';
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
    });
    applyTheme(localStorage.getItem('theme') || 'light-mode');


    // --- SIDEBAR & NAVIGATION ---
    const toggleMenu = () => {
        const isActive = sidebar.classList.contains('active');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        hamburgerBtn.querySelector('i').className = `fas ${isActive ? 'fa-bars' : 'fa-times'}`;
    };

    hamburgerBtn.addEventListener('click', e => { e.stopPropagation(); toggleMenu(); });
    overlay.addEventListener('click', toggleMenu);

    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function () {
            this.classList.toggle('active');
            const dropdown = this.nextElementSibling;
            dropdown.style.maxHeight = dropdown.style.maxHeight ? null : dropdown.scrollHeight + "px";
        });
    });

    // --- PANEL SWITCHING LOGIC ---
    const handlePanelSwitch = (clickedLink) => {
        if (!clickedLink) return;

        const targetId = clickedLink.dataset.target;
        if (!targetId) return;

        document.querySelectorAll('.sidebar-nav a.active, .sidebar-nav .nav-dropdown-toggle.active').forEach(a => a.classList.remove('active'));

        const sidebarLink = document.querySelector(`.sidebar .nav-link[data-target="${targetId}"]`);
        if (sidebarLink) {
            sidebarLink.classList.add('active');
            let parent = sidebarLink.closest('.nav-dropdown');
            if(parent) {
                let toggle = parent.previousElementSibling;
                if(toggle && toggle.classList.contains('nav-dropdown-toggle')) {
                    toggle.classList.add('active');
                }
            }
        }

        let panelToShowId = 'dashboard-panel';
        let title = 'Dashboard';
        welcomeMessage.style.display = 'block';

        if (targetId.startsWith('users-')) {
            panelToShowId = 'users-panel';
            const role = targetId.split('-')[1];
            title = `${role.charAt(0).toUpperCase() + role.slice(1)} Management`;
            welcomeMessage.style.display = 'none';
            fetchUsers(role);
        } else if (targetId.startsWith('inventory-')) {
            panelToShowId = targetId + '-panel';
            title = sidebarLink ? sidebarLink.innerText.trim() : 'Inventory';
            welcomeMessage.style.display = 'none';
            const inventoryType = targetId.split('-')[1];
            if (inventoryType === 'blood') fetchBloodInventory();
            else if (inventoryType === 'medicine') fetchMedicineInventory();
            else if (inventoryType === 'departments') fetchDepartmentsManagement();
            else if (inventoryType === 'wards') fetchWards();
        } else if (targetId.startsWith('accommodations-')) {
            panelToShowId = 'accommodations-panel';
            const type = targetId.split('-')[1];
            currentAccommodationType = type;
            title = `${type.charAt(0).toUpperCase() + type.slice(1)} Management`;
            welcomeMessage.style.display = 'none';
            fetchAccommodations(type);
        } else if (document.getElementById(targetId + '-panel')) {
            panelToShowId = targetId + '-panel';
            title = sidebarLink ? sidebarLink.innerText.trim() : 'Admin Panel';
            welcomeMessage.style.display = (targetId === 'dashboard') ? 'block' : 'none';

            if (targetId === 'settings') fetchMyProfile();
            if (targetId === 'appointments') {
                fetchDoctorsForAppointmentFilter();
                fetchAppointments();
            }
             if (targetId === 'messenger') initializeMessenger();
            if (targetId === 'reports') generateReport();
            if (targetId === 'activity') fetchActivityLogs();
            if (targetId === 'feedback') fetchFeedback();
        }

        document.querySelectorAll('.content-panel').forEach(p => p.classList.remove('active'));
        document.getElementById(panelToShowId).classList.add('active');
        panelTitle.textContent = title;

        if (window.innerWidth <= 992 && sidebar.classList.contains('active')) toggleMenu();
    };

    document.body.addEventListener('click', function (e) {
        const link = e.target.closest('.nav-link');
        if (link) {
            e.preventDefault();
            if (link.id !== 'notification-bell-wrapper') {
                handlePanelSwitch(link);
            }
        }
    });

    // --- CHART.JS & DASHBOARD STATS ---
    const updateChartAppearance = () => {
        if (!userRolesChart) return;
        const isDarkMode = document.body.classList.contains('dark-mode');
        const textColor = isDarkMode ? '#F9FAFB' : '#1F2937';
        const borderColor = isDarkMode ? '#111827' : '#FFFFFF';

        userRolesChart.options.plugins.legend.labels.color = textColor;
        userRolesChart.data.datasets[0].borderColor = borderColor;
        userRolesChart.update();
    };

    const updateDashboardStats = async () => {
        try {
            const response = await fetch('api.php?fetch=dashboard_stats');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            const stats = result.data;
            document.getElementById('total-users-stat').textContent = stats.total_users;
            document.getElementById('active-doctors-stat').textContent = stats.active_doctors;
            document.getElementById('pending-appointments-stat').textContent = stats.pending_appointments || 0;

            const lowMedicineStat = document.getElementById('low-medicine-stat');
            const lowBloodStat = document.getElementById('low-blood-stat');
            
            lowMedicineStat.style.display = 'none';
            lowBloodStat.style.display = 'none';

            if (stats.low_medicines_count > 0) {
                document.getElementById('low-medicine-count').textContent = stats.low_medicines_count;
                lowMedicineStat.style.display = 'flex';
            }

            if (stats.low_blood_count > 0) {
                document.getElementById('low-blood-count').textContent = stats.low_blood_count;
                lowBloodStat.style.display = 'flex';
            }
            
            try {
                const feedbackResponse = await fetch('api.php?fetch=feedback_summary');
                const feedbackResult = await feedbackResponse.json();
                if (feedbackResult.success && feedbackResult.data.total_reviews > 0) {
                    const avgRating = parseFloat(feedbackResult.data.average_rating).toFixed(1);
                    document.getElementById('satisfaction-score').textContent = `${avgRating} / 5`;
                    document.getElementById('patient-satisfaction-stat').style.display = 'flex';
                }
            } catch (error) {
                console.error('Could not fetch feedback summary:', error);
            }

            const chartData = [
                stats.role_counts.user || 0,
                stats.role_counts.doctor || 0,
                stats.role_counts.staff || 0,
                stats.role_counts.admin || 0
            ];

            const ctx = document.getElementById('userRolesChart').getContext('2d');
            if (userRolesChart) {
                userRolesChart.data.datasets[0].data = chartData;
                userRolesChart.update();
            } else {
                userRolesChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Users', 'Doctors', 'Staff', 'Admins'],
                        datasets: [{
                            label: 'User Roles',
                            data: chartData,
                            backgroundColor: ['#3B82F6', '#22C55E', '#F97316', '#8B5CF6'],
                            borderWidth: 4
                        }]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: true,
                        plugins: { legend: { position: 'bottom' } },
                        cutout: '70%'
                    }
                });
            }
            updateChartAppearance();
        } catch (error) {
            console.error('Failed to update dashboard stats:', error);
            showNotification('Could not refresh dashboard data.', 'error');
        }
    };

    // --- GENERIC FORM SUBMISSION ---
    const handleFormSubmit = async (formData, refreshTarget = null) => {
        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                
                const action = formData.get('action');
                if (action === 'sendNotification' || action === 'sendIndividualNotification') {
                    updateNotificationCount();
                }

                // Close any open modal
                document.querySelectorAll('.modal.show').forEach(m => m.classList.remove('show'));

                // Refresh the relevant data view
                if (refreshTarget) {
                    if (refreshTarget.startsWith('users-')) fetchUsers(refreshTarget.split('-')[1]);
                    else if (refreshTarget.startsWith('accommodations-')) fetchAccommodations(refreshTarget.split('-')[1]);
                    else if (refreshTarget === 'blood') fetchBloodInventory();
                    else if (refreshTarget === 'departments_management') fetchDepartmentsManagement();
                    else if (refreshTarget === 'medicine') fetchMedicineInventory();
                    else if (refreshTarget === 'wards') fetchWards();
                }
                updateDashboardStats();
            } else {
                throw new Error(result.message || 'An unknown error occurred.');
            }
        } catch (error) {
            console.error('Submit error:', error);
            showNotification(error.message, 'error');
        }
    };

    // --- APPOINTMENT MANAGEMENT ---
    const fetchDoctorsForAppointmentFilter = async () => {
        const doctorFilterSelect = document.getElementById('appointment-doctor-filter');
        if (doctorFilterSelect.options.length > 1) return;

        try {
            const response = await fetch('api.php?fetch=doctors_for_scheduling');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            result.data.forEach(doctor => {
                doctorFilterSelect.innerHTML += `<option value="${doctor.id}">${doctor.name} (${doctor.display_user_id})</option>`;
            });
        } catch (error) {
            console.error("Failed to fetch doctors for filter:", error);
        }
    };

    const renderAppointmentRow = (appt) => {
        const status = appt.status.charAt(0).toUpperCase() + appt.status.slice(1);
        return `
            <tr>
                <td>${appt.id}</td>
                <td>${appt.patient_name} (${appt.patient_display_id})</td>
                <td>${appt.doctor_name}</td>
                <td>${new Date(appt.appointment_date).toLocaleString()}</td>
                <td><span class="status-badge ${appt.status.toLowerCase()}">${status}</span></td>
            </tr>
        `;
    };

    const fetchAppointments = (doctorId = 'all') => {
        fetchAndRender({
            endpoint: `api.php?fetch=appointments&doctor_id=${doctorId}`,
            target: document.getElementById('appointments-table-body'),
            renderRow: renderAppointmentRow,
            columns: 5,
            emptyMessage: 'No appointments found.'
        });
    };
    
    document.getElementById('appointment-doctor-filter').addEventListener('change', (e) => fetchAppointments(e.target.value));

    // --- USER MANAGEMENT ---
    const userDetailModal = document.getElementById('user-detail-modal');
    const userForm = document.getElementById('user-form');
    
    const roleSelect = document.getElementById('role');
    const doctorFields = document.getElementById('doctor-fields');
    const staffFields = document.getElementById('staff-fields');
    
    userForm.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', () => validateField(input));
    });

    const openDetailedProfileModal = async (userId) => {
        const contentDiv = document.getElementById('user-detail-content');
        contentDiv.innerHTML = '<div class="loading-cell" style="padding: 4rem 0;"><div class="spinner"></div><span>Loading Profile...</span></div>';
        userDetailModal.classList.add('show');
        try {
            const response = await fetch(`api.php?fetch=user_details&id=${userId}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            const { user, activity, assigned_patients } = result.data;
            const pfpPath = `../uploads/profile_pictures/${user.profile_picture || 'default.png'}`;

            let roleSpecificTabs = '';
            let roleSpecificContent = '';

            if (user.role_name === 'doctor') {
                roleSpecificTabs = `<button class="detail-tab-button" data-tab="patients">Assigned Patients</button>`;
                roleSpecificContent = `<div id="patients-tab" class="detail-tab-content">
                        <h3>Assigned Patients</h3>
                        ${assigned_patients && assigned_patients.length > 0 ? assigned_patients.map(p => `<p>${p.name} (${p.display_user_id}) - Last Appointment: ${new Date(p.appointment_date).toLocaleDateString()}</p>`).join('') : '<p>No patients assigned.</p>'}
                    </div>`;
            }

            contentDiv.innerHTML = `
                <div class="user-detail-header">
                    <img src="${pfpPath}" alt="Profile Picture" class="user-detail-pfp" onerror="this.src='../uploads/profile_pictures/default.png'">
                    <div class="user-detail-info">
                        <h4>${user.name}</h4>
                        <p>${user.username} (${user.display_user_id})</p>
                        <p>${user.email}</p>
                    </div>
                </div>
                <div class="detail-tabs">
                    <button class="detail-tab-button active" data-tab="activity">Activity Log</button>
                    ${roleSpecificTabs}
                </div>
                <div id="activity-tab" class="detail-tab-content active">
                    <h3>Recent Activity</h3>
                    <div id="user-detail-activity-log">
                    ${activity.length > 0 ? activity.map(log => {
                        let iconClass = 'fa-plus';
                        if (log.action.includes('update')) iconClass = 'fa-pencil-alt';
                        if (log.action.includes('delete') || log.action.includes('deactivate')) iconClass = 'fa-trash-alt';
                        return `<div class="log-item">
                                    <div class="log-icon"><i class="fas ${iconClass}"></i></div>
                                    <div class="log-details">
                                        <p>${log.details}</p>
                                        <div class="log-meta">${new Date(log.created_at).toLocaleString()}</div>
                                    </div>
                                </div>`
                    }).join('') : '<p>No activity recorded for this user.</p>'}
                    </div>
                </div>
                ${roleSpecificContent}
            `;

            contentDiv.querySelectorAll('.detail-tab-button').forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.dataset.tab;
                    contentDiv.querySelectorAll('.detail-tab-button, .detail-tab-content').forEach(el => el.classList.remove('active'));
                    button.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                });
            });
        } catch (error) {
            contentDiv.innerHTML = `<p style="color:var(--danger-color);">Failed to load profile: ${error.message}</p>`;
        }
    };

    const toggleRoleFields = () => {
        const selectedRole = roleSelect.value;
        doctorFields.style.display = selectedRole === 'doctor' ? 'block' : 'none';
        staffFields.style.display = selectedRole === 'staff' ? 'block' : 'none';
    };

    roleSelect.addEventListener('change', toggleRoleFields);

    const fetchDepartments = async () => {
        try {
            const response = await fetch('api.php?fetch=departments');
            const result = await response.json();
            if (result.success) {
                const departmentSelect = document.getElementById('department_id');
                const staffDepartmentSelect = document.getElementById('assigned_department_id');
                departmentSelect.innerHTML = '<option value="">Select Department</option>';
                staffDepartmentSelect.innerHTML = '<option value="">Select Department</option>';
                result.data.forEach(dept => {
                    departmentSelect.innerHTML += `<option value="${dept.id}">${dept.name}</option>`;
                    staffDepartmentSelect.innerHTML += `<option value="${dept.id}">${dept.name}</option>`;
                });
            }
        } catch (error) {
            console.error('Failed to fetch departments:', error);
        }
    };
    
    const openUserModal = setupModal({
        modalId: 'user-modal',
        openBtnId: 'add-user-btn',
        formId: 'user-form',
        onOpen: (mode, user) => {
            const modalTitle = document.getElementById('modal-title');
            const passwordGroup = document.getElementById('password-group');
            const activeGroup = document.getElementById('is_active-group');
            
            roleSelect.value = currentRole;
            roleSelect.disabled = (mode === 'edit');
    
            if (mode === 'add') {
                modalTitle.textContent = `Add New ${currentRole.charAt(0).toUpperCase() + currentRole.slice(1)}`;
                document.getElementById('form-action').value = 'addUser';
                document.getElementById('password').required = true;
                passwordGroup.style.display = 'block';
                activeGroup.style.display = 'none';
            } else {
                const removePfpBtn = document.getElementById('remove-pfp-btn');
                removePfpBtn.style.display = (user.profile_picture && user.profile_picture !== 'default.png') ? 'block' : 'none';
                removePfpBtn.onclick = async () => {
                    const confirmed = await showConfirmation('Remove Picture', `Are you sure you want to remove the profile picture for ${user.username}?`);
                    if (confirmed) {
                        const formData = new FormData();
                        formData.append('action', 'removeProfilePicture');
                        formData.append('id', user.id);
                        formData.append('csrf_token', csrfToken);
                        handleFormSubmit(formData, `users-${currentRole}`);
                    }
                };
    
                modalTitle.textContent = `Edit ${user.username}`;
                document.getElementById('form-action').value = 'updateUser';
                document.getElementById('user-id').value = user.id;
                document.getElementById('name').value = user.name || '';
                document.getElementById('username').value = user.username;
                document.getElementById('email').value = user.email;
                document.getElementById('phone').value = user.phone || '';
                document.getElementById('date_of_birth').value = user.date_of_birth || '';
                document.getElementById('gender').value = user.gender || '';
                document.getElementById('password').required = false;
                passwordGroup.style.display = 'block';
                activeGroup.style.display = 'block';
                document.getElementById('is_active').value = user.active;
    
                if (user.role === 'doctor') {
                    document.getElementById('specialty').value = user.specialty || '';
                    document.getElementById('qualifications').value = user.qualifications || '';
                    document.getElementById('department_id').value = user.department_id || '';
                    document.getElementById('is_available').value = user.availability !== null ? user.availability : 1;
                } else if (user.role === 'staff') {
                    document.getElementById('shift').value = user.shift || 'day';
                    document.getElementById('assigned_department_id').value = user.assigned_department_id || '';
                }
            }
            toggleRoleFields();
        }
    });

    document.getElementById('quick-add-user-btn').addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelector('.nav-link[data-target="users-user"]').click();
        setTimeout(() => openUserModal('add'), 100);
    });

    userDetailModal.querySelector('.modal-close-btn').addEventListener('click', () => userDetailModal.classList.remove('show'));
    userDetailModal.addEventListener('click', (e) => { if (e.target === userDetailModal) userDetailModal.classList.remove('show'); });

    const renderUserRow = (user) => `
        <tr class="clickable-row" data-user-id="${user.id}">
            <td>
                <div style="display: flex; align-items: center;">
                    <img src="../uploads/profile_pictures/${user.profile_picture || 'default.png'}" alt="pfp" class="user-list-pfp" onerror="this.onerror=null;this.src='../uploads/profile_pictures/default.png';">
                    ${user.name || 'N/A'}
                </div>
            </td>
            <td>${user.display_user_id || 'N/A'}</td>
            <td>${user.username}</td>
            <td>${user.email}</td>
            <td>${user.phone || 'N/A'}</td>
            <td><span class="status-badge ${user.active == 1 ? 'active' : 'inactive'}">${user.active == 1 ? 'Active' : 'Inactive'}</span></td>
            <td>${new Date(user.created_at).toLocaleDateString()}</td>
            <td class="action-buttons">
                <button class="btn-edit" data-user='${JSON.stringify(user)}' title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn-delete" data-user='${JSON.stringify(user)}' title="Deactivate"><i class="fas fa-trash-alt"></i></button>
            </td>
        </tr>
    `;

    const fetchUsers = (role, searchTerm = '') => {
        currentRole = role;
        document.getElementById('user-table-title').textContent = `${role.charAt(0).toUpperCase() + role.slice(1)}s`;
        fetchAndRender({
            endpoint: `api.php?fetch=users&role=${role}&search=${encodeURIComponent(searchTerm)}`,
            target: document.getElementById('user-table-body'),
            renderRow: renderUserRow,
            columns: 8,
            emptyMessage: 'No users found for this role.'
        });
    };

    document.getElementById('user-table-body').addEventListener('click', async (e) => {
        const row = e.target.closest('tr');
        if (!row) return;

        const editBtn = e.target.closest('.btn-edit');
        const deleteBtn = e.target.closest('.btn-delete');

        if (editBtn) {
            e.stopPropagation();
            openUserModal('edit', JSON.parse(editBtn.dataset.user));
            return;
        }

        if (deleteBtn) {
            e.stopPropagation();
            const user = JSON.parse(deleteBtn.dataset.user);
            const confirmed = await showConfirmation('Deactivate User', `Are you sure you want to deactivate ${user.username}?`);
            if (confirmed) {
                const formData = new FormData();
                formData.append('action', 'deleteUser');
                formData.append('id', user.id);
                formData.append('csrf_token', csrfToken);
                handleFormSubmit(formData, `users-${currentRole}`);
            }
            return;
        }
        
        if (row.classList.contains('clickable-row')) {
            openDetailedProfileModal(row.dataset.userId);
        }
    });

    userForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        let isFormValid = true;
        userForm.querySelectorAll('input').forEach(input => {
            if(input.required || input.minLength > -1 || input.pattern) {
                if (input.id === 'password' && document.getElementById('form-action').value === 'updateUser' && input.value.trim() === '') {
                    if (!validateField(input, true)) isFormValid = false;
                } else {
                    if (!validateField(input)) isFormValid = false;
                }
            }
        });

        if (!isFormValid) {
            showNotification('Please correct the errors before saving.', 'error');
            return;
        }

        handleFormSubmit(new FormData(userForm), `users-${currentRole}`);
    });


    // --- ADMIN PROFILE EDIT ---
    const fetchMyProfile = async () => {
        try {
            const response = await fetch(`api.php?fetch=my_profile`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            const profile = result.data;
            document.getElementById('profile-name').value = profile.name || '';
            document.getElementById('profile-email').value = profile.email || '';
            document.getElementById('profile-phone').value = profile.phone || '';
            document.getElementById('profile-username').value = profile.username || '';
        } catch (error) {
            showNotification('Could not load your profile data.', 'error');
        }
    };

    document.getElementById('profile-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        handleFormSubmit(formData);
        document.getElementById('welcome-message').textContent = `Hello, ${formData.get('name')}!`;
        document.querySelector('.user-profile-widget .user-info strong').textContent = formData.get('name');
    });

    document.getElementById('system-settings-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const confirmed = await showConfirmation('Update Settings', 'Are you sure you want to save these system settings? This may affect system functionality like sending emails.');
        if (confirmed) {
            const formData = new FormData(e.target);
            handleFormSubmit(formData);
            document.getElementById('gmail_app_password').value = '';
        }
    });

    // --- INVENTORY MANAGEMENT (MEDICINE) ---
    const openMedicineModal = setupModal({
        modalId: 'medicine-modal',
        openBtnId: 'add-medicine-btn',
        formId: 'medicine-form',
        onOpen: (mode, medicine) => {
            document.getElementById('medicine-modal-title').textContent = mode === 'add' ? 'Add New Medicine' : `Edit ${medicine.name}`;
            document.getElementById('medicine-form-action').value = mode === 'add' ? 'addMedicine' : 'updateMedicine';
            if (mode === 'add') {
                document.getElementById('medicine-low-stock-threshold').value = 10;
            } else {
                document.getElementById('medicine-id').value = medicine.id;
                document.getElementById('medicine-name').value = medicine.name;
                document.getElementById('medicine-description').value = medicine.description || '';
                document.getElementById('medicine-quantity').value = medicine.quantity;
                document.getElementById('medicine-unit-price').value = medicine.unit_price;
                document.getElementById('medicine-low-stock-threshold').value = medicine.low_stock_threshold;
            }
        }
    });

    document.getElementById('medicine-form').addEventListener('submit', (e) => {
        e.preventDefault();
        handleFormSubmit(new FormData(e.target), 'medicine');
    });

    const renderMedicineRow = (med) => {
        const isLowStock = parseInt(med.quantity) <= parseInt(med.low_stock_threshold);
        return `
            <tr data-medicine='${JSON.stringify(med)}'>
                <td>${med.name}</td>
                <td>${med.description || 'N/A'}</td>
                <td><span class="${isLowStock ? 'quantity-low' : 'quantity-good'}">${med.quantity}</span></td>
                <td><span class="status-badge ${isLowStock ? 'low-stock' : 'in-stock'}">${isLowStock ? 'Low Stock' : 'In Stock'}</span></td>
                <td>₹ ${parseFloat(med.unit_price).toFixed(2)}</td>
                <td>${med.low_stock_threshold}</td>
                <td>${new Date(med.updated_at).toLocaleString()}</td>
                <td class="action-buttons">
                    <button class="btn-edit-medicine btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                    <button class="btn-delete-medicine btn-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
                </td>
            </tr>
        `;
    };

    const fetchMedicineInventory = () => fetchAndRender({
        endpoint: 'api.php?fetch=medicines',
        target: document.getElementById('medicine-table-body'),
        renderRow: renderMedicineRow,
        columns: 8,
        emptyMessage: 'No medicines found.'
    });

    document.getElementById('medicine-table-body').addEventListener('click', async (e) => {
        const row = e.target.closest('tr');
        if (!row) return;
        const medicine = JSON.parse(row.dataset.medicine);
        if (e.target.closest('.btn-edit-medicine')) {
            openMedicineModal('edit', medicine);
        }
        if (e.target.closest('.btn-delete-medicine')) {
            const confirmed = await showConfirmation('Delete Medicine', `Are you sure you want to delete ${medicine.name}?`);
            if (confirmed) {
                const formData = new FormData();
                formData.append('action', 'deleteMedicine');
                formData.append('id', medicine.id);
                formData.append('csrf_token', csrfToken);
                handleFormSubmit(formData, 'medicine');
            }
        }
    });

    // --- INVENTORY MANAGEMENT (BLOOD) ---
    const openBloodModal = setupModal({
        modalId: 'blood-modal',
        openBtnId: 'add-blood-btn',
        formId: 'blood-form',
        onOpen: (mode, blood) => { 
            document.getElementById('blood-modal-title').textContent = `Update Blood Unit`;
            document.getElementById('blood-group').value = blood.blood_group || 'A+';
            document.getElementById('blood-group').disabled = !!blood.blood_group;
            document.getElementById('blood-quantity-ml').value = blood.quantity_ml || 0;
            document.getElementById('blood-low-stock-threshold-ml').value = blood.low_stock_threshold_ml || 5000;
        }
    });

    document.getElementById('blood-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const bloodGroupSelect = document.getElementById('blood-group');
        if (bloodGroupSelect.disabled) {
            formData.set('blood_group', bloodGroupSelect.value);
        }
        handleFormSubmit(formData, 'blood');
    });

    const renderBloodRow = (blood) => {
        const isLowStock = parseInt(blood.quantity_ml) < parseInt(blood.low_stock_threshold_ml);
        return `
            <tr data-blood='${JSON.stringify(blood)}'>
                <td>${blood.blood_group}</td>
                <td><span class="${isLowStock ? 'quantity-low' : 'quantity-good'}">${blood.quantity_ml}</span> ml</td>
                <td><span class="status-badge ${isLowStock ? 'low-stock' : 'in-stock'}">${isLowStock ? 'Low Stock' : 'In Stock'}</span></td>
                <td>${blood.low_stock_threshold_ml} ml</td>
                <td>${new Date(blood.last_updated).toLocaleString()}</td>
                <td class="action-buttons">
                    <button class="btn-edit-blood btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                </td>
            </tr>
        `;
    };

    const fetchBloodInventory = () => fetchAndRender({
        endpoint: 'api.php?fetch=blood_inventory',
        target: document.getElementById('blood-table-body'),
        renderRow: renderBloodRow,
        columns: 6,
        emptyMessage: 'No blood inventory records found.'
    });

    document.getElementById('blood-table-body').addEventListener('click', (e) => {
        if (e.target.closest('.btn-edit-blood')) {
            const blood = JSON.parse(e.target.closest('tr').dataset.blood);
            openBloodModal('edit', blood);
        }
    });

    // --- INVENTORY MANAGEMENT (DEPARTMENTS) ---
    const openDepartmentModal = setupModal({
        modalId: 'department-modal',
        openBtnId: 'add-department-btn',
        formId: 'department-form',
        onOpen: (mode, dept) => {
            document.getElementById('department-modal-title').textContent = mode === 'add' ? 'Add New Department' : `Edit ${dept.name}`;
            document.getElementById('department-form-action').value = mode === 'add' ? 'addDepartment' : 'updateDepartment';
            const activeGroup = document.getElementById('department-active-group');
            activeGroup.style.display = mode === 'edit' ? 'block' : 'none';
            if (mode === 'edit') {
                document.getElementById('department-id').value = dept.id;
                document.getElementById('department-name').value = dept.name;
                document.getElementById('department-is-active').value = dept.is_active;
            }
        }
    });
    
    document.getElementById('department-form').addEventListener('submit', (e) => {
        e.preventDefault();
        handleFormSubmit(new FormData(e.target), 'departments_management');
    });

    const renderDepartmentRow = (dept) => `
        <tr data-department='${JSON.stringify(dept)}'>
            <td>${dept.name}</td>
            <td><span class="status-badge ${dept.is_active == 1 ? 'active' : 'inactive'}">${dept.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
            <td class="action-buttons">
                <button class="btn-edit-department btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn-delete-department btn-delete" title="Disable"><i class="fas fa-trash-alt"></i></button>
            </td>
        </tr>
    `;

    const fetchDepartmentsManagement = () => fetchAndRender({
        endpoint: 'api.php?fetch=departments_management',
        target: document.getElementById('department-table-body'),
        renderRow: renderDepartmentRow,
        columns: 3,
        emptyMessage: 'No departments found.'
    });

    document.getElementById('department-table-body').addEventListener('click', async (e) => {
        const row = e.target.closest('tr');
        if (!row) return;
        const department = JSON.parse(row.dataset.department);
        if (e.target.closest('.btn-edit-department')) {
            openDepartmentModal('edit', department);
        }
        if (e.target.closest('.btn-delete-department')) {
            const confirmed = await showConfirmation('Disable Department', `Are you sure you want to disable the "${department.name}" department?`);
            if (confirmed) {
                const formData = new FormData();
                formData.append('action', 'deleteDepartment');
                formData.append('id', department.id);
                formData.append('csrf_token', csrfToken);
                handleFormSubmit(formData, 'departments_management');
            }
        }
    });

    // --- INVENTORY MANAGEMENT (WARDS) ---
    const openWardModal = setupModal({
        modalId: 'ward-form-modal',
        openBtnId: 'add-ward-btn',
        formId: 'ward-form',
        onOpen: (mode, ward) => {
            document.getElementById('ward-form-modal-title').textContent = mode === 'add' ? 'Add New Ward' : `Edit ${ward.name}`;
            document.getElementById('ward-form-action').value = mode === 'add' ? 'addWard' : 'updateWard';
            const activeGroup = document.getElementById('ward-active-group');
            activeGroup.style.display = mode === 'edit' ? 'block' : 'none';
            if (mode === 'edit') {
                document.getElementById('ward-id-input').value = ward.id;
                document.getElementById('ward-name-input').value = ward.name;
                document.getElementById('ward-capacity-input').value = ward.capacity;
                document.getElementById('ward-description-input').value = ward.description || '';
                document.getElementById('ward-is-active-input').value = ward.is_active;
            }
        }
    });

    document.getElementById('ward-form').addEventListener('submit', (e) => {
        e.preventDefault();
        handleFormSubmit(new FormData(e.target), 'wards');
    });

    const renderWardRow = (ward) => `
        <tr data-ward='${JSON.stringify(ward)}'>
            <td>${ward.name}</td>
            <td>${ward.capacity}</td>
            <td>${ward.description || 'N/A'}</td>
            <td><span class="status-badge ${ward.is_active == 1 ? 'active' : 'inactive'}">${ward.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
            <td class="action-buttons">
                <button class="btn-edit-ward btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="btn-delete-ward btn-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
            </td>
        </tr>
    `;

    const fetchWards = () => fetchAndRender({
        endpoint: 'api.php?fetch=wards',
        target: document.getElementById('ward-table-body'),
        renderRow: renderWardRow,
        columns: 5,
        emptyMessage: 'No wards found.'
    });

    document.getElementById('ward-table-body').addEventListener('click', async (e) => {
        const row = e.target.closest('tr');
        if (!row) return;
        const ward = JSON.parse(row.dataset.ward);
        if (e.target.closest('.btn-edit-ward')) {
            openWardModal('edit', ward);
        }
        if (e.target.closest('.btn-delete-ward')) {
            const confirmed = await showConfirmation('Delete Ward', `Are you sure you want to delete ward "${ward.name}"?`);
            if (confirmed) {
                const formData = new FormData();
                formData.append('action', 'deleteWard');
                formData.append('id', ward.id);
                formData.append('csrf_token', csrfToken);
                handleFormSubmit(formData, 'wards');
            }
        }
    });

    // --- ACCOMMODATIONS MANAGEMENT ---
    const accommodationStatusSelect = document.getElementById('accommodation-status');
    const accommodationPatientGroup = document.getElementById('accommodation-patient-group');
    const accommodationDoctorGroup = document.getElementById('accommodation-doctor-group');

    const populateAccommodationDropdowns = async () => {
        try {
            const [wardsRes, patientsRes, doctorsRes] = await Promise.all([
                fetch('api.php?fetch=wards'),
                fetch('api.php?fetch=patients_for_accommodations'),
                fetch('api.php?fetch=doctors_for_scheduling')
            ]);
            const wardsResult = await wardsRes.json();
            const patientsResult = await patientsRes.json();
            const doctorsResult = await doctorsRes.json();

            const wardSelect = document.getElementById('accommodation-ward-id');
            const patientSelect = document.getElementById('accommodation-patient-id');
            const doctorSelect = document.getElementById('accommodation-doctor-id');

            wardSelect.innerHTML = '<option value="">Select Ward</option>';
            if (wardsResult.success) {
                wardsResult.data.forEach(ward => wardSelect.innerHTML += `<option value="${ward.id}">${ward.name}</option>`);
            }

            patientSelect.innerHTML = '<option value="">Select Patient</option>';
            if (patientsResult.success) {
                patientsResult.data.forEach(p => patientSelect.innerHTML += `<option value="${p.id}">${p.name} (${p.display_user_id})</option>`);
            }

            doctorSelect.innerHTML = '<option value="">Select Doctor</option>';
            if (doctorsResult.success) {
                doctorsResult.data.forEach(d => doctorSelect.innerHTML += `<option value="${d.id}">${d.name} (${d.display_user_id})</option>`);
            }
        } catch (error) {
            console.error('Failed to populate accommodation dropdowns:', error);
        }
    };
    
    accommodationStatusSelect.addEventListener('change', () => {
        const showPatient = ['occupied', 'reserved'].includes(accommodationStatusSelect.value);
        const showDoctor = accommodationStatusSelect.value === 'occupied';
        accommodationPatientGroup.style.display = showPatient ? 'block' : 'none';
        accommodationDoctorGroup.style.display = showDoctor ? 'block' : 'none';
        document.getElementById('accommodation-patient-id').required = showPatient;
        document.getElementById('accommodation-doctor-id').required = showDoctor;
    });

    const openAccommodationModal = setupModal({
        modalId: 'accommodation-modal',
        openBtnId: 'add-accommodation-btn',
        formId: 'accommodation-form',
        onOpen: async (mode, item) => {
            const type = item.type || currentAccommodationType;
            await populateAccommodationDropdowns();
            
            const typeName = type.charAt(0).toUpperCase() + type.slice(1);
            document.getElementById('accommodation-modal-title').textContent = mode === 'add' ? `Add New ${typeName}` : `Edit ${typeName} ${item.number}`;
            document.getElementById('accommodation-form-action').value = mode === 'add' ? 'addAccommodation' : 'updateAccommodation';
            document.getElementById('accommodation-type').value = type;
            document.getElementById('accommodation-number-label').textContent = `${typeName} Number`;
    
            const wardGroup = document.getElementById('accommodation-ward-group');
            wardGroup.style.display = type === 'bed' ? 'block' : 'none';
            document.getElementById('accommodation-ward-id').required = (type === 'bed');
    
            accommodationPatientGroup.style.display = 'none';
            accommodationDoctorGroup.style.display = 'none';
    
            if (mode === 'edit') {
                document.getElementById('accommodation-id').value = item.id;
                document.getElementById('accommodation-number').value = item.number;
                document.getElementById('accommodation-price-per-day').value = item.price_per_day;
                
                setTimeout(() => { // Timeout to allow dropdowns to populate
                    document.getElementById('accommodation-status').value = item.status;
                    if (type === 'bed') {
                        document.getElementById('accommodation-ward-id').value = item.ward_id;
                    }
                    accommodationStatusSelect.dispatchEvent(new Event('change'));
                    document.getElementById('accommodation-patient-id').value = item.patient_id || '';
                    document.getElementById('accommodation-doctor-id').value = item.doctor_id || '';
                }, 150);
            } else {
                document.getElementById('accommodation-price-per-day').value = '0.00';
            }
        }
    });

    document.getElementById('accommodation-form').addEventListener('submit', (e) => {
        e.preventDefault();
        handleFormSubmit(new FormData(e.target), `accommodations-${currentAccommodationType}`);
    });

    const fetchAccommodations = async (type) => {
        const container = document.getElementById('accommodations-container');
        const typeName = type.charAt(0).toUpperCase() + type.slice(1);
        container.innerHTML = `<div class="loading-cell" style="padding: 4rem 0; grid-column: 1 / -1;"><div class="spinner"></div><span>Loading ${typeName}s...</span></div>`;
        document.getElementById('accommodations-title').textContent = `${typeName} Management`;
        document.getElementById('add-accommodation-btn').innerHTML = `<i class="fas fa-plus"></i> Add New ${typeName}`;

        try {
            const data = await fetchAndRender({ endpoint: `api.php?fetch=accommodations&type=${type}` });
            if (data && data.length > 0) {
                container.innerHTML = data.map(item => {
                    let patientInfo = '';
                    if (item.status === 'occupied' && item.patient_name) {
                        let doctorInfo = item.doctor_name ? `<br><small>Doctor: ${item.doctor_name}</small>` : '';
                        patientInfo = `<div class="patient-info">Occupied by: ${item.patient_name}${doctorInfo}</div>`;
                    } else if (item.status === 'reserved' && item.patient_name) {
                        patientInfo = `<div class="patient-info">Reserved for: ${item.patient_name}</div>`;
                    }
                    const cardClass = type === 'bed' ? 'bed-card' : 'room-card';
                    const iconClass = type === 'bed' ? 'fa-bed' : 'fa-door-closed';

                    return `
                        <div class="${cardClass} ${item.status}" data-item='${JSON.stringify(item)}'>
                            <div class="item-icon"><i class="fas ${iconClass}"></i></div>
                            <div class="item-number">${typeName} ${item.number}</div>
                            ${type === 'bed' ? `<div class="item-ward">${item.ward_name}</div>` : ''}
                            <div class="item-status">${item.status}</div>
                            ${patientInfo}
                            <div class="action-buttons">
                                <button class="btn-edit-item btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete-item btn-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = `<p style="text-align:center; grid-column: 1 / -1;">No ${type}s found. Add some to get started.</p>`;
            }
        } catch (error) {
            container.innerHTML = `<p style="text-align:center; grid-column: 1 / -1;">Failed to load ${type}s: ${error.message}</p>`;
        }
    };

    document.getElementById('accommodations-container').addEventListener('click', async (e) => {
        const card = e.target.closest('.bed-card, .room-card');
        if (!card) return;

        const item = JSON.parse(card.dataset.item);
        if (e.target.closest('.btn-edit-item')) {
            openAccommodationModal('edit', item);
        }
        if (e.target.closest('.btn-delete-item')) {
            const typeName = item.type.charAt(0).toUpperCase() + item.type.slice(1);
            const confirmed = await showConfirmation(`Delete ${typeName}`, `Are you sure you want to delete ${typeName} ${item.number}?`);
            if (confirmed) {
                const formData = new FormData();
                formData.append('action', 'deleteAccommodation');
                formData.append('id', item.id);
                formData.append('csrf_token', csrfToken);
                handleFormSubmit(formData, `accommodations-${item.type}`);
            }
        }
    });
    
    // --- REPORTING ---
    const generateReport = async () => {
        const reportType = document.getElementById('report-type').value;
        const startDate = document.getElementById('start-date').value;
        const endDate = document.getElementById('end-date').value;

        if (!startDate || !endDate) {
            showNotification('Please select both a start and end date.', 'error');
            return;
        }

        document.getElementById('pdf-report-type').value = reportType;
        document.getElementById('pdf-start-date').value = startDate;
        document.getElementById('pdf-end-date').value = endDate;
        
        const summaryCardsContainer = document.getElementById('report-summary-cards');
        const tableContainer = document.getElementById('report-table-container');
        summaryCardsContainer.innerHTML = `<div class="loading-cell" style="padding: 4rem 0; grid-column: 1 / -1;"><div class="spinner"></div><span>Generating Summary...</span></div>`;
        tableContainer.innerHTML = `<div class="loading-cell" style="padding: 4rem 0;"><div class="spinner"></div><span>Generating Table...</span></div>`;

        try {
            const response = await fetch(`api.php?fetch=report&type=${reportType}&start_date=${startDate}&end_date=${endDate}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            const { summary, tableData } = result.data;

            summaryCardsContainer.innerHTML = '';
            if (reportType === 'financial') {
                summaryCardsContainer.innerHTML = `
                    <div class="summary-card"><span class="label">Total Revenue</span><span class="value">₹${parseFloat(summary.total_revenue || 0).toLocaleString('en-IN')}</span></div>
                    <div class="summary-card"><span class="label">Total Refunds</span><span class="value">₹${parseFloat(summary.total_refunds || 0).toLocaleString('en-IN')}</span></div>
                    <div class="summary-card"><span class="label">Net Revenue</span><span class="value">₹${(parseFloat(summary.total_revenue || 0) - parseFloat(summary.total_refunds || 0)).toLocaleString('en-IN')}</span></div>
                    <div class="summary-card"><span class="label">Transactions</span><span class="value">${summary.total_transactions || 0}</span></div>
                `;
            } else if (reportType === 'patient') {
                summaryCardsContainer.innerHTML = `
                    <div class="summary-card"><span class="label">Total Appointments</span><span class="value">${summary.total_appointments || 0}</span></div>
                    <div class="summary-card"><span class="label">Completed</span><span class="value">${summary.completed || 0}</span></div>
                    <div class="summary-card"><span class="label">Cancelled</span><span class="value">${summary.cancelled || 0}</span></div>
                `;
            } else if (reportType === 'resource') {
                const bed_occupancy_rate = summary.total_beds > 0 ? ((summary.occupied_beds / summary.total_beds) * 100).toFixed(1) : 0;
                const room_occupancy_rate = summary.total_rooms > 0 ? ((summary.occupied_rooms / summary.total_rooms) * 100).toFixed(1) : 0;
                summaryCardsContainer.innerHTML = `
                    <div class="summary-card"><span class="label">Occupied Beds</span><span class="value">${summary.occupied_beds || 0} / ${summary.total_beds || 0} (${bed_occupancy_rate}%)</span></div>
                    <div class="summary-card"><span class="label">Occupied Rooms</span><span class="value">${summary.occupied_rooms || 0} / ${summary.total_rooms || 0} (${room_occupancy_rate}%)</span></div>
                `;
            }

            if (tableData.length > 0) {
                const headers = Object.keys(tableData[0]);
                tableContainer.innerHTML = `
                    <h3 style="margin-top: 2.5rem; margin-bottom: 1.5rem;">Detailed Report Data</h3>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr>${headers.map(h => `<th>${h.replace(/_/g, ' ').toUpperCase()}</th>`).join('')}</tr></thead>
                            <tbody>${tableData.map(row => `<tr>${headers.map(h => `<td>${row[h]}</td>`).join('')}</tr>`).join('')}</tbody>
                        </table>
                    </div>`;
            } else {
                 tableContainer.innerHTML = `<p style="text-align:center; padding: 2rem; color: var(--text-muted);">No data found for the selected date range.</p>`;
            }

        } catch (error) {
            showNotification('Failed to generate report: ' + error.message, 'error');
            summaryCardsContainer.innerHTML = `<p style="color: var(--danger-color);">Could not load report summary.</p>`;
        }
    };

    document.getElementById('generate-report-btn').addEventListener('click', generateReport);

    // --- ACTIVITY LOGS ---
    const renderActivityLogRow = (log) => {
        let iconClass = 'fa-plus', iconBgClass = 'create';
        if (log.action.includes('update')) { iconClass = 'fa-pencil-alt'; iconBgClass = 'update'; }
        else if (log.action.includes('delete') || log.action.includes('deactivate')) { iconClass = 'fa-trash-alt'; iconBgClass = 'delete'; }
        return `
            <div class="log-item">
                <div class="log-icon ${iconBgClass}"><i class="fas ${iconClass}"></i></div>
                <div class="log-details">
                    <p>${log.details}</p>
                    <div class="log-meta">By: <strong>${log.admin_username}</strong> on ${new Date(log.created_at).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })}</div>
                </div>
            </div>`;
    };

    const fetchActivityLogs = async () => {
        const container = document.getElementById('activity-log-container');
        container.innerHTML = `<div class="loading-cell" style="padding: 4rem 0;"><div class="spinner"></div><span>Loading Activity...</span></div>`;
        const data = await fetchAndRender({ endpoint: `api.php?fetch=activity&limit=50` });
        if(data) {
            container.innerHTML = data.length > 0 ? data.map(renderActivityLogRow).join('') : `<p style="text-align: center;">No recent activity found.</p>`;
        } else {
            container.innerHTML = `<p style="text-align: center; color: var(--danger-color);">Failed to load activity logs.</p>`;
        }
    };

    document.getElementById('refresh-logs-btn').addEventListener('click', fetchActivityLogs);

    // --- FEEDBACK ---
    const fetchFeedback = async () => {
        const container = document.getElementById('feedback-container');
        container.innerHTML = `<div class="loading-cell" style="padding: 4rem 0;"><div class="spinner"></div><span>Loading Feedback...</span></div>`;
        const data = await fetchAndRender({ endpoint: 'api.php?fetch=feedback_list' });
        if (data) {
            container.innerHTML = data.length > 0 ? data.map(item => {
                const ratingStars = '<i class="fas fa-star"></i>'.repeat(item.overall_rating) + '<i class="far fa-star"></i>'.repeat(5 - item.overall_rating);
                return `
                    <div class="feedback-item ${item.feedback_type}">
                        <div class="feedback-header">
                            <span class="patient-name">${item.patient_name}</span>
                            <div class="feedback-meta">
                                <span class="feedback-type-badge ${item.feedback_type}">${item.feedback_type}</span>
                                <span class="star-rating">${ratingStars}</span>
                                <span>${new Date(item.created_at).toLocaleDateString()}</span>
                            </div>
                        </div>
                        <p class="feedback-comment">${item.comments || 'No comment provided.'}</p>
                    </div>
                `;
            }).join('') : `<p style="text-align:center;">No patient feedback has been submitted yet.</p>`;
        } else {
             container.innerHTML = `<p style="text-align:center; color: var(--danger-color);">Failed to load feedback.</p>`;
        }
    };

    // --- SCHEDULES & NOTIFICATIONS ---
    const scheduleEditorContainer = document.getElementById('doctor-schedule-editor');
    const saveScheduleBtn = document.getElementById('save-schedule-btn');

    // --- New Doctor Search Logic for Schedules ---
    const doctorSearchInput = document.getElementById('doctor-search-input');
    const doctorSearchResults = document.getElementById('doctor-search-results');
    const selectedDoctorId = document.getElementById('selected-doctor-id');
    let doctorSearchTimeout;

    doctorSearchInput.addEventListener('keyup', () => {
        clearTimeout(doctorSearchTimeout);
        const searchTerm = doctorSearchInput.value.trim();

        // Clear schedule if search is cleared
        if (searchTerm.length === 0) {
            selectedDoctorId.value = '';
            scheduleEditorContainer.innerHTML = '<p class="placeholder-text">Please select a doctor to view or edit their schedule.</p>';
            document.querySelector('.schedule-actions').style.display = 'none';
        }

        if (searchTerm.length < 2) {
            doctorSearchResults.style.display = 'none';
            return;
        }

        doctorSearchTimeout = setTimeout(async () => {
            try {
                const response = await fetch(`api.php?fetch=search_doctors&term=${encodeURIComponent(searchTerm)}`);
                const result = await response.json();
                if (!result.success) throw new Error(result.message);

                if (result.data.length > 0) {
                    doctorSearchResults.innerHTML = result.data.map(doctor => `
                        <div class="doctor-search-result-item" data-id="${doctor.id}" data-name="${doctor.name} (${doctor.display_user_id})">
                            <strong>${doctor.name}</strong> (${doctor.display_user_id})
                        </div>`).join('');
                } else {
                    doctorSearchResults.innerHTML = '<div class="doctor-search-result-item none">No doctors found.</div>';
                }
                doctorSearchResults.style.display = 'block';
            } catch (error) {
                console.error("Doctor search failed:", error);
                doctorSearchResults.innerHTML = '<div class="doctor-search-result-item none">Search error.</div>';
                doctorSearchResults.style.display = 'block';
            }
        }, 300);
    });

    doctorSearchResults.addEventListener('click', (e) => {
        const item = e.target.closest('.doctor-search-result-item');
        if (item && item.dataset.id) {
            selectedDoctorId.value = item.dataset.id;
            doctorSearchInput.value = item.dataset.name;
            doctorSearchResults.style.display = 'none';

            // Trigger schedule fetch for the selected doctor
            fetchDoctorSchedule(item.dataset.id);
        }
    });
    
    // Hide search results when clicking elsewhere
    document.addEventListener('click', (e) => {
        if (!doctorSearchInput.contains(e.target) && !doctorSearchResults.contains(e.target)) {
            doctorSearchResults.style.display = 'none';
        }
    });

    const renderScheduleEditor = (slots) => {
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        scheduleEditorContainer.innerHTML = days.map(day => {
            const daySlots = (slots[day] || []).map(slot => {
                const fromTime = convert24hTo12h(slot.from);
                const toTime = convert24hTo12h(slot.to);
                return `
                    <div class="time-slot">
                        <div class="time-inputs">
                            <label>From:</label>
                            <input type="number" class="slot-hour-from" min="1" max="12" value="${fromTime.hour}" />:
                            <input type="number" class="slot-minute-from" min="0" max="59" step="15" value="${fromTime.minute}" />
                            <select class="slot-period-from">
                                <option value="AM" ${fromTime.period === 'AM' ? 'selected' : ''}>AM</option>
                                <option value="PM" ${fromTime.period === 'PM' ? 'selected' : ''}>PM</option>
                            </select>
                        </div>
                         <div class="time-inputs">
                            <label>To:</label>
                            <input type="number" class="slot-hour-to" min="1" max="12" value="${toTime.hour}" />:
                            <input type="number" class="slot-minute-to" min="0" max="59" step="15" value="${toTime.minute}" />
                            <select class="slot-period-to">
                                <option value="AM" ${toTime.period === 'AM' ? 'selected' : ''}>AM</option>
                                <option value="PM" ${toTime.period === 'PM' ? 'selected' : ''}>PM</option>
                            </select>
                        </div>
                        <div class="limit-input">
                            <label>Limit:</label><input type="number" class="slot-limit" min="1" value="${slot.limit || 50}" placeholder="e.g., 50"/>
                        </div>
                        <button class="remove-slot-btn" title="Remove slot"><i class="fas fa-times"></i></button>
                    </div>`;
            }).join('');

            return `
            <div class="day-schedule-card" data-day="${day}">
                <h4>${day}</h4>
                <div class="time-slots-grid">${daySlots}</div>
                <button class="add-slot-btn"><i class="fas fa-plus"></i> Add Slot</button>
            </div>`;
        }).join('');
        document.querySelector('.schedule-actions').style.display = 'block';
    };

    const fetchDoctorSchedule = async (doctorId) => {
        if (!doctorId) {
            // This is now handled by the keyup event, but we keep it as a safeguard.
            scheduleEditorContainer.innerHTML = '<p class="placeholder-text">Please search for and select a doctor to view their schedule.</p>';
            document.querySelector('.schedule-actions').style.display = 'none';
            return;
        }
        scheduleEditorContainer.innerHTML = `<div class="loading-cell" style="padding: 4rem 0;"><div class="spinner"></div><span>Loading Schedule...</span></div>`;
        try {
            const response = await fetch(`api.php?fetch=fetch_doctor_schedule&doctor_id=${doctorId}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderScheduleEditor(result.data);
        } catch (error) {
            scheduleEditorContainer.innerHTML = `<p class="placeholder-text" style="color:var(--danger-color)">Failed to load schedule: ${error.message}</p>`;
        }
    };

    scheduleEditorContainer.addEventListener('click', (e) => {
        if (e.target.closest('.add-slot-btn')) {
            const grid = e.target.closest('.day-schedule-card').querySelector('.time-slots-grid');
            const slotDiv = document.createElement('div');
            slotDiv.className = 'time-slot';
            slotDiv.innerHTML = `
                <div class="time-inputs">
                    <label>From:</label>
                    <input type="number" class="slot-hour-from" min="1" max="12" value="9" />:
                    <input type="number" class="slot-minute-from" min="0" max="59" step="15" value="00" />
                    <select class="slot-period-from">
                        <option value="AM" selected>AM</option><option value="PM">PM</option>
                    </select>
                </div>
                <div class="time-inputs">
                    <label>To:</label>
                    <input type="number" class="slot-hour-to" min="1" max="12" value="5" />:
                    <input type="number" class="slot-minute-to" min="0" max="59" step="15" value="00" />
                    <select class="slot-period-to">
                        <option value="AM">AM</option><option value="PM" selected>PM</option>
                    </select>
                </div>
                <div class="limit-input">
                    <label>Limit:</label><input type="number" class="slot-limit" min="1" value="50" />
                </div>
                <button class="remove-slot-btn" title="Remove slot"><i class="fas fa-times"></i></button>`;
            grid.appendChild(slotDiv);
        }
        if (e.target.closest('.remove-slot-btn')) {
            e.target.closest('.time-slot').remove();
        }
    });

    saveScheduleBtn.addEventListener('click', async () => {
        const doctorId = selectedDoctorId.value;
        if (!doctorId) return showNotification('Please select a doctor first.', 'error');
        
        const scheduleData = {};
        let isValid = true;
        document.querySelectorAll('.day-schedule-card').forEach(dayCard => {
            const day = dayCard.dataset.day;
            const slots = [];
            dayCard.querySelectorAll('.time-slot').forEach(slotEl => {
                const fromHour = slotEl.querySelector('.slot-hour-from').value;
                const fromMinute = slotEl.querySelector('.slot-minute-from').value;
                const fromPeriod = slotEl.querySelector('.slot-period-from').value;
                const toHour = slotEl.querySelector('.slot-hour-to').value;
                const toMinute = slotEl.querySelector('.slot-minute-to').value;
                const toPeriod = slotEl.querySelector('.slot-period-to').value;
                const limit = slotEl.querySelector('.slot-limit').value;

                if (fromHour && fromMinute && fromPeriod && toHour && toMinute && toPeriod && limit) {
                    const from = convert12hTo24h(fromHour, fromMinute, fromPeriod);
                    const to = convert12hTo24h(toHour, toMinute, toPeriod);
                    
                    if (to <= from) { isValid = false; }
                    slots.push({ from, to, limit: parseInt(limit, 10) });
                }
            });
            scheduleData[day] = slots;
        });

        if (!isValid) return showNotification(`Error: 'To' time must be after 'From' time in all slots.`, 'error');

        const formData = new FormData();
        formData.append('action', 'update_doctor_schedule');
        formData.append('doctor_id', doctorId);
        formData.append('slots', JSON.stringify(scheduleData));
        formData.append('csrf_token', csrfToken);
        handleFormSubmit(formData);
    });

    const renderStaffShiftRow = (staff) => `
        <tr data-staff-id="${staff.id}">
            <td>${staff.name}</td>
            <td>${staff.display_user_id}</td>
            <td id="shift-status-${staff.id}">${staff.shift}</td>
            <td>
                <select class="shift-select" data-id="${staff.id}">
                    <option value="day" ${staff.shift === 'day' ? 'selected' : ''}>Day</option>
                    <option value="night" ${staff.shift === 'night' ? 'selected' : ''}>Night</option>
                    <option value="off" ${staff.shift === 'off' ? 'selected' : ''}>Off</option>
                </select>
            </td>
        </tr>`;

const fetchStaffShifts = (searchTerm = '') => fetchAndRender({
    endpoint: `api.php?fetch=staff_for_shifting&search=${encodeURIComponent(searchTerm)}`,
    target: document.getElementById('staff-shifts-table-body'),
    renderRow: renderStaffShiftRow,
    columns: 4,
    emptyMessage: 'No active staff found.'
});

const staffSearchInput = document.getElementById('staff-search-input');
let staffSearchDebounce;
staffSearchInput.addEventListener('keyup', () => {
    clearTimeout(staffSearchDebounce);
    staffSearchDebounce = setTimeout(() => {
        fetchStaffShifts(staffSearchInput.value.trim());
    }, 300);
});

    document.getElementById('staff-shifts-table-body').addEventListener('change', async (e) => {
        if (e.target.classList.contains('shift-select')) {
            const staffId = e.target.dataset.id;
            const newShift = e.target.value;
            const formData = new FormData();
            formData.append('action', 'update_staff_shift');
            formData.append('staff_id', staffId);
            formData.append('shift', newShift);
            formData.append('csrf_token', csrfToken);
            const result = await (await fetch('api.php', { method: 'POST', body: formData })).json();
            if (result.success) {
                showNotification(result.message, 'success');
                document.getElementById(`shift-status-${staffId}`).textContent = newShift;
            } else {
                showNotification(`Error: ${result.message}`, 'error');
                fetchStaffShifts();
            }
        }
    });

    [document.getElementById('schedules-panel'), document.getElementById('notifications-panel')].forEach(panel => {
        panel.querySelectorAll('.schedule-tab-button').forEach(button => {
            button.addEventListener('click', function () {
                const tabId = this.dataset.tab;
                panel.querySelectorAll('.schedule-tab-button, .schedule-tab-content').forEach(el => el.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(`${tabId}-content`).classList.add('active');
                if (tabId === 'staff-shifts') fetchStaffShifts();
            });
        });
    });

    document.getElementById('notification-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        const confirmed = await showConfirmation('Send Notification', `Send broadcast to all ${formData.get('role')}s?`);
        if (confirmed) {
            handleFormSubmit(formData);
            e.target.reset();
        }
    });

    document.getElementById('individual-notification-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        if (!formData.get('recipient_user_id')) return showNotification('Please select a valid user.', 'error');
        const confirmed = await showConfirmation('Send Message', `Send message to ${document.getElementById('user-search').value}?`);
        if (confirmed) {
            handleFormSubmit(formData);
            e.target.reset();
        }
    });

    // --- NOTIFICATION CENTER ---
    const notificationBell = document.getElementById('notification-bell-wrapper');
    const notificationCountBadge = document.getElementById('notification-count');
    const allNotificationsPanel = document.getElementById('all-notifications-panel');

    const updateNotificationCount = async () => {
        try {
            const result = await (await fetch('api.php?fetch=unread_notification_count')).json();
            if (result.success && result.count > 0) {
                notificationCountBadge.textContent = result.count;
                notificationCountBadge.style.display = 'grid';
            } else {
                notificationCountBadge.style.display = 'none';
            }
        } catch (error) {
            console.error('Failed to fetch notification count:', error);
        }
    };

    const loadAllNotifications = async () => {
        allNotificationsPanel.innerHTML = `<div class="loading-cell" style="padding: 4rem 0;"><div class="spinner"></div><span>Loading Notifications...</span></div>`;
        const data = await fetchAndRender({ endpoint: 'api.php?fetch=all_notifications' });
        if (data) {
            let content = `<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); padding-bottom: 1rem; margin-bottom: 1rem;"><h2 style="margin: 0;">All Notifications</h2></div>`;
            if (data.length > 0) {
                content += data.map(notif => `
                    <div class="notification-item" style="display: flex; gap: 1rem; padding: 1.5rem; border-bottom: 1px solid var(--border-light); ${notif.is_read == 0 ? 'background-color: var(--bg-grey);' : ''}">
                        <div style="font-size: 1.5rem; color: var(--primary-color); padding-top: 5px;"><i class="fas fa-envelope-open-text"></i></div>
                        <div style="flex-grow: 1;">
                            <p style="margin: 0 0 0.25rem 0; font-weight: ${notif.is_read == 0 ? '600' : '500'};">${notif.message}</p>
                            <small style="color: var(--text-muted);">From: ${notif.sender_name} on ${new Date(notif.created_at).toLocaleString()}</small>
                        </div>
                    </div>`).join('');
            } else {
                content += '<p style="text-align: center; padding: 2rem;">You have no notifications.</p>';
            }
            allNotificationsPanel.innerHTML = content;
        } else {
            allNotificationsPanel.innerHTML = '<p style="text-align: center; color: var(--danger-color);">Could not load notifications.</p>';
        }
    };

    notificationBell.addEventListener('click', async (e) => {
        e.stopPropagation();
        try {
            const formData = new FormData();
            formData.append('action', 'mark_notifications_read');
            formData.append('csrf_token', csrfToken);
            const result = await (await fetch('api.php', { method: 'POST', body: formData })).json();
            if (result.success) {
                notificationCountBadge.style.display = 'none';
                handlePanelSwitch(notificationBell);
                loadAllNotifications();
            } else {
                showNotification(result.message || 'Could not mark notifications as read.', 'error');
            }
        } catch (error) {
            showNotification('A network error occurred.', 'error');
        }
    });

    let searchTimeout;
    const userSearch = document.getElementById('user-search');
    const userSearchResults = document.getElementById('user-search-results');
    const recipientUserIdInput = document.getElementById('recipient-user-id');

    userSearch.addEventListener('keyup', () => {
        clearTimeout(searchTimeout);
        const searchTerm = userSearch.value.trim();
        if (searchTerm.length < 2) { userSearchResults.style.display = 'none'; return; }
        searchTimeout = setTimeout(async () => {
            try {
                const result = await (await fetch(`api.php?fetch=search_users&term=${encodeURIComponent(searchTerm)}`)).json();
                if (!result.success) throw new Error(result.message);
                if (result.data.length > 0) {
                    userSearchResults.innerHTML = result.data.map(user => `
                        <div class="search-result-item" data-id="${user.id}" data-name="${user.name} (${user.display_user_id})">
                            <strong>${user.name}</strong> (${user.display_user_id}) - <small>${user.role}</small>
                        </div>`).join('');
                } else {
                    userSearchResults.innerHTML = '<div class="search-result-item none">No users found.</div>';
                }
                userSearchResults.style.display = 'block';
            } catch (error) {
                userSearchResults.innerHTML = '<div class="search-result-item none">Search error.</div>';
                userSearchResults.style.display = 'block';
            }
        }, 300);
    });

    userSearchResults.addEventListener('click', (e) => {
        const item = e.target.closest('.search-result-item');
        if (item && item.dataset.id) {
            recipientUserIdInput.value = item.dataset.id;
            userSearch.value = item.dataset.name;
            userSearchResults.style.display = 'none';
        }
    });

    document.addEventListener('click', (e) => {
        if (!userSearch.contains(e.target) && !userSearchResults.contains(e.target)) {
            userSearchResults.style.display = 'none';
        }
    });

    // --- MESSENGER LOGIC ---
    function initializeMessenger() {
        if (messengerInitialized) return;

        fetchAndRenderConversations();
        
        const chatArea = document.querySelector('.chat-area');
        const backBtn = document.getElementById('back-to-conversations-btn');

        const searchInput = document.getElementById('messenger-user-search');
        searchInput.addEventListener('input', () => {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => handleMessengerSearch(searchInput.value), 300);
        });

        const listContainer = document.getElementById('conversation-list-items');
        listContainer.addEventListener('click', handleListItemClick);
        
        document.getElementById('message-form').addEventListener('submit', handleSendMessage);
        
        backBtn.addEventListener('click', () => {
            chatArea.classList.remove('active');
            activeConversationId = null; 
        });

        messengerInitialized = true;
    }

    async function handleMessengerSearch(query) {
        const listContainer = document.getElementById('conversation-list-items');
        query = query.trim();
        if (query.length < 2) {
            await fetchAndRenderConversations();
            return;
        }
        
        listContainer.innerHTML = `<div class="loading-cell"><div class="spinner"></div><span>Searching...</span></div>`;
        
        try {
            const data = await fetchAndRender({endpoint: `api.php?fetch=search_users&term=${encodeURIComponent(query)}`});
            if(data) {
                listContainer.innerHTML = data.length > 0 ? data.map(user => {
                    const avatarUrl = `../uploads/profile_pictures/${user.profile_picture || 'default.png'}`;
                    return `
                        <div class="search-result-item" data-user-id="${user.id}" data-user-name="${user.name}" data-user-avatar="${avatarUrl}" data-user-display-id="${user.role}">
                            <img src="${avatarUrl}" alt="${user.name}" class="user-avatar" onerror="this.src='../uploads/profile_pictures/default.png'">
                            <div class="conversation-details">
                                <span class="user-name">${user.name}</span>
                                <span class="last-message">${user.role} - ${user.display_user_id}</span>
                            </div>
                        </div>`;
                }).join('') : `<p class="no-items-message">No users found.</p>`;
            }
        } catch (error) {
            console.error("Search error:", error);
            listContainer.innerHTML = `<p class="no-items-message" style="color: var(--danger-color)">Search failed.</p>`;
        }
    }
    
    async function fetchAndRenderConversations() {
        const listContainer = document.getElementById('conversation-list-items');
        listContainer.innerHTML = `<div class="loading-cell"><div class="spinner"></div><span>Loading Conversations...</span></div>`;

        try {
            const data = await fetchAndRender({ endpoint: 'api.php?fetch=conversations' });
            if (data) {
                listContainer.innerHTML = data.length > 0 ? data.map(conv => {
                    const avatarUrl = `../uploads/profile_pictures/${conv.other_user_profile_picture || 'default.png'}`;
                    const lastMessageTime = conv.last_message_time ? new Date(conv.last_message_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
                    return `
                        <div class="conversation-item ${conv.conversation_id === activeConversationId ? 'active' : ''}" data-conversation-id="${conv.conversation_id}" data-user-id="${conv.other_user_id}" data-user-name="${conv.other_user_name}" data-user-avatar="${avatarUrl}" data-user-display-id="${conv.other_user_role}">
                            <img src="${avatarUrl}" alt="${conv.other_user_name}" class="user-avatar" onerror="this.src='../uploads/profile_pictures/default.png'">
                            <div class="conversation-details">
                                <span class="user-name">${conv.other_user_name}</span>
                                <span class="last-message">${conv.last_message || 'No messages yet'}</span>
                            </div>
                            <div class="conversation-meta">
                                <span class="message-time">${lastMessageTime}</span>
                                ${conv.unread_count > 0 ? `<div class="unread-indicator">${conv.unread_count}</div>` : ''}
                            </div>
                        </div>`;
                }).join('') : `<p class="no-items-message">No conversations yet. Search for a user to start chatting.</p>`;
            }
        } catch (error) {
            console.error("Failed to fetch conversations:", error);
            listContainer.innerHTML = `<p class="no-items-message" style="color: var(--danger-color)">Could not load conversations.</p>`;
        }
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
        selectConversation(
            conversationId,
            parseInt(item.dataset.userId, 10),
            item.dataset.userName,
            item.dataset.userAvatar,
            item.dataset.userDisplayId
        );
    }
    
    function selectConversation(conversationId, userId, userName, userAvatar, userDisplayId) {
        activeConversationId = conversationId;
        activeReceiverId = userId;
        
        document.querySelector('.chat-area').classList.add('active');

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
        container.innerHTML = `<div class="loading-cell" style="flex-grow: 1; display: flex; flex-direction: column; justify-content: center;"><div class="spinner"></div><span>Loading Messages...</span></div>`;
        try {
            const data = await fetchAndRender({endpoint: `api.php?fetch=messages&conversation_id=${conversationId}`});
            if(!data) throw new Error("Could not fetch messages.");
            
            let messagesHtml = '';
            let lastMessageDateStr = null;

            if (data.length > 0) {
                data.forEach(message => {
                    const currentMessageDateStr = new Date(message.created_at).toDateString();
                    if (currentMessageDateStr !== lastMessageDateStr) {
                        messagesHtml += `<div class="message-date-separator">${formatDateSeparator(message.created_at)}</div>`;
                        lastMessageDateStr = currentMessageDateStr;
                    }
                    const sentOrReceived = message.sender_id === currentUserId ? 'sent' : 'received';
                    messagesHtml += `
                        <div class="message ${sentOrReceived}">
                            <div class="message-content"><p>${message.message_text}</p></div>
                            <span class="message-timestamp">${new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                        </div>
                    `;
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
                const sentOrReceived = result.data.sender_id === currentUserId ? 'sent' : 'received';
                container.insertAdjacentHTML('beforeend', `
                    <div class="message ${sentOrReceived}">
                        <div class="message-content"><p>${result.data.message_text}</p></div>
                        <span class="message-timestamp">${new Date(result.data.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                    </div>`);
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


    // --- INITIAL LOAD ---
    updateDashboardStats();
    updateNotificationCount();
    fetchDepartments();
    generateReport();
});