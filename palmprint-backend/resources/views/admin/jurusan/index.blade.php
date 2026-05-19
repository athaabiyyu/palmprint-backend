@extends('layouts.admin')

@section('page-title', 'Jurusan & Prodi')

@section('content')

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h5 class="fw-bold mb-0">Jurusan & Program Studi</h5>
            <small class="text-muted">Kelola struktur organisasi akademik</small>
        </div>
        <button class="btn btn-primary" onclick="showModalJurusan()">
            <i class="bi bi-plus-lg me-1"></i> Tambah Jurusan
        </button>
    </div>

    {{-- Loading --}}
    <div id="loadingState" class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <div class="text-muted mt-2">Memuat data...</div>
    </div>

    {{-- Empty --}}
    <div id="emptyState" class="text-center py-5 d-none">
        <i class="bi bi-diagram-3 fs-1 text-muted d-block mb-2"></i>
        <div class="text-muted">Belum ada jurusan. Tambah jurusan terlebih dahulu.</div>
    </div>

    {{-- Container Jurusan --}}
    <div id="jurusanContainer"></div>

    {{-- Modal Jurusan --}}
    <div class="modal fade" id="modalJurusan" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:16px; border:none">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalJurusanTitle">Tambah Jurusan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="jurusanId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kode</label>
                        <input type="text" id="jurusanKode" class="form-control" placeholder="contoh: TK"
                            style="text-transform:uppercase">
                        <small class="text-muted">Maks. 10 karakter</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Jurusan</label>
                        <input type="text" id="jurusanNama" class="form-control" placeholder="contoh: Teknik">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="simpanJurusan()">
                        <i class="bi bi-check-lg me-1"></i>Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Program Studi --}}
    <div class="modal fade" id="modalProdi" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:16px; border:none">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalProdiTitle">Tambah Program Studi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="prodiId">
                    <input type="hidden" id="prodiJurusanId">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Jurusan</label>
                        <input type="text" id="prodiJurusanNama" class="form-control" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Kode</label>
                        <input type="text" id="prodiKode" class="form-control" placeholder="contoh: TI"
                            style="text-transform:uppercase">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Kode jadi prefix nama kelas — contoh: TI-4B
                        </small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Program Studi</label>
                        <input type="text" id="prodiNama" class="form-control" placeholder="contoh: Teknik Informatika">
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="simpanProdi()">
                        <i class="bi bi-check-lg me-1"></i>Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        const modalJurusan = new bootstrap.Modal(document.getElementById('modalJurusan'));
        const modalProdi = new bootstrap.Modal(document.getElementById('modalProdi'));

        // Pagination state per jurusan
        const pageState = {};
        const PAGE_SIZE = 5;

        // ── Load Data ──
        async function loadData() {
            document.getElementById('loadingState').classList.remove('d-none');
            document.getElementById('emptyState').classList.add('d-none');
            document.getElementById('jurusanContainer').innerHTML = '';

            const res = await axios.get('/api/admin/jurusans');
            const data = res.data;

            document.getElementById('loadingState').classList.add('d-none');

            if (data.length === 0) {
                document.getElementById('emptyState').classList.remove('d-none');
                return;
            }

            // Init page state
            data.forEach(j => {
                if (!pageState[j.id]) pageState[j.id] = 1;
            });

            document.getElementById('jurusanContainer').innerHTML = data.map(j => renderJurusan(j)).join('');
        }

        // ── Render satu card Jurusan ──
        function renderJurusan(j) {
            const prodis = j.program_studis ?? [];
            const page = pageState[j.id] ?? 1;
            const total = prodis.length;
            const totalPage = Math.ceil(total / PAGE_SIZE);
            const start = (page - 1) * PAGE_SIZE;
            const shown = prodis.slice(start, start + PAGE_SIZE);

            const tableRows = shown.map((p, i) => `
        <tr>
            <td style="color:#64748b; font-size:0.85rem">${start + i + 1}</td>
            <td>
                <span style="
                    background:#eff6ff; color:#1d4ed8;
                    font-size:0.75rem; font-weight:600;
                    padding:3px 10px; border-radius:20px">
                    ${p.kode}
                </span>
            </td>
            <td style="font-weight:500">${p.nama}</td>
            <td>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm"
                        style="background:#fff7ed; color:#d97706; border:none; border-radius:8px; padding:4px 10px"
                        onclick="showModalEditProdi(${p.id}, ${j.id}, '${j.nama}', '${p.kode}', '${p.nama}')">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm"
                        style="background:#fef2f2; color:#dc2626; border:none; border-radius:8px; padding:4px 10px"
                        onclick="hapusProdi(${p.id})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');

            const pagination = totalPage > 1 ? `
        <div class="d-flex justify-content-between align-items-center mt-3 px-1">
            <small class="text-muted">
                Menampilkan ${start + 1}–${Math.min(start + PAGE_SIZE, total)} dari ${total} prodi
            </small>
            <div class="d-flex gap-1">
                <button class="btn btn-sm btn-light" ${page <= 1 ? 'disabled' : ''}
                    onclick="changePage(${j.id}, ${page - 1})">
                    <i class="bi bi-chevron-left"></i>
                </button>
                ${Array.from({length: totalPage}, (_, i) => ` <
                button class = "btn btn-sm ${i + 1 === page ? 'btn-primary' : 'btn-light'}"
            onclick = "changePage(${j.id}, ${i + 1})" >
                $ {
                    i + 1
                } <
                /button>
            `).join('')}
                <button class="btn btn-sm btn-light" ${page >= totalPage ? 'disabled' : ''}
                    onclick="changePage(${j.id}, ${page + 1})">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    `: total > 0 ? `
        <div class="mt-2 px-1">
            <small class="text-muted">${total} program studi</small>
        </div>
    ` : '';

            return `
        <div class="card border-0 shadow-sm mb-4" style="border-radius:16px; overflow:hidden" id="card-jurusan-${j.id}">

            {{-- Card Header --}}
            <div class="card-header border-0 py-3 px-4"
                style="background:#fff">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <div style="
                            width:44px; height:44px;
                            background:#eff6ff;
                            border-radius:12px;
                            display:flex; align-items:center; justify-content:center;
                            font-weight:700; color:#1d4ed8; font-size:0.9rem">
                            ${j.kode}
                        </div>
                        <div>
                            <div style="font-weight:600; color:#0f172a">${j.nama}</div>
                            <div style="font-size:0.75rem; color:#94a3b8">
                                ${prodis.length} Program Studi
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm"
                            style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; color:#64748b"
                            onclick="showModalEditJurusan(${j.id}, '${j.kode}', '${j.nama}')">
                            <i class="bi bi-pencil me-1"></i>Edit
                        </button>
                        <button class="btn btn-sm"
                            style="background:#eff6ff; border:none; border-radius:8px; color:#1d4ed8; font-weight:500"
                            onclick="showModalTambahProdi(${j.id}, '${j.nama}')">
                            <i class="bi bi-plus-lg me-1"></i>Tambah Prodi
                        </button>
                        <button class="btn btn-sm"
                            style="background:#fef2f2; border:none; border-radius:8px; color:#dc2626"
                            onclick="hapusJurusan(${j.id})">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="card-body px-4 pb-4 pt-0" id="prodi-body-${j.id}">
                ${prodis.length === 0
                    ? `<div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox d-block fs-3 mb-2"></i>
                                Belum ada program studi
                            </div>`
                    : `<table class="table align-middle mb-0" style="margin-top:1px">
                                <thead>
                                    <tr style="border-bottom:2px solid #f1f5f9">
                                        <th style="color:#94a3b8; font-size:0.75rem; font-weight:600;
                                            text-transform:uppercase; padding:12px 8px; width:50px">No</th>
                                        <th style="color:#94a3b8; font-size:0.75rem; font-weight:600;
                                            text-transform:uppercase; padding:12px 8px; width:100px">Kode</th>
                                        <th style="color:#94a3b8; font-size:0.75rem; font-weight:600;
                                            text-transform:uppercase; padding:12px 8px">Nama Program Studi</th>
                                        <th style="color:#94a3b8; font-size:0.75rem; font-weight:600;
                                            text-transform:uppercase; padding:12px 8px; width:100px">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>${tableRows}</tbody>
                            </table>
                            ${pagination}`
                }
            </div>
        </div>
    `;
        }

        // ── Change Page ──
        function changePage(jurusanId, page) {
            pageState[jurusanId] = page;

            // Re-fetch data dan re-render hanya card yang bersangkutan
            axios.get('/api/admin/jurusans').then(res => {
                const jurusan = res.data.find(j => j.id === jurusanId);
                if (!jurusan) return;

                const card = document.getElementById(`card-jurusan-${jurusanId}`);
                if (card) {
                    card.outerHTML = renderJurusan(jurusan);
                }
            });
        }

        // ── Modal Jurusan: Tambah ──
        function showModalJurusan() {
            document.getElementById('modalJurusanTitle').innerText = 'Tambah Jurusan';
            document.getElementById('jurusanId').value = '';
            document.getElementById('jurusanKode').value = '';
            document.getElementById('jurusanNama').value = '';
            modalJurusan.show();
        }

        // ── Modal Jurusan: Edit ──
        function showModalEditJurusan(id, kode, nama) {
            document.getElementById('modalJurusanTitle').innerText = 'Edit Jurusan';
            document.getElementById('jurusanId').value = id;
            document.getElementById('jurusanKode').value = kode;
            document.getElementById('jurusanNama').value = nama;
            modalJurusan.show();
        }

        // ── Simpan Jurusan ──
        async function simpanJurusan() {
            const id = document.getElementById('jurusanId').value;
            const data = {
                kode: document.getElementById('jurusanKode').value.toUpperCase(),
                nama: document.getElementById('jurusanNama').value,
            };

            if (!data.kode || !data.nama) {
                alert('Kode dan nama jurusan harus diisi!');
                return;
            }

            try {
                if (id) {
                    await axios.put(`/api/admin/jurusans/${id}`, data);
                } else {
                    await axios.post('/api/admin/jurusans', data);
                }
                modalJurusan.hide();
                loadData();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Terjadi kesalahan');
            }
        }

        // ── Hapus Jurusan ──
        async function hapusJurusan(id) {
            if (!confirm('Yakin hapus jurusan ini?')) return;
            try {
                await axios.delete(`/api/admin/jurusans/${id}`);
                loadData();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal menghapus jurusan');
            }
        }

        // ── Modal Prodi: Tambah ──
        function showModalTambahProdi(jurusanId, jurusanNama) {
            document.getElementById('modalProdiTitle').innerText = 'Tambah Program Studi';
            document.getElementById('prodiId').value = '';
            document.getElementById('prodiJurusanId').value = jurusanId;
            document.getElementById('prodiJurusanNama').value = jurusanNama;
            document.getElementById('prodiKode').value = '';
            document.getElementById('prodiNama').value = '';
            modalProdi.show();
        }

        // ── Modal Prodi: Edit ──
        function showModalEditProdi(id, jurusanId, jurusanNama, kode, nama) {
            document.getElementById('modalProdiTitle').innerText = 'Edit Program Studi';
            document.getElementById('prodiId').value = id;
            document.getElementById('prodiJurusanId').value = jurusanId;
            document.getElementById('prodiJurusanNama').value = jurusanNama;
            document.getElementById('prodiKode').value = kode;
            document.getElementById('prodiNama').value = nama;
            modalProdi.show();
        }

        // ── Simpan Prodi ──
        async function simpanProdi() {
            const id = document.getElementById('prodiId').value;
            const data = {
                jurusan_id: document.getElementById('prodiJurusanId').value,
                kode: document.getElementById('prodiKode').value.toUpperCase(),
                nama: document.getElementById('prodiNama').value,
            };

            if (!data.kode || !data.nama) {
                alert('Kode dan nama program studi harus diisi!');
                return;
            }

            try {
                if (id) {
                    await axios.put(`/api/admin/prodis/${id}`, data);
                } else {
                    await axios.post('/api/admin/prodis', data);
                }
                modalProdi.hide();
                loadData();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Terjadi kesalahan');
            }
        }

        // ── Hapus Prodi ──
        async function hapusProdi(id) {
            if (!confirm('Yakin hapus program studi ini?')) return;
            try {
                await axios.delete(`/api/admin/prodis/${id}`);
                loadData();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal menghapus program studi');
            }
        }

        loadData();
    </script>
@endpush
