@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manajemen Kelas</h4>
    <button class="btn btn-primary" onclick="showModal()">
        <i class="bi bi-plus-lg me-1"></i> Tambah Kelas
    </button>
</div>

<!-- Tabel Kelas -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>Nama Kelas</th>
                    <th>Semester</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tableKelas">
                <tr><td colspan="4" class="text-center">Memuat data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalKelas" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="kelasId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Kelas</label>
                    <input type="text" id="nama" class="form-control" placeholder="contoh: TI-4A">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Semester</label>
                    <select id="semesterId" class="form-select">
                        <option value="">-- Pilih Semester --</option>
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
const modal = new bootstrap.Modal(document.getElementById('modalKelas'));

// ── Load Semester untuk dropdown ──
async function loadSemesters() {
    const res = await axios.get('/api/admin/semesters');
    const select = document.getElementById('semesterId');
    select.innerHTML = '<option value="">-- Pilih Semester --</option>';
    res.data.forEach(s => {
        select.innerHTML += `<option value="${s.id}">${s.nama} (${s.tahun_ajaran})</option>`;
    });
}

// ── Load Data Kelas ──
async function loadData() {
    const res   = await axios.get('/api/admin/kelas');
    const data  = res.data;
    const tbody = document.getElementById('tableKelas');

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Belum ada data</td></tr>';
        return;
    }

    tbody.innerHTML = data.map((k, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${k.nama}</td>
            <td>${k.semester ? k.semester.nama : '-'}</td>
            <td>
                <button class="btn btn-warning btn-sm me-1" onclick="edit(${k.id}, '${k.nama}', ${k.semester_id})">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="hapus(${k.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// ── Show Modal Tambah ──
function showModal() {
    document.getElementById('modalTitle').innerText = 'Tambah Kelas';
    document.getElementById('kelasId').value        = '';
    document.getElementById('nama').value           = '';
    document.getElementById('semesterId').value     = '';
    modal.show();
}

// ── Show Modal Edit ──
function edit(id, nama, semesterId) {
    document.getElementById('modalTitle').innerText = 'Edit Kelas';
    document.getElementById('kelasId').value        = id;
    document.getElementById('nama').value           = nama;
    document.getElementById('semesterId').value     = semesterId;
    modal.show();
}

// ── Simpan ──
async function simpan() {
    const id   = document.getElementById('kelasId').value;
    const data = {
        nama        : document.getElementById('nama').value,
        semester_id : document.getElementById('semesterId').value,
    };

    if (!data.nama || !data.semester_id) {
        alert('Nama kelas dan semester harus diisi!');
        return;
    }

    try {
        if (id) {
            await axios.put(`/api/admin/kelas/${id}`, data);
        } else {
            await axios.post('/api/admin/kelas', data);
        }
        modal.hide();
        loadData();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Terjadi kesalahan');
    }
}

// ── Hapus ──
async function hapus(id) {
    if (!confirm('Yakin hapus kelas ini?')) return;
    await axios.delete(`/api/admin/kelas/${id}`);
    loadData();
}

loadSemesters();
loadData();
</script>
@endpush