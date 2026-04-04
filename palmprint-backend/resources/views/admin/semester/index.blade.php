@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manajemen Semester</h4>
    <button class="btn btn-primary" onclick="showModal()">
        <i class="bi bi-plus-lg me-1"></i> Tambah Semester
    </button>
</div>

<!-- Tabel Semester -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>Nama Semester</th>
                    <th>Tahun Ajaran</th>
                    <th>Tipe</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tableSemester">
                <tr><td colspan="6" class="text-center">Memuat data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalSemester" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="semesterId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Semester</label>
                    <input type="text" id="nama" class="form-control" placeholder="contoh: Genap 2024/2025">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tahun Ajaran</label>
                    <input type="text" id="tahunAjaran" class="form-control" placeholder="contoh: 2024/2025">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tipe</label>
                    <select id="tipe" class="form-select">
                        <option value="ganjil">Ganjil</option>
                        <option value="genap">Genap</option>
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
const modal = new bootstrap.Modal(document.getElementById('modalSemester'));

// ── Load Data ──
async function loadData() {
    const res  = await axios.get('/api/admin/semesters');
    const data = res.data;
    const tbody = document.getElementById('tableSemester');

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Belum ada data</td></tr>';
        return;
    }

    tbody.innerHTML = data.map((s, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${s.nama}</td>
            <td>${s.tahun_ajaran}</td>
            <td><span class="badge bg-secondary">${s.tipe}</span></td>
            <td>
                ${s.is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : `<button class="btn btn-outline-success btn-sm" onclick="setAktif(${s.id})">Set Aktif</button>`
                }
            </td>
            <td>
                <button class="btn btn-warning btn-sm me-1" onclick="edit(${s.id}, '${s.nama}', '${s.tahun_ajaran}', '${s.tipe}')">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="hapus(${s.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// ── Show Modal Tambah ──
function showModal() {
    document.getElementById('modalTitle').innerText = 'Tambah Semester';
    document.getElementById('semesterId').value     = '';
    document.getElementById('nama').value           = '';
    document.getElementById('tahunAjaran').value    = '';
    document.getElementById('tipe').value           = 'ganjil';
    modal.show();
}

// ── Show Modal Edit ──
function edit(id, nama, tahunAjaran, tipe) {
    document.getElementById('modalTitle').innerText = 'Edit Semester';
    document.getElementById('semesterId').value     = id;
    document.getElementById('nama').value           = nama;
    document.getElementById('tahunAjaran').value    = tahunAjaran;
    document.getElementById('tipe').value           = tipe;
    modal.show();
}

// ── Simpan (Tambah/Edit) ──
async function simpan() {
    const id   = document.getElementById('semesterId').value;
    const data = {
        nama         : document.getElementById('nama').value,
        tahun_ajaran : document.getElementById('tahunAjaran').value,
        tipe         : document.getElementById('tipe').value,
    };

    try {
        if (id) {
            await axios.put(`/api/admin/semesters/${id}`, data);
        } else {
            await axios.post('/api/admin/semesters', data);
        }
        modal.hide();
        loadData();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Terjadi kesalahan');
    }
}

// ── Set Aktif ──
async function setAktif(id) {
    if (!confirm('Set semester ini sebagai aktif?')) return;
    await axios.post(`/api/admin/semesters/${id}/aktif`);
    loadData();
}

// ── Hapus ──
async function hapus(id) {
    if (!confirm('Yakin hapus semester ini?')) return;
    await axios.delete(`/api/admin/semesters/${id}`);
    loadData();
}

loadData();
</script>
@endpush