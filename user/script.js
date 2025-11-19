// user/script.js
document.addEventListener('DOMContentLoaded', () => {
    // --- Element Selections ---
    const navTriggers = document.querySelectorAll('.nav-link, .quick-actions-grid .action-box, .action-link, .notification-icon, .dropdown-item');
    const pages = document.querySelectorAll('.main-content .page');
    const headerTitle = document.getElementById('header-title');
    const menuToggle = document.getElementById('menu-toggle');
    const sidebar = document.getElementById('sidebar');
    const profileAvatar = document.getElementById('profile-avatar');
    const profileDropdown = document.getElementById('profile-dropdown');
    const avatarUploadInput = document.getElementById('avatar-upload');
    const profilePageAvatar = document.getElementById('profile-page-avatar');
    const themeToggle = document.getElementById('theme-checkbox');

    // Profile Page Elements
    const personalInfoForm = document.getElementById('personal-info-form');
    const changePasswordForm = document.getElementById('change-password-form');
    const notificationPrefsForm = document.getElementById('notification-prefs-form');
    const newPasswordInput = document.getElementById('new-password');
    const confirmPasswordInput = document.getElementById('confirm-password');
    const strengthMeter = document.getElementById('password-strength-meter');

    // Notification Page Elements
    const notificationList = document.querySelector('.notifications-list');
    const notificationFilter = document.getElementById('notification-filter');
    const markAllReadBtn = document.getElementById('mark-all-read-btn');
    const notificationBadge = document.querySelector('.notification-badge');
    
    // Billing Page Elements
    const billingPage = document.getElementById('billing-page');
    const billingTableBody = document.getElementById('billing-table-body');
    const billingEmptyState = document.getElementById('billing-empty-state');
    const applyBillingFiltersBtn = document.getElementById('billing-apply-filters');
    const billDetailsModal = document.getElementById('bill-details-modal');
    const billCloseModalBtn = document.getElementById('modal-close-btn');

    // --- Page Navigation Logic ---
    let tokenInterval; // To hold the interval ID for the token page
    let clockInterval; // To hold the interval ID for the live clock

    const showPage = (pageId) => {
        pages.forEach(page => page.classList.remove('active'));
        const targetPage = document.getElementById(`${pageId}-page`);
        if (targetPage) targetPage.classList.add('active');
        
        const activeLink = document.querySelector(`.nav-link[data-page="${pageId}"]`);
        if (activeLink && activeLink.querySelector('span')) {
            headerTitle.textContent = activeLink.querySelector('span').textContent;
        } else if (pageId === 'dashboard') {
            headerTitle.textContent = 'Dashboard';
        } else if (pageId === 'billing') { // Set header for billing page
            headerTitle.textContent = 'Bills & Payments';
        } else if (pageId === 'appointments') {
            headerTitle.textContent = 'Appointments';
        } else if (pageId === 'feedback') { // <-- ADDED THIS
            headerTitle.textContent = 'Feedback';
        }
    };

    const updateActiveLink = (pageId) => {
        document.querySelectorAll('.sidebar-nav .nav-link').forEach(nav => nav.classList.remove('active'));
        const sidebarLink = document.querySelector(`.sidebar-nav .nav-link[data-page="${pageId}"]`);
        if (sidebarLink) sidebarLink.classList.add('active');
    };

    const navigateToPage = (pageId) => {
        if (!pageId) return;

        // Clear any running intervals when navigating away from a page
        if (tokenInterval) clearInterval(tokenInterval);
        if (clockInterval) clearInterval(clockInterval);

        updateActiveLink(pageId);
        showPage(pageId);
        if (window.innerWidth <= 992 && sidebar.classList.contains('show')) {
            sidebar.classList.remove('show');
        }
        
        // Fetch data when navigating to specific pages
        if (pageId === 'dashboard') {
            fetchAndRenderDashboardData();
        } else if (pageId === 'notifications') {
            fetchAndRenderNotifications();
        } else if (pageId === 'billing') {
            fetchAndRenderBillingData(); 
        } else if (pageId === 'labs') {
            fetchAndRenderLabResults();
        } else if (pageId === 'prescriptions') {
            fetchAndRenderPrescriptions();
        } else if (pageId === 'appointments') {
            fetchAndRenderAppointments();
        } else if (pageId === 'summaries') { 
            fetchAndRenderDischargeSummaries();
        } else if (pageId === 'records') {
            fetchAndRenderMedicalRecords();
        } else if (pageId === 'token') {
            fetchAndRenderTokens(); // Fetch immediately
            tokenInterval = setInterval(fetchAndRenderTokens, 30000); // Then update every 30 seconds
            updateTime(); // Call once immediately
            clockInterval = setInterval(updateTime, 1000); // Start the clock
        } else if (pageId === 'profile') {
            // Fetch login history when profile page is viewed
            fetchAndRenderLoginActivity();
        } else if (pageId === 'feedback') { // <-- ADDED THIS
            fetchAndRenderFeedback();
        }
    };
    
    // --- Live Clock for Token Card ---
    const updateTime = () => {
        const dateEl = document.getElementById('current-date');
        const timeEl = document.getElementById('current-time');
        if (dateEl && timeEl) {
            const now = new Date();
            dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
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

    // --- Event Listeners (Existing) ---
    navTriggers.forEach(link => {
        link.addEventListener('click', (e) => {
            if (link.getAttribute('href') === '../logout') return;
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

    if (menuToggle) {
        menuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('show');
        });
    }
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 992 && sidebar.classList.contains('show') && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
        if (profileDropdown && profileDropdown.classList.contains('show') && !profileDropdown.contains(e.target) && e.target !== profileAvatar) {
            profileDropdown.classList.remove('show');
        }
    });

    // ===========================================
    // ===       NOTIFICATION PAGE LOGIC       ===
    // ===========================================

    const createNotificationElement = (notification) => {
        const item = document.createElement('div');
        item.className = `notification-item ${notification.is_read == 0 ? 'unread' : ''}`;
        item.dataset.id = notification.id;
        item.dataset.type = notification.type;

        const icons = {
            appointments: 'fa-calendar-check',
            billing: 'fa-file-invoice-dollar',
            labs: 'fa-vials',
            prescriptions: 'fa-pills'
        };

        item.innerHTML = `
            <div class="notification-icon ${notification.type}"><i class="fas ${icons[notification.type] || 'fa-bell'}"></i></div>
            <div class="notification-content">
                <p>${notification.message}</p>
                <small class="timestamp">${new Date(notification.timestamp).toLocaleString()}</small>
            </div>
            <a href="#" class="notification-action" data-page="${notification.type}" title="View Details"><i class="fas fa-arrow-right"></i></a>
        `;
        
        item.addEventListener('click', (e) => {
            e.preventDefault();
            markNotificationAsRead(notification.id);
            navigateToPage(notification.type);
        });

        return item;
    };
    
    const fetchAndRenderNotifications = async () => {
        if (!notificationList) return;
        
        const filter = notificationFilter ? notificationFilter.value : 'all';
        const apiUrl = `api.php?action=get_notifications&filter=${filter}`;

        try {
            notificationList.innerHTML = '<p>Loading notifications...</p>';
            
            const response = await fetch(apiUrl);
            if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
            const data = await response.json();

            notificationList.innerHTML = ''; 

            if (data.success && data.notifications.length > 0) {
                data.notifications.forEach(notification => {
                    const notificationEl = createNotificationElement(notification);
                    notificationList.appendChild(notificationEl);
                });
                updateUnreadCount(data.unread_count);
            } else {
                notificationList.innerHTML = '<p>You have no notifications.</p>';
                updateUnreadCount(0);
            }
        } catch (error) {
            console.error('Error fetching notifications:', error);
            notificationList.innerHTML = '<p>Could not load notifications. Please try again later.</p>';
        }
    };

    const markNotificationAsRead = async (notificationId) => {
        try {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('id', notificationId);
            
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                const item = notificationList.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (item) item.classList.remove('unread');
                fetchAndRenderNotifications();
            }

        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    };

    const updateUnreadCount = (count) => {
        if (!notificationBadge) return;
        if (count > 0) {
            notificationBadge.textContent = count;
            notificationBadge.style.display = 'grid';
        } else {
            notificationBadge.style.display = 'none';
        }
    };
    
    if (notificationFilter) {
        notificationFilter.addEventListener('change', fetchAndRenderNotifications);
    }
    
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async () => {
            try {
                const formData = new FormData();
                formData.append('action', 'mark_all_read');
                await fetch('api.php', { method: 'POST', body: formData });
                fetchAndRenderNotifications();
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        });
    }

    // ===========================================
    // ===   DISCHARGE SUMMARY PAGE LOGIC      ===
    // ===========================================
    const fetchAndRenderDischargeSummaries = async () => {
        const summariesList = document.querySelector('#summaries-page .summaries-list');
        const summariesEmptyState = document.getElementById('summaries-empty-state');
        if (!summariesList) return;
    
        summariesList.innerHTML = '<p>Loading summaries...</p>'; // Loading state
        if (summariesEmptyState) summariesEmptyState.style.display = 'none';
    
        try {
            const response = await fetch('api.php?action=get_discharge_summaries');
            const result = await response.json();
    
            summariesList.innerHTML = ''; // Clear loading state
    
            if (result.success && result.data.length > 0) {
                result.data.forEach(summary => {
                    const card = document.createElement('div');
                    card.className = 'summary-card';
                    card.dataset.summaryId = summary.id; // Crucial for the download link
    
                    const admissionDate = new Date(summary.admission_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    const dischargeDate = new Date(summary.discharge_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    
                    card.innerHTML = `
                        <div class="summary-card-header">
                            <div class="summary-icon"><i class="fas fa-hospital-user"></i></div>
                            <div class="summary-info">
                                <h4>Admitted: <strong>${admissionDate}</strong> - Discharged: <strong>${dischargeDate}</strong></h4>
                                <p>Admitting Physician: <strong>Dr. ${summary.doctor_name || 'N/A'}</strong> (${summary.department_name || 'N/A'})</p>
                            </div>
                        </div>
                        <div class="summary-card-actions">
                            <button class="btn-secondary btn-sm toggle-details-btn"><i class="fas fa-eye"></i> View Details</button>
                            <button class="btn-primary btn-sm download-summary-btn"><i class="fas fa-file-pdf"></i> Download Summary</button>
                        </div>
                        <div class="summary-details">
                            <hr class="section-divider">
                            <h5><i class="fas fa-file-medical-alt"></i> Discharge Summary</h5>
                            <p>${summary.summary_text || 'Not available.'}</p>
                            <hr class="section-divider">
                            <h5><i class="fas fa-notes-medical"></i> Follow-up Instructions</h5>
                            <p>${summary.notes || 'No specific instructions provided.'}</p>
                        </div>
                    `;
                    summariesList.appendChild(card);
                });
            } else {
                if (summariesEmptyState) summariesEmptyState.style.display = 'block';
            }
        } catch (error) {
            console.error('Error fetching discharge summaries:', error);
            summariesList.innerHTML = '<p class="error-text">Could not load summaries. Please try again later.</p>';
        }
    };

    const handleDownloadClick = (e) => {
        if (e.target.closest('.download-summary-btn')) {
            e.preventDefault();
            const summaryId = e.target.closest('.summary-card').dataset.summaryId;
            window.open(`api.php?action=download_discharge_summary&id=${summaryId}`, '_blank');
        }
    };
    
    document.addEventListener('click', handleDownloadClick);
    
    // ===========================================
    // ===       MEDICAL RECORDS PAGE LOGIC    ===
    // ===========================================
    const fetchAndRenderMedicalRecords = async () => {
        const container = document.getElementById('records-timeline-container');
        const loadingState = document.getElementById('records-loading-state');
        const emptyState = document.getElementById('records-empty-state');
        if (!container) return;

        loadingState.style.display = 'block';
        emptyState.style.display = 'none';
        container.innerHTML = '';

        try {
            const response = await fetch('api.php?action=get_medical_records');
            const result = await response.json();
            
            if (result.success && result.data.length > 0) {
                result.data.forEach(record => {
                    const recordEl = createRecordElement(record);
                    container.appendChild(recordEl);
                });
            } else {
                emptyState.style.display = 'block';
            }

        } catch (error) {
            console.error('Error fetching medical records:', error);
            container.innerHTML = '<p class="error-text">Could not load medical records. Please try again later.</p>';
        } finally {
            loadingState.style.display = 'none';
        }
    };

    const createRecordElement = (record) => {
        const item = document.createElement('div');
        item.className = 'timeline-item';

        const icons = {
            admission: { icon: 'fa-hospital', class: 'admission' },
            lab_result: { icon: 'fa-vials', class: 'lab' },
            prescription: { icon: 'fa-pills', class: 'prescription' }
        };

        const typeInfo = icons[record.record_type] || { icon: 'fa-file-alt', class: 'default' };
        const formattedDate = new Date(record.record_date).toLocaleDateString('en-US', {
            year: 'numeric', month: 'long', day: 'numeric'
        });

        item.innerHTML = `
            <div class="timeline-icon ${typeInfo.class}">
                <i class="fas ${typeInfo.icon}"></i>
            </div>
            <div class="timeline-content">
                <div class="timeline-header">
                    <h4 class="timeline-title">${record.title}</h4>
                    <span class="timeline-date">${formattedDate}</span>
                </div>
                <p class="timeline-details">${record.details}</p>
                <span class="status ${record.status.toLowerCase()}">${record.status}</span>
            </div>
        `;
        return item;
    };


    document.addEventListener('click', (e) => {
        const toggleBtn = e.target.closest('.toggle-details-btn');
        if (!toggleBtn) return;

        const summaryCard = toggleBtn.closest('.summary-card');
        if (!summaryCard) return;

        summaryCard.classList.toggle('active');

        if (summaryCard.classList.contains('active')) {
            toggleBtn.innerHTML = `<i class="fas fa-eye-slash"></i> Hide Details`;
        } else {
            toggleBtn.innerHTML = `<i class="fas fa-eye"></i> View Details`;
        }
    });

    // ===========================================
    // ===       PROFILE PAGE LOGIC (UPDATED)    ===
    // ===========================================

    const handleFormSubmit = async (form, action) => {
        const formData = new FormData(form);
        formData.append('action', action);
        const button = form.querySelector('button[type="submit"]');
        const originalButtonHtml = button.innerHTML;
        button.innerHTML = 'Saving...';
        button.disabled = true;

        try {
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (!response.ok) {
                alert(`Error: ${result.message || 'An unknown error occurred.'}`);
                return;
            }

            if (result.success) {
                alert(result.message);
                if (action === 'change_password') {
                    form.reset();
                }
            } else {
                alert(`Update failed: ${result.message}`);
            }
        } catch (error) {
            console.error('Form submission error:', error);
            alert('An unexpected error occurred. Please check the console.');
        } finally {
            button.innerHTML = originalButtonHtml;
            button.disabled = false;
        }
    };
    
    if (avatarUploadInput) {
        avatarUploadInput.addEventListener('change', async () => {
            const file = avatarUploadInput.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'update_profile_picture');
            formData.append('profile_picture', file);

            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    const newImageUrl = `../uploads/profile_pictures/${result.filepath}?t=${new Date().getTime()}`;
                    if (profilePageAvatar) profilePageAvatar.src = newImageUrl;
                    if (profileAvatar) profileAvatar.src = newImageUrl;
                    alert('Profile picture updated successfully!');
                } else {
                    alert(`Upload failed: ${result.message}`);
                }
            } catch (error) {
                console.error('Avatar upload error:', error);
                alert('An error occurred during upload. Please try again.');
            }
        });
    }

    const checkPasswordStrength = (password) => {
        let score = 0;
        if (password.length >= 8) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        if (score >= 4) return 'strong';
        if (score >= 2) return 'medium';
        if (score >= 1) return 'weak';
        return '';
    };
    
    if (newPasswordInput && strengthMeter) {
        const strengthBar = document.createElement('div');
        strengthBar.className = 'strength-bar';
        strengthMeter.appendChild(strengthBar);

        newPasswordInput.addEventListener('input', () => {
            const password = newPasswordInput.value;
            strengthBar.className = `strength-bar ${password.length === 0 ? '' : checkPasswordStrength(password)}`;
            if (confirmPasswordInput.value.length > 0) validatePasswordConfirmation();
        });
    }

    const validatePasswordConfirmation = () => {
        if (!newPasswordInput || !confirmPasswordInput) return;
        if (newPasswordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.style.borderColor = 'var(--status-red)';
            return false;
        } else {
            confirmPasswordInput.style.borderColor = 'var(--border-color)';
            return true;
        }
    };

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validatePasswordConfirmation);
    }
    
    if (personalInfoForm) {
        personalInfoForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleFormSubmit(personalInfoForm, 'update_personal_info');
        });
    }

    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!validatePasswordConfirmation()) {
                alert('Passwords do not match.');
                return;
            }
            handleFormSubmit(changePasswordForm, 'change_password');
        });
    }

    if (notificationPrefsForm) {
        notificationPrefsForm.addEventListener('submit', (e) => {
            e.preventDefault();
            handleFormSubmit(notificationPrefsForm, 'update_notification_prefs');
        });
    }
    
    // ===========================================
    // ===       APPOINTMENTS PAGE LOGIC       ===
    // ===========================================
    const appointmentsPage = document.getElementById('appointments-page');
    const bookNewBtn = document.getElementById('book-new-appointment-btn');
    const bookingModal = document.getElementById('booking-modal');
    const bookingCloseBtn = document.getElementById('booking-modal-close');
    const bookingBackBtn = document.getElementById('booking-back-btn');
    const bookingNextBtn = document.getElementById('booking-next-btn');
    const bookingConfirmBtn = document.getElementById('booking-confirm-btn');
    const bookingSteps = document.querySelectorAll('.booking-step');
    const bookingModalTitle = document.getElementById('booking-modal-title');
    
    let currentStep = 1;
    let bookingData = {}; 

    const stepTitles = [
        "Step 1: Find Your Doctor", "Step 2: Select Date",
        "Step 3: Confirm Details"
    ];

    const goToStep = (step) => {
        bookingSteps.forEach(s => s.style.display = 'none');
        document.getElementById(`booking-step-${step}`).style.display = 'block';
        bookingModalTitle.textContent = stepTitles[step - 1];
        currentStep = step;

        bookingBackBtn.style.display = (step > 1) ? 'inline-flex' : 'none';
        bookingNextBtn.style.display = (step < 3) ? 'inline-flex' : 'none';
        bookingConfirmBtn.style.display = (step === 3) ? 'inline-flex' : 'none';
        
        if (step === 2) {
            document.getElementById('selected-doctor-name').textContent = bookingData.doctorName;
            const today = new Date();
            
            const availableDays = bookingData.doctorSlots?.days_available || [];
            
            renderCalendar(today.getFullYear(), today.getMonth(), availableDays);

        } else if (step === 3) {
            document.getElementById('confirm-doctor').textContent = bookingData.doctorName;
            document.getElementById('confirm-date').textContent = bookingData.date;
        }
        updateNextButtonState();
    };

    const updateNextButtonState = () => {
        let enabled = false;
        switch(currentStep) {
            case 1: enabled = !!bookingData.doctorId; break;
            case 2: enabled = !!bookingData.date; break;
        }
        bookingNextBtn.disabled = !enabled;
    };


    if (appointmentsPage) {
        const tabs = appointmentsPage.querySelectorAll('.tab-link');
        const tabContents = appointmentsPage.querySelectorAll('.tab-content');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                const targetContent = document.getElementById(`${tab.dataset.tab}-appointments`);
                tabContents.forEach(c => c.style.display = 'none');
                if (targetContent) targetContent.style.display = 'block';
            });
        });
    }

    if (bookNewBtn) {
        bookNewBtn.addEventListener('click', () => {
            bookingData = {}; 
            goToStep(1);
            fetchAndPopulateSpecialties();
            fetchAndRenderDoctors();
            bookingModal.classList.add('show');
        });
    }
    if (bookingCloseBtn) bookingCloseBtn.addEventListener('click', () => bookingModal.classList.remove('show'));
    if (bookingModal) bookingModal.addEventListener('click', (e) => { if(e.target === bookingModal) bookingModal.classList.remove('show') });
    
    if (bookingNextBtn) bookingNextBtn.addEventListener('click', () => { if (currentStep < 3) goToStep(currentStep + 1); });
    if (bookingBackBtn) bookingBackBtn.addEventListener('click', () => { if (currentStep > 1) goToStep(currentStep - 1); });
    
    // =======================================================
    // === NEWLY ADDED/MODIFIED APPOINTMENT FUNCTIONS      ===
    // =======================================================

    if (bookingConfirmBtn) {
        bookingConfirmBtn.addEventListener('click', async () => {
            bookingConfirmBtn.disabled = true;
            bookingConfirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Confirming...';
            
            try {
                const formData = new FormData();
                formData.append('action', 'book_appointment');
                formData.append('doctorId', bookingData.doctorId);
                formData.append('date', bookingData.date);
    
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
    
                const result = await response.json();
    
                if (result.success) {
                    alert(result.message);
                    bookingModal.classList.remove('show');
                    fetchAndRenderAppointments(); // Refresh the list
                } else {
                    throw new Error(result.message || 'An unknown error occurred.');
                }
            } catch (error) {
                console.error('Booking failed:', error);
                alert(`Booking failed: ${error.message}`);
            } finally {
                bookingConfirmBtn.disabled = false;
                bookingConfirmBtn.innerHTML = '<i class="fas fa-check-circle"></i> Confirm Appointment';
            }
        });
    }
    
    const fetchAndRenderAppointments = async () => {
        const upcomingBody = document.getElementById('upcoming-appointments-body');
        const pastBody = document.getElementById('past-appointments-body');
        const upcomingEmpty = document.getElementById('upcoming-empty-state');
        const pastEmpty = document.getElementById('past-empty-state');
    
        try {
            upcomingBody.innerHTML = '<tr><td colspan="5">Loading...</td></tr>';
            pastBody.innerHTML = '<tr><td colspan="4">Loading...</td></tr>';
    
            const response = await fetch('api.php?action=get_appointments');
            const result = await response.json();
    
            if (!result.success) throw new Error(result.message);
    
            const { upcoming, past } = result.data;
    
            // Render Upcoming Appointments
            if (upcoming.length > 0) {
                if(upcomingEmpty) upcomingEmpty.style.display = 'none';
                upcomingBody.innerHTML = upcoming.map(app => {
                    const appDate = new Date(app.appointment_date);
                    const formattedDate = appDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                    const formattedTime = appDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                    return `
                        <tr>
                            <td data-label="Doctor"><strong>${app.doctor_name}</strong><br><small>${app.specialty}</small></td>
                            <td data-label="Date & Time">${formattedDate}, ${formattedTime}</td>
                            <td data-label="Token No.">#${String(app.token_number).padStart(2, '0')}</td>
                            <td data-label="Status"><span class="status upcoming">${app.status}</span></td>
                            <td data-label="Actions"><button class="btn-danger btn-sm cancel-appointment-btn" data-id="${app.id}">Cancel</button></td>
                        </tr>
                    `;
                }).join('');
            } else {
                if(upcomingEmpty) upcomingEmpty.style.display = 'block';
                upcomingBody.innerHTML = '';
            }
    
            // Render Past Appointments
            if (past.length > 0) {
                if(pastEmpty) pastEmpty.style.display = 'none';
                pastBody.innerHTML = past.map(app => {
                     const appDate = new Date(app.appointment_date);
                    const formattedDate = appDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                    const formattedTime = appDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                    return `
                        <tr>
                            <td data-label="Doctor"><strong>${app.doctor_name}</strong><br><small>${app.specialty}</small></td>
                            <td data-label="Date & Time">${formattedDate}, ${formattedTime}</td>
                            <td data-label="Token No.">#${String(app.token_number).padStart(2, '0')}</td>
                            <td data-label="Status"><span class="status ${app.status.toLowerCase()}">${app.status}</span></td>
                        </tr>
                    `;
                }).join('');
            } else {
                if(pastEmpty) pastEmpty.style.display = 'block';
                pastBody.innerHTML = '';
            }
        } catch (error) {
            console.error('Error fetching appointments:', error);
            upcomingBody.innerHTML = '<tr><td colspan="5" class="error-text">Could not load appointments.</td></tr>';
            pastBody.innerHTML = '<tr><td colspan="4" class="error-text">Could not load past appointments.</td></tr>';
        }
    };

    const fetchAndPopulateSpecialties = async () => {
        const specialtySelect = document.getElementById('doctor-search-specialty');
        
        if (specialtySelect.options.length > 1) {
            return;
        }

        try {
            const response = await fetch('api.php?action=get_specialties');
            const result = await response.json();

            if (result.success && result.data) {
                result.data.forEach(specialty => {
                    const option = document.createElement('option');
                    option.value = specialty.name;
                    option.textContent = specialty.name;
                    specialtySelect.appendChild(option);
                });
            }
        } catch (error) {
            console.error('Error fetching specialties:', error);
        }
    };

    const createAvailabilityHtml = (slotsJson) => {
        if (!slotsJson) {
            return '<div class="availability-info not-available"><small>Availability not specified</small></div>';
        }

        try {
            const slots = JSON.parse(slotsJson);
            
            if (!slots.general_availability || !slots.days_available) {
                 return '<div class="availability-info not-available"><small>Availability not specified</small></div>';
            }

            const time = slots.general_availability;
            const availableDays = slots.days_available.map(day => day.toLowerCase());
            const allDays = [
                { short: 'S', long: 'sunday' },
                { short: 'M', long: 'monday' },
                { short: 'T', long: 'tuesday' },
                { short: 'W', long: 'wednesday' },
                { short: 'T', long: 'thursday' },
                { short: 'F', long: 'friday' },
                { short: 'S', long: 'saturday' }
            ];

            const daysHtml = allDays.map(day => {
                const isActive = availableDays.includes(day.long);
                return `<span class="day-dot ${isActive ? 'active' : ''}" title="${day.long.charAt(0).toUpperCase() + day.long.slice(1)}">${day.short}</span>`;
            }).join('');

            return `
                <div class="availability-info">
                    <strong class="availability-time"><i class="fas fa-clock"></i> ${time}</strong>
                    <div class="availability-days">${daysHtml}</div>
                </div>
            `;
        } catch (e) {
            console.error("Error parsing doctor slots JSON:", e, slotsJson);
            return '<div class="availability-info not-available"><small>Availability not specified</small></div>';
        }
    };

    const fetchAndRenderDoctors = async () => {
        const doctorListContainer = document.getElementById('doctor-list');
        const nameSearch = document.getElementById('doctor-search-name').value;
        const specialtyFilter = document.getElementById('doctor-search-specialty')?.value || '';


        doctorListContainer.innerHTML = '<p>Loading doctors...</p>';
        
        try {
            const params = new URLSearchParams({
                action: 'get_doctors',
                name_search: nameSearch,
                specialty: specialtyFilter
            });

            const response = await fetch(`api.php?${params.toString()}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
    
            if (result.data.length > 0) {
                doctorListContainer.innerHTML = result.data.map(doc => {
                    const availabilityHtml = createAvailabilityHtml(doc.slots);
                    return `
                        <div class="doctor-card" data-doctor-id="${doc.id}" data-doctor-name="${doc.name}" data-doctor-slots='${doc.slots || '{}'}'>
                            <div class="doctor-info-left">
                                <img src="../uploads/profile_pictures/${doc.profile_picture || 'default.png'}" alt="${doc.name}">
                                <div class="doctor-info-basic">
                                    <strong>${doc.name}</strong><br>
                                    <small>${doc.specialty}</small>
                                </div>
                            </div>
                            ${availabilityHtml} 
                        </div>
                    `;
                }).join('');
            } else {
                doctorListContainer.innerHTML = '<p>No doctors found matching your search.</p>';
            }
        } catch (error) {
            console.error('Error fetching doctors:', error);
            doctorListContainer.innerHTML = '<p class="error-text">Could not load doctors.</p>';
        }
    };

    const doctorListContainer = document.getElementById('doctor-list');
    if (doctorListContainer) {
        doctorListContainer.addEventListener('click', (e) => {
            const card = e.target.closest('.doctor-card');
            if (!card) return;
            
            doctorListContainer.querySelectorAll('.doctor-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');

            bookingData.doctorId = card.dataset.doctorId;
            bookingData.doctorName = card.dataset.doctorName;
            bookingData.doctorSlots = JSON.parse(card.dataset.doctorSlots);
            updateNextButtonState();
        });
    }

    const doctorSearchInput = document.getElementById('doctor-search-name');
    const specialtyFilterInput = document.getElementById('doctor-search-specialty');

    if(doctorSearchInput) {
        doctorSearchInput.addEventListener('keyup', fetchAndRenderDoctors);
    }
    if(specialtyFilterInput) {
        specialtyFilterInput.addEventListener('change', fetchAndRenderDoctors);
    }
    
    const renderCalendar = (year, month, availableDays) => {
        const datepicker = document.getElementById('datepicker');
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let html = `
            <div class="calendar">
                <div class="calendar-header">
                    <button id="prev-month-btn">&lt;</button>
                    <span id="month-year">${monthNames[month]} ${year}</span>
                    <button id="next-month-btn">&gt;</button>
                </div>
                <div class="calendar-grid">
                    <div class="calendar-day-name">Sun</div>
                    <div class="calendar-day-name">Mon</div>
                    <div class="calendar-day-name">Tue</div>
                    <div class="calendar-day-name">Wed</div>
                    <div class="calendar-day-name">Thu</div>
                    <div class="calendar-day-name">Fri</div>
                    <div class="calendar-day-name">Sat</div>
        `;

        for (let i = 0; i < firstDay; i++) {
            html += `<div></div>`;
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const currentDate = new Date(year, month, day);
            const dateString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            
            const dayName = currentDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
            const isAvailable = availableDays.includes(dayName);
            
            let classes = 'calendar-day';
            
            if (currentDate < today) {
                classes += ' inactive';
            } else if (isAvailable) {
                classes += ' active';
            } else {
                classes += ' inactive';
            }

            if (dateString === bookingData.date) {
                classes += ' selected';
            }
            html += `<div class="${classes}" data-date="${dateString}">${day}</div>`;
        }

        html += `</div></div>`;
        datepicker.innerHTML = html;

        document.getElementById('prev-month-btn').addEventListener('click', () => {
            const newDate = new Date(year, month - 1, 1);
            renderCalendar(newDate.getFullYear(), newDate.getMonth(), availableDays);
        });
        document.getElementById('next-month-btn').addEventListener('click', () => {
            const newDate = new Date(year, month + 1, 1);
            renderCalendar(newDate.getFullYear(), newDate.getMonth(), availableDays);
        });
    };

    const bookingStep2 = document.getElementById('booking-step-2');
    if (bookingStep2) {
        bookingStep2.addEventListener('click', e => {
            const dateEl = e.target.closest('.calendar-day.active');

            if (dateEl) {
                bookingData.date = dateEl.dataset.date;
                
                delete bookingData.slot; 
                delete bookingData.token;
                
                const [year, month] = bookingData.date.split('-').map(Number);
                const availableDays = bookingData.doctorSlots?.days_available || [];
                renderCalendar(year, month - 1, availableDays);
                
                updateNextButtonState();
            }
        });
    }
    
    document.addEventListener('click', async (e) => {
        if (e.target.classList.contains('cancel-appointment-btn')) {
            const appointmentId = e.target.dataset.id;
            if (confirm('Are you sure you want to cancel this appointment?')) {
                try {
                    const formData = new FormData();
                    formData.append('action', 'cancel_appointment');
                    formData.append('appointment_id', appointmentId);
    
                    const response = await fetch('api.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
    
                    if (result.success) {
                        alert(result.message);
                        fetchAndRenderAppointments();
                    } else {
                        throw new Error(result.message);
                    }
                } catch (error) {
                    console.error('Cancellation error:', error);
                    alert(`Error: ${error.message}`);
                }
            }
        }
    });

    // ===========================================
    // ===       PRESCRIPTIONS PAGE LOGIC      ===
    // ===========================================
    const prescriptionsPage = document.getElementById('prescriptions-page');
    const prescriptionsList = document.getElementById('prescriptions-list');
    const prescriptionsEmptyState = document.getElementById('prescriptions-empty-state');
    const prescriptionsLoadingState = document.getElementById('prescriptions-loading-state');
    const applyPrescriptionFiltersBtn = document.getElementById('prescription-apply-filters');

    const createPrescriptionCard = (prescription) => {
        const card = document.createElement('div');
        card.className = 'summary-card prescription-card';
        card.dataset.prescriptionId = prescription.id;

        let itemsHtml = '';
        if (prescription.items && prescription.items.length > 0) {
            prescription.items.forEach(item => {
                itemsHtml += `
                    <tr>
                        <td><strong>${item.medicine_name}</strong></td>
                        <td>${item.dosage}</td>
                        <td>${item.frequency}</td>
                        <td>${item.quantity_prescribed}</td>
                    </tr>
                `;
            });
        } else {
            itemsHtml = '<tr><td colspan="4">No medicine items found.</td></tr>';
        }

        const status = prescription.status || 'unknown';
        const statusClass = status.toLowerCase();
        const formattedStatus = status.charAt(0).toUpperCase() + status.slice(1);

        card.innerHTML = `
            <div class="summary-card-header">
                <div class="summary-icon"><i class="fas fa-pills"></i></div>
                <div class="summary-info">
                    <h4>Prescribed on: <strong>${new Date(prescription.prescription_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</strong></h4>
                    <p>Prescribing Physician: <strong>${prescription.doctor_name}</strong> | Status: <span class="status ${statusClass}">${formattedStatus}</span></p>
                </div>
            </div>
            <div class="summary-card-actions">
                <button class="btn-secondary btn-sm toggle-details-btn"><i class="fas fa-eye"></i> View Details</button>
                <a href="api.php?action=download_prescription&id=${prescription.id}" class="btn-primary btn-sm" target="_blank"><i class="fas fa-file-pdf"></i> Download PDF</a>
            </div>
            <div class="summary-details">
                <hr class="section-divider">
                <h5><i class="fas fa-notes-medical"></i> Doctor's Notes</h5>
                <p>${prescription.notes || 'No specific notes provided.'}</p>
                
                <h5><i class="fas fa-prescription-bottle-alt"></i> Medications</h5>
                <div class="table-responsive">
                    <table class="data-table compact">
                    <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Quantity</th>
                            </tr>
                    </thead>
                    <tbody>
                            ${itemsHtml}
                    </tbody>
                    </table>
                </div>
            </div>
        `;
        return card;
    };

    const fetchAndRenderPrescriptions = async () => {
        if (!prescriptionsPage) return;

        prescriptionsLoadingState.style.display = 'block';
        prescriptionsEmptyState.style.display = 'none';
        prescriptionsList.innerHTML = '';

        try {
            const dateFilter = document.getElementById('prescription-filter-date').value;
            const statusFilter = document.getElementById('prescription-filter-status').value;
            
            const params = new URLSearchParams({
                action: 'get_prescriptions',
                date: dateFilter,
                status: statusFilter
            });
            
            const apiUrl = `api.php?${params.toString()}`;
            const response = await fetch(apiUrl);
            
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }
            
            const result = await response.json();

            if (result.success && result.data && result.data.length > 0) {
                result.data.forEach(prescription => {
                    const card = createPrescriptionCard(prescription);
                    prescriptionsList.appendChild(card);
                });
            } else {
                prescriptionsEmptyState.style.display = 'block';
            }
            
        } catch (error) {
            console.error("Error fetching prescriptions:", error);
            prescriptionsList.innerHTML = `<p style="text-align:center; color: var(--status-red);">Could not load prescriptions. Please try again later.</p>`;
        } finally {
            prescriptionsLoadingState.style.display = 'none';
        }
    };

    if (prescriptionsPage && applyPrescriptionFiltersBtn) {
        applyPrescriptionFiltersBtn.addEventListener('click', fetchAndRenderPrescriptions);
    }
    
    // ===========================================
    // ===      BILLS & PAYMENTS PAGE LOGIC      ===
    // ===========================================

    const fetchAndRenderBillingData = async () => {
        if (!billingPage) return;
    
        billingTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">Loading billing history...</td></tr>`;
        billingEmptyState.style.display = 'none';
    
        try {
            const statusFilter = document.getElementById('billing-filter-status').value;
            const dateFilter = document.getElementById('billing-filter-date').value;
    
            const params = new URLSearchParams({ action: 'get_billing_data' });
            if (statusFilter !== 'all') params.append('status', statusFilter);
            if (dateFilter) params.append('date', dateFilter);
            
            const response = await fetch(`api.php?${params.toString()}`);
            if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
            const result = await response.json();
    
            if (!result.success) throw new Error(result.message);
            
            const { summary, history } = result.data;

            document.getElementById('outstanding-balance').textContent = `₹${parseFloat(summary.outstanding_balance).toFixed(2)}`;
            document.getElementById('last-payment-amount').innerHTML = `₹${parseFloat(summary.last_payment_amount).toFixed(2)} on <span id="last-payment-date">${summary.last_payment_date}</span>`;

            if (history.length > 0) {
                billingEmptyState.style.display = 'none';
                billingTableBody.innerHTML = history.map(bill => {
                    const billDate = new Date(bill.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    const displayStatus = bill.status === 'pending' ? 'due' : bill.status;
                    const statusClass = displayStatus.toLowerCase();
                    const statusText = statusClass.charAt(0).toUpperCase() + statusClass.slice(1);
                    
                    const actionsHtml = statusClass === 'due'
                        ? `<button class="btn-primary btn-sm view-bill-details-btn" data-bill-id="${bill.id}">Pay Now</button>`
                        : `<button class="btn-secondary btn-sm view-bill-details-btn" data-bill-id="${bill.id}">View Details</button>
                           <a href="api.php?action=download_receipt&id=${bill.id}" class="action-link" style="margin-left: 10px;" target="_blank"><i class="fas fa-download"></i> Receipt</a>`;
    
                    return `
                        <tr>
                            <td data-label="Date">${billDate}</td>
                            <td data-label="Bill ID">TXN${bill.id}</td>
                            <td data-label="Description">${bill.description}</td>
                            <td data-label="Amount"><strong>₹${parseFloat(bill.amount).toFixed(2)}</strong></td>
                            <td data-label="Status"><span class="status ${statusClass}">${statusText}</span></td>
                            <td data-label="Actions">${actionsHtml}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                billingTableBody.innerHTML = '';
                billingEmptyState.style.display = 'block';
            }
    
        } catch (error) {
            console.error("Error fetching billing data:", error);
            const specificError = error.message.replace('Error:', '').trim(); 
            billingTableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--status-red);"><strong>Error:</strong> ${specificError}</td></tr>`;
        }
    };

    if (billingPage && applyBillingFiltersBtn) {
        applyBillingFiltersBtn.addEventListener('click', fetchAndRenderBillingData);
    }
    
    // --- This is the new function to fetch data and show the modal ---
    const showBillDetailsModal = async (billId) => {
        if (!billDetailsModal) return;

        try {
            const response = await fetch(`api.php?action=get_bill_details&bill_id=${billId}`);
            if (!response.ok) throw new Error(`HTTP Error: ${response.status}`);
            
            const result = await response.json();
            if (!result.success) throw new Error(result.message);

            const data = result.data;

            // Populate modal fields
            document.getElementById('modal-bill-id').textContent = `TXN${data.id}`;
            // *** ADDED THIS LINE ***
            billDetailsModal.querySelector('.modal-content').dataset.billId = data.id; 
            document.getElementById('modal-patient-name').textContent = data.patient_name;
            document.getElementById('modal-bill-date').textContent = new Date(data.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

            // Set status
            const statusEl = document.getElementById('modal-bill-status');
            const displayStatus = data.status === 'pending' ? 'due' : data.status;
            statusEl.className = `status ${displayStatus.toLowerCase()}`;
            statusEl.textContent = displayStatus.charAt(0).toUpperCase() + displayStatus.slice(1);

            // Populate itemized charges
            const itemizedBody = document.getElementById('modal-itemized-charges');
            itemizedBody.innerHTML = `
                <tr>
                    <td>${data.description}</td>
                    <td>₹${parseFloat(data.amount).toFixed(2)}</td>
                </tr>
            `;

            // Populate total
            document.getElementById('modal-total-amount').textContent = `₹${parseFloat(data.amount).toFixed(2)}`;

            // Show/Hide payment section
            const paymentSection = document.getElementById('modal-payment-section');
            if (data.status === 'paid') {
                paymentSection.style.display = 'none';
            } else {
                paymentSection.style.display = 'block';
            }

            // Show the modal
            billDetailsModal.classList.add('show');

        } catch (error) {
            console.error("Error fetching bill details:", error);
            alert(`Could not load bill details: ${error.message}`);
        }
    };

    if (billingPage) {
        billingPage.addEventListener('click', (e) => {
            const targetButton = e.target.closest('.view-bill-details-btn');
            if (targetButton) {
                showBillDetailsModal(targetButton.dataset.billId);
            }
        });
    }
    
    if (billCloseModalBtn) billCloseModalBtn.addEventListener('click', () => billDetailsModal.classList.remove('show'));
    if (billDetailsModal) billDetailsModal.addEventListener('click', (e) => { if (e.target === billDetailsModal) billDetailsModal.classList.remove('show') });
    
    // ===========================================
    // ===  NEW: PAYMENT PROCESSING LOGIC      ===
    // ===========================================

    const processPayment = async (billId, paymentMode) => {
        if (!billId) {
            alert('Error: Bill ID is missing.');
            return;
        }

        // Show loading state on buttons
        const cardBtn = document.getElementById('pay-with-card-btn');
        const upiBtn = document.getElementById('pay-with-upi-btn');
        cardBtn.disabled = true;
        upiBtn.disabled = true;
        
        // Change the button that was clicked to "Processing"
        const clickedBtn = (paymentMode === 'card') ? cardBtn : upiBtn;
        clickedBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';

        try {
            const formData = new FormData();
            formData.append('action', 'process_payment');
            formData.append('bill_id', billId);
            formData.append('payment_mode', paymentMode);

            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();

            if (result.success) {
                alert(result.message);
                billDetailsModal.classList.remove('show'); // Close modal
                fetchAndRenderBillingData(); // Refresh the billing list
            } else {
                throw new Error(result.message || 'An unknown error occurred.');
            }

        } catch (error) {
            console.error('Payment Error:', error);
            alert(`Payment failed: ${error.message}`);
        } finally {
            // Restore buttons
            cardBtn.disabled = false;
            upiBtn.disabled = false;
            cardBtn.innerHTML = '<i class="fas fa-credit-card"></i> Pay with Card';
            upiBtn.innerHTML = '<i class="fas fa-qrcode"></i> Pay with UPI';
        }
    };

    // Add listeners to the new payment buttons
    document.getElementById('pay-with-card-btn')?.addEventListener('click', () => {
        const billId = billDetailsModal.querySelector('.modal-content').dataset.billId;
        processPayment(billId, 'card');
    });

    document.getElementById('pay-with-upi-btn')?.addEventListener('click', () => {
        const billId = billDetailsModal.querySelector('.modal-content').dataset.billId;
        processPayment(billId, 'upi');
    });
    
    // ===========================================
    // ===        LAB RESULTS PAGE LOGIC       ===
    // ===========================================
    const labsPage = document.getElementById('labs-page');
    const labModal = document.getElementById('lab-details-modal');
    const closeLabModalBtn = document.getElementById('modal-lab-close-btn');
    const labTableBody = document.getElementById('lab-results-table-body');
    const labEmptyState = document.getElementById('lab-results-empty-state');
    const labLoadingState = document.getElementById('lab-results-loading-state');
    const labApplyFiltersBtn = document.getElementById('lab-apply-filters');

    if (closeLabModalBtn) closeLabModalBtn.addEventListener('click', () => labModal.classList.remove('show'));
    if (labModal) labModal.addEventListener('click', (e) => { if (e.target === labModal) labModal.classList.remove('show') });

    const renderLabResults = (results) => {
        labTableBody.innerHTML = '';
        if (results.length > 0) {
            labEmptyState.style.display = 'none';
            results.forEach(result => {
                const row = document.createElement('tr');
                const statusClass = result.status === 'completed' ? 'ready' : (result.status === 'pending' ? 'pending' : 'processing');
                
                const reportButtonHtml = result.status === 'completed' 
                    ? `<a href="api.php?action=download_lab_report&id=${result.id}" target="_blank" class="btn-secondary btn-sm" style="margin-left: 5px;"><i class="fas fa-file-pdf"></i> Report</a>` 
                    : '';

                row.innerHTML = `
                    <td data-label="Test Date">${new Date(result.test_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</td>
                    <td data-label="Test Name"><strong>${result.test_name}</strong></td>
                    <td data-label="Ordering Doctor">${result.doctor_name || 'N/A'}</td>
                    <td data-label="Status"><span class="status ${statusClass}">${result.status.charAt(0).toUpperCase() + result.status.slice(1)}</span></td>
                    <td data-label="Actions">
                        <button class="btn-primary btn-sm view-lab-details-btn" data-result-id="${result.id}" ${result.status !== 'completed' ? 'disabled' : ''}>View Details</button>
                        ${reportButtonHtml}
                    </td>`;
                labTableBody.appendChild(row);
            });
        } else {
            labEmptyState.style.display = 'block';
        }
    };
    
    const fetchAndRenderLabResults = async () => {
        if (!labsPage) return;
        labLoadingState.style.display = 'block';
        labEmptyState.style.display = 'none';
        labTableBody.innerHTML = '';
        
        try {
            const searchFilter = document.getElementById('lab-search-input').value;
            const dateFilter = document.getElementById('lab-filter-date').value;
    
            const params = new URLSearchParams();
            if (searchFilter) params.append('search', searchFilter);
            if (dateFilter) params.append('date', dateFilter);
            
            const apiUrl = `api.php?action=get_lab_results&${params.toString()}`;
    
            const response = await fetch(apiUrl);
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }
            const result = await response.json();
    
            if (result.success && result.data) {
                labsPage.dataset.results = JSON.stringify(result.data);
                renderLabResults(result.data); 
            } else {
                labEmptyState.style.display = 'block';
                labTableBody.innerHTML = '';
            }
    
        } catch (error) {
            console.error("Error fetching lab results:", error);
            labTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--status-red);">Could not load lab results. Please try again later.</td></tr>`;
        } finally {
            labLoadingState.style.display = 'none';
        }
    };

    const showLabDetailsModal = (resultId) => {
        const results = JSON.parse(labsPage.dataset.results || '[]');
        const data = results.find(r => r.id === parseInt(resultId));
        if (!data || !labModal) return;

        document.getElementById('modal-lab-test-name').textContent = data.test_name;
        document.getElementById('modal-lab-date').textContent = new Date(data.test_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('modal-lab-doctor').textContent = `Dr. ${data.doctor_name || 'N/A'}`;
        
        const detailsContainer = document.getElementById('modal-lab-result-details');
        detailsContainer.innerHTML = '';

        try {
            const resultData = JSON.parse(data.result_details);
            
            if (resultData.findings && resultData.findings.length > 0) {
                let tableHtml = `
                    <table class="data-table compact">
                        <thead>
                            <tr>
                                <th>Test Description</th>
                                <th>Results</th>
                                <th>Units</th>
                                <th>Biological Reference Value</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                resultData.findings.forEach(finding => {
                    const parameter = finding.parameter || 'N/A';
                    const result = finding.result || 'N/A';
                    const units = finding.units || ''; 
                    const range = finding.range || 'N/A';

                    tableHtml += `
                        <tr>
                            <td>${parameter}</td>
                            <td><strong>${result}</strong></td>
                            <td>${units}</td>
                            <td>${range}</td>
                        </tr>
                    `;
                });

                tableHtml += `</tbody></table>`;
                detailsContainer.innerHTML += tableHtml;
            }

            if (resultData.summary) {
                detailsContainer.innerHTML += `
                    <h5 style="margin-top: 1.5rem; margin-bottom: 0.5rem; font-weight: 600;">Summary</h5>
                    <p>${resultData.summary}</p>
                `;
            }
            
            if (detailsContainer.innerHTML === '') {
                 detailsContainer.textContent = 'Result details are not available in a structured format.';
            }

        } catch (error) {
            console.error("Failed to parse lab result details:", error);
            detailsContainer.textContent = data.result_details || 'Details are not available.';
        }

        const downloadSection = document.getElementById('modal-lab-download-section');
        const downloadBtn = document.getElementById('modal-lab-download-btn');
        if (data.status === 'completed') {
            downloadBtn.href = `api.php?action=download_lab_report&id=${data.id}`;
            downloadSection.style.display = 'block';
        } else {
            downloadSection.style.display = 'none';
        }
        
        labModal.classList.add('show');
    };

    if (labsPage) {
        labsPage.addEventListener('click', (e) => {
            const targetButton = e.target.closest('.view-lab-details-btn');
            if (targetButton) {
                showLabDetailsModal(targetButton.dataset.resultId);
            }
        });
        labApplyFiltersBtn.addEventListener('click', fetchAndRenderLabResults);
    }
    
    // ===========================================
    // ===         LIVE TOKEN PAGE LOGIC       ===
    // ===========================================
    
    const tokenListPage = document.getElementById('token-page');
    const tokenContainer = document.getElementById('token-list-container');
    const tokenLoadingState = document.getElementById('token-loading-state');
    const tokenEmptyState = document.getElementById('token-empty-state');

    const createTokenCard = (tokenData) => {
        const card = document.createElement('div');
        card.className = 'live-token-card';

        const yourToken = parseInt(tokenData.your_token, 10);
        const currentToken = parseInt(tokenData.current_token, 10);
        const totalPatients = parseInt(tokenData.total_patients, 10);
        const avgTimePerPatient = 10;

        let waitMessage = "Calculating...";
        const tokensAhead = Math.max(0, yourToken - currentToken - 1);
        
        let statusMessage = "Please wait for your turn.";
        if (yourToken === currentToken + 1) {
            statusMessage = "You're next! Please be ready near the doctor's room.";
        } else if (yourToken === currentToken) {
            statusMessage = "It's your turn now! Please proceed to the doctor's room.";
        } else if (yourToken < currentToken) {
            statusMessage = "Your turn has passed. Please contact reception.";
        }

        if (yourToken <= currentToken) {
            waitMessage = "N/A";
        } else if (tokenData.consultation_start_time) {
            const startTime = new Date(tokenData.consultation_start_time);
            const now = new Date();
            const timeElapsedMins = (now.getTime() - startTime.getTime()) / 60000;

            let remainingTimeOnCurrent = avgTimePerPatient - timeElapsedMins;

            if (remainingTimeOnCurrent > 0) {
                const totalWait = (tokensAhead * avgTimePerPatient) + remainingTimeOnCurrent;
                waitMessage = `~${Math.ceil(totalWait)} min`;
            } else {
                const totalWait = (tokensAhead * avgTimePerPatient);
                waitMessage = `~${Math.ceil(totalWait)} min (Delayed)`;
            }
        } else {
            const staticWait = Math.max(0, yourToken - currentToken) * avgTimePerPatient;
            waitMessage = `~${staticWait} min`;
        }

        const progressPercent = totalPatients > 0 ? (currentToken / totalPatients) * 100 : 0;

        card.innerHTML = `
            <div class="token-card-header-new">
                <div class="doctor-details">
                    <h3>${tokenData.doctor_name}</h3>
                    <p>${tokenData.specialty}</p>
                </div>
                <div class="location-details">
                    <span><i class="fas fa-building"></i> ${tokenData.office_floor}</span>
                    <span><i class="fas fa-door-open"></i> Room ${tokenData.office_room_number}</span>
                </div>
            </div>
            
            <div class="token-main-display">
                <div class="token-progress-circle">
                    <svg viewBox="0 0 36 36">
                        <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="circle" stroke-dasharray="${progressPercent}, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                    </svg>
                    <div class="token-number-large">
                        <small>Serving Now</small>
                        <span>#${String(currentToken).padStart(2, '0')}</span>
                    </div>
                </div>
                <div class="your-token-display">
                    <small>Your Token</small>
                    <span>#${String(yourToken).padStart(2, '0')}</span>
                    <p class="status-message">${statusMessage}</p>
                </div>
            </div>

            <div class="token-stats-grid">
                <div class="stat-item">
                    <i class="fas fa-users"></i>
                    <p>${tokenData.total_patients}</p>
                    <small>Total Patients</small>
                </div>
                <div class="stat-item">
                    <i class="fas fa-user-clock"></i>
                    <p>${tokenData.patients_left}</p>
                    <small>Patients Left</small>
                </div>
                <div class="stat-item">
                    <i class="fas fa-hourglass-half"></i>
                    <p>${waitMessage}</p> <small>Est. Your Turn</small>
                </div>
            </div>

            <div class="token-card-footer-new">
                <span id="current-date"></span>
                <span id="current-time"></span>
            </div>
        `;
        return card;
    };


    const fetchAndRenderTokens = async () => {
        if (!tokenListPage.classList.contains('active')) return; 

        tokenLoadingState.style.display = 'block';
        tokenEmptyState.style.display = 'none';
        
        try {
            const response = await fetch('api.php?action=get_live_tokens');
            const data = await response.json();
            
            tokenContainer.innerHTML = ''; 

            if (data.success && data.tokens.length > 0) {
                data.tokens.forEach(token => {
                    const card = createTokenCard(token);
                    tokenContainer.appendChild(card);
                });
            } else {
                tokenEmptyState.style.display = 'block';
            }

        } catch (error) {
            console.error("Error fetching live tokens:", error);
            tokenContainer.innerHTML = '<p style="text-align:center; color: var(--status-red);">Could not load token status. Please try again later.</p>';
        } finally {
            tokenLoadingState.style.display = 'none';
        }
    };
    
    // ===========================================
    // ===       NEW: DASHBOARD PAGE LOGIC       ===
    // ===========================================
    const fetchAndRenderDashboardData = async () => {
        const appointmentsList = document.getElementById('dashboard-appointments-list');
        const appointmentsEmpty = document.getElementById('dashboard-appointments-empty');
        const activityFeed = document.getElementById('dashboard-activity-feed');
        const activityEmpty = document.getElementById('dashboard-activity-empty');
        const tokenWidget = document.getElementById('dashboard-token-widget');
        const tokenEmpty = document.getElementById('dashboard-token-empty');
        const welcomeSubtext = document.getElementById('welcome-subtext');

        try {
            const response = await fetch('api.php?action=get_dashboard_data');
            if (!response.ok) {
                throw new Error(`HTTP Error: ${response.status}`);
            }
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Failed to fetch dashboard data.');
            }

            const data = result.data;
            
            appointmentsList.innerHTML = ''; 
            if (data.appointments && data.appointments.length > 0) {
                appointmentsEmpty.style.display = 'none';
                data.appointments.forEach(app => {
                    const date = new Date(app.appointment_date);
                    const time = date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
                    const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });

                    const card = document.createElement('div');
                    card.className = 'appointment-card';
                    card.dataset.page = 'appointments'; 
                    card.innerHTML = `
                        <div class="doctor-avatar">
                            <img src="../uploads/profile_pictures/${app.avatar}" alt="${app.doctorName}">
                        </div>
                        <div class="appointment-details">
                            <p>${app.specialty} with <strong>${app.doctorName}</strong></p>
                            <small>You have an upcoming appointment.</small>
                        </div>
                        <div class="appointment-time">
                            <span>${time}</span>
                            <small>${dateStr}</small>
                        </div>
                    `;
                    card.addEventListener('click', () => navigateToPage('appointments'));
                    appointmentsList.appendChild(card);
                });
                welcomeSubtext.textContent = `You have ${data.appointments.length} upcoming appointment(s).`;
            } else {
                appointmentsEmpty.style.display = 'block';
                welcomeSubtext.textContent = `You're all caught up. No upcoming appointments.`;
            }

            activityFeed.innerHTML = '';
            if (data.activity && data.activity.length > 0) {
                activityEmpty.style.display = 'none';
                
                const activityInfoMap = {
                    'booked_appointment': { icon: 'fa-calendar-plus', colorClass: 'appointments', page: 'appointments' },
                    'cancelled_appointment': { icon: 'fa-calendar-times', colorClass: 'billing', page: 'appointments' },
                    'downloaded_summary': { icon: 'fa-file-download', colorClass: 'labs', page: 'summaries' },
                    'downloaded_lab_report': { icon: 'fa-file-download', colorClass: 'labs', page: 'labs' },
                    'default': { icon: 'fa-history', colorClass: 'prescriptions', page: 'dashboard' }
                };

                data.activity.forEach(act => {
                    const item = document.createElement('div');
                    const activityInfo = activityInfoMap[act.action] || activityInfoMap['default'];
                    
                    item.className = 'activity-item notification-item';
                    item.dataset.page = activityInfo.page;
                    
                    const activityTime = new Date(act.time).toLocaleString('en-US', {
                        month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
                    });
                    
                    item.innerHTML = `
                        <div class="notification-icon ${activityInfo.colorClass}"><i class="fas ${activityInfo.icon}"></i></div>
                        <div class="notification-content">
                            <p>${act.details}</p>
                            <small class="timestamp">${activityTime}</small>
                        </div>
                    `;

                    item.addEventListener('click', (e) => {
                        e.preventDefault();
                        navigateToPage(activityInfo.page);
                    });
                    
                    activityFeed.appendChild(item);
                });

            } else {
                activityEmpty.style.display = 'block';
            }

            tokenWidget.innerHTML = ''; 
            if (data.token) {
                tokenEmpty.style.display = 'none';
                tokenWidget.innerHTML = `
                    <div class="mini-token-card">
                        <h4>${data.token.doctorName}</h4>
                        <div class="mini-token-body">
                            <div class="mini-token-number">
                                <p>Serving</p>
                                <span>#${String(data.token.current).padStart(2, '0')}</span>
                            </div>
                            <div class="mini-token-divider"></div>
                            <div class="mini-token-number your">
                                <p>Your Token</p>
                                <span>#${String(data.token.yours).padStart(2, '0')}</span>
                            </div>
                        </div>
                        <a href="#" class="btn-primary full-width" data-page="token">View Details</a>
                    </div>
                `;
                tokenWidget.querySelector('[data-page="token"]').addEventListener('click', (e) => {
                    e.preventDefault();
                    navigateToPage('token');
                });
            } else {
                tokenEmpty.style.display = 'block';
            }

        } catch (error) {
            console.error('Error fetching dashboard data:', error);
            appointmentsList.innerHTML = '';
            activityFeed.innerHTML = '';
            tokenWidget.innerHTML = '';
            appointmentsEmpty.style.display = 'block';
            activityEmpty.style.display = 'block';
            tokenEmpty.style.display = 'block';
            appointmentsEmpty.innerHTML = '<p>Could not load appointments.</p>';
            activityEmpty.innerHTML = '<p>Could not load activity.</p>';
            tokenEmpty.innerHTML = '<p>Could not load token status.</p>';
        }
    };

    // ===========================================
    // ===   NEW: LOGIN ACTIVITY FETCH/RENDER  ===
    // ===========================================
    const fetchAndRenderLoginActivity = async () => {
        const tableBody = document.getElementById('login-activity-body');
        if (!tableBody) return;
    
        tableBody.innerHTML = '<tr><td colspan="3" style="text-align: center;">Loading activity...</td></tr>';
    
        try {
            const response = await fetch('api.php?action=get_login_activity');
            const result = await response.json();
    
            if (result.success && result.data.length > 0) {
                tableBody.innerHTML = '';
                result.data.forEach(log => {
                    const row = document.createElement('tr');
                    const logDate = new Date(log.login_time);
                    const formattedDate = logDate.toLocaleString('en-US', {
                        month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true
                    });
                    
                    row.innerHTML = `
                        <td data-label="Date & Time">${formattedDate}</td>
                        <td data-label="IP Address">${log.ip_address}</td>
                        <td data-label="Status"><span class="status completed">Success</span></td>
                    `;
                    tableBody.appendChild(row);
                });
            } else {
                tableBody.innerHTML = '<tr><td colspan="3" style="text-align: center;">No recent login activity found.</td></tr>';
            }
        } catch (error) {
            console.error('Error fetching login activity:', error);
            tableBody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: var(--status-red);">Could not load activity.</td></tr>';
        }
    };

    // ===========================================
    // ===         PATIENT FEEDBACK LOGIC      ===
    // ===========================================
    
    const feedbackPage = document.getElementById('feedback-page');
    const feedbackModal = document.getElementById('feedback-modal');
    const feedbackModalClose = document.getElementById('feedback-modal-close');
    const feedbackForm = document.getElementById('feedback-form');
    const feedbackListContainer = document.getElementById('feedback-appointment-list');
    const feedbackLoadingState = document.getElementById('feedback-loading-state');
    const feedbackEmptyState = document.getElementById('feedback-empty-state');

    const createFeedbackCard = (app) => {
        const card = document.createElement('div');
        card.className = 'summary-card'; // Re-use existing styles
        
        const appDate = new Date(app.appointment_date).toLocaleDateString('en-US', {
            year: 'numeric', month: 'long', day: 'numeric'
        });

        // Store data on the card for the modal
        card.dataset.appointmentId = app.appointment_id;
        card.dataset.doctorName = app.doctor_name;
        card.dataset.appointmentDate = appDate;
        
        // Store submitted feedback details if they exist
        if (app.feedback_id) {
            card.dataset.comments = app.comments;
            card.dataset.rating = app.overall_rating;
        }

        const actionsHtml = (app.feedback_id)
            ? `<button class="btn-secondary btn-sm view-feedback-btn">
                   <i class="fas fa-eye"></i> View Feedback
               </button>`
            : `<button class="btn-primary btn-sm give-feedback-btn">
                   <i class="fas fa-edit"></i> Give Feedback
               </button>`;

        card.innerHTML = `
            <div class="summary-card-header">
                <div class="summary-icon"><i class="fas fa-user-md"></i></div>
                <div class="summary-info">
                    <h4>Dr. ${app.doctor_name} <small>(${app.specialty || 'General'})</small></h4>
                    <p>Appointment on: <strong>${appDate}</strong></p>
                </div>
            </div>
            <div class="summary-card-actions">
                ${actionsHtml}
            </div>
        `;
        return card;
    };

    const fetchAndRenderFeedback = async () => {
        if (!feedbackPage) return;

        feedbackLoadingState.style.display = 'block';
        feedbackEmptyState.style.display = 'none';
        feedbackListContainer.innerHTML = '';

        try {
            const response = await fetch('api.php?action=get_feedback_appointments');
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                result.data.forEach(app => {
                    const card = createFeedbackCard(app);
                    feedbackListContainer.appendChild(card);
                });
            } else {
                feedbackEmptyState.style.display = 'block';
            }
        } catch (error) {
            console.error('Error fetching feedback appointments:', error);
            feedbackListContainer.innerHTML = '<p class="error-text">Could not load appointments.</p>';
        } finally {
            feedbackLoadingState.style.display = 'none';
        }
    };

    // --- Star Rating Click Handler ---
    document.querySelectorAll('.star-rating').forEach(ratingGroup => {
        const stars = ratingGroup.querySelectorAll('.fa-star');
        const input = document.getElementById(ratingGroup.dataset.ratingInput);
        
        ratingGroup.addEventListener('click', (e) => {
            const clickedStar = e.target.closest('.fa-star');
            if (!clickedStar) return;

            const ratingValue = clickedStar.dataset.value;
            input.value = ratingValue;
            
            stars.forEach(star => {
                if (star.dataset.value <= ratingValue) {
                    star.classList.add('fas', 'selected');
                    star.classList.remove('far');
                } else {
                    star.classList.add('far');
                    star.classList.remove('fas', 'selected');
                }
            });
        });
    });
    
    // --- Reset Star Rating ---
    const resetStarRatings = (form) => {
        form.querySelectorAll('.star-rating').forEach(ratingGroup => {
            const input = document.getElementById(ratingGroup.dataset.ratingInput);
            input.value = "0";
            ratingGroup.querySelectorAll('.fa-star').forEach(star => {
                star.classList.add('far');
                star.classList.remove('fas', 'selected');
            });
        });
    };

    // --- Feedback Modal Open/Close/Submit ---
    if (feedbackListContainer) {
        feedbackListContainer.addEventListener('click', (e) => {
            const card = e.target.closest('.summary-card');
            if (!card) return;

            if (e.target.closest('.give-feedback-btn')) {
                // Populate and show modal
                document.getElementById('feedback-appointment-id').value = card.dataset.appointmentId;
                document.getElementById('feedback-doctor-name').textContent = card.dataset.doctorName;
                document.getElementById('feedback-appointment-date').textContent = card.dataset.appointmentDate;
                
                feedbackForm.reset();
                resetStarRatings(feedbackForm);
                
                feedbackModal.classList.add('show');
            }
            
            if (e.target.closest('.view-feedback-btn')) {
                // Just show an alert with existing feedback
                const rating = card.dataset.rating;
                const comments = card.dataset.comments;
                alert(`Your Feedback:\n\nRating: ${rating} Stars\nComments: ${comments || 'N/A'}`);
            }
        });
    }
    
    if (feedbackModalClose) {
        feedbackModalClose.addEventListener('click', () => feedbackModal.classList.remove('show'));
    }
    if (feedbackModal) {
        feedbackModal.addEventListener('click', (e) => {
            if (e.target === feedbackModal) feedbackModal.classList.remove('show');
        });
    }

    if (feedbackForm) {
        feedbackForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = document.getElementById('submit-feedback-btn');
            const originalBtnHtml = submitBtn.innerHTML;
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';

            try {
                const formData = new FormData(feedbackForm);
                formData.append('action', 'submit_feedback');

                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    feedbackModal.classList.remove('show');
                    fetchAndRenderFeedback(); // Refresh the list
                } else {
                    throw new Error(result.message);
                }

            } catch (error) {
                console.error('Feedback submission error:', error);
                alert(`Error: ${error.message}`);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        });
    }


    // --- Initial Page Load ---
    const savedTheme = localStorage.getItem('theme');
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    if (savedTheme) {
        applyTheme(savedTheme);
    } else if (prefersDark) {
        applyTheme('dark');
    } else {
        applyTheme('light');
    }

    navigateToPage('dashboard');
});