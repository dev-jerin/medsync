document.addEventListener("DOMContentLoaded", function () {

    const userSearchInput = document.getElementById('user-search-input');
    userSearchInput.addEventListener('keyup', () => {
        // A small delay to avoid sending too many requests while typing
        setTimeout(() => {
            fetchUsers(currentRole, userSearchInput.value.trim());
        }, 300);
    });
    // --- CORE UI ELEMENTS & STATE ---
    const csrfToken = document.querySelector('input[name="csrf_token"]').value; // Read from the DOM
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const navLinks = document.querySelectorAll('.nav-link');
    const dropdownToggles = document.querySelectorAll('.nav-dropdown-toggle');
    const panelTitle = document.getElementById('panel-title');
    const welcomeMessage = document.getElementById('welcome-message');
    let currentRole = 'user';
    let currentAccommodationType = 'bed'; // 'bed' or 'room'
    let userRolesChart = null;
    let reportChart = null;

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
                okBtn.removeEventListener('click', handleOk);
                cancelBtn.removeEventListener('click', handleCancel);
            };

            const handleOk = () => cleanup(true);
            const handleCancel = () => cleanup(false);

            okBtn.addEventListener('click', handleOk, { once: true });
            cancelBtn.addEventListener('click', handleCancel, { once: true });
        });
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

        // Update active link styling in the sidebar
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
            const type = targetId.split('-')[1]; // 'bed' or 'room'
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
            if (targetId === 'reports') generateReport();
            if (targetId === 'activity') fetchActivityLogs();
            if (targetId === 'schedules' && doctorSelect.options.length <= 1) fetchDoctorsForScheduling();
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
            const response = await fetch('admin.php?fetch=dashboard_stats');
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

    const fetchDoctorsForAppointmentFilter = async () => {
        const doctorFilterSelect = document.getElementById('appointment-doctor-filter');
        if (doctorFilterSelect.options.length > 1) return;

        try {
            const response = await fetch('admin.php?fetch=doctors_for_scheduling');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            result.data.forEach(doctor => {
                doctorFilterSelect.innerHTML += `<option value="${doctor.id}">${doctor.name} (${doctor.display_user_id})</option>`;
            });
        } catch (error) {
            console.error("Failed to fetch doctors for filter:", error);
        }
    };

    const fetchAppointments = async (doctorId = 'all') => {
        const tableBody = document.getElementById('appointments-table-body');
        tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Loading appointments...</td></tr>`;
        try {
            const response = await fetch(`admin.php?fetch=appointments&doctor_id=${doctorId}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            if (result.data.length > 0) {
                tableBody.innerHTML = result.data.map(appt => {
                    const status = appt.status.charAt(0).toUpperCase() + appt.status.slice(1);
                    return `
                <tr>
                    <td>${appt.id}</td>
                    <td>${appt.patient_name} (${appt.patient_display_id})</td>
                    <td>${appt.doctor_name}</td>
                    <td>${new Date(appt.appointment_date).toLocaleString()}</td>
                    <td><span class="status-badge ${appt.status.toLowerCase()}">${status}</span></td>
                </tr>
            `}).join('');
            } else {
                tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No appointments found.</td></tr>`;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Failed to load appointments: ${error.message}</td></tr>`;
        }
    };

    // --- USER MANAGEMENT (CRUD & Detail View) ---
    const userModal = document.getElementById('user-modal');
    const userForm = document.getElementById('user-form');
    const userDetailModal = document.getElementById('user-detail-modal');
    const addUserBtn = document.getElementById('add-user-btn');
    const quickAddUserBtn = document.getElementById('quick-add-user-btn');
    
    const modalTitle = document.getElementById('modal-title');
    const passwordGroup = document.getElementById('password-group');
    const activeGroup = document.getElementById('is_active-group');
    const roleSelect = document.getElementById('role');
    const doctorFields = document.getElementById('doctor-fields');
    const staffFields = document.getElementById('staff-fields');

    const openDetailedProfileModal = async (userId) => {
        const contentDiv = document.getElementById('user-detail-content');
        contentDiv.innerHTML = '<p>Loading profile...</p>';
        userDetailModal.classList.add('show');
        try {
            const response = await fetch(`admin.php?fetch=user_details&id=${userId}`);
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
            const response = await fetch('admin.php?fetch=departments');
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

    const openUserModal = (mode, user = {}) => {
        userForm.reset();
        roleSelect.value = currentRole;
        roleSelect.disabled = (mode === 'edit');

        if (mode === 'add') {
            modalTitle.textContent = `Add New ${currentRole.charAt(0).toUpperCase() + currentRole.slice(1)}`;
            document.getElementById('form-action').value = 'addUser';
            document.getElementById('password').required = true;
            passwordGroup.style.display = 'block';
            activeGroup.style.display = 'none';
        } else { // edit mode
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
                    closeModal(userModal);
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
        userModal.classList.add('show');
    };

    const closeModal = (modalElement) => modalElement.classList.remove('show');

    addUserBtn.addEventListener('click', () => openUserModal('add'));
    quickAddUserBtn.addEventListener('click', (e) => {
        e.preventDefault();
        document.querySelector('.nav-link[data-target="users-user"]').click();
        setTimeout(() => openUserModal('add'), 100);
    });
    userModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(userModal));
    userModal.addEventListener('click', (e) => { if (e.target === userModal) closeModal(userModal); });
    userDetailModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(userDetailModal));
    userDetailModal.addEventListener('click', (e) => { if (e.target === userDetailModal) closeModal(userDetailModal); });

    const fetchUsers = async (role, searchTerm = '') => {
        currentRole = role;
        document.getElementById('user-table-title').textContent = `${role.charAt(0).toUpperCase() + role.slice(1)}s`;
        const tableBody = document.getElementById('user-table-body');
        tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Loading...</td></tr>`;

        try {
            const response = await fetch(`admin.php?fetch=users&role=${role}&search=${encodeURIComponent(searchTerm)}`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            if (result.data.length > 0) {
                tableBody.innerHTML = result.data.map(user => `
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
                    `).join('');
            } else {
                tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">No users found for this role.</td></tr>`;
            }
        } catch (error) {
            console.error('Fetch error:', error);
            tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Failed to load users: ${error.message}</td></tr>`;
            showNotification(error.message, 'error');
        }
    };

    document.getElementById('user-table-body').addEventListener('click', async (e) => {
        const row = e.target.closest('tr');
        if (!row) return;

        const editBtn = e.target.closest('.btn-edit');
        const deleteBtn = e.target.closest('.btn-delete');

        if (editBtn) {
            e.stopPropagation();
            const user = JSON.parse(editBtn.dataset.user);
            openUserModal('edit', user);
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

    const handleFormSubmit = async (formData, refreshTarget = null) => {
        try {
            const response = await fetch('admin.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                
                const action = formData.get('action');
                if (action === 'sendNotification' || action === 'sendIndividualNotification') {
                    updateNotificationCount();
                }

                if (action.toLowerCase().includes('user')) closeModal(userModal);
                else if (action.toLowerCase().includes('medicine')) closeModal(medicineModal);
                else if (action.toLowerCase().includes('blood')) closeModal(bloodModal);
                else if (action.toLowerCase().includes('ward')) closeModal(wardFormModal);
                else if (action.toLowerCase().includes('accommodation')) closeModal(document.getElementById('accommodation-modal'));
                else if (action.toLowerCase().includes('department')) closeModal(departmentModal);

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

    userForm.addEventListener('submit', (e) => {
        e.preventDefault();
        handleFormSubmit(new FormData(userForm), `users-${currentRole}`);
    });

    // --- ADMIN PROFILE EDIT ---
    const profileForm = document.getElementById('profile-form');

    const fetchMyProfile = async () => {
        try {
            const response = await fetch(`admin.php?fetch=my_profile`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
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

    profileForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(profileForm);
        try {
            const response = await fetch('admin.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                showNotification(result.message, 'success');
                document.getElementById('welcome-message').textContent = `Hello, ${formData.get('name')}!`;
                document.querySelector('.user-profile-widget .user-info strong').textContent = formData.get('name');
            } else {
                throw new Error(result.message || 'An unknown error occurred.');
            }
        } catch (error) {
            console.error('Profile update error:', error);
            showNotification(error.message, 'error');
        }
    });

    const systemSettingsForm = document.getElementById('system-settings-form');
    if (systemSettingsForm) {
        systemSettingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const confirmed = await showConfirmation('Update Settings', 'Are you sure you want to save these system settings? This may affect system functionality like sending emails.');
            if (confirmed) {
                const formData = new FormData(systemSettingsForm);
                handleFormSubmit(formData);
                document.getElementById('gmail_app_password').value = '';
            }
        });
    }

    // --- INVENTORY MANAGEMENT ---
    const medicineModal = document.getElementById('medicine-modal');
    const medicineForm = document.getElementById('medicine-form');
    const addMedicineBtn = document.getElementById('add-medicine-btn');
    const medicineTableBody = document.getElementById('medicine-table-body');
    const departmentModal = document.getElementById('department-modal');
    const departmentForm = document.getElementById('department-form');
    const addDepartmentBtn = document.getElementById('add-department-btn');
    const departmentTableBody = document.getElementById('department-table-body');
    const bloodModal = document.getElementById('blood-modal');
    const bloodForm = document.getElementById('blood-form');
    const addBloodBtn = document.getElementById('add-blood-btn');
    const bloodTableBody = document.getElementById('blood-table-body');

    const openMedicineModal = (mode, medicine = {}) => {
        medicineForm.reset();
        if (mode === 'add') {
            document.getElementById('medicine-modal-title').textContent = 'Add New Medicine';
            document.getElementById('medicine-form-action').value = 'addMedicine';
            document.getElementById('medicine-low-stock-threshold').value = 10;
        } else {
            document.getElementById('medicine-modal-title').textContent = `Edit ${medicine.name}`;
            document.getElementById('medicine-form-action').value = 'updateMedicine';
            document.getElementById('medicine-id').value = medicine.id;
            document.getElementById('medicine-name').value = medicine.name;
            document.getElementById('medicine-description').value = medicine.description || '';
            document.getElementById('medicine-quantity').value = medicine.quantity;
            document.getElementById('medicine-unit-price').value = medicine.unit_price;
            document.getElementById('medicine-low-stock-threshold').value = medicine.low_stock_threshold;
        }
        medicineModal.classList.add('show');
    };

    addMedicineBtn.addEventListener('click', () => openMedicineModal('add'));
    medicineModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(medicineModal));
    medicineModal.addEventListener('click', (e) => { if (e.target === medicineModal) closeModal(medicineModal); });
    medicineForm.addEventListener('submit', (e) => {
        e.preventDefault();
        handleFormSubmit(new FormData(medicineForm), 'medicine');
    });

    const fetchMedicineInventory = async () => {
        medicineTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Loading...</td></tr>`;
        try {
            const response = await fetch('admin.php?fetch=medicines');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            if (result.data.length > 0) {
                medicineTableBody.innerHTML = result.data.map(med => {
                    const isLowStock = parseInt(med.quantity) <= parseInt(med.low_stock_threshold);
                    const statusClass = isLowStock ? 'low-stock' : 'in-stock';
                    const quantityClass = isLowStock ? 'quantity-low' : 'quantity-good';
                    return `
                        <tr data-medicine='${JSON.stringify(med)}'>
                            <td>${med.name}</td>
                            <td>${med.description || 'N/A'}</td>
                            <td><span class="${quantityClass}">${med.quantity}</span></td>
                            <td><span class="status-badge ${statusClass}">${isLowStock ? 'Low Stock' : 'In Stock'}</span></td>
                            <td>₹ ${parseFloat(med.unit_price).toFixed(2)}</td>
                            <td>${med.low_stock_threshold}</td>
                            <td>${new Date(med.updated_at).toLocaleString()}</td>
                            <td class="action-buttons">
                                <button class="btn-edit-medicine btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete-medicine btn-delete" title="Delete"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                    `}).join('');
            } else {
                medicineTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">No medicines found.</td></tr>`;
            }
        } catch (error) {
            medicineTableBody.innerHTML = `<tr><td colspan="8" style="text-align:center;">Failed to load medicines: ${error.message}</td></tr>`;
        }
    };

    medicineTableBody.addEventListener('click', async (e) => {
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

    const openBloodModal = (blood = {}) => {
        bloodForm.reset();
        document.getElementById('blood-modal-title').textContent = `Update Blood Unit`;
        document.getElementById('blood-group').value = blood.blood_group || 'A+';
        document.getElementById('blood-group').disabled = !!blood.blood_group;
        document.getElementById('blood-quantity-ml').value = blood.quantity_ml || 0;
        document.getElementById('blood-low-stock-threshold-ml').value = blood.low_stock_threshold_ml || 5000;
        bloodModal.classList.add('show');
    };

    addBloodBtn.addEventListener('click', () => openBloodModal());
    bloodModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(bloodModal));
    bloodModal.addEventListener('click', (e) => { if (e.target === bloodModal) closeModal(bloodModal); });

    bloodForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(bloodForm);
        if (document.getElementById('blood-group').disabled) {
            formData.set('blood_group', document.getElementById('blood-group').value);
        }
        handleFormSubmit(formData, 'blood');
    });

    const fetchBloodInventory = async () => {
        bloodTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Loading...</td></tr>`;
        try {
            const response = await fetch('admin.php?fetch=blood_inventory');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            if (result.data.length > 0) {
                bloodTableBody.innerHTML = result.data.map(blood => {
                    const isLowStock = parseInt(blood.quantity_ml) < parseInt(blood.low_stock_threshold_ml);
                    const statusClass = isLowStock ? 'low-stock' : 'in-stock';
                    const quantityClass = isLowStock ? 'quantity-low' : 'quantity-good';
                    return `
                        <tr data-blood='${JSON.stringify(blood)}'>
                            <td>${blood.blood_group}</td>
                            <td><span class="${quantityClass}">${blood.quantity_ml}</span> ml</td>
                            <td><span class="status-badge ${statusClass}">${isLowStock ? 'Low Stock' : 'In Stock'}</span></td>
                            <td>${blood.low_stock_threshold_ml} ml</td>
                            <td>${new Date(blood.last_updated).toLocaleString()}</td>
                            <td class="action-buttons">
                                <button class="btn-edit-blood btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                    `}).join('');
            } else {
                bloodTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">No blood inventory records found.</td></tr>`;
            }
        } catch (error) {
            bloodTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center;">Failed to load blood inventory.</td></tr>`;
        }
    };

    bloodTableBody.addEventListener('click', async (e) => {
        if (e.target.closest('.btn-edit-blood')) {
            const blood = JSON.parse(e.target.closest('tr').dataset.blood);
            openBloodModal(blood);
        }
    });

    const openDepartmentModal = (mode, department = {}) => {
        departmentForm.reset();
        if (mode === 'add') {
            document.getElementById('department-modal-title').textContent = 'Add New Department';
            document.getElementById('department-form-action').value = 'addDepartment';
            document.getElementById('department-active-group').style.display = 'none';
        } else {
            document.getElementById('department-modal-title').textContent = `Edit ${department.name}`;
            document.getElementById('department-form-action').value = 'updateDepartment';
            document.getElementById('department-id').value = department.id;
            document.getElementById('department-name').value = department.name;
            document.getElementById('department-is-active').value = department.is_active;
            document.getElementById('department-active-group').style.display = 'block';
        }
        departmentModal.classList.add('show');
    };

    addDepartmentBtn.addEventListener('click', () => openDepartmentModal('add'));
    departmentModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(departmentModal));
    departmentModal.addEventListener('click', (e) => { if (e.target === departmentModal) closeModal(departmentModal); });

    departmentForm.addEventListener('submit', (e) => {
        e.preventDefault();
        handleFormSubmit(new FormData(departmentForm), 'departments_management');
    });

    const fetchDepartmentsManagement = async () => {
        departmentTableBody.innerHTML = `<tr><td colspan="3" style="text-align:center;">Loading...</td></tr>`;
        try {
            const response = await fetch('admin.php?fetch=departments_management');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            if (result.data.length > 0) {
                departmentTableBody.innerHTML = result.data.map(dept => `
                        <tr data-department='${JSON.stringify(dept)}'>
                            <td>${dept.name}</td>
                            <td><span class="status-badge ${dept.is_active == 1 ? 'active' : 'inactive'}">${dept.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                            <td class="action-buttons">
                                <button class="btn-edit-department btn-edit" title="Edit"><i class="fas fa-edit"></i></button>
                                <button class="btn-delete-department btn-delete" title="Disable"><i class="fas fa-trash-alt"></i></button>
                            </td>
                        </tr>
                    `).join('');
            } else {
                departmentTableBody.innerHTML = `<tr><td colspan="3" style="text-align:center;">No departments found.</td></tr>`;
            }
        } catch (error) {
            departmentTableBody.innerHTML = `<tr><td colspan="3" style="text-align:center; color: var(--danger-color);">Failed to load departments.</td></tr>`;
        }
    };

    departmentTableBody.addEventListener('click', async (e) => {
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

    // --- Ward Management ---
    const addWardBtn = document.getElementById('add-ward-btn');
    const wardFormModal = document.getElementById('ward-form-modal');
    const wardForm = document.getElementById('ward-form');
    const wardTableBody = document.getElementById('ward-table-body');

    const openWardForm = (mode, ward = {}) => {
        wardForm.reset();
        wardFormModal.querySelector('#ward-form-modal-title').textContent = mode === 'add' ? 'Add New Ward' : `Edit ${ward.name}`;
        wardForm.querySelector('#ward-form-action').value = mode === 'add' ? 'addWard' : 'updateWard';
        const activeGroup = wardForm.querySelector('#ward-active-group');

        if (mode === 'edit') {
            wardForm.querySelector('#ward-id-input').value = ward.id;
            wardForm.querySelector('#ward-name-input').value = ward.name;
            wardForm.querySelector('#ward-capacity-input').value = ward.capacity;
            wardForm.querySelector('#ward-description-input').value = ward.description || '';
            wardForm.querySelector('#ward-is-active-input').value = ward.is_active;
            activeGroup.style.display = 'block';
        } else {
            activeGroup.style.display = 'none';
        }
        wardFormModal.classList.add('show');
    };

    addWardBtn.addEventListener('click', () => openWardForm('add'));
    wardFormModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(wardFormModal));
    wardForm.addEventListener('submit', (e) => {
        e.preventDefault();
        handleFormSubmit(new FormData(wardForm), 'wards');
    });

    const fetchWards = async () => {
        wardTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Loading...</td></tr>`;
        try {
            const response = await fetch('admin.php?fetch=wards');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            if (result.data.length > 0) {
                wardTableBody.innerHTML = result.data.map(ward => `
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
                    `).join('');
            } else {
                wardTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">No wards found.</td></tr>`;
            }
        } catch (error) {
            wardTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center;">Failed to load wards.</td></tr>`;
        }
    };

    wardTableBody.addEventListener('click', async (e) => {
        const row = e.target.closest('tr');
        if (!row) return;
        const ward = JSON.parse(row.dataset.ward);
        if (e.target.closest('.btn-edit-ward')) {
            openWardForm('edit', ward);
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

    // --- UNIFIED ACCOMMODATION MANAGEMENT ---
    const accommodationModal = document.getElementById('accommodation-modal');
    const accommodationForm = document.getElementById('accommodation-form');
    const addAccommodationBtn = document.getElementById('add-accommodation-btn');
    const accommodationsContainer = document.getElementById('accommodations-container');
    const accommodationStatusSelect = document.getElementById('accommodation-status');
    const accommodationPatientGroup = document.getElementById('accommodation-patient-group');
    const accommodationDoctorGroup = document.getElementById('accommodation-doctor-group');

    const populateAccommodationDropdowns = async () => {
        try {
            const [wardsRes, patientsRes, doctorsRes] = await Promise.all([
                fetch('admin.php?fetch=wards'),
                fetch('admin.php?fetch=patients_for_accommodations'),
                fetch('admin.php?fetch=doctors_for_scheduling')
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

    const openAccommodationModal = async (mode, item = {}, type) => {
        accommodationForm.reset();
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
            
            setTimeout(() => { // Allow dropdowns to populate
                document.getElementById('accommodation-status').value = item.status;
                if (type === 'bed') {
                    document.getElementById('accommodation-ward-id').value = item.ward_id;
                }
                accommodationStatusSelect.dispatchEvent(new Event('change')); // Trigger visibility change
                document.getElementById('accommodation-patient-id').value = item.patient_id || '';
                document.getElementById('accommodation-doctor-id').value = item.doctor_id || '';
            }, 150);
        } else {
            document.getElementById('accommodation-price-per-day').value = '0.00';
        }
        
        accommodationModal.classList.add('show');
    };

    addAccommodationBtn.addEventListener('click', () => openAccommodationModal('add', {}, currentAccommodationType));
    accommodationModal.querySelector('.modal-close-btn').addEventListener('click', () => closeModal(accommodationModal));
    accommodationForm.addEventListener('submit', (e) => {
        e.preventDefault();
        handleFormSubmit(new FormData(accommodationForm), `accommodations-${currentAccommodationType}`);
    });

    const fetchAccommodations = async (type) => {
        accommodationsContainer.innerHTML = `<p style="text-align:center;">Loading ${type}s...</p>`;
        const typeName = type.charAt(0).toUpperCase() + type.slice(1);
        document.getElementById('accommodations-title').textContent = `${typeName} Management`;
        addAccommodationBtn.innerHTML = `<i class="fas fa-plus"></i> Add New ${typeName}`;

        try {
            const response = await fetch(`admin.php?fetch=accommodations&type=${type}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            if (result.data.length > 0) {
                accommodationsContainer.innerHTML = result.data.map(item => {
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
                accommodationsContainer.innerHTML = `<p style="text-align:center;">No ${type}s found. Add some to get started.</p>`;
            }
        } catch (error) {
            accommodationsContainer.innerHTML = `<p style="text-align:center;">Failed to load ${type}s: ${error.message}</p>`;
        }
    };

    accommodationsContainer.addEventListener('click', async (e) => {
        const card = e.target.closest('.bed-card, .room-card');
        if (!card) return;

        const item = JSON.parse(card.dataset.item);
        if (e.target.closest('.btn-edit-item')) {
            openAccommodationModal('edit', item, item.type);
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
    const generateReportBtn = document.getElementById('generate-report-btn');
    const summaryCardsContainer = document.getElementById('report-summary-cards');

    const generateReport = async () => {
        const reportType = document.getElementById('report-type').value;
        const period = document.getElementById('report-period').value;

        document.getElementById('pdf-report-type').value = reportType;
        document.getElementById('pdf-period').value = period;
        summaryCardsContainer.innerHTML = '<p>Loading summary...</p>';
        document.getElementById('report-table-container').innerHTML = '';

        try {
            const response = await fetch(`admin.php?fetch=report&type=${reportType}&period=${period}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            const { summary, chartData, tableData } = result.data;

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

            const chartCtx = document.getElementById('report-chart').getContext('2d');
            if (reportChart) reportChart.destroy();
            reportChart = new Chart(chartCtx, {
                type: 'line',
                data: {
                    labels: chartData.map(item => item.label),
                    datasets: [{
                        label: reportType.charAt(0).toUpperCase() + reportType.slice(1) + ' Trend',
                        data: chartData.map(item => item.value),
                        borderColor: 'var(--primary-color)',
                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } } }
            });

            const tableContainer = document.getElementById('report-table-container');
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
            }

        } catch (error) {
            showNotification('Failed to generate report: ' + error.message, 'error');
            summaryCardsContainer.innerHTML = `<p style="color: var(--danger-color);">Could not load report summary.</p>`;
        }
    };

    generateReportBtn.addEventListener('click', generateReport);

    // --- ACTIVITY LOG (AUDIT TRAIL) ---
    const activityLogContainer = document.getElementById('activity-log-container');
    const refreshLogsBtn = document.getElementById('refresh-logs-btn');

    const fetchActivityLogs = async () => {
        activityLogContainer.innerHTML = '<p style="text-align: center;">Loading logs...</p>';
        try {
            const response = await fetch(`admin.php?fetch=activity&limit=50`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            if (result.data.length > 0) {
                activityLogContainer.innerHTML = result.data.map(log => {
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
                }).join('');
            } else {
                activityLogContainer.innerHTML = `<p style="text-align: center;">No recent activity found.</p>`;
            }
        } catch (error) {
            activityLogContainer.innerHTML = `<p style="text-align: center; color: var(--danger-color);">Failed to load activity logs.</p>`;
        }
    };

    refreshLogsBtn.addEventListener('click', fetchActivityLogs);

    // --- SCHEDULES & NOTIFICATIONS PANELS ---
    const schedulesPanel = document.getElementById('schedules-panel');
    const doctorSelect = document.getElementById('doctor-select');
    const scheduleEditorContainer = document.getElementById('doctor-schedule-editor');
    const saveScheduleBtn = document.getElementById('save-schedule-btn');
    const notificationsPanel = document.getElementById('notifications-panel');
    const notificationForm = document.getElementById('notification-form');
    const individualNotificationForm = document.getElementById('individual-notification-form');
    const userSearch = document.getElementById('user-search');
    const userSearchResults = document.getElementById('user-search-results');
    const recipientUserIdInput = document.getElementById('recipient-user-id');

    const fetchDoctorsForScheduling = async () => {
        try {
            const response = await fetch('admin.php?fetch=doctors_for_scheduling');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            doctorSelect.innerHTML = '<option value="">Select a Doctor...</option>';
            result.data.forEach(d => doctorSelect.innerHTML += `<option value="${d.id}">${d.name} (${d.display_user_id})</option>`);
        } catch (error) {
            doctorSelect.innerHTML = '<option value="">Could not load doctors</option>';
        }
    };

    const renderScheduleEditor = (slots) => {
        const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        scheduleEditorContainer.innerHTML = days.map(day => `
            <div class="day-schedule-card" data-day="${day}">
                <h4>${day}</h4>
                <div class="time-slots-grid">${(slots[day] || []).map(slot => `
                    <div class="time-slot">
                        <label>From:</label><input type="time" class="slot-from" value="${slot.from}" />
                        <label>To:</label><input type="time" class="slot-to" value="${slot.to}" />
                        <button class="remove-slot-btn" title="Remove slot"><i class="fas fa-times"></i></button>
                    </div>`).join('')}
                </div>
                <button class="add-slot-btn"><i class="fas fa-plus"></i> Add Slot</button>
            </div>`).join('');
        document.querySelector('.schedule-actions').style.display = 'block';
    };

    const fetchDoctorSchedule = async (doctorId) => {
        if (!doctorId) {
            scheduleEditorContainer.innerHTML = '<p class="placeholder-text">Please select a doctor to view or edit their schedule.</p>';
            document.querySelector('.schedule-actions').style.display = 'none';
            return;
        }
        scheduleEditorContainer.innerHTML = '<p class="placeholder-text">Loading schedule...</p>';
        try {
            const response = await fetch(`admin.php?fetch=fetch_doctor_schedule&doctor_id=${doctorId}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderScheduleEditor(result.data);
        } catch (error) {
            scheduleEditorContainer.innerHTML = `<p class="placeholder-text" style="color:var(--danger-color)">Failed to load schedule: ${error.message}</p>`;
        }
    };

    doctorSelect.addEventListener('change', () => fetchDoctorSchedule(doctorSelect.value));

    scheduleEditorContainer.addEventListener('click', (e) => {
        if (e.target.closest('.add-slot-btn')) {
            const grid = e.target.closest('.day-schedule-card').querySelector('.time-slots-grid');
            const slotDiv = document.createElement('div');
            slotDiv.className = 'time-slot';
            slotDiv.innerHTML = `<label>From:</label><input type="time" class="slot-from" value="09:00" /><label>To:</label><input type="time" class="slot-to" value="13:00" /><button class="remove-slot-btn" title="Remove slot"><i class="fas fa-times"></i></button>`;
            grid.appendChild(slotDiv);
        }
        if (e.target.closest('.remove-slot-btn')) {
            e.target.closest('.time-slot').remove();
        }
    });

    saveScheduleBtn.addEventListener('click', async () => {
        const doctorId = doctorSelect.value;
        if (!doctorId) return showNotification('Please select a doctor first.', 'error');
        
        const scheduleData = {};
        let isValid = true;
        document.querySelectorAll('.day-schedule-card').forEach(dayCard => {
            const day = dayCard.dataset.day;
            const slots = [];
            dayCard.querySelectorAll('.time-slot').forEach(slotEl => {
                const from = slotEl.querySelector('.slot-from').value;
                const to = slotEl.querySelector('.slot-to').value;
                if (from && to) {
                    if (to <= from) { isValid = false; }
                    slots.push({ from, to });
                }
            });
            scheduleData[day] = slots;
        });

        if (!isValid) return showNotification(`'To' time must be after 'From' time.`, 'error');

        const formData = new FormData();
        formData.append('action', 'update_doctor_schedule');
        formData.append('doctor_id', doctorId);
        formData.append('slots', JSON.stringify(scheduleData));
        formData.append('csrf_token', csrfToken);
        handleFormSubmit(formData);
    });

    const fetchStaffShifts = async () => {
        const staffTableBody = document.getElementById('staff-shifts-table-body');
        staffTableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Loading...</td></tr>';
        try {
            const response = await fetch('admin.php?fetch=staff_for_shifting');
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            if (result.data.length > 0) {
                staffTableBody.innerHTML = result.data.map(staff => `
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
                    </tr>`).join('');
            } else {
                staffTableBody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No active staff found.</td></tr>';
            }
        } catch (error) {
            staffTableBody.innerHTML = `<tr><td colspan="4" style="text-align:center; color: var(--danger-color);">Failed to load shifts.</td></tr>`;
        }
    };

    document.getElementById('staff-shifts-table-body').addEventListener('change', async (e) => {
        if (e.target.classList.contains('shift-select')) {
            const staffId = e.target.dataset.id;
            const newShift = e.target.value;
            const formData = new FormData();
            formData.append('action', 'update_staff_shift');
            formData.append('staff_id', staffId);
            formData.append('shift', newShift);
            formData.append('csrf_token', csrfToken);
            const result = await (await fetch('admin.php', { method: 'POST', body: formData })).json();
            if (result.success) {
                showNotification(result.message, 'success');
                document.getElementById(`shift-status-${staffId}`).textContent = newShift;
            } else {
                showNotification(`Error: ${result.message}`, 'error');
                fetchStaffShifts();
            }
        }
    });

    [schedulesPanel, notificationsPanel].forEach(panel => {
        panel.querySelectorAll('.schedule-tab-button').forEach(button => {
            button.addEventListener('click', function () {
                const tabId = this.dataset.tab;
                panel.querySelectorAll('.schedule-tab-button, .schedule-tab-content').forEach(el => el.classList.remove('active'));
                this.classList.add('active');
                document.getElementById(`${tabId}-content`).classList.add('active');
                if (tabId === 'doctor-availability' && doctorSelect.options.length <= 1) fetchDoctorsForScheduling();
                if (tabId === 'staff-shifts') fetchStaffShifts();
            });
        });
    });

    notificationForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(notificationForm);
        const confirmed = await showConfirmation('Send Notification', `Send broadcast to all ${formData.get('role')}s?`);
        if (confirmed) {
            handleFormSubmit(formData);
            notificationForm.reset();
        }
    });

    individualNotificationForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(individualNotificationForm);
        if (!formData.get('recipient_user_id')) return showNotification('Please select a valid user.', 'error');
        const confirmed = await showConfirmation('Send Message', `Send message to ${document.getElementById('user-search').value}?`);
        if (confirmed) {
            handleFormSubmit(formData);
            individualNotificationForm.reset();
        }
    });

    // --- NOTIFICATION CENTER LOGIC ---
    const notificationBell = document.getElementById('notification-bell-wrapper');
    const notificationCountBadge = document.getElementById('notification-count');
    const allNotificationsPanel = document.getElementById('all-notifications-panel');

    const updateNotificationCount = async () => {
        try {
            const result = await (await fetch('admin.php?fetch=unread_notification_count')).json();
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
        allNotificationsPanel.innerHTML = '<p style="text-align: center; padding: 2rem;">Loading messages...</p>';
        try {
            const result = await (await fetch('admin.php?fetch=all_notifications')).json();
            if (!result.success) throw new Error(result.message);

            let content = `<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-light); padding-bottom: 1rem; margin-bottom: 1rem;"><h2 style="margin: 0;">All Notifications</h2></div>`;
            if (result.data.length > 0) {
                content += result.data.map(notif => `
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
        } catch (error) {
            allNotificationsPanel.innerHTML = '<p style="text-align: center; color: var(--danger-color);">Could not load notifications.</p>';
        }
    };

    notificationBell.addEventListener('click', async (e) => {
        e.stopPropagation();
        try {
            const formData = new FormData();
            formData.append('action', 'mark_notifications_read');
            formData.append('csrf_token', csrfToken);
            const result = await (await fetch('admin.php', { method: 'POST', body: formData })).json();
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
    userSearch.addEventListener('keyup', () => {
        clearTimeout(searchTimeout);
        const searchTerm = userSearch.value.trim();
        if (searchTerm.length < 2) { userSearchResults.style.display = 'none'; return; }
        searchTimeout = setTimeout(async () => {
            try {
                const result = await (await fetch(`admin.php?fetch=search_users&term=${encodeURIComponent(searchTerm)}`)).json();
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

    document.getElementById('appointment-doctor-filter').addEventListener('change', (e) => fetchAppointments(e.target.value));

    // --- INITIAL LOAD ---
    updateDashboardStats();
    updateNotificationCount();
    fetchDepartments();
    generateReport();
});
