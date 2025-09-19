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

    // --- Page Navigation Logic ---
    let tokenInterval; // To hold the interval ID for the token page

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
        } else if (pageId === 'labs') {
            fetchAndRenderLabResults();
        } else if (pageId === 'prescriptions') {
            fetchAndRenderPrescriptions();
        } else if (pageId === 'appointments') {
            fetchAndRenderAppointments();
        } else if (pageId === 'token') {
            fetchAndRenderTokens(); // Fetch immediately
            tokenInterval = setInterval(fetchAndRenderTokens, 30000); // Then update every 30 seconds
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
            if (link.getAttribute('href') === '../logout.php') return;
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

    // --- Renders a single notification item ---
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
        
        // Add click listener to the whole item for navigation and marking as read
        item.addEventListener('click', (e) => {
            e.preventDefault();
            markNotificationAsRead(notification.id);
            navigateToPage(notification.type);
        });

        return item;
    };
    
    // --- Fetches notifications from the server and displays them ---
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

    // --- Marks a single notification as read ---
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
                // Optionally, refetch to update the count
                fetchAndRenderNotifications();
            }

        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    };

    // --- Updates the badge in the header ---
    const updateUnreadCount = (count) => {
        if (!notificationBadge) return;
        if (count > 0) {
            notificationBadge.textContent = count;
            notificationBadge.style.display = 'grid';
        } else {
            notificationBadge.style.display = 'none';
        }
    };
    
    // --- Notification Event Listeners ---
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

    // --- Generic function to handle form submissions via Fetch ---
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
                    form.reset(); // Clear password fields
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
    
    // --- Handle profile picture upload ---
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
                    const newImageUrl = `../uploads/profile_pictures/${result.filepath}?t=${new Date().getTime()}`; // Add timestamp to break cache
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

    // --- Password strength checker ---
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
    
    // --- Attach event listeners to forms ---
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
            alert('Saving Notification Preferences... (Backend logic needed)');
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
        "Step 1: Find Your Doctor", "Step 2: Select Date & Time",
        "Step 3: Pick Your Token", "Step 4: Confirm Details"
    ];

    const goToStep = (step) => {
        bookingSteps.forEach(s => s.style.display = 'none');
        document.getElementById(`booking-step-${step}`).style.display = 'block';
        bookingModalTitle.textContent = stepTitles[step - 1];
        currentStep = step;

        bookingBackBtn.style.display = (step > 1) ? 'inline-flex' : 'none';
        bookingNextBtn.style.display = (step < 4) ? 'inline-flex' : 'none';
        bookingConfirmBtn.style.display = (step === 4) ? 'inline-flex' : 'none';
        
        if (step === 2) {
            document.getElementById('selected-doctor-name').textContent = bookingData.doctorName;
            fetchAndRenderSlots(bookingData.doctorId); 
        } else if (step === 3) {
            document.getElementById('token-doctor-name').textContent = bookingData.doctorName;
            document.getElementById('token-selected-date').textContent = bookingData.date;
            document.getElementById('token-selected-slot').textContent = bookingData.slot;
            fetchAndRenderTokenGrid(bookingData.doctorId, bookingData.date, bookingData.slot);
        } else if (step === 4) {
            document.getElementById('confirm-doctor').textContent = `${bookingData.doctorName}`;
            document.getElementById('confirm-date').textContent = bookingData.date;
            document.getElementById('confirm-slot').textContent = bookingData.slot;
            document.getElementById('confirm-token').textContent = `#${bookingData.token}`;
        }
        updateNextButtonState();
    };

    const updateNextButtonState = () => {
        let enabled = false;
        switch(currentStep) {
            case 1: enabled = !!bookingData.doctorId; break;
            case 2: enabled = !!bookingData.date && !!bookingData.slot; break;
            case 3: enabled = !!bookingData.token; break;
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
            fetchAndRenderDoctors();
            bookingModal.classList.add('show');
        });
    }
    if (bookingCloseBtn) bookingCloseBtn.addEventListener('click', () => bookingModal.classList.remove('show'));
    if (bookingModal) bookingModal.addEventListener('click', (e) => { if(e.target === bookingModal) bookingModal.classList.remove('show') });
    
    if (bookingNextBtn) bookingNextBtn.addEventListener('click', () => { if (currentStep < 4) goToStep(currentStep + 1); });
    if (bookingBackBtn) bookingBackBtn.addEventListener('click', () => { if (currentStep > 1) goToStep(currentStep - 1); });

    if (bookingConfirmBtn) {
        bookingConfirmBtn.addEventListener('click', async () => {
            console.log("Submitting booking:", bookingData);
            alert('Appointment Confirmed! (This is a demo).');
            bookingModal.classList.remove('show');
            fetchAndRenderAppointments();
        });
    }
    
    const fetchAndRenderAppointments = async () => {
        const mockAppointments = {
            upcoming: [
                { id: 1, doctorName: 'Dr. Emily Carter', specialty: 'Cardiology', date: '2025-09-01', time: '11:00 AM', token: 8, status: 'scheduled' },
                { id: 2, doctorName: 'Dr. Alan Grant', specialty: 'General Checkup', date: '2025-09-05', time: '02:30 PM', token: 3, status: 'scheduled' }
            ],
            past: [
                { id: 3, doctorName: 'Dr. James Smith', specialty: 'Dermatology', date: '2025-08-15', time: '09:00 AM', token: 12, status: 'completed' }
            ]
        };
        
        const upcomingBody = document.getElementById('upcoming-appointments-body');
        upcomingBody.innerHTML = mockAppointments.upcoming.map(app => `
            <tr>
                <td data-label="Doctor"><strong>${app.doctorName}</strong><br><small>${app.specialty}</small></td>
                <td data-label="Date & Time">${new Date(app.date).toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})}, ${app.time}</td>
                <td data-label="Token No.">#${String(app.token).padStart(2, '0')}</td>
                <td data-label="Status"><span class="status upcoming">${app.status}</span></td>
                <td data-label="Actions"><button class="btn-danger btn-sm">Cancel</button></td>
            </tr>
        `).join('');

        const pastBody = document.getElementById('past-appointments-body');
        pastBody.innerHTML = mockAppointments.past.map(app => `
             <tr>
                <td data-label="Doctor"><strong>${app.doctorName}</strong><br><small>${app.specialty}</small></td>
                <td data-label="Date & Time">${new Date(app.date).toLocaleDateString('en-US', {weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'})}, ${app.time}</td>
                <td data-label="Token No.">#${String(app.token).padStart(2, '0')}</td>
                <td data-label="Status"><span class="status completed">${app.status}</span></td>
            </tr>
        `).join('');
    };

    const fetchAndRenderDoctors = async () => {
        const mockDoctors = [
            { id: 1, name: 'Dr. Emily Carter', specialty: 'Cardiology', profile_picture: 'doc1.jpg' },
            { id: 2, name: 'Dr. Alan Grant', specialty: 'General Medicine', profile_picture: 'doc2.jpg' },
            { id: 3, name: 'Dr. James Smith', specialty: 'Dermatology', profile_picture: 'doc3.jpg' }
        ];
        
        const doctorListContainer = document.getElementById('doctor-list');
        doctorListContainer.innerHTML = mockDoctors.map(doc => `
            <div class="doctor-card" data-doctor-id="${doc.id}" data-doctor-name="${doc.name}">
                <img src="../uploads/profile_pictures/${doc.profile_picture}" alt="${doc.name}">
                <div>
                    <strong>${doc.name}</strong><br>
                    <small>${doc.specialty}</small>
                </div>
            </div>
        `).join('');

        doctorListContainer.addEventListener('click', (e) => {
            const card = e.target.closest('.doctor-card');
            if (!card) return;
            doctorListContainer.querySelectorAll('.doctor-card').forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            bookingData.doctorId = card.dataset.doctorId;
            bookingData.doctorName = card.dataset.doctorName;
            updateNextButtonState();
        });
    };
    
    const fetchAndRenderSlots = async (doctorId) => {
        bookingData.date = '2025-09-10'; 
        const mockSlots = ["09:00 AM - 10:00 AM", "10:00 AM - 11:00 AM", "11:00 AM - 12:00 PM"];
        
        const slotsContainer = document.getElementById('slots-container');
        slotsContainer.innerHTML = mockSlots.map(slot => `<div class="slot" data-slot-time="${slot}">${slot}</div>`).join('');

        slotsContainer.addEventListener('click', (e) => {
            const slotEl = e.target.closest('.slot');
            if (!slotEl || slotEl.classList.contains('disabled')) return;
            slotsContainer.querySelectorAll('.slot').forEach(s => s.classList.remove('selected'));
            slotEl.classList.add('selected');
            bookingData.slot = slotEl.dataset.slotTime;
            updateNextButtonState();
        });
    };
    
    const fetchAndRenderTokenGrid = async (doctorId, date, slot) => {
        const totalTokens = 12;
        const bookedTokens = [2, 5, 8]; 

        const tokenGrid = document.getElementById('token-grid');
        tokenGrid.innerHTML = ''; 
        for (let i = 1; i <= totalTokens; i++) {
            const isBooked = bookedTokens.includes(i);
            const tokenEl = document.createElement('div');
            tokenEl.className = `token ${isBooked ? 'booked' : 'available'}`;
            tokenEl.textContent = i;
            if (!isBooked) tokenEl.dataset.tokenNumber = i;
            tokenGrid.appendChild(tokenEl);
        }

        tokenGrid.addEventListener('click', (e) => {
            const tokenEl = e.target.closest('.token.available');
            if (!tokenEl) return;
            tokenGrid.querySelectorAll('.token').forEach(t => t.classList.remove('selected'));
            tokenEl.classList.add('selected');
            bookingData.token = tokenEl.dataset.tokenNumber;
            updateNextButtonState();
        });
    };


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
                    <p>Prescribing Physician: <strong>Dr. ${prescription.doctor_name}</strong> | Status: <span class="status ${statusClass}">${formattedStatus}</span></p>
                </div>
            </div>
            <div class="summary-card-actions">
                <button class="btn-secondary btn-sm toggle-details-btn"><i class="fas fa-eye"></i> View Details</button>
                <a href="api/download_prescription.php?id=${prescription.id}" class="btn-primary btn-sm" target="_blank"><i class="fas fa-file-pdf"></i> Download PDF</a>
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
            
            const apiUrl = `api/get_prescriptions.php?date=${dateFilter}&status=${statusFilter}`;
            const response = await fetch(apiUrl);
            
            if (!response.ok) {
                 throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success && data.prescriptions.length > 0) {
                data.prescriptions.forEach(prescription => {
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

    if (prescriptionsPage) {
        applyPrescriptionFiltersBtn.addEventListener('click', fetchAndRenderPrescriptions);
    }
    
    // ===========================================
    // ===      BILLS & PAYMENTS PAGE LOGIC      ===
    // ===========================================
    
    const billingPage = document.getElementById('billing-page');
    const modalOverlay = document.getElementById('bill-details-modal');
    const billCloseModalBtn = document.getElementById('modal-close-btn');

    const getBillData = async (billId) => {
        const mockBills = {
            "1": {
                id: "TXN74652", date: "2025-08-28", description: "Consultation with Dr. Carter", amount: 50.00,
                status: "due", patientName: "John Doe",
                items: [{ description: "Cardiology Consultation Fee", amount: 50.00 }]
            },
            "2": {
                id: "TXN74601", date: "2025-08-20", description: "Lipid Profile Test", amount: 75.00,
                status: "paid", patientName: "John Doe",
                items: [
                    { description: "Lab Test: Lipid Profile", amount: 60.00 },
                    { description: "Report Generation Fee", amount: 15.00 }
                ]
            }
        };
        return mockBills[billId];
    };

    const showBillDetailsModal = async (billId) => {
        const data = await getBillData(billId);
        if (!data || !modalOverlay) return;

        document.getElementById('modal-bill-id').textContent = data.id;
        document.getElementById('modal-patient-name').textContent = data.patientName;
        document.getElementById('modal-bill-date').textContent = new Date(data.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        
        const statusEl = document.getElementById('modal-bill-status');
        statusEl.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
        statusEl.className = `status ${data.status}`;
        
        const itemizedBody = document.getElementById('modal-itemized-charges');
        itemizedBody.innerHTML = '';
        data.items.forEach(item => {
            itemizedBody.innerHTML += `<tr><td>${item.description}</td><td>$${item.amount.toFixed(2)}</td></tr>`;
        });
        
        document.getElementById('modal-total-amount').textContent = `$${data.amount.toFixed(2)}`;
        document.getElementById('modal-payment-section').style.display = data.status === 'due' ? 'block' : 'none';

        modalOverlay.classList.add('show');
    };

    if (billingPage) {
        billingPage.addEventListener('click', (e) => {
            const targetButton = e.target.closest('.view-bill-details-btn');
            if (targetButton) {
                showBillDetailsModal(targetButton.dataset.billId);
            }
        });
    }
    
    if (billCloseModalBtn) billCloseModalBtn.addEventListener('click', () => modalOverlay.classList.remove('show'));
    if (modalOverlay) modalOverlay.addEventListener('click', (e) => { if (e.target === modalOverlay) modalOverlay.classList.remove('show') });


    // ===========================================
    // ===        LAB RESULTS PAGE LOGIC       ===
    // ===========================================
    const labsPage = document.getElementById('labs-page');
    const labModal = document.getElementById('lab-details-modal');
    const closeLabModalBtn = document.getElementById('modal-lab-close-btn');
    const labTableBody = document.getElementById('lab-results-table-body');
    const emptyState = document.getElementById('lab-results-empty-state');
    const loadingState = document.getElementById('lab-results-loading-state');
    const applyFiltersBtn = document.getElementById('lab-apply-filters');

    const getLabResultsData = async (filters = {}) => {
        const mockResults = [
            { id: 1, test_date: "2025-08-20", test_name: "Lipid Profile", doctor_name: "Dr. Emily Carter", status: "ready", result_details: "Cholesterol: 200 mg/dL\nTriglycerides: 150 mg/dL\nHDL: 50 mg/dL\nLDL: 100 mg/dL", attachment_path: "/path/to/lipid-profile.pdf" },
            { id: 2, test_date: "2025-08-15", test_name: "Complete Blood Count (CBC)", doctor_name: "Dr. Alan Grant", status: "ready", result_details: "WBC: 5.0 x 10^9/L\nRBC: 4.5 x 10^12/L\nHemoglobin: 14 g/dL", attachment_path: null },
            { id: 3, test_date: "2025-09-01", test_name: "Thyroid Function Test", doctor_name: "Dr. Emily Carter", status: "pending", result_details: null, attachment_path: null }
        ];

        return mockResults.filter(result => {
            const searchInput = (filters.search || '').toLowerCase();
            const dateInput = filters.date || '';
            const nameMatch = result.test_name.toLowerCase().includes(searchInput);
            const dateMatch = dateInput ? result.test_date.startsWith(dateInput) : true;
            return nameMatch && dateMatch;
        });
    };

    const renderLabResults = (results) => {
        labTableBody.innerHTML = '';
        if (results.length > 0) {
            emptyState.style.display = 'none';
            results.forEach(result => {
                const row = document.createElement('tr');
                const statusClass = result.status === 'ready' ? 'ready' : 'pending';
                row.innerHTML = `
                    <td data-label="Test Date">${new Date(result.test_date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })}</td>
                    <td data-label="Test Name"><strong>${result.test_name}</strong></td>
                    <td data-label="Ordering Doctor">${result.doctor_name}</td>
                    <td data-label="Status"><span class="status ${statusClass}">${result.status.charAt(0).toUpperCase() + result.status.slice(1)}</span></td>
                    <td data-label="Actions">
                        <button class="btn-primary btn-sm view-lab-details-btn" data-result-id="${result.id}" ${result.status === 'pending' ? 'disabled' : ''}>View Details</button>
                        ${result.attachment_path ? `<a href="${result.attachment_path}" class="btn-secondary btn-sm" style="margin-left: 5px;" download><i class="fas fa-download"></i> Report</a>` : ''}
                    </td>`;
                labTableBody.appendChild(row);
            });
        } else {
            emptyState.style.display = 'block';
        }
    };

    const fetchAndRenderLabResults = async () => {
        if (!labsPage) return;
        loadingState.style.display = 'block';
        emptyState.style.display = 'none';
        labTableBody.innerHTML = '';
        try {
            const searchFilter = document.getElementById('lab-search-input').value;
            const dateFilter = document.getElementById('lab-filter-date').value;
            const results = await getLabResultsData({ search: searchFilter, date: dateFilter });
            labsPage.dataset.results = JSON.stringify(results);
            renderLabResults(results);
        } catch (error) {
            console.error("Error fetching lab results:", error);
            labTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">Could not load lab results.</td></tr>`;
        } finally {
            loadingState.style.display = 'none';
        }
    };

    const showLabDetailsModal = (resultId) => {
        const results = JSON.parse(labsPage.dataset.results || '[]');
        const data = results.find(r => r.id === parseInt(resultId));
        if (!data || !labModal) return;

        document.getElementById('modal-lab-test-name').textContent = data.test_name;
        document.getElementById('modal-lab-date').textContent = new Date(data.test_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
        document.getElementById('modal-lab-doctor').textContent = data.doctor_name;
        document.getElementById('modal-lab-result-details').textContent = data.result_details || 'Details are not available.';
        const downloadSection = document.getElementById('modal-lab-download-section');
        const downloadBtn = document.getElementById('modal-lab-download-btn');
        if (data.attachment_path) {
            downloadBtn.href = data.attachment_path;
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
        applyFiltersBtn.addEventListener('click', fetchAndRenderLabResults);
        if (closeLabModalBtn) closeLabModalBtn.addEventListener('click', () => labModal.classList.remove('show'));
        if (labModal) labModal.addEventListener('click', (e) => { if (e.target === labModal) labModal.classList.remove('show') });
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
        card.className = 'token-card';

        const yourToken = parseInt(tokenData.your_token, 10);
        const currentToken = parseInt(tokenData.current_token, 10);
        
        const tokensAhead = yourToken > currentToken ? yourToken - currentToken - 1 : 0;
        const avgTimePerToken = 5; 
        const estimatedWait = tokensAhead * avgTimePerToken;
        let waitMessage = `Approximately ${estimatedWait} minutes remaining.`;
        if (tokensAhead === 0 && yourToken > currentToken) {
            waitMessage = "You're next! Please be ready.";
        } else if (yourToken === currentToken) {
             waitMessage = "It's your turn now!";
        } else if (yourToken < currentToken || tokenData.token_status !== 'waiting') {
            waitMessage = "Your turn is complete or has passed.";
        }

        const progressPercentage = yourToken > 0 ? (currentToken / yourToken) * 100 : 0;
        const finalProgress = Math.min(progressPercentage, 100); 

        card.innerHTML = `
            <div class="token-card-header">
                <div class="token-doctor-info">
                    <h4>Dr. ${tokenData.doctor_name}</h4>
                    <p>${tokenData.specialty}</p>
                </div>
                <div class="token-status-badge ${tokenData.token_status.toLowerCase().replace(' ', '_')}">
                    ${tokenData.token_status}
                </div>
            </div>
            <div class="token-card-body">
                <div class="token-number-display">
                    <h5>Currently Serving</h5>
                    <span class="token-number">${currentToken > 0 ? '#' + String(currentToken).padStart(2, '0') : '--'}</span>
                </div>
                <div class="token-number-display your-token">
                    <h5>Your Token</h5>
                    <span class="token-number">#${String(yourToken).padStart(2, '0')}</span>
                </div>
            </div>
            ${ (tokenData.token_status === 'waiting' && yourToken > currentToken) ? `
            <div class="token-progress-tracker">
                <p>${tokensAhead} patient(s) ahead of you.</p>
                <div class="progress-bar">
                    <div class="progress-bar-inner" style="width: ${finalProgress}%;"></div>
                </div>
            </div>` : ''}
            <div class="token-card-footer">
                <i class="fas fa-info-circle"></i>
                <span>${waitMessage}</span>
            </div>
        `;
        return card;
    };

    const fetchAndRenderTokens = async () => {
        if (!tokenListPage.classList.contains('active')) return; 

        tokenLoadingState.style.display = 'block';
        tokenEmptyState.style.display = 'none';
        
        try {
            // NOTE: This API endpoint (get_live_tokens.php) needs to be created
            const response = await fetch('api/get_live_tokens.php');
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

// ===========================================
// ===      NEW: DASHBOARD PAGE LOGIC        ===
// ===========================================

const fetchAndRenderDashboardData = async () => {
    // Selectors for dashboard elements
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
        
        // Render Appointments
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

        // Render Activity Feed
        activityFeed.innerHTML = ''; 
        if (data.activity && data.activity.length > 0) {
            activityEmpty.style.display = 'none';
            const icons = {
                labs: { icon: 'fa-vial', page: 'labs'},
                billing: { icon: 'fa-file-invoice-dollar', page: 'billing' },
                prescriptions: { icon: 'fa-pills', page: 'prescriptions' },
                appointments: { icon: 'fa-calendar-check', page: 'appointments'}
            };
            
            data.activity.forEach(act => {
                const item = document.createElement('div');
                const activityInfo = icons[act.type] || { icon: 'fa-bell', page: 'notifications' };
                item.className = 'activity-item notification-item'; 
                item.dataset.page = activityInfo.page;
                
                const timeAgo = new Date(act.time);
                
                item.innerHTML = `
                    <div class="notification-icon ${act.type}"><i class="fas ${activityInfo.icon}"></i></div>
                    <div class="notification-content">
                        <p>${act.message}</p>
                        <small class="timestamp">${timeAgo.toLocaleString()}</small>
                    </div>
                `;
                item.addEventListener('click', () => navigateToPage(activityInfo.page));
                activityFeed.appendChild(item);
            });
        } else {
            activityEmpty.style.display = 'block';
        }

        // Render Token Widget
        tokenWidget.innerHTML = ''; 
        if (data.token) {
            tokenEmpty.style.display = 'none';
            tokenWidget.innerHTML = `
                <div class="mini-token-card">
                    <h4>Dr. ${data.token.doctorName}</h4>
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