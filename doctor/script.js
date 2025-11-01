document.addEventListener("DOMContentLoaded", function() {
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const navLinks = document.querySelectorAll('.sidebar-nav .nav-link');
    const pages = document.querySelectorAll('.main-content .page');
    const mainHeaderTitle = document.getElementById('main-header-title');
    
    // --- Global cache for dropdowns to prevent repeated API calls ---
    let patientListCache = null;

    // ===================================================================
    // --- NEW HELPER FUNCTIONS for Profile Page ---
    // ===================================================================

    /**
     * Populates a select dropdown with options from an API endpoint.
     * @param {string} endpoint The API endpoint to fetch data from (e.g., 'api.php?action=get_specialities').
     * @param {string} selectId The ID of the <select> element to populate.
     * @param {string} placeholder The text to show for the default option.
     * @param {string|number} selectedValue The value that should be pre-selected.
     */
    async function populateSelectDropdown(endpoint, selectId, placeholder, selectedValue) {
        const selectElement = document.getElementById(selectId);
        if (!selectElement) return;

        try {
            const response = await fetch(endpoint);
            const result = await response.json();
            if (result.success) {
                selectElement.innerHTML = `<option value="">-- ${placeholder} --</option>`;
                result.data.forEach(item => {
                    const isSelected = item.id == selectedValue ? 'selected' : '';
                    selectElement.innerHTML += `<option value="${item.id}" ${isSelected}>${item.name}</option>`;
                });
            } else {
                selectElement.innerHTML = `<option value="">Error loading data</option>`;
            }
        } catch (error) {
            console.error(`Failed to fetch data for ${selectId}:`, error);
            selectElement.innerHTML = `<option value="">Error loading data</option>`;
        }
    }

    /**
     * Validates a single form field and displays an error message if needed.
     * @param {HTMLElement} field The input/select element to validate.
     * @returns {boolean} True if the field is valid, false otherwise.
     */
    function validateField(field) {
        const errorElement = field.parentElement.querySelector('.validation-error');
        let message = '';

        // Check for required fields
        if (field.required && !field.value.trim()) {
            message = 'This field is required.';
        }
        // Check for pattern mismatch (like for the phone number)
        else if (field.pattern && field.value && !new RegExp(field.pattern).test(field.value)) {
             message = field.parentElement.querySelector('.validation-error').textContent || 'Invalid format.';
        }
        // Special check for date of birth year
        else if (field.id === 'profile-dob' && field.value) {
            const year = field.value.split('-')[0];
            if (year.length > 4) {
                message = 'Please enter a valid 4-digit year.';
            }
        }
        
        if (message && errorElement) {
            errorElement.textContent = message;
            return false;
        } else if (errorElement) {
            errorElement.textContent = '';
            return true;
        }
        return true;
    }


    // ===================================================================
    // --- Notification Widget Logic ---
    // ===================================================================
    const notificationBell = document.getElementById('notification-bell');
    const notificationBadge = document.getElementById('notification-badge');
    const notificationPanel = document.getElementById('notification-panel');
    const notificationDropdownBody = notificationPanel ? notificationPanel.querySelector('.dropdown-body') : null;
    const viewAllNotificationsLink = document.getElementById('view-all-notifications-link');

    const fetchUnreadNotificationCount = async () => {
        try {
            const response = await fetch('api.php?action=get_unread_notification_count');
            const result = await response.json();
            if (result.success && result.data.count > 0) {
                notificationBadge.textContent = result.data.count > 9 ? '9+' : result.data.count;
                notificationBadge.classList.remove('hidden');
                notificationBadge.style.display = 'flex';
            } else {
                notificationBadge.classList.add('hidden');
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
            const response = await fetch(`api.php?action=get_notifications&limit=${limit}`);
            const result = await response.json();
            if (!result.success) throw new Error(result.message);
            renderNotifications(result.data, container);
        } catch (error) {
            console.error('Failed to fetch notifications:', error);
            container.innerHTML = `<p class="no-items-message" style="color: var(--danger-color)">Failed to load notifications.</p>`;
        }
    };
    
    if (notificationBell) {
        notificationBell.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationPanel.classList.toggle('active');
            if (notificationPanel.classList.contains('active')) {
                fetchAndRenderNotifications(notificationDropdownBody, 5);
            }
        });
    }

    if (viewAllNotificationsLink) {
        viewAllNotificationsLink.addEventListener('click', (e) => {
            e.preventDefault();
            notificationPanel.classList.remove('active');
            const notificationsNavLink = document.querySelector('.nav-link[data-page="notifications"]');
            if (notificationsNavLink) notificationsNavLink.click();
        });
    }

    // Close notification dropdown when clicking outside
    document.addEventListener('click', (e) => {
        if (notificationPanel && !notificationPanel.contains(e.target) && !notificationBell.contains(e.target)) {
            notificationPanel.classList.remove('active');
        }
    });

    // Fetch notification count on load and periodically
    fetchUnreadNotificationCount();
    setInterval(fetchUnreadNotificationCount, 30000); // Every 30 seconds

    // ===================================================================
    // --- Dashboard Page Logic ---
    // ===================================================================
    async function loadDashboardData() {
        const appointmentsValue = document.getElementById('stat-appointments-value');
        const admissionsValue = document.getElementById('stat-admissions-value');
        const dischargesValue = document.getElementById('stat-discharges-value');
        const appointmentsTbody = document.getElementById('dashboard-appointments-tbody');
        const inpatientsTbody = document.getElementById('dashboard-inpatients-tbody');

        if (appointmentsTbody) appointmentsTbody.innerHTML = `<tr><td colspan="5" class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>`;
        if (inpatientsTbody) inpatientsTbody.innerHTML = `<tr><td colspan="3" class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>`;

        try {
            const response = await fetch('api.php?action=get_dashboard_data');
            const result = await response.json();

            if (result.success) {
                const data = result.data;
                
                if (appointmentsValue) appointmentsValue.textContent = data.stats.today_appointments || 0;
                if (admissionsValue) admissionsValue.textContent = data.stats.active_admissions || 0;
                if (dischargesValue) dischargesValue.textContent = data.stats.pending_discharges || 0;

                if (appointmentsTbody) {
                    appointmentsTbody.innerHTML = ''; 
                    if (data.appointments.length > 0) {
                        data.appointments.forEach(appt => {
                            const time = new Date(appt.appointment_date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td data-label="Token">${appt.token_number || 'N/A'}</td>
                                <td data-label="Patient Name">${appt.patient_name}</td>
                                <td data-label="Time">${time}</td>
                                <td data-label="Status"><span class="status ${appt.status.toLowerCase()}">${appt.status}</span></td>
                                <td data-label="Action">
                                    <button class="action-btn start-consultation-btn" 
                                            data-appointment-id="${appt.id}" 
                                            data-patient-name="${appt.patient_name}">
                                        <i class="fas fa-play-circle"></i> Start
                                    </button>
                                </td>
                            `;
                            appointmentsTbody.appendChild(tr);
                        });
                    } else {
                        appointmentsTbody.innerHTML = `<tr><td colspan="5" style="text-align: center;">No appointments scheduled for today.</td></tr>`;
                    }
                }
                
                if (inpatientsTbody) {
                    inpatientsTbody.innerHTML = '';
                    if (data.inpatients.length > 0) {
                        data.inpatients.forEach(patient => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td data-label="Patient Name">${patient.patient_name}</td>
                                <td data-label="Room/Bed">${patient.room_bed}</td>
                                <td data-label="Action"><button class="action-btn view-record" data-id="${patient.patient_id}"><i class="fas fa-file-medical"></i> View</button></td>
                            `;
                            inpatientsTbody.appendChild(tr);
                        });
                    } else {
                        inpatientsTbody.innerHTML = `<tr><td colspan="3" style="text-align: center;">No active in-patients.</td></tr>`;
                    }
                }
            } else {
                 console.error("Dashboard Error:", result.message);
                 if (appointmentsTbody) appointmentsTbody.innerHTML = `<tr><td colspan="5" class="loading-placeholder" style="color:var(--danger-color);">Failed to load data.</td></tr>`;
                 if (inpatientsTbody) inpatientsTbody.innerHTML = `<tr><td colspan="3" class="loading-placeholder" style="color:var(--danger-color);">Failed to load data.</td></tr>`;
            }
        } catch (error) {
            console.error("Failed to fetch dashboard data:", error);
        }
    }
    
    // ===================================================================
    // --- START: DISCHARGE PROCESS LOGIC ---
    // ===================================================================
    let allDischargeData = [];

    /**
     * Fetches all discharge requests from the API and renders them in the table.
     */
    async function loadDischargeRequests() {
        const tableBody = document.querySelector('#discharge-requests-table tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="6" class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading discharge requests...</td></tr>`;

        try {
            const response = await fetch('api.php?action=get_discharge_requests');
            const result = await response.json();

            if (result.success) {
                allDischargeData = result.data;
                renderDischargeRequestsTable(allDischargeData);
            } else {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">${result.message || 'No requests found.'}</td></tr>`;
            }
        } catch (error) {
            console.error("Error loading discharge requests:", error);
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">Failed to load data.</td></tr>`;
        }
    }

    /**
     * Renders the discharge requests table with the provided data.
     * @param {Array} requests An array of discharge request objects.
     */
    function renderDischargeRequestsTable(requests) {
        const tableBody = document.querySelector('#discharge-requests-table tbody');
        tableBody.innerHTML = '';
        if (requests.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No discharge requests found.</td></tr>`;
            return;
        }

        requests.forEach(req => {
            const tr = document.createElement('tr');
            const initiatedDate = new Date(req.initiated_at).toLocaleDateString();
            
            // Determine the CSS class for the status badge
            let statusClass = 'pending';
            if (req.status === 'Ready for Discharge') statusClass = 'ready-discharge';
            if (req.status === 'Completed') statusClass = 'completed';

            // Dynamically create action buttons based on the request's status
            let actionsHtml = `<button class="action-btn view-discharge-status" data-id="${req.admission_id}" data-patient-name="${req.patient_name}"><i class="fas fa-tasks"></i> Status</button>`;
            
            if (req.status !== 'Completed') {
                actionsHtml += `<button class="action-btn write-discharge-summary" data-id="${req.admission_id}" data-patient-name="${req.patient_name}" data-admission-date="${req.admission_date}"><i class="fas fa-file-medical-alt"></i> Summary</button>`;
            }
            // Allow downloading the summary if it's ready or completed
            if (req.status === 'Ready for Discharge' || req.status === 'Completed') {
                 actionsHtml += `<button class="action-btn download-discharge-summary" data-id="${req.admission_id}"><i class="fas fa-download"></i> PDF</button>`;
            }

            tr.innerHTML = `
                <td data-label="Req. ID">ADM-${req.admission_id}</td>
                <td data-label="Patient">${req.patient_name} (${req.display_user_id})</td>
                <td data-label="Room/Bed">${req.room_bed || 'N/A'}</td>
                <td data-label="Initiated">${initiatedDate}</td>
                <td data-label="Status"><span class="status ${statusClass}">${req.status}</span></td>
                <td data-label="Actions">${actionsHtml}</td>
            `;
            tableBody.appendChild(tr);
        });
    }

    /**
     * Opens and populates the discharge status timeline modal.
     * @param {string} admissionId The ID of the admission.
     * @param {string} patientName The name of the patient.
     */
    async function openDischargeStatusModal(admissionId, patientName) {
        const modal = document.getElementById('discharge-status-modal-overlay');
        const timeline = modal.querySelector('.timeline');
        
        document.getElementById('discharge-modal-title').textContent = `Discharge Status for ${patientName}`;
        timeline.innerHTML = `<li class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading status...</li>`;
        openModalById('discharge-status-modal-overlay');

        try {
            const response = await fetch(`api.php?action=get_discharge_status&admission_id=${admissionId}`);
            const result = await response.json();
            timeline.innerHTML = '';

            if (result.success) {
                result.data.forEach(step => {
                    const li = document.createElement('li');
                    li.className = `timeline-item ${step.is_cleared ? 'complete' : 'pending'}`;
                    const clearedAt = step.cleared_at ? new Date(step.cleared_at).toLocaleString() : 'Pending';
                    
                    li.innerHTML = `
                        <div class="timeline-icon"><i class="fas ${step.is_cleared ? 'fa-check' : 'fa-clock'}"></i></div>
                        <div class="timeline-content">
                            <strong>${step.clearance_step.charAt(0).toUpperCase() + step.clearance_step.slice(1)} Clearance</strong>
                            <p>${step.is_cleared ? `Cleared by ${step.cleared_by || 'System'}` : 'Awaiting clearance.'}</p>
                            <span>${clearedAt}</span>
                        </div>
                    `;
                    timeline.appendChild(li);
                });
            } else {
                timeline.innerHTML = `<li>Error: ${result.message}</li>`;
            }
        } catch (error) {
            console.error('Error fetching discharge status:', error);
            timeline.innerHTML = `<li>Could not load status details.</li>`;
        }
    }

    /**
     * Opens and populates the discharge summary modal for editing.
     * @param {string} admissionId The ID of the admission.
     * @param {string} patientName The name of the patient.
     * @param {string} admissionDate The date of admission.
     */
    async function openDischargeSummaryModal(admissionId, patientName, admissionDate) {
        const modal = document.getElementById('discharge-summary-modal-overlay');
        const form = document.getElementById('discharge-summary-form');
        form.reset();
        
        // Set static info
        document.getElementById('summary-admission-id').value = admissionId;
        document.getElementById('summary-patient-name').textContent = patientName;
        document.getElementById('summary-admission-date').textContent = new Date(admissionDate).toLocaleString();
        
        openModalById('discharge-summary-modal-overlay');

        try {
            // Fetch existing data to pre-fill the form
            const response = await fetch(`api.php?action=get_discharge_summary_details&admission_id=${admissionId}`);
            const result = await response.json();
            
            if (result.success && result.data) {
                form.elements['discharge_date'].value = result.data.discharge_date || '';
                form.elements['summary_text'].value = result.data.summary_text || '';
            }
        } catch (error) {
            console.error("Could not pre-fill summary:", error);
        }
    }

    // --- END: DISCHARGE PROCESS LOGIC ---

    // ===================================================================
    // --- NOTIFICATIONS PAGE LOGIC ---
    // ===================================================================
    async function loadAllNotifications() {
        const notificationListContainer = document.querySelector('.notification-list-container');
        if (!notificationListContainer) return;
        
        notificationListContainer.innerHTML = '<p class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading notifications...</p>';
        
        try {
            const response = await fetch('api.php?action=get_notifications&limit=100');
            const result = await response.json();
            
            if (result.success) {
                renderAllNotifications(result.data, notificationListContainer);
            } else {
                notificationListContainer.innerHTML = `<p class="no-items-message">${result.message || 'No notifications found.'}</p>`;
            }
        } catch (error) {
            console.error('Failed to load all notifications:', error);
            notificationListContainer.innerHTML = `<p class="no-items-message" style="color: var(--danger-color);">Failed to load notifications.</p>`;
        }
    }

    function renderAllNotifications(notifications, container) {
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
    }

    // Mark all as read button handler
    const markAllReadBtn = document.getElementById('mark-all-read-btn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async () => {
            try {
                const response = await fetch('api.php?action=mark_all_notifications_read', {
                    method: 'POST'
                });
                const result = await response.json();
                if (result.success) {
                    loadAllNotifications();
                    fetchUnreadNotificationCount();
                } else {
                    alert(result.message || 'Failed to mark notifications as read');
                }
            } catch (error) {
                console.error('Failed to mark all as read:', error);
                alert('Failed to mark notifications as read');
            }
        });
    }
    // --- END: NOTIFICATIONS PAGE LOGIC ---

    // --- START: PATIENT ENCOUNTER LOGIC ---
    document.addEventListener('click', function(e) {
        const startBtn = e.target.closest('.start-consultation-btn');
        if (startBtn) {
            const appointmentId = startBtn.dataset.appointmentId;
            const patientName = startBtn.dataset.patientName;
            openEncounterModal(appointmentId, patientName);
        }
    });

    /**
     * Opens and populates the consultation modal.
     * @param {string} appointmentId The ID of the appointment.
     * @param {string} patientName The name of the patient.
     */
    async function openEncounterModal(appointmentId, patientName) {
        const modal = document.getElementById('encounter-modal-overlay');
        const form = document.getElementById('encounter-form');
        form.reset(); // Clear previous data

        // Set initial state
        document.getElementById('encounter-modal-title').textContent = `Consultation for: ${patientName}`;
        modal.classList.add('active');
        
        try {
            const response = await fetch(`api.php?action=get_encounter_details&appointment_id=${appointmentId}`);
            const result = await response.json();

            if (result.success) {
                // Populate patient info
                document.getElementById('encounter-appointment-id').value = appointmentId;
                document.getElementById('encounter-patient-id').value = result.patient_data.patient_id;

                // Populate form with existing encounter data if it exists
                if (result.encounter_data) {
                    const data = result.encounter_data;
                    document.getElementById('encounter-id').value = data.id;
                    form.elements['chief_complaint'].value = data.chief_complaint || '';
                    form.elements['soap_subjective'].value = data.soap_subjective || '';
                    form.elements['soap_objective'].value = data.soap_objective || '';
                    form.elements['soap_assessment'].value = data.soap_assessment || '';
                    form.elements['soap_plan'].value = data.soap_plan || '';
                    form.elements['diagnosis_icd10'].value = data.diagnosis_icd10 || '';

                    if (data.vitals) {
                        form.elements['vitals[bp]'].value = data.vitals.bp || '';
                        form.elements['vitals[hr]'].value = data.vitals.hr || '';
                        form.elements['vitals[temp]'].value = data.vitals.temp || '';
                        form.elements['vitals[rr]'].value = data.vitals.rr || '';
                    }
                }
            } else {
                alert(`Error: ${result.message}`);
                modal.classList.remove('active');
            }
        } catch (error) {
            console.error('Failed to load encounter details:', error);
            alert('A network error occurred.');
            modal.classList.remove('active');
        }
    }

    // Handle saving the encounter form
    document.getElementById('encounter-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const saveButton = document.getElementById('save-encounter-btn');
        saveButton.disabled = true;
        saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Saving...`;

        const formData = new FormData(this);
        formData.append('action', 'save_encounter');

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                document.getElementById('encounter-modal-overlay').classList.remove('active');
                
                loadDashboardData();
                
                const activeAppointmentTab = document.querySelector('.appointments-page .tab-link.active');
                if (activeAppointmentTab) {
                    loadAppointments(activeAppointmentTab.dataset.tab);
                }

                alert(result.message);
            } else {
                alert(`Error: ${result.message}`);
            }

        } catch (error) {
            console.error('Save encounter error:', error);
            alert('A network error occurred while saving.');
        } finally {
            saveButton.disabled = false;
            saveButton.innerHTML = 'Save Consultation';
        }
    });

    // Handle integrated action buttons inside the encounter modal
    document.getElementById('encounter-new-prescription-btn').addEventListener('click', () => {
        const patientId = document.getElementById('encounter-patient-id').value;
        const patientName = document.getElementById('encounter-modal-title').textContent.replace('Consultation for: ', '');

        if (!patientId) {
            alert('Please save the consultation once before creating an order.');
            return;
        }

        const createBtn = document.getElementById('create-prescription-btn');
        if (createBtn) createBtn.click();

        setTimeout(() => {
            document.getElementById('patient-id-presc').value = patientId;
            document.getElementById('patient-search-presc').value = patientName;
        }, 100);
    });

    document.getElementById('encounter-new-lab-btn').addEventListener('click', () => {
        const patientId = document.getElementById('encounter-patient-id').value;
        const patientName = document.getElementById('encounter-modal-title').textContent.replace('Consultation for: ', '');

        if (!patientId) {
            alert('Please save the consultation once before creating an order.');
            return;
        }

        const createBtn = document.getElementById('place-lab-order-btn');
        if (createBtn) createBtn.click();

        setTimeout(() => {
            document.getElementById('lab-order-patient-id').value = patientId;
            document.getElementById('lab-order-patient-search').value = patientName;
        }, 100);
    });

    document.getElementById('encounter-admit-patient-btn').addEventListener('click', () => {
    const patientId = document.getElementById('encounter-patient-id').value;
    const patientName = document.getElementById('encounter-modal-title').textContent.replace('Consultation for: ', '');

    if (!patientId) {
        alert('Patient information is not available.');
        return;
    }

    // Call the existing function that opens the admit modal
    openAdmitPatientModal();

    // Use a short delay to ensure the modal is ready, then pre-fill the patient info
    setTimeout(() => {
        document.getElementById('patient-id-admit').value = patientId;
        document.getElementById('patient-search-admit').value = patientName;
        // Hide the search results dropdown since we've already selected the patient
        document.getElementById('patient-search-results-admit').classList.remove('active');
    }, 150);
});

    // ===================================================================
    // --- Medical Record Modal Logic ---
    // ===================================================================
    document.querySelector('.main-content').addEventListener('click', function(e) {
        const viewRecordBtn = e.target.closest('.view-record');
        if (viewRecordBtn) {
            const patientId = viewRecordBtn.dataset.id;
            openMedicalRecordModal(patientId);
        }
    });

    async function openMedicalRecordModal(patientId) {
        const modal = document.getElementById('medical-record-modal-overlay');
        const loader = document.getElementById('medical-record-loader');
        const content = document.getElementById('medical-record-content');
        
        modal.classList.add('active');
        loader.style.display = 'block';
        content.style.display = 'none';

        try {
            const response = await fetch(`api.php?action=get_patient_medical_record&patient_id=${patientId}`);
            const result = await response.json();

            if (result.success) {
                populateMedicalRecordModal(result.data);
            } else {
                alert(`Error: ${result.message}`);
                modal.classList.remove('active');
            }
        } catch (error) {
            console.error('Failed to load medical record:', error);
            alert('A network error occurred.');
            modal.classList.remove('active');
        } finally {
            loader.style.display = 'none';
            content.style.display = 'block';
        }
    }

    function populateMedicalRecordModal(data) {
        // 1. Populate Patient Details (This part remains the same)
        const details = data.details;
        document.getElementById('record-patient-name').textContent = details.name || 'N/A';
        document.getElementById('record-patient-id').textContent = details.display_user_id || 'N/A';
        document.getElementById('record-patient-age').textContent = details.age || 'N/A';
        document.getElementById('record-patient-gender').textContent = details.gender || 'N/A';

        // 2. Populate Clinical Encounters (NEW SECTION)
        const encountersTbody = document.getElementById('record-encounters-tbody');
        encountersTbody.innerHTML = '';
        if (data.encounters && data.encounters.length > 0) {
            data.encounters.forEach(enc => {
                // The 'view-encounter' class and 'data-appointment-id' are key for making this clickable
                encountersTbody.innerHTML += `
                    <tr class="clickable-row view-encounter" data-appointment-id="${enc.appointment_id}" title="Click to view details">
                        <td>${new Date(enc.encounter_date).toLocaleDateString()}</td>
                        <td>${enc.chief_complaint || 'N/A'}</td>
                        <td>Dr. ${enc.doctor_name}</td>
                        <td><button class="action-btn"><i class="fas fa-eye"></i> View</button></td>
                    </tr>
                `;
            });
        } else {
            encountersTbody.innerHTML = '<tr><td colspan="4">No clinical encounters found.</td></tr>';
        }

        // 3. Populate Admissions History (This part remains mostly the same)
        const admissionsTbody = document.getElementById('record-admissions-tbody');
        admissionsTbody.innerHTML = '';
        if (data.admissions.length > 0) {
            data.admissions.forEach(adm => {
                admissionsTbody.innerHTML += `
                    <tr>
                        <td>ADM-${adm.id}</td>
                        <td>${new Date(adm.admission_date).toLocaleDateString()}</td>
                        <td>${adm.room_bed || 'N/A'}</td>
                        <td><span class="status ${adm.status.toLowerCase()}">${adm.status}</span></td>
                    </tr>
                `;
            });
        } else {
            admissionsTbody.innerHTML = '<tr><td colspan="4">No admission history found.</td></tr>';
        }

        // 4. Populate Prescriptions History (MODIFIED to be clickable)
        const prescriptionsTbody = document.getElementById('record-prescriptions-tbody');
        prescriptionsTbody.innerHTML = '';
        if (data.prescriptions.length > 0) {
            data.prescriptions.forEach(rx => {
                // Added 'view-prescription' class and data-id to the <tr>
                prescriptionsTbody.innerHTML += `
                    <tr class="clickable-row view-prescription" data-id="${rx.id}" title="Click to view details">
                        <td>RX-${rx.id}</td>
                        <td>${new Date(rx.prescription_date).toLocaleDateString()}</td>
                        <td><span class="status ${rx.status.toLowerCase()}">${rx.status}</span></td>
                        <td><button class="action-btn"><i class="fas fa-eye"></i> View</button></td>
                    </tr>
                `;
            });
        } else {
            prescriptionsTbody.innerHTML = '<tr><td colspan="4">No prescription history found.</td></tr>';
        }

        // 5. Populate Labs History (MODIFIED to be clickable)
        const labsTbody = document.getElementById('record-labs-tbody');
        labsTbody.innerHTML = '';
        if (data.labs.length > 0) {
            data.labs.forEach(lab => {
                let actionHtml = `<button class="action-btn" disabled>Pending</button>`;
                let rowClass = '';
                if (lab.status.toLowerCase() === 'completed') {
                    // Added 'view-lab-report' class and data-id to the <tr>
                    rowClass = 'clickable-row view-lab-report';
                    actionHtml = `<button class="action-btn"><i class="fas fa-file-alt"></i> View</button>`;
                }
                labsTbody.innerHTML += `
                    <tr class="${rowClass}" data-id="${lab.id}" title="Click to view details">
                        <td>ORD-${lab.id}</td>
                        <td>${lab.test_name}</td>
                        <td>${new Date(lab.ordered_at).toLocaleDateString()}</td>
                        <td><span class="status ${lab.status.toLowerCase()}">${lab.status}</span></td>
                        <td>${actionHtml}</td>
                    </tr>
                `;
            });
        } else {
            labsTbody.innerHTML = '<tr><td colspan="5">No lab order history found.</td></tr>';
        }
    }
    
    // Add an Event Listener for the Clicks within the Medical Record Modal
    const medicalRecordModal = document.getElementById('medical-record-modal-overlay');
    if (medicalRecordModal) {
        medicalRecordModal.addEventListener('click', function(e) {
            const viewPrescriptionRow = e.target.closest('.view-prescription');
            const viewLabReportRow = e.target.closest('.view-lab-report');
            const viewEncounterRow = e.target.closest('.view-encounter');

            if (viewPrescriptionRow) {
                const prescriptionId = viewPrescriptionRow.dataset.id;
                // Reuse your existing function to open the prescription view modal
                openViewPrescriptionModal(prescriptionId);
            }

            if (viewLabReportRow) {
                const reportId = viewLabReportRow.dataset.id;
                // Reuse your existing function to open the lab report modal
                openViewLabReportModal(reportId);
            }

            if (viewEncounterRow) {
                const appointmentId = viewEncounterRow.dataset.appointmentId;
                const patientName = document.getElementById('record-patient-name').textContent;
                if (appointmentId) {
                    // Reuse your existing encounter modal to show the details
                    openEncounterModal(appointmentId, patientName);
                } else {
                    alert('Details for this encounter are not available.');
                }
            }
        });
    }

    // ===================================================================
    // --- Quick Actions Logic ---
    // ===================================================================
    const quickAdmitBtn = document.getElementById('quick-action-admit');
    const quickPrescribeBtn = document.getElementById('quick-action-prescribe');
    const quickLabBtn = document.getElementById('quick-action-lab');
    const quickDischargeBtn = document.getElementById('quick-action-discharge');

    if (quickAdmitBtn) {
        quickAdmitBtn.addEventListener('click', (e) => {
            e.preventDefault();
            openAdmitPatientModal(); 
        });
    }

    if (quickPrescribeBtn) {
        quickPrescribeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('create-prescription-btn').click();
        });
    }

    if (quickLabBtn) {
        quickLabBtn.addEventListener('click', (e) => {
            e.preventDefault();
            document.getElementById('place-lab-order-btn').click();
        });
    }

    if (quickDischargeBtn) {
        quickDischargeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const dischargeNavLink = document.querySelector('.nav-link[data-page="admissions"]');
            if (dischargeNavLink) {
                dischargeNavLink.click();
            }
        });
    }

    // ===================================================================
    // --- My Patients Page Logic ---
    // ===================================================================
    let allPatientsData = []; 

    async function loadMyPatients() {
        const tableBody = document.querySelector('#patients-table tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="5" class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading your patients...</td></tr>`;

        try {
            const response = await fetch('api.php?action=get_my_patients');
            const result = await response.json();

            tableBody.innerHTML = '';
            if (result.success && result.data.length > 0) {
                allPatientsData = result.data; 
                renderPatientsTable(allPatientsData);
            } else {
                tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; padding: 2rem;">No patients found.</td></tr>`;
            }
        } catch (error) {
            console.error("Error loading patients:", error);
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger-color);">Failed to load patient data.</td></tr>`;
        }
    }

    function renderPatientsTable(patientsToRender) {
        const tableBody = document.querySelector('#patients-table tbody');
        tableBody.innerHTML = '';
        patientsToRender.forEach(patient => {
            const statusClass = patient.status.toLowerCase().replace(' ', '-');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="Patient ID">${patient.display_user_id}</td>
                <td data-label="Name">${patient.name}</td>
                <td data-label="Status"><span class="status ${statusClass}">${patient.status}</span></td>
                <td data-label="Room/Bed">${patient.room_bed}</td>
                <td data-label="Actions">
                    <button class="action-btn view-record" data-id="${patient.id}"><i class="fas fa-file-medical"></i> View Record</button>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    }

    function filterPatients() {
        const searchTerm = document.getElementById('patient-search').value.toLowerCase();
        const statusFilter = document.getElementById('patient-status-filter').value;

        const filteredPatients = allPatientsData.filter(patient => {
            const nameMatch = patient.name.toLowerCase().includes(searchTerm) || patient.display_user_id.toLowerCase().includes(searchTerm);
            const statusMatch = (statusFilter === 'all') || (patient.status.toLowerCase().replace(' ', '-') === statusFilter);
            return nameMatch && statusMatch;
        });

        renderPatientsTable(filteredPatients);
    }

    // ===================================================================
    // --- Lab Orders Page Logic ---
    // ===================================================================
    let allLabOrdersData = [];

    function renderLabOrdersTable(ordersToRender) {
        const tableBody = document.querySelector('#lab-orders-table tbody');
        tableBody.innerHTML = '';

        if (ordersToRender.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No matching lab orders found.</td></tr>`;
            return;
        }

        ordersToRender.forEach(order => {
           const tr = document.createElement('tr');
           let actionButtonHtml = `<button class="action-btn" disabled><i class="fas fa-spinner"></i> In Progress</button>`;
           if (order.status.toLowerCase() === 'completed') {
               actionButtonHtml = `<button class="action-btn view-lab-report" data-id="${order.id}"><i class="fas fa-file-alt"></i> View Report</button>`;
           }

           tr.innerHTML = `
               <td data-label="Order ID">ORD-${order.id}</td>
               <td data-label="Patient">${order.patient_name}</td>
               <td data-label="Test Name">${order.test_name}</td>
               <td data-label="Order Date">${new Date(order.ordered_at).toLocaleDateString()}</td>
               <td data-label="Status"><span class="status ${order.status.toLowerCase()}">${order.status}</span></td>
               <td data-label="Actions">${actionButtonHtml}</td>
           `;
           tableBody.appendChild(tr);
        });
    }

    async function loadLabOrders() {
        const tableBody = document.querySelector('#lab-orders-table tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="6" class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading lab orders...</td></tr>`;

        try {
            const response = await fetch('api.php?action=get_lab_orders');
            const result = await response.json();

            if (result.success) {
                allLabOrdersData = result.data;
                renderLabOrdersTable(allLabOrdersData);
            } else {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No lab orders found.</td></tr>`;
            }
        } catch (error) {
            console.error("Error loading lab orders: ", error);
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">Failed to load lab orders.</td></tr>`;
        }
    }
    
    function filterLabOrders() {
        const searchTerm = document.getElementById('lab-search').value.toLowerCase();
        const statusFilter = document.getElementById('lab-status-filter').value;

        const filteredData = allLabOrdersData.filter(order => {
            const searchMatch = order.patient_name.toLowerCase().includes(searchTerm) ||
                                order.test_name.toLowerCase().includes(searchTerm);
            
            const statusMatch = (statusFilter === 'all') || (order.status.toLowerCase() === statusFilter);

            return searchMatch && statusMatch;
        });

        renderLabOrdersTable(filteredData);
    }

    const labSearchInput = document.getElementById('lab-search');
    const labStatusFilter = document.getElementById('lab-status-filter');
    if (labSearchInput) labSearchInput.addEventListener('input', filterLabOrders);
    if (labStatusFilter) labStatusFilter.addEventListener('change', filterLabOrders);


    // --- Place New Lab Order Modal Logic ---
    const placeLabOrderBtn = document.getElementById('place-lab-order-btn');
    if (placeLabOrderBtn) {
        const testRowsContainer = document.getElementById('test-rows-container');
        const addTestRowBtn = document.getElementById('add-test-row-btn');
        let patientSearchTimeout;

        function addTestRow() {
            const row = document.createElement('div');
            row.className = 'medication-row'; 
            row.innerHTML = `
                <div class="form-group" style="flex-grow: 1;">
                    <label>Test Name</label>
                    <input type="text" class="test-name-input" placeholder="e.g., Complete Blood Count" required>
                </div>
                <button type="button" class="remove-med-row-btn">&times;</button>
            `;
            testRowsContainer.appendChild(row);
        }

        placeLabOrderBtn.addEventListener('click', () => {
            const form = document.getElementById('lab-order-form');
            form.reset();
            testRowsContainer.innerHTML = '';
            addTestRow();
            
            document.getElementById('lab-order-patient-search').value = '';
            document.getElementById('lab-order-patient-id').value = '';
            document.getElementById('lab-order-patient-results').innerHTML = '';
            document.getElementById('lab-order-patient-results').classList.remove('active');

            openModalById('lab-order-modal-overlay');
        });
        
        const labPatientSearchInput = document.getElementById('lab-order-patient-search');
        const labPatientResultsDiv = document.getElementById('lab-order-patient-results');
        const labPatientIdInput = document.getElementById('lab-order-patient-id');

        if (labPatientSearchInput) {
            labPatientSearchInput.addEventListener('input', () => {
                const searchTerm = labPatientSearchInput.value.trim();
                labPatientResultsDiv.innerHTML = '';
                labPatientResultsDiv.classList.remove('active');
                
                if (searchTerm.length < 2) return;

                clearTimeout(patientSearchTimeout);
                patientSearchTimeout = setTimeout(async () => {
                    const response = await fetch(`api.php?action=search_patients&term=${encodeURIComponent(searchTerm)}`);
                    const result = await response.json();

                    if (result.success && result.data.length > 0) {
                        result.data.forEach(patient => {
                            const item = document.createElement('div');
                            item.className = 'search-result-item';
                            item.textContent = `${patient.name} (${patient.display_user_id})`;
                            item.dataset.patientId = patient.id;
                            item.dataset.patientName = patient.name;
                            labPatientResultsDiv.appendChild(item);
                        });
                        labPatientResultsDiv.classList.add('active');
                    }
                }, 300);
            });

            labPatientResultsDiv.addEventListener('click', (e) => {
                if (e.target.classList.contains('search-result-item')) {
                    labPatientIdInput.value = e.target.dataset.patientId;
                    labPatientSearchInput.value = e.target.dataset.patientName;
                    labPatientResultsDiv.classList.remove('active');
                }
            });
        }


        if (addTestRowBtn) addTestRowBtn.addEventListener('click', addTestRow);

        if (testRowsContainer) {
            testRowsContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('remove-med-row-btn')) {
                    e.target.closest('.medication-row').remove();
                    if (testRowsContainer.children.length === 0) {
                        addTestRow();
                    }
                }
            });
        }
        
        const labOrderForm = document.getElementById('lab-order-form');
        if (labOrderForm) {
            labOrderForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const patientId = document.getElementById('lab-order-patient-id').value;
                const testNameInputs = document.querySelectorAll('.test-name-input');
                const testNames = Array.from(testNameInputs).map(input => input.value.trim()).filter(name => name);
        
                if (!patientId || testNames.length === 0) {
                    alert('Please select a patient and enter at least one test name.');
                    return;
                }
        
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create_lab_order',
                        patient_id: patientId,
                        test_names: testNames,
                        encounter_id: document.getElementById('encounter-id').value
                    })
                });
                const result = await response.json();
        
                if (result.success) {
                    alert(result.message);
                    document.getElementById('lab-order-modal-overlay').classList.remove('active');
                    loadLabOrders();
                } else {
                    alert(`Error: ${result.message}`);
                }
            });
        }
    }
    
    // ===================================================================
    // --- Admissions Page Logic ---
    // ===================================================================
    let allAdmissionsData = [];

    async function loadAdmissions() {
        const tableBody = document.querySelector('#admissions-table tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="6" class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading admissions...</td></tr>`;

        try {
            const response = await fetch('api.php?action=get_admissions');
            const result = await response.json();

            if (result.success) {
                allAdmissionsData = result.data;
                renderAdmissionsTable(allAdmissionsData);
            } else {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">${result.message || 'No admissions found.'}</td></tr>`;
            }
        } catch (error) {
            console.error("Error loading admissions:", error);
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">Failed to load admissions data.</td></tr>`;
        }
    }
    
    // --- MODIFIED: Added an "Initiate Discharge" button for active patients ---
    function renderAdmissionsTable(admissionsToRender) {
        const tableBody = document.querySelector('#admissions-table tbody');
        tableBody.innerHTML = '';
        if (admissionsToRender.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No admissions found.</td></tr>`;
            return;
        }

        admissionsToRender.forEach(adm => {
            const tr = document.createElement('tr');
            const admissionDate = new Date(adm.admission_date).toLocaleString();
            
            let actionsHtml = `<button class="action-btn view-record" data-id="${adm.patient_id}"><i class="fas fa-file-medical"></i> View Record</button>`;
            
            // If the patient is active (not discharged), add the initiate discharge button
            if (adm.status === 'Active') {
                actionsHtml += `<button class="action-btn initiate-discharge-btn" data-id="${adm.id}"><i class="fas fa-sign-out-alt"></i> Initiate Discharge</button>`;
            }

            tr.innerHTML = `
                <td data-label="Adm. ID">ADM-${adm.id}</td>
                <td data-label="Patient Name">${adm.patient_name} (${adm.display_user_id})</td>
                <td data-label="Room/Bed">${adm.room_bed}</td>
                <td data-label="Adm. Date">${admissionDate}</td>
                <td data-label="Status"><span class="status ${adm.status === 'Active' ? 'in-patient' : 'completed'}">${adm.status}</span></td>
                <td data-label="Actions">${actionsHtml}</td>
            `;
            tableBody.appendChild(tr);
        });
    }

    async function openAdmitPatientModal() {
        const form = document.getElementById('admit-patient-form');
        form.reset();
    
        document.getElementById('patient-search-admit').value = '';
        document.getElementById('patient-id-admit').value = '';
        const resultsDiv = document.getElementById('patient-search-results-admit');
        resultsDiv.innerHTML = '';
        resultsDiv.classList.remove('active');
    
        const bedSelect = document.getElementById('bed-select-admit');
        bedSelect.innerHTML = '<option value="">Loading beds...</option>';
        
        openModalById('admit-patient-modal-overlay');
    
        try {
            const bedsResponse = await fetch('api.php?action=get_available_accommodations');
            const bedsResult = await bedsResponse.json();
            if (bedsResult.success) {
                bedSelect.innerHTML = '<option value="">-- Select an available bed --</option>';
                bedsResult.data.forEach(bed => {
                    bedSelect.innerHTML += `<option value="${bed.id}">${bed.identifier}</option>`;
                });
            } else {
                 bedSelect.innerHTML = '<option value="">Could not load beds</option>';
            }
        } catch (error) {
            console.error('Failed to populate modal beds:', error);
            bedSelect.innerHTML = '<option value="">Error loading beds</option>';
        }
    }
    
    // ===================================================================
    // --- Prescriptions Page Logic ---
    // ===================================================================
    let allPrescriptionsData = [];

    async function loadPrescriptions() {
        const tableBody = document.querySelector('#prescriptions-table tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="5" class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading prescriptions...</td></tr>`;

        try {
            const response = await fetch('api.php?action=get_prescriptions');
            const result = await response.json();

            if (result.success) {
                allPrescriptionsData = result.data;
                renderPrescriptionsTable(allPrescriptionsData);
            } else {
                tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">No prescriptions found.</td></tr>`;
            }
        } catch (error) {
            console.error("Error loading prescriptions:", error);
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--danger-color);">Failed to load data.</td></tr>`;
        }
    }

    function renderPrescriptionsTable(prescriptionsToRender) {
        const tableBody = document.querySelector('#prescriptions-table tbody');
        tableBody.innerHTML = '';
        if (prescriptionsToRender.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">No matching prescriptions found.</td></tr>`;
            return;
        }

        prescriptionsToRender.forEach(rx => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td data-label="Rx ID">RX-${rx.id}</td>
                <td data-label="Patient">${rx.patient_name}</td>
                <td data-label="Date Issued">${rx.prescription_date}</td>
                <td data-label="Status"><span class="status ${rx.status.toLowerCase()}">${rx.status}</span></td>
                <td data-label="Actions">
                    <button class="action-btn view-prescription" data-id="${rx.id}"><i class="fas fa-eye"></i> View</button>
                    <button class="action-btn print-prescription" data-id="${rx.id}"><i class="fas fa-print"></i> Print</button>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    }

    async function openViewPrescriptionModal(prescriptionId) {
        try {
            const response = await fetch(`api.php?action=get_prescription_details&id=${prescriptionId}`);
            const result = await response.json();

            if (result.success) {
                const rx = result.data;
                
                document.getElementById('rx-patient-name').textContent = rx.patient_name;
                document.getElementById('rx-patient-id').textContent = rx.patient_display_id;
                document.getElementById('rx-date').textContent = new Date(rx.prescription_date).toLocaleDateString();
                document.getElementById('rx-notes-content').textContent = rx.notes || 'No specific notes provided.';

                const medicationList = document.getElementById('rx-medication-list');
                medicationList.innerHTML = '';
                
                if (rx.items.length > 0) {
                    rx.items.forEach(item => {
                        const row = `
                            <tr>
                                <td>
                                    <div class="med-name">${item.name}</div>
                                    <div class="med-details">${item.dosage} - ${item.frequency} (Qty: ${item.quantity_prescribed})</div>
                                </td>
                            </tr>
                        `;
                        medicationList.innerHTML += row;
                    });
                } else {
                    medicationList.innerHTML = '<tr><td>No medications listed.</td></tr>';
                }

                document.getElementById('print-prescription-btn').dataset.id = prescriptionId;

                openModalById('prescription-view-modal-overlay');

            } else {
                alert(`Error: ${result.message}`);
            }
        } catch (error) {
            console.error('Failed to fetch prescription details:', error);
            alert('Could not load prescription details.');
        }
    }

    function filterPrescriptions() {
        const searchTerm = document.getElementById('prescription-search').value.toLowerCase();
        const dateFilter = document.getElementById('prescription-date-filter').value;

        const filteredData = allPrescriptionsData.filter(rx => {
            const searchMatch = rx.patient_name.toLowerCase().includes(searchTerm) ||
                                `rx-${rx.id}`.includes(searchTerm.toLowerCase());
            
            const dateMatch = (dateFilter === '') || (rx.prescription_date === dateFilter);

            return searchMatch && dateMatch;
        });

        renderPrescriptionsTable(filteredData);
    }
    
    const createPrescriptionBtn = document.getElementById('create-prescription-btn');
    if (createPrescriptionBtn) {
        const rowsContainer = document.getElementById('medication-rows-container');
        const addRowBtn = document.getElementById('add-medication-row-btn');
        let searchTimeout;
    
        function createMedicationRowHtml() {
            return `
                <div class="medication-row">
                    <input type="hidden" class="medicine-id-input">
                    <div class="form-group med-search-group">
                        <label>Medication</label>
                        <input type="text" class="medicine-name-search" placeholder="Type to search..." autocomplete="off" required>
                        <div class="search-results-dropdown"></div>
                    </div>
                    <div class="form-group dosage-group">
                        <label>Dosage</label>
                        <input type="text" class="dosage-input" placeholder="e.g., 500mg" required>
                    </div>
                    <div class="form-group frequency-group">
                        <label>Frequency</label>
                        <input type="text" class="frequency-input" placeholder="e.g., Twice a day" required>
                    </div>
                    <div class="form-group quantity-group">
                        <label>Qty</label>
                        <input type="number" class="quantity-input" value="1" min="1" required>
                    </div>
                    <button type="button" class="remove-med-row-btn">&times;</button>
                </div>
            `;
        }
    
        function addMedicationRow() {
            if (rowsContainer) rowsContainer.insertAdjacentHTML('beforeend', createMedicationRowHtml());
        }
    
        createPrescriptionBtn.addEventListener('click', async () => {
            const form = document.getElementById('prescription-form');
            form.reset();
            if (rowsContainer) rowsContainer.innerHTML = ''; 
            addMedicationRow();
            
            document.getElementById('patient-search-presc').value = '';
            document.getElementById('patient-id-presc').value = '';
            document.getElementById('patient-search-results-presc').innerHTML = '';
            document.getElementById('patient-search-results-presc').classList.remove('active');
            
            openModalById('prescription-modal-overlay');
        });
    
        if(addRowBtn) addRowBtn.addEventListener('click', addMedicationRow);
    
        if(rowsContainer) {
            rowsContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('remove-med-row-btn')) {
                    e.target.closest('.medication-row').remove();
                    if (rowsContainer.children.length === 0) {
                        addMedicationRow();
                    }
                }
        
                if (e.target.classList.contains('search-result-item')) {
                    const row = e.target.closest('.medication-row');
                    row.querySelector('.medicine-id-input').value = e.target.dataset.medId;
                    row.querySelector('.medicine-name-search').value = e.target.dataset.medName;
                    row.querySelector('.search-results-dropdown').classList.remove('active');
                }
            });
        
            rowsContainer.addEventListener('input', (e) => {
                if (e.target.classList.contains('medicine-name-search')) {
                    const inputField = e.target;
                    const resultsDropdown = inputField.nextElementSibling;
                    clearTimeout(searchTimeout);
        
                    const term = inputField.value.trim();
                    if (term.length < 2) {
                        resultsDropdown.classList.remove('active');
                        return;
                    }
        
                    searchTimeout = setTimeout(async () => {
                        const response = await fetch(`api.php?action=search_medicines&term=${encodeURIComponent(term)}`);
                        const result = await response.json();
                        resultsDropdown.innerHTML = '';
                        if (result.success && result.data.length > 0) {
                            result.data.forEach(med => {
                                const item = document.createElement('div');
                                item.className = 'search-result-item';
                                item.innerHTML = `${med.name} <small>(Stock: ${med.quantity})</small>`;
                                item.dataset.medId = med.id;
                                item.dataset.medName = med.name;
                                resultsDropdown.appendChild(item);
                            });
                            resultsDropdown.classList.add('active');
                        } else {
                            resultsDropdown.classList.remove('active');
                        }
                    }, 300);
                }
            });
        }
        
        const prescriptionForm = document.getElementById('prescription-form');
        if (prescriptionForm) {
            prescriptionForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const saveButton = document.getElementById('modal-save-btn-presc');
                
                const prescriptionData = {
                    patient_id: document.getElementById('patient-id-presc').value,
                    notes: document.getElementById('notes-presc').value,
                    encounter_id: document.getElementById('encounter-id').value,
                    items: []
                };
        
                document.querySelectorAll('.medication-row').forEach(row => {
                    const medicineId = row.querySelector('.medicine-id-input').value;
                    if (medicineId) {
                        prescriptionData.items.push({
                            medicine_id: medicineId,
                            dosage: row.querySelector('.dosage-input').value,
                            frequency: row.querySelector('.frequency-input').value,
                            quantity: row.querySelector('.quantity-input').value,
                        });
                    }
                });
        
                if (!prescriptionData.patient_id || prescriptionData.items.length === 0) {
                    alert('Please select a patient and add at least one valid medication.');
                    return;
                }
        
                saveButton.disabled = true;
                saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Saving...`;
        
                try {
                    const response = await fetch('api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'add_prescription', ...prescriptionData })
                    });
                    const result = await response.json();
                    if (result.success) {
                        alert(result.message);
                        document.getElementById('prescription-modal-overlay').classList.remove('active');
                        loadPrescriptions();
                    } else {
                        alert(`Error: ${result.message}`);
                    }
                } catch (error) {
                    console.error('Error creating prescription:', error);
                    alert('A network error occurred.');
                } finally {
                    saveButton.disabled = false;
                    saveButton.innerHTML = `Save Prescription`;
                }
            });

            const prescPatientSearch = document.getElementById('patient-search-presc');
            const prescPatientResults = document.getElementById('patient-search-results-presc');
            const prescPatientId = document.getElementById('patient-id-presc');
            
            if(prescPatientSearch) {
                prescPatientSearch.addEventListener('input', () => {
                     const term = prescPatientSearch.value.trim();
                     if (term.length < 2) {
                        prescPatientResults.classList.remove('active');
                        return;
                     }
                     clearTimeout(searchTimeout);
                     searchTimeout = setTimeout(async () => {
                        const res = await fetch(`api.php?action=search_patients&term=${encodeURIComponent(term)}`);
                        const result = await res.json();
                        prescPatientResults.innerHTML = '';
                        if(result.success && result.data.length > 0){
                            result.data.forEach(p => {
                                const item = document.createElement('div');
                                item.className = 'search-result-item';
                                item.textContent = `${p.name} (${p.display_user_id})`;
                                item.dataset.patientId = p.id;
                                item.dataset.patientName = p.name;
                                prescPatientResults.appendChild(item);
                            });
                            prescPatientResults.classList.add('active');
                        }
                     }, 300);
                });

                prescPatientResults.addEventListener('click', (e) => {
                    if(e.target.classList.contains('search-result-item')) {
                        prescPatientId.value = e.target.dataset.patientId;
                        prescPatientSearch.value = e.target.dataset.patientName;
                        prescPatientResults.classList.remove('active');
                    }
                });
            }
        }
    }


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
            const pageTitle = link.querySelector('i').nextSibling.textContent.trim();
            mainHeaderTitle.textContent = pageTitle;
            
            pages.forEach(page => {
                page.classList.toggle('active', page.id === pageId + '-page');
            });

            navLinks.forEach(navLink => navLink.classList.remove('active'));
            link.classList.add('active');
            
            // Stop message refresh when leaving messenger
            if (pageId !== 'messenger') {
                stopMessageRefresh();
            }
            
            if (pageId === 'bed-management') {
                initializeOccupancyManagement();
            }
            if (pageId === 'appointments') { 
                loadAppointments('today'); 
            }
            if (pageId === 'patients') {
                loadMyPatients();
            }
            if (pageId === 'admissions') {
                loadAdmissions();
            }
            if (pageId === 'prescriptions') {
                loadPrescriptions();
            }
            if (pageId === 'labs') {
                loadLabOrders();
            }
            // --- MODIFIED: Load data when Discharge Requests page is opened ---
            if (pageId === 'discharge') {
                loadDischargeRequests();
            }
            if (pageId === 'notifications') {
                loadAllNotifications();
            }
            if (pageId === 'messenger') {
                initializeMessenger();
                updateUnreadBadge();
            }
            if (pageId === 'profile') {
                if(document.querySelector('.profile-tab-link[data-tab="audit-log"]').classList.contains('active')) {
                   loadAuditLog();
                }
            }

            if (window.innerWidth <= 992) {
                closeMenu();
            }
        });
    });

    // Update unread badge on page load and periodically
    if (typeof updateUnreadBadge === 'function') {
        updateUnreadBadge();
        setInterval(updateUnreadBadge, 30000); // Update every 30 seconds
    }

    hamburgerBtn.addEventListener('click', (e) => { e.stopPropagation(); toggleMenu(); });
    overlay.addEventListener('click', closeMenu);
    
    // --- PROFILE DROPDOWN TOGGLE ---
    const userProfileWidget = document.getElementById('user-profile-widget');
    const profileDropdown = document.getElementById('profile-dropdown');

    if (userProfileWidget && profileDropdown) {
        userProfileWidget.addEventListener('click', (e) => {
            e.stopPropagation();
            userProfileWidget.classList.toggle('active');
            // Close notification panel if open
            if (notificationPanel) notificationPanel.classList.remove('active');
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
    }
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                closeMenu();
            }
        }
        // Close notification panel when clicking outside
        if (notificationPanel && !notificationPanel.contains(e.target) && !notificationBell.contains(e.target)) {
            notificationPanel.classList.remove('active');
        }
        // Close profile dropdown when clicking outside
        if (userProfileWidget && !userProfileWidget.contains(e.target)) {
            userProfileWidget.classList.remove('active');
        }
    });
    
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

    // ===================================================================
    // --- Appointments Page Logic ---
    // ===================================================================
    async function loadAppointments(period = 'today') {
        const listContainer = document.querySelector(`#${period}-tab .appointment-list`);
        const dateFilter = document.getElementById('appointment-date-filter');
        if (!listContainer) return;

        listContainer.innerHTML = `<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading appointments...</div>`;

        let apiUrl = `api.php?action=get_appointments&period=${period}`;
        if (dateFilter.value) {
            apiUrl += `&date=${dateFilter.value}`;
        }

        try {
            const response = await fetch(apiUrl);
            const result = await response.json();

            if (result.success) {
                renderAppointments(result.data, listContainer);
            } else {
                listContainer.innerHTML = `<div class="message-placeholder" style="color: var(--danger-color);">${result.message || 'Failed to load appointments.'}</div>`;
            }
        } catch (error) {
            console.error(`Error loading ${period} appointments:`, error);
            listContainer.innerHTML = `<div class="message-placeholder" style="color: var(--danger-color);">Could not connect to the server.</div>`;
        }
    }

    const dateFilterInput = document.getElementById('appointment-date-filter');
    if(dateFilterInput) {
        dateFilterInput.addEventListener('change', () => {
            const activeTab = document.querySelector('.appointments-page .tab-link.active');
            if (activeTab) {
                loadAppointments(activeTab.dataset.tab);
            }
        });
    }

    function renderAppointments(appointments, container) {
        container.innerHTML = '';
        if (appointments.length === 0) {
            container.innerHTML = `<div class="message-placeholder" style="padding: 2rem; text-align: center;">No appointments found for this period.</div>`;
            return;
        }

        appointments.forEach(appt => {
            const appointmentDate = new Date(appt.appointment_date);
            const formattedDate = appointmentDate.toLocaleDateString('en-US', { month: 'long', day: 'numeric' });
            const formattedTime = appointmentDate.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });

            const item = document.createElement('div');
            item.className = 'appointment-item';
            item.innerHTML = `
                <div class="patient-info">
                    <div class="patient-name">${appt.patient_name} (${appt.patient_display_id})</div>
                    <div class="appointment-details">
                        <i class="fas fa-calendar-alt"></i> ${formattedDate} at ${formattedTime}
                    </div>
                </div>
                <div class="appointment-status">
                    <span class="status ${appt.status.toLowerCase()}">${appt.status}</span>
                </div>
                <div class="appointment-actions">
                    <button class="action-btn view-record" data-id="${appt.user_id}"><i class="fas fa-file-medical"></i> View Record</button>
                    ${appt.status.toLowerCase() === 'scheduled' ? 
                        `<button class="action-btn start-consultation-btn" 
                                 data-appointment-id="${appt.id}" 
                                 data-patient-name="${appt.patient_name}">
                            <i class="fas fa-play-circle"></i> Start
                        </button>` 
                    : ''}
                </div>
            `;
            container.appendChild(item);
        });
    }

    function openModalById(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('active');
    }
    
    document.querySelectorAll('.modal-close-btn, .btn-secondary[data-modal-id]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal-id');
            if (modalId) {
                document.getElementById(modalId).classList.remove('active');
            }
        });
    });

    // ===================================================================
    // --- View Lab Report Modal Logic ---
    // ===================================================================
    const labsPage = document.getElementById('labs-page');
    if (labsPage) {
        const table = document.getElementById('lab-orders-table');
        if (table) {
            table.addEventListener('click', function(e) {
                if (e.target.closest('.view-lab-report')) {
                    const button = e.target.closest('.view-lab-report');
                    const reportId = button.dataset.id;
                    openViewLabReportModal(reportId);
                }
            });
        }
    }

    async function openViewLabReportModal(reportId) {
        try {
            const response = await fetch(`api.php?action=get_lab_report_details&id=${reportId}`); // Fetches report data
            if (!response.ok) throw new Error('Network error');
            const result = await response.json();

            if (result.success) {
                const report = result.data;
                const modal = document.getElementById('lab-report-view-modal-overlay');
                
                const downloadPdfBtn = document.getElementById('download-lab-report-btn');
                const printBtn = document.getElementById('print-lab-report-btn');
                
                document.querySelector('#lab-report-view-title').textContent = `Laboratory Report for ${report.patient_name}`;
                
                document.getElementById('report-patient-name').textContent = report.patient_name || 'N/A';
                document.getElementById('report-patient-id').textContent = report.patient_display_id || 'N/A';
                document.getElementById('report-patient-age-gender').textContent = `${report.patient_age || 'N/A'} / ${report.patient_gender || 'N/A'}`;
                document.getElementById('report-test-name').textContent = report.test_name || 'N/A';
                document.getElementById('report-id').textContent = `ORD-${report.id}`;
                document.getElementById('report-reported-date').textContent = new Date(report.ordered_at).toLocaleString();
                document.getElementById('report-verified-by').textContent = report.staff_name || 'Pending Verification';
                document.getElementById('report-summary-text').textContent = (report.result_details && report.result_details.summary) ? report.result_details.summary : 'No summary provided.';
                
                const findingsBody = document.getElementById('report-findings-tbody');
                findingsBody.innerHTML = '';
                if (report.result_details && Array.isArray(report.result_details.findings) && report.result_details.findings.length > 0) {
                    report.result_details.findings.forEach(finding => {
                        let flag = '';
                        let resultClass = '';
                        
                        const resultValue = parseFloat(finding.result);
                        if (!isNaN(resultValue)) {
                            const rangeParts = (finding.range || '').split('-').map(p => parseFloat(p.trim()));
                            if (rangeParts.length === 2 && !isNaN(rangeParts[0]) && !isNaN(rangeParts[1])) {
                                if (resultValue < rangeParts[0]) {
                                    flag = 'L'; // Low
                                    resultClass = 'abnormal-low';
                                } else if (resultValue > rangeParts[1]) {
                                    flag = 'H'; // High
                                    resultClass = 'abnormal-high';
                                }
                            }
                        }

                        findingsBody.innerHTML += `
                            <tr class="${resultClass}">
                                <td>${finding.parameter || 'N/A'}</td>
                                <td><strong>${finding.result || 'N/A'}</strong></td>
                                <td>${finding.units || 'N/A'}</td>
                                <td>${finding.range || 'N/A'}</td>
                                <td class="flag">${flag}</td>
                            </tr>`;
                    });
                } else {
                    findingsBody.innerHTML = `<tr><td colspan="5" style="text-align: center;">No detailed findings were entered.</td></tr>`;
                }

                if (report.attachment_path) {
                    downloadPdfBtn.style.display = 'inline-flex';
                    downloadPdfBtn.disabled = false;
                    downloadPdfBtn.onclick = () => window.open(`../${report.attachment_path}`, '_blank');
                    printBtn.disabled = true;
                    printBtn.title = "Print the downloaded PDF instead";
                    printBtn.onclick = null;
                } else {
                    downloadPdfBtn.style.display = 'none';
                    downloadPdfBtn.disabled = true;
                    downloadPdfBtn.onclick = null;
                    printBtn.disabled = false;
                    printBtn.title = "Print this report";
                    // Add print functionality
                    printBtn.onclick = () => {
                        printLabReport();
                    };
                }
                
                openModalById('lab-report-view-modal-overlay');
            } else {
                alert(`Error: ${result.message}`);
            }
        } catch (error) {
            console.error('Error fetching report details:', error);
            alert('Could not fetch report details.');
        }
    }
    
    // ===================================================================
    // --- Print Lab Report Function ---
    // ===================================================================
    function printLabReport() {
        const modalBody = document.querySelector('#lab-report-view-modal-overlay .modal-body');
        if (!modalBody) {
            alert('Unable to print report. Please try again.');
            return;
        }

        // Create a new window for printing
        const printWindow = window.open('', '_blank');
        
        // Write the HTML structure with styles
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Laboratory Report - Print</title>
                <style>
                    * {
                        margin: 0;
                        padding: 0;
                        box-sizing: border-box;
                    }
                    
                    body {
                        font-family: Arial, sans-serif;
                        padding: 20px;
                        color: #333;
                    }
                    
                    .report-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 30px;
                        padding-bottom: 20px;
                        border-bottom: 2px solid #333;
                    }
                    
                    .report-header-logo {
                        width: 80px;
                        height: auto;
                    }
                    
                    .report-hospital-details {
                        text-align: right;
                    }
                    
                    .report-hospital-details strong {
                        font-size: 18px;
                        display: block;
                        margin-bottom: 5px;
                    }
                    
                    .report-hospital-details p {
                        margin: 2px 0;
                        font-size: 12px;
                    }
                    
                    h4 {
                        margin-top: 20px;
                        margin-bottom: 10px;
                        font-size: 16px;
                        color: #0066cc;
                    }
                    
                    .report-info-grid {
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 20px;
                        margin-bottom: 30px;
                    }
                    
                    .info-box {
                        border: 1px solid #ddd;
                        padding: 15px;
                        border-radius: 5px;
                    }
                    
                    .info-box-title {
                        font-size: 14px;
                        font-weight: bold;
                        margin-bottom: 10px;
                        color: #0066cc;
                        border-bottom: 1px solid #ddd;
                        padding-bottom: 5px;
                    }
                    
                    .info-box p {
                        margin: 8px 0;
                        font-size: 13px;
                    }
                    
                    .report-section {
                        margin-bottom: 25px;
                    }
                    
                    .report-section-title {
                        font-size: 15px;
                        font-weight: bold;
                        margin-bottom: 10px;
                        color: #0066cc;
                        border-bottom: 2px solid #0066cc;
                        padding-bottom: 5px;
                    }
                    
                    .findings-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 10px;
                    }
                    
                    .findings-table th,
                    .findings-table td {
                        border: 1px solid #ddd;
                        padding: 10px;
                        text-align: left;
                        font-size: 12px;
                    }
                    
                    .findings-table th {
                        background-color: #f5f5f5;
                        font-weight: bold;
                    }
                    
                    .findings-table .abnormal-low,
                    .findings-table .abnormal-high {
                        background-color: #fff3cd;
                    }
                    
                    .findings-table .flag {
                        font-weight: bold;
                        color: #d9534f;
                    }
                    
                    .report-summary {
                        padding: 15px;
                        background-color: #f9f9f9;
                        border-left: 4px solid #0066cc;
                        font-size: 13px;
                        line-height: 1.6;
                    }
                    
                    .report-signature-section {
                        margin-top: 40px;
                        text-align: center;
                        border-top: 2px solid #333;
                        padding-top: 20px;
                    }
                    
                    .signature-box {
                        margin-top: 20px;
                        text-align: right;
                        max-width: 300px;
                        margin-left: auto;
                    }
                    
                    .signature-box p {
                        margin: 5px 0;
                        font-size: 13px;
                    }
                    
                    @media print {
                        body {
                            padding: 10px;
                        }
                        
                        @page {
                            margin: 1cm;
                        }
                    }
                </style>
            </head>
            <body>
                ${modalBody.innerHTML}
            </body>
            </html>
        `);
        
        printWindow.document.close();
        
        // Wait for content to load before printing
        printWindow.onload = function() {
            printWindow.focus();
            printWindow.print();
            // Close window after print dialog is dismissed
            printWindow.onafterprint = function() {
                printWindow.close();
            };
        };
    }
    
    // ===================================================================
    // --- Messenger Page Logic ---
    // ===================================================================
    const messengerPage = document.getElementById('messenger-page');
    let messengerInitialized = false;
    let activeConversation = { conversationId: null, otherUserId: null, otherUserName: null, otherUserPicture: null };
    let messageRefreshInterval = null;
    let lastMessageId = 0;

    function initializeMessenger() {
        if (messengerInitialized || !messengerPage) return;
        
        const conversationListEl = messengerPage.querySelector('.conversation-list');
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');
        const chatHeader = document.getElementById('chat-with-user');
        const messagesContainer = document.getElementById('chat-messages-container');

        loadConversations();
        updateUnreadBadge();

        messageForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const messageText = messageInput.value.trim();
            if (!messageText || !activeConversation.otherUserId) return;

            const originalButton = messageForm.querySelector('.send-btn');
            originalButton.disabled = true;
            originalButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`;
            
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('receiver_id', activeConversation.otherUserId);
            formData.append('message_text', messageText);

            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    messageInput.value = '';
                    renderMessage(result.data, true);
                    scrollToBottom(messagesContainer);
                    if (!activeConversation.conversationId) {
                         await loadConversations(result.data.conversation_id);
                    }
                    lastMessageId = result.data.id;
                } else {
                    showToast(result.message, 'error', 'Send Failed');
                }
            } catch (error) {
                console.error('Send message error:', error);
                showToast('Failed to send message.', 'error', 'Network Error');
            } finally {
                 originalButton.disabled = false;
                 originalButton.innerHTML = `<i class="fas fa-paper-plane"></i>`;
                 messageInput.focus();
            }
        });
        
        conversationListEl.addEventListener('click', (e) => {
            const conversationItem = e.target.closest('.conversation-item');
            if (!conversationItem) return;

            document.querySelectorAll('.conversation-item.active').forEach(item => item.classList.remove('active'));
            conversationItem.classList.add('active');

            const { conversationId, otherUserId, otherUserName, otherUserPicture, otherUserRole } = conversationItem.dataset;
            
            activeConversation = {
                conversationId: conversationId || null,
                otherUserId: parseInt(otherUserId),
                otherUserName: otherUserName,
                otherUserPicture: otherUserPicture || null,
                otherUserRole: otherUserRole || ''
            };
            
            updateChatHeader();
            
            if (conversationId) {
                loadMessages(conversationId);
                const unreadIndicator = conversationItem.querySelector('.unread-indicator');
                const unreadCount = conversationItem.querySelector('.unread-count');
                if (unreadIndicator) unreadIndicator.style.display = 'none';
                if (unreadCount) unreadCount.remove();
                updateUnreadBadge();
                startMessageRefresh();
            } else {
                messagesContainer.innerHTML = `<div class="message-placeholder">
                    <i class="fas fa-comments" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p>Start the conversation with ${otherUserName}.</p>
                </div>`;
                stopMessageRefresh();
            }
            messageInput.focus();
        });

        let searchTimeout;
        conversationListEl.addEventListener('input', (e) => {
            if (e.target.matches('.conversation-search input')) {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    const term = e.target.value.trim();
                    if (term.length > 1) {
                        searchUsers(term);
                    } else if (term.length === 0) {
                        loadConversations();
                    }
                }, 300);
            }
        });

        messengerInitialized = true;
    }

    async function loadConversations(selectConversationId = null) {
        const listItemsContainer = document.getElementById('conversation-list-items');
        if (!listItemsContainer) return;

        listItemsContainer.innerHTML = `<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading...</div>`;

        try {
            const response = await fetch('api.php?action=get_conversations');
            const result = await response.json();
            
            listItemsContainer.innerHTML = '';

            if (result.success && result.data.length > 0) {
                result.data.forEach(convo => renderConversation(convo));
                
                if (selectConversationId) {
                    const newConvoEl = listItemsContainer.querySelector(`.conversation-item[data-conversation-id='${selectConversationId}']`);
                    if (newConvoEl) newConvoEl.click();
                }

            } else {
                listItemsContainer.innerHTML = `<div style="padding: 2rem; text-align: center;">
                    <i class="fas fa-inbox" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p style="color: var(--text-muted);">No conversations yet.</p>
                    <p style="color: var(--text-muted); font-size: 0.85rem;">Search for users to start chatting!</p>
                </div>`;
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
            listItemsContainer.innerHTML = '<p style="padding: 1rem; text-align: center; color: var(--danger-color);">Could not load conversations.</p>';
        }
    }

    function renderConversation(convo) {
        const listItemsContainer = document.getElementById('conversation-list-items');
        if (!listItemsContainer) return;

        const lastMessageTime = convo.last_message_time ? formatMessageTime(new Date(convo.last_message_time)) : '';
        
        // Generate avatar HTML
        let avatarHtml = '';
        if (convo.other_user_profile_picture) {
            avatarHtml = `<img src="../uploads/profile_pictures/${convo.other_user_profile_picture}" alt="${convo.other_user_name}">`;
        } else {
            const initials = getInitials(convo.other_user_name);
            avatarHtml = `<span>${initials}</span>`;
        }
        
        // Capitalize role name
        const roleName = convo.other_user_role ? convo.other_user_role.charAt(0).toUpperCase() + convo.other_user_role.slice(1) : '';
        
        const conversationHtml = `
            <div class="conversation-item" 
                data-conversation-id="${convo.conversation_id}" 
                data-other-user-id="${convo.other_user_id}"
                data-other-user-name="${convo.other_user_name}"
                data-other-user-picture="${convo.other_user_profile_picture || ''}"
                data-other-user-role="${roleName}">
                <div class="user-avatar">${avatarHtml}</div>
                <div class="user-details">
                    <div class="user-name">${convo.other_user_name}</div>
                    <div class="last-message">${convo.last_message || 'No messages yet.'}</div>
                </div>
                <div class="message-meta">
                    <div class="message-time">${lastMessageTime}</div>
                    ${convo.unread_count > 0 ? `<span class="unread-count">${convo.unread_count}</span>` : ''}
                </div>
            </div>
        `;
        listItemsContainer.insertAdjacentHTML('beforeend', conversationHtml);
    }
    
    async function loadMessages(conversationId) {
        const messagesContainer = document.getElementById('chat-messages-container');
        messagesContainer.innerHTML = `<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>`;

        try {
            const response = await fetch(`api.php?action=get_messages&conversation_id=${conversationId}`);
            const result = await response.json();
            messagesContainer.innerHTML = '';

            if (result.success && result.data.length > 0) {
                renderMessagesWithDates(result.data);
                scrollToBottom(messagesContainer);
                lastMessageId = result.data[result.data.length - 1].id;
            } else {
                messagesContainer.innerHTML = `<div class="message-placeholder">
                    <i class="fas fa-comments" style="font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p>No messages in this conversation yet.</p>
                    <p style="font-size: 0.85rem;">Send a message to start chatting!</p>
                </div>`;
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            messagesContainer.innerHTML = '<div class="message-placeholder" style="color: var(--danger-color);">Failed to load messages.</div>';
        }
    }

    function renderMessagesWithDates(messages) {
        const messagesContainer = document.getElementById('chat-messages-container');
        let lastDate = null;
        let lastSenderId = null;
        
        messages.forEach((msg, index) => {
            const msgDate = new Date(msg.created_at);
            const dateStr = formatDate(msgDate);
            
            // Add date separator if date changed
            if (dateStr !== lastDate) {
                const separatorHtml = `<div class="date-separator"><span>${dateStr}</span></div>`;
                messagesContainer.insertAdjacentHTML('beforeend', separatorHtml);
                lastDate = dateStr;
                lastSenderId = null; // Reset sender grouping on new date
            }
            
            // Check if this message should be grouped with previous
            const isGrouped = lastSenderId === msg.sender_id && index > 0;
            renderMessage(msg, false, isGrouped);
            lastSenderId = msg.sender_id;
        });
    }

    function renderMessage(msg, isNew = false, isGrouped = false) {
        const messagesContainer = document.getElementById('chat-messages-container');
        const messageType = msg.sender_id == currentUserId ? 'sent' : 'received';
        const timestamp = formatMessageTime(new Date(msg.created_at));
        const fullTimestamp = new Date(msg.created_at).toLocaleString();

        const sanitizedText = document.createElement('p');
        sanitizedText.textContent = msg.message_text;

        const groupedClass = isGrouped ? 'grouped' : '';
        
        const messageHtml = `
            <div class="message ${messageType} ${groupedClass}" data-message-id="${msg.id}">
                <div class="message-content">
                    ${sanitizedText.outerHTML}
                    <span class="message-timestamp" title="${fullTimestamp}">${timestamp}</span>
                </div>
                ${messageType === 'sent' ? `
                <div class="message-actions">
                    <button class="message-action-btn copy-message" title="Copy message">
                        <i class="fas fa-copy"></i>
                    </button>
                    <button class="message-action-btn delete-message" title="Delete message">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>` : `
                <div class="message-actions">
                    <button class="message-action-btn copy-message" title="Copy message">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>`}
            </div>
        `;
        messagesContainer.insertAdjacentHTML('beforeend', messageHtml);
        
        // Add event listeners for message actions
        const messageEl = messagesContainer.lastElementChild;
        const copyBtn = messageEl.querySelector('.copy-message');
        const deleteBtn = messageEl.querySelector('.delete-message');
        
        if (copyBtn) {
            copyBtn.addEventListener('click', () => copyMessageText(msg.message_text));
        }
        
        if (deleteBtn) {
            deleteBtn.addEventListener('click', () => deleteMessage(msg.id));
        }
        
        if(isNew) scrollToBottom(messagesContainer);
    }
    
    async function searchUsers(term) {
        const listItemsContainer = document.getElementById('conversation-list-items');
        if (!listItemsContainer) return;

        listItemsContainer.innerHTML = `<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Searching...</div>`;

        try {
            const response = await fetch(`api.php?action=searchUsers&term=${encodeURIComponent(term)}`);
            const result = await response.json();
            
            listItemsContainer.innerHTML = '';

            if (result.success && result.data.length > 0) {
                result.data.forEach(user => {
                    // Generate avatar HTML
                    let avatarHtml = '';
                    if (user.profile_picture) {
                        avatarHtml = `<img src="../uploads/profile_pictures/${user.profile_picture}" alt="${user.name}">`;
                    } else {
                        const initials = getInitials(user.name);
                        avatarHtml = `<span>${initials}</span>`;
                    }
                    
                    const roleName = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : '';
                    
                    const searchResultHtml = `
                        <div class="conversation-item" 
                            data-conversation-id="${user.conversation_id || ''}"
                            data-other-user-id="${user.id}"
                            data-other-user-name="${user.name}"
                            data-other-user-picture="${user.profile_picture || ''}"
                            data-other-user-role="${roleName}">
                            <div class="user-avatar">${avatarHtml}</div>
                            <div class="user-details">
                                <div class="user-name">${user.name} <small>(${roleName})</small></div>
                                <div class="last-message">${user.conversation_id ? '' : 'Click to start a new conversation.'}</div>
                            </div>
                        </div>
                    `;
                    listItemsContainer.insertAdjacentHTML('beforeend', searchResultHtml);
                });
            } else {
                listItemsContainer.innerHTML = `<div style="padding: 2rem; text-align: center;">
                    <i class="fas fa-user-slash" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 1rem;"></i>
                    <p style="color: var(--text-muted);">No users found.</p>
                </div>`;
            }
        } catch (error) {
            console.error('Error searching users:', error);
            listItemsContainer.innerHTML = '<p style="padding: 1rem; text-align: center; color: var(--danger-color);">Search failed.</p>';
        }
    }

    // Auto-refresh messages
    function startMessageRefresh() {
        stopMessageRefresh();
        messageRefreshInterval = setInterval(async () => {
            if (activeConversation.conversationId) {
                await checkNewMessages();
            }
        }, 5000); // Check every 5 seconds
    }

    function stopMessageRefresh() {
        if (messageRefreshInterval) {
            clearInterval(messageRefreshInterval);
            messageRefreshInterval = null;
        }
    }

    async function checkNewMessages() {
        if (!activeConversation.conversationId) return;
        
        try {
            const response = await fetch(`api.php?action=get_messages&conversation_id=${activeConversation.conversationId}&after_id=${lastMessageId}`);
            const result = await response.json();
            
            if (result.success && result.data.length > 0) {
                const messagesContainer = document.getElementById('chat-messages-container');
                const wasAtBottom = isScrolledToBottom(messagesContainer);
                
                result.data.forEach(msg => {
                    renderMessage(msg, true);
                    lastMessageId = msg.id;
                });
                
                if (wasAtBottom) {
                    scrollToBottom(messagesContainer);
                }
                
                // Update conversation list
                loadConversations(activeConversation.conversationId);
            }
        } catch (error) {
            console.error('Error checking new messages:', error);
        }
    }

    // Message actions
    function copyMessageText(text) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('Message copied to clipboard!', 'success', 'Copied');
        }).catch(() => {
            showToast('Failed to copy message.', 'error', 'Copy Failed');
        });
    }

    async function deleteMessage(messageId) {
        if (!confirm('Are you sure you want to delete this message?')) return;
        
        try {
            const formData = new FormData();
            formData.append('action', 'delete_message');
            formData.append('message_id', messageId);
            
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (result.success) {
                const messageEl = document.querySelector(`.message[data-message-id="${messageId}"]`);
                if (messageEl) {
                    messageEl.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => messageEl.remove(), 300);
                }
                showToast('Message deleted successfully.', 'success', 'Deleted');
            } else {
                showToast(result.message, 'error', 'Delete Failed');
            }
        } catch (error) {
            console.error('Error deleting message:', error);
            showToast('Failed to delete message.', 'error', 'Error');
        }
    }

    // Update chat header with avatar
    function updateChatHeader() {
        const chatHeader = document.getElementById('chat-with-user');
        
        let avatarHtml = '';
        if (activeConversation.otherUserPicture) {
            avatarHtml = `<img src="../uploads/profile_pictures/${activeConversation.otherUserPicture}" alt="${activeConversation.otherUserName}">`;
        } else {
            const initials = getInitials(activeConversation.otherUserName);
            avatarHtml = `<span>${initials}</span>`;
        }
        
        chatHeader.innerHTML = `
            <div class="chat-header-user">
                <div class="user-avatar">${avatarHtml}</div>
                <div>
                    <div style="font-weight: 600;">${activeConversation.otherUserName}</div>
                    <div style="font-size: 0.85rem; color: var(--text-muted);">${activeConversation.otherUserRole || ''}</div>
                </div>
            </div>
        `;
    }

    // Update unread badge in sidebar
    async function updateUnreadBadge() {
        try {
            const response = await fetch('api.php?action=get_unread_count');
            const result = await response.json();
            
            if (result.success) {
                const badge = document.querySelector('.nav-item[data-page="messenger"] .notification-badge');
                if (result.count > 0) {
                    if (badge) {
                        badge.textContent = result.count;
                        badge.style.display = 'flex';
                    }
                } else {
                    if (badge) {
                        badge.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Error updating unread badge:', error);
        }
    }

    // Utility functions
    function getInitials(name) {
        return name
            .split(' ')
            .map(word => word[0])
            .join('')
            .toUpperCase()
            .substring(0, 2);
    }

    function formatDate(date) {
        const today = new Date();
        const yesterday = new Date(today);
        yesterday.setDate(yesterday.getDate() - 1);
        
        if (date.toDateString() === today.toDateString()) {
            return 'Today';
        } else if (date.toDateString() === yesterday.toDateString()) {
            return 'Yesterday';
        } else if (date.getFullYear() === today.getFullYear()) {
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        } else {
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    }

    function formatMessageTime(date) {
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMins / 60);
        const diffDays = Math.floor(diffHours / 24);
        
        if (diffMins < 1) {
            return 'Just now';
        } else if (diffMins < 60) {
            return `${diffMins}m ago`;
        } else if (diffHours < 24) {
            return `${diffHours}h ago`;
        } else if (diffDays < 7) {
            return `${diffDays}d ago`;
        } else {
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }
    }

    function scrollToBottom(element) {
        if(element) element.scrollTop = element.scrollHeight;
    }

    function isScrolledToBottom(element) {
        return element.scrollHeight - element.scrollTop <= element.clientHeight + 50;
    }

    // ===================================================================
    // --- Profile Settings Page Logic ---
    // ===================================================================
    
    // Toast Notification System
    function showToast(message, type = 'info', title = '') {
        const toastContainer = document.getElementById('toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        
        const titles = {
            success: title || 'Success',
            error: title || 'Error',
            warning: title || 'Warning',
            info: title || 'Information'
        };
        
        toast.innerHTML = `
            <div class="toast-icon">
                <i class="fas ${icons[type]}"></i>
            </div>
            <div class="toast-content">
                <div class="toast-title">${titles[type]}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        toastContainer.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
    
    // Password Strength Checker
    function checkPasswordStrength(password) {
        let strength = 0;
        const requirements = {
            length: password.length >= 8,
            uppercase: /[A-Z]/.test(password),
            lowercase: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
        };
        
        // Update requirement indicators
        Object.keys(requirements).forEach(key => {
            const element = document.getElementById(`req-${key}`);
            if (element) {
                const icon = element.querySelector('i');
                if (requirements[key]) {
                    element.classList.add('valid');
                    icon.classList.remove('fa-times-circle');
                    icon.classList.add('fa-check-circle');
                    strength++;
                } else {
                    element.classList.remove('valid');
                    icon.classList.remove('fa-check-circle');
                    icon.classList.add('fa-times-circle');
                }
            }
        });
        
        return { strength, requirements };
    }
    
    // Update password strength indicator
    function updatePasswordStrength(password) {
        const strengthBar = document.getElementById('password-strength-bar');
        const strengthText = document.getElementById('password-strength-text');
        
        if (!strengthBar || !strengthText) return;
        
        const { strength } = checkPasswordStrength(password);
        
        // Remove all classes
        strengthBar.classList.remove('weak', 'medium', 'strong');
        strengthText.classList.remove('weak', 'medium', 'strong');
        
        if (password.length === 0) {
            strengthBar.style.width = '0';
            strengthText.textContent = '';
            return;
        }
        
        if (strength <= 2) {
            strengthBar.classList.add('weak');
            strengthText.classList.add('weak');
            strengthText.textContent = 'Weak password';
        } else if (strength <= 4) {
            strengthBar.classList.add('medium');
            strengthText.classList.add('medium');
            strengthText.textContent = 'Medium strength';
        } else {
            strengthBar.classList.add('strong');
            strengthText.classList.add('strong');
            strengthText.textContent = 'Strong password';
        }
    }
    
    const personalInfoForm = document.getElementById('personal-info-form');
    if (personalInfoForm) {
        const profilePage = document.getElementById('profile-page');
        
        let profileDataLoaded = false;
        
        const loadProfileDropdowns = () => {
             if (profilePage.classList.contains('active') && !profileDataLoaded) {
                 profileDataLoaded = true;
                 fetch('api.php?action=get_doctor_details')
                     .then(res => res.json())
                     .then(result => {
                         if (result.success) {
                             populateSelectDropdown('api.php?action=get_specialities', 'profile-specialty', 'Select a Specialty', result.data.specialty_id);
                             populateSelectDropdown('api.php?action=get_departments', 'profile-department', 'Select a Department', result.data.department_id);
                             document.getElementById('profile-qualifications').value = result.data.qualifications || '';
                         }
                     }).catch(console.error);
             }
        };

        const profileNavLink = document.querySelector('.nav-link[data-page="profile"]');
        if (profileNavLink) {
            profileNavLink.addEventListener('click', () => {
                profileDataLoaded = false; 
                loadProfileDropdowns(); 
            });
        }
        
        const observer = new MutationObserver(loadProfileDropdowns);
        observer.observe(profilePage, { attributes: true, attributeFilter: ['class'] });

        personalInfoForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            let isFormValid = true;
            this.querySelectorAll('input[required], select[required]').forEach(field => {
                if (!validateField(field)) isFormValid = false;
            });
            if (!validateField(this.querySelector('#profile-phone'))) isFormValid = false;
            if (!validateField(this.querySelector('#profile-dob'))) isFormValid = false;
    
            if (!isFormValid) {
                alert('Please correct the errors before saving.');
                return;
            }
            
            const saveButton = this.querySelector('button[type="submit"]');
            const originalButtonText = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Saving...`;
    
            const formData = new FormData(this);
            formData.append('action', 'update_personal_info');
    
            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();
    
                if (result.success) {
                    showToast('Profile updated successfully!', 'success');
                    const newName = formData.get('name');
                    const specialtySelect = document.getElementById('profile-specialty');
                    const newSpecialty = specialtySelect.options[specialtySelect.selectedIndex].text;
                    
                    document.querySelector('.user-profile-widget .profile-info strong').textContent = `Dr. ${newName}`;
                    document.querySelector('.user-profile-widget .profile-info span').textContent = newSpecialty;
                    document.querySelector('.welcome-message h2').textContent = `Welcome back, Dr. ${newName}!`;
                } else {
                    showToast(result.message || 'An unknown error occurred.', 'error');
                }
            } catch (error) {
                console.error('Error updating profile:', error);
                showToast('A network error occurred. Please try again.', 'error');
            } finally {
                saveButton.disabled = false;
                saveButton.innerHTML = originalButtonText;
            }
        });
    }

    // --- Profile Picture Upload Functionality ---
    const profilePictureUpload = document.getElementById('profile-picture-upload');
    const removeProfilePictureBtn = document.getElementById('remove-profile-picture-btn');
    
    if (profilePictureUpload) {
        profilePictureUpload.addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Please upload a valid image file (JPEG, PNG, or GIF).');
                this.value = '';
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB.');
                this.value = '';
                return;
            }
            
            const formData = new FormData();
            formData.append('profile_picture', file);
            formData.append('action', 'updateProfilePicture');
            
            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success && result.new_image_url) {
                    const newUrl = `${result.new_image_url}?v=${new Date().getTime()}`;
                    document.querySelectorAll('.profile-picture, .editable-profile-picture').forEach(img => {
                        img.src = newUrl;
                    });
                    if (removeProfilePictureBtn) removeProfilePictureBtn.style.display = 'flex';
                    showToast('Profile picture updated successfully!', 'success');
                } else {
                    showToast(result.message || 'Failed to update profile picture.', 'error');
                }
            } catch (error) {
                console.error('Error uploading profile picture:', error);
                showToast('An error occurred while uploading the profile picture.', 'error');
            }
            
            this.value = '';
        });
    }
    
    if (removeProfilePictureBtn) {
        removeProfilePictureBtn.addEventListener('click', async function() {
            if (!confirm('Are you sure you want to remove your profile picture?')) return;
            
            const formData = new FormData();
            formData.append('action', 'removeProfilePicture');
            
            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    const defaultUrl = `../uploads/profile_pictures/default.png?v=${new Date().getTime()}`;
                    document.querySelectorAll('.profile-picture, .editable-profile-picture').forEach(img => {
                        img.src = defaultUrl;
                    });
                    this.style.display = 'none';
                    showToast('Profile picture removed successfully!', 'success');
                } else {
                    showToast(result.message || 'Failed to remove profile picture.', 'error');
                }
            } catch (error) {
                console.error('Error removing profile picture:', error);
                showToast('An error occurred while removing the profile picture.', 'error');
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
    const closeWebcamModalBtn = document.getElementById('close-webcam-modal');
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

        try {
            updateWebcamStatus('Uploading photo...', 'info');
            webcamUseBtn.disabled = true;
            
            const response = await fetch('api.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            if (!response.ok || !result.success) throw new Error(result.message || 'Upload failed');
            
            if (result.new_image_url) {
                const newUrl = `${result.new_image_url}?v=${new Date().getTime()}`;
                document.querySelectorAll('.profile-picture, .editable-profile-picture').forEach(img => {
                    img.src = newUrl;
                });
                if (removeProfilePictureBtn) removeProfilePictureBtn.style.display = 'flex';
                showToast('Profile picture updated successfully!', 'success');
            }
            
            closeWebcam();
        } catch (error) {
            updateWebcamStatus(error.message, 'error');
            webcamUseBtn.disabled = false;
        }
    };

    const closeWebcam = () => {
        if (webcamModal) {
            webcamModal.classList.remove('active');
            stopWebcam();
            capturedBlob = null;
        }
    };

    // Event Listeners for Webcam
    if (openWebcamBtn) {
        openWebcamBtn.addEventListener('click', () => {
            webcamModal.classList.add('active');
            startWebcam();
        });
    }

    if (closeWebcamModalBtn) closeWebcamModalBtn.addEventListener('click', closeWebcam);
    if (webcamCancelBtn) webcamCancelBtn.addEventListener('click', closeWebcam);
    if (webcamCaptureBtn) webcamCaptureBtn.addEventListener('click', capturePhoto);
    if (webcamRetakeBtn) webcamRetakeBtn.addEventListener('click', retakePhoto);
    if (webcamUseBtn) webcamUseBtn.addEventListener('click', uploadCapturedPhoto);

    // Close modal on outside click
    if (webcamModal) {
        webcamModal.addEventListener('click', (e) => {
            if (e.target === webcamModal) closeWebcam();
        });
    }

    const securityForm = document.getElementById('security-form');
    if (securityForm) {
        // Add password strength indicator
        const newPasswordInput = document.getElementById('new-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const confirmPasswordError = document.getElementById('confirm-password-error');
        
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                updatePasswordStrength(this.value);
            });
        }
        
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                const newPassword = newPasswordInput.value;
                if (this.value && this.value !== newPassword) {
                    confirmPasswordError.textContent = 'Passwords do not match';
                    confirmPasswordError.style.display = 'block';
                } else {
                    confirmPasswordError.textContent = '';
                    confirmPasswordError.style.display = 'none';
                }
            });
        }
        
        securityForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            // Validate password requirements
            const { requirements } = checkPasswordStrength(newPassword);
            const allRequirementsMet = Object.values(requirements).every(val => val === true);
            
            if (!allRequirementsMet) {
                showToast('Please meet all password requirements', 'error');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showToast('New password and confirmation do not match.', 'error');
                return;
            }

            const saveButton = this.querySelector('button[type="submit"]');
            const originalButtonText = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Updating...`;

            const formData = new FormData(this);
            formData.append('action', 'updatePassword');

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showToast('Password updated successfully!', 'success');
                    this.reset();
                    // Reset password strength indicator
                    const strengthBar = document.getElementById('password-strength-bar');
                    const strengthText = document.getElementById('password-strength-text');
                    if (strengthBar) strengthBar.style.width = '0';
                    if (strengthText) strengthText.textContent = '';
                    // Reset requirements
                    document.querySelectorAll('.password-requirements li').forEach(li => {
                        li.classList.remove('valid');
                        const icon = li.querySelector('i');
                        icon.classList.remove('fa-check-circle');
                        icon.classList.add('fa-times-circle');
                    });
                } else {
                    showToast(result.message || 'An unknown error occurred.', 'error');
                }
            } catch (error) {
                console.error('Error updating password:', error);
                showToast('A network error occurred. Please try again.', 'error');
            } finally {
                saveButton.disabled = false;
                saveButton.innerHTML = originalButtonText;
            }
        });
    }


    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', function() {
            const passwordInput = this.previousElementSibling;
            
            const isPassword = passwordInput.getAttribute('type') === 'password';
            
            if (isPassword) {
                passwordInput.setAttribute('type', 'text');
                this.classList.remove('fa-eye-slash');
                this.classList.add('fa-eye');
            } else {
                passwordInput.setAttribute('type', 'password');
                this.classList.remove('fa-eye');
                this.classList.add('fa-eye-slash');
            }
        });
    });

    const profilePageEl = document.getElementById('profile-page');
    if (profilePageEl) {
        const profileTabs = profilePageEl.querySelectorAll('.profile-tab-link');
        const profileTabContents = profilePageEl.querySelectorAll('.profile-tab-content');
        
        profileTabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const targetTab = this.dataset.tab;

                profileTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                profileTabContents.forEach(content => {
                    content.classList.toggle('active', content.id === `${targetTab}-tab`);
                });

                if (targetTab === 'audit-log') {
                    loadAuditLog();
                }
            });
        });
    }

    async function loadAuditLog() {
        const tableBody = document.querySelector('#audit-log-table tbody');
        if (!tableBody || tableBody.dataset.loaded === 'true') {
            return;
        }

        tableBody.innerHTML = `<tr><td colspan="4" class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading activity log...</td></tr>`;

        try {
            const response = await fetch('api.php?action=get_audit_log');
            if (!response.ok) throw new Error('Network response was not ok.');
            
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                tableBody.innerHTML = '';
                result.data.forEach(log => {
                    const tr = document.createElement('tr');
                    const logDate = new Date(log.created_at).toLocaleString('en-US', {
                        year: 'numeric', month: 'short', day: 'numeric', 
                        hour: '2-digit', minute: '2-digit', hour12: true
                    });

                    let actionClass = '';
                    const actionText = (log.action || '').toLowerCase();
                    if (actionText.includes('create') || actionText.includes('issued') || actionText.includes('added')) {
                        actionClass = 'log-action-create';
                    } else if (actionText.includes('update') || actionText.includes('initiated')) {
                        actionClass = 'log-action-update';
                    } else if (actionText.includes('view')) {
                        actionClass = 'log-action-view';
                    } else if (actionText.includes('login')) {
                        actionClass = 'log-action-auth';
                    }
                    
                    const targetText = log.target_user_name 
                        ? `${log.target_user_name} (${log.target_display_id})` 
                        : 'Self';

                    tr.innerHTML = `
                        <td data-label="Date & Time">${logDate}</td>
                        <td data-label="Action"><span class="${actionClass}">${log.action}</span></td>
                        <td data-label="Target">${targetText}</td>
                        <td data-label="Details">${log.details || 'N/A'}</td>
                    `;
                    tableBody.appendChild(tr);
                });
                tableBody.dataset.loaded = 'true';
            } else {
                tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 2rem;">No recent account activity found.</td></tr>`;
            }
        } catch (error) {
            console.error('Error fetching audit log:', error);
            tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center; color: var(--danger-color); padding: 2rem;">Failed to load activity log.</td></tr>`;
        }
    }

    // ===================================================================
    // --- Bed Management Page Logic ---
    // ===================================================================
    const occupancyPage = document.getElementById('bed-management-page');
    let allOccupancyData = []; 
    let isOccupancyManagerInitialized = false;

    async function initializeOccupancyManagement() {
        if (isOccupancyManagerInitialized) return;
        isOccupancyManagerInitialized = true;
        
        const gridContainer = document.getElementById('bed-grid-container');
        gridContainer.innerHTML = '<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading occupancy data...</div>';

        try {
            const [locationsRes, occupancyRes] = await Promise.all([
                fetch('api.php?action=get_locations'),
                fetch('api.php?action=get_occupancy_data')
            ]);

            if (!locationsRes.ok || !occupancyRes.ok) throw new Error('Failed to fetch management data.');

            const locationsData = await locationsRes.json();
            const occupancyData = await occupancyRes.json();
            
            if (locationsData.success) {
                populateLocationFilter(locationsData.data);
            }
            if (occupancyData.success) {
                allOccupancyData = occupancyData.data;
                renderLocations(allOccupancyData);
            } else {
                 gridContainer.innerHTML = `<div class="loading-placeholder">Error: ${occupancyData.message}</div>`;
            }
        } catch (error) {
            console.error('Error initializing occupancy management:', error);
            gridContainer.innerHTML = `<div class="loading-placeholder" style="color:var(--danger-color);">Error: Could not load data.</div>`;
        }
    }

    function populateLocationFilter(locations) {
        const filter = document.getElementById('bed-location-filter');
        filter.innerHTML = '<option value="all">All Wards & Rooms</option>';

        if (locations.wards && locations.wards.length > 0) {
            const wardGroup = document.createElement('optgroup');
            wardGroup.label = 'Wards';
            locations.wards.forEach(ward => {
                const option = document.createElement('option');
                option.value = `ward-${ward.id}`;
                option.textContent = ward.name;
                wardGroup.appendChild(option);
            });
            filter.appendChild(wardGroup);
        }

        if (locations.rooms && locations.rooms.length > 0) {
            const roomGroup = document.createElement('optgroup');
            roomGroup.label = 'Private Rooms';
            locations.rooms.forEach(room => {
                const option = document.createElement('option');
                option.value = `room-${room.id}`;
                option.textContent = `Room ${room.name}`;
                roomGroup.appendChild(option);
            });
            filter.appendChild(roomGroup);
        }
    }

    function renderLocations(locationsToRender) {
        const gridContainer = document.getElementById('bed-grid-container');
        gridContainer.innerHTML = '';

        if (locationsToRender.length === 0) {
            gridContainer.innerHTML = '<p style="text-align:center; padding: 2rem;">No locations match the current filters.</p>';
            return;
        }

        locationsToRender.forEach(loc => {
            let patientInfoHtml = '';
            let identifier = (loc.type === 'bed') ? `${loc.location_name} - Bed ${loc.bed_number}` : `Room ${loc.bed_number}`;
            
            if (loc.status === 'occupied' && loc.patient_name) {
                patientInfoHtml = `<div class="patient-info"><i class="fas fa-user-circle"></i><span>${loc.patient_name} (${loc.patient_display_id || 'N/A'})</span></div>`;
            } else if (loc.status === 'cleaning') {
                patientInfoHtml = `<div class="patient-info"><i class="fas fa-pump-soap"></i><span>Pending Sanitization</span></div>`;
            } else if (loc.status === 'reserved') {
                 patientInfoHtml = `<div class="patient-info"><i class="fas fa-user-clock"></i><span>Reserved</span></div>`;
            }

            const locationCard = `
                <div class="bed-card status-${loc.status}" 
                     data-id="${loc.id}" 
                     data-type="${loc.type}"
                     data-identifier="${identifier}"
                     data-status="${loc.status}"
                     title="Click to edit status">
                    <div class="bed-id">${identifier}</div>
                    <div class="bed-details">${loc.type === 'bed' ? loc.location_name : 'Private Room'}</div>
                    ${patientInfoHtml}
                </div>
            `;
            gridContainer.insertAdjacentHTML('beforeend', locationCard);
        });
    }
    
    function filterAndRenderLocations() {
        const locationFilter = document.getElementById('bed-location-filter').value;
        const statusFilter = document.getElementById('bed-status-filter').value;
        
        let filteredData = allOccupancyData.filter(loc => {
            const statusMatch = (statusFilter === 'all') || (loc.status === statusFilter);
            
            let locationMatch = true;
            if (locationFilter !== 'all') {
                const [type, id] = locationFilter.split('-');
                if (type === 'ward') {
                    locationMatch = (loc.type === 'bed') && (loc.location_parent_id == id);
                } else if (type === 'room') {
                    locationMatch = (loc.type === 'room') && (loc.id == id);
                }
            }
            
            return statusMatch && locationMatch;
        });

        renderLocations(filteredData);
    }
    
    if (occupancyPage) {
        document.getElementById('bed-location-filter').addEventListener('change', filterAndRenderLocations);
        document.getElementById('bed-status-filter').addEventListener('change', filterAndRenderLocations);

        document.getElementById('bed-grid-container').addEventListener('click', (e) => {
            const card = e.target.closest('.bed-card');
            if (!card) return;

            const id = card.dataset.id;
            const type = card.dataset.type;
            const status = card.dataset.status;
            const identifier = card.dataset.identifier;

            if (status === 'occupied') {
                alert('Occupied locations must be managed via the Admissions/Discharge process.');
                return;
            }

            document.getElementById('edit-location-id').value = id;
            document.getElementById('edit-location-type').value = type;
            document.getElementById('edit-location-identifier-text').textContent = identifier;
            document.getElementById('edit-location-status-select').value = status;
            openModalById('edit-bed-modal-overlay');
        });

        document.getElementById('save-location-changes-btn').addEventListener('click', async () => {
            const saveButton = document.getElementById('save-location-changes-btn');
            const originalButtonText = saveButton.textContent;
            saveButton.disabled = true;
            saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Saving...`;

            const id = document.getElementById('edit-location-id').value;
            const type = document.getElementById('edit-location-type').value;
            const newStatus = document.getElementById('edit-location-status-select').value;
            
            const formData = new FormData();
            formData.append('action', 'update_location_status');
            formData.append('id', id);
            formData.append('type', type);
            formData.append('status', newStatus);

            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    isOccupancyManagerInitialized = false; 
                    await initializeOccupancyManagement(); 
                    document.getElementById('edit-bed-modal-overlay').classList.remove('active');
                } else {
                    alert(`Error: ${result.message || 'Could not update status.'}`);
                }
            } catch (error) {
                console.error('Failed to update location status:', error);
                alert('A network error occurred. Please try again.');
            } finally {
                saveButton.disabled = false;
                saveButton.textContent = originalButtonText;
            }
        });
    }

    const patientSearchInput = document.getElementById('patient-search');
    const patientStatusFilter = document.getElementById('patient-status-filter');

    if (patientSearchInput && patientStatusFilter) {
        patientSearchInput.addEventListener('input', filterPatients);
        patientStatusFilter.addEventListener('change', filterPatients);
    }
    
    const admissionsPage = document.getElementById('admissions-page');
    if (admissionsPage) {
        // --- MODIFIED: Added event listener for the new discharge buttons ---
        admissionsPage.addEventListener('click', async (e) => {
            const initiateBtn = e.target.closest('.initiate-discharge-btn');
            if (initiateBtn) {
                e.preventDefault();
                const admissionId = initiateBtn.dataset.id;
                
                if (confirm('Are you sure you want to initiate the discharge process for this patient?')) {
                    const formData = new FormData();
                    formData.append('action', 'initiate_discharge');
                    formData.append('admission_id', admissionId);

                    try {
                        const response = await fetch('api.php', { method: 'POST', body: formData });
                        const result = await response.json();
                        alert(result.message);
                        if (result.success) {
                            // Optionally, switch to the discharge page
                            document.querySelector('.nav-link[data-page="discharge"]').click();
                        }
                    } catch (error) {
                        alert('A network error occurred.');
                    }
                }
            }
        });
        
        document.getElementById('admit-patient-btn').addEventListener('click', openAdmitPatientModal);
        
        document.getElementById('admit-patient-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const saveButton = document.getElementById('modal-save-btn-admit');
            saveButton.disabled = true;
            saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Admitting...`;

            const formData = new FormData(this);
            formData.append('action', 'admit_patient');

            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    alert('Patient admitted successfully!');
                    document.getElementById('admit-patient-modal-overlay').classList.remove('active');
                    loadAdmissions();
                } else {
                    alert(`Error: ${result.message || 'An unknown error occurred.'}`);
                }
            } catch (error) {
                console.error('Error admitting patient:', error);
                alert('A network or server error occurred.');
            } finally {
                saveButton.disabled = false;
                saveButton.innerHTML = "Confirm Admission";
            }
        });

        document.getElementById('admissions-search').addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const filteredData = allAdmissionsData.filter(adm => 
                adm.patient_name.toLowerCase().includes(searchTerm) ||
                (adm.display_user_id && adm.display_user_id.toLowerCase().includes(searchTerm))
            );
            renderAdmissionsTable(filteredData);
        });
    }

    // --- MODIFIED: Added event listener for discharge page actions ---
    const dischargePage = document.getElementById('discharge-page');
    if (dischargePage) {
        dischargePage.addEventListener('click', (e) => {
            const statusBtn = e.target.closest('.view-discharge-status');
            const summaryBtn = e.target.closest('.write-discharge-summary');
            const downloadBtn = e.target.closest('.download-discharge-summary');

            if (statusBtn) {
                openDischargeStatusModal(statusBtn.dataset.id, statusBtn.dataset.patientName);
            }
            if (summaryBtn) {
                openDischargeSummaryModal(summaryBtn.dataset.id, summaryBtn.dataset.patientName, summaryBtn.dataset.admissionDate);
            }
            if (downloadBtn) {
                window.open(`api.php?action=download_discharge_summary&admission_id=${downloadBtn.dataset.id}`, '_blank');
            }
        });
        
        // --- ADDED: Handle submission of the discharge summary form ---
        const summaryForm = document.getElementById('discharge-summary-form');
        if (summaryForm) {
            summaryForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                const saveButton = document.getElementById('save-summary-btn');
                saveButton.disabled = true;
                saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Saving...`;

                const formData = new FormData(this);
                formData.append('action', 'save_discharge_summary');

                try {
                    const response = await fetch('api.php', { method: 'POST', body: formData });
                    const result = await response.json();

                    alert(result.message);

                    if (result.success) {
                        document.getElementById('discharge-summary-modal-overlay').classList.remove('active');
                        loadDischargeRequests(); // Refresh the list
                        
                        // Ask user if they want to download the PDF now
                        if (confirm("Summary saved. Do you want to download the PDF now?")) {
                             window.open(`api.php?action=download_discharge_summary&admission_id=${formData.get('admission_id')}`, '_blank');
                        }
                    }
                } catch (error) {
                    console.error("Error saving summary:", error);
                    alert("A network error occurred.");
                } finally {
                    saveButton.disabled = false;
                    saveButton.innerHTML = "Save & Generate PDF";
                }
            });
        }
    }

    const admitPatientSearch = document.getElementById('patient-search-admit');
    const admitPatientResults = document.getElementById('patient-search-results-admit');
    const admitPatientId = document.getElementById('patient-id-admit');
    let admitSearchTimeout;

    if (admitPatientSearch) {
        admitPatientSearch.addEventListener('input', () => {
             const term = admitPatientSearch.value.trim();
             admitPatientResults.innerHTML = '';
             admitPatientResults.classList.remove('active');

             if (term.length < 2) return;

             clearTimeout(admitSearchTimeout);
             admitSearchTimeout = setTimeout(async () => {
                const res = await fetch(`api.php?action=search_patients&term=${encodeURIComponent(term)}`);
                const result = await res.json();
                
                if (result.success && result.data.length > 0) {
                    result.data.forEach(p => {
                        const item = document.createElement('div');
                        item.className = 'search-result-item';
                        item.textContent = `${p.name} (${p.display_user_id})`;
                        item.dataset.patientId = p.id;
                        item.dataset.patientName = p.name;
                        admitPatientResults.appendChild(item);
                    });
                    admitPatientResults.classList.add('active');
                }
             }, 300);
        });

        admitPatientResults.addEventListener('click', (e) => {
            if (e.target.classList.contains('search-result-item')) {
                admitPatientId.value = e.target.dataset.patientId;
                admitPatientSearch.value = e.target.dataset.patientName;
                admitPatientResults.classList.remove('active');
            }
        });
    }

    const prescriptionsPage = document.getElementById('prescriptions-page');
    if (prescriptionsPage) {
        prescriptionsPage.addEventListener('click', function(e) {
            const viewButton = e.target.closest('.view-prescription');
            const printButton = e.target.closest('.print-prescription');

            if (viewButton) {
                const prescriptionId = viewButton.dataset.id;
                openViewPrescriptionModal(prescriptionId);
            } else if (printButton) {
                const prescriptionId = printButton.dataset.id;
                if(prescriptionId) {
                    window.open(`api.php?action=download_prescription&id=${prescriptionId}`, '_blank');
                }
            }
        });
        document.getElementById('prescription-search').addEventListener('input', filterPrescriptions);
        document.getElementById('prescription-date-filter').addEventListener('change', filterPrescriptions);
    }

    const appointmentsPage = document.getElementById('appointments-page');
    if (appointmentsPage) {
        const tabs = appointmentsPage.querySelectorAll('.tab-link');
        const tabContents = appointmentsPage.querySelectorAll('.appointment-tab');

        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                const period = this.dataset.tab;

                tabContents.forEach(content => {
                    content.style.display = content.id === `${period}-tab` ? 'block' : 'none';
                });
                loadAppointments(period);
            });
        });
    }

    loadDashboardData();

});

const prescriptionViewModal = document.getElementById('prescription-view-modal-overlay');
if (prescriptionViewModal) {
    prescriptionViewModal.addEventListener('click', function(e) {
        const printBtn = e.target.closest('#print-prescription-btn');
        if (printBtn) {
            const prescriptionId = printBtn.dataset.id;
            if (prescriptionId) {
                window.open(`api.php?action=download_prescription&id=${prescriptionId}`, '_blank');
            }
        }
    });
}