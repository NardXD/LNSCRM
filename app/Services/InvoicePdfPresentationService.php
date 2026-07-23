<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PayrollPeriodInvoice;
use App\Models\User;

class InvoicePdfPresentationService
{
    private const PAYROLL_LINE_PATTERN = '/^Payroll - (.+?) \(\d{4}-\d{2}-\d{2}/';

    public function __construct(
        private PayrollConversionDetailsService $conversionDetailsService,
    ) {}

    /**
     * @return array{is_payroll_invoice: bool, lines: list<array{item: InvoiceItem, net_pay: ?float, commission: ?float}>}
     */
    public function prepare(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'client']);

        $period = $this->resolvePayrollPeriod($invoice);
        $hasPayrollLines = $invoice->items->contains(
            fn (InvoiceItem $item) => $this->isPayrollLine($item)
        );
        $isPayrollInvoice = $hasPayrollLines || $period !== null;

        $conversionDetails = (array) ($period?->conversion_details ?? []);
        $employeeMapping = (array) ($period?->employee_invoice_mapping ?? []);

        $lines = [];
        foreach ($invoice->items->sortBy('sort_order') as $item) {
            $netPay = null;
            $commission = null;

            if ($this->isPayrollLine($item)) {
                $employeeId = $this->resolveEmployeeId($invoice, $item, $employeeMapping);
                $clientId = (int) ($invoice->client_id ?? 0);

                if ($employeeId && $clientId > 0) {
                    $key = $this->conversionDetailsService->detailKey($employeeId, $clientId);
                    $detail = $conversionDetails[$key] ?? null;

                    if (is_array($detail)) {
                        $netPay = array_key_exists('net_pay', $detail) && $detail['net_pay'] !== null && $detail['net_pay'] !== ''
                            ? (float) $detail['net_pay']
                            : null;
                        $commission = array_key_exists('commission', $detail) && $detail['commission'] !== null && $detail['commission'] !== ''
                            ? (float) $detail['commission']
                            : null;
                    }
                }
            } elseif ($item->net_pay !== null) {
                $netPay = (float) $item->net_pay;
            }

            $lines[] = [
                'item' => $item,
                'net_pay' => $netPay,
                'commission' => $commission,
            ];
        }

        return [
            'is_payroll_invoice' => $isPayrollInvoice && $hasPayrollLines,
            'lines' => $lines,
        ];
    }

    private function resolvePayrollPeriod(Invoice $invoice): ?PayrollPeriodInvoice
    {
        return PayrollPeriodInvoice::query()
            ->where('company_id', $invoice->company_id)
            ->whereJsonContains('invoice_ids', $invoice->id)
            ->first();
    }

    private function isPayrollLine(InvoiceItem $item): bool
    {
        return (bool) preg_match(self::PAYROLL_LINE_PATTERN, $item->description ?? '');
    }

    /**
     * @param  array<string|int, mixed>  $employeeMapping
     */
    private function resolveEmployeeId(Invoice $invoice, InvoiceItem $item, array $employeeMapping): ?int
    {
        $employeeName = null;
        if ($item->description && preg_match(self::PAYROLL_LINE_PATTERN, $item->description, $matches)) {
            $employeeName = trim($matches[1]);
        }

        foreach ($employeeMapping as $empId => $invoiceIds) {
            $empId = (int) $empId;
            $linkedIds = array_map('intval', (array) $invoiceIds);

            if (! in_array((int) $invoice->id, $linkedIds, true)) {
                continue;
            }

            if ($employeeName === null) {
                return $empId;
            }

            $user = User::query()
                ->where('company_id', $invoice->company_id)
                ->where('id', $empId)
                ->first(['id', 'name']);

            if ($user && $user->name === $employeeName) {
                return $empId;
            }
        }

        if ($employeeName === null) {
            return null;
        }

        $user = User::query()
            ->where('company_id', $invoice->company_id)
            ->where('name', $employeeName)
            ->first(['id']);

        return $user?->id;
    }
}
