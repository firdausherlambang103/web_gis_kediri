@extends('layouts.admin')

@section('title', 'Peta Sebaran Aset')

@push('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
    
    <style>
        #map { 
            height: 75vh; 
            width: 100%; 
            border-radius: 4px; 
            border: 1px solid #ced4da; 
        }
        /* Styling Popup agar lebih rapi */
        .leaflet-popup-content-wrapper { border-radius: 4px; }
        .leaflet-popup-content { margin: 10px; font-size: 13px; line-height: 1.4; }
        .table-popup td { padding: 2px 5px; vertical-align: top; }
    </style>
@endpush

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-map-marked-alt mr-1"></i> Peta Digital Aset
                </h3>
                <div class="card-tools">
                    <button class="btn btn-success btn-sm" data-toggle="modal" data-target="#uploadModal">
                        <i class="fas fa-file-import"></i> Import SHP (.zip)
                    </button>
                </div>
            </div>
            <div class="card-body p-2">
                <div id="map"></div>
            </div>
            </div>
    </div>
</div>

<div class="modal fade" id="inputDataModal" data-backdrop="static">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Input Data Aset Baru</h4>
                <button type="button" class="close cancel-draw" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="formAset">
                    <input type="hidden" id="geometryData" name="geometry">
                    
                    <div class="form-group">
                        <label>Nama Aset <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="namaAset" required placeholder="Contoh: Tanah Wakaf Masjid Al-Hikmah">
                    </div>
                    
                    <div class="form-group">
                        <label>Jenis Aset <span class="text-danger">*</span></label>
                        <select class="form-control" id="jenisAset" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="Tanah">Tanah</option>
                            <option value="Bangunan">Bangunan</option>
                            <option value="Jalan">Jalan / Infrastruktur</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea class="form-control" id="ketAset" rows="3" placeholder="Deskripsi tambahan..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save"></i> Simpan Data
                    </button>
                </form>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-default cancel-draw" data-dismiss="modal">Batal</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="uploadModal">
    <div class="modal-dialog">
        <form action="{{ route('asset.uploadShp') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Upload File Shapefile (SHP)</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                        <h5><i class="icon fas fa-info"></i> Petunjuk!</h5>
                        Pastikan file di-compress menjadi format <strong>.zip</strong>. Di dalam zip harus terdapat file: <code>.shp</code>, <code>.shx</code>, dan <code>.dbf</code>.
                    </div>
                    <div class="form-group">
                        <label>Pilih File ZIP</label>
                        <div class="custom-file">
                            <input type="file" name="shp_file" class="custom-file-input" id="customFile" accept=".zip" required>
                            <label class="custom-file-label" for="customFile">Choose file</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-success"><i class="fas fa-upload"></i> Upload</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>

