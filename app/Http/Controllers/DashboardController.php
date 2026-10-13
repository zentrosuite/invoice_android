<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Settings\BackupSettings;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

final class DashboardController extends Controller
{
    public function __invoke(CompanySettings $company, InvoiceSettings $settings, BackupSettings $backup): View
    {
        $owed = [InvoiceStatus::Sent->value, InvoiceStatus::Partial->value];
        $today = now()->toDateString();

        $outstanding = Invoice::query()
            ->whereIn('status', $owed)
            ->selectRaw('count(*) as n, coalesce(sum(total - amount_paid), 0) as amount')
            ->first();

        $overdue = Invoice::query()
            ->whereIn('status', $owed)
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->selectRaw('count(*) as n, coalesce(sum(total - amount_paid), 0) as amount')
            ->first();

        $received = InvoicePayment::query()
            ->whereBetween('paid_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('amount');

        $receivedLast = InvoicePayment::query()
            ->whereBetween('paid_on', [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()])
            ->sum('amount');

        return view('dashboard', [
            'company' => $company,
            'profile' => $settings->profile(),
            'symbol' => $settings->currencySymbol,
            'outstanding' => $outstanding,
            'overdue' => $overdue,
            'received' => (string) $received,
            'receivedLast' => (string) $receivedLast,
            'drafts' => Invoice::query()->where('status', InvoiceStatus::Draft)->count(),
            // Overdue first (oldest due date), then the rest of what is owed, newest first.
            'unpaid' => Invoice::query()
                ->whereIn('status', $owed)
                ->orderByRaw('case when due_date is not null and due_date < ? then 0 else 1 end', [$today])
                ->orderBy('due_date')
                ->orderByDesc('invoice_date')
                ->limit(6)
                ->get(),
            'recent' => Invoice::query()->latest('updated_at')->limit(6)->get(),
            'hasInvoices' => Invoice::query()->exists(),
            'hasCustomers' => DB::table('customers')->whereNull('deleted_at')->exists(),
            'backupOverdue' => $backup->isOverdue(Invoice::query()->exists()),
            'lastBackup' => $backup->lastSaved(),
        ]);
    }
}
