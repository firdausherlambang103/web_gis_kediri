@extends('layouts.admin')

@section('title', 'Statistik & Analisis Aset')

@section('content')
<div class="container-fluid">
    
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card shadow-sm border-left-primary">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div>
                        <h4 class="m-0 font-weight-bold text-primary">Dashboard Statistik</h4>
                        <small class="text-muted">Analisis persebaran aset, tipe hak, dan tumpang tindih lahan.</small>
                    </div>
                    <form method="GET" action="{{ route('statistics.index') }}" class="form-inline">
                        <div class="input-group mr-2">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-map-marker-alt"></i></span>
                            </div>
                            <input type="text" name="kecamatan" class="form-control" placeholder="Kecamatan..." value="{{ $kecamatan }}">
                        </div>
                        <div class="input-group mr-2">
                            <div class="input-group-prepend">
                                <span class="input-group-text bg-white"><i class="fas fa-home"></i></span>
                            </div>
                            <input type="text" name="desa" class="form-control" placeholder="Desa..." value="{{ $desa }}">
                        </div>
                        <button type="submit" class="btn btn-primary shadow-sm"><i class="fas fa-filter mr-1"></i> Filter</button>
                        <a href="{{ route('statistics.index') }}" class="btn btn-light border ml-2"><i class="fas fa-sync-alt"></i></a>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-sm-6 col-md-4">
            <div class="info-box mb-3 shadow-sm p-3">
                <span class="info-box-icon bg-success elevation-1"><i class="fas fa-ruler-combined"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted">Total Luas Terpetakan</span>
                    <span class="info-box-number display-4 text-success" style="font-size: 2rem;">
                        {{ number_format($totalLuasTerpetakan, 2) }} <small>Ha</small>
                    </span>
                    <span class="progress-description text-xs text-muted">
                        Berdasarkan data {{ $kecamatan ?: 'seluruh' }} {{ $desa }}
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
                        {{ number_format($statsHak->sum('total')) }} <small>Bidang</small>
                    </span>
                    <span class="progress-description text-xs text-muted">
                        Jumlah poligon yang tersimpan
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
                        {{ number_format($overlaps->total()) }} <small>Kasus</small>
                    </span>
                    <span class="progress-description text-xs text-muted">
                        Bidang yang saling beririsan
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="card card-warning card-outline shadow h-100">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-chart-pie mr-2 text-warning"></i>Proporsi Tipe Hak
                    </h3>
                </div>
                <div class="card-body">
                    <div style="height: 350px;"> <canvas id="chartHak"></canvas>
                    </div>
                </div>
                <div class="card-footer bg-white p-0">
                    <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-striped mb-0 text-sm">
                            <thead>
                                <tr>
                                    <th>Tipe Hak</th>
                                    <th class="text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($statsHak as $s)
                                <tr>
                                    <td><i class="fas fa-circle text-xs mr-2" style="color: #6c757d"></i>{{ $s->label }}</td>
                                    <td class="text-right font-weight-bold">{{ number_format($s->total) }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="card card-success card-outline shadow h-100">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-dark">
                        <i class="fas fa-chart-bar mr-2 text-success"></i>20 Desa dengan Aset Terluas
                    </h3>
                </div>
                <div class="card-body">
                    <div style="height: 400px;"> <canvas id="chartDesa"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card card-danger card-outline shadow">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold text-danger">
                        <i class="fas fa-fire mr-2"></i>10 Desa dengan Kasus Tumpang Tindih Terbanyak
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="pl-4" style="width: 5%">No</th>
                                    <th>Nama Desa</th>
                                    <th class="text-right">Jumlah Kasus</th>
                                    <th class="text-right pr-4">Total Luas Overlap (m²)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($topOverlapVillages as $index => $village)
                                <tr>
                                    <td class="pl-4">{{ $index + 1 }}</td>
                                    <td class="font-weight-bold">{{ $village->desa }}</td>
                                    <td class="text-right">
                                        <span class="badge badge-danger p-2" style="font-size: 14px">
                                            {{ number_format($village->total_kasus) }}
                                        </span>
                                    </td>
                                    <td class="text-right pr-4 font-weight-bold">
                                        {{ number_format($village->total_luas, 2) }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle text-success mb-2"></i><br>
                                        Tidak ada data tumpang tindih yang signifikan.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card card-secondary card-outline shadow">
                <div class="card-header border-0 d-flex justify-content-between align-items-center bg-white">
                    <h3 class="card-title text-secondary font-weight-bold">
                        <i class="fas fa-list mr-2"></i>Detail Data Tumpang Tindih
                    </h3>
                    
                    <div>
                        @if(isset($lastUpdate))
                            <span class="badge badge-light border mr-2 p-2">
                                <i class="far fa-clock mr-1"></i> Update: {{ \Carbon\Carbon::parse($lastUpdate)->diffForHumans() }}
                            </span>
                        @endif
                        <form action="{{ route('statistics.run') }}" method="POST" style="display:inline">
                            @csrf
                            <button type="submit" class="btn btn-danger btn-sm shadow-sm" onclick="return confirm('Jalankan analisis ulang? Proses ini berjalan di background.')">
                                <i class="fas fa-sync mr-1"></i> Update Analisis
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover table-striped text-nowrap">
                        <thead class="bg-light">
                            <tr>
                                <th class="pl-4">Aset Pertama</th>
                                <th>Aset Kedua (Overlap)</th>
                                <th>Lokasi</th>
                                <th class="text-right">Luas Overlap</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($overlaps as $ov)
                            <tr>
                                <td class="pl-4 align-middle">
                                    <div class="font-weight-bold text-primary">{{ $ov->aset_1 }}</div>
                                    <small class="text-muted">ID: {{ $ov->id_1 }}</small>
                                </td>
                                <td class="align-middle">
                                    <div class="font-weight-bold text-danger">{{ $ov->aset_2 }}</div>
                                    <small class="text-muted">ID: {{ $ov->id_2 }}</small>
                                </td>
                                <td class="align-middle">
                                    {{ $ov->desa }}<br>
                                    <small class="text-muted">Kec. {{ $ov->kecamatan }}</small>
                                </td>
                                <td class="align-middle text-right font-weight-bold">
                                    {{ number_format($ov->luas_overlap, 2) }} m²
                                </td>
                                <td class="align-middle text-center">
                                    <a href="{{ route('dashboard', ['search' => $ov->aset_1]) }}" target="_blank" class="btn btn-xs btn-outline-info shadow-sm">
                                        <i class="fas fa-search-location mr-1"></i> Cek Peta
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <h5>Tidak ada data tumpang tindih.</h5>
                                    <p>Data aset Anda aman atau analisis belum dijalankan.</p>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white">
                    <div class="float-right">
                        {{ $overlaps->withQueryString()->links('pagination::bootstrap-4') }}
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
    $(document).ready(function() {
        // --- CHART TIPE HAK (DOUGHNUT BESAR) ---
        var ctxHak = document.getElementById('chartHak').getContext('2d');
        var dataHak = @json($statsHak);
        
        // Warna-warni cerah
        var colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#17a2b8', '#6610f2', '#e83e8c', '#fd7e14', '#6c757d'];

        new Chart(ctxHak, {
            type: 'doughnut',
            data: {
                labels: dataHak.map(x => x.label),
                datasets: [{
                    data: dataHak.map(x => x.total),
                    backgroundColor: colors,
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                maintainAspectRatio: false, // Penting agar bisa di-resize
                responsive: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'right', // Legenda di kanan agar rapi
                        labels: { font: { size: 12 } }
                    }
                },
                layout: { padding: 20 }
            }
        });

        // --- CHART DESA (BAR HORIZONTAL AGAR NAMA DESA TERBACA) ---
        var ctxDesa = document.getElementById('chartDesa').getContext('2d');
        var dataDesa = @json($statsDesa);
        
        new Chart(ctxDesa, {
            type: 'bar', // Gunakan bar chart
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
                indexAxis: 'y', // MEMBUAT CHART HORIZONTAL (Lebih mudah baca nama desa panjang)
                maintainAspectRatio: false,
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.raw.toLocaleString() + ' Hektar';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: { borderDash: [2, 2] }
                    },
                    y: {
                        ticks: { font: { size: 11 } }
                    }
                }
            }
        });
    });
</script>
@endpush