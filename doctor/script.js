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
    // --- MOVED FUNCTION DEFINITION HERE ---
    // This function is now accessible to the entire script.
    // ===================================================================
    async function loadLabResults() {
        const tableBody = document.querySelector('#lab-results-table tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="6" class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading lab results...</td></tr>`;

        try {
            const response = await fetch('api.php?action=get_lab_results');
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const result = await response.json();

            tableBody.innerHTML = ''; // Clear loading state
            if (result.success && result.data.length > 0) {
                result.data.forEach(report => {
                   const tr = document.createElement('tr');
                   tr.className = `lab-row`;
                   tr.dataset.status = report.status.toLowerCase();
                   
                   let actionButtonHtml = '';
                   switch(report.status.toLowerCase()) {
                       case 'completed':
                           actionButtonHtml = `<button class="action-btn view-lab-report" data-id="${report.id}"><i class="fas fa-file-alt"></i> View Report</button>`;
                           break;
                       case 'processing':
                           actionButtonHtml = `<button class="action-btn" disabled><i class="fas fa-spinner"></i> In Progress</button>`;
                           break;
                       case 'pending':
                           actionButtonHtml = `<button class="action-btn add-result-entry" data-id="${report.id}"><i class="fas fa-plus-circle"></i> Add Result</button>`;
                           break;
                       default:
                           actionButtonHtml = 'N/A';
                   }

                   tr.innerHTML = `
                       <td data-label="Report ID">LR${report.id}</td>
                       <td data-label="Patient">${report.patient_name}</td>
                       <td data-label="Test Name">${report.test_name}</td>
                       <td data-label="Date">${report.test_date}</td>
                       <td data-label="Status"><span class="status ${report.status.toLowerCase()}">${report.status}</span></td>
                       <td data-label="Actions">${actionButtonHtml}</td>
                   `;
                   tableBody.appendChild(tr);
                });
            } else {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 2rem;">No lab results found.</td></tr>`;
            }

        } catch (error) {
            console.error("Error loading lab results:", error);
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; padding: 2rem; color: var(--danger-color);">Failed to load lab results.</td></tr>`;
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
            
            // --- Page specific initializers ---
            if (pageId === 'bed-management') {
                initializeOccupancyManagement();
            }
            if (pageId === 'labs') {
                loadLabResults();
            }
            if (pageId === 'profile') {
                // Pre-load audit log if navigating to profile settings
                const auditLogTab = document.querySelector('.profile-tab-link[data-tab="audit-log"]');
                if(auditLogTab && auditLogTab.classList.contains('active')) {
                   loadAuditLog();
                }
            }


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

    // --- All Page Logic (Existing) ---
    // ... (appointments, patients, prescriptions, etc. logic remains here) ...
    
    // ===================================================================
    // --- NEW: Lab Results Page Logic ---
    // ===================================================================
    const labsPage = document.getElementById('labs-page');
    if (labsPage) {
        // --- Event Delegation for table actions ---
        document.getElementById('lab-results-table').addEventListener('click', function(e) {
            if (e.target.closest('.view-lab-report')) {
                const button = e.target.closest('.view-lab-report');
                const reportId = button.dataset.id;
                openViewLabReportModal(reportId);
            }
             if (e.target.closest('.add-result-entry')) {
                // Future enhancement: Open modal pre-filled for a pending test
                alert('Functionality to update a pending test is in development.');
            }
        });

        // --- Add New Lab Result Modal Logic ---
        document.getElementById('add-lab-result-btn').addEventListener('click', openAddLabResultModal);

        async function openAddLabResultModal() {
            const form = document.getElementById('lab-result-form');
            form.reset();
            document.getElementById('key-findings-container').innerHTML = ''; // Clear findings

            const patientSelect = document.getElementById('lab-patient-select');
            patientSelect.innerHTML = '<option value="">Loading patients...</option>';
            
            try {
                if (!patientListCache) {
                    const response = await fetch('api.php?action=get_patients_for_dropdown');
                    const result = await response.json();
                    if (result.success) {
                        patientListCache = result.data;
                    } else {
                        throw new Error(result.message);
                    }
                }
                patientSelect.innerHTML = '<option value="">-- Choose a patient --</option>';
                patientListCache.forEach(patient => {
                    patientSelect.innerHTML += `<option value="${patient.id}">${patient.display_user_id} - ${patient.name}</option>`;
                });
            } catch (error) {
                console.error('Failed to load patients:', error);
                patientSelect.innerHTML = '<option value="">Could not load patients</option>';
            }
            openModalById('lab-result-modal-overlay');
        }

        async function openViewLabReportModal(reportId) {
            try {
                const response = await fetch(`api.php?action=get_lab_report_details&id=${reportId}`);
                if (!response.ok) throw new Error('Network error');
                const result = await response.json();

                if (result.success) {
                    const report = result.data;
                    document.getElementById('report-patient-name').textContent = report.patient_name;
                    document.querySelector('#lab-report-view-title').textContent = `Lab Report for ${report.patient_name}`;
                    document.querySelector('.report-view-header div:nth-child(2)').innerHTML = `<strong>Test:</strong> ${report.test_name}`;
                    document.querySelector('.report-view-header div:nth-child(3)').innerHTML = `<strong>Report ID:</strong> LR${report.id}`;
                    document.querySelector('.report-view-header div:nth-child(4)').innerHTML = `<strong>Date:</strong> ${report.test_date}`;

                    const findingsBody = document.querySelector('.findings-table tbody');
                    findingsBody.innerHTML = '';
                    if (report.result_details && Array.isArray(report.result_details.findings) && report.result_details.findings.length > 0) {
                         report.result_details.findings.forEach(finding => {
                            findingsBody.innerHTML += `<tr><td>${finding.parameter}</td><td>${finding.result}</td><td>${finding.range}</td></tr>`;
                         });
                    } else {
                         findingsBody.innerHTML = `<tr><td colspan="3">No detailed findings were entered.</td></tr>`;
                    }
                    
                    document.querySelector('.report-view-body p').textContent = report.result_details.summary || 'No summary provided.';
                    
                    openModalById('lab-report-view-modal-overlay');
                } else {
                    alert(`Error: ${result.message}`);
                }
            } catch (error) {
                console.error('Error fetching report details:', error);
                alert('Could not fetch report details.');
            }
        }
        
        // --- Manage "Key Findings" in the add/edit modal ---
        const findingsContainer = document.getElementById('key-findings-container');
        document.getElementById('add-finding-btn').addEventListener('click', () => {
             const findingRow = document.createElement('div');
             findingRow.className = 'finding-row';
             findingRow.innerHTML = `
                <input type="text" class="finding-parameter" placeholder="Parameter (e.g., Hemoglobin)">
                <input type="text" class="finding-result" placeholder="Result (e.g., 14.5 g/dL)">
                <input type="text" class="finding-range" placeholder="Reference Range">
                <button type="button" class="btn-remove-finding">&times;</button>
             `;
             findingsContainer.appendChild(findingRow);
        });

        findingsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-remove-finding')) {
                e.target.parentElement.remove();
            }
        });
        
        // --- Handle Lab Result Form Submission ---
        document.getElementById('lab-result-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const saveButton = document.getElementById('modal-save-btn-lab');
            const originalButtonText = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Saving...`;

            // Collect key findings into a structured object
            const findings = [];
            document.querySelectorAll('.finding-row').forEach(row => {
                const parameter = row.querySelector('.finding-parameter').value.trim();
                const result = row.querySelector('.finding-result').value.trim();
                const range = row.querySelector('.finding-range').value.trim();
                if (parameter && result) {
                    findings.push({ parameter, result, range });
                }
            });
            
            const summary = document.getElementById('lab-summary').value;
            const resultDetails = JSON.stringify({ findings, summary });

            // The constructor now correctly gathers all fields because we added `name` attributes in the HTML.
            const formData = new FormData(this); 
            
            // We still need to manually append the action and our custom JSON object.
            formData.append('action', 'add_lab_result');
            formData.append('result_details', resultDetails);
            formData.append('test_date', new Date().toISOString().slice(0, 10)); 
            
            try {
                const response = await fetch('api.php', { method: 'POST', body: formData });
                const result = await response.json();
                
                if (result.success) {
                    alert(result.message);
                    document.getElementById('lab-result-modal-overlay').classList.remove('active');
                    loadLabResults(); // Refresh the table
                } else {
                    alert(`Error: ${result.message || 'An unknown error occurred.'}`);
                }

            } catch (error) {
                console.error('Error submitting lab result:', error);
                alert('A network or server error occurred. Please try again.');
            } finally {
                 saveButton.disabled = false;
                 saveButton.innerHTML = originalButtonText;
            }
        });
    }

    // ===================================================================
    // --- UPDATED: Profile Settings Page Logic ---
    // ===================================================================
    const personalInfoForm = document.getElementById('personal-info-form');
    if (personalInfoForm) {
        personalInfoForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const saveButton = this.querySelector('button[type="submit"]');
            const originalButtonText = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Saving...`;

            const formData = new FormData(this);
            formData.append('action', 'update_personal_info');

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Profile updated successfully!');
                    // Dynamically update the name in the header and welcome message
                    const newName = formData.get('name');
                    const newSpecialty = formData.get('specialty');

                    const headerProfileName = document.querySelector('.user-profile-widget .profile-info strong');
                    const headerProfileSpecialty = document.querySelector('.user-profile-widget .profile-info span');
                    if (headerProfileName) {
                        headerProfileName.textContent = `Dr. ${newName}`;
                    }
                    if (headerProfileSpecialty) {
                        headerProfileSpecialty.textContent = newSpecialty;
                    }
                    
                    const welcomeMessageName = document.querySelector('.welcome-message h2');
                    if(welcomeMessageName){
                        welcomeMessageName.textContent = `Welcome back, Dr. ${newName}!`;
                    }

                } else {
                    alert(`Error: ${result.message || 'An unknown error occurred.'}`);
                }

            } catch (error) {
                console.error('Error updating profile:', error);
                alert('A network error occurred. Please try again.');
            } finally {
                saveButton.disabled = false;
                saveButton.innerHTML = originalButtonText;
            }
        });
    }

    // --- Profile Tab Switching & Audit Log Loading ---
    const profilePage = document.getElementById('profile-page');
    if (profilePage) {
        const profileTabs = profilePage.querySelectorAll('.profile-tab-link');
        const profileTabContents = profilePage.querySelectorAll('.profile-tab-content');
        
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
            return; // Already loaded, do nothing
        }

        tableBody.innerHTML = `<tr><td colspan="4" class="loading-placeholder" style="text-align:center; padding: 2rem;"><i class="fas fa-spinner fa-spin"></i> Loading activity log...</td></tr>`;

        try {
            const response = await fetch('api.php?action=get_audit_log');
            if (!response.ok) throw new Error('Network response was not ok.');
            
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                tableBody.innerHTML = ''; // Clear loading spinner
                result.data.forEach(log => {
                    const tr = document.createElement('tr');

                    // 1. Format Date & Time
                    const logDate = new Date(log.created_at).toLocaleString('en-US', {
                        year: 'numeric', month: 'short', day: 'numeric', 
                        hour: '2-digit', minute: '2-digit', hour12: true
                    });

                    // 2. Determine Action Class & Text
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
                    
                    // 3. Determine Target
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
                tableBody.dataset.loaded = 'true'; // Mark as loaded
            } else {
                tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 2rem;">No recent account activity found.</td></tr>`;
            }
        } catch (error) {
            console.error('Error fetching audit log:', error);
            tableBody.innerHTML = `<tr><td colspan="4" style="text-align:center; color: var(--danger-color); padding: 2rem;">Failed to load activity log.</td></tr>`;
        }
    }


    // ===================================================================
    // --- UPDATED: Bed Management Page Logic ---
    // ===================================================================
    const occupancyPage = document.getElementById('bed-management-page');
    let allOccupancyData = []; // Store all bed and room data
    let isOccupancyManagerInitialized = false;

    async function initializeOccupancyManagement() {
        if (isOccupancyManagerInitialized) return;
        isOccupancyManagerInitialized = true;
        
        const gridContainer = document.getElementById('bed-grid-container');
        gridContainer.innerHTML = '<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading occupancy data...</div>';

        try {
            // NOTE: The actions 'get_locations' and 'get_occupancy_data' now point to the corrected logic in api.php
            const [locationsRes, occupancyRes] = await Promise.all([
                fetch('api.php?action=get_locations'),
                fetch('api.php?action=get_occupancy_data')
            ]);

            if (!locationsRes.ok || !occupancyRes.ok) throw new Error('Failed to fetch management data.');

            const locationsData = await locationsRes.json();
            const occupancyData = await occupancyRes.json();
            
            if (locationsData.success) populateLocationFilter(locationsData.data);
            if (occupancyData.success) {
                allOccupancyData = occupancyData.data;
                renderLocations(allOccupancyData);
            } else {
                 gridContainer.innerHTML = `<div class="loading-placeholder">Error: ${occupancyData.message}</div>`;
            }
        } catch (error) {
            console.error('Error initializing occupancy management:', error);
            gridContainer.innerHTML = `<div class="loading-placeholder">Error: ${error.message}</div>`;
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
                option.textContent = room.name; // 'name' is the alias for room_number
                roomGroup.appendChild(option);
            });
            filter.appendChild(roomGroup);
        }
    }

    function renderLocations(locationsToRender) {
        const gridContainer = document.getElementById('bed-grid-container');
        gridContainer.innerHTML = '';

        if (locationsToRender.length === 0) {
            gridContainer.innerHTML = '<p>No locations match the current filters.</p>';
            return;
        }

        locationsToRender.forEach(loc => {
            let patientInfoHtml = '';
            let identifier = (loc.type === 'bed') ? `${loc.location_name} - ${loc.bed_number}` : loc.bed_number;
            
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
                    <div class="bed-details">${loc.location_name}</div>
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
                    // Show beds from the selected ward
                    locationMatch = (loc.type === 'bed') && (loc.location_parent_id == id);
                } else if (type === 'room') {
                    // Show the specific selected room
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
                    // Reset initialization flag to allow re-fetching fresh data
                    isOccupancyManagerInitialized = false; 
                    await initializeOccupancyManagement(); 
                    filterAndRenderLocations(); // Re-apply filters after re-fetching
                    document.getElementById('edit-bed-modal-overlay').classList.remove('active');
                } else {
                    alert(`Error: ${result.message || 'Could not update status.'}`);
                }
            } catch (error) {
                console.error('Failed to update location status:', error);
                alert('A network error occurred. Please try again.');
            }
        });
    }

}); // End of DOMContentLoaded