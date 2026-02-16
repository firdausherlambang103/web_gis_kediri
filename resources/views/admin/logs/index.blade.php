@extends('layouts.admin')
@section('title', 'Log Aktivitas')

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header">Riwayat Aktivitas Sistem</div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm table-hover text-nowrap">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>User</th>
                        <th>Aksi</th>
                        <th>Target</th>
                        <th>Deskripsi</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('d M Y H:i:s') }}</td>
                        <td class="font-weight-bold">{{ $log->user->name ?? 'Deleted User' }}</td>
                        <td>
                            <span class="badge 
                                {{ $log->action == 'DELETE' ? 'badge-danger' : 
                                  ($log->action == 'CREATE' || $log->action == 'UPLOAD' ? 'badge-success' : 
                                  ($log->action == 'UPDATE' ? 'badge-info' : 'badge-secondary')) }}">
                                {{ $log->action }}
                            </span>
                        </td>
                        <td>{{ Str::limit($log->target, 30) }}</td>
                        <td>{{ Str::limit($log->description, 50) }}</td>
                        <td class="text-muted text-xs">{{ $log->ip_address }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection