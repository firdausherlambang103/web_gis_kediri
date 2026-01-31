@extends('layouts.admin')
@section('title', 'Peta Sebaran Aset')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <style>
        #map { height: 75vh; width: 100%; border-radius: 4px; border: 1px solid #ced4da; }
        #map-loading {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            z-index: 9999; background: rgba(255, 255, 255, 0.9); padding: 10px 20px;
            border-radius: 50px; font-weight: bold; display: none; pointer-events: none;
        }
        .progress-group { display: none; margin-top: 15px; }
        .upload-log { max-height: 100px; overflow-y: auto; font-size: 0.85rem; margin-top: 10px; border: 1px solid #ddd; padding: 5px; background: #f9f9f9; }
    </style>
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-map-marked-alt mr-1"></i> Peta Digital</h3>
                <div class="card-tools">
                    <button class="btn btn-default btn-sm" onclick="map.fitBounds(geoJsonLayer.getBounds())">
                        <i class="fas fa-compress-arrows-alt"></i> Zoom Data
                    </button>
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#uploadModal">
                        <i class="fas fa-file-import"></i> Upload SHP
                    </button>
                </div>
            </div>
            <div class="card-body p-0 position-relative">
                <div id="map-loading"><i class="fas fa-spinner fa-spin text-primary"></i> Memuat Data...</div>
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
                    <i class="fas fa-info-circle"></i> <b>Tips:</b> Pilih banyak file .zip sekaligus. Sistem akan menguploadnya satu per satu agar stabil.
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

    // --- 1. LOGIKA SMART BATCH UPLOAD (Core Solution) ---
    var selectedFiles = [];

    $('#shpFilesInput').on('change', function() {
        selectedFiles = Array.from($(this)[0].files);
        var label = selectedFiles.length > 0 ? selectedFiles.length + ' file dipilih' : 'Pilih file...';
        $(this).next('.custom-file-label').html(label);
        
        var names = selectedFiles.map(f => f.name).join(', ');
        $('#fileListInfo').text(names.substring(0, 80) + (names.length>80?'...':''));
    });

    async function startBatchUpload() {
        if (selectedFiles.length === 0) {
            Swal.fire('Warning', 'Pilih minimal satu file ZIP!', 'warning');
            return;
        }

        // Kunci UI
        $('#btnStartUpload').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
        $('#shpFilesInput').prop('disabled', true);
        $('#progressArea').show();
        $('#uploadLog').show().html('');

        let successCount = 0;
        let failCount = 0;

        // LOOPING UPLOAD (Satu per Satu)
        for (let i = 0; i < selectedFiles.length; i++) {
            let file = selectedFiles[i];
            
            // Update Progress
            let percent = Math.round(((i) / selectedFiles.length) * 100);
            $('#progressBar').css('width', percent + '%');
            $('#progressText').text(`Proses ${i+1} dari ${selectedFiles.length}: ${file.name}`);
            $('#progressPercent').text(percent + '%');

            // Siapkan Data
            let formData = new FormData();
            formData.append('shp_files[]', file); // Array name agar diterima controller

            try {
                let response = await fetch("{{ route('asset.uploadShp') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json' // PENTING: Minta respon JSON
                    },
                    body: formData
                });

                // Cek apakah response JSON valid (bukan error HTML)
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error("Server Error (Bukan JSON). Cek ukuran file/PHP logs.");
                }

                let result = await response.json();

                if (response.ok) {
                    successCount++;
                } else {
                    failCount++;
                    $('#uploadLog').append(`<div class="text-danger small"><i class="fas fa-times"></i> <b>${file.name}</b>: ${result.message}</div>`);
                }

            } catch (error) {
                failCount++;
                $('#uploadLog').append(`<div class="text-danger small"><i class="fas fa-times"></i> <b>${file.name}</b>: ${error.message}</div>`);
            }
        }

        // Selesai
        $('#progressBar').css('width', '100%').addClass(failCount > 0 ? 'bg-warning' : 'bg-success');
        $('#progressText').text('Selesai!');
        $('#progressPercent').text('100%');
        $('#btnStartUpload').html('Selesai').removeClass('btn-success').addClass('btn-secondary');

        // Refresh Peta
        loadData();

        Swal.fire({
            title: 'Selesai',
            text: `Berhasil: ${successCount}, Gagal: ${failCount}`,
            icon: failCount === 0 ? 'success' : 'warning'
        });
    }

    function resetUploadModal() {
        $('#btnStartUpload').prop('disabled', false).html('<i class="fas fa-upload"></i> Mulai Upload').addClass('btn-success').removeClass('btn-secondary');
        $('#shpFilesInput').prop('disabled', false).val('').next('.custom-file-label').html('Pilih file ZIP...');
        $('#progressArea').hide();
        $('#progressBar').css('width', '0%').removeClass('bg-success bg-warning').addClass('bg-primary');
        $('#uploadLog').hide().html('');
        selectedFiles = [];
        $('#fileListInfo').text('');
    }

    // --- 2. LOGIKA PETA & DATA ---
    var map = L.map('map').setView([-7.629, 111.52], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
    var googleSat = L.tileLayer('http://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',{ maxZoom: 20, subdomains:['mt0','mt1','mt2','mt3'] });
    L.control.layers({ "Peta Jalan": L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png'), "Satelit": googleSat }).addTo(map);

    var geoJsonLayer = L.geoJSON(null, {
        style: function(f) { return { color: f.properties.color||'#3388ff', weight: 2 }; },
        onEachFeature: function(f, l) {
            var p = f.properties;
            var c = p.raw_data ? 
                `<b>${p.name}</b><br>NIB: ${p.raw_data.NIB||'-'}<br>Luas: ${p.raw_data.LUASTERTUL||0}` : 
                `<b>${p.name}</b>`;
            l.bindPopup(c);
        }
    }).addTo(map);

    var abortController = null;
    function loadData() {
        // Jangan load jika zoom terlalu jauh (berat)
        if(map.getZoom() < 10) { 
            geoJsonLayer.clearLayers(); 
            $('#map-loading').hide(); 
            return; 
        }
        
        $('#map-loading').show();
        var b = map.getBounds();
        var p = new URLSearchParams({ north:b.getNorth(), south:b.getSouth(), east:b.getEast(), west:b.getWest() });
        
        if(abortController) abortController.abort();
        abortController = new AbortController();

        fetch("{{ route('api.assets') }}?"+p, { signal: abortController.signal })
            .then(r=>r.json()).then(d=>{
                geoJsonLayer.clearLayers();
                if(d.features) geoJsonLayer.addData(d);
                $('#map-loading').hide();
            }).catch(e=>{ if(e.name!=='AbortError') $('#map-loading').hide(); });
    }
    map.on('moveend', loadData);
    loadData();

    // Fitur Draw Manual
    var drawnItems = new L.FeatureGroup(); map.addLayer(drawnItems);
    var drawControl = new L.Control.Draw({ edit: { featureGroup: drawnItems }, draw: { polygon: true, polyline: false, rectangle: true, circle: false, marker: true } });
    map.addControl(drawControl);
    
    map.on(L.Draw.Event.CREATED, function (e) {
        var layer = e.layer; 
        $('#geometryData').val(JSON.stringify(layer.toGeoJSON().geometry));
        $('#inputDataModal').modal('show'); 
        drawnItems.addLayer(layer);
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