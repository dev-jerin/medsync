document.addEventListener("DOMContentLoaded", function() {
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
            const pageTitle = link.querySelector('i').nextSibling.textContent.trim();
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

    // --- Hamburger Menu Logic ---
    hamburgerBtn.addEventListener('click', (e) => { e.stopPropagation(); toggleMenu(); });
    overlay.addEventListener('click', closeMenu);
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 992 && sidebar.classList.contains('active')) {
            if (!sidebar.contains(e.target) && !hamburgerBtn.contains(e.target)) {
                closeMenu();
            }
        }
    });
    
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

    // Reusable open modal function
    function openModalById(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('active');
    }
    
    // Generic Modal Close button handler
    document.querySelectorAll('.modal-close-btn, .btn-secondary[data-modal-id]').forEach(btn => {
        btn.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal-id');
            if (modalId) {
                document.getElementById(modalId).classList.remove('active');
            }
        });
    });

    // --- All Page Logic ---
    
    // Appointments Page
    const appointmentPage = document.getElementById('appointments-page');
    if (appointmentPage) {
        const tabLinks = appointmentPage.querySelectorAll('.tab-link');
        const appointmentTabs = appointmentPage.querySelectorAll('.appointment-tab');
        tabLinks.forEach(link => {
            link.addEventListener('click', () => {
                const tabId = link.getAttribute('data-tab');
                tabLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                appointmentTabs.forEach(tab => {
                    tab.style.display = (tab.id === tabId + '-tab') ? 'block' : 'none';
                });
            });
        });
    }

    // My Patients Page Filter Logic
    const patientsPage = document.getElementById('patients-page');
    if(patientsPage) {
        const searchInput = document.getElementById('patient-search');
        const statusFilter = document.getElementById('patient-status-filter');
        const patientTableRows = document.querySelectorAll('#patients-table tbody .patient-row');

        function filterPatients() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;

            patientTableRows.forEach(row => {
                const name = row.querySelector('td[data-label="Name"]').textContent.toLowerCase();
                const id = row.querySelector('td[data-label="Patient ID"]').textContent.toLowerCase();
                const status = row.getAttribute('data-status');
                const matchesSearch = name.includes(searchTerm) || id.includes(searchTerm);
                const matchesStatus = (statusValue === 'all') || (status === statusValue);
                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }
        searchInput.addEventListener('keyup', filterPatients);
        statusFilter.addEventListener('change', filterPatients);
    }

    // Prescription Page & Modal Logic
    const prescriptionPage = document.getElementById('prescriptions-page');
    if(prescriptionPage) {
        document.getElementById('create-prescription-btn').addEventListener('click', () => openModalById('prescription-modal-overlay'));
        document.getElementById('quick-action-prescribe')?.addEventListener('click', (e) => { e.preventDefault(); document.querySelector('.nav-link[data-page="prescriptions"]').click(); setTimeout(() => openModalById('prescription-modal-overlay'), 50); });

        function showPrescriptionPreview(data) {
            document.getElementById('rx-patient-name').textContent = data.patientName;
            document.getElementById('rx-patient-id').textContent = data.patientId;
            document.getElementById('rx-date').textContent = new Date().toLocaleDateString('en-CA');
            document.getElementById('rx-medication-list').innerHTML = `
                <tr>
                    <td>
                        <div class="med-name">${data.medication}</div>
                        <div class="med-details">${data.dosage} - ${data.frequency}</div>
                    </td>
                </tr>
            `;
            document.getElementById('rx-notes-content').textContent = data.notes || 'N/A';
            openModalById('prescription-view-modal-overlay');
        }

        document.getElementById('modal-save-btn-presc').addEventListener('click', () => {
            const form = document.getElementById('prescription-form');
            const patientSelect = form.querySelector('#patient-select-presc');
            const selectedOption = patientSelect.options[patientSelect.selectedIndex];
            
            const prescriptionData = {
                patientId: selectedOption.value,
                patientName: selectedOption.dataset.name,
                medication: form.querySelector('#medication').value,
                dosage: form.querySelector('#dosage').value,
                frequency: form.querySelector('#frequency').value,
                notes: form.querySelector('#notes-presc').value
            };
            
            if (!prescriptionData.patientId || !prescriptionData.medication) {
                alert('Please select a patient and enter medication name.');
                return;
            }

            document.getElementById('prescription-modal-overlay').classList.remove('active');
            form.reset();
            showPrescriptionPreview(prescriptionData);
        });
        
        prescriptionPage.addEventListener('click', function(e) {
            if (e.target.closest('.view-prescription-btn')) {
                const row = e.target.closest('tr');
                const previewData = {
                    patientName: row.querySelector('td[data-label="Patient"]').textContent,
                    patientId: 'N/A',
                    medication: 'Simulated Medication',
                    dosage: '500mg',
                    frequency: 'Once a day',
                    notes: 'This is a preview of an existing prescription.'
                };
                showPrescriptionPreview(previewData);
            }
        });
        
        document.getElementById('print-prescription-btn').addEventListener('click', () => {
            window.print();
        });

        const searchInput = document.getElementById('prescription-search'), dateInput = document.getElementById('prescription-date-filter'), tableRows = document.querySelectorAll('#prescriptions-table tbody tr');
        function filterPrescriptions() {
            const searchTerm = searchInput.value.toLowerCase(), dateTerm = dateInput.value;
            tableRows.forEach(row => {
                const matchesSearch = row.querySelector('td[data-label="Patient"]').textContent.toLowerCase().includes(searchTerm) || row.querySelector('td[data-label="Rx ID"]').textContent.toLowerCase().includes(searchTerm);
                const matchesDate = (dateTerm === '') || (row.querySelector('td[data-label="Date Issued"]').textContent === dateTerm);
                row.style.display = (matchesSearch && matchesDate) ? '' : 'none';
            });
        }
        searchInput.addEventListener('keyup', filterPrescriptions);
        dateInput.addEventListener('change', filterPrescriptions);
    }

    // Admissions Page & Modal Logic
    const admissionsPage = document.getElementById('admissions-page');
    if(admissionsPage) {
        document.getElementById('admit-patient-btn').addEventListener('click', () => openModalById('admit-patient-modal-overlay'));
        document.getElementById('modal-save-btn-admit').addEventListener('click', () => {
            alert('Patient admission saved! (Frontend Demo)');
            document.getElementById('admit-patient-modal-overlay').classList.remove('active');
        });
        document.getElementById('quick-action-admit')?.addEventListener('click', (e) => { e.preventDefault(); document.querySelector('.nav-link[data-page="admissions"]').click(); setTimeout(() => openModalById('admit-patient-modal-overlay'), 50); });

        const searchInput = document.getElementById('admissions-search'), tableRows = document.querySelectorAll('#admissions-table tbody .admission-row');
        function filterAdmissions() {
            const searchTerm = searchInput.value.toLowerCase();
            tableRows.forEach(row => {
                const matchesSearch = row.querySelector('td[data-label="Patient Name"]').textContent.toLowerCase().includes(searchTerm) || row.querySelector('td[data-label="Adm. ID"]').textContent.toLowerCase().includes(searchTerm);
                row.style.display = matchesSearch ? '' : 'none';
            });
        }
        searchInput.addEventListener('keyup', filterAdmissions);
    }
    
    // Bed Management Page Logic
    const bedManagementPage = document.getElementById('bed-management-page');
    if (bedManagementPage) {
        const floorFilter = document.getElementById('bed-floor-filter');
        const statusFilter = document.getElementById('bed-status-filter');
        const bedCards = document.querySelectorAll('.bed-card');

        function filterBeds() {
            const selectedFloor = floorFilter.value;
            const selectedStatus = statusFilter.value;

            bedCards.forEach(card => {
                const cardFloor = card.getAttribute('data-floor');
                const cardStatus = card.getAttribute('data-status');

                const floorMatch = (selectedFloor === 'all') || (selectedFloor === cardFloor);
                const statusMatch = (selectedStatus === 'all') || (selectedStatus === cardStatus);

                if (floorMatch && statusMatch) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        floorFilter.addEventListener('change', filterBeds);
        statusFilter.addEventListener('change', filterBeds);
    }


    // Discharge Requests Page & Modal Logic
    const dischargePage = document.getElementById('discharge-page');
    if (dischargePage) {
        document.getElementById('discharge-requests-table').addEventListener('click', function(e) {
            if (e.target.closest('.view-discharge-status')) {
                const patientName = e.target.closest('tr').querySelector('td[data-label="Patient"]').textContent;
                document.getElementById('discharge-modal-title').textContent = `Discharge Status for ${patientName}`;
                openModalById('discharge-status-modal-overlay');
            }
        });

        const searchInput = document.getElementById('discharge-search'), statusFilter = document.getElementById('discharge-status-filter'), tableRows = document.querySelectorAll('#discharge-requests-table tbody .discharge-row');
        function filterDischarges() {
            const searchTerm = searchInput.value.toLowerCase(), statusValue = statusFilter.value;
            tableRows.forEach(row => {
                const matchesSearch = row.querySelector('td[data-label="Patient"]').textContent.toLowerCase().includes(searchTerm) || row.querySelector('td[data-label="Req. ID"]').textContent.toLowerCase().includes(searchTerm);
                const matchesStatus = (statusValue === 'all') || (row.getAttribute('data-status') === statusValue);
                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }
        searchInput.addEventListener('keyup', filterDischarges);
        statusFilter.addEventListener('change', filterDischarges);
    }

    // Lab Results Page & Modals Logic
    const labsPage = document.getElementById('labs-page');
    if (labsPage) {
        const labResultModal = document.getElementById('lab-result-modal-overlay');
        document.getElementById('add-lab-result-btn').addEventListener('click', () => openModalById('lab-result-modal-overlay'));
        document.querySelector('.nav-link[data-page="labs"]').addEventListener('click', function() {
            document.querySelectorAll('.add-result-entry').forEach(button => {
                button.addEventListener('click', () => openModalById('lab-result-modal-overlay'));
            });
        });
        document.getElementById('quick-action-lab')?.addEventListener('click', (e) => { e.preventDefault(); document.querySelector('.nav-link[data-page="labs"]').click(); setTimeout(() => openModalById('lab-result-modal-overlay'), 50); });

        const findingsContainer = document.getElementById('key-findings-container');
        document.getElementById('add-finding-btn').addEventListener('click', function() {
            const newFinding = document.createElement('div');
            newFinding.className = 'finding-row';
            newFinding.innerHTML = `
                <input type="text" placeholder="Parameter (e.g., Hemoglobin)">
                <input type="text" placeholder="Value (e.g., 14.5 g/dL)">
                <button type="button" class="btn-remove-finding">&times;</button>
            `;
            findingsContainer.appendChild(newFinding);
        });
        findingsContainer.addEventListener('click', function(e) {
            if (e.target.classList.contains('btn-remove-finding')) {
                e.target.closest('.finding-row').remove();
            }
        });
        
        document.getElementById('modal-save-btn-lab').addEventListener('click', () => {
             alert('Lab result saved! (Frontend Demo)');
             labResultModal.classList.remove('active');
        });

        document.getElementById('lab-results-table').addEventListener('click', function(e) {
            if (e.target.closest('.view-lab-report')) {
                const patientName = e.target.closest('tr').querySelector('td[data-label="Patient"]').textContent;
                document.getElementById('report-patient-name').textContent = patientName;
                document.getElementById('lab-report-view-title').textContent = `Lab Report for ${patientName}`;
                openModalById('lab-report-view-modal-overlay');
            }
        });
        
        const searchInput = document.getElementById('lab-search'), statusFilter = document.getElementById('lab-status-filter'), tableRows = document.querySelectorAll('#lab-results-table tbody .lab-row');
        function filterLabs() {
            const searchTerm = searchInput.value.toLowerCase(), statusValue = statusFilter.value;
            tableRows.forEach(row => {
                const matchesSearch = row.querySelector('td[data-label="Patient"]').textContent.toLowerCase().includes(searchTerm) || row.querySelector('td[data-label="Test Name"]').textContent.toLowerCase().includes(searchTerm);
                const matchesStatus = (statusValue === 'all') || (row.getAttribute('data-status') === statusValue);
                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }
        searchInput.addEventListener('keyup', filterLabs);
        statusFilter.addEventListener('change', filterLabs);
    }
    
    // --- Profile Page & Widget Logic ---
    const profileWidget = document.getElementById('user-profile-widget');
    if (profileWidget) {
        profileWidget.addEventListener('click', () => {
            document.querySelector('.nav-link[data-page="profile"]').click();
        });
    }

    const profilePage = document.getElementById('profile-page');
    if (profilePage) {
        const tabLinks = profilePage.querySelectorAll('.profile-tab-link');
        const tabContents = profilePage.querySelectorAll('.profile-tab-content');

        tabLinks.forEach(link => {
            link.addEventListener('click', () => {
                const tabId = link.getAttribute('data-tab');
                tabLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
                tabContents.forEach(content => {
                    content.classList.toggle('active', content.id === tabId + '-tab');
                });
            });
        });

        profilePage.querySelectorAll('.toggle-password').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const passwordInput = this.previousElementSibling;
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });
        
        const profilePicUpload = document.getElementById('profile-picture-upload');
        const editableProfilePic = document.querySelector('.editable-profile-picture');
        if (profilePicUpload && editableProfilePic) {
            profilePicUpload.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        editableProfilePic.src = e.target.result;
                        document.querySelector('.user-profile-widget .profile-picture').src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
        }

        document.getElementById('personal-info-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Personal information updated successfully!');
        });
        document.getElementById('security-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Password changed successfully!');
            e.target.reset();
        });
        document.getElementById('notifications-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            alert('Notification preferences saved!');
        });
    }

    // --- Medical Record Modal Logic ---
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-records-btn')) {
            e.preventDefault();
            const button = e.target.closest('.view-records-btn');
            const row = button.closest('tr');
            
            let patientName = row.querySelector('td[data-label="Name"]')?.textContent || row.querySelector('td[data-label="Patient Name"]')?.textContent;
            let patientId = row.querySelector('td[data-label="Patient ID"]')?.textContent;

            document.getElementById('record-modal-title').textContent = `Medical Record for ${patientName}`;
            document.getElementById('record-patient-name').textContent = patientName;
            document.getElementById('record-patient-id').textContent = patientId;
            openModalById('medical-record-modal-overlay');
        }

        if (e.target.closest('#medical-record-modal-overlay .view-lab-report')) {
            const button = e.target.closest('.view-lab-report');
            const patientName = button.dataset.patientName;
            document.getElementById('report-patient-name').textContent = patientName;
            document.getElementById('lab-report-view-title').textContent = `Lab Report for ${patientName}`;
            openModalById('lab-report-view-modal-overlay');
        }
    });
    
    // --- Messenger Page Logic ---
    const messengerPage = document.getElementById('messenger-page');
    if (messengerPage) {
        const conversationItems = messengerPage.querySelectorAll('.conversation-item');
        const chatHeader = messengerPage.querySelector('#chat-with-user');
        const messagesContainer = messengerPage.querySelector('#chat-messages-container');
        const messageForm = messengerPage.querySelector('#message-form');
        const messageInput = messengerPage.querySelector('#message-input');
        
        const conversations = {
            user1: `<div class="message received"><div class="message-content"><p>Hi Dr. Carter, can you please check on Michael Brown's latest ECG report?</p><span class="message-timestamp">8:08 PM</span></div></div><div class="message sent"><div class="message-content"><p>Of course, Dr. Smith. I'm looking at it now. The results seem normal.</p><span class="message-timestamp">8:09 PM</span></div></div><div class="message received"><div class="message-content"><p>Great, thank you. Also, please review the new lab results when you have a moment.</p><span class="message-timestamp">8:09 PM</span></div></div><div class="message sent"><div class="message-content"><p>Yes, I'll review the new lab results.</p><span class="message-timestamp">8:10 PM</span></div></div>`,
            user2: `<div class="message received"><div class="message-content"><p>Good evening, Dr. Carter. Just a reminder that the weekly staff meeting is scheduled for tomorrow at 9 AM.</p><span class="message-timestamp">7:45 PM</span></div></div><div class="message sent"><div class="message-content"><p>Thanks for the reminder, Alice. I'll be there.</p><span class="message-timestamp">7:46 PM</span></div></div>`,
            user3: `<div class="message received"><div class="message-content"><p>Dr. Carter, patient in Room 201-A is stable. Vitals are normal.</p><span class="message-timestamp">Yesterday</span></div></div>`
        };

        conversationItems.forEach(item => {
            item.addEventListener('click', function() {
                conversationItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                const userName = this.dataset.userName;
                const userId = this.dataset.userId;
                chatHeader.textContent = userName;
                messagesContainer.innerHTML = conversations[userId];
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            });
        });
        
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const messageText = messageInput.value.trim();
            if (messageText === '') return;
            const now = new Date();
            const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
            const messageEl = document.createElement('div');
            messageEl.className = 'message sent';
            messageEl.innerHTML = `<div class="message-content"><p>${messageText}</p><span class="message-timestamp">${time}</span></div>`;
            messagesContainer.appendChild(messageEl);
            messageInput.value = '';
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        });
    }

    // --- Notification Dropdown Logic ---
    const notificationBell = document.getElementById('notification-bell');
    const notificationPanel = document.getElementById('notification-panel');
    const notificationBadge = document.getElementById('notification-badge');

    if (notificationBell) {
        notificationBell.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationPanel.classList.toggle('active');
            notificationBadge.classList.add('hidden');
        });

        document.getElementById('view-all-notifications-link').addEventListener('click', (e) => {
            e.preventDefault();
            notificationPanel.classList.remove('active');
            document.querySelector('.nav-link[data-page="notifications"]').click();
        });
    }

    document.addEventListener('click', (e) => {
        if (notificationPanel && !notificationPanel.contains(e.target) && !notificationBell.contains(e.target)) {
            notificationPanel.classList.remove('active');
        }
    });

    // --- Notifications Page Logic ---
    const notificationsPage = document.getElementById('notifications-page');
    if (notificationsPage) {
        const markAllReadBtn = document.getElementById('mark-all-read-btn');
        const typeFilter = document.getElementById('notification-type-filter');
        const notificationItems = notificationsPage.querySelectorAll('.notification-list-item');
        
        markAllReadBtn.addEventListener('click', () => {
            notificationItems.forEach(item => {
                item.classList.remove('unread');
                item.classList.add('read');
            });
        });
        
        typeFilter.addEventListener('change', () => {
            const selectedType = typeFilter.value;
            notificationItems.forEach(item => {
                const itemType = item.dataset.type;
                if (selectedType === 'all' || selectedType === itemType) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    }
});