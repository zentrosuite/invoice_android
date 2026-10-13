<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Illuminate\Contracts\View\View;

final class DashboardController extends Controller
{
    public function __invoke(CompanySettings $company, InvoiceSettings $invoice): View
    {
        return view('dashboard', [
            'company' => $company,
            'profile' => $invoice->profile(),
        ]);
    }
}
