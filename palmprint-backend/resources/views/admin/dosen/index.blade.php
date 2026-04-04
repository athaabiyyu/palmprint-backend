@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manajemen Dosen</h4>
    <button class="btn btn-primary" onclick="showModal()">
        <i class="bi bi-plus-lg me-1"></i> Tambah Dosen
    </button>
</div>

<!-- Tabel Dosen -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>NIP</th>
                    <th>Nama Dosen</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tableDosen">
                <tr><td colspan="4" class="text-center">Memuat data...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div class="modal fade" id="modalDosen" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Tambah Dosen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="dosenId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">NIP</label>
                    <input type="text" id="nip" class="form-control" placeholder="contoh: 19800716201012001">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Dosen</label>
                    <input type="text" id="nama" class="form-control" placeholder="contoh: Yuri Ariyanto">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password</label>
                    <input type="password" id="password" class="form-control" placeholder="Minimal 6 karakter">
                    <div class="form-text" id="passwordHint">Kosongkan jika tidak ingin mengubah password</div>
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
const modal = new bootstrap.Modal(document.getElementById('modalDosen'));

// ── Load Data ──
async function loadData() {
    const res   = await axios.get('/api/admin/dosens');
    const data  = res.data;
    const tbody = document.getElementById('tableDosen');

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Belum ada data</td></tr>';
        return;
    }

    tbody.innerHTML = data.map((d, i) => `
        <tr>
            <td>${i + 1}</td>
            <td>${d.nip}</td>
            <td>${d.nama}</td>
            <td>
                <button class="btn btn-warning btn-sm me-1" onclick="edit(${d.id}, '${d.nip}', '${d.nama}')">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-danger btn-sm" onclick="hapus(${d.id})">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

// ── Show Modal Tambah ──
function showModal() {
    document.getElementById('modalTitle').innerText  = 'Tambah Dosen';
    document.getElementById('dosenId').value         = '';
    document.getElementById('nip').value             = '';
    document.getElementById('nama').value            = '';
    document.getElementById('password').value        = '';
    document.getElementById('passwordHint').style.display = 'none';
    document.getElementById('password').placeholder = 'Minimal 6 karakter';
    modal.show();
}

// ── Show Modal Edit ──
function edit(id, nip, nama) {
    document.getElementById('modalTitle').innerText  = 'Edit Dosen';
    document.getElementById('dosenId').value         = id;
    document.getElementById('nip').value             = nip;
    document.getElementById('nama').value            = nama;
    document.getElementById('password').value        = '';
    document.getElementById('passwordHint').style.display = 'block';
    document.getElementById('password').placeholder = 'Kosongkan jika tidak diubah';
    modal.show();
}

// ── Simpan ──
async function simpan() {
    const id       = document.getElementById('dosenId').value;
    const password = document.getElementById('password').value;

    const data = {
        nip  : document.getElementById('nip').value,
        nama : document.getElementById('nama').value,
    };

    if (!id && !password) {
        alert('Password wajib diisi untuk dosen baru!');
        return;
    }

    if (password) data.password = password;

    if (!data.nip || !data.nama) {
        alert('NIP dan nama harus diisi!');
        return;
    }

    try {
        if (id) {
            await axios.put(`/api/admin/dosens/${id}`, data);
        } else {
            await axios.post('/api/admin/dosens', data);
        }
        modal.hide();
        loadData();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Terjadi kesalahan');
    }
}

// ── Hapus ──
async function hapus(id) {
    if (!confirm('Yakin hapus dosen ini?')) return;
    await axios.delete(`/api/admin/dosens/${id}`);
    loadData();
}

loadData();
</script>
@endpush