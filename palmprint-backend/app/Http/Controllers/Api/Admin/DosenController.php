<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dosen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DosenController extends Controller
{
    public function index()
    {
        return response()->json(Dosen::latest()->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'nip'      => 'required|string|unique:dosens,nip',
            'nama'     => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        $dosen = Dosen::create([
            'nip'      => $request->nip,
            'nama'     => $request->nama,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'message' => 'Dosen berhasil dibuat',
            'data'    => $dosen,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $dosen = Dosen::findOrFail($id);

        $data = $request->only(['nip', 'nama']);
        if ($request->password) {
            $data['password'] = Hash::make($request->password);
        }

        $dosen->update($data);

        return response()->json([
            'message' => 'Dosen berhasil diupdate',
            'data'    => $dosen,
        ]);
    }

    public function destroy($id)
    {
        Dosen::findOrFail($id)->delete();
        return response()->json(['message' => 'Dosen berhasil dihapus']);
    }
}