@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manajemen Mata Kuliah</h4>
    <button class="btn btn-primary" onclick="showModal()">
        <i class="bi bi-plus-lg me-1"></i> Tambah Mata Kuliah
    </button>
</div>

<!-- Tabel Mata Kuliah -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>Kode</th>
                    <th>Nama Mata Kuliah</th>
                    <th>SKS</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tableMatkul">
                <tr><td colspan="5" class="text-center">Memuat data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalMatkul" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Mata Kuliah</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="matkulId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Kode</label>
                    <input type="text" id="kode" class="form-control" placeholder="contoh: MK001">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Mata Kuliah</label>
                    <input type="text" id="nama" class="form-control" placeholder="contoh: Basis Data">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">SKS</label>
                    <select id="sks" class="form-select">
                        <option value="1">1 SKS</option>
                        <option value="2">2 SKS</option>
                        <option value="3" selected>3 SKS</option>
                        <option value="4">4 SKS</option>
                        <option value="5">5 SKS</option>
                        <option value="6">6 SKS</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="simpan()">Simpan</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const modal = new bootstrap.Modal(document.getElementById('modalMatkul'));

// ── Load Data ──
async function loadData() {
    const res   = await axios.get('/api/admin/matkuls');
    const data  = res.data;
    const tbody = document.getElementById('tableMatkul');

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Belum ada data</td></tr>';
        return;
    }

    tbody.innerHTML = data.map((m, i) => `
        <tr>
            <td>${i + 1}</td>
            <td><span class="badge bg-primary">${m.kode}</span></td>
            <td>${m.nama}</td>
            <td>${m.sks} SKS</td>
            <td>
                <button class="btn btn-warning btn-sm me-1" onclick="edit(${m.id}, '${m.kode}', '${m.nama}', ${m.sks})">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="hapus(${m.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// ── Show Modal Tambah ──
function showModal() {
    document.getElementById('modalTitle').innerText = 'Tambah Mata Kuliah';
    document.getElementById('matkulId').value       = '';
    document.getElementById('kode').value           = '';
    document.getElementById('nama').value           = '';
    document.getElementById('sks').value            = '3';
    modal.show();
}

// ── Show Modal Edit ──
function edit(id, kode, nama, sks) {
    document.getElementById('modalTitle').innerText = 'Edit Mata Kuliah';
    document.getElementById('matkulId').value       = id;
    document.getElementById('kode').value           = kode;
    document.getElementById('nama').value           = nama;
    document.getElementById('sks').value            = sks;
    modal.show();
}

// ── Simpan ──
async function simpan() {
    const id   = document.getElementById('matkulId').value;
    const data = {
        kode : document.getElementById('kode').value,
        nama : document.getElementById('nama').value,
        sks  : document.getElementById('sks').value,
    };

    if (!data.kode || !data.nama) {
        alert('Kode dan nama mata kuliah harus diisi!');
        return;
    }

    try {
        if (id) {
            await axios.put(`/api/admin/matkuls/${id}`, data);
        } else {
            await axios.post('/api/admin/matkuls', data);
        }
        modal.hide();
        loadData();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Terjadi kesalahan');
    }
}

// ── Hapus ──
async function hapus(id) {
    if (!confirm('Yakin hapus mata kuliah ini?')) return;
    await axios.delete(`/api/admin/matkuls/${id}`);
    loadData();
}

loadData();
</script>
@endpush