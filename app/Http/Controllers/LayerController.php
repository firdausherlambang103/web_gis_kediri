<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Layer;

class LayerController extends Controller
{
    public function index()
    {
        // Ambil semua data layer, urutkan terbaru
        $layers = Layer::orderBy('created_at', 'desc')->get();
        return view('admin.layers.index', compact('layers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'color' => 'required|string|max:20',
        ]);

        Layer::create([
            'name' => $request->name,
            'color' => $request->color,
            'description' => $request->description,
            'is_active' => true
        ]);

        return back()->with('success', 'Layer berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $layer = Layer::findOrFail($id);
        
        $layer->update([
            'name' => $request->name,
            'color' => $request->color,
            'description' => $request->description,
            'is_active' => $request->has('is_active') ? true : false
        ]);

        return back()->with('success', 'Layer berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $layer = Layer::findOrFail($id);
        // Opsional: Cek apakah layer sedang dipakai oleh aset?
        if($layer->features()->count() > 0) {
            return back()->with('error', 'Gagal hapus! Layer ini masih digunakan oleh data aset.');
        }
        
        $layer->delete();
        return back()->with('success', 'Layer berhasil dihapus!');
    }
}