@extends('layouts.app')

@section('title', 'Account Settings')

@section('breadcrumb')
    <li><span class="font-medium text-primary">Account Settings</span></li>
@endsection

@section('page-title', 'Account Settings')
@section('page-subtitle', 'Manage user accounts and access levels')

@section('page-actions')
    <a href="{{ route('admin.users.create') }}"
       class="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white transition hover:opacity-90">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Add User
    </a>
@endsection

@section('content')

{{-- Table Card --}}
<div class="rounded-xl border shadow-sm overflow-hidden"
     style="background: var(--epms-header-bg); border-color: var(--epms-border);">

    {{-- Card Header --}}
    <div class="flex items-center justify-between px-5 py-4 border-b"
         style="border-color: var(--epms-border);">
        <h2 class="font-semibold text-sm" style="color: var(--epms-text);">
            Registered Users
        </h2>
        <span class="text-xs" style="color: var(--epms-text-muted);">
            Company: {{ session('company_name', 'All') }}
        </span>
    </div>

    {{-- Table --}}
    <div class="p-5">
        <div class="overflow-x-auto">
            <table id="usersTable" class="w-full text-sm" style="color: var(--epms-text);">
                <thead>
                    <tr class="border-b" style="border-color: var(--epms-border);">
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">#</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Name</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Username</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Role</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Company</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Employee Code</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Status</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Last Login</th>
                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide" style="color: var(--epms-text-muted);">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

{{-- Reset Password Modal --}}
<div id="resetPasswordModal"
     class="fixed inset-0 z-50 hidden items-center justify-center"
     x-data="{ show: false, userId: null, userName: '' }"
     x-on:open-reset-password.window="show = true; userId = $event.detail.id; userName = $event.detail.name"
     x-show="show">
    <div class="absolute inset-0 bg-black/50" @click="show = false"></div>
    <div class="relative w-full max-w-md rounded-xl border p-6 shadow-xl z-10"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <h3 class="text-lg font-bold mb-1" style="color: var(--epms-text);">Reset Password</h3>
        <p class="text-sm mb-4" style="color: var(--epms-text-muted);">
            Set new password for <span class="font-semibold" style="color: var(--epms-text);" x-text="userName"></span>
        </p>
        <form id="resetPasswordForm">
            @csrf
            <div class="mb-3">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">New Password <span class="text-red-500">*</span></label>
                <input type="password" id="new_password" name="new_password" required
                       class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none focus:border-primary"
                       style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);"
                       placeholder="Min 8 chars, uppercase, number, special char">
            </div>
            <div class="mb-5">
                <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">Confirm Password <span class="text-red-500">*</span></label>
                <input type="password" id="new_password_confirmation" name="new_password_confirmation" required
                       class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none focus:border-primary"
                       style="background: var(--epms-header-bg); color: var(--epms-text); border-color: var(--epms-border);"
                       placeholder="Repeat new password">
            </div>
            <p id="resetPasswordError" class="text-sm text-red-500 mb-3 hidden"></p>
            <div class="flex gap-3 justify-end">
                <button type="button" @click="show = false"
                        class="px-4 py-2 rounded-lg text-sm font-medium border transition"
                        style="border-color: var(--epms-border); color: var(--epms-text);">
                    Cancel
                </button>
                <button type="button" id="resetPasswordSubmit"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-primary text-white transition hover:opacity-90">
                    <span id="resetPasswordBtnText">Reset Password</span>
                    <span id="resetPasswordSpinner" class="loading loading-spinner loading-xs hidden"></span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Confirm Modal (toggle active / reset session) --}}
<div id="confirmModal"
     class="fixed inset-0 z-50 hidden items-center justify-center"
     x-data="{ show: false, message: '', onConfirm: null }"
     x-on:open-confirm.window="show = true; message = $event.detail.message; onConfirm = $event.detail.callback"
     x-show="show">
    <div class="absolute inset-0 bg-black/50" @click="show = false"></div>
    <div class="relative w-full max-w-sm rounded-xl border p-6 shadow-xl z-10"
         style="background: var(--epms-header-bg); border-color: var(--epms-border);">
        <h3 class="text-lg font-bold mb-2" style="color: var(--epms-text);">Confirm Action</h3>
        <p class="text-sm mb-5" style="color: var(--epms-text-muted);" x-text="message"></p>
        <div class="flex gap-3 justify-end">
            <button @click="show = false"
                    class="px-4 py-2 rounded-lg text-sm font-medium border transition"
                    style="border-color: var(--epms-border); color: var(--epms-text);">
                Cancel
            </button>
            <button @click="onConfirm && onConfirm(); show = false"
                    class="px-4 py-2 rounded-lg text-sm font-medium bg-primary text-white transition hover:opacity-90">
                Confirm
            </button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
// ── DataTables init ────────────────────────────────────────────────────────
$(document).ready(function () {
    const table = $('#usersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("admin.users.datatable") }}',
            type: 'GET',
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        },
        columns: [
            { data: 'DT_RowIndex',      name: 'DT_RowIndex',         orderable: false, searchable: false, width: '40px' },
            { data: 'user_name',        name: 'user_name' },
            { data: 'username',         name: 'username' },
            { data: 'role_name',        name: 'role_name',           orderable: false },
            { data: 'company_name',     name: 'company_name',        orderable: false },
            { data: 'user_employee_code', name: 'user_employee_code', defaultContent: '-' },
            { data: 'status_badge',     name: 'is_active',           orderable: false },
            { data: 'last_login',       name: 'last_login_at',       orderable: false },
            { data: 'actions',          name: 'actions',             orderable: false, searchable: false },
        ],
        order: [[1, 'asc']],
        pageLength: 25,
        language: { processing: '<div class="flex justify-center py-4"><span class="loading loading-spinner loading-md text-primary"></span></div>' },
        dom: '<"flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4"lf>rtip',
        drawCallback: function() {
            // Style DataTables elements
            document.querySelectorAll('#usersTable tbody tr').forEach(tr => {
                tr.style.borderBottom = '1px solid var(--epms-border)';
            });
        }
    });

    // Reload on flash
    @if(session('success') || session('error'))
        table.ajax.reload();
    @endif
});

