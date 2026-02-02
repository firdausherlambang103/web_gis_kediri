@extends('layouts.admin')

@section('title', 'Statistik & Analisis Aset')

@section('content')
<div class="container-fluid">
    <div class="card card-outline card-primary mb-3">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('statistics.index') }}" class="form-inline justify-content-end">
                <label class="mr-2 small">Filter Wilayah:</label>
                <input type="text" name="kecamatan" class="form-control form-control-sm mr-2" placeholder="Kecamatan..." value="{{ $kecamatan }}">
                <input type="text" name="desa" class="form-control form-control-sm mr-2" placeholder="Desa..." value="{{ $desa }}">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</button>
            </form>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-pie mr-1"></i> Distribusi Tipe Hak</h3>
                </div>
                <div class="card-body">
                    <canvas id="chartHak" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                </div>
                <div class="card-footer bg-white p-0">
                    <ul class="nav flex-column">
                        @foreach($statsHak as $s)
                        <li class="nav-item border-bottom">
                            <a href="#" class="nav-link text-muted small py-1">
                                {{ $s->label }} <span class="float-right badge bg-primary">{{ number_format($s->total) }}</span>
                            </a>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card card-success">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-chart-bar mr-1"></i> Top Desa Terpetakan (Hektar)</h3>
                </div>
                <div class="card-body">
                    <canvas id="chartDesa" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                </div>
                <div class="card-footer small text-muted">
                    Total Luas Terpetakan (Filtered): <b>{{ number_format($totalLuasTerpetakan, 2) }} Hektar</b>
                </div>
            </div>

            <div class="card card-info mt-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-search-location mr-1"></i> Status Pemetaan</h3>
                </div>
                <div class="card-body text-center">
                    <h1 class="display-4 text-info">{{ number_format($totalLuasTerpetakan, 1) }} Ha</h1>
                    <p class="text-muted">Total Luas Aset Terpetakan (Sesuai Filter)</p>
                    <div class="alert alert-light border small text-left">
                        <i class="fas fa-info-circle"></i> <b>Catatan:</b> Untuk menghitung persentase "Belum Terpetakan" secara akurat, diperlukan data poligon batas wilayah resmi (Desa/Kecamatan) sebagai pembanding total luas wilayah.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card card-danger card-outline">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Analisis Tumpang Tindih (Overlap)
                        @if(!$desa) <small class="text-danger ml-2">(Menampilkan 50 teratas, filter Desa untuk detail)</small> @endif
                    </h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-sm text-nowrap">
                        <thead>
                            <tr>
                                <th>Aset 1</th>
                                <th>Aset 2</th>
                                <th>Lokasi (Desa)</th>
                                <th class="text-right">Luas Overlap</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($overlaps as $ov)
                            <tr>
                                <td class="text-primary font-weight-bold">{{ $ov->aset_1 }}</td>
                                <td class="text-danger font-weight-bold">{{ $ov->aset_2 }}</td>
                                <td>{{ $ov->desa ?? '-' }}</td>
                                <td class="text-right">{{ number_format($ov->luas_tumpang_tindih, 2) }} m²</td>
                                <td class="text-center">
                                    <a href="{{ route('dashboard', ['search' => $ov->aset_1]) }}" target="_blank" class="btn btn-xs btn-outline-primary"><i class="fas fa-search"></i> Cek</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-success">
                                    <i class="fas fa-check-circle fa-2x mb-2"></i><br>
                                    Tidak ditemukan tumpang tindih signifikan (>5m²) pada data ini.
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
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // --- CHART TIPE HAK (PIE) ---
    var ctxHak = document.getElementById('chartHak').getContext('2d');
    var dataHak = @json($statsHak);
    var labelsHak = dataHak.map(x => x.label);
    var valuesHak = dataHak.map(x => x.total);
    var colors = ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de', '#605ca8', '#ff851b'];

    new Chart(ctxHak, {
        type: 'doughnut',
        data: {
            labels: labelsHak,
            datasets: [{
                data: valuesHak,
                backgroundColor: colors.slice(0, labelsHak.length)
            }]
        },
        options: { maintainAspectRatio: false, responsive: true, plugins: { legend: { display: false } } }
    });

    // --- CHART DESA (BAR) ---
    var ctxDesa = document.getElementById('chartDesa').getContext('2d');
    var dataDesa = @json($statsDesa);
    
    new Chart(ctxDesa, {
        type: 'bar',
        data: {
            labels: dataDesa.map(x => x.desa),
            datasets: [{
                label: 'Luas (Hektar)',
                data: dataDesa.map(x => x.luas_hektar),
                backgroundColor: '#007bff'
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            scales: { y: { beginAtZero: true } }
        }
    });
</script>
@endpush