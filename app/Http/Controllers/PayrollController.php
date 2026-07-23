<?php

namespace App\Http\Controllers;

use App\Exports\PayrollReportExport;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PayrollPeriodInvoice;
use App\Models\PayrollReport;
use App\Models\PayrollReportItem;
use App\Models\PnlManualExpense;
use App\Models\SalaryComputation;
use App\Models\SalaryComputationHistory;
use App\Models\TimeTracking;
use App\Models\TimeTrackingEditHistory;
use App\Models\User;
use App\Services\InvoiceItemHoursSyncService;
use App\Services\PayrollConversionDetailsService;
use App\Services\TimezoneService;
use App\Services\WiseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class PayrollController extends Controller
{
    private function conversionDetailsService(): PayrollConversionDetailsService
    {
        return app(PayrollConversionDetailsService::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function payrollReportRowsKeyedByEmployeeId(string $startDate, string $endDate): array
    {
        $reportRequest = Request::create('/api/payroll/payroll-report', 'GET', [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
        $reportResponse = $this->getPayrollReport($reportRequest);
        $payload = $reportResponse->getData(true);
        if (! ($payload['success'] ?? false)) {
            return [];
        }

        $keyed = [];
        foreach ($payload['data'] ?? [] as $row) {
            $employeeId = (int) ($row['employee_id'] ?? 0);
            if ($employeeId > 0) {
                $keyed[$employeeId] = $row;
            }
        }

        return $keyed;
    }

    /**
     * @return array<string, list<int>>
     */
    private function convertedClientsByEmployeeFromPeriod(?PayrollPeriodInvoice $periodInvoice): array
    {
        if (! $periodInvoice) {
            return [];
        }

        $employeeMapping = (array) ($periodInvoice->employee_invoice_mapping ?? []);
        $invoiceIds = array_map('intval', (array) ($periodInvoice->invoice_ids ?? []));
        if ($invoiceIds === []) {
            return [];
        }

        $invoiceClientById = Invoice::whereIn('id', $invoiceIds)
            ->pluck('client_id', 'id')
            ->map(fn ($clientId) => (int) $clientId)
            ->toArray();

        $convertedClientsByEmployee = [];
        foreach ($employeeMapping as $empId => $empInvoiceIds) {
            $clientIds = [];
            foreach ((array) $empInvoiceIds as $invId) {
                $clientId = $invoiceClientById[(int) $invId] ?? null;
                if ($clientId) {
                    $clientIds[] = (int) $clientId;
                }
            }
            if ($clientIds !== []) {
                $convertedClientsByEmployee[(string) $empId] = array_values(array_unique($clientIds));
            }
        }

        return $convertedClientsByEmployee;
    }

    /**
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $reportRow
     */
    private function resolveConversionField(array $detail, array $reportRow, string $field): float
    {
        if (array_key_exists($field, $detail) && $detail[$field] !== '' && $detail[$field] !== null) {
            return round((float) $detail[$field], 2);
        }

        return round((float) ($reportRow[$field] ?? 0), 2);
    }

    private function calculateReportCommissionForBill(User $employee, float $billAmount): float
    {
        $commissionType = $employee->sales_rep_commission_type;
        $commissionValue = $employee->sales_rep_commission_value !== null
            ? (float) $employee->sales_rep_commission_value
            : null;

        if (! $commissionType || $commissionValue === null || $billAmount <= 0) {
            return 0.0;
        }

        if ($commissionType === 'percent') {
            return round($billAmount * ($commissionValue / 100), 2);
        }

        if ($commissionType === 'usd') {
            return round($commissionValue, 2);
        }

        return 0.0;
    }

    /**
     * Display the payroll page.
     */
    public function index()
    {
        return view('dashboard.payroll');
    }

    /**
     * Payroll summary grouped by sales rep.
     */
    public function salesRepSummary()
    {
        return view('dashboard.payroll-sales-reps');
    }

    /**
     * Standalone P&L dashboard (payroll period).
     */
    public function pnl()
    {
        return view('dashboard.pnl');
    }

    /**
     * PNL line for payroll period: prorated client invoice (vs required hours), commission, payroll cost (gross), margin.
     *
     * @param  float|null  $commissionValue  USD amount (monthly) or percent value when type is percent
     */
    private function calculatePayrollPnlLine(
        float $hoursWorked,
        float $employeeRequiredHours,
        float $clientInvoiceMonthly,
        ?string $commissionType,
        ?float $commissionValue,
        float $grossPay
    ): array {
        $ratio = $employeeRequiredHours > 0 ? ($hoursWorked / $employeeRequiredHours) : 0.0;
        $proratedClient = round($clientInvoiceMonthly * $ratio, 2);

        $commission = 0.0;
        if ($commissionType && $commissionValue !== null) {
            if ($commissionType === 'percent') {
                $commission = round($proratedClient * ($commissionValue / 100), 2);
            } elseif ($commissionType === 'usd') {
                $commission = round($commissionValue * $ratio, 2);
            }
        }

        $salaryCost = round($grossPay, 2);
        $margin = round($proratedClient - $salaryCost, 2);

        return [
            'pnl_salary_cost' => $salaryCost,
            'pnl_client_invoice' => $proratedClient,
            'pnl_commission' => $commission,
            'pnl_margin' => $margin,
        ];
    }

    /**
     * Assign a billing invoice line to an employee when the line description matches an employee
     * assigned to the invoice client; otherwise bucket under employee_id 0 ("invoice" in P&L).
     *
     * @param  array<int, array<int, string>>  $employeesByClientId  client_id => [employee_id => name]
     * @return array<int, float> employee_id => allocated amount
     */
    private function allocatePnlBillingLineByDescription(
        float $amount,
        int $clientId,
        string $description,
        array $employeesByClientId
    ): array {
        if ($amount <= 0 || $clientId < 1) {
            return [];
        }

        $description = trim($description);
        $employees = $employeesByClientId[$clientId] ?? [];

        if ($description !== '') {
            foreach ($employees as $employeeId => $name) {
                if (strcasecmp(trim($name), $description) === 0) {
                    return [(int) $employeeId => round($amount, 2)];
                }
            }
        }

        return [0 => round($amount, 2)];
    }

    /**
     * @param  array<int, float>  $splits
     * @param  array<int, float>  $billingNetPayByEmployeeId
     * @param  array<int, array<int, float>>  $billingNetPayByEmployeeClient
     */
    private function accumulatePnlBillingNetPayFromLine(
        float $lineNetPay,
        float $lineAmount,
        array $splits,
        int $clientId,
        ?array $allowedEmployeeIds,
        array &$billingNetPayByEmployeeId,
        array &$billingNetPayByEmployeeClient,
        ?array &$weeklyNetPay = null,
        ?array &$weeklyPaidNetPay = null,
        ?string $weekKey = null,
        bool $isPaidInvoice = false,
    ): void {
        if ($lineNetPay <= 0 || $clientId < 1 || $splits === []) {
            return;
        }

        $weeklyTotal = 0.0;

        foreach ($splits as $empId => $part) {
            $empId = (int) $empId;
            if ($allowedEmployeeIds !== null && $empId !== 0 && ! isset($allowedEmployeeIds[$empId])) {
                continue;
            }

            $share = $lineAmount > 0
                ? round($lineNetPay * ((float) $part / $lineAmount), 2)
                : round($lineNetPay, 2);
            $billingNetPayByEmployeeId[$empId] = round(($billingNetPayByEmployeeId[$empId] ?? 0) + $share, 2);
            $billingNetPayByEmployeeClient[$empId][$clientId] = round(
                ($billingNetPayByEmployeeClient[$empId][$clientId] ?? 0) + $share,
                2
            );
            $weeklyTotal = round($weeklyTotal + $share, 2);
        }

        if ($weeklyNetPay !== null && $weekKey !== null && $weeklyTotal > 0) {
            $weeklyNetPay[$weekKey] = round(($weeklyNetPay[$weekKey] ?? 0) + $weeklyTotal, 2);
            if ($isPaidInvoice && $weeklyPaidNetPay !== null) {
                $weeklyPaidNetPay[$weekKey] = round(($weeklyPaidNetPay[$weekKey] ?? 0) + $weeklyTotal, 2);
            }
        }
    }

    /**
     * @param  array<string, float>  $weeklyNetPay
     * @param  array<string, float>  $weeklyCommission
     * @param  array<string, float>  $weeklyPaidNetPay
     * @param  array<string, float>  $weeklyPaidCommission
     */
    private function accumulatePnlWeeklyPayrollCostsFromLine(
        ?PayrollPeriodInvoice $period,
        int $empId,
        int $clientId,
        string $weekKey,
        bool $isPaidInvoice,
        array &$weeklyNetPay,
        array &$weeklyCommission,
        array &$weeklyPaidNetPay,
        array &$weeklyPaidCommission,
    ): void {
        if (! $period || $empId < 1 || $clientId < 1) {
            return;
        }

        $key = $this->conversionDetailsService()->detailKey($empId, $clientId);
        $detail = (array) (($period->conversion_details ?? [])[$key] ?? null);
        if ($detail === []) {
            return;
        }

        $netPay = round((float) ($detail['net_pay'] ?? 0), 2);
        $commission = round((float) ($detail['commission'] ?? 0), 2);

        if ($netPay > 0) {
            $weeklyNetPay[$weekKey] = round(($weeklyNetPay[$weekKey] ?? 0) + $netPay, 2);
            if ($isPaidInvoice) {
                $weeklyPaidNetPay[$weekKey] = round(($weeklyPaidNetPay[$weekKey] ?? 0) + $netPay, 2);
            }
        }

        if ($commission > 0) {
            $weeklyCommission[$weekKey] = round(($weeklyCommission[$weekKey] ?? 0) + $commission, 2);
            if ($isPaidInvoice) {
                $weeklyPaidCommission[$weekKey] = round(($weeklyPaidCommission[$weekKey] ?? 0) + $commission, 2);
            }
        }
    }

    /**
     * Split an amount across keys proportional to positive weights (last key absorbs rounding).
     *
     * @param  array<int|string, float>  $partsByKey
     * @return array<int|string, float>
     */
    private function allocatePnlAmountProportionally(float $amount, array $partsByKey): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0 || $partsByKey === []) {
            return [];
        }

        $positive = [];
        foreach ($partsByKey as $key => $weight) {
            $w = round((float) $weight, 2);
            if ($w > 0) {
                $positive[$key] = $w;
            }
        }

        if ($positive === []) {
            $keys = array_keys($partsByKey);
            $n = count($keys);
            if ($n < 1) {
                return [];
            }
            $share = round($amount / $n, 2);
            $allocated = [];
            $running = 0.0;
            foreach ($keys as $i => $key) {
                if ($i === $n - 1) {
                    $allocated[$key] = round($amount - $running, 2);
                } else {
                    $allocated[$key] = $share;
                    $running += $share;
                }
            }

            return $allocated;
        }

        $total = round(array_sum($positive), 2);
        $allocated = [];
        $running = 0.0;
        $keys = array_keys($positive);
        $n = count($keys);
        foreach ($keys as $i => $key) {
            if ($i === $n - 1) {
                $allocated[$key] = round($amount - $running, 2);
            } else {
                $part = round($amount * ($positive[$key] / $total), 2);
                $allocated[$key] = $part;
                $running += $part;
            }
        }

        return $allocated;
    }

    /**
     * @param  array<int, float>  $paidPayrollByClientId
     * @param  array<int, float>  $paidBillingByClientId
     * @param  array<int, float>  $paidNetPayByClientId
     * @param  array<int, float>  $commissionByClientId
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{by_client: array<int, array<string, mixed>>, by_sales_rep: array<int, array<string, mixed>>}
     */
    /**
     * @return array{paid: float, unpaid: float}
     */
    private function splitPnlAmountByPaidCollections(float $amount, float $paidCollections, float $totalCollections): array
    {
        if ($totalCollections <= 0) {
            return [
                'paid' => 0.0,
                'unpaid' => round($amount, 2),
            ];
        }

        $paidRatio = min(1.0, $paidCollections / $totalCollections);
        $paid = round($amount * $paidRatio, 2);

        return [
            'paid' => $paid,
            'unpaid' => round($amount - $paid, 2),
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PayrollPeriodInvoice>  $periodInvoices
     * @param  array<int, array<int, float>>  $payrollByEmployeeClient
     * @param  array<int, array<int, float>>  $billingByEmployeeClient
     * @param  array<int, array<int, float>>  $paidPayrollByEmployeeClient
     * @param  array<int, array<int, float>>  $paidBillingByEmployeeClient
     * @param  array<int, array<int, float>>  $billingNetPayByEmployeeClient
     * @return array<int, array<string, mixed>>
     */
    private function buildPnlEmployeeClientRows(
        $periodInvoices,
        array $payrollByEmployeeClient,
        array $billingByEmployeeClient,
        array $paidPayrollByEmployeeClient,
        array $paidBillingByEmployeeClient,
        array $billingNetPayByEmployeeClient,
        ?array $allowedEmployeeIds,
        ?int $filterClientId
    ): array {
        $conversionByKey = [];

        foreach ($periodInvoices as $periodInvoice) {
            foreach ((array) ($periodInvoice->conversion_details ?? []) as $detailKey => $detail) {
                if (! is_array($detail)) {
                    continue;
                }

                $parsed = $this->conversionDetailsService()->parseKey((string) $detailKey);
                $empId = $parsed['employee_id'];
                $clientId = $parsed['client_id'] ?? (int) ($detail['client_id'] ?? 0);
                if ($empId < 1 || $clientId < 1) {
                    continue;
                }
                if (! $this->isPnlAllowedEmployee($allowedEmployeeIds, $empId)) {
                    continue;
                }
                if ($filterClientId && $clientId !== $filterClientId) {
                    continue;
                }

                $key = $empId.':'.$clientId;
                if (! isset($conversionByKey[$key])) {
                    $conversionByKey[$key] = [
                        'employee_id' => $empId,
                        'client_id' => $clientId,
                        'bill_amount' => 0.0,
                        'net_pay' => 0.0,
                        'commission' => 0.0,
                    ];
                }

                $conversionByKey[$key]['bill_amount'] = round(
                    $conversionByKey[$key]['bill_amount'] + (float) ($detail['bill_amount'] ?? 0),
                    2
                );
                $conversionByKey[$key]['net_pay'] = round(
                    $conversionByKey[$key]['net_pay'] + (float) ($detail['net_pay'] ?? 0),
                    2
                );
                $conversionByKey[$key]['commission'] = round(
                    $conversionByKey[$key]['commission'] + (float) ($detail['commission'] ?? 0),
                    2
                );
            }
        }

        $allKeys = [];
        foreach (array_keys($conversionByKey) as $key) {
            $allKeys[$key] = true;
        }
        foreach ($payrollByEmployeeClient as $empId => $byClient) {
            foreach ($byClient as $clientId => $amount) {
                if ((float) $amount > 0) {
                    $allKeys[(int) $empId.':'.(int) $clientId] = true;
                }
            }
        }
        foreach ($billingByEmployeeClient as $empId => $byClient) {
            foreach ($byClient as $clientId => $amount) {
                if ((float) $amount > 0) {
                    $allKeys[(int) $empId.':'.(int) $clientId] = true;
                }
            }
        }
        foreach ($billingNetPayByEmployeeClient as $empId => $byClient) {
            foreach ($byClient as $clientId => $amount) {
                if ((float) $amount > 0) {
                    $allKeys[(int) $empId.':'.(int) $clientId] = true;
                }
            }
        }

        $rows = [];
        foreach (array_keys($allKeys) as $key) {
            $parts = explode(':', $key, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $empId = (int) $parts[0];
            $clientId = (int) $parts[1];
            if ($clientId < 1 || $empId < 0) {
                continue;
            }
            if (! $this->isPnlAllowedEmployee($allowedEmployeeIds, $empId)) {
                continue;
            }
            if ($filterClientId && $clientId !== $filterClientId) {
                continue;
            }

            $conversion = $conversionByKey[$key] ?? null;
            $payrollPart = round((float) ($payrollByEmployeeClient[$empId][$clientId] ?? 0), 2);
            $billingPart = round((float) ($billingByEmployeeClient[$empId][$clientId] ?? 0), 2);
            $paidPayrollPart = round((float) ($paidPayrollByEmployeeClient[$empId][$clientId] ?? 0), 2);
            $paidBillingPart = round((float) ($paidBillingByEmployeeClient[$empId][$clientId] ?? 0), 2);
            $collections = round($payrollPart + $billingPart, 2);
            $paidCollections = round($paidPayrollPart + $paidBillingPart, 2);
            $unpaidCollections = round($collections - $paidCollections, 2);
            $hasPayrollInvoiced = $payrollPart > 0;
            $conversionNetPay = $hasPayrollInvoiced
                ? round((float) ($conversion['net_pay'] ?? 0), 2)
                : 0.0;
            $billingNetPay = round((float) ($billingNetPayByEmployeeClient[$empId][$clientId] ?? 0), 2);
            $netPay = round($conversionNetPay + $billingNetPay, 2);
            $commission = $hasPayrollInvoiced
                ? round((float) ($conversion['commission'] ?? 0), 2)
                : 0.0;
            $conversionNetPaySplit = $hasPayrollInvoiced
                ? $this->splitPnlAmountByPaidCollections($conversionNetPay, $paidPayrollPart, $payrollPart)
                : ['paid' => 0.0, 'unpaid' => 0.0];
            $billingNetPaySplit = $billingPart > 0
                ? $this->splitPnlAmountByPaidCollections($billingNetPay, $paidBillingPart, $billingPart)
                : ['paid' => 0.0, 'unpaid' => $billingNetPay];
            $netPaySplit = [
                'paid' => round($conversionNetPaySplit['paid'] + $billingNetPaySplit['paid'], 2),
                'unpaid' => round($conversionNetPaySplit['unpaid'] + $billingNetPaySplit['unpaid'], 2),
            ];
            $commissionSplit = $hasPayrollInvoiced
                ? $this->splitPnlAmountByPaidCollections($commission, $paidPayrollPart, $payrollPart)
                : ['paid' => 0.0, 'unpaid' => 0.0];

            if ($collections <= 0 && $netPay <= 0 && $commission <= 0) {
                continue;
            }

            $rows[] = [
                'employee_id' => $empId,
                'client_id' => $clientId,
                'collections' => $collections,
                'paid_collections' => $paidCollections,
                'unpaid_collections' => $unpaidCollections,
                'net_pay' => $netPay,
                'paid_net_pay' => $netPaySplit['paid'],
                'unpaid_net_pay' => $netPaySplit['unpaid'],
                'commission' => $commission,
                'paid_commission' => $commissionSplit['paid'],
                'unpaid_commission' => $commissionSplit['unpaid'],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{employee_id: int, client_id: int, collections: float, net_pay: float, commission: float}>  $employeeClientRows
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{by_client: array<int, array<string, mixed>>, by_sales_rep: array<int, array<string, mixed>>}
     */
    private function buildPnlClientAndSalesRepBreakdowns(
        array $employeeClientRows,
        array $rows,
        int $companyId,
        ?int $filterClientId = null
    ): array {
        $employeeNamesById = [];
        foreach ($rows as $row) {
            $empId = (int) ($row['employee_id'] ?? 0);
            if ($empId > 0) {
                $employeeNamesById[$empId] = (string) ($row['employee_name'] ?? 'Employee #'.$empId);
            }
        }

        $groupedByClient = [];
        foreach ($employeeClientRows as $row) {
            $clientId = (int) ($row['client_id'] ?? 0);
            $empId = (int) ($row['employee_id'] ?? 0);
            if ($clientId < 1) {
                continue;
            }
            if ($filterClientId && $clientId !== $filterClientId) {
                continue;
            }

            if (! isset($groupedByClient[$clientId])) {
                $groupedByClient[$clientId] = [
                    'client_id' => $clientId,
                    'collections' => 0.0,
                    'paid_collections' => 0.0,
                    'unpaid_collections' => 0.0,
                    'net_pay' => 0.0,
                    'paid_net_pay' => 0.0,
                    'unpaid_net_pay' => 0.0,
                    'commission' => 0.0,
                    'paid_commission' => 0.0,
                    'unpaid_commission' => 0.0,
                    'employees' => [],
                ];
            }

            $collections = round((float) ($row['collections'] ?? 0), 2);
            $paidCollections = round((float) ($row['paid_collections'] ?? 0), 2);
            $unpaidCollections = round((float) ($row['unpaid_collections'] ?? 0), 2);
            $netPay = round((float) ($row['net_pay'] ?? 0), 2);
            $paidNetPay = round((float) ($row['paid_net_pay'] ?? 0), 2);
            $unpaidNetPay = round((float) ($row['unpaid_net_pay'] ?? 0), 2);
            $commission = round((float) ($row['commission'] ?? 0), 2);
            $paidCommission = round((float) ($row['paid_commission'] ?? 0), 2);
            $unpaidCommission = round((float) ($row['unpaid_commission'] ?? 0), 2);

            $groupedByClient[$clientId]['employees'][] = [
                'employee_id' => $empId,
                'employee_name' => $empId > 0
                    ? (string) ($employeeNamesById[$empId] ?? 'Employee #'.$empId)
                    : 'invoice',
                'collections' => $collections,
                'paid_collections' => $paidCollections,
                'unpaid_collections' => $unpaidCollections,
                'net_pay' => $netPay,
                'paid_net_pay' => $paidNetPay,
                'unpaid_net_pay' => $unpaidNetPay,
                'commission' => $commission,
                'paid_commission' => $paidCommission,
                'unpaid_commission' => $unpaidCommission,
                'net_profit' => round($collections - $netPay - $commission, 2),
                'paid_net_profit' => round($paidCollections - $paidNetPay - $paidCommission, 2),
                'unpaid_net_profit' => round($unpaidCollections - $unpaidNetPay - $unpaidCommission, 2),
            ];

            $groupedByClient[$clientId]['collections'] = round($groupedByClient[$clientId]['collections'] + $collections, 2);
            $groupedByClient[$clientId]['paid_collections'] = round($groupedByClient[$clientId]['paid_collections'] + $paidCollections, 2);
            $groupedByClient[$clientId]['unpaid_collections'] = round($groupedByClient[$clientId]['unpaid_collections'] + $unpaidCollections, 2);
            $groupedByClient[$clientId]['net_pay'] = round($groupedByClient[$clientId]['net_pay'] + $netPay, 2);
            $groupedByClient[$clientId]['paid_net_pay'] = round($groupedByClient[$clientId]['paid_net_pay'] + $paidNetPay, 2);
            $groupedByClient[$clientId]['unpaid_net_pay'] = round($groupedByClient[$clientId]['unpaid_net_pay'] + $unpaidNetPay, 2);
            $groupedByClient[$clientId]['commission'] = round($groupedByClient[$clientId]['commission'] + $commission, 2);
            $groupedByClient[$clientId]['paid_commission'] = round($groupedByClient[$clientId]['paid_commission'] + $paidCommission, 2);
            $groupedByClient[$clientId]['unpaid_commission'] = round($groupedByClient[$clientId]['unpaid_commission'] + $unpaidCommission, 2);
        }

        $clientIds = array_keys($groupedByClient);
        $clientNames = $clientIds === []
            ? collect()
            : Client::query()
                ->where('company_id', $companyId)
                ->whereIn('id', $clientIds)
                ->pluck('name', 'id');

        $byClient = collect($groupedByClient)->map(function (array $clientRow, int $clientId) use ($clientNames) {
            $employees = collect($clientRow['employees'])
                ->sortBy('employee_name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();

            $collections = round((float) $clientRow['collections'], 2);
            $paidCollections = round((float) $clientRow['paid_collections'], 2);
            $unpaidCollections = round((float) $clientRow['unpaid_collections'], 2);
            $netPay = round((float) $clientRow['net_pay'], 2);
            $paidNetPay = round((float) $clientRow['paid_net_pay'], 2);
            $unpaidNetPay = round((float) $clientRow['unpaid_net_pay'], 2);
            $commission = round((float) $clientRow['commission'], 2);
            $paidCommission = round((float) $clientRow['paid_commission'], 2);
            $unpaidCommission = round((float) $clientRow['unpaid_commission'], 2);

            return [
                'client_id' => $clientId,
                'client_name' => (string) ($clientNames[$clientId] ?? 'Client #'.$clientId),
                'collections' => $collections,
                'paid_collections' => $paidCollections,
                'unpaid_collections' => $unpaidCollections,
                'net_pay' => $netPay,
                'paid_net_pay' => $paidNetPay,
                'unpaid_net_pay' => $unpaidNetPay,
                'commission' => $commission,
                'paid_commission' => $paidCommission,
                'unpaid_commission' => $unpaidCommission,
                'net_profit' => round($collections - $netPay - $commission, 2),
                'paid_net_profit' => round($paidCollections - $paidNetPay - $paidCommission, 2),
                'unpaid_net_profit' => round($unpaidCollections - $unpaidNetPay - $unpaidCommission, 2),
                'employees' => $employees,
            ];
        })->filter(fn (array $row) => $row['collections'] > 0
            || $row['net_pay'] > 0
            || $row['commission'] > 0
            || count($row['employees']) > 0)
            ->sortBy('client_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $salesRepMap = [];
        foreach ($rows as $row) {
            $repId = (int) ($row['sales_rep_id'] ?? 0);
            $key = $repId > 0 ? (string) $repId : 'unassigned';
            if (! isset($salesRepMap[$key])) {
                $salesRepMap[$key] = [
                    'sales_rep_id' => $repId > 0 ? $repId : null,
                    'sales_rep_name' => $repId > 0
                        ? (string) ($row['sales_rep_name'] ?? 'Unknown')
                        : 'Unassigned',
                    'collections' => 0.0,
                    'net_pay' => 0.0,
                    'commission' => 0.0,
                    'paid_commission' => 0.0,
                    'unpaid_commission' => 0.0,
                    'net_profit' => 0.0,
                ];
            }
            $collections = round((float) ($row['pnl_client_invoice_paid'] ?? 0), 2);
            $netPay = round((float) ($row['pnl_net_pay_paid'] ?? 0), 2);
            $commission = round((float) ($row['pnl_commission'] ?? 0), 2);
            $payrollInvoicedTotal = round((float) ($row['pnl_payroll_invoiced'] ?? 0), 2);
            $paidPayrollInvoicedTotal = round((float) ($row['pnl_payroll_invoiced_paid'] ?? 0), 2);
            $commissionSplit = $this->splitPnlAmountByPaidCollections(
                $commission,
                $paidPayrollInvoicedTotal,
                $payrollInvoicedTotal
            );
            $salesRepMap[$key]['collections'] = round($salesRepMap[$key]['collections'] + $collections, 2);
            $salesRepMap[$key]['net_pay'] = round($salesRepMap[$key]['net_pay'] + $netPay, 2);
            $salesRepMap[$key]['commission'] = round($salesRepMap[$key]['commission'] + $commission, 2);
            $salesRepMap[$key]['paid_commission'] = round($salesRepMap[$key]['paid_commission'] + $commissionSplit['paid'], 2);
            $salesRepMap[$key]['unpaid_commission'] = round($salesRepMap[$key]['unpaid_commission'] + $commissionSplit['unpaid'], 2);
            $salesRepMap[$key]['net_profit'] = round(
                $salesRepMap[$key]['collections'] - $salesRepMap[$key]['net_pay'] - $salesRepMap[$key]['commission'],
                2
            );
        }

        $bySalesRep = collect($salesRepMap)
            ->sortBy('sales_rep_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'by_client' => $byClient,
            'by_sales_rep' => $bySalesRep,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{filter_sales_rep_id: ?int, filter_unassigned: bool, allowed_employee_ids: ?array<int, true>}
     */
    private function resolvePnlSalesRepEmployeeFilter(Request $request, array $rows): array
    {
        $filterSalesRepId = null;
        $filterUnassigned = false;

        if ($request->filled('sales_rep_id')) {
            $raw = $request->get('sales_rep_id');
            if ($raw === 'unassigned') {
                $filterUnassigned = true;
            } else {
                $parsed = (int) $raw;
                if ($parsed > 0) {
                    $filterSalesRepId = $parsed;
                }
            }
        }

        if ($filterSalesRepId === null && ! $filterUnassigned) {
            return [
                'filter_sales_rep_id' => null,
                'filter_unassigned' => false,
                'allowed_employee_ids' => null,
            ];
        }

        $allowedEmployeeIds = [];
        foreach ($rows as $row) {
            $employeeId = (int) ($row['employee_id'] ?? 0);
            if ($employeeId < 1) {
                continue;
            }
            $repId = (int) ($row['sales_rep_id'] ?? 0);
            if ($filterUnassigned && $repId < 1) {
                $allowedEmployeeIds[$employeeId] = true;
            } elseif ($filterSalesRepId !== null && $repId === $filterSalesRepId) {
                $allowedEmployeeIds[$employeeId] = true;
            }
        }

        return [
            'filter_sales_rep_id' => $filterSalesRepId,
            'filter_unassigned' => $filterUnassigned,
            'allowed_employee_ids' => $allowedEmployeeIds,
        ];
    }

    /**
     * @param  array<int, true>|null  $allowedEmployeeIds
     */
    private function isPnlAllowedEmployee(?array $allowedEmployeeIds, int $employeeId): bool
    {
        if ($employeeId === 0) {
            return true;
        }

        return $allowedEmployeeIds === null || isset($allowedEmployeeIds[$employeeId]);
    }

    /**
     * Rebuild employee → invoice IDs from payroll line descriptions on converted invoices.
     *
     * @param  array<int>  $invoiceIds
     * @param  array<string, int>  $employeeNameToId
     * @return array<string, array<int>>
     */
    private function rebuildEmployeeInvoiceMappingFromPayrollLines(array $invoiceIds, array $employeeNameToId): array
    {
        $mapping = [];

        if ($invoiceIds === [] || $employeeNameToId === []) {
            return $mapping;
        }

        $payrollLinePattern = '/^Payroll - (.+?) \(\d{4}-\d{2}-\d{2}/';
        $items = InvoiceItem::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->get(['invoice_id', 'description']);

        foreach ($items as $item) {
            if (! preg_match($payrollLinePattern, $item->description ?? '', $matches)) {
                continue;
            }

            $name = trim($matches[1]);
            $empId = $employeeNameToId[$name] ?? null;
            if (! $empId) {
                continue;
            }

            $key = (string) $empId;
            $mapping[$key] = $mapping[$key] ?? [];
            $mapping[$key][] = (int) $item->invoice_id;
        }

        foreach ($mapping as $key => $ids) {
            $mapping[$key] = array_values(array_unique(array_map('intval', (array) $ids)));
        }

        return $mapping;
    }

    /**
     * Merge stored and rebuilt employee invoice mappings for P&L net pay recognition.
     *
     * @param  array<string|int, mixed>  $storedMapping
     * @param  array<string, array<int>>  $rebuiltMapping
     * @return array<string, array<int>>
     */
    private function mergeEmployeeInvoiceMappings(array $storedMapping, array $rebuiltMapping): array
    {
        $merged = [];

        foreach ($storedMapping as $empKey => $invoiceIds) {
            $key = (string) $empKey;
            $merged[$key] = array_values(array_unique(array_map('intval', (array) $invoiceIds)));
        }

        foreach ($rebuiltMapping as $empKey => $invoiceIds) {
            $key = (string) $empKey;
            $existing = $merged[$key] ?? [];
            $merged[$key] = array_values(array_unique(array_merge($existing, $invoiceIds)));
        }

        return $merged;
    }

    /**
     * Sum tracked hours per employee per calendar week (Monday start), for a date range.
     * Keys are Y-m-d of the week's Monday; values are hours rounded to 1 decimal.
     *
     * @return array<int, array<string, float>>
     */
    private function aggregateTimeTrackingHoursByUserAndWeek(int $companyId, string $startDate, string $endDate): array
    {
        $rows = TimeTracking::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [
                Carbon::parse($startDate)->toDateString(),
                Carbon::parse($endDate)->toDateString(),
            ])
            ->whereNotNull('hours_worked')
            ->where('hours_worked', '>', 0)
            ->get(['user_id', 'date', 'hours_worked']);

        $secondsByUserWeek = [];

        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            $weekStart = Carbon::parse($row->date)->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
            if (! isset($secondsByUserWeek[$uid][$weekStart])) {
                $secondsByUserWeek[$uid][$weekStart] = 0;
            }
            $secondsByUserWeek[$uid][$weekStart] += (int) $row->hours_worked;
        }

        $out = [];
        foreach ($secondsByUserWeek as $uid => $weeks) {
            foreach ($weeks as $weekKey => $seconds) {
                $out[$uid][$weekKey] = round($seconds / 3600, 1);
            }
        }

        return $out;
    }

    /**
     * Get time tracking records for payroll.
     */
    public function getTimeTrackingRecords(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            // Get filters - default to current month if not provided
            $employeeId = $request->get('employee_id', 'all');
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            // Default to current month if dates are not provided
            if (! $startDate) {
                $startDate = TimezoneService::now()->startOfMonth()->format('Y-m-d');
            }
            if (! $endDate) {
                $endDate = TimezoneService::now()->endOfMonth()->format('Y-m-d');
            }
            $page = (int) $request->get('page', 1);
            $perPage = (int) $request->get('per_page', 10);

            // Build query
            $query = TimeTracking::with('user')
                ->where('company_id', $user->company_id)
                ->whereBetween('date', [$startDate, $endDate]);

            // Filter by employee if specified
            if ($employeeId !== 'all') {
                $query->where('user_id', $employeeId);
            }

            // Order by date descending, then by created_at
            $query->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc');

            // Get total count
            $total = $query->count();

            // Get paginated results
            $records = $query->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get()
                ->map(function ($record) {
                    // Format time_in
                    $timeIn = '--';
                    if ($record->time_in) {
                        $timeIn = is_string($record->time_in)
                            ? TimezoneService::toCompanyTimezone(Carbon::createFromFormat('H:i:s', $record->time_in))->format('h:i A')
                            : TimezoneService::toCompanyTimezone($record->time_in)->format('h:i A');
                    }

                    // Format time_out
                    $timeOut = '--';
                    if ($record->time_out) {
                        $timeOut = is_string($record->time_out)
                            ? TimezoneService::toCompanyTimezone(Carbon::createFromFormat('H:i:s', $record->time_out))->format('h:i A')
                            : TimezoneService::toCompanyTimezone($record->time_out)->format('h:i A');
                    }

                    // Calculate total hours
                    $totalHours = '0';
                    if ($record->hours_worked && $record->hours_worked > 0) {
                        // hours_worked is in seconds
                        $hours = floor($record->hours_worked / 3600);
                        $minutes = floor(($record->hours_worked % 3600) / 60);
                        $totalHours = $hours + ($minutes / 60);
                        $totalHours = number_format($totalHours, 2);
                    } elseif ($record->time_in && $record->time_out) {
                        // Calculate from time_in and time_out if hours_worked is missing
                        $timeInStr = is_string($record->time_in) ? $record->time_in : $record->time_in->format('H:i:s');
                        $timeOutStr = is_string($record->time_out) ? $record->time_out : $record->time_out->format('H:i:s');
                        $dateStr = $record->date instanceof \Carbon\Carbon ? $record->date->format('Y-m-d') : $record->date;

                        $timeInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeInStr);
                        $timeOutDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeOutStr);

                        if ($timeOutDateTime->lessThan($timeInDateTime)) {
                            $timeOutDateTime->addDay();
                        }

                        $hoursWorkedSeconds = max(0, $timeOutDateTime->timestamp - $timeInDateTime->timestamp);
                        $hours = floor($hoursWorkedSeconds / 3600);
                        $minutes = floor(($hoursWorkedSeconds % 3600) / 60);
                        $totalHours = $hours + ($minutes / 60);
                        $totalHours = number_format($totalHours, 2);
                    }

                    // Break duration - not in database, defaulting to 1 hour or calculated
                    $breakDuration = '01:00'; // Default 1 hour break
                    // Could be calculated as: if total hours > 6, then 1 hour break, else 0.5 hours

                    // Get user initials
                    $user = $record->user;
                    $initials = 'N/A';
                    if ($user) {
                        $nameParts = explode(' ', $user->name);
                        if (count($nameParts) >= 2) {
                            $initials = strtoupper(substr($nameParts[0], 0, 1).substr($nameParts[1], 0, 1));
                        } else {
                            $initials = strtoupper(substr($user->name, 0, 2));
                        }
                    }

                    return [
                        'id' => $record->id,
                        'employee' => [
                            'id' => $user->id ?? null,
                            'name' => $user->name ?? 'Unknown',
                            'initials' => $initials,
                        ],
                        'date' => $record->date instanceof \Carbon\Carbon
                            ? $record->date->format('M d, Y')
                            : Carbon::parse($record->date)->format('M d, Y'),
                        'timeIn' => $timeIn,
                        'timeOut' => $timeOut,
                        'breakDuration' => $breakDuration,
                        'totalHours' => $totalHours,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $records,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get employees for the filter dropdown.
     */
    public function getEmployees(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $employees = User::where('company_id', $user->company_id)
                ->where('status', 'active')
                ->orderBy('name')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $employees,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get clients for payroll report filters.
     */
    public function getClients(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $clients = Client::where('company_id', $user->company_id)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(function ($client) {
                    return [
                        'id' => $client->id,
                        'name' => $client->name,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $clients,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update time tracking record.
     */
    public function updateTimeTrackingRecord(Request $request, $id)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'time_in' => 'nullable|date_format:H:i:s',
                'time_out' => 'nullable|date_format:H:i:s',
                'time_out_date' => 'nullable|date',
                'reason' => 'nullable|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Find the record
            $record = TimeTracking::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (! $record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Time tracking record not found.',
                ], 404);
            }

            // Store old values for history
            $oldTimeIn = $record->time_in;
            $oldTimeOut = $record->time_out;
            $oldHoursWorked = $record->hours_worked;

            // Update time in/out if provided
            $newTimeIn = $request->has('time_in') && $request->time_in ? $request->time_in : $record->time_in;
            $newTimeOut = $request->has('time_out') && $request->time_out ? $request->time_out : $record->time_out;

            // Get time out date (use provided date or default to record's date)
            $timeOutDate = $request->has('time_out_date') && $request->time_out_date
                ? Carbon::parse($request->time_out_date)->format('Y-m-d')
                : ($record->date instanceof \Carbon\Carbon ? $record->date->format('Y-m-d') : $record->date);

            // Calculate new hours worked if both times are provided
            $newHoursWorked = $oldHoursWorked;
            if ($newTimeIn && $newTimeOut) {
                $timeInStr = is_string($newTimeIn) ? $newTimeIn : Carbon::parse($newTimeIn)->format('H:i:s');
                $timeOutStr = is_string($newTimeOut) ? $newTimeOut : Carbon::parse($newTimeOut)->format('H:i:s');
                $dateStr = $record->date instanceof \Carbon\Carbon ? $record->date->format('Y-m-d') : $record->date;

                $timeInDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr.' '.$timeInStr);
                $timeOutDateTime = Carbon::createFromFormat('Y-m-d H:i:s', $timeOutDate.' '.$timeOutStr);

                // Handle edge case where time_out might be on next day (shouldn't happen if time_out_date is correct, but keep as fallback)
                if ($timeOutDateTime->lessThan($timeInDateTime)) {
                    $timeOutDateTime->addDay();
                }

                $newHoursWorked = max(0, $timeOutDateTime->timestamp - $timeInDateTime->timestamp);
            }

            // Update the record
            $record->update([
                'time_in' => $newTimeIn,
                'time_out' => $newTimeOut,
                'hours_worked' => $newHoursWorked,
            ]);

            // Create edit history if values changed
            if ($oldTimeIn != $newTimeIn || $oldTimeOut != $newTimeOut || $oldHoursWorked != $newHoursWorked) {
                TimeTrackingEditHistory::create([
                    'time_tracking_record_id' => $record->id,
                    'edited_by' => $user->id,
                    'old_time_in' => $oldTimeIn,
                    'new_time_in' => $newTimeIn,
                    'old_time_out' => $oldTimeOut,
                    'new_time_out' => $newTimeOut,
                    'old_hours_worked' => $oldHoursWorked,
                    'new_hours_worked' => $newHoursWorked,
                    'reason' => $request->reason,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Time tracking record updated successfully.',
                'data' => [
                    'id' => $record->id,
                    'time_in' => $newTimeIn ? (is_string($newTimeIn) ? Carbon::createFromFormat('H:i:s', $newTimeIn)->format('h:i A') : Carbon::parse($newTimeIn)->format('h:i A')) : '--',
                    'time_out' => $newTimeOut ? (is_string($newTimeOut) ? Carbon::createFromFormat('H:i:s', $newTimeOut)->format('h:i A') : Carbon::parse($newTimeOut)->format('h:i A')) : '--',
                    'total_hours' => $newHoursWorked > 0 ? number_format($newHoursWorked / 3600, 2) : '0',
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get edit history for a time tracking record.
     */
    public function getEditHistory($id)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            // Verify the record belongs to the user's company
            $record = TimeTracking::where('id', $id)
                ->where('company_id', $user->company_id)
                ->first();

            if (! $record) {
                return response()->json([
                    'success' => false,
                    'message' => 'Time tracking record not found.',
                ], 404);
            }

            $history = TimeTrackingEditHistory::where('time_tracking_record_id', $id)
                ->with('editedByUser')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'edited_by' => $item->editedByUser ? $item->editedByUser->name : 'Unknown',
                        'edited_at' => $item->created_at->format('M d, Y h:i A'),
                        'old_time_in' => $item->old_time_in ? Carbon::createFromFormat('H:i:s', $item->old_time_in)->format('h:i A') : '--',
                        'new_time_in' => $item->new_time_in ? Carbon::createFromFormat('H:i:s', $item->new_time_in)->format('h:i A') : '--',
                        'old_time_out' => $item->old_time_out ? Carbon::createFromFormat('H:i:s', $item->old_time_out)->format('h:i A') : '--',
                        'new_time_out' => $item->new_time_out ? Carbon::createFromFormat('H:i:s', $item->new_time_out)->format('h:i A') : '--',
                        'old_hours' => $item->old_hours_worked ? number_format($item->old_hours_worked / 3600, 2) : '0',
                        'new_hours' => $item->new_hours_worked ? number_format($item->new_hours_worked / 3600, 2) : '0',
                        'reason' => $item->reason,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get salary computation for the company.
     */
    public function getSalaryComputation(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            // Get filters - default to current month if not provided
            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            if (! $startDate) {
                $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            }
            if (! $endDate) {
                $endDate = Carbon::now()->endOfMonth()->format('Y-m-d');
            }

            // Get all active employees for the company
            $employees = User::where('company_id', $user->company_id)
                ->where('status', 'active')
                ->whereNotNull('salary')
                ->get();

            $salaryComputation = [];
            $totalGrossPay = 0;
            $totalDeductions = 0;

            foreach ($employees as $employee) {
                // Get total hours worked from time tracking records (stored in seconds, convert to hours)
                $totalHoursWorkedInSeconds = TimeTracking::where('user_id', $employee->id)
                    ->whereBetween('date', [$startDate, $endDate])
                    ->whereNotNull('hours_worked')
                    ->where('hours_worked', '>', 0)
                    ->sum('hours_worked');

                // Convert seconds to hours (3600 seconds = 1 hour)
                $totalHoursWorked = $totalHoursWorkedInSeconds / 3600;

                // Check if there's a saved salary computation for this period
                $savedComputation = SalaryComputation::where('user_id', $employee->id)
                    ->where('company_id', $user->company_id)
                    ->where('period_start_date', $startDate)
                    ->where('period_end_date', $endDate)
                    ->first();

                // Use saved required hours if they exist, otherwise use default
                $standardMonthlyHours = 160;
                $requiredHours = $savedComputation ? $savedComputation->required_hours : $standardMonthlyHours;

                // Calculate proportional base salary: base_salary * (hours_worked / required_hours)
                $fullBaseSalary = $employee->salary ?? 0;
                $proportionalBaseSalary = $fullBaseSalary * ($totalHoursWorked / $requiredHours);

                // Calculate overtime hours (only hours beyond required hours)
                $overtimeHours = max(0, $totalHoursWorked - $requiredHours);

                // Calculate hourly rate and overtime pay
                $hourlyRate = $fullBaseSalary > 0 ? ($fullBaseSalary / $requiredHours) : 0;
                $overtimeRate = $hourlyRate * 1.5; // 1.5x for overtime
                $overtimePay = $overtimeHours * $overtimeRate;

                // Always use allowances from users database
                $allowances = floatval($employee->allowances ?? 0);

                // Gross pay = proportional base salary + overtime pay + allowances
                $grossPay = $proportionalBaseSalary + $overtimePay + $allowances;

                // Calculate deductions: use saved if exists and > 0, otherwise calculate default 15%

                if ($savedComputation && $savedComputation->deductions !== null && $savedComputation->deductions > 0) {
                    $finalDeductions = $savedComputation->deductions;
                } else {
                    $finalDeductions = 0;
                }

                // Net pay = gross pay - deductions
                $netPay = $grossPay - $finalDeductions;

                $totalGrossPay += $grossPay;
                $totalDeductions += $finalDeductions;

                // Get user initials
                $nameParts = explode(' ', $employee->name);
                $initials = 'N/A';
                if (count($nameParts) >= 2) {
                    $initials = strtoupper(substr($nameParts[0], 0, 1).substr($nameParts[1], 0, 1));
                } else {
                    $initials = strtoupper(substr($employee->name, 0, 2));
                }

                $salaryComputation[] = [
                    'id' => $employee->id,
                    'computation_id' => $savedComputation ? $savedComputation->id : null,
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'initials' => $initials,
                    ],
                    'fullBaseSalary' => number_format($fullBaseSalary, 2, '.', ''), // Full monthly salary
                    'baseSalary' => number_format($proportionalBaseSalary, 2, '.', ''), // Proportional base salary
                    'hoursWorked' => round($totalHoursWorked, 1),
                    'requiredHours' => round($requiredHours, 1),
                    'overtime' => round($overtimeHours, 1),
                    'allowances' => number_format($allowances, 2, '.', ''),
                    'grossPay' => number_format($grossPay, 2, '.', ''),
                    'deductions' => number_format($finalDeductions, 2, '.', ''),
                    'deductionDetails' => $savedComputation ? $savedComputation->deduction_details : null,
                    'netPay' => number_format($netPay, 2, '.', ''),
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $salaryComputation,
                'summary' => [
                    'totalEmployees' => count($salaryComputation),
                    'totalGrossPay' => number_format($totalGrossPay, 2, '.', ''),
                    'totalDeductions' => number_format($totalDeductions, 2, '.', ''),
                    'totalNetPay' => number_format($totalGrossPay - $totalDeductions, 2, '.', ''),
                ],
                'dateRange' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save or update salary computation.
     */
    public function saveSalaryComputation(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date',
                'required_hours' => 'nullable|numeric|min:0|max:999',
                'deductions' => 'nullable|numeric|min:0',
                'deduction_details' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Verify the employee belongs to the user's company
            $employee = User::where('id', $request->user_id)
                ->where('company_id', $user->company_id)
                ->first();

            if (! $employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.',
                ], 404);
            }

            // Get or create salary computation
            $computation = SalaryComputation::where('user_id', $request->user_id)
                ->where('company_id', $user->company_id)
                ->where('period_start_date', $request->start_date)
                ->where('period_end_date', $request->end_date)
                ->first();

            // Get total hours worked from time tracking records (stored in seconds, convert to hours)
            $totalHoursWorkedInSeconds = TimeTracking::where('user_id', $request->user_id)
                ->whereBetween('date', [$request->start_date, $request->end_date])
                ->whereNotNull('hours_worked')
                ->where('hours_worked', '>', 0)
                ->sum('hours_worked');

            // Convert seconds to hours (3600 seconds = 1 hour)
            $totalHoursWorked = $totalHoursWorkedInSeconds / 3600;
            $requiredHours = $request->required_hours ?? 160;

            // Calculate proportional base salary: base_salary * (hours_worked / required_hours)
            $fullBaseSalary = $employee->salary ?? 0;
            $proportionalBaseSalary = $fullBaseSalary * ($totalHoursWorked / $requiredHours);

            // Calculate overtime hours (only hours beyond required hours)
            $overtimeHours = max(0, $totalHoursWorked - $requiredHours);

            // Calculate hourly rate and overtime pay
            $hourlyRate = $fullBaseSalary > 0 ? ($fullBaseSalary / $requiredHours) : 0;
            $overtimeRate = $hourlyRate * 1.5;
            $overtimePay = $overtimeHours * $overtimeRate;
            $allowances = $computation ? $computation->allowances : 0;

            // Gross pay = proportional base salary + overtime pay + allowances
            $grossPay = $proportionalBaseSalary + $overtimePay + $allowances;
            $deductions = $request->deductions ?? ($grossPay * 0.15);

            // Net pay = gross pay - deductions
            $netPay = $grossPay - $deductions;

            // Store old values for history
            $oldValues = null;
            if ($computation) {
                $oldValues = [
                    'required_hours' => $computation->required_hours,
                    'deductions' => $computation->deductions,
                    'deduction_details' => $computation->deduction_details,
                    'gross_pay' => $computation->gross_pay,
                    'net_pay' => $computation->net_pay,
                ];
            }

            // Create or update computation
            $computationData = [
                'user_id' => $request->user_id,
                'company_id' => $user->company_id,
                'period_start_date' => $request->start_date,
                'period_end_date' => $request->end_date,
                'base_salary' => $proportionalBaseSalary,
                'hours_worked' => $totalHoursWorked,
                'required_hours' => $requiredHours,
                'overtime_hours' => $overtimeHours,
                'allowances' => $allowances,
                'deductions' => $deductions,
                'deduction_details' => $request->deduction_details,
                'gross_pay' => $grossPay,
                'net_pay' => $netPay,
            ];

            if ($computation) {
                $computation->update($computationData);
            } else {
                $computation = SalaryComputation::create($computationData);
            }

            // Create history if values changed
            if ($oldValues && (
                $oldValues['required_hours'] != $requiredHours ||
                $oldValues['deductions'] != $deductions ||
                $oldValues['deduction_details'] != $request->deduction_details
            )) {
                SalaryComputationHistory::create([
                    'salary_computation_id' => $computation->id,
                    'edited_by' => $user->id,
                    'old_required_hours' => $oldValues['required_hours'],
                    'new_required_hours' => $requiredHours,
                    'old_deductions' => $oldValues['deductions'],
                    'new_deductions' => $deductions,
                    'old_deduction_details' => $oldValues['deduction_details'],
                    'new_deduction_details' => $request->deduction_details,
                    'old_gross_pay' => $oldValues['gross_pay'],
                    'new_gross_pay' => $grossPay,
                    'old_net_pay' => $oldValues['net_pay'],
                    'new_net_pay' => $netPay,
                    'reason' => $request->reason,
                ]);
            } elseif (! $oldValues) {
                // First save - create history entry
                SalaryComputationHistory::create([
                    'salary_computation_id' => $computation->id,
                    'edited_by' => $user->id,
                    'old_required_hours' => null,
                    'new_required_hours' => $requiredHours,
                    'old_deductions' => null,
                    'new_deductions' => $deductions,
                    'old_deduction_details' => null,
                    'new_deduction_details' => $request->deduction_details,
                    'old_gross_pay' => null,
                    'new_gross_pay' => $grossPay,
                    'old_net_pay' => null,
                    'new_net_pay' => $netPay,
                    'reason' => $request->reason ?? 'Initial save',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Salary computation saved successfully.',
                'data' => [
                    'id' => $computation->id,
                    'gross_pay' => number_format($grossPay, 2, '.', ''),
                    'deductions' => number_format($deductions, 2, '.', ''),
                    'net_pay' => number_format($netPay, 2, '.', ''),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get salary computation history.
     */
    public function getSalaryComputationHistory(Request $request, $userId)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $startDate = $request->get('start_date');
            $endDate = $request->get('end_date');

            if (! $startDate || ! $endDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Start date and end date are required.',
                ], 422);
            }

            // Verify the employee belongs to the user's company
            $employee = User::where('id', $userId)
                ->where('company_id', $user->company_id)
                ->first();

            if (! $employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found.',
                ], 404);
            }

            // Get salary computation for this period
            $computation = SalaryComputation::where('user_id', $userId)
                ->where('company_id', $user->company_id)
                ->where('period_start_date', $startDate)
                ->where('period_end_date', $endDate)
                ->first();

            if (! $computation) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                ]);
            }

            $history = SalaryComputationHistory::where('salary_computation_id', $computation->id)
                ->with('editedByUser')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'edited_by' => $item->editedByUser ? $item->editedByUser->name : 'Unknown',
                        'edited_at' => $item->created_at->format('M d, Y h:i A'),
                        'old_required_hours' => $item->old_required_hours ? number_format($item->old_required_hours, 1) : '--',
                        'new_required_hours' => $item->new_required_hours ? number_format($item->new_required_hours, 1) : '--',
                        'old_deductions' => $item->old_deductions ? number_format($item->old_deductions, 2) : '--',
                        'new_deductions' => $item->new_deductions ? number_format($item->new_deductions, 2) : '--',
                        'old_gross_pay' => $item->old_gross_pay ? number_format($item->old_gross_pay, 2) : '--',
                        'new_gross_pay' => $item->new_gross_pay ? number_format($item->new_gross_pay, 2) : '--',
                        'old_net_pay' => $item->old_net_pay ? number_format($item->old_net_pay, 2) : '--',
                        'new_net_pay' => $item->new_net_pay ? number_format($item->new_net_pay, 2) : '--',
                        'reason' => $item->reason,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all saved salary computations for the current month.
     */
    public function getSavedComputations(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

            // Get all saved computations for the company within the date range
            $computations = SalaryComputation::where('company_id', $user->company_id)
                ->whereBetween('period_start_date', [$startDate, $endDate])
                ->where('period_end_date', '<=', $endDate)
                ->with(['user', 'editHistory' => function ($query) {
                    $query->latest()->limit(1)->with('editedByUser');
                }])
                ->orderBy('period_start_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($computation) {
                    // Get user initials
                    $nameParts = explode(' ', $computation->user->name ?? '');
                    $initials = 'N/A';
                    if (count($nameParts) >= 2) {
                        $initials = strtoupper(substr($nameParts[0], 0, 1).substr($nameParts[1], 0, 1));
                    } else {
                        $initials = strtoupper(substr($computation->user->name ?? '', 0, 2));
                    }

                    return [
                        'id' => $computation->id,
                        'employee' => [
                            'id' => $computation->user_id,
                            'name' => $computation->user->name ?? 'Unknown',
                            'initials' => $initials,
                        ],
                        'period_start_date' => $computation->period_start_date->format('M d, Y'),
                        'period_end_date' => $computation->period_end_date->format('M d, Y'),
                        'full_base_salary' => number_format($computation->user->salary ?? $computation->base_salary, 2, '.', ''),
                        'hours_worked' => round($computation->hours_worked, 1),
                        'required_hours' => round($computation->required_hours, 1),
                        'overtime_hours' => round($computation->overtime_hours, 1),
                        'allowances' => number_format($computation->allowances, 2, '.', ''),
                        'gross_pay' => number_format($computation->gross_pay, 2, '.', ''),
                        'deductions' => number_format($computation->deductions, 2, '.', ''),
                        'deduction_details' => $computation->deduction_details,
                        'net_pay' => number_format($computation->net_pay, 2, '.', ''),
                        'status' => $computation->status,
                        'created_at' => $computation->created_at->format('M d, Y h:i A'),
                        'updated_at' => $computation->updated_at->format('M d, Y h:i A'),
                        'last_edited_by' => $computation->editHistory->first()?->editedByUser->name ?? null,
                        'last_edited_at' => $computation->editHistory->first()?->created_at->format('M d, Y h:i A') ?? null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $computations,
                'dateRange' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payroll report data.
     */
    public function getPayrollReport(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
            $persistedOnly = filter_var($request->get('persisted_only', false), FILTER_VALIDATE_BOOLEAN);

            // Get all employees in the company with their assigned clients (includes admins)
            $employees = User::where('company_id', $user->company_id)
                ->with('clients', 'salesRep')
                ->orderBy('name')
                ->get();

            $reportData = [];
            $totalGrossPay = 0;
            $totalDeductions = 0;
            $totalNetPay = 0;
            $pnlTotals = [
                'total_salary_cost' => 0.0,
                'total_client_invoice' => 0.0,
                'total_commission' => 0.0,
                'total_margin' => 0.0,
            ];

            $userWeekHours = $persistedOnly ? [] : $this->aggregateTimeTrackingHoursByUserAndWeek(
                (int) $user->company_id,
                $startDate,
                $endDate
            );

            $periodInvoice = PayrollPeriodInvoice::where('company_id', $user->company_id)
                ->where('period_start_date', $startDate)
                ->where('period_end_date', $endDate)
                ->first();

            $periodInvoiceForStatus = $periodInvoice;

            $convertedEmployeeIdsFromMapping = [];
            if ($periodInvoice && ! $persistedOnly) {
                foreach (array_keys((array) ($periodInvoice->employee_invoice_mapping ?? [])) as $empIdKey) {
                    $employeeIdFromMapping = (int) $empIdKey;
                    if ($employeeIdFromMapping > 0) {
                        $convertedEmployeeIdsFromMapping[$employeeIdFromMapping] = true;
                    }
                }
            }

            $convertedClientsByEmployee = $this->convertedClientsByEmployeeFromPeriod($periodInvoice);

            // Persisted fallback source for P&L: generated payroll snapshots saved on conversion.
            $persistedPeriodDetailsByEmployee = [];
            if ($persistedOnly) {
                $periodInvoices = PayrollPeriodInvoice::query()
                    ->where('company_id', $user->company_id)
                    ->whereDate('period_start_date', '<=', $endDate)
                    ->whereDate('period_end_date', '>=', $startDate)
                    ->get(['conversion_details']);

                foreach ($periodInvoices as $persistedPeriodInvoice) {
                    $details = (array) ($persistedPeriodInvoice->conversion_details ?? []);
                    $employeeIds = [];
                    foreach (array_keys($details) as $detailKey) {
                        $parsed = $this->conversionDetailsService()->parseKey((string) $detailKey);
                        if ($parsed['employee_id'] > 0) {
                            $employeeIds[$parsed['employee_id']] = true;
                        }
                    }
                    foreach (array_keys($employeeIds) as $eid) {
                        if (! isset($persistedPeriodDetailsByEmployee[$eid])) {
                            $persistedPeriodDetailsByEmployee[$eid] = ['net_pay' => 0.0, 'hours_worked' => 0.0];
                        }
                        $persistedPeriodDetailsByEmployee[$eid]['net_pay'] += $this->conversionDetailsService()->sumForEmployee($details, (int) $eid, 'net_pay');
                        $persistedPeriodDetailsByEmployee[$eid]['hours_worked'] += $this->conversionDetailsService()->sumForEmployee($details, (int) $eid, 'hours_worked');
                    }
                }
            }

            foreach ($employees as $employee) {
                $monthlyBaseSalary = (float) ($employee->salary ?? 0);
                $hoursWorkedSeconds = (int) TimeTracking::where('user_id', $employee->id)
                    ->where('company_id', $user->company_id)
                    ->whereBetween('date', [
                        Carbon::parse($startDate)->format('Y-m-d'),
                        Carbon::parse($endDate)->format('Y-m-d'),
                    ])
                    ->whereNotNull('hours_worked')
                    ->where('hours_worked', '>', 0)
                    ->sum('hours_worked');

                $savedComputation = SalaryComputation::where('user_id', $employee->id)
                    ->where('company_id', $user->company_id)
                    ->where('period_start_date', $startDate)
                    ->where('period_end_date', $endDate)
                    ->first();

                $hoursWorkedForPnl = 0.0;

                if ($savedComputation) {
                    // Prefer persisted computation for pay figures for this period.
                    $employeeRequiredHours = (float) ($savedComputation->required_hours ?? 160);
                    $overtimeHours = round((float) ($savedComputation->overtime_hours ?? 0), 1);
                    $allowances = (float) ($savedComputation->allowances ?? 0);
                    $grossPay = (float) ($savedComputation->gross_pay ?? 0);
                    $deductions = (float) ($savedComputation->deductions ?? 0);
                    $netPay = (float) ($savedComputation->net_pay ?? 0);
                    $hoursWorkedForPnl = (float) ($savedComputation->hours_worked ?? 0);
                    // Hours column: actual tracked time (seconds → hours); fallback to saved snapshot if no tracking.
                    $hoursWorked = $hoursWorkedSeconds > 0
                        ? round($hoursWorkedSeconds / 3600, 6)
                        : (float) ($savedComputation->hours_worked ?? 0);
                } elseif ($persistedOnly) {
                    // P&L path can request persisted-only mode.
                    $fallbackDetail = $persistedPeriodDetailsByEmployee[(int) $employee->id] ?? ['net_pay' => 0.0, 'hours_worked' => 0.0];
                    $hoursWorked = (float) ($fallbackDetail['hours_worked'] ?? 0);
                    $employeeRequiredHours = (float) (($employee->required_work_hours !== null && $employee->required_work_hours > 0)
                        ? $employee->required_work_hours
                        : 160);
                    $overtimeHours = 0.0;
                    $allowances = 0.0;
                    $grossPay = (float) ($fallbackDetail['net_pay'] ?? 0);
                    $deductions = 0.0;
                    $netPay = (float) ($fallbackDetail['net_pay'] ?? 0);
                    $hoursWorkedForPnl = (float) ($fallbackDetail['hours_worked'] ?? 0);
                } else {
                    // Payroll report: compute pay from time tracking + monthly base salary.
                    $employeeRequiredHours = ($employee->required_work_hours !== null && $employee->required_work_hours > 0)
                        ? (float) $employee->required_work_hours
                        : 160.0;

                    $hoursWorked = $hoursWorkedSeconds > 0 ? ($hoursWorkedSeconds / 3600) : 0.0;
                    $hoursWorkedForPnl = $hoursWorked;

                    if ($hoursWorked <= 0 || $employeeRequiredHours <= 0) {
                        $proportionalBaseSalary = 0.0;
                    } else {
                        $proportionalBaseSalary = ($monthlyBaseSalary * $hoursWorked) / $employeeRequiredHours;
                    }

                    $overtimeHours = max(0, $hoursWorked - $employeeRequiredHours);
                    $hourlyRate = ($monthlyBaseSalary > 0 && $employeeRequiredHours > 0) ? ($monthlyBaseSalary / $employeeRequiredHours) : 0.0;
                    $overtimeRate = $hourlyRate * 1.5;
                    $overtimePay = $overtimeHours * $overtimeRate;
                    $allowances = (float) ($employee->allowances ?? 0);
                    $grossPay = $proportionalBaseSalary + $overtimePay + $allowances;
                    $deductions = 0.0;
                    $netPay = $grossPay - $deductions;
                }

                if ($persistedOnly && ! $savedComputation) {
                    $hoursWorkedSeconds = 0;
                }

                $employeeId = (int) $employee->id;
                $assignedClientIds = array_map('intval', $employee->clients->pluck('id')->all());
                $convertedClientIds = $convertedClientsByEmployee[(string) $employeeId] ?? [];
                $isFullyConverted = $assignedClientIds !== []
                    && $convertedClientIds !== []
                    && array_diff($assignedClientIds, $convertedClientIds) === [];

                $netPayFromConversion = false;
                if (! $persistedOnly && isset($convertedEmployeeIdsFromMapping[$employeeId]) && $isFullyConverted) {
                    $conversionDetails = (array) ($periodInvoice->conversion_details ?? []);
                    if ($this->conversionDetailsService()->employeeHasAnyDetail($conversionDetails, $employeeId)) {
                        $netPay = round(
                            $this->conversionDetailsService()->sumForEmployee($conversionDetails, $employeeId, 'net_pay'),
                            2
                        );
                        $netPayFromConversion = true;
                    }
                }

                // Get client names, IDs, and invoice amount
                $clientNames = $employee->clients->pluck('name')->join(', ') ?: '—';
                $clientIds = $employee->clients->pluck('id')->toArray();
                $clientInvoiceAmount = floatval($employee->client_invoice_amount ?? 0);

                $pnlLine = $this->calculatePayrollPnlLine(
                    $hoursWorkedForPnl,
                    $employeeRequiredHours,
                    $clientInvoiceAmount,
                    $employee->sales_rep_commission_type,
                    $employee->sales_rep_commission_value !== null ? (float) $employee->sales_rep_commission_value : null,
                    (float) $grossPay
                );

                // Payroll report commission is based on Bill Amount (client invoice amount),
                // not on prorated hours.
                $reportCommission = 0.0;
                $commissionType = $employee->sales_rep_commission_type;
                $commissionValue = $employee->sales_rep_commission_value !== null ? (float) $employee->sales_rep_commission_value : null;
                if ($commissionType && $commissionValue !== null) {
                    if ($commissionType === 'percent') {
                        $reportCommission = round($clientInvoiceAmount * ($commissionValue / 100), 2);
                    } elseif ($commissionType === 'usd') {
                        $reportCommission = round($commissionValue, 2);
                    }
                }

                $pnlTotals['total_salary_cost'] += $pnlLine['pnl_salary_cost'];
                $pnlTotals['total_client_invoice'] += $pnlLine['pnl_client_invoice'];
                $pnlTotals['total_commission'] += $reportCommission;
                $pnlTotals['total_margin'] += $pnlLine['pnl_margin'];

                $hoursByWeekForEmployee = [];
                foreach ($userWeekHours[(int) $employee->id] ?? [] as $weekKey => $weekHours) {
                    if ($weekHours > 0) {
                        $hoursByWeekForEmployee[$weekKey] = $weekHours;
                    }
                }

                // Add employee to report data (base_salary = monthly salary from profile; hours from time tracking seconds)
                $reportData[] = [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'sales_rep_id' => $employee->sales_rep_id,
                    'sales_rep_name' => $employee->salesRep?->name,
                    'department' => $employee->department ?? 'N/A',
                    'clients' => $clientNames,
                    'client_ids' => $clientIds,
                    'converted_client_ids' => $convertedClientsByEmployee[(string) $employeeId] ?? [],
                    'client_invoice_amount' => round($clientInvoiceAmount, 2),
                    'base_salary' => $monthlyBaseSalary,
                    'hours_worked' => round((float) $hoursWorked, 6),
                    'hours_worked_seconds' => $hoursWorkedSeconds,
                    'required_hours' => round($employeeRequiredHours, 1),
                    'overtime_hours' => round($overtimeHours, 1),
                    'allowances' => floatval($allowances),
                    'gross_pay' => round(floatval($grossPay), 2),
                    'deductions' => round(floatval($deductions), 2),
                    'net_pay' => round(floatval($netPay), 2),
                    'net_pay_from_conversion' => $netPayFromConversion,
                    'sales_rep_commission_type' => $employee->sales_rep_commission_type,
                    'sales_rep_commission_value' => $employee->sales_rep_commission_value !== null ? round((float) $employee->sales_rep_commission_value, 2) : null,
                    'pnl_salary_cost' => $pnlLine['pnl_salary_cost'],
                    'pnl_client_invoice' => $pnlLine['pnl_client_invoice'],
                    'pnl_commission' => $reportCommission,
                    'pnl_margin' => $pnlLine['pnl_margin'],
                    'hours_by_week' => empty($hoursByWeekForEmployee) ? new \stdClass : (object) $hoursByWeekForEmployee,
                ];

                $totalGrossPay += $grossPay;
                $totalDeductions += $deductions;
                $totalNetPay += $netPay;
            }

            $invoiceStatus = ['generated' => false];
            if ($periodInvoiceForStatus && ! $persistedOnly) {
                $periodInvoice = $periodInvoiceForStatus;
                $invoiceIds = $periodInvoice->invoice_ids ?? [];
                $employeeMapping = $periodInvoice->employee_invoice_mapping ?? [];
                $convertedIds = array_map('intval', $periodInvoice->converted_employee_ids ?? []);
                $mappingKeys = array_keys($employeeMapping);
                $hasValidKeys = ! empty($convertedIds) && ! empty(array_intersect($mappingKeys, $convertedIds));
                if ((empty($employeeMapping) || ! $hasValidKeys) && ! empty($invoiceIds) && ! empty($reportData)) {
                    $employeeMapping = [];
                    $items = InvoiceItem::whereIn('invoice_id', $invoiceIds)->get(['invoice_id', 'description']);
                    foreach ($items as $item) {
                        if (preg_match('/^Payroll - (.+?) \(\d{4}-\d{2}-\d{2}/', $item->description ?? '', $m)) {
                            $name = trim($m[1]);
                            foreach ($reportData as $emp) {
                                if (($emp['employee_name'] ?? '') === $name) {
                                    $empId = $emp['employee_id'];
                                    $employeeMapping[$empId] = $employeeMapping[$empId] ?? [];
                                    $employeeMapping[$empId][] = $item->invoice_id;
                                    break;
                                }
                            }
                        }
                    }
                }
                $invoiceStatusById = [];
                $invoiceClientById = [];
                if (! empty($invoiceIds)) {
                    $invoices = Invoice::whereIn('id', $invoiceIds)->get(['id', 'status', 'client_id']);
                    $invoiceStatusById = $invoices->keyBy('id')->map(fn ($i) => ucfirst(strtolower($i->status ?? 'draft')))->toArray();
                    $invoiceClientById = $invoices->keyBy('id')->map(fn ($i) => (int) $i->client_id)->toArray();
                }
                $employeeStatuses = [];
                foreach ($employeeMapping as $empId => $empInvoiceIds) {
                    $statuses = array_filter(array_map(fn ($invId) => $invoiceStatusById[$invId] ?? null, (array) $empInvoiceIds));
                    if (! empty($statuses)) {
                        $priority = ['Paid' => 3, 'Sent' => 2, 'Overdue' => 1, 'Draft' => 0];
                        usort($statuses, fn ($a, $b) => ($priority[$b] ?? 0) <=> ($priority[$a] ?? 0));
                        $employeeStatuses[(string) $empId] = $statuses[0];
                    }
                }

                // Compute per-employee client payment status: paid / unpaid / partial / not_invoiced
                $employeePaymentStatus = [];
                foreach ($employeeMapping as $empId => $empInvoiceIds) {
                    $statuses = array_filter(array_map(fn ($invId) => $invoiceStatusById[$invId] ?? null, (array) $empInvoiceIds));
                    if (empty($statuses)) {
                        $employeePaymentStatus[(string) $empId] = 'not_invoiced';
                    } else {
                        $paidCount = count(array_filter($statuses, fn ($s) => strtolower($s) === 'paid'));
                        $total = count($statuses);
                        if ($paidCount === $total) {
                            $employeePaymentStatus[(string) $empId] = 'paid';
                        } elseif ($paidCount === 0) {
                            $employeePaymentStatus[(string) $empId] = 'unpaid';
                        } else {
                            $employeePaymentStatus[(string) $empId] = 'partial';
                        }
                    }
                }

                // Build per-employee map of converted client IDs so the UI can offer
                // un-invoiced clients while preventing duplicate billing.
                $convertedClientsByEmployee = [];
                foreach ($employeeMapping as $empId => $empInvoiceIds) {
                    $clientIds = [];
                    foreach ((array) $empInvoiceIds as $invId) {
                        $cid = $invoiceClientById[$invId] ?? null;
                        if ($cid) {
                            $clientIds[] = (int) $cid;
                        }
                    }
                    if (! empty($clientIds)) {
                        $convertedClientsByEmployee[(string) $empId] = array_values(array_unique($clientIds));
                    }
                }

                // Annotate report rows with per-employee converted client IDs and client payment status.
                foreach ($reportData as &$row) {
                    $empKey = (string) ($row['employee_id'] ?? '');
                    $row['converted_client_ids'] = $convertedClientsByEmployee[$empKey] ?? [];
                    $row['client_payment_status'] = $employeePaymentStatus[$empKey] ?? 'not_invoiced';
                }
                unset($row);

                // An employee is "fully converted" (locked) only when every assigned
                // client already has an invoice for this period. Employees with any
                // un-invoiced client remain available for further conversion.
                $fullyConvertedEmployeeIds = [];
                foreach ($reportData as $row) {
                    $empId = (int) ($row['employee_id'] ?? 0);
                    if ($empId < 1) {
                        continue;
                    }
                    $assigned = array_map('intval', $row['client_ids'] ?? []);
                    $converted = $convertedClientsByEmployee[(string) $empId] ?? [];
                    if (! empty($assigned) && ! empty($converted) && empty(array_diff($assigned, $converted))) {
                        $fullyConvertedEmployeeIds[] = $empId;
                    }
                }

                $invoiceStatus = [
                    'generated' => true,
                    'generated_at' => $periodInvoice->created_at?->toIso8601String(),
                    'invoice_ids' => $invoiceIds,
                    'converted_employee_ids' => $fullyConvertedEmployeeIds,
                    'converted_clients_by_employee' => $convertedClientsByEmployee,
                    'employee_statuses' => $employeeStatuses,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $reportData,
                'summary' => [
                    'total_employees' => count($reportData),
                    'total_gross_pay' => floatval($totalGrossPay),
                    'total_deductions' => floatval($totalDeductions),
                    'total_net_pay' => floatval($totalNetPay),
                ],
                'pnl_summary' => [
                    'total_salary_cost' => round($pnlTotals['total_salary_cost'], 2),
                    'total_client_invoice' => round($pnlTotals['total_client_invoice'], 2),
                    'total_commission' => round($pnlTotals['total_commission'], 2),
                    'total_margin' => round($pnlTotals['total_margin'], 2),
                ],
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'invoice_status' => $invoiceStatus,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * P&L using actual invoice line totals in the date range: payroll-converted lines (description "Payroll - …")
     * plus all other billing line items. Billing is split across report employees when the invoice client
     * matches an employee’s assigned clients (equal split if several); otherwise the invoice creator is used
     * if they appear on the report; otherwise the amount is reported as billing_unallocated.
     */
    public function getPnlInvoiceBasis(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $payrollRequest = Request::create('/api/payroll/payroll-report', 'GET', array_merge(
                $request->query(),
                ['persisted_only' => 1]
            ));
            $prResponse = $this->getPayrollReport($payrollRequest);
            $payload = $prResponse->getData(true);
            if (! ($payload['success'] ?? false)) {
                return response()->json($payload, $prResponse->getStatusCode());
            }

            $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
            $companyId = (int) $user->company_id;
            $filterClientId = $request->filled('client_id') ? (int) $request->get('client_id') : null;
            if ($filterClientId !== null && $filterClientId < 1) {
                $filterClientId = null;
            }

            $salesRepFilter = $this->resolvePnlSalesRepEmployeeFilter($request, $payload['data']);
            $allowedEmployeeIds = $salesRepFilter['allowed_employee_ids'];

            $payrollLinePattern = '/^Payroll - (.+?) \(\d{4}-\d{2}-\d{2}/';

            $rows = $payload['data'];
            $placementMeta = [];
            foreach ($rows as $row) {
                $eid = (int) ($row['employee_id'] ?? 0);
                if ($eid < 1) {
                    continue;
                }
                $placementMeta[$eid] = [
                    'client_ids' => array_map('intval', $row['client_ids'] ?? []),
                ];
            }

            $employeeNamesById = [];
            $employeeNameToId = [];
            foreach ($rows as $row) {
                $eid = (int) ($row['employee_id'] ?? 0);
                $name = (string) ($row['employee_name'] ?? '');
                if ($eid > 0 && $name !== '') {
                    $employeeNamesById[$eid] = $name;
                    $employeeNameToId[$name] = $eid;
                }
            }

            $employeesByClientId = [];
            foreach ($placementMeta as $employeeId => $meta) {
                $name = (string) ($employeeNamesById[$employeeId] ?? '');
                if ($name === '') {
                    continue;
                }
                foreach ($meta['client_ids'] as $clientIdKey) {
                    $employeesByClientId[(int) $clientIdKey][$employeeId] = $name;
                }
            }

            $periodInvoices = PayrollPeriodInvoice::query()
                ->where('company_id', $companyId)
                ->whereDate('period_start_date', '<=', Carbon::parse($endDate)->toDateString())
                ->whereDate('period_end_date', '>=', Carbon::parse($startDate)->toDateString())
                ->get(['conversion_details', 'invoice_ids']);

            $periodByInvoiceId = [];
            foreach ($periodInvoices as $periodInvoice) {
                foreach ((array) ($periodInvoice->invoice_ids ?? []) as $invoiceId) {
                    $periodByInvoiceId[(int) $invoiceId] = $periodInvoice;
                }
            }

            $itemsQuery = InvoiceItem::query()
                ->join('invoices', 'invoice_items.invoice_id', '=', 'invoices.id')
                ->where('invoices.company_id', $companyId)
                ->whereBetween('invoices.invoice_date', [
                    Carbon::parse($startDate)->toDateString(),
                    Carbon::parse($endDate)->toDateString(),
                ]);

            if ($filterClientId) {
                $itemsQuery->where('invoices.client_id', $filterClientId);
            }

            $items = $itemsQuery->get([
                'invoice_items.description',
                'invoice_items.total',
                'invoice_items.net_pay',
                'invoices.id as invoice_id',
                'invoices.invoice_date',
                'invoices.client_id',
                'invoices.user_id',
                'invoices.status',
            ]);

            $payrollInvoiced = 0.0;
            $billingInvoiced = 0.0;
            $billingUnallocated = 0.0;
            $paidPayrollInvoiced = 0.0;
            $paidBillingInvoiced = 0.0;
            $paidBillingUnallocated = 0.0;
            $payrollByEmployeeName = [];
            $billingByEmployeeId = [];
            $paidPayrollByEmployeeName = [];
            $paidBillingByEmployeeId = [];
            $paidPayrollByClientId = [];
            $paidBillingByClientId = [];
            $payrollByClientId = [];
            $billingByClientId = [];
            $payrollByEmployeeClient = [];
            $billingByEmployeeClient = [];
            $paidPayrollByEmployeeClient = [];
            $paidBillingByEmployeeClient = [];
            $billingNetPayByEmployeeId = [];
            $billingNetPayByEmployeeClient = [];
            $weeklyPayrollColl = [];
            $weeklyBillingColl = [];
            $weeklyPaidPayrollColl = [];
            $weeklyPaidBillingColl = [];
            $weeklyNetPay = [];
            $weeklyCommission = [];
            $weeklyPaidNetPay = [];
            $weeklyPaidCommission = [];

            foreach ($items as $item) {
                $amount = round((float) $item->total, 2);
                $weekKey = Carbon::parse($item->invoice_date)->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
                $isPaidInvoice = strtolower((string) ($item->status ?? '')) === 'paid';
                $invoiceId = (int) ($item->invoice_id ?? 0);

                if (preg_match($payrollLinePattern, $item->description ?? '', $m)) {
                    $employeeName = trim($m[1]);
                    $empId = (int) ($employeeNameToId[$employeeName] ?? 0);
                    if (! $this->isPnlAllowedEmployee($allowedEmployeeIds, $empId)) {
                        continue;
                    }

                    $payrollInvoiced += $amount;
                    $clientId = (int) ($item->client_id ?? 0);
                    if ($clientId > 0) {
                        $payrollByClientId[$clientId] = round(($payrollByClientId[$clientId] ?? 0) + $amount, 2);
                        if ($empId > 0) {
                            $payrollByEmployeeClient[$empId][$clientId] = round(
                                ($payrollByEmployeeClient[$empId][$clientId] ?? 0) + $amount,
                                2
                            );
                        }
                    }
                    $payrollByEmployeeName[$employeeName] = ($payrollByEmployeeName[$employeeName] ?? 0) + $amount;
                    $weeklyPayrollColl[$weekKey] = round(($weeklyPayrollColl[$weekKey] ?? 0) + $amount, 2);
                    if ($isPaidInvoice) {
                        $paidPayrollInvoiced += $amount;
                        $paidPayrollByEmployeeName[$employeeName] = ($paidPayrollByEmployeeName[$employeeName] ?? 0) + $amount;
                        $weeklyPaidPayrollColl[$weekKey] = round(($weeklyPaidPayrollColl[$weekKey] ?? 0) + $amount, 2);
                        if ($clientId > 0) {
                            $paidPayrollByClientId[$clientId] = round(($paidPayrollByClientId[$clientId] ?? 0) + $amount, 2);
                            if ($empId > 0) {
                                $paidPayrollByEmployeeClient[$empId][$clientId] = round(
                                    ($paidPayrollByEmployeeClient[$empId][$clientId] ?? 0) + $amount,
                                    2
                                );
                            }
                        }
                    }

                    $this->accumulatePnlWeeklyPayrollCostsFromLine(
                        $periodByInvoiceId[$invoiceId] ?? null,
                        $empId,
                        $clientId,
                        $weekKey,
                        $isPaidInvoice,
                        $weeklyNetPay,
                        $weeklyCommission,
                        $weeklyPaidNetPay,
                        $weeklyPaidCommission,
                    );
                } else {
                    $clientId = (int) ($item->client_id ?? 0);
                    $lineNetPay = round((float) ($item->net_pay ?? 0), 2);
                    $splits = $this->allocatePnlBillingLineByDescription(
                        $amount,
                        $clientId,
                        (string) ($item->description ?? ''),
                        $employeesByClientId
                    );

                    if ($allowedEmployeeIds !== null) {
                        $allowedAmount = 0.0;
                        $allowedPaidAmount = 0.0;
                        foreach ($splits as $empId => $part) {
                            if ($empId !== 0 && ! isset($allowedEmployeeIds[$empId])) {
                                continue;
                            }
                            $part = round((float) $part, 2);
                            $allowedAmount = round($allowedAmount + $part, 2);
                            $billingByEmployeeId[$empId] = round(($billingByEmployeeId[$empId] ?? 0) + $part, 2);
                            if ($clientId > 0) {
                                $billingByEmployeeClient[$empId][$clientId] = round(
                                    ($billingByEmployeeClient[$empId][$clientId] ?? 0) + $part,
                                    2
                                );
                            }
                            if ($isPaidInvoice) {
                                $allowedPaidAmount = round($allowedPaidAmount + $part, 2);
                                $paidBillingByEmployeeId[$empId] = round(($paidBillingByEmployeeId[$empId] ?? 0) + $part, 2);
                                if ($clientId > 0) {
                                    $paidBillingByEmployeeClient[$empId][$clientId] = round(
                                        ($paidBillingByEmployeeClient[$empId][$clientId] ?? 0) + $part,
                                        2
                                    );
                                }
                            }
                        }

                        $this->accumulatePnlBillingNetPayFromLine(
                            $lineNetPay,
                            $amount,
                            $splits,
                            $clientId,
                            $allowedEmployeeIds,
                            $billingNetPayByEmployeeId,
                            $billingNetPayByEmployeeClient,
                            $weeklyNetPay,
                            $weeklyPaidNetPay,
                            $weekKey,
                            $isPaidInvoice,
                        );

                        if ($allowedAmount > 0) {
                            $billingInvoiced = round($billingInvoiced + $allowedAmount, 2);
                            $weeklyBillingColl[$weekKey] = round(($weeklyBillingColl[$weekKey] ?? 0) + $allowedAmount, 2);
                            if ($clientId > 0) {
                                $billingByClientId[$clientId] = round(
                                    ($billingByClientId[$clientId] ?? 0) + $allowedAmount,
                                    2
                                );
                            }
                            if ($isPaidInvoice && $allowedPaidAmount > 0) {
                                $paidBillingInvoiced = round($paidBillingInvoiced + $allowedPaidAmount, 2);
                                $weeklyPaidBillingColl[$weekKey] = round(($weeklyPaidBillingColl[$weekKey] ?? 0) + $allowedPaidAmount, 2);
                                if ($clientId > 0) {
                                    $paidBillingByClientId[$clientId] = round(
                                        ($paidBillingByClientId[$clientId] ?? 0) + $allowedPaidAmount,
                                        2
                                    );
                                }
                            }
                        }

                        continue;
                    }

                    $billingInvoiced += $amount;
                    $weeklyBillingColl[$weekKey] = round(($weeklyBillingColl[$weekKey] ?? 0) + $amount, 2);
                    if ($clientId > 0) {
                        $billingByClientId[$clientId] = round(($billingByClientId[$clientId] ?? 0) + $amount, 2);
                    }
                    if ($isPaidInvoice) {
                        $paidBillingInvoiced += $amount;
                        $weeklyPaidBillingColl[$weekKey] = round(($weeklyPaidBillingColl[$weekKey] ?? 0) + $amount, 2);
                        if ($clientId > 0) {
                            $paidBillingByClientId[$clientId] = round(($paidBillingByClientId[$clientId] ?? 0) + $amount, 2);
                        }
                    }

                    if ($splits === []) {
                        $billingUnallocated += $amount;
                        if ($isPaidInvoice) {
                            $paidBillingUnallocated += $amount;
                        }
                    } else {
                        foreach ($splits as $empId => $part) {
                            $billingByEmployeeId[$empId] = round(($billingByEmployeeId[$empId] ?? 0) + $part, 2);
                            if ($clientId > 0) {
                                $billingByEmployeeClient[$empId][$clientId] = round(
                                    ($billingByEmployeeClient[$empId][$clientId] ?? 0) + $part,
                                    2
                                );
                            }
                            if ($isPaidInvoice) {
                                $paidBillingByEmployeeId[$empId] = round(($paidBillingByEmployeeId[$empId] ?? 0) + $part, 2);
                                if ($clientId > 0) {
                                    $paidBillingByEmployeeClient[$empId][$clientId] = round(
                                        ($paidBillingByEmployeeClient[$empId][$clientId] ?? 0) + $part,
                                        2
                                    );
                                }
                            }
                        }

                        $this->accumulatePnlBillingNetPayFromLine(
                            $lineNetPay,
                            $amount,
                            $splits,
                            $clientId,
                            null,
                            $billingNetPayByEmployeeId,
                            $billingNetPayByEmployeeClient,
                            $weeklyNetPay,
                            $weeklyPaidNetPay,
                            $weekKey,
                            $isPaidInvoice,
                        );
                    }
                }
            }

            $payrollInvoiced = round($payrollInvoiced, 2);
            $billingInvoiced = round($billingInvoiced, 2);
            $billingUnallocated = round($billingUnallocated, 2);
            $paidPayrollInvoiced = round($paidPayrollInvoiced, 2);
            $paidBillingInvoiced = round($paidBillingInvoiced, 2);
            $paidBillingUnallocated = round($paidBillingUnallocated, 2);
            $totalCollections = round($payrollInvoiced + $billingInvoiced, 2);
            $paidTotalCollections = round($paidPayrollInvoiced + $paidBillingInvoiced, 2);
            $totalSalary = 0.0;
            $totalCommission = 0.0;

            // Net pay and commission from per-client conversion_details (not paid-invoice ratios).
            $netPayByEmployeeId = [];
            $netPayByClientId = [];
            $commissionByClientId = [];
            $commissionByEmployeeId = [];

            foreach ($periodInvoices as $periodInvoice) {
                foreach ((array) ($periodInvoice->conversion_details ?? []) as $detailKey => $detail) {
                    if (! is_array($detail)) {
                        continue;
                    }

                    $parsed = $this->conversionDetailsService()->parseKey((string) $detailKey);
                    $empId = $parsed['employee_id'];
                    $clientId = $parsed['client_id'] ?? (int) ($detail['client_id'] ?? 0);
                    if ($empId < 1 || $clientId < 1) {
                        continue;
                    }
                    if (! $this->isPnlAllowedEmployee($allowedEmployeeIds, $empId)) {
                        continue;
                    }
                    if ($filterClientId && $clientId !== $filterClientId) {
                        continue;
                    }

                    $netPay = round((float) ($detail['net_pay'] ?? 0), 2);
                    $hasPayrollInvoiced = round((float) ($payrollByEmployeeClient[$empId][$clientId] ?? 0), 2) > 0;
                    if ($netPay > 0 && $hasPayrollInvoiced) {
                        $netPayByClientId[$clientId] = round(($netPayByClientId[$clientId] ?? 0) + $netPay, 2);
                        $netPayByEmployeeId[$empId] = round(($netPayByEmployeeId[$empId] ?? 0) + $netPay, 2);
                    }

                    $storedCommission = round((float) ($detail['commission'] ?? 0), 2);
                    if ($storedCommission > 0 && $hasPayrollInvoiced) {
                        $commissionByClientId[$clientId] = round(
                            ($commissionByClientId[$clientId] ?? 0) + $storedCommission,
                            2
                        );
                        $commissionByEmployeeId[$empId] = round(
                            ($commissionByEmployeeId[$empId] ?? 0) + $storedCommission,
                            2
                        );
                    }
                }
            }

            $totalPaidNetPay = round(array_sum($netPayByEmployeeId), 2);

            foreach ($rows as $idx => $row) {
                $name = $row['employee_name'] ?? '';
                $empId = (int) ($row['employee_id'] ?? 0);
                $invoicedPayroll = round((float) ($payrollByEmployeeName[$name] ?? 0), 2);
                $invoicedBilling = round((float) ($billingByEmployeeId[$empId] ?? 0), 2);
                $invoicedTotal = round($invoicedPayroll + $invoicedBilling, 2);
                $paidPayroll = round((float) ($paidPayrollByEmployeeName[$name] ?? 0), 2);
                $paidBilling = round((float) ($paidBillingByEmployeeId[$empId] ?? 0), 2);
                $paidInvoicedTotal = round($paidPayroll + $paidBilling, 2);

                $commission = round((float) ($commissionByEmployeeId[$empId] ?? 0), 2);
                if ($invoicedPayroll <= 0) {
                    $commission = 0.0;
                }

                $employeeNetPay = round((float) ($netPayByEmployeeId[$empId] ?? 0), 2);
                if ($invoicedPayroll <= 0) {
                    $employeeNetPay = 0.0;
                }
                $employeeNetPay = round(
                    $employeeNetPay + (float) ($billingNetPayByEmployeeId[$empId] ?? 0),
                    2
                );
                $payrollCost = $employeeNetPay;
                $margin = round($invoicedTotal - $payrollCost, 2);

                $rows[$idx]['pnl_payroll_invoiced'] = $invoicedPayroll;
                $rows[$idx]['pnl_billing_invoiced'] = $invoicedBilling;
                $rows[$idx]['pnl_client_invoice'] = $invoicedTotal;
                $rows[$idx]['pnl_payroll_invoiced_paid'] = $paidPayroll;
                $rows[$idx]['pnl_billing_invoiced_paid'] = $paidBilling;
                $rows[$idx]['pnl_client_invoice_paid'] = $paidInvoicedTotal;
                $rows[$idx]['pnl_net_pay_paid'] = $employeeNetPay;
                $rows[$idx]['pnl_commission'] = $commission;
                $rows[$idx]['pnl_salary_cost'] = $payrollCost;
                $rows[$idx]['pnl_margin'] = $margin;

                $totalSalary += $payrollCost;
                $totalCommission += $commission;
            }

            if ($filterClientId || $allowedEmployeeIds !== null) {
                if ($filterClientId) {
                    $rows = array_values(array_filter(
                        $rows,
                        fn (array $row) => in_array($filterClientId, array_map('intval', $row['client_ids'] ?? []), true)
                    ));
                }
                if ($allowedEmployeeIds !== null) {
                    $rows = array_values(array_filter(
                        $rows,
                        fn (array $row) => isset($allowedEmployeeIds[(int) ($row['employee_id'] ?? 0)])
                    ));
                }
                $totalSalary = round(array_sum(array_map(
                    fn (array $row) => (float) ($row['pnl_salary_cost'] ?? 0),
                    $rows
                )), 2);
                $totalCommission = round(array_sum(array_map(
                    fn (array $row) => (float) ($row['pnl_commission'] ?? 0),
                    $rows
                )), 2);
                $totalPaidNetPay = round(array_sum(array_map(
                    fn (array $row) => (float) ($row['pnl_net_pay_paid'] ?? 0),
                    $rows
                )), 2);
            }

            $invoiceBucketNetPay = 0.0;
            $invoiceBillingTotal = 0.0;
            $invoiceBillingPaid = 0.0;
            if ($allowedEmployeeIds === null) {
                if ($filterClientId) {
                    $invoiceBucketNetPay = round((float) ($billingNetPayByEmployeeClient[0][$filterClientId] ?? 0), 2);
                    $invoiceBillingTotal = round((float) ($billingByEmployeeClient[0][$filterClientId] ?? 0), 2);
                    $invoiceBillingPaid = round((float) ($paidBillingByEmployeeClient[0][$filterClientId] ?? 0), 2);
                } else {
                    $invoiceBucketNetPay = round((float) ($billingNetPayByEmployeeId[0] ?? 0), 2);
                    $invoiceBillingTotal = round(array_sum($billingByEmployeeClient[0] ?? []), 2);
                    $invoiceBillingPaid = round(array_sum($paidBillingByEmployeeClient[0] ?? []), 2);
                }

                if ($invoiceBucketNetPay > 0) {
                    $totalSalary = round($totalSalary + $invoiceBucketNetPay, 2);
                }
            }

            $expenses = 0.0;
            $totalPayrollOutflow = round($totalSalary + $totalCommission, 2);
            $netProfit = round($totalCollections - $totalPayrollOutflow - $expenses, 2);

            $paidNetPayTotal = 0.0;
            $unpaidNetPayTotal = 0.0;
            $paidCommissionTotal = 0.0;
            $unpaidCommissionTotal = 0.0;
            foreach ($rows as $row) {
                $empId = (int) ($row['employee_id'] ?? 0);
                $commission = round((float) ($row['pnl_commission'] ?? 0), 2);
                $payrollInvoicedTotal = round((float) ($row['pnl_payroll_invoiced'] ?? 0), 2);
                $paidPayrollInvoicedTotal = round((float) ($row['pnl_payroll_invoiced_paid'] ?? 0), 2);
                $billingInvoicedTotal = round((float) ($row['pnl_billing_invoiced'] ?? 0), 2);
                $paidBillingInvoicedTotal = round((float) ($row['pnl_billing_invoiced_paid'] ?? 0), 2);

                $conversionNetPay = round((float) ($netPayByEmployeeId[$empId] ?? 0), 2);
                if ($payrollInvoicedTotal <= 0) {
                    $conversionNetPay = 0.0;
                }
                $billingNetPay = round((float) ($billingNetPayByEmployeeId[$empId] ?? 0), 2);

                $conversionNetPaySplit = $this->splitPnlAmountByPaidCollections(
                    $conversionNetPay,
                    $paidPayrollInvoicedTotal,
                    $payrollInvoicedTotal
                );
                $billingNetPaySplit = $this->splitPnlAmountByPaidCollections(
                    $billingNetPay,
                    $paidBillingInvoicedTotal,
                    $billingInvoicedTotal
                );

                $paidNetPayTotal = round(
                    $paidNetPayTotal + $conversionNetPaySplit['paid'] + $billingNetPaySplit['paid'],
                    2
                );
                $unpaidNetPayTotal = round(
                    $unpaidNetPayTotal + $conversionNetPaySplit['unpaid'] + $billingNetPaySplit['unpaid'],
                    2
                );

                $paidRatio = $payrollInvoicedTotal > 0
                    ? min(1.0, $paidPayrollInvoicedTotal / $payrollInvoicedTotal)
                    : 0.0;
                $unpaidRatio = 1.0 - $paidRatio;
                $paidCommissionTotal = round($paidCommissionTotal + ($commission * $paidRatio), 2);
                $unpaidCommissionTotal = round($unpaidCommissionTotal + ($commission * $unpaidRatio), 2);
            }

            if ($allowedEmployeeIds === null && $invoiceBucketNetPay > 0) {
                $invoiceNetPaySplit = $this->splitPnlAmountByPaidCollections(
                    $invoiceBucketNetPay,
                    $invoiceBillingPaid,
                    $invoiceBillingTotal
                );
                $paidNetPayTotal = round($paidNetPayTotal + $invoiceNetPaySplit['paid'], 2);
                $unpaidNetPayTotal = round($unpaidNetPayTotal + $invoiceNetPaySplit['unpaid'], 2);
            }

            $unpaidCollectionsTotal = round($totalCollections - $paidTotalCollections, 2);
            $paidExpenses = $totalCollections > 0
                ? round($expenses * ($paidTotalCollections / $totalCollections), 2)
                : 0.0;
            $unpaidExpenses = round($expenses - $paidExpenses, 2);
            $netProfitPaid = round($paidTotalCollections - $paidNetPayTotal - $paidCommissionTotal - $paidExpenses, 2);
            $netProfitUnpaid = round($unpaidCollectionsTotal - $unpaidNetPayTotal - $unpaidCommissionTotal - $unpaidExpenses, 2);

            $weeklyColl = [];
            foreach ($weeklyPayrollColl as $wk => $v) {
                $weeklyColl[$wk] = ($weeklyColl[$wk] ?? 0) + $v;
            }
            foreach ($weeklyBillingColl as $wk => $v) {
                $weeklyColl[$wk] = round(($weeklyColl[$wk] ?? 0) + $v, 2);
            }

            $weeklyPaidColl = [];
            foreach ($weeklyPaidPayrollColl as $wk => $v) {
                $weeklyPaidColl[$wk] = ($weeklyPaidColl[$wk] ?? 0) + $v;
            }
            foreach ($weeklyPaidBillingColl as $wk => $v) {
                $weeklyPaidColl[$wk] = round(($weeklyPaidColl[$wk] ?? 0) + $v, 2);
            }

            $weekRows = [];
            $endBound = Carbon::parse($endDate)->endOfDay();
            $weekCursor = Carbon::parse($startDate)->copy()->startOfWeek(Carbon::MONDAY);
            $weekMondayKeys = [];
            while ($weekCursor->lte($endBound)) {
                $weekMondayKeys[] = $weekCursor->format('Y-m-d');
                $weekCursor->addWeek();
            }
            $nWeeks = max(1, count($weekMondayKeys));
            $weekIndex = 0;
            foreach ($weekMondayKeys as $wk) {
                $curWeek = Carbon::parse($wk);
                $collW = round((float) ($weeklyColl[$wk] ?? 0), 2);
                $paidCollW = round((float) ($weeklyPaidColl[$wk] ?? 0), 2);
                $payrollInvoicedW = round((float) ($weeklyPayrollColl[$wk] ?? 0), 2);
                $billingInvoicedW = round((float) ($weeklyBillingColl[$wk] ?? 0), 2);
                $paidPayrollInvoicedW = round((float) ($weeklyPaidPayrollColl[$wk] ?? 0), 2);
                $paidBillingInvoicedW = round((float) ($weeklyPaidBillingColl[$wk] ?? 0), 2);
                $netPayW = round((float) ($weeklyNetPay[$wk] ?? 0), 2);
                $commissionW = round((float) ($weeklyCommission[$wk] ?? 0), 2);
                $paidNetPayW = round((float) ($weeklyPaidNetPay[$wk] ?? 0), 2);
                $paidCommissionW = round((float) ($weeklyPaidCommission[$wk] ?? 0), 2);
                $unpaidNetPayW = round($netPayW - $paidNetPayW, 2);
                $unpaidCommissionW = round($commissionW - $paidCommissionW, 2);

                if ($totalCollections > 0) {
                    $alloc = $collW / $totalCollections;
                    $expW = round($expenses * $alloc, 2);
                } else {
                    $perWeekExp = $nWeeks > 0 ? round($expenses / $nWeeks, 2) : $expenses;
                    $expW = ($weekIndex === $nWeeks - 1)
                        ? round($expenses - $perWeekExp * max(0, $nWeeks - 1), 2)
                        : $perWeekExp;
                }

                $paidExpW = $collW > 0
                    ? round($expW * ($paidCollW / $collW), 2)
                    : 0.0;
                $unpaidExpW = round($expW - $paidExpW, 2);
                $paidNetProfitW = round($paidCollW - $paidNetPayW - $paidCommissionW - $paidExpW, 2);
                $unpaidNetProfitW = round(($collW - $paidCollW) - $unpaidNetPayW - $unpaidCommissionW - $unpaidExpW, 2);
                $netW = round($paidNetProfitW + $unpaidNetProfitW, 2);

                $weekRows[] = [
                    'week_key' => $wk,
                    'period_label' => 'W '.$curWeek->format('m/d'),
                    'collections' => $collW,
                    'paid_collections' => $paidCollW,
                    'payroll_invoiced' => $payrollInvoicedW,
                    'billing_invoiced' => $billingInvoicedW,
                    'paid_payroll_invoiced' => $paidPayrollInvoicedW,
                    'paid_billing_invoiced' => $paidBillingInvoicedW,
                    'net_pay' => $netPayW,
                    'commission' => $commissionW,
                    'paid_net_pay' => $paidNetPayW,
                    'unpaid_net_pay' => $unpaidNetPayW,
                    'paid_commission' => $paidCommissionW,
                    'unpaid_commission' => $unpaidCommissionW,
                    'expenses' => $expW,
                    'paid_expenses' => $paidExpW,
                    'unpaid_expenses' => $unpaidExpW,
                    'net_profit' => $netW,
                    'paid_net_profit' => $paidNetProfitW,
                    'unpaid_net_profit' => $unpaidNetProfitW,
                ];
                $weekIndex++;
            }

            if ($commissionByClientId === []) {
                foreach ($rows as $row) {
                    $empId = (int) ($row['employee_id'] ?? 0);
                    $commission = round((float) ($row['pnl_commission'] ?? 0), 2);
                    if ($empId < 1 || $commission <= 0) {
                        continue;
                    }
                    $parts = [];
                    foreach ($payrollByClientId as $clientIdKey => $part) {
                        $parts[(int) $clientIdKey] = round(($parts[(int) $clientIdKey] ?? 0) + (float) $part, 2);
                    }
                    if ($parts === []) {
                        continue;
                    }
                    if ($filterClientId) {
                        $parts = isset($parts[$filterClientId])
                            ? [$filterClientId => $parts[$filterClientId]]
                            : [];
                    }
                    foreach ($this->allocatePnlAmountProportionally($commission, $parts) as $clientIdKey => $clientCommission) {
                        $commissionByClientId[(int) $clientIdKey] = round(
                            ($commissionByClientId[(int) $clientIdKey] ?? 0) + $clientCommission,
                            2
                        );
                    }
                }
            }

            $employeeClientRows = $this->buildPnlEmployeeClientRows(
                $periodInvoices,
                $payrollByEmployeeClient,
                $billingByEmployeeClient,
                $paidPayrollByEmployeeClient,
                $paidBillingByEmployeeClient,
                $billingNetPayByEmployeeClient,
                $allowedEmployeeIds,
                $filterClientId
            );

            $pnlBreakdowns = $this->buildPnlClientAndSalesRepBreakdowns(
                $employeeClientRows,
                $rows,
                $companyId,
                $filterClientId
            );

            return response()->json([
                'success' => true,
                'collections' => [
                    'payroll_invoiced' => $payrollInvoiced,
                    'billing_invoiced' => $billingInvoiced,
                    'billing_unallocated' => $billingUnallocated,
                    'total' => $totalCollections,
                    'paid_payroll_invoiced' => $paidPayrollInvoiced,
                    'paid_billing_invoiced' => $paidBillingInvoiced,
                    'paid_billing_unallocated' => $paidBillingUnallocated,
                    'paid_total' => $paidTotalCollections,
                ],
                'payroll_summary' => [
                    'total_salary' => round($totalSalary, 2),
                    'total_commission' => round($totalCommission, 2),
                    'total_payroll' => round($totalSalary, 2),
                    'total_payroll_outflow' => $totalPayrollOutflow,
                    'total_paid_net_pay' => $paidNetPayTotal,
                    'total_unpaid_net_pay' => $unpaidNetPayTotal,
                    'total_paid_commission' => round($paidCommissionTotal, 2),
                    'total_unpaid_commission' => round($unpaidCommissionTotal, 2),
                ],
                'expenses' => $expenses,
                'net_profit' => $netProfit,
                'net_profit_paid' => $netProfitPaid,
                'net_profit_unpaid' => $netProfitUnpaid,
                'weekly' => $weekRows,
                'data' => $rows,
                'by_client' => $pnlBreakdowns['by_client'],
                'by_sales_rep' => $pnlBreakdowns['by_sales_rep'],
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'filtered_client_id' => $filterClientId,
                'filtered_sales_rep_id' => $salesRepFilter['filter_sales_rep_id'],
                'filtered_sales_rep_unassigned' => $salesRepFilter['filter_unassigned'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPnlManualExpense(PnlManualExpense $expense): array
    {
        return [
            'id' => $expense->id,
            'date' => $expense->expense_date->format('Y-m-d'),
            'amount' => round((float) $expense->amount, 2),
            'notes' => $expense->notes ?? '',
            'client_id' => $expense->client_id,
            'client_name' => $expense->client?->name,
            'created_by' => $expense->user?->name,
        ];
    }

    /**
     * API: list company P&L manual expenses for a date range.
     */
    public function getPnlManualExpenses(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
            $filterClientId = $request->filled('client_id') ? (int) $request->get('client_id') : null;
            if ($filterClientId !== null && $filterClientId < 1) {
                $filterClientId = null;
            }

            $expensesQuery = PnlManualExpense::query()
                ->where('company_id', $user->company_id)
                ->whereBetween('expense_date', [
                    Carbon::parse($startDate)->toDateString(),
                    Carbon::parse($endDate)->toDateString(),
                ]);

            if ($filterClientId) {
                $expensesQuery->where('client_id', $filterClientId);
            }

            $expenses = $expensesQuery
                ->with(['user:id,name', 'client:id,name'])
                ->orderBy('expense_date')
                ->orderBy('id')
                ->get()
                ->map(fn (PnlManualExpense $expense) => $this->formatPnlManualExpense($expense))
                ->values();

            return response()->json([
                'success' => true,
                'data' => $expenses,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: create a company P&L manual expense.
     */
    public function storePnlManualExpense(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'date' => 'required|date_format:Y-m-d',
                'amount' => 'required|numeric|min:0.01',
                'notes' => 'nullable|string|max:2000',
                'client_id' => 'nullable|integer|exists:clients,id',
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
            $expenseDate = $request->get('date');

            if ($expenseDate < $startDate || $expenseDate > $endDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date must fall within the selected month.',
                ], 422);
            }

            $clientId = $request->filled('client_id') ? (int) $request->get('client_id') : null;
            if ($clientId) {
                $clientValid = Client::query()
                    ->where('id', $clientId)
                    ->where('company_id', $user->company_id)
                    ->exists();
                if (! $clientValid) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected client is not valid for your company.',
                    ], 422);
                }
            }

            $expense = PnlManualExpense::query()->create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'client_id' => $clientId,
                'expense_date' => $expenseDate,
                'amount' => round((float) $request->get('amount'), 2),
                'notes' => $request->get('notes'),
            ]);

            $expense->load(['user:id,name', 'client:id,name']);

            return response()->json([
                'success' => true,
                'data' => $this->formatPnlManualExpense($expense),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: update a company P&L manual expense.
     */
    public function updatePnlManualExpense(Request $request, PnlManualExpense $pnlManualExpense)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            if ((int) $pnlManualExpense->company_id !== (int) $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found.',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'date' => 'required|date_format:Y-m-d',
                'amount' => 'required|numeric|min:0.01',
                'notes' => 'nullable|string|max:2000',
                'client_id' => 'nullable|integer|exists:clients,id',
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
            $expenseDate = $request->get('date');

            if ($expenseDate < $startDate || $expenseDate > $endDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date must fall within the selected period.',
                ], 422);
            }

            $clientId = $request->filled('client_id') ? (int) $request->get('client_id') : null;
            if ($clientId) {
                $clientValid = Client::query()
                    ->where('id', $clientId)
                    ->where('company_id', $user->company_id)
                    ->exists();
                if (! $clientValid) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Selected client is not valid for your company.',
                    ], 422);
                }
            }

            $pnlManualExpense->update([
                'client_id' => $clientId,
                'expense_date' => $expenseDate,
                'amount' => round((float) $request->get('amount'), 2),
                'notes' => $request->get('notes'),
            ]);

            $pnlManualExpense->load(['user:id,name', 'client:id,name']);

            return response()->json([
                'success' => true,
                'data' => $this->formatPnlManualExpense($pnlManualExpense),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: delete a company P&L manual expense.
     */
    public function deletePnlManualExpense(PnlManualExpense $pnlManualExpense)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            if ((int) $pnlManualExpense->company_id !== (int) $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Expense not found.',
                ], 404);
            }

            $pnlManualExpense->delete();

            return response()->json([
                'success' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: payroll totals grouped by sales rep for selected date range.
     */
    public function getSalesRepPayrollSummary(Request $request)
    {
        try {
            $reportResponse = $this->getPayrollReport($request);
            $payload = $reportResponse->getData(true);
            $pnlSummary = $payload['pnl_summary'] ?? [];

            if (! ($payload['success'] ?? false)) {
                return response()->json($payload, $reportResponse->getStatusCode());
            }

            $rows = collect($payload['data'] ?? []);
            $groups = $rows->groupBy(function ($row) {
                $repId = (int) ($row['sales_rep_id'] ?? 0);

                return $repId > 0 ? (string) $repId : 'unassigned';
            });

            $summary = $groups->map(function ($group, $key) {
                $sample = $group->first() ?? [];
                $repName = $key === 'unassigned'
                    ? 'Unassigned'
                    : (string) ($sample['sales_rep_name'] ?? 'Unknown');

                return [
                    'sales_rep_key' => $key,
                    'sales_rep_id' => $key === 'unassigned' ? null : (int) $key,
                    'sales_rep_name' => $repName,
                    'employee_count' => $group->count(),
                    'bill_amount' => round((float) $group->sum(fn ($r) => (float) ($r['client_invoice_amount'] ?? 0)), 2),
                    'generated_payroll' => round((float) $group->sum(fn ($r) => (float) ($r['net_pay'] ?? 0)), 2),
                    'commission' => round((float) $group->sum(fn ($r) => (float) ($r['pnl_commission'] ?? 0)), 2),
                ];
            })->values()->sortBy('sales_rep_name', SORT_NATURAL | SORT_FLAG_CASE)->values();

            $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));
            $companyId = Auth::user()?->company_id;

            $reports = PayrollPeriodInvoice::query()
                ->where('company_id', $companyId)
                ->whereDate('period_start_date', '<=', $endDate)
                ->whereDate('period_end_date', '>=', $startDate)
                ->with('createdBy:id,name')
                ->orderBy('period_start_date', 'desc')
                ->get()
                ->map(function (PayrollPeriodInvoice $periodInvoice) {
                    $employeeCount = collect($periodInvoice->converted_employee_ids ?? [])->count();
                    $generatedAmount = $this->conversionDetailsService()->totalSum(
                        (array) ($periodInvoice->conversion_details ?? []),
                        'net_pay'
                    );

                    return [
                        'id' => (int) $periodInvoice->id,
                        'period_start_date' => $periodInvoice->period_start_date?->format('Y-m-d'),
                        'period_end_date' => $periodInvoice->period_end_date?->format('Y-m-d'),
                        'created_at' => $periodInvoice->created_at?->format('Y-m-d H:i:s'),
                        'created_by' => $periodInvoice->createdBy?->name,
                        'status' => 'generated',
                        'employee_count' => $employeeCount,
                        'total_amount' => $generatedAmount,
                    ];
                })->values();

            return response()->json([
                'success' => true,
                'data' => $summary,
                'reports' => $reports,
                'totals' => [
                    // Bill Amount on this page must be the payroll report bill amount
                    // (sum of client_invoice_amount for the selected date range).
                    'bill_amount' => round((float) $summary->sum('bill_amount'), 2),
                    'generated_payroll' => round((float) $summary->sum('generated_payroll'), 2),
                    // Use payroll report's filtered commission total directly.
                    'commission' => round((float) ($pnlSummary['total_commission'] ?? $summary->sum('commission')), 2),
                    'sales_reps' => $summary->count(),
                ],
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * API: detail rows for a generated payroll report.
     */
    public function getSalesRepPayrollReportDetails(PayrollPeriodInvoice $payrollPeriodInvoice)
    {
        try {
            $user = Auth::user();
            if (! $user || ! $user->company_id || $payrollPeriodInvoice->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to payroll report.',
                ], 403);
            }

            $payrollPeriodInvoice->load('createdBy:id,name');
            $employeeIds = collect($payrollPeriodInvoice->converted_employee_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values();
            $employees = User::whereIn('id', $employeeIds)->with('salesRep')->get()->keyBy('id');
            $details = collect($payrollPeriodInvoice->conversion_details ?? []);
            $invoiceMap = collect($payrollPeriodInvoice->employee_invoice_mapping ?? []);

            // Pull payroll report rows for this same period so Bill Amount + Commission
            // are aligned with payroll report logic (commission based on bill amount).
            $reportRequest = Request::create('/api/payroll/payroll-report', 'GET', [
                'start_date' => $payrollPeriodInvoice->period_start_date?->format('Y-m-d'),
                'end_date' => $payrollPeriodInvoice->period_end_date?->format('Y-m-d'),
            ]);
            $reportResponse = $this->getPayrollReport($reportRequest);
            $reportPayload = $reportResponse->getData(true);
            $reportRowsByEmployee = collect($reportPayload['data'] ?? [])->keyBy(function ($row) {
                return (int) ($row['employee_id'] ?? 0);
            });

            $items = $employeeIds->map(function ($employeeId) use ($employees, $details, $invoiceMap) {
                $employee = $employees->get($employeeId);
                $invoiceIds = collect($invoiceMap->get((string) $employeeId, []))->map(fn ($v) => (int) $v)->filter()->values()->all();

                return [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee?->name ?? ('Employee #'.$employeeId),
                    'sales_rep_name' => $employee?->salesRep?->name ?? 'Unassigned',
                    'hours_worked' => $this->conversionDetailsService()->sumForEmployee($details->all(), (int) $employeeId, 'hours_worked'),
                    'generated_payroll' => $this->conversionDetailsService()->sumForEmployee($details->all(), (int) $employeeId, 'net_pay'),
                    'invoice_ids' => $invoiceIds,
                    'client_breakdown' => collect($this->conversionDetailsService()->clientIdsForEmployee($details->all(), (int) $employeeId))
                        ->map(function (int $clientId) use ($details, $employeeId) {
                            $key = $this->conversionDetailsService()->detailKey($employeeId, $clientId);
                            $detail = is_array($details->get($key)) ? $details->get($key) : [];

                            return [
                                'client_id' => $clientId,
                                'hours_worked' => round((float) ($detail['hours_worked'] ?? 0), 2),
                                'generated_payroll' => round((float) ($detail['net_pay'] ?? 0), 2),
                                'bill_amount' => round((float) ($detail['bill_amount'] ?? 0), 2),
                                'base_salary' => round((float) ($detail['base_salary'] ?? 0), 2),
                                'commission' => round((float) ($detail['commission'] ?? 0), 2),
                            ];
                        })->values()->all(),
                ];
            })->map(function ($row) use ($reportRowsByEmployee, $details) {
                $reportRow = $reportRowsByEmployee->get((int) ($row['employee_id'] ?? 0), []);
                $employeeId = (int) ($row['employee_id'] ?? 0);
                $storedCommission = $this->conversionDetailsService()->sumForEmployee($details->all(), $employeeId, 'commission');
                $storedBillAmount = $this->conversionDetailsService()->sumForEmployee($details->all(), $employeeId, 'bill_amount');
                $row['bill_amount'] = $storedBillAmount > 0
                    ? $storedBillAmount
                    : round((float) ($reportRow['client_invoice_amount'] ?? 0), 2);
                $row['commission'] = $storedCommission > 0
                    ? $storedCommission
                    : round((float) ($reportRow['pnl_commission'] ?? 0), 2);

                return $row;
            })->values();

            return response()->json([
                'success' => true,
                'report' => [
                    'id' => (int) $payrollPeriodInvoice->id,
                    'period_start_date' => $payrollPeriodInvoice->period_start_date?->format('Y-m-d'),
                    'period_end_date' => $payrollPeriodInvoice->period_end_date?->format('Y-m-d'),
                    'created_at' => $payrollPeriodInvoice->created_at?->format('Y-m-d H:i:s'),
                    'created_by' => $payrollPeriodInvoice->createdBy?->name,
                    'status' => 'generated',
                    'total_amount' => round((float) $items->sum('generated_payroll'), 2),
                ],
                'items' => $items,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Convert payroll report to invoices (one invoice per client, one conversion per date range).
     */
    public function convertPayrollToInvoice(Request $request)
    {
        try {
            $user = Auth::user();
            if (! $user || ! $user->company_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
            }

            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'selected_employee_ids' => 'required|array',
                'selected_employee_ids.*' => 'integer|exists:users,id',
                'employee_details' => 'nullable|array',
                'employee_details.*.employee_id' => 'integer',
                'employee_details.*.hours_worked' => 'nullable|numeric|min:0',
                'employee_details.*.net_pay' => 'nullable|numeric',
                'employee_details.*.bill_amount' => 'nullable|numeric|min:0',
                'employee_details.*.base_salary' => 'nullable|numeric|min:0',
                'employee_details.*.commission' => 'nullable|numeric|min:0',
                'employee_details.*.selected_client_ids' => 'nullable|array',
                'employee_details.*.selected_client_ids.*' => 'integer',
            ]);

            $startDate = $validated['start_date'];
            $endDate = $validated['end_date'];
            $selectedIds = $validated['selected_employee_ids'];
            $employeeDetailsArr = $validated['employee_details'] ?? [];
            $employeeDetails = [];
            $employeeSelectedClientIds = [];
            foreach ($employeeDetailsArr as $d) {
                $eid = $d['employee_id'] ?? null;
                if ($eid !== null) {
                    $employeeDetails[(string) $eid] = $d;
                    if (isset($d['selected_client_ids']) && is_array($d['selected_client_ids'])) {
                        $employeeSelectedClientIds[(string) $eid] = array_values(array_unique(array_map('intval', $d['selected_client_ids'])));
                    }
                }
            }

            $existing = PayrollPeriodInvoice::where('company_id', $user->company_id)
                ->where('period_start_date', $startDate)
                ->where('period_end_date', $endDate)
                ->first();

            $alreadyConverted = $existing ? ($existing->converted_employee_ids ?? []) : [];

            // Compute existing (employee, client) pairs already invoiced for this period so we
            // can skip duplicate billing while still allowing un-invoiced clients to be billed.
            $alreadyInvoicedClientsByEmployee = [];
            if ($existing) {
                $existingMappingForLookup = $existing->employee_invoice_mapping ?? [];
                $allExistingInvoiceIds = collect($existingMappingForLookup)
                    ->flatten()
                    ->unique()
                    ->filter()
                    ->values()
                    ->all();
                $existingInvoiceClientById = [];
                if (! empty($allExistingInvoiceIds)) {
                    $existingInvoiceClientById = Invoice::whereIn('id', $allExistingInvoiceIds)
                        ->pluck('client_id', 'id')
                        ->map(fn ($cid) => (int) $cid)
                        ->toArray();
                }
                foreach ($existingMappingForLookup as $empId => $invIds) {
                    $clientIdsForEmp = [];
                    foreach ((array) $invIds as $invId) {
                        $cid = $existingInvoiceClientById[$invId] ?? null;
                        if ($cid) {
                            $clientIdsForEmp[] = (int) $cid;
                        }
                    }
                    if (! empty($clientIdsForEmp)) {
                        $alreadyInvoicedClientsByEmployee[(int) $empId] = array_values(array_unique($clientIdsForEmp));
                    }
                }
            }

            $employees = User::where('company_id', $user->company_id)
                ->whereIn('id', $selectedIds)
                ->with('clients')
                ->get();

            // Build client -> [employee lines] map (employees with a non-negative client_invoice_amount,
            // including zero, that are assigned to that client). When the request specifies selected_client_ids for an
            // employee, restrict the lines to those clients. Skip (employee, client) pairs that
            // were already invoiced in a previous conversion for this period.
            $clientLines = [];
            $skippedAlreadyInvoiced = [];
            foreach ($employees as $emp) {
                $detail = $employeeDetails[(string) $emp->id] ?? [];
                $amount = isset($detail['bill_amount']) ? floatval($detail['bill_amount']) : floatval($emp->client_invoice_amount ?? 0);
                if ($amount < 0) {
                    continue;
                }

                $assignedClients = $emp->clients;
                $selectedClientIds = $employeeSelectedClientIds[(string) $emp->id] ?? null;

                if ($selectedClientIds !== null) {
                    $assignedClients = $assignedClients->filter(
                        fn ($client) => in_array((int) $client->id, $selectedClientIds, true)
                    );
                }

                $invoicedClientIds = $alreadyInvoicedClientsByEmployee[(int) $emp->id] ?? [];

                foreach ($assignedClients as $client) {
                    $clientId = (int) $client->id;

                    if (in_array($clientId, $invoicedClientIds, true)) {
                        $skippedAlreadyInvoiced[] = [
                            'employee_id' => (int) $emp->id,
                            'employee_name' => $emp->name,
                            'client_id' => $clientId,
                            'client_name' => $client->name,
                        ];

                        continue;
                    }

                    if (! isset($clientLines[$clientId])) {
                        $clientLines[$clientId] = ['client' => $client, 'items' => []];
                    }
                    $hoursWorked = isset($detail['hours_worked']) && $detail['hours_worked'] !== ''
                        ? (float) $detail['hours_worked']
                        : null;

                    $clientLines[$clientId]['items'][] = [
                        'employee_id' => $emp->id,
                        'description' => "Payroll - {$emp->name} ({$startDate} to {$endDate})",
                        'hours_worked' => $hoursWorked,
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'total' => $amount,
                    ];
                }
            }

            $clientLines = array_filter($clientLines, fn ($c) => count($c['items']) > 0);
            if (empty($clientLines)) {
                $message = ! empty($skippedAlreadyInvoiced)
                    ? 'All selected employee/client pairs have already been converted to invoice. Pick at least one client that has not yet been invoiced.'
                    : 'No billable items found. Selected employees must have at least one client assigned.';

                return response()->json([
                    'success' => false,
                    'message' => $message,
                    'skipped_already_invoiced' => $skippedAlreadyInvoiced,
                ], 422);
            }

            DB::beginTransaction();

            $invoiceIds = [];
            $employeeInvoiceMapping = $existing ? ($existing->employee_invoice_mapping ?? []) : [];
            $invoiceDate = Carbon::parse($endDate)->format('Y-m-d');
            $dueDate = Carbon::parse($endDate)->addDays(30)->format('Y-m-d');
            $defaultWisePaymentUrl = $user->company?->default_wise_payment_url;

            foreach ($clientLines as $clientId => $data) {
                $items = $data['items'];
                $subtotal = array_sum(array_column($items, 'total'));
                $taxRate = 0;
                $taxAmount = 0;
                $total = $subtotal;

                $invoice = Invoice::create([
                    'company_id' => $user->company_id,
                    'client_id' => $clientId,
                    'user_id' => $user->id,
                    'invoice_number' => Invoice::generateInvoiceNumber(),
                    'invoice_date' => $invoiceDate,
                    'due_date' => $dueDate,
                    'status' => 'draft',
                    'subtotal' => $subtotal,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                    'notes' => "Generated from payroll report {$startDate} to {$endDate}",
                    'wise_payment_url' => $defaultWisePaymentUrl,
                ]);

                foreach ($items as $idx => $item) {
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => $item['description'],
                        'hours_worked' => $item['hours_worked'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'total' => $item['total'],
                        'sort_order' => $idx,
                    ]);
                    $empId = $item['employee_id'] ?? null;
                    if ($empId) {
                        $employeeInvoiceMapping[$empId] = $employeeInvoiceMapping[$empId] ?? [];
                        $employeeInvoiceMapping[$empId][] = $invoice->id;
                    }
                }

                $invoiceIds[] = $invoice->id;
            }

            $mappingForSave = [];
            foreach ($employeeInvoiceMapping as $empId => $invIds) {
                $mappingForSave[(string) $empId] = array_values((array) $invIds);
            }
            $conversionDetails = $existing ? (array) ($existing->conversion_details ?? []) : [];
            $mergedInvoiceIds = $existing
                ? array_values(array_merge($existing->invoice_ids ?? [], $invoiceIds))
                : $invoiceIds;
            $periodForBillLookup = new PayrollPeriodInvoice(['invoice_ids' => $mergedInvoiceIds]);
            $hoursSync = app(InvoiceItemHoursSyncService::class);
            $reportRowsByEmployeeId = $this->payrollReportRowsKeyedByEmployeeId($startDate, $endDate);
            $employeesById = $employees->keyBy('id');

            foreach ($selectedIds as $empId) {
                $empId = (int) $empId;
                $detail = $employeeDetails[(string) $empId] ?? $employeeDetails[$empId] ?? [];
                $reportRow = $reportRowsByEmployeeId[$empId] ?? [];
                $employee = $employeesById->get($empId);
                if (! $employee) {
                    continue;
                }

                $billByClient = $hoursSync->billAmountsByClientForEmployeeInPeriod($empId, $periodForBillLookup);
                if ($billByClient === []) {
                    continue;
                }

                $alreadyInvoiced = $alreadyInvoicedClientsByEmployee[$empId] ?? [];
                $selectedClientIds = $employeeSelectedClientIds[(string) $empId] ?? [];
                $newClientIds = array_values(array_unique(array_filter(
                    $selectedClientIds !== []
                        ? $selectedClientIds
                        : array_map('intval', array_keys($billByClient)),
                    fn (int $clientId) => $clientId > 0 && ! in_array($clientId, $alreadyInvoiced, true)
                )));

                if ($newClientIds === []) {
                    continue;
                }

                $netPay = $this->resolveConversionField($detail, $reportRow, 'net_pay');
                $hoursWorked = $this->resolveConversionField($detail, $reportRow, 'hours_worked');
                $baseSalary = $this->resolveConversionField($detail, $reportRow, 'base_salary');
                $commissionFromDetail = array_key_exists('commission', $detail) && $detail['commission'] !== '' && $detail['commission'] !== null
                    ? round((float) $detail['commission'], 2)
                    : null;

                foreach ($newClientIds as $clientId) {
                    $clientId = (int) $clientId;
                    $clientBillAmount = round((float) ($billByClient[$clientId] ?? $detail['bill_amount'] ?? 0), 2);
                    $clientCommission = $commissionFromDetail ?? $this->calculateReportCommissionForBill($employee, $clientBillAmount);

                    $conversionDetails = $this->conversionDetailsService()->upsertEmployeeClientConversion(
                        $conversionDetails,
                        $empId,
                        $clientId,
                        $clientBillAmount,
                        $netPay,
                        $baseSalary,
                        $hoursWorked,
                        $clientCommission,
                    );
                }
            }
            if ($existing) {
                $mergedInvoiceIds = array_merge($existing->invoice_ids ?? [], $invoiceIds);
                $mergedEmployeeIds = array_unique(array_merge($alreadyConverted, $selectedIds));
                $existingMapping = $existing->employee_invoice_mapping ?? [];
                $existingMapping = is_array($existingMapping) ? $existingMapping : [];
                foreach ($existingMapping as $k => $v) {
                    $kInt = (int) $k;
                    if ($kInt > 0 && in_array($kInt, $alreadyConverted) && ! isset($mappingForSave[(string) $k])) {
                        $mappingForSave[(string) $k] = array_values((array) $v);
                    }
                }
                $existing->update([
                    'invoice_ids' => array_values($mergedInvoiceIds),
                    'converted_employee_ids' => array_values(array_map('intval', $mergedEmployeeIds)),
                    'employee_invoice_mapping' => $mappingForSave,
                    'conversion_details' => $conversionDetails,
                ]);
            } else {
                PayrollPeriodInvoice::create([
                    'company_id' => $user->company_id,
                    'period_start_date' => $startDate,
                    'period_end_date' => $endDate,
                    'invoice_ids' => $invoiceIds,
                    'converted_employee_ids' => $selectedIds,
                    'employee_invoice_mapping' => $mappingForSave,
                    'conversion_details' => $conversionDetails,
                    'created_by_user_id' => $user->id,
                ]);
            }

            DB::commit();

            $stripeLinkService = app(\App\Services\StripePaymentLinkService::class);
            if ($stripeLinkService->isConfigured($user->company_id)) {
                foreach (Invoice::whereIn('id', $invoiceIds)->get() as $createdInvoice) {
                    $stripeLinkService->generateForInvoice($createdInvoice);
                }
            }

            return response()->json([
                'success' => true,
                'message' => count($invoiceIds).' invoice(s) created successfully.',
                'invoice_ids' => $invoiceIds,
                'converted_employee_ids' => $existing ? array_values(array_merge($alreadyConverted, $selectedIds)) : $selectedIds,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create invoices: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of payroll conversions (converted to invoice records).
     */
    public function getConvertedInvoicesList(Request $request)
    {
        try {
            $user = Auth::user();
            if (! $user || ! $user->company_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
            }

            $month = $request->get('month', now()->format('Y-m'));
            try {
                $monthStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable $e) {
                $monthStart = now()->startOfMonth();
                $month = $monthStart->format('Y-m');
            }
            $monthEnd = $monthStart->copy()->endOfMonth();
            $perPage = max(1, min(100, (int) $request->get('per_page', 10)));
            $page = max(1, (int) $request->get('page', 1));

            $periods = PayrollPeriodInvoice::where('company_id', $user->company_id)
                ->whereDate('period_start_date', '<=', $monthEnd->format('Y-m-d'))
                ->whereDate('period_end_date', '>=', $monthStart->format('Y-m-d'))
                ->orderBy('period_start_date', 'desc')
                ->get();

            $records = [];
            $userCache = [];
            foreach ($periods as $p) {
                $invoiceIds = $p->invoice_ids ?? [];
                if (empty($invoiceIds)) {
                    continue;
                }
                $periodStart = $p->period_start_date instanceof \Carbon\Carbon ? $p->period_start_date->format('M j') : $p->period_start_date;
                $periodEnd = $p->period_end_date instanceof \Carbon\Carbon ? $p->period_end_date->format('M j, Y') : $p->period_end_date;
                $periodStr = "{$periodStart} - {$periodEnd}";

                $invoices = Invoice::whereIn('id', $invoiceIds)->with('client')->get()->keyBy('id');
                $items = InvoiceItem::whereIn('invoice_id', $invoiceIds)->get();

                foreach ($items as $item) {
                    if (! preg_match('/^Payroll - (.+?) \(\d{4}-\d{2}-\d{2}/', $item->description ?? '', $m)) {
                        continue;
                    }
                    $empName = trim($m[1]);
                    $invoice = $invoices->get($item->invoice_id);
                    if (! $invoice) {
                        continue;
                    }
                    $clientName = $invoice->client?->name ?? '—';
                    $billAmount = (float) ($item->total ?? 0);
                    $status = ucfirst(strtolower($invoice->status ?? 'draft'));

                    $records[] = [
                        'employee' => $empName,
                        'client' => $clientName,
                        'period' => $periodStr,
                        'bill_amount' => $billAmount,
                        'status' => $status,
                        'invoice_id' => $invoice->id,
                        'invoice_number' => $invoice->invoice_number ?? '—',
                        'invoice_item_id' => $item->id,
                        'period_start_date' => $p->period_start_date instanceof \Carbon\Carbon ? $p->period_start_date->format('Y-m-d') : $p->period_start_date,
                        'period_end_date' => $p->period_end_date instanceof \Carbon\Carbon ? $p->period_end_date->format('Y-m-d') : $p->period_end_date,
                    ];
                }
            }

            $total = count($records);
            $lastPage = max(1, (int) ceil($total / $perPage));
            $page = min($page, $lastPage);
            $offset = ($page - 1) * $perPage;
            $pagedRecords = array_slice($records, $offset, $perPage);

            return response()->json([
                'success' => true,
                'data' => $pagedRecords,
                'month' => $month,
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $total,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a converted payroll invoice (draft only). Removes the invoice item, recalculates/removes the invoice, and updates payroll tracking.
     */
    public function deleteConvertedInvoice(Request $request)
    {
        try {
            $user = Auth::user();
            if (! $user || ! $user->company_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
            }

            $validated = $request->validate([
                'invoice_item_id' => 'required|integer|exists:invoice_items,id',
            ]);

            $item = InvoiceItem::findOrFail($validated['invoice_item_id']);
            $invoice = $item->invoice;
            if (! $invoice) {
                return response()->json(['success' => false, 'message' => 'Invoice not found.'], 404);
            }
            if ($invoice->company_id !== $user->company_id) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }
            if (strtolower($invoice->status ?? '') !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft invoices can be deleted. This invoice has status: '.($invoice->status ?? 'unknown'),
                ], 422);
            }

            // Must be a payroll line item - capture employee name before delete
            if (! preg_match('/^Payroll - (.+?) \(\d{4}-\d{2}-\d{2}/', $item->description ?? '', $m)) {
                return response()->json(['success' => false, 'message' => 'Invalid item.'], 422);
            }
            $empName = trim($m[1]);
            $employee = User::where('company_id', $user->company_id)
                ->where('name', $empName)
                ->first();
            $empId = $employee?->id;

            DB::beginTransaction();

            $invoiceId = $invoice->id;
            $item->delete();

            $remainingItems = InvoiceItem::where('invoice_id', $invoiceId)->get();
            if ($remainingItems->isEmpty()) {
                $invoice->delete();
                $invoiceDeleted = true;
            } else {
                $subtotal = $remainingItems->sum('total');
                $invoice->update([
                    'subtotal' => $subtotal,
                    'tax_amount' => 0,
                    'total' => $subtotal,
                ]);
                $invoiceDeleted = false;
            }

            // Update PayrollPeriodInvoice
            if ($invoiceDeleted) {
                app(InvoiceItemHoursSyncService::class)->detachInvoiceFromPayrollPeriod($invoice);
            } else {
                $periods = PayrollPeriodInvoice::where('company_id', $user->company_id)
                    ->whereJsonContains('invoice_ids', $invoiceId)
                    ->get();

                foreach ($periods as $p) {
                    $mapping = $p->employee_invoice_mapping ?? [];
                    $conversionDetails = (array) ($p->conversion_details ?? []);
                    $convertedIds = $p->converted_employee_ids ?? [];
                    $invoiceClientId = (int) ($invoice->client_id ?? 0);

                    if ($empId !== null && isset($mapping[(string) $empId])) {
                        $invIds = array_filter((array) $mapping[(string) $empId], fn ($id) => (int) $id !== (int) $invoiceId);
                        if (empty($invIds)) {
                            unset($mapping[(string) $empId]);
                            $convertedIds = array_values(array_filter($convertedIds, fn ($id) => (int) $id !== (int) $empId));
                            $conversionDetails = $this->conversionDetailsService()->removeEmployee($conversionDetails, (int) $empId);
                            app(InvoiceItemHoursSyncService::class)->clearEmployeeSalaryHoursForPeriod($p, (int) $empId);
                        } else {
                            $mapping[(string) $empId] = array_values($invIds);
                            if ($invoiceClientId > 0) {
                                $conversionDetails = $this->conversionDetailsService()->removeEmployeeClient(
                                    $conversionDetails,
                                    (int) $empId,
                                    $invoiceClientId
                                );
                            }
                        }
                    }

                    $p->update([
                        'converted_employee_ids' => array_values(array_unique($convertedIds)),
                        'employee_invoice_mapping' => $mapping,
                        'conversion_details' => $conversionDetails,
                    ]);
                }

                app(InvoiceItemHoursSyncService::class)->syncFromInvoice($invoice->fresh(['items']));
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Converted invoice deleted successfully.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export payroll report as Excel.
     */
    public function exportPayrollReport(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $startDate = $request->get('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

            // Get company information
            $company = \App\Models\Company::find($user->company_id);

            // Check if report data is provided (from frontend with edited deductions)
            $providedReportData = $request->input('report_data');

            if ($providedReportData && is_array($providedReportData)) {
                // Use provided report data (includes edited deductions from frontend)
                $reportData = $providedReportData;

                // Calculate totals from provided data
                $totalGrossPay = collect($reportData)->sum('gross_pay');
                $totalDeductions = collect($reportData)->sum('deductions');
                $totalNetPay = collect($reportData)->sum('net_pay');
            } else {
                // Generate report data from database (fallback for direct URL access)
                $employees = User::where('company_id', $user->company_id)
                    ->with('clients')
                    ->orderBy('name')
                    ->get();

                $reportData = [];
                $totalGrossPay = 0;
                $totalDeductions = 0;
                $totalNetPay = 0;

                foreach ($employees as $employee) {
                    // Always calculate from time tracking records for accurate hours
                    $savedComputation = SalaryComputation::where('user_id', $employee->id)
                        ->where('company_id', $user->company_id)
                        ->where('period_start_date', $startDate)
                        ->where('period_end_date', $endDate)
                        ->first();

                    $fullBaseSalary = $employee->salary ?? 0;

                    // Get total hours worked from database (seconds)
                    $hoursWorkedSeconds = (int) TimeTracking::where('user_id', $employee->id)
                        ->where('company_id', $user->company_id)
                        ->whereBetween('date', [
                            Carbon::parse($startDate)->format('Y-m-d'),
                            Carbon::parse($endDate)->format('Y-m-d'),
                        ])
                        ->whereNotNull('hours_worked')
                        ->where('hours_worked', '>', 0)
                        ->sum('hours_worked');

                    $hoursWorked = $hoursWorkedSeconds > 0 ? ($hoursWorkedSeconds / 3600) : 0.0;
                    // Use employee's required_work_hours from database, fallback to 160
                    $employeeRequiredHours = ($employee->required_work_hours !== null && $employee->required_work_hours > 0)
                        ? floatval($employee->required_work_hours)
                        : 160;

                    // Calculate proportional base salary using rounded hours
                    if ($hoursWorked <= 0) {
                        $proportionalBaseSalary = 0;
                    } elseif ($employeeRequiredHours > 0) {
                        // Use exact division to avoid floating point precision issues
                        $proportionalBaseSalary = ($fullBaseSalary * $hoursWorked) / $employeeRequiredHours;
                    } else {
                        $proportionalBaseSalary = 0;
                    }

                    // Calculate overtime hours (only hours beyond required hours)
                    $overtimeHours = max(0, $hoursWorked - $employeeRequiredHours);

                    // Calculate hourly rate and overtime pay
                    $hourlyRate = ($fullBaseSalary > 0 && $employeeRequiredHours > 0) ? ($fullBaseSalary / $employeeRequiredHours) : 0;
                    $overtimeRate = $hourlyRate * 1.5;
                    $overtimePay = $overtimeHours * $overtimeRate;

                    // Always use allowances from users database
                    $allowances = floatval($employee->allowances ?? 0);

                    // Gross pay = proportional base salary + overtime pay + allowances
                    $grossPay = $proportionalBaseSalary + $overtimePay + $allowances;
                    if ($savedComputation && $savedComputation->deductions > 0) {
                        $deductions = $savedComputation->deductions;
                    } else {
                        $deductions = 0;
                    }

                    $netPay = $grossPay - $deductions;

                    $clientNames = $employee->clients->pluck('name')->join(', ') ?: '—';
                    $clientInvoiceAmount = round(floatval($employee->client_invoice_amount ?? 0), 2);

                    $reportData[] = [
                        'employee_name' => $employee->name,
                        'clients' => $clientNames,
                        'client_invoice_amount' => $clientInvoiceAmount,
                        'base_salary' => floatval($fullBaseSalary),
                        'hours_worked' => round((float) $hoursWorked, 6),
                        'hours_worked_seconds' => $hoursWorkedSeconds,
                        'required_hours' => round($employeeRequiredHours, 1),
                        'overtime_hours' => round($overtimeHours, 1),
                        'allowances' => floatval($allowances),
                        'gross_pay' => round(floatval($grossPay), 2),
                        'deductions' => round(floatval($deductions), 2),
                        'net_pay' => round(floatval($netPay), 2),
                    ];

                    $totalGrossPay += $grossPay;
                    $totalDeductions += $deductions;
                    $totalNetPay += $netPay;
                }
            }

            // Format dates for display
            $startDateFormatted = Carbon::parse($startDate)->format('M d, Y');
            $endDateFormatted = Carbon::parse($endDate)->format('M d, Y');
            $generatedDate = Carbon::now()->format('M d, Y');

            // Generate Excel file
            $filename = 'payroll-report-'.$startDate.'-to-'.$endDate.'.xlsx';

            return Excel::download(
                new PayrollReportExport(
                    $reportData,
                    [
                        'total_employees' => count($reportData),
                        'total_gross_pay' => floatval($totalGrossPay),
                        'total_deductions' => floatval($totalDeductions),
                        'total_net_pay' => floatval($totalNetPay),
                    ],
                    $company,
                    [
                        'start_date' => $startDateFormatted,
                        'end_date' => $endDateFormatted,
                    ],
                    $generatedDate,
                    null // Per-employee required hours
                ),
                $filename
            );

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while generating Excel file: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export a saved payroll report (by id) as Excel.
     */
    public function exportSavedReportExcel(PayrollReport $payrollReport)
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id || $payrollReport->company_id !== $user->company_id) {
            abort(404, 'Report not found.');
        }

        $payrollReport->load('items');
        $reportData = $payrollReport->items->map(function ($item) {
            return [
                'employee_name' => $item->employee_name ?? '--',
                'base_salary' => (float) ($item->base_salary ?? 0),
                'hours_worked' => (float) ($item->hours_worked ?? 0),
                'required_hours' => (float) ($item->required_hours ?? 0),
                'overtime_hours' => (float) ($item->overtime_hours ?? 0),
                'allowances' => (float) ($item->allowances ?? 0),
                'gross_pay' => (float) ($item->gross_pay ?? 0),
                'deductions' => (float) ($item->deductions ?? 0),
                'net_pay' => (float) ($item->net_pay ?? 0),
            ];
        })->all();

        $totalGrossPay = collect($reportData)->sum('gross_pay');
        $totalDeductions = collect($reportData)->sum('deductions');
        $totalNetPay = collect($reportData)->sum('net_pay');
        $company = \App\Models\Company::find($user->company_id);
        $startDateFormatted = Carbon::parse($payrollReport->period_start_date)->format('M d, Y');
        $endDateFormatted = Carbon::parse($payrollReport->period_end_date)->format('M d, Y');
        $generatedDate = Carbon::now()->format('M d, Y');

        $filename = 'payroll-report-'.Carbon::parse($payrollReport->period_start_date)->format('Y-m-d').'-to-'.Carbon::parse($payrollReport->period_end_date)->format('Y-m-d').'.xlsx';

        return Excel::download(
            new PayrollReportExport(
                $reportData,
                [
                    'total_employees' => count($reportData),
                    'total_gross_pay' => $totalGrossPay,
                    'total_deductions' => $totalDeductions,
                    'total_net_pay' => $totalNetPay,
                ],
                $company,
                ['start_date' => $startDateFormatted, 'end_date' => $endDateFormatted],
                $generatedDate,
                null
            ),
            $filename
        );
    }

    /**
     * Export a saved payroll report (by id) as PDF.
     */
    public function exportSavedReportPdf(PayrollReport $payrollReport)
    {
        $user = Auth::user();
        if (! $user || ! $user->company_id || $payrollReport->company_id !== $user->company_id) {
            abort(404, 'Report not found.');
        }

        $payrollReport->load('items');
        $company = \App\Models\Company::find($user->company_id);
        $periodStart = Carbon::parse($payrollReport->period_start_date)->format('M d, Y');
        $periodEnd = Carbon::parse($payrollReport->period_end_date)->format('M d, Y');

        $pdf = Pdf::loadView('payroll.pdf', [
            'report' => $payrollReport,
            'company' => $company,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ])->setPaper('a4', 'landscape')->setOption('enable-local-file-access', true);

        $filename = 'payroll-report-'.Carbon::parse($payrollReport->period_start_date)->format('Y-m-d').'-to-'.Carbon::parse($payrollReport->period_end_date)->format('Y-m-d').'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Get saved payroll reports (for Wise).
     */
    public function getSavedPayrollReports(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $reports = PayrollReport::where('company_id', $user->company_id)
                ->with(['items.user:id,wise_account,wise_currency', 'createdBy:id,name'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Use current wise_account from users table for display (overrides stored value)
            // Treat net_pay 0 as sent (nothing to send)
            foreach ($reports as $report) {
                foreach ($report->items as $item) {
                    $currentWiseAccount = $item->user?->wise_account ?? $item->wise_account;
                    $item->setAttribute('wise_account', $currentWiseAccount ?: null);
                    if ((float) ($item->net_pay ?? 0) == 0) {
                        $item->setAttribute('wise_status', 'sent');
                    }
                }
            }

            $wiseService = new WiseService($user->company_id);
            $wiseConfigured = $wiseService->isConfigured();

            $wiseBalances = [];
            if ($wiseConfigured) {
                $balanceResult = $wiseService->getBalances();
                if ($balanceResult['success'] && ! empty($balanceResult['balances'])) {
                    $wiseBalances = $balanceResult['balances'];
                }
            }

            if ($wiseConfigured) {
                foreach ($reports as $report) {
                    $reportCurrency = strtoupper($report->currency ?? 'USD');
                    foreach ($report->items as $item) {
                        if (! empty($item->wise_transfer_id) && is_numeric($item->wise_transfer_id)) {
                            $result = $wiseService->getTransferStatus((int) $item->wise_transfer_id);
                            $item->setAttribute('wise_api_status', $result['success'] ? ($result['status'] ?? null) : null);
                            $item->setAttribute('wise_status_label', $item->wise_api_status
                                ? WiseService::formatWiseStatus($item->wise_api_status)
                                : ($item->wise_status === 'failed' ? 'Failed' : null));
                        }
                        // Convert net_pay to employee's wise_currency when different from report currency
                        // Uses Wise Quote API so displayed amount reflects actual receive amount (after fees)
                        $employeeCurrency = strtoupper(trim($item->user?->wise_currency ?? '') ?: $reportCurrency);
                        if ($reportCurrency !== $employeeCurrency) {
                            $netPay = (float) ($item->net_pay ?? 0);
                            $conv = $wiseService->convertAmount($reportCurrency, $employeeCurrency, $netPay);
                            if ($conv['success'] && isset($conv['target_amount'])) {
                                $item->setAttribute('net_pay_display', $conv['target_amount']);
                                $item->setAttribute('currency_display', $employeeCurrency);
                                $item->setAttribute('fee_amount', $conv['fee_amount'] ?? 0);
                                $item->setAttribute('fee_currency', $conv['fee_currency'] ?? $reportCurrency);
                            } else {
                                $item->setAttribute('net_pay_display', $netPay);
                                $item->setAttribute('currency_display', $reportCurrency);
                                $item->setAttribute('fee_amount', 0);
                                $item->setAttribute('fee_currency', $reportCurrency);
                            }
                        } else {
                            $item->setAttribute('net_pay_display', (float) ($item->net_pay ?? 0));
                            $item->setAttribute('currency_display', $reportCurrency);
                            $item->setAttribute('fee_amount', 0);
                            $item->setAttribute('fee_currency', $reportCurrency);
                        }
                    }
                }
            } else {
                foreach ($reports as $report) {
                    $reportCurrency = strtoupper($report->currency ?? 'USD');
                    foreach ($report->items as $item) {
                        $item->setAttribute('net_pay_display', (float) ($item->net_pay ?? 0));
                        $item->setAttribute('currency_display', $reportCurrency);
                        $item->setAttribute('fee_amount', 0);
                        $item->setAttribute('fee_currency', $reportCurrency);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $reports,
                'wise_configured' => $wiseConfigured,
                'wise_balances' => $wiseBalances,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update employee required work hours (from payroll report).
     */
    public function updateEmployeeRequiredWorkHours(Request $request, User $employee)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            if ($employee->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            $validated = $request->validate([
                'required_work_hours' => 'required|numeric|min:0|max:999',
            ]);

            $employee->required_work_hours = $validated['required_work_hours'];
            $employee->save();

            return response()->json([
                'success' => true,
                'message' => 'Required work hours updated successfully.',
                'data' => ['required_work_hours' => floatval($employee->required_work_hours)],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an employee's base salary (monthly salary) used by payroll calculations.
     */
    public function updateEmployeeBaseSalary(Request $request, User $employee): \Illuminate\Http\JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            if ($employee->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            $validated = $request->validate([
                'base_salary' => 'required|numeric|min:0|max:99999999.99',
            ]);

            $employee->salary = $validated['base_salary'];
            $employee->save();

            return response()->json([
                'success' => true,
                'message' => 'Base salary updated successfully.',
                'data' => ['base_salary' => floatval($employee->salary)],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an employee's client invoice amount (Bill Amount) used by payroll calculations.
     */
    public function updateEmployeeClientInvoiceAmount(Request $request, User $employee): \Illuminate\Http\JsonResponse
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            if ($employee->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            $validated = $request->validate([
                'client_invoice_amount' => 'required|numeric|min:0|max:99999999.99',
            ]);

            $employee->client_invoice_amount = $validated['client_invoice_amount'];
            $employee->save();

            return response()->json([
                'success' => true,
                'message' => 'Bill amount updated successfully.',
                'data' => ['client_invoice_amount' => floatval($employee->client_invoice_amount)],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save payroll report for Wise payroll sending (per employee).
     */
    public function savePayrollReport(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'report_data' => 'required|array',
                'report_data.*.employee_id' => 'required|exists:users,id',
                'report_data.*.employee_name' => 'required|string',
                'report_data.*.net_pay' => 'required|numeric|min:0',
                'report_data.*.gross_pay' => 'required|numeric|min:0',
                'report_data.*.base_salary' => 'nullable|numeric|min:0',
                'report_data.*.hours_worked' => 'nullable|numeric|min:0',
                'report_data.*.required_hours' => 'nullable|numeric|min:0',
                'report_data.*.overtime_hours' => 'nullable|numeric|min:0',
                'report_data.*.allowances' => 'nullable|numeric|min:0',
                'report_data.*.deductions' => 'nullable|numeric|min:0',
            ]);

            $reportData = $validated['report_data'];

            // Verify all employees belong to the same company
            $employeeIds = collect($reportData)->pluck('employee_id')->unique();
            $employees = User::whereIn('id', $employeeIds)
                ->where('company_id', $user->company_id)
                ->get()
                ->keyBy('id');

            if ($employees->count() !== $employeeIds->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more employees do not belong to your company.',
                ], 403);
            }

            $totalAmount = collect($reportData)->sum('net_pay');

            $payrollReport = DB::transaction(function () use ($user, $validated, $reportData, $employees, $totalAmount) {
                $report = PayrollReport::create([
                    'company_id' => $user->company_id,
                    'period_start_date' => $validated['start_date'],
                    'period_end_date' => $validated['end_date'],
                    'total_amount' => $totalAmount,
                    'currency' => 'USD',
                    'status' => 'ready_for_wise',
                    'created_by' => $user->id,
                ]);

                foreach ($reportData as $item) {
                    $employee = $employees->get($item['employee_id']);

                    $netPay = (float) ($item['net_pay'] ?? 0);
                    PayrollReportItem::create([
                        'payroll_report_id' => $report->id,
                        'user_id' => $item['employee_id'],
                        'employee_name' => $item['employee_name'],
                        'wise_account' => $employee?->wise_account,
                        'net_pay' => $item['net_pay'],
                        'gross_pay' => $item['gross_pay'] ?? 0,
                        'base_salary' => $item['base_salary'] ?? 0,
                        'hours_worked' => $item['hours_worked'] ?? 0,
                        'required_hours' => $item['required_hours'] ?? 0,
                        'overtime_hours' => $item['overtime_hours'] ?? 0,
                        'allowances' => $item['allowances'] ?? 0,
                        'deductions' => $item['deductions'] ?? 0,
                        'currency' => 'USD',
                        'wise_status' => $netPay == 0 ? 'sent' : 'pending',
                    ]);
                }

                return $report->load('items');
            });

            return response()->json([
                'success' => true,
                'message' => 'Payroll report saved successfully. Ready for Wise payroll sending.',
                'data' => $payrollReport,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a single payroll report to Wise.
     */
    public function sendPayrollToWise(PayrollReport $payrollReport)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            if ($payrollReport->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            $wiseService = new WiseService($user->company_id);
            if ($wiseService->isConfigured()) {
                $failed = [];
                foreach ($payrollReport->items as $item) {
                    if (($item->wise_status ?? 'pending') === 'sent') {
                        continue;
                    }
                    $result = $wiseService->sendPayrollItem($item, $user->company_id);
                    if (! $result['success']) {
                        $failed[] = $item->employee_name.': '.($result['error'] ?? 'Unknown error');
                    }
                }
                if (! empty($failed)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some transfers failed: '.implode('; ', $failed),
                    ], 422);
                }
            }
            $payrollReport->update(['status' => 'sent']);
            $payrollReport->items()->where('wise_status', '!=', 'sent')->update(['wise_status' => 'sent']);

            return response()->json([
                'success' => true,
                'message' => 'Payroll report sent to Wise successfully.',
                'data' => $payrollReport->fresh(['items']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk send multiple payroll reports to Wise.
     */
    public function bulkSendPayrollToWise(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $validated = $request->validate([
                'report_ids' => 'required|array',
                'report_ids.*' => 'required|integer|exists:payroll_reports,id',
            ]);

            $reports = PayrollReport::whereIn('id', $validated['report_ids'])
                ->where('company_id', $user->company_id)
                ->get();

            if ($reports->count() !== count($validated['report_ids'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more reports do not belong to your company.',
                ], 403);
            }

            $wiseService = new WiseService($user->company_id);
            $sentCount = 0;
            $errors = [];

            foreach ($reports as $report) {
                $reportErrors = [];
                if ($wiseService->isConfigured()) {
                    foreach ($report->items as $item) {
                        if (($item->wise_status ?? 'pending') === 'sent') {
                            continue;
                        }
                        $result = $wiseService->sendPayrollItem($item, $user->company_id);
                        if (! $result['success']) {
                            $reportErrors[] = $item->employee_name.': '.($result['error'] ?? 'Unknown');
                        }
                    }
                    $errors = array_merge($errors, $reportErrors);
                } else {
                    $report->items()->update(['wise_status' => 'sent']);
                }
                if (empty($reportErrors)) {
                    $report->update(['status' => 'sent']);
                    $sentCount++;
                }
            }

            if (! empty($errors)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some transfers failed: '.implode('; ', array_slice($errors, 0, 5)).(count($errors) > 5 ? ' ...' : ''),
                    'sent_count' => $sentCount,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully sent {$sentCount} report(s) to Wise.",
                'sent_count' => $sentCount,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send a single payroll report item (employee) to Wise.
     */
    public function sendPayrollItemToWise(PayrollReportItem $payrollReportItem)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $report = $payrollReportItem->payrollReport;
            if (! $report || $report->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            $wiseService = new WiseService($user->company_id);
            if ($wiseService->isConfigured()) {
                $result = $wiseService->sendPayrollItem($payrollReportItem, $user->company_id);
                if (! $result['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['error'] ?? 'Failed to send to Wise.',
                    ], 422);
                }
            } else {
                $payrollReportItem->update(['wise_status' => 'sent']);
            }

            // Update report status based on items: if all sent → sent
            $allSent = $report->items()->where('wise_status', '!=', 'sent')->count() === 0;
            if ($allSent) {
                $report->update(['status' => 'sent']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee payroll sent to Wise successfully.',
                'data' => $report->fresh(['items']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a saved payroll report.
     */
    public function deletePayrollReport(PayrollReport $payrollReport)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            if ($payrollReport->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            // Only block delete if any item was actually sent (sent with net_pay > 0); 0 net pay "sent" is allowed to delete
            $hasActuallySentItems = $payrollReport->items()
                ->where('wise_status', 'sent')
                ->where('net_pay', '>', 0)
                ->exists();
            if ($hasActuallySentItems) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a report that contains sent records.',
                ], 422);
            }

            $payrollReport->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payroll report deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a payroll report item (employee from report).
     */
    public function deletePayrollReportItem(PayrollReportItem $payrollReportItem)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $report = $payrollReportItem->payrollReport;
            if (! $report || $report->company_id !== $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access.',
                ], 403);
            }

            // Only block delete if actually sent (net_pay > 0); 0 net pay "sent" can be deleted
            if ($payrollReportItem->wise_status === 'sent' && (float) ($payrollReportItem->net_pay ?? 0) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a record that has been sent.',
                ], 422);
            }

            $payrollReportItem->delete();

            // Update report total_amount and status if no items left
            $remainingItems = $report->items()->count();
            if ($remainingItems === 0) {
                $report->delete();
            } else {
                $report->total_amount = $report->items()->sum('net_pay');
                $report->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee removed from report successfully.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk delete payroll reports.
     */
    public function bulkDeletePayrollReports(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->company_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated or no company assigned.',
                ], 401);
            }

            $validated = $request->validate([
                'report_ids' => 'required|array',
                'report_ids.*' => 'required|integer|exists:payroll_reports,id',
            ]);

            $reports = PayrollReport::whereIn('id', $validated['report_ids'])
                ->where('company_id', $user->company_id)
                ->get();

            if ($reports->count() !== count($validated['report_ids'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or more reports do not belong to your company.',
                ], 403);
            }

            // Only block if any report has items actually sent (sent with net_pay > 0)
            $reportsWithActuallySent = $reports->filter(fn ($r) => $r->items()
                ->where('wise_status', 'sent')
                ->where('net_pay', '>', 0)
                ->exists());
            if ($reportsWithActuallySent->isNotEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete reports that contain sent records. Remove sent reports from selection.',
                ], 422);
            }

            $deletedCount = 0;
            foreach ($reports as $report) {
                $report->delete();
                $deletedCount++;
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} report(s).",
                'deleted_count' => $deletedCount,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }
}
