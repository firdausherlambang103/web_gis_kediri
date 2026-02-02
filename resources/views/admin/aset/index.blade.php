@extends('layouts.admin')

@section('title', 'Data Aset Terdaftar')

@section('content')
<div class="container-fluid">
    <div class="card card-primary card-outline card-tabs shadow-sm">
        
        <div class="card-header p-0 pt-1 border-bottom-0">
            <ul class="nav nav-tabs" id="custom-tabs-three-tab" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ !$sumber ? 'active' : '' }}" href="{{ route('aset.index') }}">
                        <i class="fas fa-layer-group mr-1"></i> Semua Data
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $sumber == 'manual' ? 'active' : '' }}" href="{{ route('aset.index', ['sumber' => 'manual']) }}">
                        <i class="fas fa-pen-nib mr-1 text-warning"></i> Data Gambar Manual
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $sumber == 'import' ? 'active' : '' }}" href="{{ route('aset.index', ['sumber' => 'import']) }}">
                        <i class="fas fa-file-import mr-1 text-success"></i> Data Import SHP
                    </a>
                </li>
                
                <li class="nav-item ml-auto mr-2 mt-1">
                    <button type="button" class="btn btn-sm btn-success shadow-sm" data-toggle="modal" data-target="#modalImport">
                        <i class="fas fa-file-upload mr-1"></i> Import SHP
                    </button>
                </li>
            </ul>
        </div>

        <div class="card-body bg-white">
            
            <div class="filter-box bg-light p-3 rounded mb-3 border">
                <form method="GET" action="{{ route('aset.index') }}">
                    <input type="hidden" name="sumber" value="{{ $sumber }}">

                    <div class="row">
                        <div class="col-md-3 mb-2">
                            <label class="small text-muted font-weight-bold">Cari Nama/NIB</label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="search" class="form-control" placeholder="Contoh: Wakaf, 00809..." value="{{ $search }}">
                                <div class="input-group-append">
                                    <span class="input-group-text bg-white"><i class="fas fa-search text-primary"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-2">
                            <label class="small text-muted font-weight-bold">Filter Tipe Hak</label>
                            <select name="hak" class="form-control form-control-sm">
                                <option value="">-- Semua Hak --</option>
                                <option value="HM" {{ $hak == 'HM' ? 'selected' : '' }}>Hak Milik (HM)</option>
                                <option value="HGB" {{ $hak == 'HGB' ? 'selected' : '' }}>Hak Guna Bangunan (HGB)</option>
                                <option value="HP" {{ $hak == 'HP' ? 'selected' : '' }}>Hak Pakai (HP)</option>
                                <option value="WAKAF" {{ $hak == 'WAKAF' ? 'selected' : '' }}>Tanah Wakaf</option>
                                <option value="KOSONG" {{ $hak == 'KOSONG' ? 'selected' : '' }}>Belum Ada Hak</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="small text-muted font-weight-bold">Kecamatan</label>
                            <input type="text" name="kecamatan" class="form-control form-control-sm" placeholder="Kecamatan..." value="{{ $kecamatan }}">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="small text-muted font-weight-bold">Desa/Kelurahan</label>
                            <input type="text" name="desa" class="form-control form-control-sm" placeholder="Desa..." value="{{ $desa }}">
                        </div>
                        <div class="col-md-2 mb-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-sm btn-primary btn-block shadow-sm">
                                <i class="fas fa-filter mr-1"></i> Terapkan
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover table-striped text-nowrap border-bottom">
                    <thead class="bg-primary text-white">
                        <tr>
                            <th style="width: 5%">No</th>
                            <th style="width: 25%">Nama Aset / NIB</th>
                            <th style="width: 15%">Tipe Hak</th>
                            <th style="width: 20%">Lokasi Wilayah</th>
                            <th style="width: 15%">Luas (m²)</th>
                            <th style="width: 10%">Sumber</th>
                            <th class="text-center" style="width: 10%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data as $key => $item)
                            @php
                                $props = is_string($item->properties) ? json_decode($item->properties, true) : $item->properties;
                                $raw = $props['raw_data'] ?? [];
                                $type = $props['type'] ?? 'Imported';

                                $tipeHak = $raw['TIPEHAK'] ?? $raw['TIPE_HAK'] ?? $raw['tipehak'] ?? $raw['HAK'] ?? $raw['REMARK'] ?? '-';
                                $desa = $raw['KELURAHAN'] ?? $raw['kelurahan'] ?? $raw['DESA'] ?? $raw['NAMOBJ'] ?? null;
                                $kec  = $raw['KECAMATAN'] ?? $raw['kecamatan'] ?? $raw['WADMKC'] ?? null;
                                
                                $lokasi = '';
                                if($desa) $lokasi .= 'Desa ' . $desa;
                                if($desa && $kec) $lokasi .= '<br>';
                                if($kec) $lokasi .= '<small class="text-muted"><i class="fas fa-map-marker-alt mr-1"></i>Kec. ' . $kec . '</small>';
                                if(!$lokasi) $lokasi = '<span class="text-muted font-italic text-xs">Tidak ada data wilayah</span>';

                                $luas = $raw['LUASTERTUL'] ?? $raw['LUAS'] ?? $raw['SHAPE_Area'] ?? 0;
                                
                                $badgeColor = 'badge-secondary';
                                if(stripos($tipeHak, 'Milik') !== false || stripos($tipeHak, 'HM') !== false) $badgeColor = 'badge-success';
                                elseif(stripos($tipeHak, 'Guna') !== false || stripos($tipeHak, 'HGB') !== false) $badgeColor = 'badge-warning';
                                elseif(stripos($tipeHak, 'Pakai') !== false || stripos($tipeHak, 'HP') !== false) $badgeColor = 'badge-info';
                                elseif(stripos($tipeHak, 'Wakaf') !== false) $badgeColor = 'badge-primary';
                            @endphp
                            <tr>
                                <td>{{ $data->firstItem() + $key }}</td>
                                <td>
                                    <strong class="text-dark">{{ $item->name }}</strong>
                                    @if(isset($raw['NIB']))
                                        <br><small class="text-muted"><i class="fas fa-barcode mr-1"></i>NIB: {{ $raw['NIB'] }}</small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $badgeColor }} p-2 shadow-sm" style="font-size: 0.85em; font-weight: 500;">
                                        {{ Str::limit($tipeHak, 20) }}
                                    </span>
                                </td>
                                <td>{!! $lokasi !!}</td>
                                <td class="font-weight-bold text-dark">
                                    {{ number_format((float)$luas, 0, ',', '.') }} <small>m²</small>
                                </td>
                                <td>
                                    @if($type == 'Manual')
                                        <span class="badge badge-light border"><i class="fas fa-pen text-warning mr-1"></i> Manual</span>
                                    @else
                                        <span class="badge badge-light border"><i class="fas fa-file-import text-success mr-1"></i> Import</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group">
                                        @if($item->center)
                                            @php $geo = json_decode($item->center); $coords = $geo->coordinates; @endphp
                                            <a href="{{ route('dashboard', ['lat' => $coords[1], 'lng' => $coords[0], 'search' => $item->name]) }}" target="_blank" class="btn btn-xs btn-outline-info" title="Lihat Peta">
                                                <i class="fas fa-map-marked-alt"></i>
                                            </a>
                                        @endif
                                        <button class="btn btn-xs btn-outline-warning" onclick="editAsset({{ $item->id }})" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-xs btn-outline-danger" onclick="deleteAsset({{ $item->id }})" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="py-4">
                                        <i class="fas fa-folder-open fa-3x mb-3 text-gray-300"></i>
                                        <h5>Belum ada data aset yang ditemukan.</h5>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3 d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Total Data: <strong>{{ number_format($data->total()) }}</strong> Aset
                </div>
                <div>{{ $data->withQueryString()->links('pagination::bootstrap-4') }}</div>
            </div>
        </div>
    </div>
