@extends('layouts.admin')

@section('title', 'Statistik & Analisis Aset')

@section('content')
<div class="container-fluid">
    
    {{-- 1. DASHBOARD RINGKASAN (INFO BOX) --}}
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card shadow-sm border-left-primary">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="m-0 font-weight-bold text-primary">Dashboard Statistik</h4>
                        <small class="text-muted">Analisis persebaran aset, tipe hak, dan tumpang tindih lahan.</small>
                    </div>
                    
                    {{-- Form Filter Utama (GET) untuk refresh halaman/chart --}}
                    <form method="GET" action="{{ route('statistics.index') }}" class="form-inline">
                        {{-- Filter Layer (Dropdown) --}}
                        <div class="input-group mr-2">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-layer-group"></i></span>
                            </div>
                            <select name="layer_id" class="custom-select" onchange="this.form.submit()">
                                <option value="">-- Semua Layer --</option>
                                @foreach($layers as $layer)
                                    <option value="{{ $layer->id }}" {{ request('layer_id') == $layer->id ? 'selected' : '' }}>
                                        {{ $layer->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Filter Kecamatan --}}
                        <div class="input-group mr-2">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-map-marker-alt"></i></span>
                            </div>
                            <input type="text" name="kecamatan" class="form-control" placeholder="Kecamatan..." value="{{ $kecamatan ?? '' }}">
                        </div>

                        {{-- Tombol Filter --}}
                        <button type="submit" class="btn btn-primary shadow-sm"><i class="fas fa-filter mr-1"></i> Filter</button>
                        <a href="{{ route('statistics.index') }}" class="btn btn-light border ml-2"><i class="fas fa-sync-alt"></i></a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Info Box Stats --}}
    @if(isset($statsHak)) {{-- Cek variable agar tidak error saat load awal --}}
    <div class="row">
        <div class="col-12 col-sm-6 col-md-4">
            <div class="info-box mb-3 shadow-sm p-3">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-ruler-combined"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Luas Terpetakan</span>
                    <span class="info-box-number display-4 text-success" style="font-size: 2rem;">
                        {{ number_format($totalLuasTerpetakan ?? 0, 2) }} <small>Ha</small>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
            <div class="info-box mb-3 shadow-sm p-3">
                <span class="info-box-icon bg-primary elevation-1"><i class="fas fa-layer-group"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Bidang Aset</span>
                    <span class="info-box-number text-primary" style="font-size: 2rem;">
                        {{ number_format($statsHak->sum('total') ?? 0) }} <small>Bidang</small>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-md-4">
            <div class="info-box mb-3 shadow-sm p-3">
                <span class="info-box-icon bg-danger elevation-1"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Potensi Tumpang Tindih</span>
                    <span class="info-box-number text-danger" style="font-size: 2rem;">
                        {{ number_format($overlaps->total() ?? 0) }} <small>Kasus</small>
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- Chart Area --}}
    <div class="row">
        <div class="col-md-5">
            <div class="card card-warning card-outline shadow h-100">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-chart-pie mr-2 text-warning"></i>Proporsi Tipe Hak</h3>
                </div>
                <div class="card-body">
                    <div style="height: 350px;"> <canvas id="chartHak"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card card-success card-outline shadow h-100">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark"><i class="fas fa-chart-bar mr-2 text-success"></i>20 Desa dengan Aset Terluas</h3>
                </div>
                <div class="card-body">
                    <div style="height: 400px;"> <canvas id="chartDesa"></canvas></div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- 2. BAGIAN DATA TABEL & EXPORT --}}
    <div class="row mt-4">
        <div class="col-12">
            <div class="card card-secondary card-outline shadow">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h3 class="card-title font-weight-bold text-secondary">
                        <i class="fas fa-table mr-2"></i>Data Detail Aset
                    </h3>
                    
                    {{-- Form Filter AJAX & Export --}}
                    <form id="ajaxFilterForm" class="form-inline">
                        <input type="hidden" name="layer_id" value="{{ request('layer_id') }}">
                        <input type="hidden" name="kecamatan" value="{{ request('kecamatan') }}">
                        
                        <select name="hak" class="custom-select mr-2">
                            <option value="">-- Semua Hak --</option>
                            <option value="HM">Hak Milik</option>
                            <option value="HGB">HGB</option>
                            <option value="HGU">HGU</option>
                            <option value="HP">Hak Pakai</option>
                            <option value="WAKAF">Wakaf</option>
                        </select>

                        <button type="button" class="btn btn-primary mr-2" onclick="loadAjaxTable()">
                            <i class="fas fa-search mr-1"></i> Tampilkan
                        </button>
                        
                        <button type="button" class="btn btn-success" onclick="exportExcel()">
                            <i class="fas fa-file-excel mr-1"></i> Export Excel
                        </button>
                    </form>
                </div>
                
                {{-- Area Tabel AJAX --}}
                <div class="card-body p-0 table-responsive" id="ajaxResultArea">
                    <div class="p-5 text-center text-muted">
                        <i class="fas fa-search fa-3x mb-3 text-gray-300"></i><br>
                        Klik tombol "Tampilkan" untuk melihat data detail.
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // --- 1. Fungsi AJAX Table ---
    function loadAjaxTable() {
        let formData = $('#ajaxFilterForm').serialize();
        
        $('#ajaxResultArea').html('<div class="p-5 text-center"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><br>Sedang memuat data...</div>');

        $.post("{{ route('statistics.run') }}", formData + "&_token={{ csrf_token() }}", function(res) {
            if(res.status == 'success') {
                $('#ajaxResultArea').html(res.html);
            } else {
                $('#ajaxResultArea').html('<div class="alert alert-danger m-3">Gagal memuat data.</div>');
            }
        }).fail(function() {
            $('#ajaxResultArea').html('<div class="alert alert-danger m-3">Terjadi kesalahan server.</div>');
        });
    }

    // --- 2. Fungsi Export Excel ---
    function exportExcel() {
        // Ambil nilai dari form filter yang aktif
        // Kita bisa ambil dari parameter URL (filter global) + filter lokal di form AJAX
        let urlParams = new URLSearchParams(window.location.search);
        let layerId = urlParams.get('layer_id') || $('input[name="layer_id"]').val() || '';
        let kecamatan = urlParams.get('kecamatan') || $('input[name="kecamatan"]').val() || '';
        let hak = $('select[name="hak"]').val();

        // Redirect ke route export
        let url = "{{ route('statistics.export') }}?" + 
                  "layer_id=" + layerId + 
                  "&kecamatan=" + kecamatan + 
                  "&hak=" + hak;
        
        window.open(url, '_blank');
    }

    // --- 3. Inisialisasi Chart (Jika Data Ada) ---
    $(document).ready(function() {
        @if(isset($statsHak))
            // Chart Hak
            var ctxHak = document.getElementById('chartHak').getContext('2d');
            var dataHak = @json($statsHak);
            var colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6610f2', '#e83e8c', '#fd7e14', '#6c757d'];

            new Chart(ctxHak, {
                type: 'doughnut',
                data: {
                    labels: dataHak.map(x => x.label),
                    datasets: [{
                        data: dataHak.map(x => x.total),
                        backgroundColor: colors,
                        borderWidth: 2,
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    responsive: true,
                    plugins: {
                        legend: { position: 'right' }
                    },
                    layout: { padding: 20 }
                }
            });

            // Chart Desa
            var ctxDesa = document.getElementById('chartDesa').getContext('2d');
            var dataDesa = @json($statsDesa);
            
            new Chart(ctxDesa, {
                type: 'bar',
                data: {
                    labels: dataDesa.map(x => x.desa),
                    datasets: [{
                        label: 'Luas Aset (Hektar)',
                        data: dataDesa.map(x => x.luas_hektar),
                        backgroundColor: 'rgba(40, 167, 69, 0.7)',
                        borderColor: '#28a745',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { x: { beginAtZero: true } }
                }
            });
        @endif
    });
</script>
@endpush