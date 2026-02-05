@extends('layouts.admin')
@section('title', 'Peta Sebaran Aset')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    <style>
        #map { height: calc(100vh - 57px); width: 100%; border: 1px solid #ced4da; }
        
        #map-loading {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            z-index: 2000; background: rgba(255, 255, 255, 0.95); padding: 15px 30px;
            border-radius: 50px; font-weight: bold; display: none; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); color: #333;
        }

        /* Filter Box (Kiri Atas) */
        .map-filter-box {
            position: absolute; top: 10px; right: 10px; z-index: 1000;
            background: rgba(255, 255, 255, 0.95); padding: 15px; border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2); width: 280px;
            backdrop-filter: blur(5px);
        }

        /* Layer Control (Kanan Atas - Bawah Filter) */
        .layer-control-box {
            position: absolute; top: 280px; right: 10px; z-index: 1000;
            background: rgba(255, 255, 255, 0.95); padding: 10px; border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2); width: 280px; 
            max-height: 300px; overflow-y: auto;
            backdrop-filter: blur(5px);
        }

        .cluster-marker {
            background-color: rgba(220, 53, 69, 0.85);
            border: 3px solid white; border-radius: 50%;
            color: white; font-weight: bold; text-align: center;
            line-height: 30px; box-shadow: 0 3px 5px rgba(0,0,0,0.3);
        }

        .popup-table { width: 100%; font-size: 12px; margin-top: 5px; margin-bottom: 10px; }
        .popup-table td { padding: 4px 6px; border-bottom: 1px solid #f0f0f0; vertical-align: top; }
        .bg-label { background-color: #f8f9fa; font-weight: 600; color: #555; width: 40%; }
        
        .progress-group { display: none; margin-top: 15px; }
        .upload-log { max-height: 100px; overflow-y: auto; font-size: 0.85rem; margin-top: 10px; border: 1px solid #ddd; padding: 5px; background: #f9f9f9; }
    </style>
@endpush

@section('content')
<div class="container-fluid p-0 position-relative">
    <div id="map-loading">
        <i class="fas fa-circle-notch fa-spin text-primary mr-2"></i> <span id="loading-text">Memuat Data...</span>
    </div>

    <div class="map-filter-box">
        <h6 class="font-weight-bold mb-3"><i class="fas fa-search text-primary"></i> Filter Peta</h6>
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
                <option value="KOSONG">Belum Ada Hak</option>
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

    <div class="layer-control-box">
        <h6 class="font-weight-bold mb-2"><i class="fas fa-layer-group text-primary"></i> Layer Aktif</h6>
        <div id="layerList" class="mb-2">
            @forelse($layers as $layer)
            <div class="custom-control custom-checkbox mb-1">
                <input type="checkbox" class="custom-control-input layer-checkbox" 
                       id="layer_{{ $layer->id }}" value="{{ $layer->id }}" checked
                       data-color="{{ $layer->color }}">
                <label class="custom-control-label small" for="layer_{{ $layer->id }}">
                    <span class="badge badge-dot mr-1" style="background-color: {{ $layer->color }}; width: 10px; height: 10px; display: inline-block; border-radius: 50%;"></span>
                    {{ $layer->name }}
                </label>
            </div>
            @empty
                <p class="text-muted small">Belum ada layer.</p>
            @endforelse
        </div>
        <button class="btn btn-xs btn-outline-primary btn-block" data-toggle="modal" data-target="#modalAddLayer">
            <i class="fas fa-plus mr-1"></i> Buat Layer Baru
        </button>
    </div>

    <div id="map"></div>
</div>

<div class="modal fade" id="modalAddLayer">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Layer Baru</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Nama Layer</label>
                    <input type="text" id="newLayerName" class="form-control" placeholder="Misal: Jalan, Sungai...">
                </div>
                <div class="form-group">
                    <label>Warna Default</label>
                    <input type="color" id="newLayerColor" class="form-control" value="#3388ff">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-block" onclick="createNewLayer()">Simpan</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDraw" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit mr-2"></i>Simpan Data Bidang</h5>
                <button type="button" class="close text-white" onclick="cancelDraw()"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <form id="formDraw">
                    <input type="hidden" id="drawGeometry">
                    
                    <div class="form-group">
                        <label>Layer Tujuan</label>
                        <select id="drawLayerId" class="form-control">
                            <option value="">-- Tanpa Layer (Default) --</option>
                            @foreach($layers as $layer)
                                <option value="{{ $layer->id }}">{{ $layer->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Nama Aset / Bidang <span class="text-danger">*</span></label>
                        <input type="text" id="drawName" class="form-control" placeholder="Contoh: Tanah Wakaf Masjid..." required>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Status / Tipe Hak <span class="text-danger">*</span></label>
                                <select id="drawStatus" class="form-control">
                                    <option value="Hak Milik">Hak Milik (HM)</option>
                                    <option value="Hak Pakai">Hak Pakai (HP)</option>
                                    <option value="Hak Guna Bangunan">HGB</option>
                                    <option value="Wakaf">Tanah Wakaf</option>
                                    <option value="Tanah Negara">Tanah Negara</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Warna Blok <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="color" id="drawColor" class="form-control" value="#ff0000" style="height: 38px;">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Kecamatan</label>
                                <input type="text" id="drawKec" class="form-control" placeholder="Nama Kecamatan">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Desa/Kelurahan</label>
                                <input type="text" id="drawDesa" class="form-control" placeholder="Nama Desa">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Keterangan / Penggunaan</label>
                        <textarea id="drawDesc" class="form-control" rows="2" placeholder="Contoh: Sawah, Kebun, Bangunan..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="cancelDraw()">Batal</button>
                <button type="button" class="btn btn-primary" onclick="saveDraw()">Simpan Aset</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadModal" data-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Upload SHP ke Layer</h4>
                <button type="button" class="close" onclick="resetUploadModal()" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                
                <div class="form-group">
                    <label>Pilih Layer Tujuan <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <select id="uploadLayerSelect" class="form-control">
                            <option value="">-- Pilih Layer --</option>
                            @foreach($layers as $layer)
                                <option value="{{ $layer->id }}">{{ $layer->name }}</option>
                            @endforeach
                        </select>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" data-toggle="modal" data-target="#modalAddLayer"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info small mt-2"><i class="fas fa-info-circle"></i> Pilih banyak file <b>.zip</b> sekaligus.</div>
                <div class="form-group">
                    <div class="custom-file">
                        <input type="file" class="custom-file-input" id="shpFilesInput" accept=".zip" multiple>
                        <label class="custom-file-label" for="shpFilesInput">Pilih file ZIP...</label>
                    </div>
                    <small id="fileListInfo" class="text-muted mt-2"></small>
                </div>
                <div class="progress-group" id="progressArea">
                    <div class="d-flex justify-content-between small mb-1">
                        <span id="progressText">Menyiapkan...</span><span id="progressPercent">0%</span>
                    </div>
                    <div class="progress progress-sm"><div class="progress-bar bg-primary" id="progressBar" style="width: 0%"></div></div>
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

    // === 1. TANGKAP PARAMETER URL ===
    const urlParams = new URLSearchParams(window.location.search);
    const paramLat = urlParams.get('lat');
    const paramLng = urlParams.get('lng');
    const paramSearch = urlParams.get('search');
    const paramHak = urlParams.get('hak');

    if(paramSearch) $('#searchMap').val(paramSearch);
    if(paramHak) $('#filterHak').val(paramHak);

    // === 2. INIT PETA ===
    var startLat = paramLat ? parseFloat(paramLat) : -7.8;
    var startLng = paramLng ? parseFloat(paramLng) : 112.0;
    var startZoom = paramLat ? 18 : 12;

    var map = L.map('map', { zoomControl: false }).setView([startLat, startLng], startZoom); 
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: 'OSM' });
    var googleSat = L.tileLayer('http://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',{ maxZoom: 20, subdomains:['mt0','mt1','mt2','mt3'] });
    osm.addTo(map);
    L.control.layers({ "Peta Jalan": osm, "Satelit": googleSat }, null, { position: 'bottomright' }).addTo(map);
    L.Control.geocoder({ position: 'topleft' }).addTo(map);

    if(paramLat && paramLng) {
        L.marker([startLat, startLng]).addTo(map).bindPopup("<b>Lokasi Terpilih</b><br>" + (paramSearch || "")).openPopup();
    }

    // === 3. MANAJEMEN LAYER ===
    
    // a. Buat Layer Baru
    function createNewLayer() {
        var name = $('#newLayerName').val();
        var color = $('#newLayerColor').val();
        
        if(!name) { Swal.fire('Error', 'Nama Layer wajib diisi', 'error'); return; }

        $.post("{{ route('layer.store') }}", {
            _token: "{{ csrf_token() }}", name: name, color: color
        }, function(res) {
            $('#modalAddLayer').modal('hide');
            Swal.fire('Sukses', 'Layer berhasil dibuat', 'success').then(() => location.reload());
        }).fail(function() {
            Swal.fire('Gagal', 'Terjadi kesalahan', 'error');
        });
    }

    // b. Filter Layer Checkbox
    $('.layer-checkbox').change(function() {
        loadData(); // Reload peta saat layer dicentang/uncentang
    });

    // === 4. GAMBAR MANUAL ===
    var drawnItems = new L.FeatureGroup(); map.addLayer(drawnItems);
    var drawControl = new L.Control.Draw({ edit: { featureGroup: drawnItems }, draw: { polygon: true, rectangle: true, marker: true, circle: false, polyline: false, circlemarker: false } });
    map.addControl(drawControl);

    var currentLayer = null;
    map.on(L.Draw.Event.CREATED, function (e) {
        currentLayer = e.layer;
        var geojson = currentLayer.toGeoJSON();
        $('#drawGeometry').val(JSON.stringify(geojson.geometry));
        $('#modalDraw').modal('show');
        drawnItems.addLayer(currentLayer);
    });

    function cancelDraw() {
        if(currentLayer) drawnItems.removeLayer(currentLayer);
        $('#modalDraw').modal('hide'); $('#formDraw')[0].reset();
    }

    function saveDraw() {
        var name = $('#drawName').val();
        var status = $('#drawStatus').val();
        if(!name || !status) { Swal.fire('Error', 'Nama dan Status wajib diisi!', 'error'); return; }

        $('#modalDraw').modal('hide'); $('#map-loading').fadeIn(); $('#loading-text').text("Menyimpan...");
        $.ajax({
            url: "{{ route('asset.storeDraw') }}", type: "POST",
            data: {
                _token: "{{ csrf_token() }}", name: name, status: status, 
                color: $('#drawColor').val(), layer_id: $('#drawLayerId').val(), // Kirim Layer ID
                kecamatan: $('#drawKec').val(), desa: $('#drawDesa').val(), 
                description: $('#drawDesc').val(), geometry: $('#drawGeometry').val()
            },
            success: function(res) {
                $('#map-loading').fadeOut(); Swal.fire('Berhasil', res.message, 'success');
                $('#formDraw')[0].reset(); drawnItems.clearLayers(); loadData();
            },
            error: function(err) { $('#map-loading').fadeOut(); Swal.fire('Gagal', 'Terjadi kesalahan server.', 'error'); }
        });
    }

    // === 5. LOAD DATA & VISUALISASI ===
    
    // Fungsi warna diperbarui untuk mendukung Warna Layer
    function getColor(props) {
        // 1. Cek jika ada warna manual di properti
        if (props.color && props.color !== '#ff0000') return props.color;
        
        // 2. Cek warna dari layer (jika ada)
        if (props.layer_color) return props.layer_color;

        // 3. Fallback ke warna tipe hak (Logic lama)
        var raw = props.raw_data || {};
        var tipe = raw.TIPEHAK || raw.TIPE_HAK || 'Import';
        tipe = tipe.toString().toUpperCase();
        
        if (tipe.includes('HM') || tipe.includes('MILIK')) return '#28a745';
        if (tipe.includes('HGB') || tipe.includes('BANGUNAN')) return '#ffc107';
        if (tipe.includes('HP') || tipe.includes('PAKAI')) return '#17a2b8';
        if (tipe.includes('WAKAF')) return '#6f42c1';
        return '#3388ff';
    }

    var selectedLayer = null;
    var geoJsonLayer = L.geoJSON(null, {
        style: function(feature) {
            var col = getColor(feature.properties || {});
            return { color: col, fillColor: col, weight: 1, opacity: 1, fillOpacity: 0.5 };
        },
        pointToLayer: function(feature, latlng) {
            if (feature.properties.type === 'cluster') {
                var size = feature.properties.count > 100 ? 40 : 30;
                return L.marker(latlng, { icon: L.divIcon({ className: 'cluster-marker', html: feature.properties.count, iconSize: [size, size] }) });
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
                    selectedLayer.setStyle({ color: '#ff0000', weight: 3, fillOpacity: 0.8 });
                    selectedLayer.bringToFront();
                });

                var p = feature.properties;
                var raw = p.raw_data || {};
                var tipe = raw.TIPEHAK || raw.TIPE_HAK || '-';
                var luas = raw.LUASTERTUL || raw.LUAS || 0;
                var desa = raw.KELURAHAN || raw.DESA || '-';
                var kec = raw.KECAMATAN || '-';
                var ket = p.description || raw.PENGGUNAAN || '-';

                var content = `
                    <div style="min-width:220px;">
                        <h6 class="text-primary font-weight-bold border-bottom pb-2 mb-2">${p.name}</h6>
                        <table class="popup-table">
                            <tr><td class="bg-label">Status</td><td>${tipe}</td></tr>
                            <tr><td class="bg-label">Luas</td><td>${parseFloat(luas).toLocaleString('id-ID')} m²</td></tr>
                            <tr><td class="bg-label">Lokasi</td><td>${desa}, ${kec}</td></tr>
                            <tr><td class="bg-label">Ket</td><td>${ket}</td></tr>
                        </table>
                        <div class="mt-2 d-flex justify-content-between">
                            <button class="btn btn-xs btn-warning text-white" onclick="editAsset(${p.id})"><i class="fas fa-edit"></i> Edit</button>
                            <button class="btn btn-xs btn-danger" onclick="deleteAsset(${p.id})"><i class="fas fa-trash"></i> Hapus</button>
                            <a href="/aset?search=${p.name}" target="_blank" class="btn btn-xs btn-outline-info">Detail</a>
                        </div>
                    </div>`;
                layer.bindPopup(content);
            }
        }
    }).addTo(map);

    var abortController = null;
    function loadData() {
        $('#map-loading').fadeIn(); $('#loading-text').text("Memuat Data...");
        
        // Ambil Layer yang dicentang
        var selectedLayers = [];
        $('.layer-checkbox:checked').each(function() { selectedLayers.push($(this).val()); });

        var params = new URLSearchParams({
            north: map.getBounds().getNorth(), south: map.getBounds().getSouth(),
            east: map.getBounds().getEast(), west: map.getBounds().getWest(),
            zoom: map.getZoom(), search: $('#searchMap').val(), hak: $('#filterHak').val()
        });

        // Append array layer ke URL (Manual karena URLSearchParams tidak support array PHP style)
        selectedLayers.forEach(id => params.append('layers[]', id));

        if (abortController) abortController.abort();
        abortController = new AbortController();

        fetch("{{ route('api.assets') }}?" + params.toString(), { signal: abortController.signal })
            .then(res => res.json())
            .then(data => {
                geoJsonLayer.clearLayers();
                if(data.features && data.features.length > 0) {
                    geoJsonLayer.addData(data);
                    $('#loading-text').text(data.strategy === 'cluster' ? "Mode Cluster" : "Selesai");
                    setTimeout(() => $('#map-loading').fadeOut(), 800);
                } else {
                    $('#loading-text').text("Data Kosong"); setTimeout(() => $('#map-loading').fadeOut(), 1000);
                }
            })
            .catch(err => { if (err.name !== 'AbortError') $('#map-loading').fadeOut(); });
    }
    map.on('moveend', loadData); loadData();

    // === 6. UPLOAD LOGIC (Dengan Layer) ===
    var selectedFiles = [];
    $('#shpFilesInput').on('change', function() {
        selectedFiles = Array.from($(this)[0].files);
        $(this).next('.custom-file-label').html(selectedFiles.length > 0 ? selectedFiles.length + ' file dipilih' : 'Pilih file...');
    });

    async function startBatchUpload() {
        // 1. Validasi Input
        var layerId = $('#uploadLayerSelect').val();
        if(!layerId) { Swal.fire('Error', 'Pilih Layer Tujuan dulu!', 'error'); return; }
        
        if (selectedFiles.length === 0) { Swal.fire('Warning', 'Pilih file dulu!', 'warning'); return; }
        
        // 2. Siapkan UI
        $('#btnStartUpload').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Memproses...');
        $('#progressArea').show(); 
        $('#uploadLog').show().html('');
        
        let successCount = 0; 
        let failCount = 0;
        let errorDetails = []; // Array untuk menampung pesan error spesifik

        // 3. Loop Upload File
        for (let i = 0; i < selectedFiles.length; i++) {
            let file = selectedFiles[i];
            
            // Update Progress Bar
            let percent = Math.round(((i) / selectedFiles.length) * 100);
            $('#progressBar').css('width', percent + '%'); 
            $('#progressText').text(`Proses ${i+1}/${selectedFiles.length}`); 
            $('#progressPercent').text(percent + '%');
            
            let formData = new FormData(); 
            formData.append('shp_files[]', file);
            formData.append('layer_id', layerId);

            try {
                // Kirim ke Server
                let response = await fetch("{{ route('asset.uploadShp') }}", { 
                    method: 'POST', 
                    headers: { 
                        'X-CSRF-TOKEN': '{{ csrf_token() }}', 
                        'Accept': 'application/json' 
                    }, 
                    body: formData 
                });
                
                let result = await response.json();
                
                if (response.ok) {
                    successCount++; 
                } else { 
                    failCount++; 
                    // TANGKAP PESAN ERROR DARI CONTROLLER
                    let msg = result.message || 'Gagal tanpa pesan spesifik';
                    
                    // Simpan ke list error untuk Popup nanti
                    errorDetails.push(`<b>${file.name}</b>: <span class="text-danger">${msg}</span>`);
                    
                    // Tampilkan di log kecil bawah progress bar
                    $('#uploadLog').append(`<div class="text-danger small border-bottom py-1"><i class="fas fa-times"></i> ${file.name}: ${msg}</div>`); 
                }
            } catch (error) { 
                failCount++; 
                errorDetails.push(`<b>${file.name}</b>: Masalah Koneksi / Server Timeout`);
                $('#uploadLog').append(`<div class="text-danger small"><i class="fas fa-times"></i> ${file.name}: Error Koneksi</div>`); 
            }
        }
        
        // 4. Finalisasi UI
        $('#progressBar').css('width', '100%').addClass(failCount > 0 ? 'bg-warning' : 'bg-success');
        $('#progressText').text('Selesai!'); $('#progressPercent').text('100%');
        $('#btnStartUpload').html('Selesai').removeClass('btn-success').addClass('btn-secondary');
        
        loadData(); // Refresh Peta
        
        // === 5. TAMPILKAN POPUP HASIL (DENGAN DETAIL ERROR) ===
        if(failCount > 0) {
            Swal.fire({
                title: 'Proses Selesai (Ada Gagal)',
                icon: 'warning',
                width: 700,
                html: `
                    <div class="text-left">
                        <p class="mb-2">
                            <span class="badge badge-success p-2">Berhasil: ${successCount}</span>
                            <span class="badge badge-danger p-2">Gagal: ${failCount}</span>
                        </p>
                        <div class="card bg-light">
                            <div class="card-body p-2" style="max-height: 300px; overflow-y: auto; font-size: 0.85rem; text-align: left;">
                                ${errorDetails.join('<hr class="my-1">')}
                            </div>
                        </div>
                    </div>
                `
            });
        } else {
            Swal.fire('Sukses', `Berhasil upload ${successCount} file!`, 'success');
        }
    }
    
    function resetUploadModal() {
        $('#btnStartUpload').prop('disabled', false).html('<i class="fas fa-upload"></i> Mulai Upload').addClass('btn-success').removeClass('btn-secondary');
        $('#progressArea').hide(); $('#uploadLog').hide().html(''); selectedFiles = [];
    }

    // === 7. FUNGSI EDIT & DELETE (Integrasi dari kode sebelumnya) ===
    function editAsset(id) {
        $.get('/asset/' + id, function(data) {
            $('#editId').val(data.id);
            $('#editName').val(data.name);
            $('#editStatus').val(data.status);
            $('#editKec').val(data.kecamatan);
            $('#editDesa').val(data.desa);
            $('#editLuas').val(data.luas);
            $('#editDesc').val(data.description);
            $('#editColor').val(data.color);
            $('#modalEdit').modal('show');
        }).fail(function() { Swal.fire('Error', 'Gagal mengambil data', 'error'); });
    }

    $('#formEdit').submit(function(e) {
        e.preventDefault();
        var id = $('#editId').val();
        $.ajax({
            url: '/asset/' + id, type: 'PUT',
            data: {
                _token: '{{ csrf_token() }}', name: $('#editName').val(), status: $('#editStatus').val(),
                kecamatan: $('#editKec').val(), desa: $('#editDesa').val(), luas: $('#editLuas').val(), description: $('#editDesc').val(), color: $('#editColor').val()
            },
            success: function(res) {
                $('#modalEdit').modal('hide'); Swal.fire('Sukses', res.message, 'success'); loadData();
            },
            error: function(err) { Swal.fire('Gagal', 'Terjadi kesalahan server', 'error'); }
        });
    });

    function deleteAsset(id) {
        Swal.fire({ title: 'Yakin hapus data?', text: "Tidak bisa dikembalikan!", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Ya, Hapus!' }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/asset/' + id, type: 'DELETE', data: { _token: '{{ csrf_token() }}' },
                    success: function(res) { Swal.fire('Terhapus!', res.message, 'success'); loadData(); },
                    error: function() { Swal.fire('Gagal', 'Error saat menghapus', 'error'); }
                });
            }
        });
    }
</script>
@endpush