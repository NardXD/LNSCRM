<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PayrollPeriodInvoice;
use App\Models\SalaryComputation;
use App\Models\User;

class InvoiceItemHoursSyncService
{
    public function __construct(
        private PayrollConversionDetailsService $conversionDetails
    ) {}

    /**
     * Propagate line-item hours and bill amounts to linked payroll period records.
     */
    public function syncFromInvoice(Invoice $invoice): void
    {
        $invoice->loadMissing('items');

        $periods = PayrollPeriodInvoice::query()
            ->where('company_id', $invoice->company_id)
            ->whereJsonContains('invoice_ids', $invoice->id)
            ->get();

        if ($periods->isEmpty()) {
            return;
        }

        foreach ($periods as $period) {
            $conversionDetails = (array) ($period->conversion_details ?? []);
            $mapping = (array) ($period->employee_invoice_mapping ?? []);
            $affectedEmployeeIds = [];

            foreach ($invoice->items as $item) {
                $employeeId = $this->resolveEmployeeIdForLineItem($invoice, $item, $mapping);
                if ($employeeId === null) {
                    continue;
                }

                $clientId = (int) ($invoice->client_id ?? 0);
                if ($clientId < 1) {
                    continue;
                }

                $affectedEmployeeIds[$employeeId] = true;

                $key = $this->conversionDetails->detailKey($employeeId, $clientId);
                $existing = is_array($conversionDetails[$key] ?? null) ? $conversionDetails[$key] : [];
                $hours = $item->hours_worked !== null ? round((float) $item->hours_worked, 2) : null;
                $conversionDetails[$key] = array_merge($existing, [
                    'employee_id' => $employeeId,
                    'client_id' => $clientId,
                    'hours_worked' => $hours,
                ]);

                SalaryComputation::query()
                    ->where('company_id', $invoice->company_id)
                    ->where('user_id', $employeeId)
                    ->whereDate('period_start_date', $period->period_start_date)
                    ->whereDate('period_end_date', $period->period_end_date)
                    ->update(['hours_worked' => $hours ?? 0]);
            }

            foreach (array_keys($affectedEmployeeIds) as $employeeId) {
                $this->refreshEmployeeClientBillAmounts($conversionDetails, (int) $employeeId, $period);
            }

            $period->update(['conversion_details' => $conversionDetails]);
            $this->pruneOrphanedEmployees($period->fresh());
        }
    }

    public function clearEmployeeSalaryHoursForPeriod(PayrollPeriodInvoice $period, int $employeeId): void
    {
        $this->resetSalaryComputationHours($period, $employeeId);
    }

    /**
     * Remove payroll period links when an invoice is deleted from billing.
     */
    public function detachInvoiceFromPayrollPeriod(Invoice $invoice): void
    {
        $invoiceId = (int) $invoice->id;
        $companyId = (int) $invoice->company_id;
        $invoiceClientId = (int) ($invoice->client_id ?? 0);

        $periods = PayrollPeriodInvoice::query()
            ->where('company_id', $companyId)
            ->whereJsonContains('invoice_ids', $invoiceId)
            ->get();

        foreach ($periods as $period) {
            $invoiceIds = array_values(array_filter(
                array_map('intval', (array) ($period->invoice_ids ?? [])),
                fn (int $id) => $id !== $invoiceId
            ));

            $mapping = (array) ($period->employee_invoice_mapping ?? []);
            $conversionDetails = (array) ($period->conversion_details ?? []);
            $convertedIds = array_map('intval', (array) ($period->converted_employee_ids ?? []));
            $removedEmployeeIds = [];

            foreach (array_keys($mapping) as $empId) {
                $empKey = (string) $empId;
                $employeeId = (int) $empId;
                $empInvoiceIds = array_values(array_filter(
                    array_map('intval', (array) ($mapping[$empKey] ?? [])),
                    fn (int $id) => $id !== $invoiceId
                ));

                if ($empInvoiceIds === []) {
                    unset($mapping[$empKey]);
                    $conversionDetails = $this->conversionDetails->removeEmployee($conversionDetails, $employeeId);
                    $convertedIds = array_values(array_filter($convertedIds, fn (int $id) => $id !== $employeeId));
                    $removedEmployeeIds[] = $employeeId;
                } else {
                    $mapping[$empKey] = $empInvoiceIds;
                    if ($invoiceClientId > 0) {
                        $conversionDetails = $this->conversionDetails->removeEmployeeClient(
                            $conversionDetails,
                            $employeeId,
                            $invoiceClientId
                        );
                    }
                }
            }

            foreach ($removedEmployeeIds as $employeeId) {
                $this->resetSalaryComputationHours($period, $employeeId);
            }

            if ($invoiceIds === []) {
                $period->delete();

                continue;
            }

            $period->invoice_ids = $invoiceIds;
            $period->employee_invoice_mapping = $mapping;
            $period->converted_employee_ids = array_values(array_unique($convertedIds));

            foreach (array_keys($mapping) as $empId) {
                $this->refreshEmployeeClientBillAmounts($conversionDetails, (int) $empId, $period);
            }

            $period->update([
                'invoice_ids' => $invoiceIds,
                'employee_invoice_mapping' => $mapping,
                'converted_employee_ids' => array_values(array_unique($convertedIds)),
                'conversion_details' => $conversionDetails,
            ]);

            $this->pruneOrphanedEmployees($period->fresh());
        }
    }

