@extends('layouts.admin')
@section('title', 'Master Data Layer')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            {{-- Notifikasi Sukses --}}
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Daftar Layer Peta</h3>
                    <div class="card-tools">
                        {{-- Tombol Tambah --}}
                        <button class="btn btn-primary btn-sm" onclick="showAddModal()">
                            <i class="fas fa-plus"></i> Tambah Layer
                        </button>
                    </div>
                </div>
                <div class="card-body table-responsive p-0">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Layer</th>
                                <th>Tipe</th>
                                <th>Warna / Style</th>
                                <th>Keterangan</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($layers as $index => $layer)
                            <tr>
                                <td>{{ $layers->firstItem() + $index }}</td>
                                <td><strong>{{ $layer->name }}</strong></td>
                                <td>
                                    @if($layer->mode == 'auto_hak')
                                        <span class="badge badge-success">Layer Utama (Peta Bidang)</span>
                                    @else
                                        <span class="badge badge-secondary">Layer Standar</span>
                                    @endif
                                </td>
                                <td>
                                    {{-- Preview Warna --}}
                                    @if($layer->mode == 'auto_hak')
                                        <div class="d-flex small flex-wrap" style="max-width: 250px;">
                                            <span class="mr-2 mb-1" style="color:{{$layer->color_hm}}">■ HM</span>
                                            <span class="mr-2 mb-1" style="color:{{$layer->color_hgb}}">■ HGB</span>
                                            <span class="mr-2 mb-1" style="color:{{$layer->color_hgu}}">■ HGU</span>
                                            <span class="mr-2 mb-1" style="color:{{$layer->color_hp}}">■ HP</span>
                                            <span class="mr-2 mb-1" style="color:{{$layer->color_wakaf}}">■ Wakaf</span>
                                            <span class="mr-2 mb-1" style="color:{{$layer->color_tn}}">■ TN</span>
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center">
                                            <div style="width: 20px; height: 20px; background-color: {{ $layer->color }}; border-radius: 4px; border: 1px solid #ccc; margin-right: 8px;"></div>
                                            <span>{{ $layer->color }}</span>
                                        </div>
                                    @endif
                                </td>
                                <td>{{ Str::limit($layer->description, 30) ?? '-' }}</td>
                                <td class="text-center">
                                    {{-- Tombol Edit --}}
                                    <button class="btn btn-warning btn-sm text-white btn-edit" 
                                            data-json="{{ json_encode($layer) }}">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    {{-- Tombol Hapus --}}
                                    <form action="{{ route('master-layer.destroy', $layer->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus layer ini? Data aset di dalamnya akan ikut terhapus!');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted">Belum ada data layer.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer clearfix">
                    {{ $layers->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Form (Digunakan untuk Tambah & Edit) --}}
