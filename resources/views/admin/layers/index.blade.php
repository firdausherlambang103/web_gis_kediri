@extends('layouts.admin')

@section('title', 'Master Data Layer')

@section('content')
<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Master Data Layer</h1>
        <button class="btn btn-primary shadow-sm" data-toggle="modal" data-target="#modalCreate">
            <i class="fas fa-plus fa-sm text-white-50"></i> Tambah Layer Baru
        </button>
    </div>

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    @endif
    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
    </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Layer / Kategori Aset</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead class="bg-light">
                        <tr>
                            <th width="5%">No</th>
                            <th>Nama Layer</th>
                            <th>Tipe Layer</th>
                            <th>Warna Default</th>
                            <th>Keterangan</th>
                            <th width="10%">Status</th>
                            <th width="15%">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($layers as $index => $layer)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="font-weight-bold">{{ $layer->name }}</td>
                            <td>
                                @if($layer->mode == 'auto_hak')
                                    <span class="badge badge-info"><i class="fas fa-magic"></i> Otomatis (Smart)</span>
                                    <div style="font-size: 0.75rem; color: #666;">Deteksi: HM, HGB, Wakaf, dll</div>
                                @else
                                    <span class="badge badge-secondary"><i class="fas fa-paint-brush"></i> Standar</span>
                                    <div style="font-size: 0.75rem; color: #666;">Satu warna flat</div>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div style="width: 25px; height: 25px; background-color: {{ $layer->color }}; border: 1px solid #ccc; border-radius: 4px; margin-right: 10px;"></div>
                                    <code>{{ $layer->color }}</code>
                                </div>
                            </td>
                            <td>{{ $layer->description ?? '-' }}</td>
                            <td>
                                @if($layer->is_active)
                                    <span class="badge badge-success">Aktif</span>
                                @else
                                    <span class="badge badge-secondary">Non-Aktif</span>
                                @endif
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" 
                                    onclick="editLayer(
                                        {{ $layer->id }}, 
                                        '{{ $layer->name }}', 
                                        '{{ $layer->color }}', 
                                        '{{ $layer->description }}', 
                                        {{ $layer->is_active }},
                                        '{{ $layer->mode ?? 'standard' }}'
                                    )">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <form action="{{ route('master-layer.destroy', $layer->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus layer ini? Data aset di dalamnya akan ikut terhapus atau error.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">Data masih kosong. Silakan tambah layer baru.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tambah Layer Baru</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form action="{{ route('master-layer.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Layer <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="Contoh: Peta Bidang Tanah / LSD" required>
                    </div>

                    <div class="form-group">
                        <label>Tipe Layer <span class="text-danger">*</span></label>
                        <select name="mode" class="form-control" id="createMode" onchange="checkMode('create')">
                            <option value="standard">Layer Standar (Satu Warna - Misal: LSD)</option>
                            <option value="auto_hak">Layer Utama (Smart Color - HM/HGB/Wakaf)</option>
                        </select>
                        <small class="text-muted" id="createModeHelp">
                            Warna akan mengikuti settingan di bawah ini untuk semua data.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Warna Default <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="color" name="color" class="form-control" style="height: 40px;" required>
                        </div>
                        <small class="text-muted">Klik kotak warna untuk memilih.</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Layer</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <form id="formEdit" method="POST">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="form-group">
                        <label>Nama Layer <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Tipe Layer <span class="text-danger">*</span></label>
                        <select name="mode" class="form-control" id="editMode" onchange="checkMode('edit')">
                            <option value="standard">Layer Standar (Satu Warna)</option>
                            <option value="auto_hak">Layer Utama (Smart Color - HM/HGB/Wakaf)</option>
                        </select>
                        <small class="text-muted" id="editModeHelp">
                            Keterangan tipe layer.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Warna Default <span class="text-danger">*</span></label>
                        <input type="color" name="color" id="editColor" class="form-control" style="height: 40px;" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="description" id="editDesc" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="editActive" name="is_active" value="1">
                        <label class="form-check-label" for="editActive">Aktif / Tampilkan di Peta</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Fungsi mengisi form edit saat tombol diklik
    function editLayer(id, name, color, desc, active, mode) {
        $('#formEdit').attr('action', '/master-layer/' + id);
        $('#editName').val(name);
        $('#editColor').val(color);
        $('#editDesc').val(desc);
        $('#editActive').prop('checked', active == 1);
        
        // Set dropdown mode, default ke standard jika null
        $('#editMode').val(mode ? mode : 'standard');
        
        // Jalankan pengecekan deskripsi bantuan
        checkMode('edit');
        
        $('#modalEdit').modal('show');
    }

    // Fungsi mengubah teks bantuan saat dropdown berubah
    function checkMode(type) {
        var mode = $('#' + type + 'Mode').val();
        var helpText = '';
        
        if (mode == 'auto_hak') {
            helpText = '<b>Mode Pintar:</b> Warna di peta akan otomatis berubah sesuai Hak (HM=Hijau, HGB=Kuning, dll). Warna default di bawah hanya dipakai jika hak tidak terdeteksi.';
        } else {
            helpText = '<b>Mode Standar:</b> Semua data di layer ini akan menggunakan satu warna yang Anda pilih di bawah.';
        }
        
        $('#' + type + 'ModeHelp').html(helpText);
    }
</script>
@endsection