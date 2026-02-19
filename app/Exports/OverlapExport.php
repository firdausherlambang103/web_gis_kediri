<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OverlapExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $layerId;

    public function __construct($layerId)
    {
        $this->layerId = $layerId;
    }

    /**
    * Mengambil data dari database
    */
    public function collection()
    {
        $query = DB::table('overlap_results')
            ->select('overlap_results.*');

        if ($this->layerId) {
            $query->join('spatial_features', 'overlap_results.id_1', '=', 'spatial_features.id')
                  ->where('spatial_features.layer_id', $this->layerId);
        }

        return $query->orderBy('luas_overlap', 'desc')->limit(10000)->get();
    }

    /**
    * Judul Kolom (Header)
    */
    public function headings(): array
    {
        return [
            'ID',
            'ID Aset 1',
            'Nama Aset 1',
            'ID Aset 2',
            'Nama Aset 2',
            'Desa / Kelurahan',
            'Kecamatan',
            'Luas Overlap (m²)',
            'Waktu Analisis',
        ];
    }

    /**
    * Mapping Data per Baris
    */
    public function map($row): array
    {
        return [
            $row->id,
            $row->id_1,
            $row->aset_1,
            $row->id_2,
            $row->aset_2,
            $row->desa,
            $row->kecamatan,
            number_format($row->luas_overlap, 2, ',', '.'),
            $row->created_at,
        ];
    }

    /**
    * Styling (Bold Header)
    */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]], // Baris 1 Bold
        ];
    }
}