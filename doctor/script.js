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
            
            if (pageId === 'bed-management') {
                initializeOccupancyManagement();
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
    // Note: To keep this response focused, I'm omitting the older, unchanged page logic.
    // The new logic is self-contained below.


    // ===================================================================
    // --- NEW & UPDATED: Bed Management Page Logic ---
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
                    const dataIndex = allOccupancyData.findIndex(loc => loc.id == id && loc.type === type);
                    if (dataIndex !== -1) {
                        allOccupancyData[dataIndex].status = newStatus;
                        // If patient was associated, clear it on the frontend too
                        if (newStatus === 'available' || newStatus === 'cleaning') {
                            allOccupancyData[dataIndex].patient_name = null;
                            allOccupancyData[dataIndex].patient_display_id = null;
                        }
                    }
                    filterAndRenderLocations();
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