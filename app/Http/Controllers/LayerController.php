<?php

namespace App\Http\Controllers;

use App\Models\Layer;
use Illuminate\Http\Request;

class LayerController extends Controller
{
    public function index()
    {
        $layers = Layer::orderBy('created_at', 'desc')->paginate(10);
        return view('admin.layers.index', compact('layers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'mode' => 'required|in:standard,auto_hak', // Validasi Mode
            'color' => 'required_if:mode,standard', // Warna default wajib jika standar
        ]);

        Layer::create([
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => true,
            'mode' => $request->mode,
            'color' => $request->color ?? '#3388ff', // Fallback
            // Simpan warna hak
            'color_hm' => $request->color_hm ?? '#28a745',
            'color_hgb' => $request->color_hgb ?? '#ffc107',
            'color_hp' => $request->color_hp ?? '#17a2b8',
            'color_wakaf' => $request->color_wakaf ?? '#6f42c1',
            'color_hgu' => $request->color_hgu ?? '#fd7e14',
            'color_tn' => $request->color_tn ?? '#6c757d',
        ]);

        return back()->with('success', 'Layer berhasil ditambahkan!');
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'mode' => 'required|in:standard,auto_hak',
        ]);

        $layer = Layer::findOrFail($id);
        
        $layer->update([
            'name' => $request->name,
            'description' => $request->description,
            'mode' => $request->mode,
            'color' => $request->color,
            'color_hm' => $request->color_hm,
            'color_hgb' => $request->color_hgb,
            'color_hp' => $request->color_hp,
            'color_wakaf' => $request->color_wakaf,
            'color_hgu' => $request->color_hgu,
            'color_tn' => $request->color_tn,
        ]);

        return back()->with('success', 'Layer berhasil diperbarui!');
    }

    public function destroy($id)
    {
        $layer = Layer::findOrFail($id);
        $layer->delete();
        return back()->with('success', 'Layer berhasil dihapus!');
    }
}