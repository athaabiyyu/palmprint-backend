@extends('layouts.admin')

@section('page-title', 'Akademik — Semester')

@section('content')

{{-- Header --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="fw-bold mb-0">Manajemen Semester</h5>
        <small class="text-muted">Kelola periode akademik aktif</small>
    </div>
    <button class="btn btn-primary" onclick="showModal()">
        <i class="bi bi-plus-lg me-1"></i> Tambah Semester
    </button>
</div>

{{-- Cards Semester --}}
<div id="semesterContainer" class="row g-3">
    <div class="col-12 text-center py-5">
        <div class="spinner-border text-primary"></div>
        <div class="text-muted mt-2">Memuat data...</div>
    </div>
</div>

{{-- Modal Tambah/Edit --}}
<div class="modal fade" id="modalSemester" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:16px; border:none">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="modalTitle">Tambah Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="semesterId">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nama Semester</label>
                    <input type="text" id="nama" class="form-control"
                        placeholder="contoh: Genap 2024/2025">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tahun Ajaran</label>
                    <input type="text" id="tahunAjaran" class="form-control"
                        placeholder="contoh: 2024/2025">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Tipe</label>
                    <div class="d-flex gap-3 mt-1">
                        <label class="d-flex align-items-center gap-2 cursor-pointer">
                            <input type="radio" name="tipe" id="tipeGanjil" value="ganjil" checked>
                            <span>Ganjil</span>
                        </label>
                        <label class="d-flex align-items-center gap-2 cursor-pointer">
                            <input type="radio" name="tipe" id="tipeGenap" value="genap">
                            <span>Genap</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" onclick="simpan()">
                    <i class="bi bi-check-lg me-1"></i>Simpan
                </button>
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
    const container = document.getElementById('semesterContainer');
    container.innerHTML = `
        <div class="col-12 text-center py-5">
            <div class="spinner-border text-primary"></div>
            <div class="text-muted mt-2">Memuat data...</div>
        </div>`;

    const res  = await axios.get('/api/admin/semesters');
    const data = res.data;

    if (data.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-calendar-x fs-1 text-muted d-block mb-2"></i>
                <div class="text-muted">Belum ada semester. Tambah semester terlebih dahulu.</div>
            </div>`;
        return;
    }

    container.innerHTML = data.map(s => {
        const isActive  = s.is_active;
        const tipeColor = s.tipe === 'ganjil'
            ? { bg: '#eff6ff', text: '#1d4ed8' }
            : { bg: '#f0fdf4', text: '#16a34a' };

        return `
            <div class="col-md-6 col-lg-4">
                <div class="card border-0 shadow-sm h-100"
                    style="border-radius:16px; overflow:hidden;
                    ${isActive ? 'border-top:3px solid #1d4ed8 !important' : ''}">

                    {{-- Top accent untuk semester aktif --}}
                    ${isActive ? '<div style="height:4px; background:#1d4ed8"></div>' : ''}

                    <div class="card-body p-4">

                        {{-- Badge aktif --}}
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div style="
                                background:${tipeColor.bg};
                                color:${tipeColor.text};
                                font-size:0.72rem;
                                font-weight:600;
                                padding:4px 12px;
                                border-radius:20px">
                                ${s.tipe.charAt(0).toUpperCase() + s.tipe.slice(1)}
                            </div>
                            ${isActive
                                ? `<div style="
                                    display:flex; align-items:center; gap:6px;
                                    background:#eff6ff; color:#1d4ed8;
                                    font-size:0.72rem; font-weight:600;
                                    padding:4px 12px; border-radius:20px">
                                    <span style="
                                        width:7px; height:7px;
                                        background:#1d4ed8;
                                        border-radius:50%;
                                        animation: pulse 1.5s infinite">
                                    </span>
                                    Aktif
                                </div>`
                                : '<div></div>'
                            }
                        </div>

                        {{-- Nama & Tahun --}}
                        <div style="font-size:1.1rem; font-weight:700; color:#0f172a; margin-bottom:4px">
                            ${s.nama}
                        </div>
                        <div style="color:#94a3b8; font-size:0.82rem; margin-bottom:20px">
                            <i class="bi bi-calendar3 me-1"></i>${s.tahun_ajaran}
                        </div>

                        {{-- Aksi --}}
                        <div class="d-flex gap-2">
                            ${!isActive
                                ? `<button class="btn btn-sm flex-grow-1"
                                    style="background:#eff6ff; color:#1d4ed8; border:none; border-radius:8px; font-weight:500"
                                    onclick="setAktif(${s.id})">
                                    <i class="bi bi-check-circle me-1"></i>Set Aktif
                                </button>`
                                : `<div class="flex-grow-1"></div>`
                            }
                            <button class="btn btn-sm"
                                style="background:#f8fafc; color:#64748b; border:1px solid #e2e8f0; border-radius:8px"
                                onclick="edit(${s.id}, '${s.nama}', '${s.tahun_ajaran}', '${s.tipe}')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm"
                                style="background:#fef2f2; color:#dc2626; border:none; border-radius:8px"
                                onclick="hapus(${s.id})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// ── Show Modal Tambah ──
function showModal() {
    document.getElementById('modalTitle').innerText  = 'Tambah Semester';
    document.getElementById('semesterId').value      = '';
    document.getElementById('nama').value            = '';
    document.getElementById('tahunAjaran').value     = '';
    document.getElementById('tipeGanjil').checked    = true;
    modal.show();
}

// ── Show Modal Edit ──
function edit(id, nama, tahunAjaran, tipe) {
    document.getElementById('modalTitle').innerText = 'Edit Semester';
    document.getElementById('semesterId').value     = id;
    document.getElementById('nama').value           = nama;
    document.getElementById('tahunAjaran').value    = tahunAjaran;
    document.querySelector(`input[name="tipe"][value="${tipe}"]`).checked = true;
    modal.show();
}

// ── Simpan ──
async function simpan() {
    const id   = document.getElementById('semesterId').value;
    const data = {
        nama         : document.getElementById('nama').value,
        tahun_ajaran : document.getElementById('tahunAjaran').value,
        tipe         : document.querySelector('input[name="tipe"]:checked').value,
    };

    if (!data.nama || !data.tahun_ajaran) {
        alert('Nama dan tahun ajaran harus diisi!');
        return;
    }

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
    try {
        await axios.post(`/api/admin/semesters/${id}/aktif`);
        loadData();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Terjadi kesalahan');
    }
}

// ── Hapus ──
async function hapus(id) {
    if (!confirm('Yakin hapus semester ini?')) return;
    try {
        await axios.delete(`/api/admin/semesters/${id}`);
        loadData();
    } catch (e) {
        alert(e.response?.data?.message ?? 'Gagal menghapus semester');
    }
}

loadData();
</script>

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}
</style>
@endpush