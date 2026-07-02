<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') — Palmprint Dosen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f0f4ff;
        }

        /* ── Topbar ── */
        .topbar {
            background: #1e3a8a;
            padding: 0 28px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(30, 58, 138, 0.15);
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            text-decoration: none;
        }

        .topbar-brand .brand-icon {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .topbar-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: rgba(255, 255, 255, 0.85);
            border-radius: 10px;
            padding: 7px 14px;
            font-size: 0.82rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .topbar-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .topbar-btn.danger:hover {
            background: rgba(239, 68, 68, 0.3);
        }

        .topbar-divider {
            width: 1px;
            height: 24px;
            background: rgba(255, 255, 255, 0.15);
        }

        .topbar-name {
            color: #fff;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .topbar-role {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.72rem;
        }

        .topbar-avatar {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        /* ── Subbar (filter hari) ── */
        .subbar {
            background: #fff;
            border-bottom: 1px solid #e8edf5;
            padding: 0 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 52px;
            position: sticky;
            top: 64px;
            z-index: 99;
        }

        .filter-hari-btn {
            background: none;
            border: none;
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filter-hari-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .filter-hari-btn.active {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
        }

        /* ── Main ── */
        .main-content {
            padding: 28px;
        }

        /* ── Jadwal Card ── */
        .jadwal-card {
            background: #fff;
            border-radius: 16px;
            border: 1.5px solid #e8edf5;
            padding: 20px;
            transition: all 0.2s;
            height: 100%;
        }

        .jadwal-card:hover {
            border-color: #bfdbfe;
            box-shadow: 0 4px 16px rgba(30, 58, 138, 0.08);
            transform: translateY(-2px);
        }

        .jadwal-card.is-today {
            border-color: #1d4ed8;
            border-left: 4px solid #1d4ed8;
        }

        .jadwal-card.not-today {
            opacity: 0.75;
        }

        /* Card Modal */
        .modal-content {
            border-radius: 16px !important;
            border: none !important;
        }

        .modal-header {
            border-bottom: 1px solid #f1f5f9 !important;
        }

        .modal-footer {
            border-top: 1px solid #f1f5f9 !important;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        @keyframes pulse-dot {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: 0.5;
                transform: scale(0.8);
            }
        }
    </style>
</head>

<body>

    <!-- Topbar -->
    <nav class="topbar">
        <div class="topbar-brand">
            <div class="brand-icon">
                <i class="bi bi-hand-index-thumb"></i>
            </div>
            <div>
                <div style="font-size:0.9rem; font-weight:700">Palmprint</div>
                <div style="font-size:0.65rem; color:rgba(255,255,255,0.5); font-weight:400">Portal Dosen</div>
            </div>
        </div>

        <div class="topbar-right">
            {{-- Semester aktif --}}
            @php $semAktif = \App\Models\Semester::where('is_active', true)->first(); @endphp
            @if ($semAktif)
                <div
                    style="
                    background:rgba(255,255,255,0.1);
                    border-radius:8px;
                    padding:5px 12px;
                    color:rgba(255,255,255,0.8);
                    font-size:0.75rem">
                    <i class="bi bi-calendar-check me-1"></i>{{ $semAktif->nama }}
                </div>
                <div class="topbar-divider"></div>
            @endif

            {{-- Avatar + Nama --}}
            <div class="topbar-avatar" id="topbarAvatar">D</div>
            <div>
                <div class="topbar-name" id="topbarNama">Dosen</div>
                <div class="topbar-role" id="topbarNip">-</div>
            </div>

            <div class="topbar-divider"></div>

            {{-- Action buttons --}}
            <button class="topbar-btn" onclick="showModalProfil()">
                <i class="bi bi-person-circle"></i> Profil
            </button>
            <button class="topbar-btn" onclick="showModalRekap()">
                <i class="bi bi-clipboard-data"></i> Rekap
            </button>
            <button class="topbar-btn danger" onclick="logout()">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </button>
        </div>
    </nav>

    <!-- Subbar — Filter Hari -->
    <div class="subbar">
        <div class="d-flex align-items-center gap-1">
            <button class="filter-hari-btn active" data-hari="" onclick="setFilterHari(this, '')">
                Semua
            </button>
            <button class="filter-hari-btn" data-hari="senin" onclick="setFilterHari(this, 'senin')">Senin</button>
            <button class="filter-hari-btn" data-hari="selasa" onclick="setFilterHari(this, 'selasa')">Selasa</button>
            <button class="filter-hari-btn" data-hari="rabu" onclick="setFilterHari(this, 'rabu')">Rabu</button>
            <button class="filter-hari-btn" data-hari="kamis" onclick="setFilterHari(this, 'kamis')">Kamis</button>
            <button class="filter-hari-btn" data-hari="jumat" onclick="setFilterHari(this, 'jumat')">Jumat</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span style="color:#94a3b8; font-size:0.78rem" id="subbarTanggal"></span>
            <button class="filter-hari-btn" onclick="loadJadwal()">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        @yield('content')
    </div>

    <!-- Modal Buka Sesi -->
    <div class="modal fade" id="modalBukaSesi" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Buka Sesi Absensi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="jadwalId">
                    <div class="p-3 rounded-3 mb-3" style="background:#eff6ff; border:1px solid #bfdbfe">
                        <div style="font-size:0.8rem; color:#64748b">Mata Kuliah</div>
                        <div class="fw-bold" id="infoMatkul" style="color:#1e293b"></div>
                        <div style="font-size:0.8rem; color:#64748b; margin-top:4px">Kelas</div>
                        <div class="fw-semibold" id="infoKelas" style="color:#1e293b"></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Durasi Absensi</label>
                        <div class="d-flex gap-2 flex-wrap">
                            @php
                                $durasiOptions = [10, 15, 20, 30, 60, 180];
                                $durasiLabel = function ($menit) {
                                    if ($menit >= 60) {
                                        $jam = intdiv($menit, 60);
                                        $sisaMenit = $menit % 60;
                                        return $sisaMenit > 0 ? "{$jam} jam {$sisaMenit} mnt" : "{$jam} jam";
                                    }
                                    return "{$menit} mnt";
                                };
                            @endphp
                            @foreach ($durasiOptions as $dur)
                                <label style="cursor:pointer">
                                    <input type="radio" name="durasi" value="{{ $dur }}"
                                        {{ $dur === 15 ? 'checked' : '' }} style="display:none"
                                        onchange="selectDurasi(this)">
                                    <div class="durasi-btn" data-val="{{ $dur }}"
                                        style="
                padding:8px 16px;
                border-radius:10px;
                border:1.5px solid #e2e8f0;
                font-size:0.82rem;
                font-weight:500;
                color:#64748b;
                background:#f8fafc;
                {{ $dur === 15 ? 'border-color:#1d4ed8; background:#eff6ff; color:#1d4ed8;' : '' }}
            ">
                                        {{ $durasiLabel($dur) }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-success" onclick="bukaSesi()">
                        <i class="bi bi-unlock me-1"></i> Buka Absensi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Detail Sesi -->
    <div class="modal fade" id="modalDetailSesi" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="detailMatkul">Detail Absensi</h5>
                        <small class="text-muted" id="detailKelas"></small>
                    </div>
                    <div class="d-flex align-items-center gap-3 ms-auto me-3">
                        <div class="text-center">
                            <div style="font-size:0.7rem; color:#94a3b8">Sisa Waktu</div>
                            <div id="countdown"
                                style="font-size:1.3rem; font-weight:700; color:#dc2626; font-variant-numeric:tabular-nums">
                                --:--
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-4" id="statistikAbsen"></div>
                    <div style="max-height:320px; overflow-y:auto">
                        <table class="table table-sm align-middle">
                            <thead style="position:sticky; top:0; background:#fff">
                                <tr style="border-bottom:2px solid #f1f5f9">
                                    <th
                                        style="color:#94a3b8; font-size:0.72rem; text-transform:uppercase; font-weight:600">
                                        NIM</th>
                                    <th
                                        style="color:#94a3b8; font-size:0.72rem; text-transform:uppercase; font-weight:600">
                                        Nama</th>
                                    <th
                                        style="color:#94a3b8; font-size:0.72rem; text-transform:uppercase; font-weight:600">
                                        Status</th>
                                    <th
                                        style="color:#94a3b8; font-size:0.72rem; text-transform:uppercase; font-weight:600">
                                        Waktu</th>
                                </tr>
                            </thead>
                            <tbody id="tableDaftarHadir"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-danger" id="btnTutupSesi" onclick="tutupSesi()">
                        <i class="bi bi-lock me-1"></i> Tutup Absensi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Profil -->
    <div class="modal fade" id="modalProfil" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Profil Saya</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <ul class="nav nav-tabs px-4 pt-3" id="tabProfil">
                        <li class="nav-item">
                            <button class="nav-link active" onclick="switchTab('info')">
                                <i class="bi bi-person me-1"></i>Info
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" onclick="switchTab('password')">
                                <i class="bi bi-key me-1"></i>Ganti Password
                            </button>
                        </li>
                    </ul>
                    <div class="p-4">
                        <div id="tabInfo">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">NIP</label>
                                <input type="text" id="profilNip" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Nama</label>
                                <input type="text" id="profilNama" class="form-control">
                            </div>
                            <button class="btn btn-primary w-100" onclick="simpanProfil()">
                                <i class="bi bi-check-lg me-1"></i>Simpan
                            </button>
                        </div>
                        <div id="tabPassword" class="d-none">
                            <div class="p-3 rounded-3 mb-3"
                                style="background:#eff6ff; font-size:0.82rem; color:#1d4ed8">
                                <i class="bi bi-info-circle me-1"></i>
                                Password default adalah NIP. Segera ganti setelah login pertama!
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Password Lama</label>
                                <input type="password" id="passwordLama" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Password Baru</label>
                                <input type="password" id="passwordBaru" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                                <input type="password" id="passwordKonfirmasi" class="form-control">
                            </div>
                            <button class="btn btn-warning w-100" onclick="simpanPassword()">
                                <i class="bi bi-key me-1"></i>Ganti Password
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Rekap -->
    <div class="modal fade" id="modalRekap" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-clipboard-data me-2 text-primary"></i>Rekap Kehadiran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 align-items-end mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Mata Kuliah</label>
                            <select id="rekapJadwalId" class="form-select"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Dari Tanggal</label>
                            <input type="date" id="rekapDari" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Sampai Tanggal</label>
                            <input type="date" id="rekapSampai" class="form-control">
                        </div>
                        <div class="col-md-2 d-flex gap-1">
                            <button class="btn btn-primary w-100" onclick="loadRekapDetail()">
                                <i class="bi bi-search me-1"></i>Tampilkan
                            </button>
                            <button class="btn btn-outline-secondary" onclick="loadRiwayat()" title="Riwayat Sesi">
                                <i class="bi bi-clock-history"></i>
                            </button>
                        </div>
                    </div>

                    <div id="rekapInfo" class="d-none">
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div style="background:#f8fafc; border-radius:12px; padding:12px 16px">
                                    <div
                                        style="font-size:0.72rem; color:#94a3b8; font-weight:600; text-transform:uppercase">
                                        Mata Kuliah</div>
                                    <div class="fw-bold mt-1" id="rekapInfoMatkul">-</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div style="background:#f8fafc; border-radius:12px; padding:12px 16px">
                                    <div
                                        style="font-size:0.72rem; color:#94a3b8; font-weight:600; text-transform:uppercase">
                                        Kelas</div>
                                    <div class="fw-bold mt-1" id="rekapInfoKelas">-</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div style="background:#f8fafc; border-radius:12px; padding:12px 16px">
                                    <div
                                        style="font-size:0.72rem; color:#94a3b8; font-weight:600; text-transform:uppercase">
                                        Total Pertemuan</div>
                                    <div class="fw-bold mt-1" id="rekapInfoPertemuan">-</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div style="background:#f8fafc; border-radius:12px; padding:12px 16px">
                                    <div
                                        style="font-size:0.72rem; color:#94a3b8; font-weight:600; text-transform:uppercase">
                                        Total Mahasiswa</div>
                                    <div class="fw-bold mt-1" id="rekapInfoMahasiswa">-</div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>No</th>
                                        <th>NIM</th>
                                        <th>Nama</th>
                                        <th class="text-center text-success">Hadir</th>
                                        <th class="text-center text-danger">Alpha</th>
                                        <th class="text-center text-warning">Izin</th>
                                        <th class="text-center" style="color:#0891b2">Sakit</th>
                                        <th class="text-center">% Hadir</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="rekapBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="rekapEmpty" class="text-center text-muted py-5">
                        <i class="bi bi-clipboard-data fs-1 d-block mb-2 text-muted"></i>
                        Pilih mata kuliah untuk melihat rekap
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Riwayat -->
    <div class="modal fade" id="modalRiwayat" tabindex="-1" style="z-index:1060">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Riwayat Sesi</h5>
                        <small class="text-muted">
                            <span id="riwayatMatkul"></span>
                            <span id="riwayatKelas" class="ms-1"></span>
                        </small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="riwayatLoading" class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                    </div>
                    <div id="riwayatContent" class="d-none">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal</th>
                                    <th>Dibuka</th>
                                    <th>Ditutup</th>
                                    <th class="text-center">Hadir</th>
                                    <th class="text-center">Alpha</th>
                                    <th class="text-center">% Hadir</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="riwayatBody"></tbody>
                        </table>
                        <div id="riwayatEmpty" class="text-center text-muted py-4 d-none">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            Belum ada sesi yang pernah dibuka
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        // ── Auth ──
        const token = localStorage.getItem('dosen_token');
        if (!token) window.location.href = '/dosen/login';
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        axios.defaults.headers.common['Accept'] = 'application/json';

        // ── Data ──
        const dosen = JSON.parse(localStorage.getItem('dosen_data') ?? '{}');
        let activeSesiId = null;
        let countdownTimer = null;
        let filterHari = null;
        let allJadwals = [];
        let hariSekarang = '';
        let detailRefreshTimer = null;

        const hariMap = {
            senin: 'Senin',
            selasa: 'Selasa',
            rabu: 'Rabu',
            kamis: 'Kamis',
            jumat: 'Jumat'
        };

        const DEV_MODE = true; // Hapus / set false saat production

        const modalBuka = new bootstrap.Modal(document.getElementById('modalBukaSesi'));
        const modalDetail = new bootstrap.Modal(document.getElementById('modalDetailSesi'));
        const modalProfil = new bootstrap.Modal(document.getElementById('modalProfil'));
        const modalRekap = new bootstrap.Modal(document.getElementById('modalRekap'));
        const modalRiwayat = new bootstrap.Modal(document.getElementById('modalRiwayat'));

        // ── Init topbar ──
        document.getElementById('topbarNama').innerText = dosen.nama ?? 'Dosen';
        document.getElementById('topbarNip').innerText = dosen.nip ?? '-';
        document.getElementById('topbarAvatar').innerText = (dosen.nama ?? 'D')[0].toUpperCase();
        document.getElementById('subbarTanggal').innerText = new Date().toLocaleDateString('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });

        // ── Durasi selector ──
        function selectDurasi(radio) {
            document.querySelectorAll('.durasi-btn').forEach(btn => {
                btn.style.borderColor = '#e2e8f0';
                btn.style.background = '#f8fafc';
                btn.style.color = '#64748b';
            });
            const btn = document.querySelector(`.durasi-btn[data-val="${radio.value}"]`);
            if (btn) {
                btn.style.borderColor = '#1d4ed8';
                btn.style.background = '#eff6ff';
                btn.style.color = '#1d4ed8';
            }
        }

        // ── Load Jadwal ──
        async function loadJadwal() {
            const container = document.getElementById('jadwalContainer');
            container.innerHTML = `
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary"></div>
                <div class="text-muted mt-2" style="font-size:0.85rem">Memuat jadwal...</div>
            </div>`;

            try {
                const res = await axios.get('/api/dosen/jadwal-hari-ini');
                allJadwals = res.data;

                const today = allJadwals.find(j => j.is_today);
                if (today) hariSekarang = today.hari;

                if (filterHari === null) {
                    filterHari = hariSekarang;
                    document.querySelectorAll('.filter-hari-btn').forEach(btn => {
                        btn.classList.toggle('active', btn.dataset.hari === filterHari);
                    });
                }

                renderJadwal();
            } catch (e) {
                if (e.response?.status === 401) {
                    localStorage.clear();
                    window.location.href = '/dosen/login';
                }
            }
        }

        // ── Set Filter Hari ──
        function setFilterHari(btn, hari) {
            filterHari = hari;
            document.querySelectorAll('.filter-hari-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            renderJadwal();
        }

        // ── Render Jadwal ──
        function renderJadwal() {
            const container = document.getElementById('jadwalContainer');
            const filtered = filterHari === '' ?
                allJadwals :
                allJadwals.filter(j => j.hari === filterHari);

            if (filtered.length === 0) {
                container.innerHTML = `
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x" style="font-size:3rem; color:#cbd5e1"></i>
                        <div class="mt-3" style="color:#94a3b8; font-weight:500">
                            Tidak ada jadwal ${filterHari === hariSekarang ? 'hari ini' : 'di hari ini'}
                        </div>
                    </div>
                </div>`;
                return;
            }

            container.innerHTML = filtered.map(j => {
                const sesiAktif = j.sesi_aktif;
                const isToday = j.is_today;

                const statusHtml = sesiAktif ?
                    `<span style="
                    display:inline-flex; align-items:center; gap:5px;
                    background:#f0fdf4; color:#16a34a;
                    font-size:0.72rem; font-weight:600;
                    padding:3px 10px; border-radius:20px">
                    <span style="width:6px; height:6px; background:#16a34a; border-radius:50%;
                        animation:pulse-dot 1.5s infinite"></span>
                    Sesi Aktif
                </span>` :
                    isToday ?
                    `<span style="
                        background:#eff6ff; color:#1d4ed8;
                        font-size:0.72rem; font-weight:600;
                        padding:3px 10px; border-radius:20px">
                        Hari Ini
                    </span>` :
                    `<span style="
                        background:#f1f5f9; color:#94a3b8;
                        font-size:0.72rem; font-weight:600;
                        padding:3px 10px; border-radius:20px">
                        ${hariMap[j.hari]}
                    </span>`;

                const canOpen = isToday || DEV_MODE;

                const actionBtn = sesiAktif ?
                    `<button onclick="lihatDetail(${sesiAktif.id})"
        style="width:100%; padding:10px; background:#f0fdf4;
        border:1.5px solid #bbf7d0; border-radius:10px;
        color:#16a34a; font-weight:600; font-size:0.85rem; cursor:pointer">
        <i class="bi bi-eye me-1"></i>Lihat Absensi
      </button>` :
                    canOpen ?
                    `<button onclick="showModalBuka(${j.id}, '${j.mata_kuliah.nama}', '${j.kelas.nama}')"
            style="width:100%; padding:10px; background:#1d4ed8;
            border:none; border-radius:10px;
            color:#fff; font-weight:600; font-size:0.85rem; cursor:pointer">
            <i class="bi bi-unlock me-1"></i>Buka Absensi${DEV_MODE && !isToday ? ' <span style="font-size:0.7rem;opacity:0.7">[DEV]</span>' : ''}
          </button>` :
                    `<button disabled
            style="width:100%; padding:10px; background:#f1f5f9;
            border:none; border-radius:10px;
            color:#94a3b8; font-weight:500; font-size:0.85rem; cursor:not-allowed">
            <i class="bi bi-lock me-1"></i>Bukan Hari Ini
          </button>`;

                return `
                <div class="col-md-6 col-lg-4">
                    <div class="jadwal-card ${isToday ? 'is-today' : 'not-today'}">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div style="flex:1; margin-right:8px">
                                <div style="font-weight:700; font-size:0.95rem; color:#0f172a; line-height:1.3">
                                    ${j.mata_kuliah.nama}
                                </div>
                                <div style="font-size:0.78rem; color:#94a3b8; margin-top:2px">
                                    ${j.kelas.nama} • ${j.mata_kuliah.sks} SKS
                                </div>
                            </div>
                            ${statusHtml}
                        </div>

                        <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:16px">
                            <div style="font-size:0.8rem; color:#64748b; display:flex; align-items:center; gap:6px">
                                <i class="bi bi-clock" style="color:#94a3b8"></i>
                                ${j.jam_mulai.substring(0,5)} — ${j.jam_selesai.substring(0,5)}
                            </div>
                            ${j.ruangan ? `
                                    <div style="font-size:0.8rem; color:#64748b; display:flex; align-items:center; gap:6px">
                                        <i class="bi bi-geo-alt" style="color:#94a3b8"></i>
                                        ${j.ruangan}
                                    </div>` : ''}
                            <div style="font-size:0.8rem; color:#64748b; display:flex; align-items:center; gap:6px">
                                <i class="bi bi-person" style="color:#94a3b8"></i>
                                ${j.dosen?.nama ?? '-'}
                            </div>
                        </div>

                        ${actionBtn}
                    </div>
                </div>`;
            }).join('');
        }

        // ── Show Modal Buka ──
        function showModalBuka(jadwalId, matkul, kelas) {
            document.getElementById('jadwalId').value = jadwalId;
            document.getElementById('infoMatkul').innerText = matkul;
            document.getElementById('infoKelas').innerText = kelas;
            modalBuka.show();
        }

        // ── Buka Sesi ──
        async function bukaSesi() {
            const jadwalId = document.getElementById('jadwalId').value;
            const durasi = document.querySelector('input[name="durasi"]:checked')?.value ?? 15;

            try {
                await axios.post('/api/dosen/sesi/buka', {
                    jadwal_id: jadwalId,
                    durasi_menit: durasi,
                });
                modalBuka.hide();
                loadJadwal();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal membuka sesi');
            }
        }

        // ── Lihat Detail Sesi ──
        async function lihatDetail(sesiId) {
            activeSesiId = sesiId;
            await refreshDetail();
            startCountdownFromDetail();
            modalDetail.show();

            // Auto refresh daftar hadir setiap 10 detik
            if (detailRefreshTimer) clearInterval(detailRefreshTimer);
            detailRefreshTimer = setInterval(() => refreshDetail(), 10000);

            document.getElementById('modalDetailSesi').addEventListener('hidden.bs.modal', () => {
                clearInterval(detailRefreshTimer);
            }, {
                once: true
            });
        }

        // ── Refresh Detail ──
        async function refreshDetail() {
            try {
                const res = await axios.get(`/api/dosen/sesi/${activeSesiId}/detail`);
                const data = res.data;

                document.getElementById('detailMatkul').innerText = data.sesi.jadwal.mata_kuliah.nama;
                document.getElementById('detailKelas').innerText =
                    data.sesi.jadwal.kelas.nama + ' • ' + data.sesi.tanggal;

                const hadir = data.daftar_hadir.filter(m => m.status === 'hadir').length;
                const belum = data.daftar_hadir.filter(m => m.status === 'belum').length;
                const total = data.daftar_hadir.length;

                document.getElementById('statistikAbsen').innerHTML = `
                <div class="col-4">
                    <div style="background:#f0fdf4; border-radius:12px; padding:12px; text-align:center">
                        <div style="font-size:1.8rem; font-weight:700; color:#16a34a">${hadir}</div>
                        <div style="font-size:0.75rem; color:#86efac">Hadir</div>
                    </div>
                </div>
                <div class="col-4">
                    <div style="background:#fff7ed; border-radius:12px; padding:12px; text-align:center">
                        <div style="font-size:1.8rem; font-weight:700; color:#d97706">${belum}</div>
                        <div style="font-size:0.75rem; color:#fcd34d">Belum Absen</div>
                    </div>
                </div>
                <div class="col-4">
                    <div style="background:#f0f4ff; border-radius:12px; padding:12px; text-align:center">
                        <div style="font-size:1.8rem; font-weight:700; color:#1d4ed8">${total}</div>
                        <div style="font-size:0.75rem; color:#93c5fd">Total</div>
                    </div>
                </div>`;

                document.getElementById('tableDaftarHadir').innerHTML =
                    data.daftar_hadir.map(m => `
                    <tr>
                        <td style="font-size:0.82rem; color:#64748b">${m.nim}</td>
                        <td style="font-weight:500">${m.nama}</td>
                        <td>
                            ${m.status === 'hadir'
                                ? `<span style="background:#f0fdf4; color:#16a34a; font-size:0.72rem; font-weight:600; padding:3px 10px; border-radius:20px">Hadir</span>`
                                : `<span style="background:#f1f5f9; color:#94a3b8; font-size:0.72rem; font-weight:600; padding:3px 10px; border-radius:20px">Belum</span>`
                            }
                        </td>
                        <td style="font-size:0.82rem; color:#64748b">
                            ${m.waktu ? new Date(m.waktu).toLocaleTimeString('id-ID') : '-'}
                        </td>
                    </tr>`).join('');

                return data.sesi;
            } catch (e) {
                return null;
            }
        }

        async function startCountdownFromDetail() {
            const sesi = await refreshDetail();
            if (!sesi) return;
            startCountdown(sesi.dibuka_at, sesi.durasi_menit);
        }

        // ── Countdown ──
        function startCountdown(dibukaAt, durasiMenit) {
            if (countdownTimer) clearInterval(countdownTimer);
            const batas = new Date(dibukaAt);
            batas.setMinutes(batas.getMinutes() + parseInt(durasiMenit));

            countdownTimer = setInterval(() => {
                const sisa = batas - new Date();
                if (sisa <= 0) {
                    clearInterval(countdownTimer);
                    document.getElementById('countdown').innerText = 'Habis';
                    document.getElementById('btnTutupSesi').disabled = true;
                    loadJadwal();
                    return;
                }
                const m = Math.floor(sisa / 60000);
                const s = Math.floor((sisa % 60000) / 1000);
                document.getElementById('countdown').innerText =
                    String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
            }, 1000);
        }

        // ── Tutup Sesi ──
        async function tutupSesi() {
            if (!confirm('Yakin tutup sesi absensi sekarang?')) return;
            try {
                await axios.post(`/api/dosen/sesi/${activeSesiId}/tutup`);
                if (countdownTimer) clearInterval(countdownTimer);
                if (detailRefreshTimer) clearInterval(detailRefreshTimer);
                modalDetail.hide();
                loadJadwal();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal menutup sesi');
            }
        }

        // ── Profil ──
        function showModalProfil() {
            document.getElementById('profilNip').value = dosen.nip ?? '';
            document.getElementById('profilNama').value = dosen.nama ?? '';
            switchTab('info');
            document.getElementById('passwordLama').value = '';
            document.getElementById('passwordBaru').value = '';
            document.getElementById('passwordKonfirmasi').value = '';
            modalProfil.show();
        }

        function switchTab(tab) {
            document.getElementById('tabInfo').classList.toggle('d-none', tab !== 'info');
            document.getElementById('tabPassword').classList.toggle('d-none', tab !== 'password');
            document.querySelectorAll('#tabProfil .nav-link').forEach((btn, i) => {
                btn.classList.toggle('active', (i === 0 && tab === 'info') || (i === 1 && tab === 'password'));
            });
        }

        async function simpanProfil() {
            const nip = document.getElementById('profilNip').value;
            const nama = document.getElementById('profilNama').value;
            if (!nip || !nama) {
                alert('NIP dan nama harus diisi!');
                return;
            }
            try {
                const res = await axios.put('/api/dosen/profil', {
                    nip,
                    nama
                });
                const dosenBaru = res.data.data;
                localStorage.setItem('dosen_data', JSON.stringify(dosenBaru));
                document.getElementById('topbarNama').innerText = dosenBaru.nama;
                document.getElementById('topbarNip').innerText = dosenBaru.nip;
                document.getElementById('topbarAvatar').innerText = dosenBaru.nama[0].toUpperCase();
                alert('Profil berhasil diupdate!');
                modalProfil.hide();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal menyimpan profil');
            }
        }

        async function simpanPassword() {
            const lama = document.getElementById('passwordLama').value;
            const baru = document.getElementById('passwordBaru').value;
            const konfirm = document.getElementById('passwordKonfirmasi').value;
            if (!lama || !baru || !konfirm) {
                alert('Semua field harus diisi!');
                return;
            }
            if (baru !== konfirm) {
                alert('Konfirmasi password tidak cocok!');
                return;
            }
            if (baru.length < 6) {
                alert('Password minimal 6 karakter!');
                return;
            }
            try {
                await axios.put('/api/dosen/ganti-password', {
                    password_lama: lama,
                    password_baru: baru
                });
                alert('Password berhasil diubah! Silakan login ulang.');
                await logout();
            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal mengganti password');
            }
        }

        // ── Rekap ──
        let allJadwalRekap = [];

        async function showModalRekap() {
            try {
                const res = await axios.get('/api/dosen/rekap');
                allJadwalRekap = res.data;
                const select = document.getElementById('rekapJadwalId');
                const hariShort = {
                    senin: 'Sen',
                    selasa: 'Sel',
                    rabu: 'Rab',
                    kamis: 'Kam',
                    jumat: 'Jum'
                };
                select.innerHTML = '<option value="">-- Pilih Mata Kuliah --</option>';
                allJadwalRekap.forEach(j => {
                    select.innerHTML +=
                        `<option value="${j.id}">${j.mata_kuliah.nama} — ${j.kelas.nama} (${hariShort[j.hari]} ${j.jam_mulai.substring(0,5)})</option>`;
                });
                document.getElementById('rekapInfo').classList.add('d-none');
                document.getElementById('rekapEmpty').classList.remove('d-none');
                document.getElementById('rekapDari').value = '';
                document.getElementById('rekapSampai').value = '';
                modalRekap.show();
            } catch (e) {
                alert('Gagal memuat data rekap');
            }
        }

        async function loadRekapDetail() {
            const jadwalId = document.getElementById('rekapJadwalId').value;
            const dari = document.getElementById('rekapDari').value;
            const sampai = document.getElementById('rekapSampai').value;
            if (!jadwalId) {
                alert('Pilih mata kuliah terlebih dahulu!');
                return;
            }
            try {
                let url = `/api/dosen/rekap/${jadwalId}`;
                const p = new URLSearchParams();
                if (dari) p.append('tanggal_dari', dari);
                if (sampai) p.append('tanggal_sampai', sampai);
                if (p.toString()) url += '?' + p.toString();
                const res = await axios.get(url);
                const data = res.data;
                document.getElementById('rekapInfoMatkul').innerText = data.jadwal.mata_kuliah.nama;
                document.getElementById('rekapInfoKelas').innerText = data.jadwal.kelas.nama;
                document.getElementById('rekapInfoPertemuan').innerText = data.sesis.length + ' pertemuan';
                document.getElementById('rekapInfoMahasiswa').innerText = data.rekap.length + ' mahasiswa';
                const tbody = document.getElementById('rekapBody');
                tbody.innerHTML = data.rekap.length === 0 ?
                    '<tr><td colspan="9" class="text-center text-muted">Belum ada data</td></tr>' :
                    data.rekap.map((m, i) => {
                        const p = m.persentase;
                        const w = p >= 75 ? 'success' : p >= 50 ? 'warning' : 'danger';
                        return `<tr>
                        <td>${i+1}</td>
                        <td><span class="badge bg-dark">${m.nim}</span></td>
                        <td>${m.nama}</td>
                        <td class="text-center"><span class="badge bg-success">${m.hadir}</span></td>
                        <td class="text-center"><span class="badge bg-danger">${m.alpha}</span></td>
                        <td class="text-center"><span class="badge bg-warning text-dark">${m.izin}</span></td>
                        <td class="text-center"><span class="badge bg-info text-dark">${m.sakit}</span></td>
                        <td class="text-center" style="min-width:120px">
                            <div class="progress" style="height:18px">
                                <div class="progress-bar bg-${w}" style="width:${p}%">${p}%</div>
                            </div>
                        </td>
                        <td><span class="badge bg-${p >= 75 ? 'success' : 'danger'}">${p >= 75 ? 'Lulus' : 'Tidak Lulus'}</span></td>
                    </tr>`;
                    }).join('');
                document.getElementById('rekapInfo').classList.remove('d-none');
                document.getElementById('rekapEmpty').classList.add('d-none');
            } catch (e) {
                alert(e.response?.data?.message ?? 'Gagal memuat rekap');
            }
        }

        async function loadRiwayat() {
            const jadwalId = document.getElementById('rekapJadwalId').value;
            if (!jadwalId) {
                alert('Pilih mata kuliah terlebih dahulu!');
                return;
            }
            document.getElementById('riwayatLoading').classList.remove('d-none');
            document.getElementById('riwayatContent').classList.add('d-none');
            modalRiwayat.show();
            try {
                const res = await axios.get(`/api/dosen/rekap/${jadwalId}/riwayat`);
                const data = res.data;
                document.getElementById('riwayatMatkul').innerText = data.jadwal.mata_kuliah.nama;
                document.getElementById('riwayatKelas').innerText = '— ' + data.jadwal.kelas.nama;
                const tbody = document.getElementById('riwayatBody');
                const empty = document.getElementById('riwayatEmpty');
                if (data.sesis.length === 0) {
                    tbody.innerHTML = '';
                    empty.classList.remove('d-none');
                } else {
                    empty.classList.add('d-none');
                    tbody.innerHTML = data.sesis.map((s, i) => {
                        const w = s.persentase >= 75 ? 'success' : s.persentase >= 50 ? 'warning' : 'danger';
                        const dibuka = s.dibuka_at ? new Date(s.dibuka_at).toLocaleTimeString('id-ID', {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) : '-';
                        const ditutup = s.ditutup_at ? new Date(s.ditutup_at).toLocaleTimeString('id-ID', {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) : '-';
                        return `<tr>
                        <td>${i+1}</td>
                        <td><div class="fw-semibold">${s.tanggal}</div><small class="text-muted">${s.durasi_menit} menit</small></td>
                        <td><small>${dibuka}</small></td>
                        <td><small>${ditutup}</small></td>
                        <td class="text-center"><span class="badge bg-success">${s.jumlah_hadir}</span> <small class="text-muted">/ ${s.total_mahasiswa}</small></td>
                        <td class="text-center"><span class="badge bg-danger">${s.jumlah_alpha}</span></td>
                        <td class="text-center" style="min-width:100px">
                            <div class="progress" style="height:16px">
                                <div class="progress-bar bg-${w}" style="width:${s.persentase}%">${s.persentase}%</div>
                            </div>
                        </td>
                        <td>${s.is_active ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-secondary">Selesai</span>'}</td>
                    </tr>`;
                    }).join('');
                }
                document.getElementById('riwayatLoading').classList.add('d-none');
                document.getElementById('riwayatContent').classList.remove('d-none');
            } catch (e) {
                alert('Gagal memuat riwayat sesi');
                modalRiwayat.hide();
            }
        }

        // ── Logout ──
        async function logout() {
            try {
                await axios.post('/api/dosen/logout');
            } catch (e) {}
            localStorage.clear();
            window.location.href = '/dosen/login';
        }

        loadJadwal();
        setInterval(loadJadwal, 30000);
    </script>

    @stack('scripts')
</body>

</html>
