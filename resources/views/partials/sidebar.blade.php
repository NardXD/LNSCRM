<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            @auth
                @php
                    $company = auth()->user()->company;
                    $companyLogo = $company?->logoUrl();
                    $companyName = $company ? $company->name : 'CRM';
                @endphp
                @if($companyLogo)
                    <div class="logo-icon" style="background: transparent; padding: 0;">
                        <img src="{{ $companyLogo }}" alt="{{ $companyName }}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 10px;">
                    </div>
                @else
                    <div class="logo-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                            <path d="M2 17l10 5 10-5"/>
                            <path d="M2 12l10 5 10-5"/>
                        </svg>
                    </div>
                @endif
                <span class="logo-text">{{ $companyName }}</span>
            @else
            <div class="logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5z"/>
                    <path d="M2 17l10 5 10-5"/>
                    <path d="M2 12l10 5 10-5"/>
                </svg>
            </div>
                <span class="logo-text">CRM</span>
            @endauth
        </div>
        <button class="sidebar-toggle" onclick="toggleSidebar()" id="sidebarToggleBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" id="toggleIcon">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
        </button>
    </div>

    @php
        use App\Helpers\SidebarHelper;
        $menuItems = SidebarHelper::getFilteredMenuItems($userPermissions ?? [], $companyModuleSlugs ?? null);
        $mainItems = array_filter($menuItems, fn($item) => $item['category'] === 'main');
        $settingsItems = array_filter($menuItems, fn($item) => $item['category'] === 'settings');
    @endphp

    <nav class="nav">
        @if(count($mainItems) > 0)
        <div class="nav-section">
            <div class="nav-label">Main</div>
            @foreach($mainItems as $item)
                @php
                    $permKeys = $item['permission_any'] ?? [$item['permission']];
                    $hasPermission = auth()->check() && ! empty(array_intersect($permKeys, $userPermissions ?? []));
                @endphp
                @if($hasPermission)
                    @if($item['route'] === 'payroll')
                        @php
                            $hasPayroll = in_array('view_payroll', $userPermissions ?? []);
                            $hasWiseRecipients = in_array('view_wise_recipients', $userPermissions ?? []);
                            $hasSalesRepPayrollReport = in_array('view_payroll_sales_rep_report', $userPermissions ?? []);
                            $hasPnlPermission = auth()->check() && auth()->user()->hasPermission('view_pnl');
                            $showPnlLink = $hasPnlPermission && ($companyHasPnlFeature ?? true);
                            $showPayrollMenu = $hasPayroll || $hasWiseRecipients || $showPnlLink || $hasSalesRepPayrollReport;
                        @endphp
                        @if($showPayrollMenu)
                        <div class="nav-item-parent {{ request()->routeIs('payroll') || request()->routeIs('payroll.sales-reps') || request()->routeIs('wise-recipients') || request()->routeIs('pnl') ? 'active' : '' }}">
                            <div class="nav-item nav-item-toggle" onclick="toggleSubmenu('payrollSubmenu')">
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                                <span class="nav-text">Payroll</span>
                                <svg class="nav-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="nav-submenu" id="payrollSubmenu" style="display: {{ request()->routeIs('payroll') || request()->routeIs('payroll.sales-reps') || request()->routeIs('wise-recipients') || request()->routeIs('pnl') ? 'block' : 'none' }};">
                                @if($hasPayroll)
                                <a href="{{ route('payroll') }}" class="nav-subitem {{ request()->routeIs('payroll') && !request()->routeIs('wise-recipients') && !request()->routeIs('pnl') ? 'active' : '' }}">
                                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="1" x2="12" y2="23"/>
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                    <span class="nav-text">Payroll</span>
                                </a>
                                @endif
                                @if(auth()->check() && auth()->user()->hasPermission('view_payroll_sales_rep_report'))
                                <a href="{{ route('payroll.sales-reps') }}" class="nav-subitem {{ request()->routeIs('payroll.sales-reps') ? 'active' : '' }}">
                                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    <span class="nav-text">Payroll Report</span>
                                </a>
                                @endif
                                @if($showPnlLink)
                                <a href="{{ route('pnl') }}" class="nav-subitem {{ request()->routeIs('pnl') ? 'active' : '' }}">
                                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="18" y1="20" x2="18" y2="10"/>
                                        <line x1="12" y1="20" x2="12" y2="4"/>
                                        <line x1="6" y1="20" x2="6" y2="14"/>
                                    </svg>
                                    <span class="nav-text">P&amp;L</span>
                                </a>
                                @endif
                                @if($hasWiseRecipients)
                                <a href="{{ route('wise-recipients') }}" class="nav-subitem {{ request()->routeIs('wise-recipients') ? 'active' : '' }}">
                                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    <span class="nav-text">Wise Recipients</span>
                                </a>
                                @endif
                            </div>
                        </div>
                        @endif
                    @elseif($item['route'] === 'quotation-builder')
                        @php
                            $hasQuotationBuilder = in_array('view_quotation_builder', $userPermissions ?? []);
                            $hasQuotationEmailTemplate = in_array('view_quotation_builder_email_template', $userPermissions ?? []);
                            $hasQuotationM365Mail = in_array('view_quotation_builder_microsoft_365_mail', $userPermissions ?? []);
                            $showQuotationMenu = $hasQuotationBuilder || $hasQuotationEmailTemplate || $hasQuotationM365Mail;
                        @endphp
                        @if($showQuotationMenu)
                        <div class="nav-item-parent {{ request()->routeIs('quotation-builder') || request()->routeIs('quotation-builder.*') ? 'active' : '' }}">
                            <div class="nav-item nav-item-toggle" onclick="toggleSubmenu('quotationBuilderSubmenu')">
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 19l7-7 3 3-7 7-3-3z"/>
                                    <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                                    <path d="M2 2l7.586 7.586"/>
                                    <circle cx="11" cy="11" r="2"/>
                                </svg>
                                <span class="nav-text">Quotation Builder</span>
                                <svg class="nav-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 12 15 18 9"/>
                                </svg>
                            </div>
                            <div class="nav-submenu" id="quotationBuilderSubmenu" style="display: {{ request()->routeIs('quotation-builder') || request()->routeIs('quotation-builder.*') ? 'block' : 'none' }};">
                                @if($hasQuotationBuilder)
                                <a href="{{ route('quotation-builder') }}" class="nav-subitem {{ request()->routeIs('quotation-builder') && !request()->routeIs('quotation-builder.microsoft-365-mail') && !request()->routeIs('quotation-builder.email-template') && !request()->routeIs('quotation-builder.leads.quote') ? 'active' : '' }}">
                                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 19l7-7 3 3-7 7-3-3z"/>
                                        <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                                        <path d="M2 2l7.586 7.586"/>
                                        <circle cx="11" cy="11" r="2"/>
                                    </svg>
                                    <span class="nav-text">Leads &amp; quotes</span>
                                </a>
                                @endif
                                @if($hasQuotationEmailTemplate)
                                <a href="{{ route('quotation-builder.email-template') }}" class="nav-subitem {{ request()->routeIs('quotation-builder.email-template') ? 'active' : '' }}">
                                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                        <polyline points="22,6 12,13 2,6"/>
                                    </svg>
                                    <span class="nav-text">Email template</span>
                                </a>
                                @endif
                                @if($hasQuotationM365Mail)
                                <a href="{{ route('quotation-builder.microsoft-365-mail') }}" class="nav-subitem {{ request()->routeIs('quotation-builder.microsoft-365-mail') ? 'active' : '' }}">
                                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                        <polyline points="22,6 12,13 2,6"/>
                                    </svg>
                                    <span class="nav-text">Microsoft 365 Mail</span>
                                </a>
                                @endif
                            </div>
                        </div>
                        @endif
                    @else
                        <a href="{{ route($item['route']) }}" class="nav-item {{ request()->routeIs($item['route']) || request()->routeIs($item['route'] . '.*') ? 'active' : '' }}">
                            @if($item['icon'] === 'dashboard')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="7" height="7"/>
                                    <rect x="14" y="3" width="7" height="7"/>
                                    <rect x="14" y="14" width="7" height="7"/>
                                    <rect x="3" y="14" width="7" height="7"/>
                                </svg>
                            @elseif($item['icon'] === 'clock')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polyline points="12 6 12 12 16 14"/>
                                </svg>
                            @elseif($item['icon'] === 'users')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            @elseif($item['icon'] === 'monitor')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                            @elseif($item['icon'] === 'phone')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                                </svg>
                            @elseif($item['icon'] === 'dollar-sign')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                            @elseif($item['icon'] === 'layers')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                                </svg>
                            @elseif($item['icon'] === 'message-circle')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                            @elseif($item['icon'] === 'credit-card')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="1" x2="12" y2="23"/>
                                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                </svg>
                            @elseif($item['icon'] === 'briefcase')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            @elseif($item['icon'] === 'clipboard-list')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                                    <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                                    <path d="M9 14l2 2 4-4"/>
                                </svg>
                            @elseif($item['icon'] === 'user-plus')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <line x1="19" y1="8" x2="19" y2="14"/>
                                    <line x1="22" y1="11" x2="16" y2="11"/>
                                </svg>
                            @elseif($item['icon'] === 'bar-chart')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="20" x2="12" y2="10"/>
                                    <line x1="18" y1="20" x2="18" y2="4"/>
                                    <line x1="6" y1="20" x2="6" y2="14"/>
                                </svg>
                            @elseif($item['icon'] === 'mail')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                                    <polyline points="22,6 12,13 2,6"/>
                                </svg>
                            @elseif($item['icon'] === 'inbox')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/>
                                    <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>
                                </svg>
                            @elseif($item['icon'] === 'viber')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                    <path d="M14.5 9.5c.5 1.5 1.5 2.5 3 3"/>
                                    <path d="M12.5 8c.8 2.5 2.5 4.2 5 5"/>
                                </svg>
                            @elseif($item['icon'] === 'whatsapp')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>
                                    <path d="M9.5 10.5c.5 2 2 3.5 4 4"/>
                                </svg>
                            @elseif($item['icon'] === 'facebook')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                                </svg>
                            @elseif($item['icon'] === 'sms')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                    <line x1="8" y1="9" x2="16" y2="9"/>
                                    <line x1="8" y1="13" x2="13" y2="13"/>
                                </svg>
                            @elseif($item['icon'] === 'megaphone')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 11l19-7v16L3 13v-2z"/>
                                    <path d="M11.6 16.8a3 3 0 0 1-3 3H7"/>
                                </svg>
                            @elseif($item['icon'] === 'book')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/>
                                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
                                </svg>
                            @elseif($item['icon'] === 'link')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="3"/>
                                    <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"/>
                                </svg>
                            @elseif($item['icon'] === 'pen-tool')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 19l7-7 3 3-7 7-3-3z"/>
                                    <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                                    <path d="M2 2l7.586 7.586"/>
                                    <circle cx="11" cy="11" r="2"/>
                                </svg>
                            @elseif($item['icon'] === 'file-text')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                    <polyline points="10 9 9 9 8 9"/>
                                </svg>
                            @elseif($item['icon'] === 'calendar')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/>
                                    <line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                            @elseif($item['icon'] === 'zap')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"/>
                                    <line x1="12" y1="22.08" x2="12" y2="12"/>
                                </svg>
                            @elseif($item['icon'] === 'key')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                                </svg>
                            @elseif($item['icon'] === 'settings')
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                            @endif
                            <span class="nav-text">{{ $item['label'] }}</span>
                            @if(in_array($item['route'], ['messaging', 'viber', 'whatsapp', 'facebook', 'sms'], true))
                                <span class="nav-unread-badge" data-channel="{{ $item['route'] }}" style="display: none;" aria-hidden="true"></span>
                            @endif
                        </a>
                    @endif
                @endif
            @endforeach
        </div>
        @endif

        @if(count($settingsItems) > 0)
        <div class="nav-section">
            <div class="nav-label">Settings</div>
            @foreach($settingsItems as $item)
                @php
                    $permKeysSettings = $item['permission_any'] ?? [$item['permission']];
                    $hasPermission = auth()->check() && ! empty(array_intersect($permKeysSettings, $userPermissions ?? []));
                @endphp
                @if($hasPermission)
                    <a href="{{ route($item['route']) }}" class="nav-item {{ request()->routeIs($item['route']) ? 'active' : '' }}">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"/>
                            </svg>
                            <span class="nav-text">{{ $item['label'] }}</span>
                        </a>
                @endif
            @endforeach
        </div>
        @endif
    </nav>
</aside>

