<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use Illuminate\View\View;

class ConfigController extends BaseController
{
    public function index(): View
    {
        // TODO: Sprint 2 — full estate settings
        $config = $this->companyConfig;
        return view('admin.config.index', compact('config'));
    }
}