// ── Reset Password ─────────────────────────────────────────────────────────
let _resetUserId = null;

function resetPassword(id, name) {
    _resetUserId = id;
    document.getElementById('new_password').value = '';
    document.getElementById('new_password_confirmation').value = '';
    document.getElementById('resetPasswordError').classList.add('hidden');
    window.dispatchEvent(new CustomEvent('open-reset-password', {
        detail: { id, name }
    }));
}

document.getElementById('resetPasswordSubmit').addEventListener('click', async function () {
    const pw  = document.getElementById('new_password').value;
    const cpw = document.getElementById('new_password_confirmation').value;
    const err = document.getElementById('resetPasswordError');
    const btn = document.getElementById('resetPasswordBtnText');
    const spn = document.getElementById('resetPasswordSpinner');

    err.classList.add('hidden');
    if (!pw || pw !== cpw) {
        err.textContent = pw !== cpw ? 'Passwords do not match.' : 'Password is required.';
        err.classList.remove('hidden');
        return;
    }

    btn.textContent = 'Processing...'; spn.classList.remove('hidden');

    try {
        const res = await fetch(`/admin/users/${_resetUserId}/reset-password`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ new_password: pw, new_password_confirmation: cpw })
        });
        const json = await res.json();
        if (json.success) {
            window.dispatchEvent(new CustomEvent('open-reset-password', { detail: { id: null, name: '' } }));
            // close modal via Alpine
            document.getElementById('resetPasswordModal')._x_dataStack[0].show = false;
            showToast(json.message, 'success');
        } else {
            err.textContent = json.message || 'Failed to reset password.';
            err.classList.remove('hidden');
        }
    } catch (e) {
        err.textContent = 'An error occurred. Please try again.';
        err.classList.remove('hidden');
    } finally {
        btn.textContent = 'Reset Password'; spn.classList.add('hidden');
    }
});

// ── Toggle Active ──────────────────────────────────────────────────────────
function toggleActive(id, name, isActive) {
    const msg = isActive
        ? `Deactivate user "${name}"? They will no longer be able to login.`
        : `Activate user "${name}"?`;
    window.dispatchEvent(new CustomEvent('open-confirm', {
        detail: {
            message: msg,
            callback: async () => {
                try {
                    const res = await fetch(`/admin/users/${id}/toggle-active`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                    });
                    const json = await res.json();
                    if (json.success) {
                        showToast(json.message, 'success');
                        $('#usersTable').DataTable().ajax.reload(null, false);
                    } else {
                        showToast(json.message || 'Failed.', 'error');
                    }
                } catch { showToast('An error occurred.', 'error'); }
            }
        }
    }));
}

// ── Reset Session ──────────────────────────────────────────────────────────
function resetSession(id, name) {
    window.dispatchEvent(new CustomEvent('open-confirm', {
        detail: {
            message: `Reset login session for "${name}"? Their mobile app will need to re-login.`,
            callback: async () => {
                try {
                    const res = await fetch(`/admin/users/${id}/reset-session`, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                    });
                    const json = await res.json();
                    showToast(json.success ? json.message : (json.message || 'Failed.'), json.success ? 'success' : 'error');
                } catch { showToast('An error occurred.', 'error'); }
            }
        }
    }));
}

// ── Toast helper ───────────────────────────────────────────────────────────
function showToast(message, type = 'success') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706' };
    const toast = document.createElement('div');
    toast.className = 'fixed top-5 right-5 z-[9999] rounded-lg px-4 py-3 text-white text-sm shadow-lg flex items-center gap-2 transition-all';
    toast.style.backgroundColor = colors[type] || colors.success;
    toast.innerHTML = `<span>${message}</span>`;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3500);
}
</script>

{{-- DataTables CSS --}}
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">

{{-- DataTables JS --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

{{-- Action button styles --}}
<style>
    .btn-action {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px; height: 30px;
        border-radius: 6px;
        border: 1px solid;
        transition: opacity 0.15s;
        cursor: pointer;
    }
    .btn-action:hover { opacity: 0.75; }
    .btn-edit    { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
    .btn-warning { background: #fffbeb; border-color: #fde68a; color: #d97706; }
    .btn-danger  { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
    .btn-success { background: #f0fdf4; border-color: #bbf7d0; color: #16a34a; }
    .btn-info    { background: #f0f9ff; border-color: #bae6fd; color: #0284c7; }
    /* DataTables styling */
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        background: var(--epms-header-bg);
        color: var(--epms-text);
        border: 1px solid var(--epms-border);
        border-radius: 8px;
        padding: 4px 10px;
        font-size: 13px;
    }
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        color: var(--epms-text-muted);
        font-size: 13px;
        margin-top: 12px;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 6px !important;
        padding: 4px 10px !important;
        color: var(--epms-text) !important;
    }
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #5750f1 !important;
        border-color: #5750f1 !important;
        color: white !important;
    }
    table.dataTable thead th {
        border-bottom: 1px solid var(--epms-border) !important;
    }
    table.dataTable tbody td {
        padding: 10px 12px !important;
        vertical-align: middle;
    }
    table.dataTable.no-footer { border-bottom: none; }
</style>
@endpush
