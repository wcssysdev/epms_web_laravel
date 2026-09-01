<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use Illuminate\View\View;

class UserController extends BaseController
{
    public function index(): View
    {
        // TODO: Sprint 1 — full user management
        return view('admin.users.index');
    }
}