</div>

@include('admin.aset.partials.modals')

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>
<script>
    $(document).ready(function () { bsCustomFileInput.init(); });

    // --- FUNGSI EDIT ---
    function editAsset(id) {
        // Fetch data
        $.get('/asset/' + id, function(data) {
            $('#editId').val(data.id);
            $('#editName').val(data.name);
            $('#editStatus').val(data.status); // Harus match value option
            $('#editKec').val(data.kecamatan);
            $('#editDesa').val(data.desa);
            $('#editLuas').val(data.luas);
            $('#editDesc').val(data.description);
            $('#editColor').val(data.color);
            
            $('#modalEdit').modal('show');
        }).fail(function() {
            Swal.fire('Error', 'Gagal mengambil data', 'error');
        });
    }

    // Submit Edit
    $('#formEdit').submit(function(e) {
        e.preventDefault();
        var id = $('#editId').val();
        
        $.ajax({
            url: '/asset/' + id,
            type: 'PUT',
            data: {
                _token: '{{ csrf_token() }}',
                name: $('#editName').val(),
                status: $('#editStatus').val(),
                kecamatan: $('#editKec').val(),
                desa: $('#editDesa').val(),
                luas: $('#editLuas').val(),
                description: $('#editDesc').val(),
                color: $('#editColor').val()
            },
            success: function(res) {
                $('#modalEdit').modal('hide');
                Swal.fire('Sukses', res.message, 'success').then(() => location.reload());
            },
            error: function(err) {
                Swal.fire('Gagal', 'Terjadi kesalahan server', 'error');
            }
        });
    });

    // --- FUNGSI HAPUS ---
    function deleteAsset(id) {
        Swal.fire({
            title: 'Yakin hapus data?',
            text: "Data yang dihapus tidak bisa dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Hapus!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/asset/' + id,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function(res) {
                        Swal.fire('Terhapus!', res.message, 'success').then(() => location.reload());
                    },
                    error: function() {
                        Swal.fire('Gagal', 'Data gagal dihapus', 'error');
                    }
                });
            }
        })
    }
</script>
@endpush