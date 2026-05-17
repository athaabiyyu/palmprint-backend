@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0">Manajemen Dosen</h4>
    <button class="btn btn-primary" onclick="showModal()">
        <i class="bi bi-plus-lg me-1"></i> Tambah Dosen
    </button>
</div>

<!-- Tabel -->
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>NIP</th>
                    <th>Nama</th>
                    <th>Status</th>
                    <th>Password Default</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody id="tableDosen">
                <tr><td colspan="6" class="text-center">Memuat data...</td></tr>
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
                    <input type="text" id="nip" class="form-control"
                        placeholder="contoh: 198501012010011001">
                    <small class="text-muted">NIP akan digunakan sebagai password default</small>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Dosen</label>
                    <input type="text" id="nama" class="form-control"
                        placeholder="contoh: Dr. Budi Santoso">
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
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Belum ada data</td></tr>';
        return;
    }

    tbody.innerHTML = data.map((d, i) => `
        <tr class="${!d.is_active ? 'table-secondary' : ''}">
            <td>${i + 1}</td>
            <td><span class="badge bg-dark">${d.nip}</span></td>
            <td>
                ${d.nama}
                ${!d.is_active ? '<span class="badge bg-danger ms-1">Nonaktif</span>' : ''}
            </td>
            <td>
                ${d.is_active
                    ? '<span class="badge bg-success">Aktif</span>'
                    : '<span class="badge bg-danger">Nonaktif</span>'
                }
            </td>
            <td>
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>Sama dengan NIP
                </small>
            </td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-warning btn-sm"
                        onclick="edit(${d.id}, '${d.nip}', '${d.nama}')"
                        title="Edit">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-info btn-sm"
                        onclick="resetPassword(${d.id}, '${d.nama}')"
                        title="Reset Password ke NIP">
                        <i class="bi bi-key"></i>
                    </button>
                    <button class="btn btn-${d.is_active ? 'secondary' : 'success'} btn-sm"
                        onclick="toggleAktif(${d.id}, ${d.is_active})"
                        title="${d.is_active ? 'Nonaktifkan' : 'Aktifkan'}">
                        <i class="bi bi-${d.is_active ? 'slash-circle' : 'check-circle'}"></i>
                    </button>
                    <button class="btn btn-danger btn-sm"
                        onclick="hapus(${d.id})"
                        title="Hapus">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// ── Show Modal Tambah ──
function showModal() {
    document.getElementById('modalTitle').innerText = 'Tambah Dosen';
    document.getElementById('dosenId').value        = '';
    document.getElementById('nip').value            = '';
    document.getElementById('nama').value           = '';
    modal.show();
}

// ── Show Modal Edit ──
function edit(id, nip, nama) {
    document.getElementById('modalTitle').innerText = 'Edit Dosen';
    document.getElementById('dosenId').value        = id;
    document.getElementById('nip').value            = nip;
    document.getElementById('nama').value           = nama;
    modal.show();
}

// ── Simpan ──
async function simpan() {
    const id   = document.getElementById('dosenId').value;
    const data = {
        nip  : document.getElementById('nip').value,
        nama : document.getElementById('nama').value,
    };

    if (!data.nip || !data.nama) {
        alert('NIP dan nama harus diisi!');
        return;
    }

    try {
        if (id) {
            await axios.put(`/api/admin/dosens/${id}`, data);
        } else {
            await axios.post('/api/admin/dosens', data);
            alert('Dosen berhasil ditambahkan!\nPassword default: ' + data.nip);
        }
        modal.hide();
        loadData();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Terjadi kesalahan');
    }
}

// ── Toggle Aktif ──
async function toggleAktif(id, isActive) {
    const aksi = isActive ? 'nonaktifkan' : 'aktifkan';
    if (!confirm(`Yakin ${aksi} akun dosen ini?`)) return;
    try {
        const res = await axios.put(`/api/admin/dosens/${id}/toggle-aktif`);
        alert(res.data.message);
        loadData();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Terjadi kesalahan');
    }
}

// ── Reset Password ──
async function resetPassword(id, nama) {
    if (!confirm(`Reset password ${nama} ke NIP?`)) return;
    try {
        await axios.put(`/api/admin/dosens/${id}/reset-password`);
        alert('Password berhasil direset ke NIP!');
    } catch (e) {
        alert(e.response?.data?.message ?? 'Terjadi kesalahan');
    }
}

// ── Hapus ──
async function hapus(id) {
    if (!confirm('Yakin hapus dosen ini?')) return;
    try {
        await axios.delete(`/api/admin/dosens/${id}`);
        loadData();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Gagal menghapus dosen');
    }
}

loadData();
</script>
@endpush