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

    if (avatarUploadInput && profilePageAvatar) {
        avatarUploadInput.addEventListener('change', () => {
            const file = avatarUploadInput.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    profilePageAvatar.src = e.target.result;
                    if (profileAvatar) profileAvatar.src = e.target.result;
                    // TODO: Add an AJAX/Fetch call here to upload the new avatar to the server.
                    // Example:
                    // const formData = new FormData();
                    // formData.append('profile_picture', file);
                    // fetch('api/update_avatar.php', { method: 'POST', body: formData });
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
        item.className = `notification-item ${notification.is_read ? '' : 'unread'}`;
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
                <small class="timestamp">${notification.timestamp}</small>
            </div>
            <a href="#" class="notification-action" data-page="${notification.type}" title="View Details"><i class="fas fa-arrow-right"></i></a>
        `;
        
        // Add click listener to the whole item for navigation and marking as read
        item.addEventListener('click', () => {
            markNotificationAsRead(notification.id);
            navigateToPage(notification.type);
        });

        return item;
    };
    
    // --- Fetches notifications from the server and displays them ---
    const fetchAndRenderNotifications = async () => {
        if (!notificationList) return;
        
        const filter = notificationFilter ? notificationFilter.value : 'all';
        // You need to create this backend file.
        // It should query the DB and return a JSON array of notifications.
        const apiUrl = `api/get_notifications.php?filter=${filter}`;

        try {
            // Show a loading state (optional)
            notificationList.innerHTML = '<p>Loading notifications...</p>';
            
            const response = await fetch(apiUrl);
            const data = await response.json();

            notificationList.innerHTML = ''; // Clear loading/previous state

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
        // You need to create this backend file.
        // It should take a notification ID and update its 'is_read' status in the DB.
        try {
            const formData = new FormData();
            formData.append('id', notificationId);
            await fetch('api/mark_read.php', { method: 'POST', body: formData });
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
            // You need to create this backend file.
            // It should mark all user's unread notifications as read.
            try {
                await fetch('api/mark_all_read.php', { method: 'POST' });
                // Refresh the list after marking all as read
                fetchAndRenderNotifications();
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        });
    }

    // ===========================================
    // ===   DISCHARGE SUMMARY PAGE LOGIC      ===
    // ===========================================

    // Using event delegation for dynamically added summary cards
    document.addEventListener('click', (e) => {
        const toggleBtn = e.target.closest('.toggle-details-btn');
        if (!toggleBtn) return;

        const summaryCard = toggleBtn.closest('.summary-card');
        if (!summaryCard) return;

        summaryCard.classList.toggle('active');

        // Update button text and icon for better UX
        if (summaryCard.classList.contains('active')) {
            toggleBtn.innerHTML = `<i class="fas fa-eye-slash"></i> Hide Details`;
        } else {
            toggleBtn.innerHTML = `<i class="fas fa-eye"></i> View Details`;
        }
    });


    // ===========================================
    // ===   PROFILE PAGE FORM LOGIC (Existing)  ===
    // ===========================================

    // --- Password Strength Meter ---
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

    // --- Password Confirmation Validation ---
    const validatePasswordConfirmation = () => {
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
    
    // --- Form Submit Handlers ---
    if (personalInfoForm) {
        personalInfoForm.addEventListener('submit', (e) => {
            e.preventDefault();
            // TODO: Add AJAX/Fetch call to a backend script (e.g., api/update_profile.php).
            // Collect form data using: new FormData(personalInfoForm)
            // On success, show a success message. On error, show an error.
            alert('Saving Personal Information... (Backend logic needed)');
        });
    }

    if (changePasswordForm) {
        changePasswordForm.addEventListener('submit', (e) => {
            e.preventDefault();
            if (!validatePasswordConfirmation()) {
                alert('Passwords do not match.');
                return;
            }
            if (newPasswordInput.value.length < 8) {
                alert('Password must be at least 8 characters long.');
                return;
            }
            // TODO: Add AJAX/Fetch call to a backend script (e.g., api/update_password.php).
            // Send current_password, new_password, etc.
            // On success, clear fields and show success message. On error, show error.
            alert('Updating password... (Backend logic needed)');
        });
    }

    if (notificationPrefsForm) {
        notificationPrefsForm.addEventListener('submit', (e) => {
            e.preventDefault();
            // TODO: Add AJAX/Fetch call to a backend script (e.g., api/update_preferences.php).
            // Collect checkbox states and send them to the server.
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
    let bookingData = {}; // Object to hold { doctorId, doctorName, date, slot, token }

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
        
        // --- Step-specific actions when moving TO a step ---
        if (step === 2) {
            document.getElementById('selected-doctor-name').textContent = bookingData.doctorName;
            fetchAndRenderSlots(bookingData.doctorId); // Fetch slots for the selected doctor
        } else if (step === 3) {
            document.getElementById('token-doctor-name').textContent = bookingData.doctorName;
            document.getElementById('token-selected-date').textContent = bookingData.date;
            document.getElementById('token-selected-slot').textContent = bookingData.slot;
            // ** FIX: Renamed function to avoid conflict **
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

    // --- Tab logic for Appointments Page ---
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

    // --- Event Listeners for Booking Wizard ---
    if (bookNewBtn) {
        bookNewBtn.addEventListener('click', () => {
            bookingData = {}; // Reset data
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
            // REAL IMPLEMENTATION:
            // try {
            //     const response = await fetch('api/book_appointment.php', {
            //         method: 'POST',
            //         headers: { 'Content-Type': 'application/json' },
            //         body: JSON.stringify(bookingData)
            //     });
            //     const result = await response.json();
            //     if (result.success) {
            //         alert('Appointment booked successfully!');
            //         bookingModal.classList.remove('show');
            //         fetchAndRenderAppointments(); // Refresh the list
            //     } else {
            //         alert('Error: ' + result.message);
            //     }
            // } catch (err) {
            //     alert('An error occurred. Please try again.');
            // }
            alert('Appointment Confirmed! (This is a demo).');
            bookingModal.classList.remove('show');
            fetchAndRenderAppointments();
        });
    }
    
    // --- MOCK DATA AND API FUNCTIONS (Replace with real fetch calls) ---
    
    // Mock: Fetches and displays existing appointments
    const fetchAndRenderAppointments = async () => {
        // In a real app, you would fetch this from an API
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

    // Mock: Fetches and displays doctors for Step 1
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
    
    // Mock: Fetches and renders available slots for Step 2
    const fetchAndRenderSlots = async (doctorId) => {
        // This would fetch from `api/get_doctor_availability.php?doctor_id=${doctorId}`
        // For now, using a simple date and mock slots
        bookingData.date = '2025-09-10'; // Assume user picked a date
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
    
    // ** FIX: Renamed function to fetchAndRenderTokenGrid to avoid conflict **
    // Mock: Fetches and renders the token selection grid for Step 3
    const fetchAndRenderTokenGrid = async (doctorId, date, slot) => {
        // This would fetch from `api/get_booked_tokens.php?doctor_id=...`
        const totalTokens = 12;
        const bookedTokens = [2, 5, 8]; // Mock booked tokens

        const tokenGrid = document.getElementById('token-grid');
        tokenGrid.innerHTML = ''; // Clear previous tokens
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

    // --- Renders a single prescription card ---
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

    // --- Fetches and renders prescriptions ---
    const fetchAndRenderPrescriptions = async () => {
        if (!prescriptionsPage) return;

        prescriptionsLoadingState.style.display = 'block';
        prescriptionsEmptyState.style.display = 'none';
        prescriptionsList.innerHTML = '';

        try {
            const dateFilter = document.getElementById('prescription-filter-date').value;
            const statusFilter = document.getElementById('prescription-filter-status').value;
            
            // IMPORTANT: You must create this backend API endpoint.
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

    // --- Event Listeners for Prescription Page ---
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
        
        // --- Estimated Wait Time Calculation ---
        const tokensAhead = yourToken > currentToken ? yourToken - currentToken - 1 : 0;
        const avgTimePerToken = 5; // Assuming 5 minutes per token on average
        const estimatedWait = tokensAhead * avgTimePerToken;
        let waitMessage = `Approximately ${estimatedWait} minutes remaining.`;
        if (tokensAhead === 0 && yourToken > currentToken) {
            waitMessage = "You're next! Please be ready.";
        } else if (yourToken === currentToken) {
             waitMessage = "It's your turn now!";
        } else if (yourToken < currentToken || tokenData.token_status !== 'waiting') {
            waitMessage = "Your turn is complete or has passed.";
        }

        // --- Progress Bar Calculation ---
        const progressPercentage = yourToken > 0 ? (currentToken / yourToken) * 100 : 0;
        const finalProgress = Math.min(progressPercentage, 100); // Cap at 100%

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
        if (!tokenListPage.classList.contains('active')) return; // Only run if page is active

        tokenLoadingState.style.display = 'block';
        tokenEmptyState.style.display = 'none';
        
        try {
            // You will need to create this backend file.
            const response = await fetch('api/get_live_tokens.php');
            const data = await response.json();
            
            tokenContainer.innerHTML = ''; // Clear previous cards

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
    // In a real app, you would fetch from multiple API endpoints
    // For this demo, we'll use mock data and a timeout to simulate a network request.
    const getDashboardData = () => {
        return new Promise(resolve => {
            setTimeout(() => {
                const mockData = {
                    appointments: [
                        { doctorName: 'Dr. Emily Carter', specialty: 'Cardiology', time: 'Tomorrow, 11:00 AM', date: 'Sep 01', avatar: 'doc1.jpg' },
                        { doctorName: 'Dr. Alan Grant', specialty: 'General Medicine', time: '02:30 PM', date: 'Sep 05', avatar: 'doc2.jpg' }
                    ],
                    activity: [
                        { type: 'labs', icon: 'fa-vial', message: 'Your <strong>Lipid Profile</strong> lab result is ready.', time: '1 day ago', page: 'labs'},
                        { type: 'billing', icon: 'fa-file-invoice-dollar', message: 'You made a payment of <strong>$50.00</strong> for Consultation.', time: '2 days ago', page: 'billing'},
                        { type: 'prescriptions', icon: 'fa-pills', message: 'A new prescription was added by <strong>Dr. James Smith</strong>.', time: '5 days ago', page: 'prescriptions'}
                    ],
                    token: { doctorName: "Dr. Carter's Clinic", current: 5, yours: 8 }
                    // To test empty states, use this:
                    // appointments: [], activity: [], token: null
                };
                resolve(mockData);
            }, 1000); // Simulate 1-second loading time
        });
    };

    const data = await getDashboardData();
    
    // --- Render Appointments ---
    const appointmentsList = document.getElementById('dashboard-appointments-list');
    const appointmentsEmpty = document.getElementById('dashboard-appointments-empty');
    appointmentsList.innerHTML = ''; // Clear skeleton
    if (data.appointments && data.appointments.length > 0) {
        appointmentsEmpty.style.display = 'none';
        data.appointments.forEach(app => {
            const card = document.createElement('div');
            card.className = 'appointment-card';
            card.dataset.page = 'appointments'; // For navigation
            card.innerHTML = `
                <div class="doctor-avatar">
                    <img src="../uploads/profile_pictures/${app.avatar}" alt="${app.doctorName}">
                </div>
                <div class="appointment-details">
                    <p>${app.specialty} with <strong>${app.doctorName}</strong></p>
                    <small>You have an upcoming appointment.</small>
                </div>
                <div class="appointment-time">
                    <span>${app.time}</span>
                    <small>${app.date}</small>
                </div>
            `;
            card.addEventListener('click', () => navigateToPage('appointments'));
            appointmentsList.appendChild(card);
        });
        document.getElementById('welcome-subtext').textContent = `You have ${data.appointments.length} upcoming appointment(s).`;
    } else {
        appointmentsEmpty.style.display = 'block';
        document.getElementById('welcome-subtext').textContent = `You're all caught up. No upcoming appointments.`;
    }

    // --- Render Activity Feed ---
    const activityFeed = document.getElementById('dashboard-activity-feed');
    const activityEmpty = document.getElementById('dashboard-activity-empty');
    activityFeed.innerHTML = ''; // Clear skeleton
    if (data.activity && data.activity.length > 0) {
        activityEmpty.style.display = 'none';
        data.activity.forEach(act => {
            const item = document.createElement('div');
            item.className = 'activity-item notification-item'; // Re-use notification styles
            item.dataset.page = act.page;
            item.innerHTML = `
                <div class="notification-icon ${act.type}"><i class="fas ${act.icon}"></i></div>
                <div class="notification-content">
                    <p>${act.message}</p>
                    <small class="timestamp">${act.time}</small>
                </div>
            `;
            item.addEventListener('click', () => navigateToPage(act.page));
            activityFeed.appendChild(item);
        });
    } else {
        activityEmpty.style.display = 'block';
    }

    // --- Render Token Widget ---
    const tokenWidget = document.getElementById('dashboard-token-widget');
    const tokenEmpty = document.getElementById('dashboard-token-empty');
    tokenWidget.innerHTML = ''; // Clear skeleton
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
        // Re-attach event listener for the new button
        tokenWidget.querySelector('[data-page="token"]').addEventListener('click', (e) => {
            e.preventDefault();
            navigateToPage('token');
        });
    } else {
        tokenEmpty.style.display = 'block';
    }
};