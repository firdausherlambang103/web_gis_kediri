<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Layer;

class LayerController extends Controller
{
    /**
     * Menampilkan halaman Master Data Layer
     */
    public function index()
    {
        // Ambil semua data layer, urutkan dari yang terbaru
        $layers = Layer::orderBy('created_at', 'desc')->get();
        
        return view('admin.layers.index', compact('layers'));
    }

    /**
     * Menyimpan Layer Baru
     */
    public function store(Request $request)
    {
        // Validasi input
        $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'required|string|max:20',
            'mode' => 'required|in:standard,auto_hak', // Validasi tipe layer
        ]);

        // Simpan ke Database
        Layer::create([
            'name' => $request->name,
            'color' => $request->color,
            'description' => $request->description,
            'mode' => $request->mode, // Simpan mode (Standard / Auto Hak)
            'is_active' => true
        ]);

        return back()->with('success', 'Layer berhasil ditambahkan!');
    }

    /**
     * Update Data Layer
     */
    public function update(Request $request, $id)
    {
        $layer = Layer::findOrFail($id);
        
        // Validasi input
        $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'required|string|max:20',
            'mode' => 'required|in:standard,auto_hak',
        ]);

        // Update data
        $layer->update([
            'name' => $request->name,
            'color' => $request->color,
            'description' => $request->description,
            'mode' => $request->mode, // Update mode
            'is_active' => $request->has('is_active') ? true : false
        ]);

        return back()->with('success', 'Layer berhasil diperbarui!');
    }

    /**
     * Hapus Layer
     */
    public function destroy($id)
    {
        $layer = Layer::findOrFail($id);
        
        // Cek apakah layer ini memiliki data aset?
        // Jika ingin mencegah penghapusan layer yang ada isinya:
        /*
        if($layer->features()->count() > 0) {
            return back()->with('error', 'Gagal hapus! Layer ini masih menampung data aset.');
        }
        */
        
        $layer->delete();
        return back()->with('success', 'Layer berhasil dihapus!');
    }
}