<script>
    $(document).ready(function () {
        bsCustomFileInput.init(); // Init input file custom bootstrap
    });

    // --- 1. Inisialisasi Peta ---
    var map = L.map('map').setView([-7.629, 111.52], 13); // Default Madiun

    // --- 2. Basemaps (Layer Peta) ---
    var osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap',
        maxZoom: 19
    }).addTo(map);

    var googleSat = L.tileLayer('http://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',{
        maxZoom: 20,
        subdomains:['mt0','mt1','mt2','mt3']
    });

    L.control.layers({ "Peta Jalan": osm, "Satelit": googleSat }).addTo(map);

    // --- 3. Layer GeoJSON dengan Popup Detail ---
    var geoJsonLayer = L.geoJSON(null, {
        style: function(feature) {
            // Style default untuk polygon/line
            return {
                color: feature.properties.color || '#3388ff',
                fillColor: feature.properties.color || '#3388ff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.5
            };
        },
        onEachFeature: function (feature, layer) {
            var props = feature.properties;
            var popupContent = "";

            // LOGIKA: Cek apakah data Import (punya raw_data) atau Manual
            if (props.raw_data) {
                // === TAMPILAN UNTUK DATA IMPORT (SHP) ===
                var raw = props.raw_data;
                
                // Ambil data atribut (gunakan operator OR || '-' jika null)
                var nib = raw.NIB || '-';
                var hak = raw.TIPEHAK || '-';
                var luas = raw.LUASTERTUL || raw.LUASPETA || 0;
                var kec = raw.KECAMATAN || '-';
                var kel = raw.KELURAHAN || '-';
                var guna = raw.PENGGUNAAN || '-';

                popupContent = `
                    <div style="min-width: 220px;">
                        <h6 style="font-weight:bold; color:#007bff; border-bottom:1px solid #ddd; padding-bottom:5px;">
                            <i class="fas fa-file-import"></i> Aset Import
                        </h6>
                        <table class="table-popup" style="width:100%;">
                            <tr><td class="text-muted">NIB</td><td>: <b>${nib}</b></td></tr>
                            <tr><td class="text-muted">Hak</td><td>: ${hak}</td></tr>
                            <tr><td class="text-muted">Luas</td><td>: ${luas} m²</td></tr>
                            <tr><td class="text-muted">Lokasi</td><td>: ${kel}, ${kec}</td></tr>
                            <tr><td class="text-muted">Guna</td><td>: ${guna}</td></tr>
                        </table>
                    </div>
                `;
            } else {
                // === TAMPILAN UNTUK DATA MANUAL (DRAW) ===
                popupContent = `
                    <div style="min-width: 180px;">
                        <h6 style="font-weight:bold; border-bottom:1px solid #ddd; padding-bottom:5px;">
                            ${props.name}
                        </h6>
                        <table class="table-popup" style="width:100%;">
                            <tr><td class="text-muted" width="40px">Jenis</td><td>: ${props.type || '-'}</td></tr>
                            <tr><td class="text-muted">Ket</td><td>: ${props.description || '-'}</td></tr>
                        </table>
                    </div>
                `;
            }

            layer.bindPopup(popupContent);
        }
    }).addTo(map);

    // --- 4. Fungsi Load Data (dengan Auto Zoom Fix) ---
    function loadData() {
        fetch("{{ route('api.assets') }}")
            .then(res => res.json())
            .then(data => {
                geoJsonLayer.clearLayers();
                geoJsonLayer.addData(data);
                
                // Auto Zoom ke data yang ada
                if (geoJsonLayer.getLayers().length > 0) {
                    map.fitBounds(geoJsonLayer.getBounds());
                }
            })
            .catch(err => {
                console.error("Gagal memuat data:", err);
                Swal.fire('Error', 'Gagal memuat data peta.', 'error');
            });
    }

    loadData(); // Load saat pertama dibuka

    // --- 5. Fitur Draw (Gambar Manual) ---
    var drawnItems = new L.FeatureGroup();
    map.addLayer(drawnItems);

    var drawControl = new L.Control.Draw({
        edit: { featureGroup: drawnItems },
        draw: {
            polygon: true,
            polyline: false,
            rectangle: true,
            circle: false,
            marker: true,
            circlemarker: false
        }
    });
    map.addControl(drawControl);

    var currentLayer = null;

    map.on(L.Draw.Event.CREATED, function (event) {
        currentLayer = event.layer;
        
        var geojson = currentLayer.toGeoJSON();
        var geometryJson = JSON.stringify(geojson.geometry);

        $('#geometryData').val(geometryJson);
        $('#inputDataModal').modal('show');
        drawnItems.addLayer(currentLayer);
    });

    $('.cancel-draw').click(function() {
        if(currentLayer) {
            drawnItems.removeLayer(currentLayer);
            currentLayer = null;
        }
    });

    // --- 6. Simpan Data Manual via AJAX ---
    $('#formAset').submit(function(e){
        e.preventDefault();
        
        var formData = {
            name: $('#namaAset').val(),
            type: $('#jenisAset').val(),
            description: $('#ketAset').val(),
            geometry: $('#geometryData').val(),
            _token: "{{ csrf_token() }}"
        };

        $.ajax({
            url: "{{ route('asset.storeDraw') }}",
            type: "POST",
            data: formData,
            beforeSend: function() {
                $('button[type="submit"]').attr('disabled', true).text('Menyimpan...');
            },
            success: function(response) {
                $('#inputDataModal').modal('hide');
                $('#formAset')[0].reset();
                $('button[type="submit"]').attr('disabled', false).html('<i class="fas fa-save"></i> Simpan Data');
                
                drawnItems.clearLayers();
                loadData();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: response.message,
                    timer: 2000,
                    showConfirmButton: false
                });
            },
            error: function(xhr) {
                $('button[type="submit"]').attr('disabled', false).html('<i class="fas fa-save"></i> Simpan Data');
                var msg = xhr.responseJSON ? xhr.responseJSON.message : 'Terjadi kesalahan server.';
                Swal.fire('Gagal!', msg, 'error');
            }
        });
    });

    // --- 7. Geocoder (Pencarian) ---
    L.Control.geocoder({
        defaultMarkGeocode: false
    })
    .on('markgeocode', function(e) {
        var bbox = e.geocode.bbox;
        var poly = L.polygon([
            bbox.getSouthEast(),
            bbox.getNorthEast(),
            bbox.getNorthWest(),
            bbox.getSouthWest()
        ]);
        map.fitBounds(poly.getBounds());
    })
    .addTo(map);

</script>
@endpush