<div class="modal fade" id="modalForm">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="modalTitle">Layer</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="formLayer" method="POST">
                @csrf
                <input type="hidden" name="_method" id="formMethod" value="POST">
                
                <div class="modal-body">
                    {{-- Nama Layer --}}
                    <div class="form-group">
                        <label>Nama Layer <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="inputName" class="form-control" required placeholder="Contoh: Peta Bidang 2024">
                    </div>

                    {{-- Pilihan Tipe Layer --}}
                    <div class="form-group">
                        <label>Tipe Layer</label>
                        <div class="d-flex p-2 border rounded bg-light">
                            <div class="custom-control custom-radio mr-4">
                                <input class="custom-control-input mode-selector" type="radio" id="modeStandard" name="mode" value="standard" checked>
                                <label for="modeStandard" class="custom-control-label">Standar (1 Warna)</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input class="custom-control-input mode-selector" type="radio" id="modeAuto" name="mode" value="auto_hak">
                                <label for="modeAuto" class="custom-control-label font-weight-bold text-success">Layer Utama (Otomatis)</label>
                            </div>
                        </div>
                        <small class="text-muted" id="helpMode">
                            Layer standar menggunakan satu warna untuk semua aset. Layer utama akan mewarnai aset secara otomatis berdasarkan tipe Hak (HM, HGB, dll).
                        </small>
                    </div>

                    {{-- Setting Warna Standar --}}
                    <div id="boxStandard" class="p-3 bg-white border rounded mb-3">
                        <div class="form-group mb-0">
                            <label>Warna Default Layer <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="color" name="color" id="inputColor" class="form-control form-control-color" value="#3388ff" style="max-width: 60px;">
                                <input type="text" class="form-control" value="Pilih Warna" readonly style="background: white;">
                            </div>
                        </div>
                    </div>

                    {{-- Setting Warna Utama (Hak) --}}
                    <div id="boxAuto" class="p-3 bg-white border rounded mb-3" style="display: none;">
                        <label class="d-block border-bottom pb-2 mb-2">Setting Warna Tipe Hak</label>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <label class="small mb-0 font-weight-bold">Hak Milik (HM)</label>
                                <input type="color" name="color_hm" id="colorHm" class="form-control form-control-sm w-100" value="#28a745">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="small mb-0 font-weight-bold">HGB</label>
                                <input type="color" name="color_hgb" id="colorHgb" class="form-control form-control-sm w-100" value="#ffc107">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="small mb-0 font-weight-bold">HGU</label>
                                <input type="color" name="color_hgu" id="colorHgu" class="form-control form-control-sm w-100" value="#fd7e14">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="small mb-0 font-weight-bold">Hak Pakai (HP)</label>
                                <input type="color" name="color_hp" id="colorHp" class="form-control form-control-sm w-100" value="#17a2b8">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="small mb-0 font-weight-bold">Wakaf</label>
                                <input type="color" name="color_wakaf" id="colorWakaf" class="form-control form-control-sm w-100" value="#6f42c1">
                            </div>
                            <div class="col-6 mb-2">
                                <label class="small mb-0 font-weight-bold text-muted">Tanah Negara</label>
                                <input type="color" name="color_tn" id="colorTn" class="form-control form-control-sm w-100" value="#6c757d">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="description" id="inputDesc" class="form-control" rows="2" placeholder="Deskripsi singkat layer..."></textarea>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save mr-1"></i> Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // 1. Toggle Tampilan Form Warna berdasarkan Mode Layer
    $('.mode-selector').on('change', function() {
        if($('#modeAuto').is(':checked')) {
            $('#boxStandard').hide();
            $('#boxAuto').slideDown();
        } else {
            $('#boxAuto').hide();
            $('#boxStandard').slideDown();
        }
    });

    // 2. Fungsi Menampilkan Modal Tambah
    function showAddModal() {
        $('#modalTitle').text('Tambah Layer Baru');
        $('#formLayer').attr('action', "{{ route('master-layer.store') }}");
        $('#formMethod').val('POST');
        
        // Reset Form
        $('#inputName').val('');
        $('#inputDesc').val('');
        $('#modeStandard').prop('checked', true).trigger('change');
        
        // Reset Warna Default
        $('#inputColor').val('#3388ff');
        $('#colorHm').val('#28a745');
        $('#colorHgb').val('#ffc107');
        $('#colorHgu').val('#fd7e14'); // Reset HGU
        $('#colorHp').val('#17a2b8');
        $('#colorWakaf').val('#6f42c1');
        $('#colorTn').val('#6c757d');
        
        $('#modalForm').modal('show');
    }

    // 3. Fungsi Menampilkan Modal Edit (Isi Data dari Tombol)
    $('.btn-edit').on('click', function() {
        var data = $(this).data('json');
        
        $('#modalTitle').text('Edit Layer');
        $('#formLayer').attr('action', '/master-layer/' + data.id);
        $('#formMethod').val('PUT'); // Spoofing method PUT untuk Update Laravel
        
        $('#inputName').val(data.name);
        $('#inputDesc').val(data.description);
        
        // Pilih Radio Button
        if(data.mode == 'auto_hak') {
            $('#modeAuto').prop('checked', true).trigger('change');
        } else {
            $('#modeStandard').prop('checked', true).trigger('change');
        }
        
        // Isi Warna Standard
        $('#inputColor').val(data.color);
        
        // Isi Warna Hak (Gunakan operator || untuk fallback ke default jika null)
        $('#colorHm').val(data.color_hm || '#28a745');
        $('#colorHgb').val(data.color_hgb || '#ffc107');
        $('#colorHgu').val(data.color_hgu || '#fd7e14'); // HGU
        $('#colorHp').val(data.color_hp || '#17a2b8');
        $('#colorWakaf').val(data.color_wakaf || '#6f42c1');
        $('#colorTn').val(data.color_tn || '#6c757d');
        
        $('#modalForm').modal('show');
    });
</script>
@endpush