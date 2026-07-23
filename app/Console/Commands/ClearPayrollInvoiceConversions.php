<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PayrollPeriodInvoice;
use Illuminate\Console\Command;

class ClearPayrollInvoiceConversions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:clear-invoice-conversions {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all payroll period invoice records and their associated invoices';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $periodInvoices = PayrollPeriodInvoice::all();
        $totalPeriods = $periodInvoices->count();

        if ($totalPeriods === 0) {
            $this->info('No payroll invoice conversion records found.');

            return 0;
        }

        $allInvoiceIds = [];
        foreach ($periodInvoices as $p) {
            $ids = $p->invoice_ids ?? [];
            if (is_string($ids)) {
                $ids = json_decode($ids, true) ?? [];
            }
            $allInvoiceIds = array_merge($allInvoiceIds, $ids);
        }
        $allInvoiceIds = array_unique(array_filter($allInvoiceIds));
        $invoiceCount = count($allInvoiceIds);

        if (! $this->option('force')) {
            if (! $this->confirm("This will permanently delete {$totalPeriods} period record(s) and {$invoiceCount} invoice(s) with their line items. Continue?")) {
                $this->info('Aborted.');

                return 1;
            }
        }

        try {
            \DB::transaction(function () use ($allInvoiceIds, $invoiceCount, $totalPeriods) {
                if (! empty($allInvoiceIds)) {
                    InvoiceItem::whereIn('invoice_id', $allInvoiceIds)->delete();
                    Invoice::whereIn('id', $allInvoiceIds)->delete();
                    $this->info("Deleted {$invoiceCount} invoice(s) and their line items.");
                }

                PayrollPeriodInvoice::query()->delete();
                $this->info("Deleted {$totalPeriods} payroll period invoice record(s).");
            });
            $this->info('Done. All payroll invoice conversion data has been removed.');
        } catch (\Throwable $e) {
            $this->error('Error: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
