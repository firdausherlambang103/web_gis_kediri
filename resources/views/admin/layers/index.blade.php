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
                            <th>Warna</th>
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
                                    onclick="editLayer({{ $layer->id }}, '{{ $layer->name }}', '{{ $layer->color }}', '{{ $layer->description }}', {{ $layer->is_active }})">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('master-layer.destroy', $layer->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin hapus layer ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">Data masih kosong. Silakan tambah layer baru.</td>
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
                        <input type="text" name="name" class="form-control" placeholder="Contoh: SHM, HGB, Tanah Wakaf" required>
                    </div>
                    <div class="form-group">
                        <label>Warna Peta <span class="text-danger">*</span></label>
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
                        <label>Warna Peta <span class="text-danger">*</span></label>
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
    function editLayer(id, name, color, desc, active) {
        $('#formEdit').attr('action', '/master-layer/' + id);
        $('#editName').val(name);
        $('#editColor').val(color);
        $('#editDesc').val(desc);
        $('#editActive').prop('checked', active == 1);
        $('#modalEdit').modal('show');
    }
</script>
@endsection