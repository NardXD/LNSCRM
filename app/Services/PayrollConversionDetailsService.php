<?php

namespace App\Services;

class PayrollConversionDetailsService
{
    public function detailKey(int $employeeId, int $clientId): string
    {
        return $employeeId.':'.$clientId;
    }

    /**
     * @return array{employee_id: int, client_id: ?int, legacy: bool}
     */
    public function parseKey(string $key): array
    {
        if (str_contains($key, ':')) {
            [$employeeId, $clientId] = explode(':', $key, 2);

            return [
                'employee_id' => (int) $employeeId,
                'client_id' => (int) $clientId,
                'legacy' => false,
            ];
        }

        return [
            'employee_id' => (int) $key,
            'client_id' => null,
            'legacy' => true,
        ];
    }

    /**
     * @param  array<string|int, mixed>  $details
     */
    public function sumForEmployee(array $details, int $employeeId, string $field): float
    {
        $total = 0.0;

        foreach ($details as $key => $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $parsed = $this->parseKey((string) $key);
            if ($parsed['employee_id'] !== $employeeId) {
                continue;
            }
            $total += (float) ($detail[$field] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * @param  array<string|int, mixed>  $details
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function groupByEmployee(array $details): array
    {
        $grouped = [];

        foreach ($details as $key => $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $parsed = $this->parseKey((string) $key);
            $employeeId = $parsed['employee_id'];
            if ($employeeId < 1) {
                continue;
            }
            $clientId = $parsed['client_id'] ?? (int) ($detail['client_id'] ?? 0);
            if ($clientId < 1) {
                $grouped[$employeeId]['__legacy'] = $detail;

                continue;
            }
            $grouped[$employeeId][$clientId] = $detail;
        }

        return $grouped;
    }

    /**
     * @param  array<string|int, mixed>  $details
     * @return list<int>
     */
    public function clientIdsForEmployee(array $details, int $employeeId): array
    {
        $clientIds = [];

        foreach ($details as $key => $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $parsed = $this->parseKey((string) $key);
            if ($parsed['employee_id'] !== $employeeId) {
                continue;
            }
            $clientId = $parsed['client_id'] ?? (int) ($detail['client_id'] ?? 0);
            if ($clientId > 0) {
                $clientIds[] = $clientId;
            }
        }

        return array_values(array_unique($clientIds));
    }

    /**
     * @param  array<string|int, float>  $billAmountByClientId
     * @return array<string, float>
     */
    public function allocateProportionally(float $amount, array $billAmountByClientId): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0 || $billAmountByClientId === []) {
            return [];
        }

        $positive = [];
        foreach ($billAmountByClientId as $clientId => $weight) {
            $w = round((float) $weight, 2);
            if ($w > 0) {
                $positive[(string) $clientId] = $w;
            }
        }

        if ($positive === []) {
            $keys = array_keys($billAmountByClientId);
            $n = count($keys);
            if ($n < 1) {
                return [];
            }
            $share = round($amount / $n, 2);
            $allocated = [];
            $running = 0.0;
            foreach ($keys as $i => $clientId) {
                $key = (string) $clientId;
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
     * @param  array<string|int, mixed>  $details
     * @param  array<string|int, float>  $billAmountByClientId
     * @return array<string|int, mixed>
     */
    public function upsertEmployeeClientBreakdown(
        array $details,
        int $employeeId,
        array $billAmountByClientId,
        ?float $netPay,
        ?float $hoursWorked,
        ?float $baseSalary
    ): array {
        $legacyKey = (string) $employeeId;
        if (isset($details[$legacyKey]) && is_array($details[$legacyKey])) {
            unset($details[$legacyKey]);
        }

        $netPayAlloc = $this->allocateProportionally((float) ($netPay ?? 0), $billAmountByClientId);
        $hoursAlloc = $this->allocateProportionally((float) ($hoursWorked ?? 0), $billAmountByClientId);

        foreach ($billAmountByClientId as $clientId => $billAmount) {
            $clientId = (int) $clientId;
            if ($clientId < 1) {
                continue;
            }
            $key = $this->detailKey($employeeId, $clientId);
            $existing = is_array($details[$key] ?? null) ? $details[$key] : [];
            $details[$key] = array_merge($existing, [
                'employee_id' => $employeeId,
                'client_id' => $clientId,
                'bill_amount' => round((float) $billAmount, 2),
                'net_pay' => $netPayAlloc[(string) $clientId] ?? 0.0,
                'hours_worked' => $hoursAlloc[(string) $clientId] ?? 0.0,
                'base_salary' => $baseSalary,
            ]);
        }

        return $details;
    }

    /**
     * Store payroll conversion values for a single employee/client pair.
     * Existing records for other clients are left unchanged.
     *
     * @param  array<string|int, mixed>  $details
     * @return array<string|int, mixed>
     */
    public function upsertEmployeeClientConversion(
        array $details,
        int $employeeId,
        int $clientId,
        float $billAmount,
        float $netPay,
        float $baseSalary,
        ?float $hoursWorked = null,
        ?float $commission = null,
    ): array {
        $legacyKey = (string) $employeeId;
        if (isset($details[$legacyKey]) && is_array($details[$legacyKey])) {
            unset($details[$legacyKey]);
        }

        if ($clientId < 1) {
            return $details;
        }

        $key = $this->detailKey($employeeId, $clientId);
        $existing = is_array($details[$key] ?? null) ? $details[$key] : [];

        $record = array_merge($existing, [
            'employee_id' => $employeeId,
            'client_id' => $clientId,
            'bill_amount' => round($billAmount, 2),
            'net_pay' => round($netPay, 2),
            'base_salary' => round($baseSalary, 2),
        ]);

        if ($hoursWorked !== null) {
            $record['hours_worked'] = round($hoursWorked, 2);
        }

        if ($commission !== null) {
            $record['commission'] = round($commission, 2);
        }

        $details[$key] = $record;

        return $details;
    }

    /**
     * Re-split an employee's net pay across clients without changing the employee total.
     *
     * @param  array<string|int, mixed>  $details
     * @param  array<string|int, float>  $billAmountByClientId
     * @return array<string|int, mixed>
     */
    public function reallocateEmployeeNetPayAmongClients(
        array $details,
        int $employeeId,
        array $billAmountByClientId,
        ?float $netPayTotal = null
    ): array {
        $total = $netPayTotal ?? $this->sumForEmployee($details, $employeeId, 'net_pay');
        $total = round((float) $total, 2);
        if ($total <= 0 || $billAmountByClientId === []) {
            return $details;
        }

        $netPayAlloc = $this->allocateProportionally($total, $billAmountByClientId);
        foreach ($billAmountByClientId as $clientId => $billAmount) {
            $clientId = (int) $clientId;
            if ($clientId < 1) {
                continue;
            }
            $key = $this->detailKey($employeeId, $clientId);
            $existing = is_array($details[$key] ?? null) ? $details[$key] : [];
            $details[$key] = array_merge($existing, [
                'employee_id' => $employeeId,
                'client_id' => $clientId,
                'bill_amount' => round((float) $billAmount, 2),
                'net_pay' => $netPayAlloc[(string) $clientId] ?? 0.0,
            ]);
        }

        return $details;
    }

    /**
     * @param  array<string|int, mixed>  $details
     */
    public function removeEmployeeClient(array $details, int $employeeId, int $clientId): array
    {
        unset($details[$this->detailKey($employeeId, $clientId)]);

        return $details;
    }

    /**
     * @param  array<string|int, mixed>  $details
     */
    public function removeEmployee(array $details, int $employeeId): array
    {
        foreach (array_keys($details) as $key) {
            $parsed = $this->parseKey((string) $key);
            if ($parsed['employee_id'] === $employeeId) {
                unset($details[$key]);
            }
        }

        return $details;
    }

    /**
     * @param  array<string|int, mixed>  $details
     */
    public function employeeHasAnyDetail(array $details, int $employeeId): bool
    {
        foreach (array_keys($details) as $key) {
            $parsed = $this->parseKey((string) $key);
            if ($parsed['employee_id'] === $employeeId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string|int, mixed>  $details
     */
    public function totalSum(array $details, string $field): float
    {
        $total = 0.0;

        foreach ($details as $key => $detail) {
            if (! is_array($detail)) {
                continue;
            }
            $parsed = $this->parseKey((string) $key);
            if ($parsed['legacy']) {
                $total += (float) ($detail[$field] ?? 0);

                continue;
            }
            $total += (float) ($detail[$field] ?? 0);
        }

        return round($total, 2);
    }
}
