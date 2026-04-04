@extends('layouts.admin')

@section('content')
<h4 class="mb-4 fw-bold">Dashboard</h4>

<div class="row g-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-primary bg-opacity-10 p-3 rounded-3">
                    <i class="bi bi-people fs-3 text-primary"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Mahasiswa</div>
                    <div class="fw-bold fs-4">{{ $totalMahasiswa }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-success bg-opacity-10 p-3 rounded-3">
                    <i class="bi bi-person-badge fs-3 text-success"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Dosen</div>
                    <div class="fw-bold fs-4">{{ $totalDosen }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-warning bg-opacity-10 p-3 rounded-3">
                    <i class="bi bi-building fs-3 text-warning"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Kelas</div>
                    <div class="fw-bold fs-4">{{ $totalKelas }}</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="bg-danger bg-opacity-10 p-3 rounded-3">
                    <i class="bi bi-book fs-3 text-danger"></i>
                </div>
                <div>
                    <div class="text-muted small">Total Mata Kuliah</div>
                    <div class="fw-bold fs-4">{{ $totalMatkul }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection