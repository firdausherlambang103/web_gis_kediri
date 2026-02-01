@extends('layouts.admin')
@section('title', 'Peta Sebaran Aset')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <style>
        #map { height: 85vh; width: 100%; border: 1px solid #ced4da; border-radius: 4px; }
        
        /* Indikator Loading */
        #map-loading {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            z-index: 2000; background: rgba(255, 255, 255, 0.95); padding: 15px 30px;
            border-radius: 50px; font-weight: bold; display: none; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); color: #333;
        }

        /* Panel Filter Mengambang */
        .map-filter-box {
            position: absolute; top: 10px; right: 10px; z-index: 1000;
            background: rgba(255, 255, 255, 0.95); padding: 15px; border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2); width: 280px;
            backdrop-filter: blur(5px);
        }

        /* Style Cluster */
        .cluster-marker {
            background-color: rgba(220, 53, 69, 0.85);
            border: 3px solid white; border-radius: 50%;
            color: white; font-weight: bold; text-align: center;
            line-height: 30px; box-shadow: 0 3px 5px rgba(0,0,0,0.3);
        }

        /* Tabel Popup Detail */
        .popup-table { width: 100%; font-size: 11px; margin-top: 5px; }
        .popup-table td { padding: 3px 0; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .popup-key { font-weight: bold; color: #666; width: 35%; padding-right: 5px; }
        .popup-val { color: #000; font-weight: 500; }
        
        /* Upload Progress */
        .progress-group { display: none; margin-top: 15px; }
        .upload-log { max-height: 100px; overflow-y: auto; font-size: 0.85rem; margin-top: 10px; border: 1px solid #ddd; padding: 5px; background: #f9f9f9; }
    </style>
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline p-0 mb-0">
            <div class="card-body p-0 position-relative">
                
                <div id="map-loading">
                    <i class="fas fa-circle-notch fa-spin text-primary mr-2"></i> 
                    <span id="loading-text">Memuat Data...</span>
                </div>

                <div class="map-filter-box">
                    <h6 class="font-weight-bold mb-3"><i class="fas fa-layer-group text-primary"></i> Filter Peta</h6>
                    
                    <div class="form-group mb-2">
                        <label class="small mb-1">Pencarian (Nama/NIB)</label>
                        <div class="input-group input-group-sm">
                            <input type="text" id="searchMap" class="form-control" placeholder="Contoh: 00123...">
                            <div class="input-group-append">
                                <button class="btn btn-default" onclick="$('#searchMap').val(''); loadData();"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="small mb-1">Tipe Hak</label>
                        <select id="filterHak" class="form-control form-control-sm">
                            <option value="">Semua Data</option>
                            <option value="HM">Hak Milik (HM)</option>
                            <option value="HGB">Hak Guna Bangunan</option>
                            <option value="HP">Hak Pakai (HP)</option>
                            <option value="WAKAF">Tanah Wakaf</option>
                        </select>
                    </div>

                    <button class="btn btn-primary btn-sm btn-block shadow-sm" onclick="loadData()">
                        <i class="fas fa-search mr-1"></i> Terapkan Filter
                    </button>
                    
                    <hr class="my-2">
                    
                    <button class="btn btn-success btn-sm btn-block shadow-sm" data-toggle="modal" data-target="#uploadModal">
                        <i class="fas fa-cloud-upload-alt mr-1"></i> Upload SHP
                    </button>
                </div>

                <div id="map"></div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadModal" data-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Batch Upload SHP</h4>
                <button type="button" class="close" onclick="resetUploadModal()" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info small">
                    <i class="fas fa-info-circle"></i> Pilih banyak file <b>.zip</b> sekaligus. Sistem akan memproses satu per satu agar tidak error.
                </div>
                <div class="form-group">
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" id="shpFilesInput" accept=".zip" multiple>
                        <label class="custom-file-label" for="shpFilesInput">Pilih file ZIP...</label>
                    </div>
                    <small id="fileListInfo" class="text-muted mt-2"></small>
                </div>
                <div class="progress-group" id="progressArea">
                    <div class="d-flex justify-content-between small mb-1">
                        <span id="progressText">Menyiapkan...</span>
                        <span id="progressPercent">0%</span>
                    </div>
                    <div class="progress progress-sm">
                        <div class="progress-bar bg-primary" id="progressBar" style="width: 0%"></div>
                    </div>
                    <div id="uploadLog" class="upload-log" style="display:none;"></div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default" onclick="resetUploadModal()" data-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-success" id="btnStartUpload" onclick="startBatchUpload()">
                    <i class="fas fa-upload"></i> Mulai Upload
                </button>
            </div>
        </div>
    </div>
</div>

@include('admin.aset.partials.modals') 

@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>

<script>
    $(document).ready(function () { bsCustomFileInput.init(); });

    // === 1. TANGKAP PARAMETER URL (Update Penting) ===
    // Untuk membaca filter atau koordinat dari halaman tabel aset
    const urlParams = new URLSearchParams(window.location.search);
    const paramLat = urlParams.get('lat');
    const paramLng = urlParams.get('lng');
    const paramSearch = urlParams.get('search');
    const paramHak = urlParams.get('hak');

    // Isi Input Filter Otomatis
    if(paramSearch) $('#searchMap').val(paramSearch);
    if(paramHak) $('#filterHak').val(paramHak);

    // === 2. KONFIGURASI PETA ===
    // Tentukan titik awal: Jika ada parameter lat/lng, gunakan itu. Jika tidak, default Kediri.
    var startLat = paramLat ? parseFloat(paramLat) : -7.8;
    var startLng = paramLng ? parseFloat(paramLng) : 112.0;
    var startZoom = paramLat ? 18 : 12; // Zoom dekat jika target spesifik

    var map = L.map('map', { zoomControl: false }).setView([startLat, startLng], startZoom); 
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // Layer Satelit & Jalan
    var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OSM' });
    var googleSat = L.tileLayer('http://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',{ maxZoom: 20, subdomains:['mt0','mt1','mt2','mt3'] });
    
    osm.addTo(map); // Default OSM
    L.control.layers({ "Peta Jalan": osm, "Satelit": googleSat }, null, { position: 'bottomright' }).addTo(map);

    // Marker Sementara (Jika buka dari tabel)
    if(paramLat && paramLng) {
        L.marker([startLat, startLng]).addTo(map)
            .bindPopup("<b>Lokasi Terpilih</b><br>" + (paramSearch || "")).openPopup();
    }

    // === 3. FUNGSI WARNA ===
    function getColorByHak(tipe) {
        if (!tipe) return '#3388ff'; // Default Biru
        tipe = tipe.toString().toUpperCase();
        
        if (tipe.includes('HM') || tipe.includes('MILIK')) return '#28a745'; // Hijau
        if (tipe.includes('HGB') || tipe.includes('BANGUNAN')) return '#ffc107'; // Kuning
        if (tipe.includes('HP') || tipe.includes('PAKAI')) return '#17a2b8'; // Biru Muda
        if (tipe.includes('WAKAF')) return '#6f42c1'; // Ungu
        return '#3388ff'; // Default
    }

    var selectedLayer = null;

    // === 4. LAYER GEOJSON ===
    var geoJsonLayer = L.geoJSON(null, {
        
        style: function(feature) {
            var raw = feature.properties.raw_data || {};
            var tipe = raw.TIPE_HAK || raw.REMARK || raw.PENGGUNAAN; 
            return {
                color: getColorByHak(tipe),
                fillColor: getColorByHak(tipe),
                weight: 1, opacity: 1, fillOpacity: 0.5
            };
        },

        pointToLayer: function(feature, latlng) {
            if (feature.properties.type === 'cluster') {
                var size = feature.properties.count > 100 ? 40 : 30;
                return L.marker(latlng, {
                    icon: L.divIcon({
                        className: 'cluster-marker',
                        html: feature.properties.count,
                        iconSize: [size, size]
                    })
                });
            }
            return L.marker(latlng);
        },

        onEachFeature: function(feature, layer) {
            if (feature.properties.type === 'cluster') {
                layer.bindPopup(`<b>Area Padat</b><br>${feature.properties.count} Aset.<br>Zoom in.`);
                layer.on('click', function() { map.flyTo(layer.getLatLng(), map.getZoom() + 2); });
            } else {
                layer.on('click', function(e) {
                    if (selectedLayer) geoJsonLayer.resetStyle(selectedLayer);
                    selectedLayer = e.target;
                    selectedLayer.setStyle({ color: '#ff4500', fillColor: '#ffa500', weight: 3, fillOpacity: 0.8 });
                    selectedLayer.bringToFront();
                });

                var props = feature.properties;
                var raw = props.raw_data;
                var rows = '';
                if (raw) {
                    for (var key in raw) {
                        if (raw.hasOwnProperty(key) && raw[key] && !['geometry','SHAPE_Leng'].includes(key)) {
                            rows += `<tr><td class="popup-key">${key}</td><td class="popup-val">${raw[key]}</td></tr>`;
                        }
                    }
                }
                var content = `
                    <div style="min-width:220px; max-height:250px; overflow-y:auto;">
                        <h6 class="text-primary font-weight-bold border-bottom pb-2 mb-2">${props.name}</h6>
                        <table class="popup-table"><tbody>${rows}</tbody></table>
                    </div>`;
                layer.bindPopup(content);
            }
        }
    }).addTo(map);

    // === 5. LOAD DATA ===
    var abortController = null;

    function loadData() {
        $('#map-loading').fadeIn();
        $('#loading-text').text("Memuat Data...");

        var bounds = map.getBounds();
        var search = $('#searchMap').val();
        var hak = $('#filterHak').val();

        var params = new URLSearchParams({
            north: bounds.getNorth(), south: bounds.getSouth(),
            east: bounds.getEast(), west: bounds.getWest(),
            zoom: map.getZoom(),
            search: search,
            hak: hak
        });

        if (abortController) abortController.abort();
        abortController = new AbortController();

        fetch("{{ route('api.assets') }}?" + params.toString(), { signal: abortController.signal })
            .then(res => res.json())
            .then(data => {
                geoJsonLayer.clearLayers();
                if(data.features && data.features.length > 0) {
                    geoJsonLayer.addData(data);
                    
                    if(data.strategy === 'cluster') $('#loading-text').text("Mode Cluster");
                    else if(data.strategy === 'simplified') $('#loading-text').text("Mode Cepat");
                    else $('#loading-text').text("Mode Detail");
                    
                    setTimeout(() => $('#map-loading').fadeOut(), 1000);
                } else {
                    $('#loading-text').text("Data Kosong");
                    setTimeout(() => $('#map-loading').fadeOut(), 1500);
                }
            })
            .catch(err => { if (err.name !== 'AbortError') $('#map-loading').fadeOut(); });
    }

    map.on('moveend', loadData);
    loadData(); // Load awal

    // === 6. UPLOAD LOGIC ===
    var selectedFiles = [];
    $('#shpFilesInput').on('change', function() {
        selectedFiles = Array.from($(this)[0].files);
        var label = selectedFiles.length > 0 ? selectedFiles.length + ' file dipilih' : 'Pilih file...';
        $(this).next('.custom-file-label').html(label);
        var names = selectedFiles.map(f => f.name).join(', ');
        $('#fileListInfo').text(names.substring(0, 80) + (names.length>80?'...':''));
    });

    async function startBatchUpload() {
        if (selectedFiles.length === 0) { Swal.fire('Warning', 'Pilih file dulu!', 'warning'); return; }
        $('#btnStartUpload').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
        $('#shpFilesInput').prop('disabled', true);
        $('#progressArea').show(); $('#uploadLog').show().html('');

        let successCount = 0; let failCount = 0;
        for (let i = 0; i < selectedFiles.length; i++) {
            let file = selectedFiles[i];
            let percent = Math.round(((i) / selectedFiles.length) * 100);
            $('#progressBar').css('width', percent + '%');
            $('#progressText').text(`Proses ${i+1}/${selectedFiles.length}: ${file.name}`);
            $('#progressPercent').text(percent + '%');

            let formData = new FormData(); formData.append('shp_files[]', file);
            try {
                let response = await fetch("{{ route('asset.uploadShp') }}", {
                    method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }, body: formData
                });
                const type = response.headers.get("content-type");
                if (!type || !type.includes("json")) throw new Error("Server Error HTML");
                let result = await response.json();
                if (response.ok) successCount++; else { failCount++; $('#uploadLog').append(`<div class="text-danger small"><i class="fas fa-times"></i> ${file.name}: ${result.message}</div>`); }
            } catch (error) { failCount++; $('#uploadLog').append(`<div class="text-danger small"><i class="fas fa-times"></i> ${file.name}: ${error.message}</div>`); }
        }
        $('#progressBar').css('width', '100%').addClass(failCount > 0 ? 'bg-warning' : 'bg-success');
        $('#progressText').text('Selesai!'); $('#progressPercent').text('100%');
        $('#btnStartUpload').html('Selesai').removeClass('btn-success').addClass('btn-secondary');
        loadData();
        Swal.fire({ title: 'Selesai', text: `Berhasil: ${successCount}, Gagal: ${failCount}`, icon: failCount === 0 ? 'success' : 'warning' });
    }

    function resetUploadModal() {
        $('#btnStartUpload').prop('disabled', false).html('<i class="fas fa-upload"></i> Mulai Upload').addClass('btn-success').removeClass('btn-secondary');
        $('#shpFilesInput').prop('disabled', false).val('').next('.custom-file-label').html('Pilih file ZIP...');
        $('#progressArea').hide(); $('#uploadLog').hide().html(''); selectedFiles = []; $('#fileListInfo').text('');
    }

    // Draw & Submit Manual
    var drawnItems = new L.FeatureGroup(); map.addLayer(drawnItems);
    var drawControl = new L.Control.Draw({ edit: { featureGroup: drawnItems }, draw: { polygon: true, polyline: false, rectangle: true, circle: false, marker: true } });
    map.addControl(drawControl);
    map.on(L.Draw.Event.CREATED, function (e) {
        var layer = e.layer; $('#geometryData').val(JSON.stringify(layer.toGeoJSON().geometry));
        $('#inputDataModal').modal('show'); drawnItems.addLayer(layer);
    });
    $('.cancel-draw').click(function() { drawnItems.clearLayers(); });
    $('#formAset').submit(function(e){
        e.preventDefault();
        $.ajax({
            url: "{{ route('asset.storeDraw') }}", type: "POST",
            data: { name: $('#namaAset').val(), type: $('#jenisAset').val(), description: $('#ketAset').val(), geometry: $('#geometryData').val(), _token: "{{ csrf_token() }}" },
            success: function(res) { $('#inputDataModal').modal('hide'); $('#formAset')[0].reset(); drawnItems.clearLayers(); loadData(); Swal.fire('Berhasil', res.message, 'success'); },
            error: function() { Swal.fire('Gagal', 'Error server', 'error'); }
        });
    });
    L.Control.geocoder().addTo(map);
</script>
@endpush