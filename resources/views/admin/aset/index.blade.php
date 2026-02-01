@extends('layouts.admin')

@section('title', 'Data Aset Wilayah')

@section('content')
<div class="row">
    <div class="col-12">
        
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="m-0 text-dark font-weight-bold">Database Aset</h4>
                <small class="text-muted">Kelola data aset tanah dan bangunan</small>
            </div>
            <a href="{{ route('dashboard', ['search' => request('search'), 'hak' => request('hak')]) }}" class="btn btn-success shadow-sm">
                <i class="fas fa-map-marked-alt mr-1"></i> Lihat Hasil Filter di Peta
            </a>
        </div>

        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filter Pencarian</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('aset.index') }}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small text-muted font-weight-bold">Cari Nama / NIB</label>
                                <div class="input-group input-group-sm">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text bg-light"><i class="fas fa-search text-primary"></i></span>
                                    </div>
                                    <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Contoh: Tanah Wakaf...">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small text-muted font-weight-bold">Tipe Hak</label>
                                <select name="hak" class="form-control form-control-sm">
                                    <option value="">-- Semua Hak --</option>
                                    <option value="HM" {{ $hak == 'HM' ? 'selected' : '' }}>Hak Milik (HM)</option>
                                    <option value="HGB" {{ $hak == 'HGB' ? 'selected' : '' }}>Hak Guna Bangunan (HGB)</option>
                                    <option value="HP" {{ $hak == 'HP' ? 'selected' : '' }}>Hak Pakai (HP)</option>
                                    <option value="WAKAF" {{ $hak == 'WAKAF' ? 'selected' : '' }}>Tanah Wakaf</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small text-muted font-weight-bold">Kecamatan</label>
                                <input type="text" name="kecamatan" class="form-control form-control-sm" value="{{ $kecamatan }}" placeholder="Semua Kecamatan">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="small text-muted font-weight-bold">Desa / Kelurahan</label>
                                <input type="text" name="desa" class="form-control form-control-sm" value="{{ $desa }}" placeholder="Semua Desa">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-2">
                        <div class="col-12 text-right">
                            <a href="{{ route('aset.index') }}" class="btn btn-default btn-sm mr-1">
                                <i class="fas fa-sync-alt mr-1"></i> Reset
                            </a>
                            <button type="submit" class="btn btn-primary btn-sm px-4">
                                <i class="fas fa-filter mr-1"></i> Terapkan Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card card-outline card-info shadow-sm">
            <div class="card-header border-0">
                <h3 class="card-title text-info">
                    <i class="fas fa-list-ul mr-1"></i> Daftar Aset Terdaftar
                </h3>
                <div class="card-tools">
                    <span class="badge badge-info px-2 py-1">{{ number_format($data->total()) }} Data</span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover text-nowrap mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 50px" class="text-center">No</th>
                                <th>Identitas Aset</th>
                                <th>Jenis Hak</th>
                                <th>Lokasi Aset</th>
                                <th class="text-right">Luas (m²)</th>
                                <th class="text-center" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $key => $item)
                                @php
                                    $props = $item->properties;
                                    $raw = $props['raw_data'] ?? [];
                                    $type = $props['type'] ?? 'Manual';
                                    
                                    // Ambil Data Aman
                                    $nib = $raw['NIB'] ?? '-';
                                    $hakItem = $raw['TIPE_HAK'] ?? '-';
                                    $kec = $raw['KECAMATAN'] ?? '-';
                                    $des = $raw['KELURAHAN'] ?? '-';
                                    $luas = $raw['LUASTERTUL'] ?? $raw['LUASPETA'] ?? 0;
                                    
                                    // Hitung Koordinat untuk tombol peta (Dari ST_Centroid di Controller)
                                    $lat = 0; $lng = 0;
                                    if(isset($item->center)) {
                                        $center = json_decode($item->center);
                                        $lat = $center->coordinates[1] ?? 0;
                                        $lng = $center->coordinates[0] ?? 0;
                                    }

                                    // Logika Warna Badge Hak
                                    $hakColor = 'badge-secondary';
                                    if(stripos($hakItem, 'HM') !== false || stripos($hakItem, 'MILIK') !== false) $hakColor = 'badge-success';
                                    elseif(stripos($hakItem, 'HGB') !== false) $hakColor = 'badge-warning';
                                    elseif(stripos($hakItem, 'Pakai') !== false || stripos($hakItem, 'HP') !== false) $hakColor = 'badge-info';
                                    elseif(stripos($hakItem, 'Wakaf') !== false) $hakColor = 'badge-primary';
                                @endphp
                                <tr>
                                    <td class="text-center align-middle text-muted">{{ $data->firstItem() + $key }}</td>
                                    <td class="align-middle">
                                        <div class="font-weight-bold text-dark">{{ $item->name }}</div>
                                        @if($nib != '-')
                                            <small class="text-muted"><i class="fas fa-barcode mr-1"></i>{{ $nib }}</small>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        @if($type == 'Imported')
                                            <span class="badge {{ $hakColor }} px-2 py-1" style="font-weight: 500;">{{ $hakItem }}</span>
                                        @else
                                            <span class="badge badge-light border text-muted px-2 py-1">{{ $type }}</span>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        <div class="text-dark"><i class="fas fa-map-marker-alt text-danger mr-1" style="font-size:10px;"></i> {{ $des }}</div>
                                        <small class="text-muted pl-3">Kec. {{ $kec }}</small>
                                    </td>
                                    <td class="align-middle text-right font-weight-bold text-dark">
                                        {{ number_format((float)$luas, 0, ',', '.') }}
                                    </td>
                                    <td class="align-middle text-center">
                                        @if($lat != 0 && $lng != 0)
                                            <a href="{{ route('dashboard', ['lat' => $lat, 'lng' => $lng, 'search' => $item->name]) }}" 
                                               class="btn btn-sm btn-outline-success"
                                               title="Lihat Lokasi di Peta">
                                                <i class="fas fa-map-marker-alt"></i>
                                            </a>
                                        @else
                                            <button class="btn btn-sm btn-outline-secondary" disabled title="Lokasi tidak tersedia">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <div class="py-4">
                                            <i class="fas fa-folder-open fa-3x mb-3 text-light"></i><br>
                                            <h5>Data tidak ditemukan</h5>
                                            <p class="mb-0 small">Coba ubah filter pencarian Anda.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer clearfix bg-white border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="text-muted small">
                        Menampilkan {{ $data->firstItem() }} - {{ $data->lastItem() }} dari <b>{{ number_format($data->total()) }}</b> data
                    </div>
                    <div>
                        {{ $data->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection