{{-- P&L: month/year filter, granularity, manual expenses; data loads automatically. --}}
@php
    $pnlDefaultStart = date('Y-m-01');
    $pnlDefaultEnd = date('Y-m-t');
@endphp
<div class="payroll-pnl-module" id="payrollPnlSection">
    <div class="payroll-pnl-module-inner">
        <header class="payroll-pnl-module-head">
            <div class="payroll-pnl-module-head-text">
                <h3 class="payroll-pnl-module-title">P&amp;L</h3>
                <p class="payroll-pnl-module-lead">Profit and loss from invoiced amounts (payroll conversion lines + billing) for the selected month</p>
                <p class="payroll-pnl-module-section-label">Profit &amp; Loss <span class="payroll-pnl-module-muted">— invoice collections vs payroll cost &amp; expenses</span></p>
            </div>
        </header>

        <div class="payroll-pnl-toolbar">
            <div class="payroll-pnl-toolbar-period">
                <label class="payroll-pnl-field-label" for="pnlDateStart">From</label>
                <input type="date" id="pnlDateStart" class="payroll-pnl-date-input" value="{{ $pnlDefaultStart }}" aria-label="Start date">
                <label class="payroll-pnl-field-label" for="pnlDateEnd">To</label>
                <input type="date" id="pnlDateEnd" class="payroll-pnl-date-input" value="{{ $pnlDefaultEnd }}" aria-label="End date">
            </div>
            <div class="payroll-pnl-toolbar-trail">
                <label class="payroll-pnl-field-label" for="pnlFilterClient">Client</label>
                <select id="pnlFilterClient" class="payroll-pnl-select payroll-pnl-select--client" aria-label="Filter by client">
                    <option value="">All clients</option>
                </select>
                <label class="payroll-pnl-field-label" for="pnlFilterSalesRep">Sales rep</label>
                <select id="pnlFilterSalesRep" class="payroll-pnl-select payroll-pnl-select--sales-rep" aria-label="Filter by sales rep">
                    <option value="">All sales reps</option>
                </select>
                <label class="payroll-pnl-granularity">
                    <select id="payrollPnlGranularity" class="payroll-pnl-select" aria-label="Period breakdown (weekly or monthly)">
                        <option value="weekly" selected>Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </label>
                <button type="button" class="payroll-pnl-expense-btn" id="payrollPnlAddExpenseBtn">
                    <span aria-hidden="true">+</span> Add expense
                </button>
            </div>
        </div>

        <div class="payroll-pnl-results" id="payrollPnlResults" hidden>
        <div class="payroll-pnl-kpi-row" id="payrollPnlKpiRow">
            <div class="payroll-pnl-kpi-card">
                <div class="payroll-pnl-kpi-top">
                    <span class="payroll-pnl-kpi-label">Total collections</span>
                    <span class="payroll-pnl-kpi-icon payroll-pnl-kpi-icon--up" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/></svg>
                    </span>
                </div>
                <span class="payroll-pnl-kpi-value" id="pnlKpiCollections">$0.00</span>
                <span class="payroll-pnl-kpi-sub" id="pnlKpiCollectionsUnpaid" hidden></span>
                <span class="payroll-pnl-kpi-hint" id="pnlKpiCollectionsBreakdown">Paid status</span>
            </div>
            <div class="payroll-pnl-kpi-card">
                <div class="payroll-pnl-kpi-top">
                    <span class="payroll-pnl-kpi-label">Total payroll</span>
                    <span class="payroll-pnl-kpi-icon payroll-pnl-kpi-icon--down" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 18l-9.5-9.5-5 5L1 6"/><path d="M17 18h6v-6"/></svg>
                    </span>
                </div>
                <span class="payroll-pnl-kpi-value" id="pnlKpiPayroll">$0.00</span>
                <span class="payroll-pnl-kpi-sub" id="pnlKpiPayrollUnpaid" hidden></span>
                <span class="payroll-pnl-kpi-hint" id="pnlKpiPayrollBreakdown">Net pay from generated payroll period invoices · Paid status</span>
            </div>
            <div class="payroll-pnl-kpi-card">
                <div class="payroll-pnl-kpi-top">
                    <span class="payroll-pnl-kpi-label">Commission</span>
                    <span class="payroll-pnl-kpi-icon payroll-pnl-kpi-icon--com" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                </div>
                <span class="payroll-pnl-kpi-value" id="pnlKpiCommission">$0.00</span>
                <span class="payroll-pnl-kpi-sub" id="pnlKpiCommissionUnpaid" hidden></span>
                <span class="payroll-pnl-kpi-hint" id="pnlKpiCommissionBreakdown">Paid status</span>
            </div>
            <div class="payroll-pnl-kpi-card">
                <div class="payroll-pnl-kpi-top">
                    <span class="payroll-pnl-kpi-label">Internal Expenses / CC</span>
                    <span class="payroll-pnl-kpi-icon payroll-pnl-kpi-icon--exp" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                    </span>
                </div>
                <span class="payroll-pnl-kpi-value" id="pnlKpiExpenses">$0.00</span>
                <span class="payroll-pnl-kpi-sub" id="pnlKpiExpensesUnpaid" hidden></span>
            </div>
            <div class="payroll-pnl-kpi-card payroll-pnl-kpi-card--emphasis">
                <div class="payroll-pnl-kpi-top">
                    <span class="payroll-pnl-kpi-label">Net profit</span>
                    <span class="payroll-pnl-kpi-icon payroll-pnl-kpi-icon--net" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                </div>
                <span class="payroll-pnl-kpi-value" id="pnlKpiNetProfit">$0.00</span>
                <span class="payroll-pnl-kpi-sub" id="pnlKpiNetProfitUnpaid" hidden></span>
                <span class="payroll-pnl-kpi-hint" id="pnlKpiNetProfitBreakdown">Paid status</span>
            </div>
        </div>

        <section class="payroll-pnl-trend" aria-labelledby="payrollPnlTrendHeading">
            <h4 class="payroll-pnl-subheading" id="payrollPnlTrendHeading">P&amp;L trend</h4>
            <p class="payroll-pnl-trend-caption" id="pnlTrendCaption">Net profit by week (collections from invoice dates in range)</p>
            <p class="payroll-pnl-chart-legend">
                <span class="payroll-pnl-legend-item"><span class="payroll-pnl-legend-swatch payroll-pnl-legend-swatch--paid"></span> Paid</span>
                <span class="payroll-pnl-legend-item"><span class="payroll-pnl-legend-swatch payroll-pnl-legend-swatch--unpaid"></span> Unpaid</span>
            </p>
            <div class="payroll-pnl-trend-chart" id="pnlTrendChartBars" role="img" aria-label="Bar chart of net profit by period"></div>
        </section>

        <section class="payroll-pnl-breakdown" aria-labelledby="payrollPnlBreakdownHeading">
            <h4 class="payroll-pnl-subheading" id="payrollPnlBreakdownHeading">Period breakdown</h4>
            <div class="payroll-pnl-table-scroll">
                <table class="payroll-pnl-period-table" id="pnlPeriodBreakdownTable">
                    <thead>
                        <tr>
                            <th scope="col">Period</th>
                            <th scope="col" class="payroll-pnl-num">Collections</th>
                            <th scope="col" class="payroll-pnl-num">Net Pay</th>
                            <th scope="col" class="payroll-pnl-num">Commission</th>
                            <th scope="col" class="payroll-pnl-num">Expenses</th>
                            <th scope="col" class="payroll-pnl-num">Net profit</th>
                        </tr>
                    </thead>
                    <tbody id="pnlPeriodBreakdownBody"></tbody>
                    <tfoot>
                        <tr class="payroll-pnl-period-total">
                            <th scope="row">Total</th>
                            <td class="payroll-pnl-num" id="pnlPeriodTotalCollections">$0.00</td>
                            <td class="payroll-pnl-num" id="pnlPeriodTotalPayroll">$0.00</td>
                            <td class="payroll-pnl-num" id="pnlPeriodTotalCommission">$0.00</td>
                            <td class="payroll-pnl-num" id="pnlPeriodTotalExpenses">$0.00</td>
                            <td class="payroll-pnl-num" id="pnlPeriodTotalNet">$0.00</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <div class="payroll-pnl-dimension-grid">
            <section class="payroll-pnl-dimension payroll-pnl-dimension--client" aria-labelledby="payrollPnlByClientHeading">
                <h4 class="payroll-pnl-subheading" id="payrollPnlByClientHeading">By client</h4>
                <p class="payroll-pnl-trend-caption">Net profit by client (invoiced collections vs payroll cost &amp; commission)</p>
                <div class="payroll-pnl-trend-chart payroll-pnl-dimension-chart" id="pnlByClientChart" role="img" aria-label="Bar chart of net profit by client"></div>
                <div class="payroll-pnl-table-scroll payroll-pnl-dimension-table-wrap">
                    <table class="payroll-pnl-period-table">
                        <thead>
                            <tr>
                                <th scope="col" class="payroll-pnl-client-col">Client</th>
                                <th scope="col" class="payroll-pnl-employee-col">Employee</th>
                                <th scope="col" class="payroll-pnl-num">Collections</th>
                                <th scope="col" class="payroll-pnl-num">Net Pay</th>
                                <th scope="col" class="payroll-pnl-num">Commission</th>
                                <th scope="col" class="payroll-pnl-num">Expenses</th>
                                <th scope="col" class="payroll-pnl-num">Net profit</th>
                            </tr>
                        </thead>
                        <tbody id="pnlByClientBody"></tbody>
                        <tfoot>
                            <tr class="payroll-pnl-period-total">
                                <th scope="row" colspan="2">Total</th>
                                <td class="payroll-pnl-num" id="pnlByClientTotalCollections">$0.00</td>
                                <td class="payroll-pnl-num" id="pnlByClientTotalNetPay">$0.00</td>
                                <td class="payroll-pnl-num" id="pnlByClientTotalCommission">$0.00</td>
                                <td class="payroll-pnl-num" id="pnlByClientTotalExpenses">$0.00</td>
                                <td class="payroll-pnl-num" id="pnlByClientTotalNet">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <div class="payroll-pnl-dimension-pair">
            <section class="payroll-pnl-dimension payroll-pnl-dimension--sales-rep" aria-labelledby="payrollPnlBySalesRepHeading">
                <h4 class="payroll-pnl-subheading" id="payrollPnlBySalesRepHeading">By sales rep</h4>
                <p class="payroll-pnl-trend-caption">Commission by assigned sales rep</p>
                <div class="payroll-pnl-trend-chart payroll-pnl-dimension-chart" id="pnlBySalesRepChart" role="img" aria-label="Bar chart of commission by sales rep"></div>
                <div class="payroll-pnl-table-scroll payroll-pnl-dimension-table-wrap">
                    <table class="payroll-pnl-period-table">
                        <thead>
                            <tr>
                                <th scope="col">Sales rep</th>
                                <th scope="col" class="payroll-pnl-num">Commission</th>
                            </tr>
                        </thead>
                        <tbody id="pnlBySalesRepBody"></tbody>
                        <tfoot>
                            <tr class="payroll-pnl-period-total">
                                <th scope="row">Total</th>
                                <td class="payroll-pnl-num" id="pnlBySalesRepTotalCommission">$0.00</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <section class="payroll-pnl-dimension payroll-pnl-dimension--expenses" aria-labelledby="payrollPnlExpensesHeading">
                <h4 class="payroll-pnl-subheading" id="payrollPnlExpensesHeading">Expenses</h4>
                <p class="payroll-pnl-trend-caption">Internal expenses &amp; credit card charges for the selected period</p>
                <div class="payroll-pnl-table-scroll payroll-pnl-dimension-table-wrap">
                    <table class="payroll-pnl-period-table payroll-pnl-expenses-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col">Client</th>
                                <th scope="col">Notes</th>
                                <th scope="col" class="payroll-pnl-num">Amount</th>
                                <th scope="col" class="payroll-pnl-expense-actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pnlExpensesBody"></tbody>
                        <tfoot>
                            <tr class="payroll-pnl-period-total">
                                <th scope="row" colspan="3">Total</th>
                                <td class="payroll-pnl-num" id="pnlExpensesTotal">$0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
            </div>
        </div>
        </div>
    </div>
