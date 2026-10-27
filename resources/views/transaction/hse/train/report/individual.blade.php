@extends('layouts.app')

@section('title', 'Individual Employee Training Report')

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary font-weight-bold">
                        <i class="fas fa-user-graduate me-2"></i> Individual Employee Training Report
                    </h5>
                    <div>
                        <a href="{{ route('transaction.hse.report.print_individual', ['employee_code' => $employeeCode, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" 
                           target="_blank" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-print me-1"></i> Print (FM_CW_047)
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Filter Form -->
                    <form method="GET" action="{{ route('transaction.hse.report.individual') }}" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label font-weight-bold">Employee Code / Name</label>
                            <input type="text" name="employee_code" class="form-control" placeholder="Search by Employee Code or Name" value="{{ $employeeCode }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold">From Date</label>
                            <input type="date" name="from_date" class="form-control" value="{{ $fromDate }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label font-weight-bold">To Date</label>
                            <input type="date" name="to_date" class="form-control" value="{{ $toDate }}">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-1"></i> Filter
                            </button>
                        </div>
                    </form>

                    <!-- Data Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-light text-center">
                                <tr>
                                    <th style="width: 5%;">No</th>
                                    <th style="width: 12%;">Date</th>
                                    <th style="width: 15%;">Emp Code</th>
                                    <th style="width: 20%;">Emp Name</th>
                                    <th style="width: 25%;">Course Name</th>
                                    <th style="width: 10%;">Attendance</th>
                                    <th style="width: 8%;">Result</th>
                                    <th style="width: 5%;">Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $index => $row)
                                    <tr>
                                        <td class="text-center">{{ $records->firstItem() + $index }}</td>
                                        <td class="text-center">{{ !empty($row->training_date) ? date('d-m-Y', strtotime($row->training_date)) : '-' }}</td>
                                        <td><code>{{ $row->employee_code }}</code></td>
                                        <td class="font-weight-bold">{{ $row->employee_name }}</td>
                                        <td>
                                            {{ $row->course_name ?? $row->course_no }}
                                            @if(!empty($row->course_category))
                                                <br><small class="text-muted">{{ $row->course_category }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(strtolower($row->attendance_status) === 'present')
                                                <span class="badge bg-success">Present</span>
                                            @elseif(strtolower($row->attendance_status) === 'late')
                                                <span class="badge bg-warning text-dark">Late</span>
                                            @else
                                                <span class="badge bg-danger">Absent</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if(strtoupper($row->training_result_type) === 'PASS')
                                                <span class="badge bg-primary">PASS</span>
                                            @else
                                                <span class="badge bg-danger">FAIL</span>
                                            @endif
                                        </td>
                                        <td class="text-end">{{ isset($row->training_result_score) ? number_format((float)$row->training_result_score, 2) : '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            No training records found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-end mt-3">
                        {{ $records->withQueryString()->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
