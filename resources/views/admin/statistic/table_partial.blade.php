<table class="table table-bordered table-striped table-hover">
    <thead class="bg-primary text-white">
        <tr>
            <th>No</th>
            <th>Nama Aset</th>
            <th>Layer</th>
            <th>Kecamatan</th>
            <th>Desa</th>
            <th>Status Hak</th>
            <th>Luas (m²)</th>
        </tr>
    </thead>
    <tbody>
        @forelse($data as $index => $item)
            @php 
                $props = json_decode($item->properties, true); 
                $raw = $props['raw_data'] ?? [];
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $item->name }}</td>
                <td>
                    <span class="badge badge-secondary">{{ $item->layer->name ?? '-' }}</span>
                </td>
                <td>{{ $raw['KECAMATAN'] ?? '-' }}</td>
                <td>{{ $raw['KELURAHAN'] ?? $raw['DESA'] ?? '-' }}</td>
                <td>
                    <span class="font-weight-bold">{{ $raw['TIPEHAK'] ?? $raw['TIPE_HAK'] ?? '-' }}</span>
                </td>
                <td class="text-right">{{ number_format($raw['LUASTERTUL'] ?? $raw['LUAS'] ?? 0, 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="7" class="text-center">Tidak ada data ditemukan sesuai filter.</td>
            </tr>
        @endforelse
    </tbody>
</table>