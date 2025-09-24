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
                                <td data-label="Action"><button class="action-btn"><i class="fas fa-play-circle"></i> Start</button></td>
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
            const dischargeNavLink = document.querySelector('.nav-link[data-page="discharge"]');
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
    async function loadLabOrders() {
        const tableBody = document.querySelector('#lab-orders-table tbody');
        if (!tableBody) return;
        tableBody.innerHTML = `<tr><td colspan="6" class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading lab orders...</td></tr>`;

        try {
            const response = await fetch('api.php?action=get_lab_orders');
            const result = await response.json();

            tableBody.innerHTML = '';
            if (result.success && result.data.length > 0) {
                result.data.forEach(order => {
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
            } else {
                tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center;">No lab orders found.</td></tr>`;
            }

        } catch (error) {
            console.error("Error loading lab orders: ", error);
            tableBody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--danger-color);">Failed to load lab orders.</td></tr>`;
        }
    }

    const placeLabOrderBtn = document.getElementById('place-lab-order-btn');
    if (placeLabOrderBtn) {
        const testRowsContainer = document.getElementById('test-rows-container');
        const addTestRowBtn = document.getElementById('add-test-row-btn');

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

        placeLabOrderBtn.addEventListener('click', async () => {
            const form = document.getElementById('lab-order-form');
            form.reset();
            testRowsContainer.innerHTML = '';
            addTestRow();

            const patientSelect = document.getElementById('lab-order-patient-select');
            patientSelect.innerHTML = '<option value="">Loading...</option>';
            if (!patientListCache) { 
                const response = await fetch('api.php?action=get_patients_for_dropdown');
                const result = await response.json();
                if (result.success) patientListCache = result.data;
            }
            patientSelect.innerHTML = '<option value="">-- Choose a patient --</option>';
            patientListCache.forEach(p => {
                patientSelect.innerHTML += `<option value="${p.id}">${p.display_user_id} - ${p.name}</option>`;
            });

            openModalById('lab-order-modal-overlay');
        });

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
                const patientId = document.getElementById('lab-order-patient-select').value;
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
                        test_names: testNames
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
            tr.innerHTML = `
                <td data-label="Adm. ID">ADM-${adm.id}</td>
                <td data-label="Patient Name">${adm.patient_name} (${adm.display_user_id})</td>
                <td data-label="Room/Bed">${adm.room_bed}</td>
                <td data-label="Adm. Date">${admissionDate}</td>
                <td data-label="Status"><span class="status ${adm.status === 'Active' ? 'in-patient' : 'completed'}">${adm.status}</span></td>
                <td data-label="Actions">
                    <button class="action-btn" data-id="${adm.id}"><i class="fas fa-file-medical"></i> View</button>
                    <button class="action-btn danger" data-id="${adm.id}"><i class="fas fa-sign-out-alt"></i> Discharge</button>
                </td>
            `;
            tableBody.appendChild(tr);
        });
    }

    async function openAdmitPatientModal() {
        const form = document.getElementById('admit-patient-form');
        form.reset();

        const patientSelect = document.getElementById('patient-select-admit');
        const bedSelect = document.getElementById('bed-select-admit');
        patientSelect.innerHTML = '<option value="">Loading...</option>';
        bedSelect.innerHTML = '<option value="">Loading...</option>';
        
        openModalById('admit-patient-modal-overlay');

        try {
            if (!patientListCache) {
                const response = await fetch('api.php?action=get_patients_for_dropdown');
                const result = await response.json();
                if (result.success) patientListCache = result.data;
            }
            patientSelect.innerHTML = '<option value="">-- Choose a patient --</option>';
            patientListCache.forEach(p => {
                patientSelect.innerHTML += `<option value="${p.id}">${p.display_user_id} - ${p.name}</option>`;
            });

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
            console.error('Failed to populate modal:', error);
            patientSelect.innerHTML = '<option value="">Error loading patients</option>';
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
                
                // Populate modal fields
                document.getElementById('rx-patient-name').textContent = rx.patient_name;
                document.getElementById('rx-patient-id').textContent = rx.patient_display_id;
                document.getElementById('rx-date').textContent = new Date(rx.prescription_date).toLocaleDateString();
                document.getElementById('rx-notes-content').textContent = rx.notes || 'No specific notes provided.';

                const medicationList = document.getElementById('rx-medication-list');
                medicationList.innerHTML = ''; // Clear previous items
                
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
            
            const patientSelect = document.getElementById('patient-select-presc');
            patientSelect.innerHTML = '<option value="">Loading...</option>';
            openModalById('prescription-modal-overlay');
    
            if (!patientListCache) {
                const response = await fetch('api.php?action=get_patients_for_dropdown');
                const result = await response.json();
                if (result.success) patientListCache = result.data;
            }
            patientSelect.innerHTML = '<option value="">-- Choose a patient --</option>';
            patientListCache.forEach(p => {
                patientSelect.innerHTML += `<option value="${p.id}">${p.display_user_id} - ${p.name}</option>`;
            });
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
                    patient_id: document.getElementById('patient-select-presc').value,
                    notes: document.getElementById('notes-presc').value,
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
            if (pageId === 'messenger') {
                initializeMessenger();
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

    // ===================================================================
    // --- Appointments Page Logic ---
    // ===================================================================
    async function loadAppointments(period = 'today') {
        const listContainer = document.querySelector(`#${period}-tab .appointment-list`);
        const dateFilter = document.getElementById('appointment-date-filter');
        if (!listContainer) return;

        listContainer.innerHTML = `<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading appointments...</div>`;

        // Build the API URL with the date if it's selected
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

    // Add this event listener for the date filter
    const dateFilterInput = document.getElementById('appointment-date-filter');
    if(dateFilterInput) {
        dateFilterInput.addEventListener('change', () => {
            // When a date is selected, find the active tab and reload its appointments
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
                    <button class="action-btn"><i class="fas fa-file-medical"></i> View Record</button>
                    ${appt.status.toLowerCase() === 'scheduled' ? '<button class="action-btn"><i class="fas fa-play-circle"></i> Start</button>' : ''}
                </div>
            `;
            container.appendChild(item);
        });
    }

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
            const response = await fetch(`api.php?action=get_lab_report_details&id=${reportId}`);
            if (!response.ok) throw new Error('Network error');
            const result = await response.json();

            if (result.success) {
                const report = result.data;
                document.getElementById('report-patient-name').textContent = report.patient_name;
                document.querySelector('#lab-report-view-title').textContent = `Lab Report for ${report.patient_name}`;
                document.querySelector('.report-view-header div:nth-child(2)').innerHTML = `<strong>Test:</strong> ${report.test_name}`;
                document.querySelector('.report-view-header div:nth-child(3)').innerHTML = `<strong>Report ID:</strong> ORD-${report.id}`;
                document.querySelector('.report-view-header div:nth-child(4)').innerHTML = `<strong>Date:</strong> ${new Date(report.ordered_at).toLocaleDateString()}`;

                const findingsBody = document.querySelector('.findings-table tbody');
                findingsBody.innerHTML = '';
                if (report.result_details && Array.isArray(report.result_details.findings) && report.result_details.findings.length > 0) {
                     report.result_details.findings.forEach(finding => {
                        findingsBody.innerHTML += `<tr><td>${finding.parameter}</td><td>${finding.result}</td><td>${finding.range}</td></tr>`;
                     });
                } else {
                     findingsBody.innerHTML = `<tr><td colspan="3">No detailed findings were entered.</td></tr>`;
                }
                
                document.querySelector('.report-view-body p').textContent = (report.result_details && report.result_details.summary) ? report.result_details.summary : 'No summary provided.';
                
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
    // --- Messenger Page Logic (Existing) ---
    // ===================================================================
    const messengerPage = document.getElementById('messenger-page');
    let messengerInitialized = false;
    let activeConversation = { conversationId: null, otherUserId: null, otherUserName: null };

    function initializeMessenger() {
        if (messengerInitialized || !messengerPage) return;
        
        const conversationListEl = messengerPage.querySelector('.conversation-list');
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');
        const chatHeader = document.getElementById('chat-with-user');
        const messagesContainer = document.getElementById('chat-messages-container');

        loadConversations();

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
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Send message error:', error);
                alert('Failed to send message.');
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

            const { conversationId, otherUserId, otherUserName } = conversationItem.dataset;
            
            activeConversation = {
                conversationId: conversationId || null,
                otherUserId: parseInt(otherUserId),
                otherUserName: otherUserName
            };
            
            chatHeader.textContent = otherUserName;
            
            if (conversationId) {
                loadMessages(conversationId);
                const unreadIndicator = conversationItem.querySelector('.unread-indicator');
                if (unreadIndicator) unreadIndicator.style.display = 'none';
            } else {
                messagesContainer.innerHTML = `<div class="message-placeholder">Start the conversation with ${otherUserName}.</div>`;
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
        const listContainer = messengerPage.querySelector('.conversation-list');
        listContainer.innerHTML = `<div class="conversation-search"><input type="text" placeholder="Search users..."></div><div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading...</div>`;

        try {
            const response = await fetch('api.php?action=get_conversations');
            const result = await response.json();
            
            const placeholder = listContainer.querySelector('.loading-placeholder');
            if (placeholder) placeholder.remove();

            if (result.success && result.data.length > 0) {
                result.data.forEach(convo => renderConversation(convo));
                
                if (selectConversationId) {
                    const newConvoEl = listContainer.querySelector(`.conversation-item[data-conversation-id='${selectConversationId}']`);
                    if (newConvoEl) newConvoEl.click();
                }

            } else {
                listContainer.insertAdjacentHTML('beforeend', '<p style="padding: 1rem; text-align: center;">No conversations yet.</p>');
            }
        } catch (error) {
            console.error('Error loading conversations:', error);
            listContainer.insertAdjacentHTML('beforeend', '<p style="padding: 1rem; text-align: center; color: var(--danger-color);">Could not load conversations.</p>');
        }
    }

    function renderConversation(convo) {
        const listContainer = messengerPage.querySelector('.conversation-list');
        const lastMessageTime = convo.last_message_time ? new Date(convo.last_message_time).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        
        const conversationHtml = `
            <div class="conversation-item" 
                 data-conversation-id="${convo.conversation_id}" 
                 data-other-user-id="${convo.other_user_id}"
                 data-other-user-name="${convo.other_user_name}">
                <i class="fas ${getIconForRole(convo.other_user_role)} user-avatar"></i>
                <div class="user-details">
                    <div class="user-name">${convo.other_user_name}</div>
                    <div class="last-message">${convo.last_message || 'No messages yet.'}</div>
                </div>
                <div class="message-meta">
                    <div class="message-time">${lastMessageTime}</div>
                    ${convo.unread_count > 0 ? '<span class="unread-indicator"></span>' : ''}
                </div>
            </div>
        `;
        listContainer.insertAdjacentHTML('beforeend', conversationHtml);
    }
    
    async function loadMessages(conversationId) {
        const messagesContainer = document.getElementById('chat-messages-container');
        messagesContainer.innerHTML = `<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Loading messages...</div>`;

        try {
            const response = await fetch(`api.php?action=get_messages&conversation_id=${conversationId}`);
            const result = await response.json();
            messagesContainer.innerHTML = '';

            if (result.success && result.data.length > 0) {
                result.data.forEach(msg => renderMessage(msg));
                scrollToBottom(messagesContainer);
            } else {
                messagesContainer.innerHTML = '<div class="message-placeholder">No messages in this conversation yet.</div>';
            }
        } catch (error) {
            console.error('Error loading messages:', error);
            messagesContainer.innerHTML = '<div class="message-placeholder" style="color: var(--danger-color);">Failed to load messages.</div>';
        }
    }

    function renderMessage(msg, isNew = false) {
        const messagesContainer = document.getElementById('chat-messages-container');
        const messageType = msg.sender_id == currentUserId ? 'sent' : 'received';
        const timestamp = new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

        const sanitizedText = document.createElement('p');
        sanitizedText.textContent = msg.message_text;

        const messageHtml = `
            <div class="message ${messageType}">
                <div class="message-content">
                    ${sanitizedText.outerHTML}
                    <span class="message-timestamp">${timestamp}</span>
                </div>
            </div>
        `;
        messagesContainer.insertAdjacentHTML('beforeend', messageHtml);
        if(isNew) scrollToBottom(messagesContainer);
    }
    
    async function searchUsers(term) {
        const listContainer = messengerPage.querySelector('.conversation-list');
        const searchBarHtml = listContainer.querySelector('.conversation-search').outerHTML;
        listContainer.innerHTML = searchBarHtml + `<div class="loading-placeholder"><i class="fas fa-spinner fa-spin"></i> Searching...</div>`;

        try {
            const response = await fetch(`api.php?action=searchUsers&term=${encodeURIComponent(term)}`);
            const result = await response.json();
            
            const placeholder = listContainer.querySelector('.loading-placeholder');
            if (placeholder) placeholder.remove();

            if (result.success && result.data.length > 0) {
                result.data.forEach(user => {
                    const searchResultHtml = `
                        <div class="conversation-item" 
                             data-other-user-id="${user.id}"
                             data-other-user-name="${user.name}">
                            <i class="fas ${getIconForRole(user.role)} user-avatar"></i>
                            <div class="user-details">
                                <div class="user-name">${user.name} <small>(${user.role})</small></div>
                                <div class="last-message">Click to start a new conversation.</div>
                            </div>
                        </div>
                    `;
                    listContainer.insertAdjacentHTML('beforeend', searchResultHtml);
                });
            } else {
                listContainer.insertAdjacentHTML('beforeend', '<p style="padding: 1rem; text-align: center;">No users found.</p>');
            }
        } catch (error) {
            console.error('Error searching users:', error);
            listContainer.insertAdjacentHTML('beforeend', '<p style="padding: 1rem; text-align: center; color: var(--danger-color);">Search failed.</p>');
        }
    }

    function scrollToBottom(element) {
        if(element) element.scrollTop = element.scrollHeight;
    }

    function getIconForRole(role) {
        switch(role) {
            case 'admin': return 'fa-user-shield';
            case 'doctor': return 'fa-user-doctor';
            case 'staff': return 'fa-user-nurse';
            default: return 'fa-user';
        }
    }

    // ===================================================================
    // --- Profile Settings Page Logic (REVISED) ---
    // ===================================================================
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
                    alert('Profile updated successfully!');
                    const newName = formData.get('name');
                    const specialtySelect = document.getElementById('profile-specialty');
                    const newSpecialty = specialtySelect.options[specialtySelect.selectedIndex].text;
                    
                    document.querySelector('.user-profile-widget .profile-info strong').textContent = `Dr. ${newName}`;
                    document.querySelector('.user-profile-widget .profile-info span').textContent = newSpecialty;
                    document.querySelector('.welcome-message h2').textContent = `Welcome back, Dr. ${newName}!`;
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

    // --- NEW: Security Form (Password Update) Logic ---
    const securityForm = document.getElementById('security-form');
    if (securityForm) {
        securityForm.addEventListener('submit', async function(e) {
            e.preventDefault(); // This is the crucial line that prevents the redirect

            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            // --- Basic Client-Side Validation ---
            if (newPassword.length < 8) {
                alert('New password must be at least 8 characters long.');
                return;
            }
            if (newPassword !== confirmPassword) {
                alert('New password and confirmation do not match.');
                return;
            }

            const saveButton = this.querySelector('button[type="submit"]');
            const originalButtonText = saveButton.innerHTML;
            saveButton.disabled = true;
            saveButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Updating...`;

            const formData = new FormData(this);
            formData.append('action', 'updatePassword'); // Tell the API what to do

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert('Password updated successfully!');
                    this.reset(); // Clear the form fields
                } else {
                    // Display the specific error message from the server
                    alert(`Error: ${result.message || 'An unknown error occurred.'}`);
                }
            } catch (error) {
                console.error('Error updating password:', error);
                alert('A network error occurred. Please try again.');
            } finally {
                // Restore the button to its original state
                saveButton.disabled = false;
                saveButton.innerHTML = originalButtonText;
            }
        });
    }


// --- NEW: Password visibility toggle logic ---
document.querySelectorAll('.toggle-password').forEach(icon => {
    icon.addEventListener('click', function() {
        // Find the password input field next to the icon
        const passwordInput = this.previousElementSibling;
        
        // Check the current type of the input field
        const isPassword = passwordInput.getAttribute('type') === 'password';
        
        // Change the type and the icon
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

    const prescriptionsPage = document.getElementById('prescriptions-page');
    if (prescriptionsPage) {
        prescriptionsPage.addEventListener('click', function(e) {
            const viewButton = e.target.closest('.view-prescription');
            const printButton = e.target.closest('.print-prescription'); // New

            if (viewButton) {
                const prescriptionId = viewButton.dataset.id;
                openViewPrescriptionModal(prescriptionId);
            } else if (printButton) { // New else-if block
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

// Add this new event listener for the modal's print button
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