@extends('layouts.admin')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold mb-0">Jurusan & Program Studi</h4>
        <button class="btn btn-primary" onclick="showModalJurusan()">
            <i class="bi bi-plus-lg me-1"></i> Tambah Jurusan
        </button>
    </div>

    <!-- List Jurusan Accordion -->
    <div class="accordion" id="accordionJurusan">
        <!-- diisi JS -->
    </div>
    <div id="emptyJurusan" class="text-center text-muted py-5" style="display:none">
        Belum ada jurusan. Tambah jurusan terlebih dahulu.
    </div>

    <!-- Modal Jurusan -->
    <div class="modal fade" id="modalJurusan" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalJurusanTitle">Tambah Jurusan</h5>
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="simpanJurusan()">Simpan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Program Studi -->
    <div class="modal fade" id="modalProdi" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalProdiTitle">Tambah Program Studi</h5>
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
                        <small class="text-muted">Kode ini akan jadi prefix nama kelas. Contoh: TI-4B</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Program Studi</label>
                        <input type="text" id="prodiNama" class="form-control" placeholder="contoh: Teknik Informatika">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" onclick="simpanProdi()">Simpan</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const modalJurusan = new bootstrap.Modal(document.getElementById('modalJurusan'));
        const modalProdi = new bootstrap.Modal(document.getElementById('modalProdi'));

        // ── Load Data ──
        async function loadData() {
            const res = await axios.get('/api/admin/jurusans');
            const data = res.data;
            const accordion = document.getElementById('accordionJurusan');
            const empty = document.getElementById('emptyJurusan');

            if (data.length === 0) {
                accordion.innerHTML = '';
                empty.style.display = 'block';
                return;
            }

            empty.style.display = 'none';
            accordion.innerHTML = data.map(j => `
        <div class="accordion-item border-0 shadow-sm mb-3 rounded" id="jurusan-${j.id}">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed rounded fw-semibold"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#collapse-${j.id}">
                    <span class="badge bg-primary me-2">${j.kode}</span>
                    ${j.nama}
                    <span class="badge bg-secondary ms-2">${j.program_studis.length} Prodi</span>
                </button>
            </h2>
            <div id="collapse-${j.id}" class="accordion-collapse collapse"
                data-bs-parent="#accordionJurusan">
                <div class="accordion-body pt-0">

                    <!-- Aksi Jurusan -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <small class="text-muted">Daftar Program Studi</small>
                        <div>
                            <button class="btn btn-warning btn-sm me-1"
                                onclick="showModalEditJurusan(${j.id}, '${j.kode}', '${j.nama}')">
                                <i class="bi bi-pencil me-1"></i>Edit Jurusan
                            </button>
                            <button class="btn btn-danger btn-sm me-1"
                                onclick="hapusJurusan(${j.id})">
                                <i class="bi bi-trash me-1"></i>Hapus Jurusan
                            </button>
                            <button class="btn btn-primary btn-sm"
                                onclick="showModalTambahProdi(${j.id}, '${j.nama}')">
                                <i class="bi bi-plus-lg me-1"></i>Tambah Prodi
                            </button>
                        </div>
                    </div>

                    <!-- Tabel Prodi -->
                    ${j.program_studis.length === 0
                        ? `<p class="text-muted text-center">Belum ada program studi</p>`
                        : `<table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>Kode</th>
                                        <th>Nama Program Studi</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${j.program_studis.map((p, i) => `
                                    <tr>
                                        <td>${i + 1}</td>
                                        <td><span class="badge bg-success">${p.kode}</span></td>
                                        <td>${p.nama}</td>
                                        <td>
                                            <button class="btn btn-warning btn-sm me-1"
                                                onclick="showModalEditProdi(${p.id}, ${j.id}, '${j.nama}', '${p.kode}', '${p.nama}')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm"
                                                onclick="hapusProdi(${p.id})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `).join('')}
                                </tbody>
                            </table>`
                    }
                </div>
            </div>
        </div>
    `).join('');
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
            if (!confirm('Yakin hapus jurusan ini? Semua program studi di dalamnya juga akan terhapus!')) return;
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
