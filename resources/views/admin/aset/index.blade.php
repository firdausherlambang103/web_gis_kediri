@extends('layouts.admin')

@section('title', 'Data Aset Wilayah')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-table mr-1"></i> Data Aset Terdaftar</h3>
            </div>
            <div class="card-body">
                
                <form method="GET" action="{{ route('aset.index') }}" class="mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Cari Nama / NIB</label>
                                <input type="text" name="search" class="form-control" value="{{ $search }}" placeholder="Contoh: 00855...">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Kecamatan</label>
                                <input type="text" name="kecamatan" class="form-control" value="{{ $kecamatan }}" placeholder="Semua Kecamatan">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Desa / Kelurahan</label>
                                <input type="text" name="desa" class="form-control" value="{{ $desa }}" placeholder="Semua Desa">
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-group w-100">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Filter Data
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 50px">No</th>
                                <th>Nama Aset / NIB</th>
                                <th>Tipe</th>
                                <th>Lokasi (Kec/Desa)</th>
                                <th>Luas (m²)</th>
                                <th>Tanggal Input</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $key => $item)
                                @php
                                    // Helper untuk ambil data properties JSON
                                    $props = $item->properties;
                                    $raw = $props['raw_data'] ?? [];
                                    $type = $props['type'] ?? 'Manual';
                                @endphp
                                <tr>
                                    <td>{{ $data->firstItem() + $key }}</td>
                                    <td>
                                        <strong>{{ $item->name }}</strong><br>
                                        <small class="text-muted">NIB: {{ $raw['NIB'] ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <span class="badge badge-{{ $type == 'Imported' ? 'success' : 'info' }}">
                                            {{ $type }}
                                        </span>
                                    </td>
                                    <td>
                                        Desa: {{ $raw['KELURAHAN'] ?? '-' }}<br>
                                        Kec: {{ $raw['KECAMATAN'] ?? '-' }}
                                    </td>
                                    <td>
                                        {{ $raw['LUASTERTUL'] ?? $raw['LUASPETA'] ?? 0 }}
                                    </td>
                                    <td>{{ $item->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-folder-open fa-2x mb-2"></i><br>
                                        Data tidak ditemukan. Coba reset filter.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $data->links() }}
                </div>
                
            </div>
        </div>
    </div>
</div>
@endsection