</div>

<div class="payroll-pnl-modal-overlay" id="pnlExpenseModalOverlay" hidden aria-hidden="true">
    <div class="payroll-pnl-modal" role="dialog" aria-modal="true" aria-labelledby="pnlExpenseModalTitle" id="pnlExpenseModal">
        <div class="payroll-pnl-modal-head">
            <h3 class="payroll-pnl-modal-title" id="pnlExpenseModalTitle">Add expense</h3>
            <button type="button" class="payroll-pnl-modal-close" id="pnlExpenseModalClose" aria-label="Close">&times;</button>
        </div>
        <form class="payroll-pnl-modal-form" id="pnlExpenseForm">
            <div class="payroll-pnl-modal-field">
                <label for="pnlExpenseDate">Date</label>
                <input type="date" id="pnlExpenseDate" class="payroll-pnl-modal-input" required>
            </div>
            <div class="payroll-pnl-modal-field">
                <label for="pnlExpenseAmount">Amount (USD)</label>
                <input type="number" id="pnlExpenseAmount" class="payroll-pnl-modal-input" min="0.01" step="0.01" required placeholder="0.00">
            </div>
            <div class="payroll-pnl-modal-field">
                <label for="pnlExpenseClient">Client <span class="payroll-pnl-module-muted">(optional)</span></label>
                <select id="pnlExpenseClient" class="payroll-pnl-modal-input" aria-label="Client for this expense">
                    <option value="">Company-wide (all clients)</option>
                </select>
            </div>
            <div class="payroll-pnl-modal-field">
                <label for="pnlExpenseNotes">Notes</label>
                <textarea id="pnlExpenseNotes" class="payroll-pnl-modal-textarea" rows="3" placeholder="Optional description"></textarea>
            </div>
            <p class="payroll-pnl-modal-error" id="pnlExpenseFormError" hidden></p>
            <div class="payroll-pnl-modal-actions">
                <button type="button" class="payroll-pnl-modal-btn payroll-pnl-modal-btn--ghost" id="pnlExpenseModalCancel">Cancel</button>
                <button type="submit" class="payroll-pnl-modal-btn payroll-pnl-modal-btn--primary" id="pnlExpenseSubmitBtn">Save expense</button>
            </div>
        </form>
        <div class="payroll-pnl-modal-list-wrap">
            <h4 class="payroll-pnl-modal-sub">Expenses for this month</h4>
            <ul class="payroll-pnl-modal-list" id="pnlExpenseSavedList"></ul>
        </div>
    </div>
</div>
