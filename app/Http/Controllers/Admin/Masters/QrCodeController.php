<?php

namespace App\Http\Controllers\Admin\Masters;

use App\Http\Controllers\BaseController;
use Illuminate\View\View;

/**
 * QR Code Generator — a standalone utility (no table). Renders an interactive
 * page where any free text is turned into a QR code client-side, with a
 * print action. Mirrors CI3 masters/qrcode.
 */
class QrCodeController extends BaseController
{
    public function index(): View
    {
        return view('admin.masters.qrcode.index');
    }
}
