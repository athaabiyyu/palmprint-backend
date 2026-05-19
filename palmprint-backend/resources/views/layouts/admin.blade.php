<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Sistem Absensi Palmprint</title>
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

        /* ── Sidebar ── */
        .sidebar {
            min-height: 100vh;
            width: 260px;
            background: #1e3a8a;
            box-shadow: 4px 0 20px rgba(30, 58, 138, 0.15);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar-brand {
            padding: 24px 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 8px;
        }

        .sidebar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .sidebar-brand .brand-text {
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            line-height: 1.2;
        }

        .sidebar-brand .brand-sub {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.7rem;
            font-weight: 400;
        }

        /* Nav items */
        .sidebar nav {
            padding: 8px 12px;
        }

        .nav-label {
            color: rgba(255, 255, 255, 0.35);
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            padding: 12px 8px 4px;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.65);
            border-radius: 10px;
            padding: 9px 12px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 2px;
        }

        .sidebar .nav-link:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar .nav-link.active {
            color: #1e3a8a;
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            font-weight: 600;
        }

        .sidebar .nav-link.active i {
            color: #1d4ed8;
        }

        .sidebar .nav-link i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        /* Collapse arrow */
        .sidebar .nav-link[data-bs-toggle="collapse"] {
            justify-content: space-between;
        }

        .sidebar .nav-link[data-bs-toggle="collapse"] .nav-link-inner {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar .nav-link[data-bs-toggle="collapse"]::after {
            content: "\F282";
            font-family: "bootstrap-icons";
            font-size: 0.75rem;
            transition: transform 0.2s;
            color: rgba(255, 255, 255, 0.4);
        }

        .sidebar .nav-link[data-bs-toggle="collapse"][aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        /* Submenu */
        .nav-submenu {
            margin-left: 8px;
            padding-left: 12px;
            border-left: 2px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 4px;
        }

        .nav-submenu .nav-link {
            font-size: 0.82rem;
            padding: 7px 10px;
            color: rgba(255, 255, 255, 0.55);
        }

        .nav-submenu .nav-link.active {
            color: #1e3a8a;
            background: #fff;
        }

        /* Badge notif di sidebar */
        .nav-badge {
            background: #ef4444;
            color: #fff;
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 20px;
            font-weight: 600;
            margin-left: auto;
        }

        /* ── Main Content ── */
        .main-wrapper {
            margin-left: 260px;
            min-height: 100vh;
        }

        /* Top bar */
        .topbar {
            background: #fff;
            border-bottom: 1px solid #e8edf5;
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 99;
            box-shadow: 0 1px 8px rgba(0, 0, 0, 0.05);
        }

        .topbar-title {
            font-weight: 600;
            font-size: 1rem;
            color: #1e293b;
        }

        .topbar-sub {
            font-size: 0.78rem;
            color: #94a3b8;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .topbar-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #1d4ed8, #60a5fa);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
        }

        /* Main content area */
        .main-content {
            padding: 28px;
        }

        /* ── Cards ── */
        .card {
            border-radius: 16px !important;
            border: none !important;
        }

        .card-header {
            border-radius: 16px 16px 0 0 !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }

        /* Scrollbar sidebar */
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Brand -->
        <div class="sidebar-brand">
            <div class="d-flex align-items-center gap-3">
                <div class="brand-icon">
                    <i class="bi bi-hand-index-thumb text-white"></i>
                </div>
                <div>
                    <div class="brand-text">Palmprint</div>
                    <div class="brand-sub">Admin Dashboard</div>
                </div>
            </div>
        </div>

        <!-- Nav -->
        <nav class="nav flex-column">

            <div class="nav-label">Menu Utama</div>

            <a href="/admin/dashboard" class="nav-link {{ request()->is('admin/dashboard') ? 'active' : '' }}">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>

            <a href="/admin/jurusan" class="nav-link {{ request()->is('admin/jurusan') ? 'active' : '' }}">
                <i class="bi bi-diagram-3"></i> Jurusan & Prodi
            </a>

            <div class="nav-label">Akademik</div>

            <a class="nav-link {{ request()->is('admin/semester') || request()->is('admin/kelas') ? 'active' : '' }}"
                data-bs-toggle="collapse" href="#menuAkademik"
                aria-expanded="{{ request()->is('admin/semester') || request()->is('admin/kelas') ? 'true' : 'false' }}">
                <span class="nav-link-inner">
                    <i class="bi bi-mortarboard"></i> Akademik
                </span>
            </a>
            <div class="collapse nav-submenu {{ request()->is('admin/semester') || request()->is('admin/kelas') ? 'show' : '' }}"
                id="menuAkademik">
                <a href="/admin/semester" class="nav-link {{ request()->is('admin/semester') ? 'active' : '' }}">
                    <i class="bi bi-calendar3"></i> Semester
                </a>
                <a href="/admin/kelas" class="nav-link {{ request()->is('admin/kelas') ? 'active' : '' }}">
                    <i class="bi bi-people"></i> Kelas
                </a>
            </div>

            <a class="nav-link {{ request()->is('admin/matkul') || request()->is('admin/jadwal') ? 'active' : '' }}"
                data-bs-toggle="collapse" href="#menuPerkuliahan"
                aria-expanded="{{ request()->is('admin/matkul') || request()->is('admin/jadwal') ? 'true' : 'false' }}">
                <span class="nav-link-inner">
                    <i class="bi bi-journal-bookmark"></i> Perkuliahan
                </span>
            </a>
            <div class="collapse nav-submenu {{ request()->is('admin/matkul') || request()->is('admin/jadwal') ? 'show' : '' }}"
                id="menuPerkuliahan">
                <a href="/admin/matkul" class="nav-link {{ request()->is('admin/matkul') ? 'active' : '' }}">
                    <i class="bi bi-book"></i> Mata Kuliah
                </a>
                <a href="/admin/jadwal" class="nav-link {{ request()->is('admin/jadwal') ? 'active' : '' }}">
                    <i class="bi bi-table"></i> Jadwal
                </a>
            </div>

            <div class="nav-label">SDM</div>

            <a href="/admin/dosen" class="nav-link {{ request()->is('admin/dosen') ? 'active' : '' }}">
                <i class="bi bi-person-badge"></i> Dosen
            </a>

            <a href="/admin/mahasiswa" class="nav-link {{ request()->is('admin/mahasiswa') ? 'active' : '' }}">
                <i class="bi bi-people"></i> Mahasiswa
            </a>

            <div class="nav-label">Absensi</div>

            <a href="/admin/surat" class="nav-link {{ request()->is('admin/surat') ? 'active' : '' }}">
                <i class="bi bi-envelope-paper"></i> Surat
                {{-- Badge notif surat pending --}}
                @php $pendingCount = \App\Models\Surat::where('status','pending')->count(); @endphp
                @if ($pendingCount > 0)
                    <span class="nav-badge">{{ $pendingCount }}</span>
                @endif
            </a>

            <a href="/admin/rekap" class="nav-link {{ request()->is('admin/rekap') ? 'active' : '' }}">
                <i class="bi bi-clipboard-data"></i> Rekap Absensi
            </a>

        </nav>
    </div>

    <!-- Main Wrapper -->
    <div class="main-wrapper">

        <!-- Topbar -->
        <div class="topbar">
            <div>
                <div class="topbar-title">
                    @yield('page-title', 'Dashboard')
                </div>
                <div class="topbar-sub">
                    {{ \Carbon\Carbon::now()->translatedFormat('l, d F Y') }}
                </div>
            </div>
            <div class="topbar-right">
                {{-- Semester Aktif --}}
                @php $semAktif = \App\Models\Semester::where('is_active', true)->first(); @endphp
                @if ($semAktif)
                    <span class="badge rounded-pill px-3 py-2"
                        style="background:#eff6ff; color:#1d4ed8; font-size:0.78rem; font-weight:600">
                        <i class="bi bi-calendar-check me-1"></i>{{ $semAktif->nama }}
                    </span>
                @endif

                {{-- Avatar Admin --}}
                <div class="topbar-avatar">A</div>
            </div>
        </div>

        <!-- Content -->
        <div class="main-content">
            @yield('content')
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    @stack('scripts')
</body>

</html>
