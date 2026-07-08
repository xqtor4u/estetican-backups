import L from 'leaflet';

const COLORS = {
    branches: '#0d6efd',
    clients: '#198754',
    pets: '#fd7e14',
    vehicles: '#6f42c1',
};

export default function mapaZonasFactory(config) {
    return {
        csrfToken: config.csrfToken,
        updatePetUrlTemplate: config.updatePetUrlTemplate,
        storeVehicleUrl: config.storeVehicleUrl,
        branches: config.branches ?? [],
        clients: config.clients ?? [],
        pets: config.pets ?? [],
        vehicles: config.vehicles ?? [],
        unlocatedPets: config.unlocatedPets ?? [],
        showBranches: true,
        showClients: true,
        showPets: true,
        showVehicles: true,
        placementMode: 'pet',
        selectedPetId: '',
        newVehicleName: '',
        newVehicleNotes: '',
        pendingLat: null,
        pendingLng: null,
        placementError: '',
        placing: false,
        map: null,
        layers: {},
        modalInstance: null,

        init() {
            this.map = L.map(this.$refs.mapContainer);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            const center = this.averageCenter(this.branches) ?? this.averageCenter(this.clients) ?? { lat: 19.4326, lng: -99.1332 };
            this.map.setView([center.lat, center.lng], this.branches.length ? 13 : 5);

            this.layers.branches = L.layerGroup().addTo(this.map);
            this.layers.clients = L.layerGroup().addTo(this.map);
            this.layers.pets = L.layerGroup().addTo(this.map);
            this.layers.vehicles = L.layerGroup().addTo(this.map);

            this.branches.forEach((b) => this.addMarker('branches', b));
            this.clients.forEach((c) => this.addMarker('clients', c));
            this.pets.forEach((p) => this.addMarker('pets', p));
            this.vehicles.forEach((v) => this.addMarker('vehicles', v));

            this.$watch('showBranches', (v) => this.toggleLayer('branches', v));
            this.$watch('showClients', (v) => this.toggleLayer('clients', v));
            this.$watch('showPets', (v) => this.toggleLayer('pets', v));
            this.$watch('showVehicles', (v) => this.toggleLayer('vehicles', v));

            this.map.on('click', (e) => this.openPlacementModal(e.latlng));
        },

        averageCenter(points) {
            if (!points || points.length === 0) return null;
            const lat = points.reduce((sum, p) => sum + p.lat, 0) / points.length;
            const lng = points.reduce((sum, p) => sum + p.lng, 0) / points.length;
            return { lat, lng };
        },

        addMarker(type, point) {
            const marker = L.circleMarker([point.lat, point.lng], {
                radius: 8,
                color: COLORS[type],
                fillColor: COLORS[type],
                fillOpacity: 0.85,
                weight: 2,
            }).bindPopup(point.label);

            marker.addTo(this.layers[type]);
        },

        toggleLayer(type, visible) {
            if (visible) {
                this.layers[type].addTo(this.map);
            } else {
                this.map.removeLayer(this.layers[type]);
            }
        },

        openPlacementModal(latlng) {
            this.pendingLat = latlng.lat;
            this.pendingLng = latlng.lng;
            this.placementMode = this.unlocatedPets.length ? 'pet' : 'vehicle';
            this.selectedPetId = '';
            this.newVehicleName = '';
            this.newVehicleNotes = '';
            this.placementError = '';

            if (!this.modalInstance) {
                this.modalInstance = new bootstrap.Modal(this.$refs.placementModal);
            }
            this.modalInstance.show();
        },

        async submitPlacement() {
            this.placementError = '';

            if (this.placementMode === 'pet') {
                if (!this.selectedPetId) {
                    this.placementError = 'Selecciona una mascota.';
                    return;
                }
                await this.submitPetLocation();
            } else {
                if (!this.newVehicleName.trim()) {
                    this.placementError = 'El nombre del vehículo es obligatorio.';
                    return;
                }
                await this.submitVehicle();
            }
        },

        async submitPetLocation() {
            this.placing = true;

            try {
                const url = this.updatePetUrlTemplate.replace('__PET_ID__', this.selectedPetId);
                const response = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({ lat: this.pendingLat, lng: this.pendingLng }),
                });

                const data = await response.json();

                if (!response.ok) {
                    this.placementError = data.message ?? 'No se pudo ubicar la mascota.';
                    this.placing = false;
                    return;
                }

                this.addMarker('pets', data);
                this.unlocatedPets = this.unlocatedPets.filter((p) => p.id !== data.id);
                this.modalInstance.hide();
            } catch (e) {
                this.placementError = 'Error de red al ubicar la mascota.';
            }

            this.placing = false;
        },

        async submitVehicle() {
            this.placing = true;

            try {
                const response = await fetch(this.storeVehicleUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                    },
                    body: JSON.stringify({
                        name: this.newVehicleName,
                        notes: this.newVehicleNotes,
                        lat: this.pendingLat,
                        lng: this.pendingLng,
                    }),
                });

                const data = await response.json();

                if (!response.ok) {
                    const errors = data.errors ? Object.values(data.errors).flat().join(' ') : null;
                    this.placementError = errors || data.message || 'No se pudo crear el vehículo.';
                    this.placing = false;
                    return;
                }

                this.addMarker('vehicles', data);
                this.modalInstance.hide();
            } catch (e) {
                this.placementError = 'Error de red al crear el vehículo.';
            }

            this.placing = false;
        },
    };
}