    private function pruneOrphanedEmployees(PayrollPeriodInvoice $period): void
    {
        $mapping = (array) ($period->employee_invoice_mapping ?? []);
        $conversionDetails = (array) ($period->conversion_details ?? []);
        $convertedIds = array_map('intval', (array) ($period->converted_employee_ids ?? []));
        $changed = false;

        foreach (array_keys($mapping) as $empId) {
            if ($this->employeeHasPayrollLinesInPeriod((int) $empId, $period)) {
                continue;
            }

            $employeeId = (int) $empId;
            unset($mapping[(string) $empId]);
            $conversionDetails = $this->conversionDetails->removeEmployee($conversionDetails, $employeeId);
            $convertedIds = array_values(array_filter($convertedIds, fn (int $id) => $id !== $employeeId));
            $this->resetSalaryComputationHours($period, $employeeId);
            $changed = true;
        }

        if (! $changed) {
            return;
        }

        $invoiceIds = array_map('intval', (array) ($period->invoice_ids ?? []));
        if ($mapping === [] || $invoiceIds === []) {
            $period->delete();

            return;
        }

        $period->update([
            'employee_invoice_mapping' => $mapping,
            'converted_employee_ids' => array_values(array_unique($convertedIds)),
            'conversion_details' => $conversionDetails,
        ]);
    }

    private function resetSalaryComputationHours(PayrollPeriodInvoice $period, int $employeeId): void
    {
        SalaryComputation::query()
            ->where('company_id', $period->company_id)
            ->where('user_id', $employeeId)
            ->whereDate('period_start_date', $period->period_start_date)
            ->whereDate('period_end_date', $period->period_end_date)
            ->update(['hours_worked' => 0]);
    }

    private function employeeHasPayrollLinesInPeriod(int $employeeId, PayrollPeriodInvoice $period): bool
    {
        $employee = User::query()->find($employeeId, ['id', 'name']);
        if (! $employee) {
            return false;
        }

        $invoiceIds = array_map('intval', (array) ($period->invoice_ids ?? []));
        if ($invoiceIds === []) {
            return false;
        }

        $descriptionPrefix = "Payroll - {$employee->name} (";

        return InvoiceItem::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->where('description', 'like', $descriptionPrefix.'%')
            ->exists();
    }

    /**
     * @param  array<string|int, mixed>  $conversionDetails
     */
    private function refreshEmployeeClientBillAmounts(array &$conversionDetails, int $employeeId, PayrollPeriodInvoice $period): void
    {
        $billAmounts = $this->billAmountsByClientForEmployeeInPeriod($employeeId, $period);
        foreach ($billAmounts as $clientId => $billAmount) {
            $key = $this->conversionDetails->detailKey($employeeId, (int) $clientId);
            $existing = is_array($conversionDetails[$key] ?? null) ? $conversionDetails[$key] : [];
            $conversionDetails[$key] = array_merge($existing, [
                'employee_id' => $employeeId,
                'client_id' => (int) $clientId,
                'bill_amount' => round((float) $billAmount, 2),
            ]);
        }
    }

    /**
     * @return array<int, float>
     */
    public function billAmountsByClientForEmployeeInPeriod(int $employeeId, PayrollPeriodInvoice $period): array
    {
        $employee = User::query()->find($employeeId, ['id', 'name']);
        if (! $employee) {
            return [];
        }

        $invoiceIds = array_map('intval', (array) ($period->invoice_ids ?? []));
        if ($invoiceIds === []) {
            return [];
        }

        $descriptionPrefix = "Payroll - {$employee->name} (";
        $items = InvoiceItem::query()
            ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
            ->whereIn('invoice_items.invoice_id', $invoiceIds)
            ->where('invoice_items.description', 'like', $descriptionPrefix.'%')
            ->get(['invoice_items.total', 'invoices.client_id']);

        $amounts = [];
        foreach ($items as $item) {
            $clientId = (int) ($item->client_id ?? 0);
            if ($clientId < 1) {
                continue;
            }
            $amounts[$clientId] = round(($amounts[$clientId] ?? 0) + (float) $item->total, 2);
        }

        return $amounts;
    }

    private function sumBillAmountForEmployeeInPeriod(int $employeeId, PayrollPeriodInvoice $period): ?float
    {
        $amounts = $this->billAmountsByClientForEmployeeInPeriod($employeeId, $period);
        if ($amounts === []) {
            return null;
        }

        return round(array_sum($amounts), 2);
    }

    /**
     * @param  array<string|int, mixed>  $employeeInvoiceMapping
     */
    private function resolveEmployeeIdForLineItem(Invoice $invoice, InvoiceItem $item, array $employeeInvoiceMapping): ?int
    {
        $employeeName = null;
        if ($item->description && preg_match('/^Payroll - (.+?) \(\d{4}-\d{2}-\d{2} to \d{4}-\d{2}-\d{2}\)/', $item->description, $matches)) {
            $employeeName = trim($matches[1]);
        }

        foreach ($employeeInvoiceMapping as $empId => $invoiceIds) {
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

        return null;
    }
}
