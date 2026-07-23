@push('scripts')
<script>
(function () {
    const pnlCanLoadData = {{ ($companyHasPnlFeature ?? true) && auth()->user()?->hasPermission('view_pnl') ? 'true' : 'false' }};

    let reportData = [];
    let pnlCollectionsMeta = {
        payroll_invoiced: 0,
        billing_invoiced: 0,
        billing_unallocated: 0,
        total: 0,
        paid_payroll_invoiced: 0,
        paid_billing_invoiced: 0,
        paid_billing_unallocated: 0,
        paid_total: 0,
    };
    let pnlWeekSnapshots = [];
    let pnlApiExpensesBase = 0;
    let pnlManualExpenses = [];
    let pnlEditingExpenseId = null;
    let pnlByClient = [];
    let pnlBySalesRep = [];
    let pnlPayrollSummary = {
        total_paid_net_pay: 0,
        total_unpaid_net_pay: 0,
        total_paid_commission: 0,
        total_unpaid_commission: 0,
    };
    let pnlNetProfitMeta = {
        paid: 0,
        unpaid: 0,
    };

    function pnlCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }

    /** Weekly snapshots for the 12-month window (monthly trend only; KPI/table stay on selected month). */
    let pnlTrend12WeekSnapshots = [];
    let pnlTrend12ReportData = [];
    let pnlTrend12CollectionsTotal = 0;
    let pnlTrend12ApiExpensesBase = 0;

    function formatPayrollMoney(amount) {
        const n = parseFloat(amount);
        const v = isNaN(n) ? 0 : n;
        return '$' + v.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function roundMoney(n) {
        return Math.round((parseFloat(n) || 0) * 100) / 100;
    }

    function pnlUnpaidCollectionsAmount() {
        return roundMoney(
            (parseFloat(pnlCollectionsMeta.total) || 0)
            - (parseFloat(pnlCollectionsMeta.paid_total) || 0)
        );
    }

    function pnlUnpaidCollectionsLabel(amount) {
        if (roundMoney(amount) <= 0) {
            return '';
        }

        return 'Total collections (unpaid): ' + formatPayrollMoney(amount);
    }

    function pnlUnpaidPayrollLabel(amount) {
        if (roundMoney(amount) <= 0) {
            return '';
        }

        return 'Total payroll (unpaid): ' + formatPayrollMoney(amount);
    }

    function pnlUnpaidNetProfitLabel(amount) {
        if (roundMoney(amount) <= 0) {
            return '';
        }

        return 'Net profit (unpaid): ' + formatPayrollMoney(amount);
    }

    function pnlUnpaidCommissionLabel(amount) {
        if (roundMoney(amount) <= 0) {
            return '';
        }

        return 'Commission (unpaid): ' + formatPayrollMoney(amount);
    }

    function pnlSplitByCollectionsRatio(amount, paidColl, totalColl) {
        const total = roundMoney(totalColl);
        const paid = roundMoney(paidColl);
        const value = roundMoney(amount);
        if (total <= 0) {
            return { paid: 0, unpaid: value };
        }
        const paidAmount = roundMoney(value * (paid / total));
        return { paid: paidAmount, unpaid: roundMoney(value - paidAmount) };
    }

    function renderPnlPaidUnpaidCell(paid, unpaid) {
        const paidVal = roundMoney(paid);
        const unpaidVal = roundMoney(unpaid);
        let html = '<span class="payroll-pnl-cell-main">' + formatPayrollMoney(paidVal) + '</span>';
        if (unpaidVal > 0) {
            html += '<span class="payroll-pnl-cell-sub">' + formatPayrollMoney(unpaidVal) + ' unpaid</span>';
        }
        return html;
    }

    function sumPaidUnpaidField(rows, paidField, unpaidField) {
        return {
            paid: roundMoney(rows.reduce(function (s, r) { return s + roundMoney(r[paidField]); }, 0)),
            unpaid: roundMoney(rows.reduce(function (s, r) { return s + roundMoney(r[unpaidField]); }, 0)),
        };
    }

    function pnlNetProfitFromComponents(collections, payroll, commission, expenses) {
        return roundMoney(
            roundMoney(collections)
            - roundMoney(payroll)
            - roundMoney(commission)
            - roundMoney(expenses)
        );
    }

    /** Internal expenses / CC are deducted in full on the paid side only. */
    function pnlExpenseAmountSplit(amount) {
        const value = roundMoney(amount);
        return { paid: value, unpaid: 0 };
    }

    function enrichPeriodRowPaidUnpaid(row, expensesSplitOverride) {
        const collections = roundMoney(row.collections);
        const paidCollections = roundMoney(row.paid_collections);
        const unpaidCollections = roundMoney(collections - paidCollections);
        const payrollInvoiced = roundMoney(row.payroll_invoiced);
        const paidPayrollInvoiced = roundMoney(row.paid_payroll_invoiced);
        let payrollSplit;
        let commissionSplit;
        if (payrollInvoiced > 0) {
            payrollSplit = pnlSplitByCollectionsRatio(row.payroll, paidPayrollInvoiced, payrollInvoiced);
            commissionSplit = pnlSplitByCollectionsRatio(row.commission, paidPayrollInvoiced, payrollInvoiced);
        } else if (collections > 0) {
            payrollSplit = pnlSplitByCollectionsRatio(row.payroll, paidCollections, collections);
            commissionSplit = pnlSplitByCollectionsRatio(row.commission, paidCollections, collections);
        } else {
            payrollSplit = { paid: 0, unpaid: roundMoney(row.payroll) };
            commissionSplit = { paid: 0, unpaid: roundMoney(row.commission) };
        }
        const expensesSplit = expensesSplitOverride || pnlExpenseAmountSplit(row.expenses);
        const paidNetProfit = pnlNetProfitFromComponents(
            paidCollections,
            payrollSplit.paid,
            commissionSplit.paid,
            expensesSplit.paid
        );
        const unpaidNetProfit = pnlNetProfitFromComponents(
            unpaidCollections,
            payrollSplit.unpaid,
            commissionSplit.unpaid,
            expensesSplit.unpaid
        );

        return Object.assign({}, row, {
            paid_collections: paidCollections,
            unpaid_collections: unpaidCollections,
            paid_payroll: payrollSplit.paid,
            unpaid_payroll: payrollSplit.unpaid,
            paid_commission: commissionSplit.paid,
            unpaid_commission: commissionSplit.unpaid,
            paid_expenses: expensesSplit.paid,
            unpaid_expenses: expensesSplit.unpaid,
            paid_net_profit: paidNetProfit,
            unpaid_net_profit: unpaidNetProfit,
        });
    }

    function setPnlKpiSub(el, text) {
        if (!el) {
            return;
        }

        if (text) {
            el.textContent = text;
            el.hidden = false;
        } else {
            el.textContent = '';
            el.hidden = true;
        }
    }

    function clearPnlKpiUnpaidSubs() {
        setPnlKpiSub(document.getElementById('pnlKpiCollectionsUnpaid'), '');
        setPnlKpiSub(document.getElementById('pnlKpiPayrollUnpaid'), '');
        setPnlKpiSub(document.getElementById('pnlKpiCommissionUnpaid'), '');
        setPnlKpiSub(document.getElementById('pnlKpiExpensesUnpaid'), '');
        setPnlKpiSub(document.getElementById('pnlKpiNetProfitUnpaid'), '');
    }

    /**
     * P&L Net Pay = sum of net_pay (from payroll_period_invoices.conversion_details)
     * for the employee's linked invoices that are paid within the selected period.
     * Computed by the backend; we just read it back here.
     */
    function pnlEffectiveBaseSalary(emp) {
        return roundMoney(emp.pnl_net_pay_paid);
    }

    function syncPnlRowDerivedCosts(emp) {
        const inv = roundMoney(emp.pnl_client_invoice);
        const payrollCost = pnlEffectiveBaseSalary(emp);
        emp.pnl_effective_base_salary = payrollCost;
        emp.pnl_margin = roundMoney(inv - payrollCost);
    }

    function pnlSelectedYearMonth() {
        const end = document.getElementById('pnlDateEnd')?.value;
        if (end && end.length >= 7) {
            const parts = end.split('-');
            const y = parseInt(parts[0], 10);
            const m = parseInt(parts[1], 10);
            if (Number.isFinite(y) && m >= 1 && m <= 12) return { year: y, month: m };
        }
        const now = new Date();
        return { year: now.getFullYear(), month: now.getMonth() + 1 };
    }

    function pnlDateRangeFromFilter() {
        return { start: pnlDateStart(), end: pnlDateEnd() };
    }

    /** First day of month (year, month 1–12) to last day of selected month — 12 calendar months for trend API. */
    function pnlTwelveMonthRangeEndingSelected() {
        const { year, month } = pnlSelectedYearMonth();
        const pad = function (n) { return String(n).padStart(2, '0'); };
        const endLast = new Date(year, month, 0).getDate();
        const end = year + '-' + pad(month) + '-' + pad(endLast);
        let sy = year;
        let sm = month;
        for (let k = 0; k < 11; k++) {
            sm--;
            if (sm < 1) {
                sm = 12;
                sy--;
            }
        }
        const start = sy + '-' + pad(sm) + '-01';
        return { start, end };
    }

    /** Oldest → newest month keys (YYYY-MM), length n, ending at selected month. */
    function pnlLastNMonthKeysEndingSelected(n) {
        const { year, month } = pnlSelectedYearMonth();
        const keys = [];
        for (let i = n - 1; i >= 0; i--) {
            let yy = year;
            let mm = month - i;
            while (mm < 1) {
                mm += 12;
                yy--;
            }
            keys.push(yy + '-' + String(mm).padStart(2, '0'));
        }
        return keys;
    }

    function pnlDateStart() {
        return document.getElementById('pnlDateStart')?.value || '';
    }

    function pnlDateEnd() {
        return document.getElementById('pnlDateEnd')?.value || '';
    }

    function readLegacySessionExpenses() {
        const a = pnlDateStart();
        const b = pnlDateEnd();
        if (!a || !b || typeof sessionStorage === 'undefined') {
            return [];
        }
        const v2Key = 'pnl_manual_exp_v2_' + a + '_' + b;
        const v1Key = 'pnl_manual_exp_v1_' + a + '_' + b;
        try {
            const raw = sessionStorage.getItem(v2Key);
            if (raw) {
                const arr = JSON.parse(raw);
                if (Array.isArray(arr) && arr.length) {
                    sessionStorage.removeItem(v2Key);
                    return arr;
                }
            }
            const v1 = parseFloat(sessionStorage.getItem(v1Key) || '0');
            if (!isNaN(v1) && v1 > 0) {
                sessionStorage.removeItem(v1Key);
                return [{ date: a, amount: roundMoney(v1), notes: 'Imported balance' }];
            }
        } catch (e) { /* ignore */ }
        return [];
    }

    function readManualExpensesList() {
        return Array.isArray(pnlManualExpenses) ? pnlManualExpenses : [];
    }

    async function fetchPnlManualExpenses(startDate, endDate) {
        const params = new URLSearchParams({ start_date: startDate, end_date: endDate });
        const clientId = pnlActiveClientId();
        if (clientId) {
            params.set('client_id', String(clientId));
        }
        const response = await fetch('/api/payroll/pnl-expenses?' + params.toString());
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Failed to load expenses.');
        }
        pnlManualExpenses = Array.isArray(result.data) ? result.data : [];

        const legacy = readLegacySessionExpenses();
        if (legacy.length) {
            for (const entry of legacy) {
                if (!entry || !entry.date || entry.date < startDate || entry.date > endDate) {
                    continue;
                }
                const amt = roundMoney(parseFloat(entry.amount) || 0);
                if (amt <= 0) {
                    continue;
                }
                const saveResponse = await fetch('/api/payroll/pnl-expenses', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': pnlCsrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        date: entry.date,
                        amount: amt,
                        notes: entry.notes || '',
                        start_date: startDate,
                        end_date: endDate,
                    }),
                });
                if (saveResponse.ok) {
                    const saveResult = await saveResponse.json();
                    if (saveResult.success && saveResult.data) {
                        pnlManualExpenses.push(saveResult.data);
                    }
                }
            }
        }
    }

    function manualExpensesInSelectedMonth() {
        const start = pnlDateStart();
        const end = pnlDateEnd();
        const clientId = pnlActiveClientId();
        return readManualExpensesList().filter(function (e) {
            if (!e || !e.date || e.date < start || e.date > end) {
                return false;
            }
            if (!clientId) {
                return true;
            }
            return parseInt(e.client_id, 10) === clientId;
        });
    }

    function manualExpenseTotalInMonth() {
        return roundMoney(manualExpensesInSelectedMonth().reduce(function (s, e) {
            return s + roundMoney(parseFloat(e.amount) || 0);
        }, 0));
    }

    function pnlManualExpenseEntryPaidUnpaidSplit(entry) {
        return pnlExpenseAmountSplit(parseFloat(entry?.amount) || 0);
    }

    function pnlManualExpensePaidUnpaidSplit() {
        return pnlExpenseAmountSplit(manualExpenseTotalInMonth());
    }

    function pnlTotalExpensePaidUnpaidSplit() {
        return pnlExpenseAmountSplit(totalPnlExpenses());
    }

    function manualExpensePaidUnpaidBySnapKey(snaps, isMonthly) {
        const map = new Map();
        snaps.forEach(function (snap) {
            map.set(snap.week_key, { paid: 0, unpaid: 0 });
        });
        manualExpensesInSelectedMonth().forEach(function (entry) {
            const amt = roundMoney(parseFloat(entry.amount) || 0);
            if (amt <= 0) {
                return;
            }
            const snapKey = findSnapKeyForExpense(entry.date, snaps, isMonthly);
            if (!snapKey || !map.has(snapKey)) {
                return;
            }
            const split = pnlManualExpenseEntryPaidUnpaidSplit(entry);
            const cur = map.get(snapKey);
            cur.paid = roundMoney(cur.paid + split.paid);
            cur.unpaid = roundMoney(cur.unpaid + split.unpaid);
        });
        return map;
    }

    function totalPnlExpenses() {
        return roundMoney(pnlApiExpensesBase + manualExpenseTotalInMonth());
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str == null ? '' : String(str);
        return d.innerHTML;
    }

    function pnlTitleCase(str) {
        if (str == null || String(str).trim() === '') {
            return '—';
        }

        return String(str)
            .trim()
            .split(/\s+/)
            .map(function (word) {
                if (!word) {
                    return word;
                }

                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            })
            .join(' ');
    }

    function pnlDisplayName(str) {
        return escapeHtml(pnlTitleCase(str));
    }

    function isoWeekMondayYmd(dateStr) {
        const d = new Date(dateStr + 'T12:00:00');
        const day = d.getDay();
        const diff = day === 0 ? -6 : 1 - day;
        d.setDate(d.getDate() + diff);
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function dateInIsoWeek(expStr, mondayYmd) {
        const t = new Date(expStr + 'T12:00:00').getTime();
        const start = new Date(mondayYmd + 'T12:00:00').getTime();
        const end = start + 6 * 86400000;
        return t >= start && t <= end;
    }

    function findSnapKeyForExpense(expDate, snaps, isMonthly) {
        if (!snaps.length) return null;
        if (isMonthly) {
            const mk = expDate.length >= 7 ? expDate.slice(0, 7) : '';
            if (mk && snaps.some(function (s) { return s.week_key === mk; })) return mk;
            return snaps[0].week_key;
        }
        const mon = isoWeekMondayYmd(expDate);
        if (snaps.some(function (s) { return s.week_key === mon; })) return mon;
        for (let i = 0; i < snaps.length; i++) {
            if (dateInIsoWeek(expDate, snaps[i].week_key)) return snaps[i].week_key;
        }
        return snaps[0].week_key;
    }

    function manualExpenseBySnapKey(snaps, isMonthly) {
        const map = new Map();
        snaps.forEach(function (s) { map.set(s.week_key, 0); });
        manualExpensesInSelectedMonth().forEach(function (e) {
            const amt = roundMoney(parseFloat(e.amount) || 0);
            if (amt <= 0) return;
            const k = findSnapKeyForExpense(e.date, snaps, isMonthly);
            if (k) map.set(k, roundMoney((map.get(k) || 0) + amt));
        });
        return map;
    }

    function pnlGranularity() {
        return document.getElementById('payrollPnlGranularity')?.value || 'weekly';
    }

    function monthKeyFromWeekMonday(ymd) {
        if (!ymd) return '';
        const d = new Date(ymd + 'T12:00:00');
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    }

    function monthLabel(ymKey) {
        const parts = String(ymKey).split('-');
        if (parts.length !== 2) return ymKey;
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const m = parseInt(parts[1], 10);
        return (months[m - 1] || parts[1]) + ' ' + parts[0];
    }

    function aggregateSnapshotsByMonth(snapshots) {
        const empty = () => ({
            collections: 0,
            paid_collections: 0,
            payroll_invoiced: 0,
            paid_payroll_invoiced: 0,
            net_pay: 0,
            commission: 0,
            paid_net_pay: 0,
            unpaid_net_pay: 0,
            paid_commission: 0,
            unpaid_commission: 0,
            expenses: 0,
            paid_expenses: 0,
            unpaid_expenses: 0,
            paid_net_profit: 0,
            unpaid_net_profit: 0,
        });
        const map = new Map();
        snapshots.forEach(w => {
            const mk = monthKeyFromWeekMonday(w.week_key);
            if (!mk) return;
            const cur = map.get(mk) || empty();
            cur.collections = roundMoney(cur.collections + (parseFloat(w.collections) || 0));
            cur.paid_collections = roundMoney(cur.paid_collections + (parseFloat(w.paid_collections) || 0));
            cur.payroll_invoiced = roundMoney(cur.payroll_invoiced + (parseFloat(w.payroll_invoiced) || 0));
            cur.paid_payroll_invoiced = roundMoney(cur.paid_payroll_invoiced + (parseFloat(w.paid_payroll_invoiced) || 0));
            cur.net_pay = roundMoney(cur.net_pay + (parseFloat(w.net_pay) || 0));
            cur.commission = roundMoney(cur.commission + (parseFloat(w.commission) || 0));
            cur.paid_net_pay = roundMoney(cur.paid_net_pay + (parseFloat(w.paid_net_pay) || 0));
            cur.unpaid_net_pay = roundMoney(cur.unpaid_net_pay + (parseFloat(w.unpaid_net_pay) || 0));
            cur.paid_commission = roundMoney(cur.paid_commission + (parseFloat(w.paid_commission) || 0));
            cur.unpaid_commission = roundMoney(cur.unpaid_commission + (parseFloat(w.unpaid_commission) || 0));
            cur.expenses = roundMoney(cur.expenses + (parseFloat(w.expenses) || 0));
            cur.paid_expenses = roundMoney(cur.paid_expenses + (parseFloat(w.paid_expenses) || 0));
            cur.unpaid_expenses = roundMoney(cur.unpaid_expenses + (parseFloat(w.unpaid_expenses) || 0));
            cur.paid_net_profit = roundMoney(cur.paid_net_profit + (parseFloat(w.paid_net_profit) || 0));
            cur.unpaid_net_profit = roundMoney(cur.unpaid_net_profit + (parseFloat(w.unpaid_net_profit) || 0));
            map.set(mk, cur);
        });
        return Array.from(map.entries())
            .sort((a, b) => a[0].localeCompare(b[0]))
            .map(([mk, v]) => ({
                week_key: mk,
                period_label: monthLabel(mk),
                collections: roundMoney(v.collections),
                paid_collections: roundMoney(v.paid_collections),
                payroll_invoiced: roundMoney(v.payroll_invoiced),
                paid_payroll_invoiced: roundMoney(v.paid_payroll_invoiced),
                net_pay: roundMoney(v.net_pay),
                commission: roundMoney(v.commission),
                paid_net_pay: roundMoney(v.paid_net_pay),
                unpaid_net_pay: roundMoney(v.unpaid_net_pay),
                paid_commission: roundMoney(v.paid_commission),
                unpaid_commission: roundMoney(v.unpaid_commission),
                expenses: roundMoney(v.expenses),
                paid_expenses: roundMoney(v.paid_expenses),
                unpaid_expenses: roundMoney(v.unpaid_expenses),
                paid_net_profit: roundMoney(v.paid_net_profit),
                unpaid_net_profit: roundMoney(v.unpaid_net_profit),
            }));
    }

    function mapPnlWeekSnapshot(r) {
        return {
            week_key: r.week_key,
            period_label: r.period_label || '',
            collections: parseFloat(r.collections) || 0,
            paid_collections: parseFloat(r.paid_collections) || 0,
            payroll_invoiced: parseFloat(r.payroll_invoiced) || 0,
            paid_payroll_invoiced: parseFloat(r.paid_payroll_invoiced) || 0,
            net_pay: r.net_pay != null ? parseFloat(r.net_pay) || 0 : null,
            commission: r.commission != null ? parseFloat(r.commission) || 0 : null,
            paid_net_pay: r.paid_net_pay != null ? parseFloat(r.paid_net_pay) || 0 : null,
            unpaid_net_pay: r.unpaid_net_pay != null ? parseFloat(r.unpaid_net_pay) || 0 : null,
            paid_commission: r.paid_commission != null ? parseFloat(r.paid_commission) || 0 : null,
            unpaid_commission: r.unpaid_commission != null ? parseFloat(r.unpaid_commission) || 0 : null,
            expenses: r.expenses != null ? parseFloat(r.expenses) || 0 : null,
            paid_expenses: r.paid_expenses != null ? parseFloat(r.paid_expenses) || 0 : null,
            unpaid_expenses: r.unpaid_expenses != null ? parseFloat(r.unpaid_expenses) || 0 : null,
            paid_net_profit: r.paid_net_profit != null ? parseFloat(r.paid_net_profit) || 0 : null,
            unpaid_net_profit: r.unpaid_net_profit != null ? parseFloat(r.unpaid_net_profit) || 0 : null,
        };
    }

    function snapHasBackendCosts(w) {
        return w.net_pay != null || w.commission != null;
    }

    function recomputePeriodRowNetProfit(row) {
        row.paid_net_profit = pnlNetProfitFromComponents(
            row.paid_collections,
            row.paid_payroll,
            row.paid_commission,
            row.paid_expenses
        );
        row.unpaid_net_profit = pnlNetProfitFromComponents(
            row.unpaid_collections,
            row.unpaid_payroll,
            row.unpaid_commission,
            row.unpaid_expenses
        );
        return row;
    }

    function reconcilePeriodRowsToExpenseTruth(rows) {
        if (!rows.length) {
            return rows;
        }

        const truthExpenses = pnlTotalExpensePaidUnpaidSplit();
        const reconciled = rows.map(recomputePeriodRowNetProfit);
        const last = reconciled[reconciled.length - 1];
        const curExpenses = sumPaidUnpaidField(reconciled, 'paid_expenses', 'unpaid_expenses');

        last.paid_expenses = roundMoney(last.paid_expenses + (truthExpenses.paid - curExpenses.paid));
        last.unpaid_expenses = 0;
        reconciled.forEach(recomputePeriodRowNetProfit);

        return reconciled;
    }

    function buildPeriodRowFromBackendSnap(w, manualExpSplit) {
        const collW = roundMoney(w.collections);
        const paidCollW = roundMoney(w.paid_collections);
        const unpaidCollW = roundMoney(collW - paidCollW);
        const manualPaid = roundMoney(manualExpSplit?.paid || 0);
        const apiSplit = pnlExpenseAmountSplit(roundMoney(w.expenses || 0));
        const paidExpW = roundMoney(apiSplit.paid + manualPaid);

        return recomputePeriodRowNetProfit({
            week_key: w.week_key,
            period_label: w.period_label,
            collections: collW,
            paid_collections: paidCollW,
            unpaid_collections: unpaidCollW,
            paid_payroll: roundMoney(w.paid_net_pay),
            unpaid_payroll: roundMoney(w.unpaid_net_pay),
            paid_commission: roundMoney(w.paid_commission),
            unpaid_commission: roundMoney(w.unpaid_commission),
            paid_expenses: paidExpW,
            unpaid_expenses: 0,
        });
    }

    function getPeriodSnapshotsForUi() {
        if (!pnlWeekSnapshots.length) return [];
        if (pnlGranularity() === 'monthly') {
            return aggregateSnapshotsByMonth(pnlWeekSnapshots);
        }
        return pnlWeekSnapshots.map(w => ({
            week_key: w.week_key,
            period_label: w.period_label || w.week_key,
            collections: roundMoney(w.collections),
            paid_collections: roundMoney(w.paid_collections),
            payroll_invoiced: roundMoney(w.payroll_invoiced),
            paid_payroll_invoiced: roundMoney(w.paid_payroll_invoiced),
            net_pay: w.net_pay != null ? roundMoney(w.net_pay) : null,
            commission: w.commission != null ? roundMoney(w.commission) : null,
            paid_net_pay: w.paid_net_pay != null ? roundMoney(w.paid_net_pay) : null,
            unpaid_net_pay: w.unpaid_net_pay != null ? roundMoney(w.unpaid_net_pay) : null,
            paid_commission: w.paid_commission != null ? roundMoney(w.paid_commission) : null,
            unpaid_commission: w.unpaid_commission != null ? roundMoney(w.unpaid_commission) : null,
            expenses: w.expenses != null ? roundMoney(w.expenses) : null,
            paid_expenses: w.paid_expenses != null ? roundMoney(w.paid_expenses) : null,
            unpaid_expenses: w.unpaid_expenses != null ? roundMoney(w.unpaid_expenses) : null,
            paid_net_profit: w.paid_net_profit != null ? roundMoney(w.paid_net_profit) : null,
            unpaid_net_profit: w.unpaid_net_profit != null ? roundMoney(w.unpaid_net_profit) : null,
        }));
    }

    function pnlTotalNetPay() {
        return roundMoney(
            (parseFloat(pnlPayrollSummary.total_paid_net_pay) || 0)
            + (parseFloat(pnlPayrollSummary.total_unpaid_net_pay) || 0)
        );
    }

    function pnlTotalCommission() {
        return roundMoney(
            (parseFloat(pnlPayrollSummary.total_paid_commission) || 0)
            + (parseFloat(pnlPayrollSummary.total_unpaid_commission) || 0)
        );
    }

    function pnlPeriodAllocShare(snap, totalPayrollInvoiced, totalColl, nPeriods) {
        const payrollInvoicedW = roundMoney(snap.payroll_invoiced);
        const collW = roundMoney(snap.collections);
        if (totalPayrollInvoiced > 0) {
            return payrollInvoicedW / totalPayrollInvoiced;
        }
        if (totalColl > 0) {
            return collW / totalColl;
        }

        return nPeriods > 0 ? 1 / nPeriods : 1;
    }

    function buildPeriodPnlRows() {
        const snaps = getPeriodSnapshotsForUi();
        const n = snaps.length;
        if (!n) return [];

        const isMonthly = pnlGranularity() === 'monthly';
        const manualBySnap = manualExpenseBySnapKey(snaps, isMonthly);
        const manualPaidUnpaidBySnap = manualExpensePaidUnpaidBySnapKey(snaps, isMonthly);
        const useBackendCosts = snaps.some(snapHasBackendCosts);

        if (useBackendCosts) {
            return reconcilePeriodRowsToExpenseTruth(snaps.map(function (w) {
                return buildPeriodRowFromBackendSnap(
                    w,
                    manualPaidUnpaidBySnap.get(w.week_key) || { paid: 0, unpaid: 0 }
                );
            }));
        }

        const totalPayrollCost = pnlTotalNetPay();
        const totalComm = pnlTotalCommission();
        const totalColl = roundMoney(pnlCollectionsMeta.total);
        const totalPayrollInvoiced = roundMoney(pnlCollectionsMeta.payroll_invoiced);
        const apiExp = roundMoney(pnlApiExpensesBase);

        if (totalColl > 0) {
            return reconcilePeriodRowsToExpenseTruth(snaps.map(w => {
                const collW = roundMoney(w.collections);
                const paidCollW = roundMoney(w.paid_collections);
                const payrollInvoicedW = roundMoney(w.payroll_invoiced);
                const paidPayrollInvoicedW = roundMoney(w.paid_payroll_invoiced);
                const allocShare = pnlPeriodAllocShare(w, totalPayrollInvoiced, totalColl, n);
                const collShare = collW / totalColl;
                const payrollW = roundMoney(totalPayrollCost * allocShare);
                const commissionW = roundMoney(totalComm * allocShare);
                const expApiW = roundMoney(apiExp * collShare);
                const expManualW = roundMoney(manualBySnap.get(w.week_key) || 0);
                const expManualSplit = manualPaidUnpaidBySnap.get(w.week_key) || { paid: 0, unpaid: 0 };
                const expApiSplit = pnlExpenseAmountSplit(expApiW, 0);
                const expW = roundMoney(expApiW + expManualW);
                return enrichPeriodRowPaidUnpaid({
                    week_key: w.week_key,
                    period_label: w.period_label,
                    collections: collW,
                    paid_collections: paidCollW,
                    payroll_invoiced: payrollInvoicedW,
                    paid_payroll_invoiced: paidPayrollInvoicedW,
                    payroll: payrollW,
                    commission: commissionW,
                    expenses: expW,
                    net_profit: roundMoney(collW - payrollW - commissionW - expW),
                }, {
                    paid: roundMoney(expApiSplit.paid + expManualSplit.paid),
                    unpaid: roundMoney(expApiSplit.unpaid + expManualSplit.unpaid),
                });
            }));
        }

        const nPeriods = n;
        const perPayroll = roundMoney(totalPayrollCost / nPeriods);
        const perCom = roundMoney(totalComm / nPeriods);
        const perApi = roundMoney(apiExp / nPeriods);
        return reconcilePeriodRowsToExpenseTruth(snaps.map((w, wi) => {
            const collW = roundMoney(w.collections);
            const paidCollW = roundMoney(w.paid_collections);
            const payrollW = wi === nPeriods - 1
                ? roundMoney(totalPayrollCost - perPayroll * Math.max(0, nPeriods - 1))
                : perPayroll;
            const commissionW = wi === nPeriods - 1
                ? roundMoney(totalComm - perCom * Math.max(0, nPeriods - 1))
                : perCom;
            const expApiW = wi === nPeriods - 1
                ? roundMoney(apiExp - perApi * Math.max(0, nPeriods - 1))
                : perApi;
            const expManualW = roundMoney(manualBySnap.get(w.week_key) || 0);
            const expManualSplit = manualPaidUnpaidBySnap.get(w.week_key) || { paid: 0, unpaid: 0 };
            const expApiSplit = pnlExpenseAmountSplit(expApiW, 0);
            const expW = roundMoney(expApiW + expManualW);
            return enrichPeriodRowPaidUnpaid({
                week_key: w.week_key,
                period_label: w.period_label,
                collections: collW,
                paid_collections: paidCollW,
                payroll_invoiced: roundMoney(w.payroll_invoiced),
                paid_payroll_invoiced: roundMoney(w.paid_payroll_invoiced),
                payroll: payrollW,
                commission: commissionW,
                expenses: expW,
                net_profit: roundMoney(collW - payrollW - commissionW - expW),
            }, {
                paid: roundMoney(expApiSplit.paid + expManualSplit.paid),
                unpaid: roundMoney(expApiSplit.unpaid + expManualSplit.unpaid),
            });
        }));
    }

    function build12MonthTrendPnlRows() {
        const monthKeys = pnlLastNMonthKeysEndingSelected(12);
        const agg = aggregateSnapshotsByMonth(pnlTrend12WeekSnapshots);
        const aggMap = new Map();
        agg.forEach(function (r) {
            aggMap.set(r.week_key, r);
        });
        const snaps = monthKeys.map(function (mk) {
            const row = aggMap.get(mk);
            return {
                week_key: mk,
                period_label: monthLabel(mk),
                collections: row ? roundMoney(row.collections) : 0,
                paid_collections: row ? roundMoney(row.paid_collections) : 0,
                payroll_invoiced: row ? roundMoney(row.payroll_invoiced) : 0,
                paid_payroll_invoiced: row ? roundMoney(row.paid_payroll_invoiced) : 0,
            };
        });
        const n = snaps.length;
        if (!n) return [];

        let sumPayrollCost = 0;
        let sumComm = 0;
        (pnlTrend12ReportData || []).forEach(function (emp) {
            sumPayrollCost += pnlEffectiveBaseSalary(emp);
            sumComm += parseFloat(emp.pnl_commission || 0);
        });
        const totalPayroll = roundMoney(sumPayrollCost);
        const totalComm = roundMoney(sumComm);
        const totalColl = roundMoney(pnlTrend12CollectionsTotal || pnlCollectionsMeta.total);
        const apiExp = roundMoney(pnlTrend12ApiExpensesBase);
        const manualBySnap = manualExpenseBySnapKey(snaps, true);

        if (totalColl > 0) {
            return snaps.map(function (w) {
                const collW = roundMoney(w.collections);
                const paidCollW = roundMoney(w.paid_collections);
                const share = collW / totalColl;
                const payrollW = roundMoney(totalPayroll * share);
                const commissionW = roundMoney(totalComm * share);
                const expApiW = roundMoney(apiExp * share);
                const expManualW = roundMoney(manualBySnap.get(w.week_key) || 0);
                const expW = roundMoney(expApiW + expManualW);
                return enrichPeriodRowPaidUnpaid({
                    week_key: w.week_key,
                    period_label: w.period_label,
                    collections: collW,
                    paid_collections: paidCollW,
                    payroll: payrollW,
                    commission: commissionW,
                    expenses: expW,
                    net_profit: roundMoney(collW - payrollW - commissionW - expW),
                });
            });
        }

        const nPeriods = n;
        const perPayroll = roundMoney(totalPayroll / nPeriods);
        const perCom = roundMoney(totalComm / nPeriods);
        const perApi = roundMoney(apiExp / nPeriods);
        return snaps.map(function (w, wi) {
            const collW = roundMoney(w.collections);
            const paidCollW = roundMoney(w.paid_collections);
            const payrollW = wi === nPeriods - 1
                ? roundMoney(totalPayroll - perPayroll * Math.max(0, nPeriods - 1))
                : perPayroll;
            const commissionW = wi === nPeriods - 1
                ? roundMoney(totalComm - perCom * Math.max(0, nPeriods - 1))
                : perCom;
            const expApiW = wi === nPeriods - 1
                ? roundMoney(apiExp - perApi * Math.max(0, nPeriods - 1))
                : perApi;
            const expManualW = roundMoney(manualBySnap.get(w.week_key) || 0);
            const expW = roundMoney(expApiW + expManualW);
            return enrichPeriodRowPaidUnpaid({
                week_key: w.week_key,
                period_label: w.period_label,
                collections: collW,
                paid_collections: paidCollW,
                payroll: payrollW,
                commission: commissionW,
                expenses: expW,
                net_profit: roundMoney(collW - payrollW - commissionW - expW),
            });
        });
    }

    function clearPnlTrend12() {
        pnlTrend12WeekSnapshots = [];
        pnlTrend12ReportData = [];
        pnlTrend12CollectionsTotal = 0;
        pnlTrend12ApiExpensesBase = 0;
    }

    async function fetchPnl12MonthTrend() {
        if (!pnlCanLoadData || pnlGranularity() !== 'monthly') {
            return;
        }
        const { start, end } = pnlTwelveMonthRangeEndingSelected();
        try {
            const params = pnlApiQueryParams(start, end);
            const response = await fetch('/api/payroll/pnl-invoice-basis?' + params.toString());
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Trend request failed');
            }
            pnlTrend12WeekSnapshots = (result.weekly || []).map(function (r) {
                return mapPnlWeekSnapshot(r);
            });
            pnlTrend12ReportData = JSON.parse(JSON.stringify(result.data || []));
            pnlTrend12ReportData.forEach(syncPnlRowDerivedCosts);
            pnlTrend12CollectionsTotal = parseFloat(result.collections?.total) || 0;
            pnlTrend12ApiExpensesBase = parseFloat(result.expenses) || 0;
        } catch (err) {
            console.error(err);
            clearPnlTrend12();
        }
    }

    function renderPnlTrendChart(weekRows) {
        renderPnlPaidUnpaidBreakdownChart(
            document.getElementById('pnlTrendChartBars'),
            weekRows,
            'period_label',
            'paid_net_profit',
            'unpaid_net_profit'
        );
    }

    function renderPnlPaidUnpaidBreakdownChart(host, rows, labelField, paidField, unpaidField) {
        if (!host) return;
        if (!rows.length) {
            host.innerHTML = '<div style="padding:1rem;color:var(--text-secondary);font-size:0.875rem;">No data for this range.</div>';
            return;
        }
        const maxAbs = Math.max(1, ...rows.map(function (r) {
            return Math.abs(roundMoney(r[paidField])) + Math.abs(roundMoney(r[unpaidField]));
        }));
        const maxH = 160;
        host.innerHTML = rows.map(function (r) {
            const paidVal = roundMoney(r[paidField]);
            const unpaidVal = roundMoney(r[unpaidField]);
            const totalVal = roundMoney(paidVal + unpaidVal);
            const paidH = Math.max(0, (Math.abs(paidVal) / maxAbs) * maxH);
            const unpaidH = Math.max(0, (Math.abs(unpaidVal) / maxAbs) * maxH);
            const labelRaw = r[labelField] || '—';
            const label = labelField === 'client_name' ? pnlTitleCase(labelRaw) : labelRaw;
            const paidCls = paidVal > 0 ? 'payroll-pnl-bar--pos' : (paidVal < 0 ? 'payroll-pnl-bar--neg' : 'payroll-pnl-bar--zero');
            const unpaidCls = unpaidVal > 0 ? 'payroll-pnl-bar--unpaid-pos' : (unpaidVal < 0 ? 'payroll-pnl-bar--unpaid-neg' : 'payroll-pnl-bar--zero');
            const paidBar = paidH > 0
                ? '<div class="payroll-pnl-bar ' + paidCls + '" style="height:' + Math.max(2, paidH) + 'px"></div>'
                : '';
            const unpaidBar = unpaidH > 0
                ? '<div class="payroll-pnl-bar ' + unpaidCls + '" style="height:' + Math.max(2, unpaidH) + 'px"></div>'
                : '';
            return '<div class="payroll-pnl-bar-col" title="' + escapeHtml(label) + ': paid ' + formatPayrollMoney(paidVal) + ', unpaid ' + formatPayrollMoney(unpaidVal) + '">'
                + '<div class="payroll-pnl-bar-stack">' + paidBar + unpaidBar + '</div>'
                + '<span class="payroll-pnl-bar-label">' + escapeHtml(label) + '</span>'
                + '</div>';
        }).join('');
    }

    function renderPnlBreakdownChart(host, rows, labelField, valueField = 'net_profit') {
        if (!host) return;
        if (valueField === 'commission' && rows.length && rows[0].paid_commission != null) {
            renderPnlPaidUnpaidBreakdownChart(host, rows, labelField, 'paid_commission', 'unpaid_commission');
            return;
        }
        if (valueField === 'net_profit' && rows.length && rows[0].paid_net_profit != null) {
            renderPnlPaidUnpaidBreakdownChart(host, rows, labelField, 'paid_net_profit', 'unpaid_net_profit');
            return;
        }
        if (!rows.length) {
            host.innerHTML = '<div style="padding:1rem;color:var(--text-secondary);font-size:0.875rem;">No data for this range.</div>';
            return;
        }
        const maxAbs = Math.max(1, ...rows.map(function (r) { return Math.abs(parseFloat(r[valueField]) || 0); }));
        const maxH = 160;
        host.innerHTML = rows.map(function (r) {
            const v = roundMoney(r[valueField]);
            const h = Math.max(4, (Math.abs(v) / maxAbs) * maxH);
            const cls = v > 0 ? 'payroll-pnl-bar--pos' : (v < 0 ? 'payroll-pnl-bar--neg' : 'payroll-pnl-bar--zero');
            const labelRaw = r[labelField] || '—';
            const label = labelField === 'client_name' ? pnlTitleCase(labelRaw) : labelRaw;
            return '<div class="payroll-pnl-bar-col" title="' + escapeHtml(label) + ': ' + formatPayrollMoney(v) + '">'
                + '<div class="payroll-pnl-bar ' + cls + '" style="height:' + h + 'px"></div>'
                + '<span class="payroll-pnl-bar-label">' + escapeHtml(label) + '</span>'
                + '</div>';
        }).join('');
    }

    function manualExpensesByClientId() {
        const map = new Map();
        manualExpensesInSelectedMonth().forEach(function (e) {
            const amt = roundMoney(parseFloat(e.amount) || 0);
            if (amt <= 0) return;
            const cid = e.client_id ? parseInt(e.client_id, 10) : 0;
            const key = cid > 0 ? String(cid) : 'company';
            map.set(key, roundMoney((map.get(key) || 0) + amt));
        });
        return map;
    }

    function applyExpensesToClientRows(clients) {
        const apiExp = roundMoney(pnlApiExpensesBase);
        const manualByClient = manualExpensesByClientId();
        const companyWideManual = roundMoney(manualByClient.get('company') || 0);
        const totalColl = roundMoney(clients.reduce(function (s, c) { return s + roundMoney(c.collections); }, 0));

        return clients.map(function (client) {
            const coll = roundMoney(client.collections);
            const paidColl = roundMoney(client.paid_collections);
            const unpaidColl = roundMoney(client.unpaid_collections != null ? client.unpaid_collections : (coll - paidColl));
            const share = totalColl > 0 ? coll / totalColl : 0;
            const clientKey = client.client_id ? String(client.client_id) : '';
            const clientManual = clientKey ? roundMoney(manualByClient.get(clientKey) || 0) : 0;
            const expApi = roundMoney(apiExp * share);
            const expCompanyManual = roundMoney(companyWideManual * share);
            const clientExpenses = roundMoney(expApi + clientManual + expCompanyManual);
            const expenseSplit = pnlExpenseAmountSplit(clientExpenses);
            const employees = (client.employees || []).map(function (emp) {
                const empColl = roundMoney(emp.collections);
                const empPaidColl = roundMoney(emp.paid_collections);
                const empUnpaidColl = roundMoney(emp.unpaid_collections != null ? emp.unpaid_collections : (empColl - empPaidColl));
                const empShare = coll > 0 ? empColl / coll : 0;
                const expenses = roundMoney(clientExpenses * empShare);
                const empExpenseSplit = pnlExpenseAmountSplit(expenses);
                const paidNetProfit = pnlNetProfitFromComponents(
                    empPaidColl,
                    emp.paid_net_pay,
                    emp.paid_commission,
                    expenses
                );
                const unpaidNetProfit = pnlNetProfitFromComponents(
                    empUnpaidColl,
                    emp.unpaid_net_pay,
                    emp.unpaid_commission,
                    0
                );
                return Object.assign({}, emp, {
                    expenses: expenses,
                    paid_expenses: empExpenseSplit.paid,
                    unpaid_expenses: empExpenseSplit.unpaid,
                    net_profit: pnlNetProfitFromComponents(empColl, emp.net_pay, emp.commission, expenses),
                    paid_net_profit: paidNetProfit,
                    unpaid_net_profit: unpaidNetProfit,
                });
            });
            const paidNetProfit = roundMoney(employees.reduce(function (s, emp) {
                return s + roundMoney(emp.paid_net_profit);
            }, 0));
            const unpaidNetProfit = roundMoney(employees.reduce(function (s, emp) {
                return s + roundMoney(emp.unpaid_net_profit);
            }, 0));

            return Object.assign({}, client, {
                employees: employees,
                expenses: clientExpenses,
                paid_expenses: expenseSplit.paid,
                unpaid_expenses: expenseSplit.unpaid,
                net_profit: roundMoney(employees.reduce(function (s, emp) { return s + roundMoney(emp.net_profit); }, 0)),
                paid_net_profit: paidNetProfit,
                unpaid_net_profit: unpaidNetProfit,
            });
        });
    }

    function applyExpensesToSalesRepRows(rows) {
        const expensesTotal = totalPnlExpenses();
        const totalColl = roundMoney(rows.reduce(function (s, r) { return s + roundMoney(r.collections); }, 0));

        return rows.map(function (row) {
            const coll = roundMoney(row.collections);
            const share = totalColl > 0 ? coll / totalColl : (rows.length ? 1 / rows.length : 0);
            const expenses = roundMoney(expensesTotal * share);
            const netProfit = roundMoney(coll - roundMoney(row.net_pay) - roundMoney(row.commission) - expenses);
            return Object.assign({}, row, { expenses: expenses, net_profit: netProfit });
        });
    }

    function renderPnlSalesRepTable(rows) {
        const tbody = document.getElementById('pnlBySalesRepBody');
        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="2" class="payroll-pnl-empty">No data for this period.</td></tr>';
        } else {
            tbody.innerHTML = rows.map(function (r) {
                return '<tr>'
                    + '<th scope="row">' + escapeHtml(r.sales_rep_name || '—') + '</th>'
                    + '<td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell">' + renderPnlPaidUnpaidCell(r.paid_commission, r.unpaid_commission) + '</td>'
                    + '</tr>';
            }).join('');
        }

        const sumPaid = roundMoney(rows.reduce(function (s, r) { return s + roundMoney(r.paid_commission); }, 0));
        const sumUnpaid = roundMoney(rows.reduce(function (s, r) { return s + roundMoney(r.unpaid_commission); }, 0));
        const ftCom = document.getElementById('pnlBySalesRepTotalCommission');
        if (ftCom) ftCom.innerHTML = renderPnlPaidUnpaidCell(sumPaid, sumUnpaid);
    }

    function renderPnlClientEmployeeTable(clients) {
        const tbody = document.getElementById('pnlByClientBody');
        if (!tbody) return;

        const allEmployees = clients.flatMap(function (client) {
            return client.employees || [];
        });

        if (!clients.length || !allEmployees.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="payroll-pnl-empty">No data for this period.</td></tr>';
        } else {
            tbody.innerHTML = clients.map(function (client) {
                let html = '<tr class="payroll-pnl-client-group">'
                    + '<th scope="row" class="payroll-pnl-client-name">' + pnlDisplayName(client.client_name) + '</th>'
                    + '<th scope="row" class="payroll-pnl-client-spacer"></th>'
                    + '</tr>';

                (client.employees || []).forEach(function (emp) {
                    const netCls = emp.paid_net_profit > 0 ? 'payroll-pnl-amt-pos' : (emp.paid_net_profit < 0 ? 'payroll-pnl-amt-neg' : '');
                    html += '<tr class="payroll-pnl-client-employee">'
                        + '<td class="payroll-pnl-client-spacer" aria-hidden="true"></td>'
                        + '<th scope="row" class="payroll-pnl-client-employee-name">' + pnlDisplayName(emp.employee_name) + '</th>'
                        + '<td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell">' + renderPnlPaidUnpaidCell(emp.paid_collections, emp.unpaid_collections) + '</td>'
                        + '<td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell">' + renderPnlPaidUnpaidCell(emp.paid_net_pay, emp.unpaid_net_pay) + '</td>'
                        + '<td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell">' + renderPnlPaidUnpaidCell(emp.paid_commission, emp.unpaid_commission) + '</td>'
                        + '<td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell">' + renderPnlPaidUnpaidCell(emp.paid_expenses, emp.unpaid_expenses) + '</td>'
                        + '<td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell ' + netCls + '">' + renderPnlPaidUnpaidCell(emp.paid_net_profit, emp.unpaid_net_profit) + '</td>'
                        + '</tr>';
                });

                return html;
            }).join('');
        }

        const totals = {
            collections: sumPaidUnpaidField(allEmployees, 'paid_collections', 'unpaid_collections'),
            net_pay: sumPaidUnpaidField(allEmployees, 'paid_net_pay', 'unpaid_net_pay'),
            commission: sumPaidUnpaidField(allEmployees, 'paid_commission', 'unpaid_commission'),
            expenses: sumPaidUnpaidField(allEmployees, 'paid_expenses', 'unpaid_expenses'),
            net_profit: sumPaidUnpaidField(allEmployees, 'paid_net_profit', 'unpaid_net_profit'),
        };

        const ftCol = document.getElementById('pnlByClientTotalCollections');
        const ftPay = document.getElementById('pnlByClientTotalNetPay');
        const ftCom = document.getElementById('pnlByClientTotalCommission');
        const ftExp = document.getElementById('pnlByClientTotalExpenses');
        const ftNet = document.getElementById('pnlByClientTotalNet');
        if (ftCol) ftCol.innerHTML = renderPnlPaidUnpaidCell(totals.collections.paid, totals.collections.unpaid);
        if (ftPay) ftPay.innerHTML = renderPnlPaidUnpaidCell(totals.net_pay.paid, totals.net_pay.unpaid);
        if (ftCom) ftCom.innerHTML = renderPnlPaidUnpaidCell(totals.commission.paid, totals.commission.unpaid);
        if (ftExp) ftExp.innerHTML = renderPnlPaidUnpaidCell(totals.expenses.paid, totals.expenses.unpaid);
        if (ftNet) {
            ftNet.innerHTML = renderPnlPaidUnpaidCell(totals.net_profit.paid, totals.net_profit.unpaid);
            ftNet.classList.remove('payroll-pnl-amt-pos', 'payroll-pnl-amt-neg');
            if (totals.net_profit.paid > 0) ftNet.classList.add('payroll-pnl-amt-pos');
            else if (totals.net_profit.paid < 0) ftNet.classList.add('payroll-pnl-amt-neg');
        }
    }

    function renderPnlDimensionTable(bodyId, rows, labelField, totalsPrefix) {
        const tbody = document.getElementById(bodyId);
        if (!tbody) return;

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="payroll-pnl-empty">No data for this period.</td></tr>';
        } else {
            tbody.innerHTML = rows.map(function (r) {
                const netCls = r.net_profit > 0 ? 'payroll-pnl-amt-pos' : (r.net_profit < 0 ? 'payroll-pnl-amt-neg' : '');
                return '<tr>'
                    + '<th scope="row">' + escapeHtml(r[labelField] || '—') + '</th>'
                    + '<td class="payroll-pnl-num">' + formatPayrollMoney(r.collections) + '</td>'
                    + '<td class="payroll-pnl-num">' + formatPayrollMoney(r.net_pay) + '</td>'
                    + '<td class="payroll-pnl-num">' + formatPayrollMoney(r.commission) + '</td>'
                    + '<td class="payroll-pnl-num">' + formatPayrollMoney(r.expenses) + '</td>'
                    + '<td class="payroll-pnl-num ' + netCls + '">' + formatPayrollMoney(r.net_profit) + '</td>'
                    + '</tr>';
            }).join('');
        }

        const sumCollections = roundMoney(rows.reduce(function (s, r) { return s + roundMoney(r.collections); }, 0));
        const sumNetPay = roundMoney(rows.reduce(function (s, r) { return s + roundMoney(r.net_pay); }, 0));
        const sumCommission = roundMoney(rows.reduce(function (s, r) { return s + roundMoney(r.commission); }, 0));
        const sumExpenses = roundMoney(rows.reduce(function (s, r) { return s + roundMoney(r.expenses); }, 0));
        const sumNet = roundMoney(rows.reduce(function (s, r) { return s + roundMoney(r.net_profit); }, 0));

        const ftCol = document.getElementById(totalsPrefix + 'TotalCollections');
        const ftPay = document.getElementById(totalsPrefix + 'TotalNetPay');
        const ftCom = document.getElementById(totalsPrefix + 'TotalCommission');
        const ftExp = document.getElementById(totalsPrefix + 'TotalExpenses');
        const ftNet = document.getElementById(totalsPrefix + 'TotalNet');
        if (ftCol) ftCol.textContent = formatPayrollMoney(sumCollections);
        if (ftPay) ftPay.textContent = formatPayrollMoney(sumNetPay);
        if (ftCom) ftCom.textContent = formatPayrollMoney(sumCommission);
        if (ftExp) ftExp.textContent = formatPayrollMoney(sumExpenses);
        if (ftNet) {
            ftNet.textContent = formatPayrollMoney(sumNet);
            ftNet.classList.remove('payroll-pnl-amt-pos', 'payroll-pnl-amt-neg');
            if (sumNet > 0) ftNet.classList.add('payroll-pnl-amt-pos');
            else if (sumNet < 0) ftNet.classList.add('payroll-pnl-amt-neg');
        }
    }

    let pnlClientMap = new Map();
    let pnlAllClientMap = new Map();
    let pnlSalesRepMap = new Map();
    let pnlAllSalesRepMap = new Map();

    function pnlApiQueryParams(startDate, endDate) {
        const params = new URLSearchParams({ start_date: startDate, end_date: endDate });
        const clientId = pnlActiveClientId();
        if (clientId) {
            params.set('client_id', String(clientId));
        }
        const salesRep = pnlActiveSalesRepFilter();
        if (salesRep === 'unassigned') {
            params.set('sales_rep_id', 'unassigned');
        } else if (salesRep) {
            params.set('sales_rep_id', String(salesRep));
        }
        return params;
    }

    function populatePnlClientFilter(data, preserveOptions) {
        const sel = document.getElementById('pnlFilterClient');
        if (!sel) return;
        const preserve = preserveOptions === true || (preserveOptions && preserveOptions.preserve);
        if (!preserve) {
            const map = new Map();
            data.forEach(function (emp) {
                const ids = emp.client_ids || [];
                const names = String(emp.clients || '').split(',').map(function (n) { return n.trim(); });
                ids.forEach(function (id, i) {
                    const cid = parseInt(id, 10);
                    if (!isNaN(cid) && !map.has(cid)) {
                        map.set(cid, names[i] || String(cid));
                    }
                });
            });
            pnlAllClientMap = map;
        }
        pnlClientMap = pnlAllClientMap;
        const current = parseInt(sel.value, 10);
        const sorted = Array.from(pnlClientMap.entries()).sort(function (a, b) {
            return String(a[1]).localeCompare(String(b[1]));
        });
        sel.innerHTML = '<option value="">All clients</option>'
            + sorted.map(function (entry) {
                return '<option value="' + entry[0] + '"' + (entry[0] === current ? ' selected' : '') + '>' + escapeHtml(entry[1]) + '</option>';
            }).join('');
    }

    function populatePnlSalesRepFilter(data, preserveOptions) {
        const sel = document.getElementById('pnlFilterSalesRep');
        if (!sel) return;
        const preserve = preserveOptions === true || (preserveOptions && preserveOptions.preserve);
        if (!preserve) {
            const map = new Map();
            let hasUnassigned = false;
            data.forEach(function (emp) {
                const repId = parseInt(emp.sales_rep_id, 10);
                if (!isNaN(repId) && repId > 0) {
                    if (!map.has(String(repId))) {
                        map.set(String(repId), emp.sales_rep_name || ('Rep #' + repId));
                    }
                } else {
                    hasUnassigned = true;
                }
            });
            if (hasUnassigned) {
                map.set('unassigned', 'Unassigned');
            }
            pnlAllSalesRepMap = map;
        }
        pnlSalesRepMap = pnlAllSalesRepMap;
        const current = sel.value;
        const sorted = Array.from(pnlSalesRepMap.entries()).sort(function (a, b) {
            if (a[0] === 'unassigned') return 1;
            if (b[0] === 'unassigned') return -1;
            return String(a[1]).localeCompare(String(b[1]));
        });
        sel.innerHTML = '<option value="">All sales reps</option>'
            + sorted.map(function (entry) {
                return '<option value="' + escapeHtml(entry[0]) + '"' + (entry[0] === current ? ' selected' : '') + '>' + escapeHtml(entry[1]) + '</option>';
            }).join('');
    }

    function pnlActiveSalesRepFilter() {
        const v = document.getElementById('pnlFilterSalesRep')?.value;
        if (!v) return null;
        if (v === 'unassigned') return 'unassigned';
        const id = parseInt(v, 10);
        return isNaN(id) ? null : id;
    }

    function pnlActiveClientId() {
        const v = document.getElementById('pnlFilterClient')?.value;
        return v ? parseInt(v, 10) : null;
    }

    function filteredReportData() {
        return reportData;
    }

    function pnlFilteredCollectionsLabel() {
        const parts = ['Invoiced in period'];
        const clientId = pnlActiveClientId();
        if (clientId) {
            parts.unshift('Filtered by client: ' + (pnlClientMap.get(clientId) || String(clientId)));
        }
        const salesRep = pnlActiveSalesRepFilter();
        if (salesRep === 'unassigned') {
            parts.unshift('Filtered by sales rep: Unassigned');
        } else if (salesRep) {
            parts.unshift('Filtered by sales rep: ' + (pnlSalesRepMap.get(String(salesRep)) || String(salesRep)));
        }
        return parts.join(' · ');
    }

    function pnlFilterCaptionSuffix() {
        const bits = [];
        const clientId = pnlActiveClientId();
        if (clientId) {
            bits.push(pnlClientMap.get(clientId) || 'Selected client');
        }
        const salesRep = pnlActiveSalesRepFilter();
        if (salesRep === 'unassigned') {
            bits.push('Unassigned sales rep');
        } else if (salesRep) {
            bits.push(pnlSalesRepMap.get(String(salesRep)) || 'Selected sales rep');
        }
        return bits.length ? (' · ' + bits.join(' · ')) : '';
    }

    function refreshPnlDashboard() {
        const section = document.getElementById('payrollPnlSection');
        const results = document.getElementById('payrollPnlResults');
        const periodBody = document.getElementById('pnlPeriodBreakdownBody');
        if (!section || !periodBody) return;

        if (!reportData || reportData.length === 0) {
            if (results) results.hidden = true;
            periodBody.innerHTML = '';
            pnlByClient = [];
            pnlBySalesRep = [];
            renderPnlClientEmployeeTable([]);
            renderPnlSalesRepTable([]);
            renderPnlBreakdownChart(document.getElementById('pnlByClientChart'), [], 'client_name');
            renderPnlBreakdownChart(document.getElementById('pnlBySalesRepChart'), [], 'sales_rep_name', 'commission');
            clearPnlTrend12();
            const th = document.getElementById('pnlTrendChartBars');
            if (th) th.classList.remove('payroll-pnl-trend-chart--dense');
            renderPnlTrendChart([]);
            const kBr = document.getElementById('pnlKpiCollectionsBreakdown');
            if (kBr) kBr.textContent = 'Paid status';
            const kPayBr = document.getElementById('pnlKpiPayrollBreakdown');
            if (kPayBr) kPayBr.textContent = 'Net pay from generated payroll period invoices · Paid status';
            const kComBr = document.getElementById('pnlKpiCommissionBreakdown');
            if (kComBr) kComBr.textContent = 'Paid status';
            const kNetBr = document.getElementById('pnlKpiNetProfitBreakdown');
            if (kNetBr) kNetBr.textContent = 'Paid status';
            pnlPayrollSummary = {
                total_paid_net_pay: 0,
                total_unpaid_net_pay: 0,
                total_paid_commission: 0,
                total_unpaid_commission: 0,
            };
            pnlNetProfitMeta = { paid: 0, unpaid: 0 };
            clearPnlKpiUnpaidSubs();
            renderPnlExpensesTable();
            return;
        }

        if (results) results.hidden = false;
        reportData.forEach(syncPnlRowDerivedCosts);

        const expensesTotal = totalPnlExpenses();
        const paidCollections = roundMoney(pnlCollectionsMeta.paid_total);
        const unpaidCollections = pnlUnpaidCollectionsAmount();
        const paidPayrollNetPay = roundMoney(pnlPayrollSummary.total_paid_net_pay);
        const unpaidPayrollNetPay = roundMoney(pnlPayrollSummary.total_unpaid_net_pay);
        const paidCommission = roundMoney(pnlPayrollSummary.total_paid_commission);
        const unpaidCommission = roundMoney(pnlPayrollSummary.total_unpaid_commission);
        const paidNetProfit = pnlNetProfitFromComponents(
            paidCollections,
            paidPayrollNetPay,
            paidCommission,
            expensesTotal
        );
        const unpaidNetProfit = pnlNetProfitFromComponents(
            unpaidCollections,
            unpaidPayrollNetPay,
            unpaidCommission,
            0
        );
        const clientRowsForKpi = applyExpensesToClientRows(pnlByClient.slice());

        const trendCap = document.getElementById('pnlTrendCaption');
        if (trendCap) {
            const filterSuffix = pnlFilterCaptionSuffix();
            trendCap.textContent = pnlGranularity() === 'monthly'
                ? 'Net profit by month (last 12 months ending selected month; same allocation rules as period breakdown)' + filterSuffix
                : 'Net profit by week (collections from invoice dates in range)' + filterSuffix;
        }

        const kCol = document.getElementById('pnlKpiCollections');
        const kColUnpaid = document.getElementById('pnlKpiCollectionsUnpaid');
        const kColBr = document.getElementById('pnlKpiCollectionsBreakdown');
        const kPay = document.getElementById('pnlKpiPayroll');
        const kPayUnpaid = document.getElementById('pnlKpiPayrollUnpaid');
        const kPayBr = document.getElementById('pnlKpiPayrollBreakdown');
        const kCom = document.getElementById('pnlKpiCommission');
        const kComUnpaid = document.getElementById('pnlKpiCommissionUnpaid');
        const kComBr = document.getElementById('pnlKpiCommissionBreakdown');
        const kExp = document.getElementById('pnlKpiExpenses');
        const kExpUnpaid = document.getElementById('pnlKpiExpensesUnpaid');
        const kNet = document.getElementById('pnlKpiNetProfit');
        const kNetUnpaid = document.getElementById('pnlKpiNetProfitUnpaid');
        const kNetBr = document.getElementById('pnlKpiNetProfitBreakdown');

        if (kCol) kCol.textContent = formatPayrollMoney(paidCollections);
        setPnlKpiSub(kColUnpaid, pnlUnpaidCollectionsLabel(unpaidCollections));
        if (kColBr) {
            const paidPayrollInvoiced = parseFloat(pnlCollectionsMeta.paid_payroll_invoiced) || 0;
            const paidBillingInvoiced = parseFloat(pnlCollectionsMeta.paid_billing_invoiced) || 0;
            const unalloc = parseFloat(pnlCollectionsMeta.paid_billing_unallocated) || 0;
            let t = 'Paid status · '
                + formatPayrollMoney(paidPayrollInvoiced) + ' payroll · '
                + formatPayrollMoney(paidBillingInvoiced) + ' billing';
            if (unalloc > 0) {
                t += ' · ' + formatPayrollMoney(unalloc) + ' unallocated';
            }
            t += ' · ' + pnlFilteredCollectionsLabel();
            kColBr.textContent = t;
        }
        if (kPay) kPay.textContent = formatPayrollMoney(paidPayrollNetPay);
        setPnlKpiSub(kPayUnpaid, pnlUnpaidPayrollLabel(unpaidPayrollNetPay));
        if (kPayBr) {
            kPayBr.textContent = 'Paid status · '
                + formatPayrollMoney(paidPayrollNetPay) + ' net pay · '
                + pnlFilteredCollectionsLabel();
        }
        if (kCom) kCom.textContent = formatPayrollMoney(paidCommission);
        setPnlKpiSub(kComUnpaid, pnlUnpaidCommissionLabel(unpaidCommission));
        if (kComBr) kComBr.textContent = 'Paid status · ' + pnlFilteredCollectionsLabel();
        if (kExp) kExp.textContent = formatPayrollMoney(expensesTotal);
        setPnlKpiSub(kExpUnpaid, '');
        if (kNet) {
            kNet.textContent = formatPayrollMoney(paidNetProfit);
            kNet.classList.remove('pnl-amt-pos', 'pnl-amt-neg');
            if (paidNetProfit > 0) kNet.classList.add('pnl-amt-pos');
            else if (paidNetProfit < 0) kNet.classList.add('pnl-amt-neg');
        }
        setPnlKpiSub(kNetUnpaid, pnlUnpaidNetProfitLabel(unpaidNetProfit));
        if (kNetBr) kNetBr.textContent = 'Paid · collections − payroll − commission − expenses';

        const weekRows = buildPeriodPnlRows();
        periodBody.innerHTML = weekRows.map(r => {
            const netCls = r.paid_net_profit > 0 ? 'payroll-pnl-amt-pos' : (r.paid_net_profit < 0 ? 'payroll-pnl-amt-neg' : '');
            return `<tr>
                <th scope="row">${r.period_label}</th>
                <td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell">${renderPnlPaidUnpaidCell(r.paid_collections, r.unpaid_collections)}</td>
                <td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell">${renderPnlPaidUnpaidCell(r.paid_payroll, r.unpaid_payroll)}</td>
                <td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell">${renderPnlPaidUnpaidCell(r.paid_commission, r.unpaid_commission)}</td>
                <td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell">${renderPnlPaidUnpaidCell(r.paid_expenses, r.unpaid_expenses)}</td>
                <td class="payroll-pnl-num payroll-pnl-paid-unpaid-cell ${netCls}">${renderPnlPaidUnpaidCell(r.paid_net_profit, r.unpaid_net_profit)}</td>
            </tr>`;
        }).join('');

        const periodTotals = {
            collections: sumPaidUnpaidField(weekRows, 'paid_collections', 'unpaid_collections'),
            payroll: sumPaidUnpaidField(weekRows, 'paid_payroll', 'unpaid_payroll'),
            commission: sumPaidUnpaidField(weekRows, 'paid_commission', 'unpaid_commission'),
            expenses: sumPaidUnpaidField(weekRows, 'paid_expenses', 'unpaid_expenses'),
            net_profit: sumPaidUnpaidField(weekRows, 'paid_net_profit', 'unpaid_net_profit'),
        };

        const ftCol = document.getElementById('pnlPeriodTotalCollections');
        const ftPayroll = document.getElementById('pnlPeriodTotalPayroll');
        const ftCom = document.getElementById('pnlPeriodTotalCommission');
        const ftExp = document.getElementById('pnlPeriodTotalExpenses');
        const ftNet = document.getElementById('pnlPeriodTotalNet');
        if (ftCol) ftCol.innerHTML = renderPnlPaidUnpaidCell(periodTotals.collections.paid, periodTotals.collections.unpaid);
        if (ftPayroll) ftPayroll.innerHTML = renderPnlPaidUnpaidCell(periodTotals.payroll.paid, periodTotals.payroll.unpaid);
        if (ftCom) ftCom.innerHTML = renderPnlPaidUnpaidCell(periodTotals.commission.paid, periodTotals.commission.unpaid);
        if (ftExp) ftExp.innerHTML = renderPnlPaidUnpaidCell(periodTotals.expenses.paid, periodTotals.expenses.unpaid);
        if (ftNet) {
            ftNet.innerHTML = renderPnlPaidUnpaidCell(periodTotals.net_profit.paid, periodTotals.net_profit.unpaid);
            ftNet.classList.remove('payroll-pnl-amt-pos', 'payroll-pnl-amt-neg');
            if (periodTotals.net_profit.paid > 0) ftNet.classList.add('payroll-pnl-amt-pos');
            else if (periodTotals.net_profit.paid < 0) ftNet.classList.add('payroll-pnl-amt-neg');
        }

        const trendRows = (pnlGranularity() === 'monthly' && pnlTrend12WeekSnapshots.length)
            ? build12MonthTrendPnlRows()
            : buildPeriodPnlRows();
        const trendHost = document.getElementById('pnlTrendChartBars');
        if (trendHost) {
            trendHost.classList.add('payroll-pnl-trend-chart--dense');
        }
        renderPnlTrendChart(trendRows);

        const clientRows = clientRowsForKpi;
        const salesRepRows = pnlBySalesRep.slice();
        renderPnlBreakdownChart(document.getElementById('pnlByClientChart'), clientRows, 'client_name', 'net_profit');
        renderPnlBreakdownChart(document.getElementById('pnlBySalesRepChart'), salesRepRows, 'sales_rep_name', 'commission');
        renderPnlClientEmployeeTable(clientRows);
        renderPnlSalesRepTable(salesRepRows);
        renderPnlExpensesTable();
    }

    window.generatePnlReport = async function generatePnlReport() {
        if (!pnlCanLoadData) {
            alert('You do not have permission to load payroll data.');
            return;
        }
        const startDate = pnlDateStart();
        const endDate = pnlDateEnd();

        const section = document.getElementById('payrollPnlSection');
        if (section) section.setAttribute('aria-busy', 'true');

        try {
            const params = pnlApiQueryParams(startDate, endDate);
            const [response] = await Promise.all([
                fetch('/api/payroll/pnl-invoice-basis?' + params.toString()),
                fetchPnlManualExpenses(startDate, endDate),
            ]);
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const result = await response.json();
            if (!result.success) {
                alert(result.message || 'Failed to load P&L data.');
                reportData = [];
                pnlCollectionsMeta = {
                    payroll_invoiced: 0,
                    billing_invoiced: 0,
                    billing_unallocated: 0,
                    total: 0,
                    paid_payroll_invoiced: 0,
                    paid_billing_invoiced: 0,
                    paid_billing_unallocated: 0,
                    paid_total: 0,
                };
                pnlWeekSnapshots = [];
                pnlApiExpensesBase = 0;
                pnlManualExpenses = [];
                pnlByClient = [];
                pnlBySalesRep = [];
                pnlPayrollSummary = {
                    total_paid_net_pay: 0,
                    total_unpaid_net_pay: 0,
                    total_paid_commission: 0,
                    total_unpaid_commission: 0,
                };
                pnlNetProfitMeta = { paid: 0, unpaid: 0 };
                clearPnlTrend12();
                refreshPnlDashboard();
                return;
            }
            pnlCollectionsMeta = {
                payroll_invoiced: parseFloat(result.collections?.payroll_invoiced) || 0,
                billing_invoiced: parseFloat(result.collections?.billing_invoiced) || 0,
                billing_unallocated: parseFloat(result.collections?.billing_unallocated) || 0,
                total: parseFloat(result.collections?.total) || 0,
                paid_payroll_invoiced: parseFloat(result.collections?.paid_payroll_invoiced) || 0,
                paid_billing_invoiced: parseFloat(result.collections?.paid_billing_invoiced) || 0,
                paid_billing_unallocated: parseFloat(result.collections?.paid_billing_unallocated) || 0,
                paid_total: parseFloat(result.collections?.paid_total) || 0,
            };
            pnlApiExpensesBase = parseFloat(result.expenses) || 0;
            pnlWeekSnapshots = (result.weekly || []).map(r => mapPnlWeekSnapshot(r));
            reportData = JSON.parse(JSON.stringify(result.data || []));
            pnlByClient = Array.isArray(result.by_client) ? result.by_client : [];
            pnlBySalesRep = Array.isArray(result.by_sales_rep) ? result.by_sales_rep : [];
            pnlPayrollSummary = {
                total_paid_net_pay: parseFloat(result.payroll_summary?.total_paid_net_pay) || 0,
                total_unpaid_net_pay: parseFloat(result.payroll_summary?.total_unpaid_net_pay) || 0,
                total_paid_commission: parseFloat(result.payroll_summary?.total_paid_commission) || 0,
                total_unpaid_commission: parseFloat(result.payroll_summary?.total_unpaid_commission) || 0,
            };
            pnlNetProfitMeta = {
                paid: parseFloat(result.net_profit_paid) || 0,
                unpaid: parseFloat(result.net_profit_unpaid) || 0,
            };
            populatePnlClientFilter(reportData, { preserve: !!pnlActiveClientId() });
            populatePnlSalesRepFilter(reportData, { preserve: !!pnlActiveSalesRepFilter() });
            if (pnlGranularity() === 'monthly') {
                await fetchPnl12MonthTrend();
            } else {
                clearPnlTrend12();
            }
            refreshPnlDashboard();
        } catch (e) {
            console.error(e);
            alert('Could not load P&L. Please try again.');
            reportData = [];
            pnlCollectionsMeta = {
                payroll_invoiced: 0,
                billing_invoiced: 0,
                billing_unallocated: 0,
                total: 0,
                paid_payroll_invoiced: 0,
                paid_billing_invoiced: 0,
                paid_billing_unallocated: 0,
                paid_total: 0,
            };
            pnlWeekSnapshots = [];
            pnlApiExpensesBase = 0;
            pnlManualExpenses = [];
            pnlByClient = [];
            pnlBySalesRep = [];
            pnlPayrollSummary = {
                total_paid_net_pay: 0,
                total_unpaid_net_pay: 0,
                total_paid_commission: 0,
                total_unpaid_commission: 0,
            };
            pnlNetProfitMeta = { paid: 0, unpaid: 0 };
            clearPnlTrend12();
            refreshPnlDashboard();
        } finally {
            if (section) section.removeAttribute('aria-busy');
        }
    };

    document.getElementById('payrollPnlGranularity')?.addEventListener('change', async function () {
        if (!reportData.length) return;
        if (pnlGranularity() === 'monthly') {
            await fetchPnl12MonthTrend();
        } else {
            clearPnlTrend12();
        }
        refreshPnlDashboard();
    });

    function todayYmdLocal() {
        const n = new Date();
        return n.getFullYear() + '-' + String(n.getMonth() + 1).padStart(2, '0') + '-' + String(n.getDate()).padStart(2, '0');
    }

    function clampDateToPnlMonth(dStr) {
        const s = pnlDateStart();
        const e = pnlDateEnd();
        if (!dStr || dStr < s) return s;
        if (dStr > e) return e;
        return dStr;
    }

    function renderPnlExpensesTable() {
        const tbody = document.getElementById('pnlExpensesBody');
        const totalEl = document.getElementById('pnlExpensesTotal');
        if (!tbody) {
            return;
        }

        const rows = manualExpensesInSelectedMonth().slice().sort(function (a, b) {
            return String(a.date).localeCompare(String(b.date));
        });

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="payroll-pnl-empty">No expenses for this period.</td></tr>';
        } else {
            tbody.innerHTML = rows.map(function (e) {
                const notesText = (e.notes && String(e.notes).trim()) ? escapeHtml(String(e.notes).trim()) : '—';
                const clientLabel = e.client_name
                    ? pnlDisplayName(e.client_name)
                    : 'Company-wide';

                return '<tr>'
                    + '<th scope="row">' + escapeHtml(e.date) + '</th>'
                    + '<td>' + clientLabel + '</td>'
                    + '<td class="payroll-pnl-expense-notes">' + notesText + '</td>'
                    + '<td class="payroll-pnl-num">' + formatPayrollMoney(e.amount) + '</td>'
                    + '<td class="payroll-pnl-expense-actions-col">'
                    + '<div class="payroll-pnl-expense-actions">'
                    + '<button type="button" class="payroll-pnl-expense-action-btn" data-pnl-expense-edit="' + escapeHtml(String(e.id)) + '">Edit</button>'
                    + '<button type="button" class="payroll-pnl-expense-action-btn payroll-pnl-expense-action-btn--danger" data-pnl-expense-delete="' + escapeHtml(String(e.id)) + '">Delete</button>'
                    + '</div>'
                    + '</td>'
                    + '</tr>';
            }).join('');
        }

        if (totalEl) {
            totalEl.textContent = formatPayrollMoney(manualExpenseTotalInMonth());
        }
    }

    function refreshPnlExpenseViews() {
        renderPnlExpenseSavedList();
        renderPnlExpensesTable();
    }

    async function deletePnlExpenseById(id) {
        const response = await fetch('/api/payroll/pnl-expenses/' + encodeURIComponent(id), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': pnlCsrfToken(),
                'Accept': 'application/json',
            },
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Failed to remove expense.');
        }

        pnlManualExpenses = readManualExpensesList().filter(function (e) {
            return String(e.id) !== String(id);
        });

        if (pnlEditingExpenseId != null && String(pnlEditingExpenseId) === String(id)) {
            resetPnlExpenseFormMode();
        }

        refreshPnlExpenseViews();
        if (reportData.length) {
            refreshPnlDashboard();
        }
    }

    function setPnlExpenseModalMode(editingId) {
        pnlEditingExpenseId = editingId != null ? editingId : null;
        const title = document.getElementById('pnlExpenseModalTitle');
        const submitBtn = document.getElementById('pnlExpenseSubmitBtn');
        if (title) {
            title.textContent = pnlEditingExpenseId != null ? 'Edit expense' : 'Add expense';
        }
        if (submitBtn) {
            submitBtn.textContent = pnlEditingExpenseId != null ? 'Update expense' : 'Save expense';
        }
    }

    function resetPnlExpenseFormMode() {
        setPnlExpenseModalMode(null);
        const m = pnlExpenseModalEls();
        if (m.amount) {
            m.amount.value = '';
        }
        if (m.notes) {
            m.notes.value = '';
        }
    }

    function pnlExpenseModalEls() {
        return {
            overlay: document.getElementById('pnlExpenseModalOverlay'),
            form: document.getElementById('pnlExpenseForm'),
            date: document.getElementById('pnlExpenseDate'),
            amount: document.getElementById('pnlExpenseAmount'),
            client: document.getElementById('pnlExpenseClient'),
            notes: document.getElementById('pnlExpenseNotes'),
            err: document.getElementById('pnlExpenseFormError'),
            list: document.getElementById('pnlExpenseSavedList'),
        };
    }

    function populatePnlExpenseClientSelect() {
        const sel = pnlExpenseModalEls().client;
        if (!sel) return;
        const activeClientId = pnlActiveClientId();
        const sorted = Array.from(pnlClientMap.entries()).sort(function (a, b) {
            return String(a[1]).localeCompare(String(b[1]));
        });
        sel.innerHTML = '<option value="">Company-wide (all clients)</option>'
            + sorted.map(function (entry) {
                const selected = activeClientId && entry[0] === activeClientId ? ' selected' : '';
                return '<option value="' + entry[0] + '"' + selected + '>' + escapeHtml(entry[1]) + '</option>';
            }).join('');
        if (activeClientId) {
            sel.value = String(activeClientId);
        }
    }

    function setPnlExpenseFormError(msg) {
        const el = pnlExpenseModalEls().err;
        if (!el) return;
        if (msg) {
            el.textContent = msg;
            el.hidden = false;
        } else {
            el.textContent = '';
            el.hidden = true;
        }
    }

    function renderPnlExpenseSavedList() {
        const { list } = pnlExpenseModalEls();
        if (!list) return;
        const rows = manualExpensesInSelectedMonth().slice().sort(function (a, b) {
            return String(a.date).localeCompare(String(b.date));
        });
        if (!rows.length) {
            list.innerHTML = '<li class="payroll-pnl-modal-list-empty">No expenses saved for this month yet.</li>';
            return;
        }
        list.innerHTML = rows.map(function (e) {
            const notesText = (e.notes && String(e.notes).trim()) ? escapeHtml(String(e.notes).trim()) : '—';
            const clientLabel = e.client_name
                ? escapeHtml(String(e.client_name))
                : 'Company-wide';
            return '<li><div class="payroll-pnl-modal-list-meta"><strong>' + escapeHtml(e.date) + '</strong> · ' + formatPayrollMoney(e.amount)
                + '<div class="payroll-pnl-modal-list-date">' + clientLabel + ' · ' + notesText + '</div></div>'
                + '<button type="button" class="payroll-pnl-modal-list-remove" data-pnl-expense-id="' + escapeHtml(e.id) + '">Remove</button></li>';
        }).join('');
    }

    function openPnlExpenseModal(editingExpense) {
        const m = pnlExpenseModalEls();
        if (!m.overlay || !m.date || !m.amount || !m.notes) return;
        const start = pnlDateStart();
        const end = pnlDateEnd();
        m.date.min = start;
        m.date.max = end;
        populatePnlExpenseClientSelect();
        setPnlExpenseFormError('');

        if (editingExpense) {
            setPnlExpenseModalMode(editingExpense.id);
            m.date.value = editingExpense.date || clampDateToPnlMonth(todayYmdLocal());
            m.amount.value = String(editingExpense.amount ?? '');
            m.notes.value = editingExpense.notes || '';
            if (m.client) {
                m.client.value = editingExpense.client_id ? String(editingExpense.client_id) : '';
            }
        } else {
            resetPnlExpenseFormMode();
            m.date.value = clampDateToPnlMonth(todayYmdLocal());
            m.amount.value = '';
            m.notes.value = '';
        }

        renderPnlExpenseSavedList();
        m.overlay.hidden = false;
        m.overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        setTimeout(function () { m.date.focus(); }, 10);
    }

    function openPnlExpenseModalForEdit(expenseId) {
        const expense = readManualExpensesList().find(function (e) {
            return String(e.id) === String(expenseId);
        });
        if (!expense) {
            return;
        }
        openPnlExpenseModal(expense);
    }

    function closePnlExpenseModal() {
        const m = pnlExpenseModalEls();
        if (!m.overlay) return;
        m.overlay.hidden = true;
        m.overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        setPnlExpenseFormError('');
        resetPnlExpenseFormMode();
    }

    document.getElementById('pnlDateStart')?.addEventListener('change', function () {
        const clientSel = document.getElementById('pnlFilterClient');
        const salesRepSel = document.getElementById('pnlFilterSalesRep');
        if (clientSel) clientSel.value = '';
        if (salesRepSel) salesRepSel.value = '';
        closePnlExpenseModal();
        window.generatePnlReport();
    });
    document.getElementById('pnlDateEnd')?.addEventListener('change', function () {
        const clientSel = document.getElementById('pnlFilterClient');
        const salesRepSel = document.getElementById('pnlFilterSalesRep');
        if (clientSel) clientSel.value = '';
        if (salesRepSel) salesRepSel.value = '';
        closePnlExpenseModal();
        window.generatePnlReport();
    });
    document.getElementById('pnlFilterClient')?.addEventListener('change', function () {
        window.generatePnlReport();
    });
    document.getElementById('pnlFilterSalesRep')?.addEventListener('change', function () {
        window.generatePnlReport();
    });

    document.getElementById('payrollPnlAddExpenseBtn')?.addEventListener('click', function () {
        openPnlExpenseModal();
    });

    document.getElementById('pnlExpensesBody')?.addEventListener('click', async function (ev) {
        const editBtn = ev.target.closest('[data-pnl-expense-edit]');
        if (editBtn) {
            openPnlExpenseModalForEdit(editBtn.getAttribute('data-pnl-expense-edit'));
            return;
        }

        const deleteBtn = ev.target.closest('[data-pnl-expense-delete]');
        if (!deleteBtn) {
            return;
        }

        if (!confirm('Delete this expense?')) {
            return;
        }

        deleteBtn.disabled = true;
        try {
            await deletePnlExpenseById(deleteBtn.getAttribute('data-pnl-expense-delete'));
        } catch (e) {
            console.error(e);
            alert(e.message || 'Could not remove expense. Please try again.');
            deleteBtn.disabled = false;
        }
    });

    document.getElementById('pnlExpenseModalClose')?.addEventListener('click', closePnlExpenseModal);
    document.getElementById('pnlExpenseModalCancel')?.addEventListener('click', closePnlExpenseModal);
    document.getElementById('pnlExpenseModalOverlay')?.addEventListener('click', function (ev) {
        if (ev.target === ev.currentTarget) closePnlExpenseModal();
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && document.getElementById('pnlExpenseModalOverlay') && !document.getElementById('pnlExpenseModalOverlay').hidden) {
            closePnlExpenseModal();
        }
    });

    document.getElementById('pnlExpenseSavedList')?.addEventListener('click', async function (ev) {
        const btn = ev.target.closest('[data-pnl-expense-id]');
        if (!btn) return;
        if (!confirm('Delete this expense?')) {
            return;
        }
        const id = btn.getAttribute('data-pnl-expense-id');
        btn.disabled = true;
        try {
            await deletePnlExpenseById(id);
        } catch (e) {
            console.error(e);
            alert(e.message || 'Could not remove expense. Please try again.');
            btn.disabled = false;
        }
    });

    document.getElementById('pnlExpenseForm')?.addEventListener('submit', async function (ev) {
        ev.preventDefault();
        const m = pnlExpenseModalEls();
        const start = pnlDateStart();
        const end = pnlDateEnd();
        const dateVal = m.date && m.date.value;
        const amtRaw = m.amount && m.amount.value;
        const amt = parseFloat(String(amtRaw).replace(/[$,]/g, '').trim());
        const notes = m.notes ? String(m.notes.value).trim() : '';
        const clientVal = m.client && m.client.value ? parseInt(m.client.value, 10) : null;

        if (!dateVal) {
            setPnlExpenseFormError('Please select a date.');
            return;
        }
        if (dateVal < start || dateVal > end) {
            setPnlExpenseFormError('Date must fall within the selected month.');
            return;
        }
        if (isNaN(amt) || amt <= 0) {
            setPnlExpenseFormError('Enter a valid amount greater than zero.');
            return;
        }

        const submitBtn = m.form?.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;
        setPnlExpenseFormError('');

        try {
            const isEdit = pnlEditingExpenseId != null;
            const response = await fetch(
                isEdit
                    ? '/api/payroll/pnl-expenses/' + encodeURIComponent(String(pnlEditingExpenseId))
                    : '/api/payroll/pnl-expenses',
                {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': pnlCsrfToken(),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        date: dateVal,
                        amount: roundMoney(amt),
                        notes: notes,
                        client_id: clientVal && !isNaN(clientVal) ? clientVal : null,
                        start_date: start,
                        end_date: end,
                    }),
                }
            );
            const result = await response.json();
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Failed to save expense.');
            }

            if (isEdit) {
                pnlManualExpenses = readManualExpensesList().map(function (e) {
                    return String(e.id) === String(result.data.id) ? result.data : e;
                });
                resetPnlExpenseFormMode();
            } else {
                pnlManualExpenses = readManualExpensesList().concat([result.data]);
                m.amount.value = '';
                m.notes.value = '';
                m.date.value = clampDateToPnlMonth(dateVal);
            }

            refreshPnlExpenseViews();
            if (reportData.length) refreshPnlDashboard();
        } catch (e) {
            console.error(e);
            setPnlExpenseFormError(e.message || 'Could not save expense. Please try again.');
        } finally {
            if (submitBtn) submitBtn.disabled = false;
        }
    });

    if (pnlCanLoadData) {
        window.generatePnlReport();
    }
})();
</script>
@endpush
