<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Datamatics Eswatini</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell antialiased">
    @if(auth()->check())
        @php
            $user = auth()->user();
            $unreadTenderCount = $layoutUnreadTenderAssignments ?? 0;
            $unreadQuotationCount = $layoutUnreadQuotationAssignments ?? 0;
            $unreadOperationsCount = $unreadTenderCount + $unreadQuotationCount;
            $unreadCrmNotifications = $layoutUnreadCrmNotifications ?? 0;

            $activeModule = 'dashboard';

            if (request()->routeIs('clients.*')) {
                $activeModule = 'clients';
            } elseif (request()->routeIs('client-activities.*')) {
                $activeModule = 'clients';
            } elseif (request()->routeIs('sales-quotations.*') || request()->routeIs('job-cards.*') || request()->routeIs('delivery-notes.*') || request()->routeIs('invoices.*') || request()->routeIs('payments.*') || request()->routeIs('expenses.*') || request()->routeIs('catalog-items.*')) {
                $activeModule = 'finance';
            } elseif (request()->routeIs('tender-proposals.*') || request()->routeIs('quotations.*') || request()->routeIs('assignments.*') || request()->routeIs('submissions.*') || request()->routeIs('reminders.*') || request()->routeIs('requisitions.*')) {
                $activeModule = 'operations';
            } elseif (request()->routeIs('tasks.*')) {
                $activeModule = 'tasks';
            } elseif (request()->routeIs('attendance.*')) {
                $activeModule = 'attendance';
            } elseif (request()->routeIs('suppliers.*') || request()->routeIs('purchases.*')) {
                $activeModule = 'suppliers';
            } elseif (request()->routeIs('documents.*')) {
                $activeModule = 'documents';
            } elseif (request()->routeIs('approvals.*') || request()->routeIs('notifications.*')) {
                $activeModule = 'approvals';
            } elseif (request()->routeIs('reports.*')) {
                $activeModule = 'reports';
            } elseif (request()->routeIs('users.*') || request()->routeIs('departments.*') || request()->routeIs('settings.*')) {
                $activeModule = 'admin';
            }

            $moduleNav = [
                [
                    'key' => 'dashboard',
                    'label' => 'Dashboard',
                    'meta' => 'Executive view',
                    'route' => route('dashboard'),
                    'visible' => true,
                    'icon' => 'dashboard',
                    'badge' => null,
                ],
                [
                    'key' => 'clients',
                    'label' => 'Clients',
                    'meta' => 'Accounts and contacts',
                    'route' => route('clients.index'),
                    'visible' => true,
                    'icon' => 'clients',
                    'badge' => null,
                ],
                [
                    'key' => 'finance',
                    'label' => 'Finance',
                    'meta' => 'Quotations, invoices, payments',
                    'route' => route('sales-quotations.index'),
                    'visible' => true,
                    'icon' => 'finance',
                    'badge' => null,
                ],
                [
                    'key' => 'tasks',
                    'label' => 'Tasks',
                    'meta' => 'Work and deadlines',
                    'route' => route('tasks.index'),
                    'visible' => true,
                    'icon' => 'operations',
                    'badge' => null,
                ],
                [
                    'key' => 'attendance',
                    'label' => 'Attendance',
                    'meta' => 'Clock in and reports',
                    'route' => route('attendance.index'),
                    'visible' => true,
                    'icon' => 'reports',
                    'badge' => null,
                ],
                [
                    'key' => 'suppliers',
                    'label' => 'Suppliers',
                    'meta' => 'Procurement tracking',
                    'route' => route('suppliers.index'),
                    'visible' => true,
                    'icon' => 'clients',
                    'badge' => null,
                ],
                [
                    'key' => 'documents',
                    'label' => 'Documents',
                    'meta' => 'Central registry',
                    'route' => route('documents.index'),
                    'visible' => true,
                    'icon' => 'reports',
                    'badge' => null,
                ],
                [
                    'key' => 'approvals',
                    'label' => 'Approvals',
                    'meta' => 'Inbox and alerts',
                    'route' => route('approvals.index'),
                    'visible' => true,
                    'icon' => 'admin',
                    'badge' => $unreadCrmNotifications ?: null,
                ],
                [
                    'key' => 'operations',
                    'label' => 'Operations',
                    'meta' => 'Tender and request workflow',
                    'route' => $layoutNextUnreadTenderUrl ?? route('tender-proposals.index'),
                    'visible' => true,
                    'icon' => 'operations',
                    'badge' => $unreadOperationsCount ?: null,
                ],
                [
                    'key' => 'reports',
                    'label' => 'Reports',
                    'meta' => 'Performance and workload',
                    'route' => route('reports.index'),
                    'visible' => $user->canViewReports(),
                    'icon' => 'reports',
                    'badge' => null,
                ],
                [
                    'key' => 'admin',
                    'label' => 'Admin',
                    'meta' => 'Users, departments, settings',
                    'route' => route('users.index'),
                    'visible' => $user->isSuperAdmin(),
                    'icon' => 'admin',
                    'badge' => null,
                ],
            ];

            $moduleLabels = collect($moduleNav)->keyBy('key');
            $activeModuleMeta = $moduleLabels->get($activeModule);

            $subNavItems = match ($activeModule) {
                'clients' => [
                    ['label' => 'Client Register', 'route' => route('clients.index'), 'active' => request()->routeIs('clients.index') || request()->routeIs('clients.show'), 'visible' => true, 'badge' => null],
                    ['label' => 'New Client', 'route' => route('clients.create'), 'active' => request()->routeIs('clients.create'), 'visible' => $user->canManageFinance(), 'badge' => null],
                    ['label' => 'Follow-ups', 'route' => route('client-activities.index'), 'active' => request()->routeIs('client-activities.*'), 'visible' => true, 'badge' => null],
                ],
                'finance' => [
                    ['label' => 'Sales Quotations', 'route' => route('sales-quotations.index'), 'active' => request()->routeIs('sales-quotations.*'), 'visible' => true, 'badge' => null],
                    ['label' => 'Job Cards', 'route' => route('job-cards.index'), 'active' => request()->routeIs('job-cards.*'), 'visible' => true, 'badge' => null],
                    ['label' => 'Delivery Notes', 'route' => route('delivery-notes.index'), 'active' => request()->routeIs('delivery-notes.*'), 'visible' => true, 'badge' => null],
                    ['label' => 'Invoices', 'route' => route('invoices.index'), 'active' => request()->routeIs('invoices.*') || request()->routeIs('payments.*'), 'visible' => true, 'badge' => null],
                    ['label' => 'Expenses', 'route' => route('expenses.index'), 'active' => request()->routeIs('expenses.*'), 'visible' => true, 'badge' => null],
                    ['label' => 'Item Catalog', 'route' => route('catalog-items.index'), 'active' => request()->routeIs('catalog-items.*'), 'visible' => true, 'badge' => null],
                ],
                'operations' => [
                    ['label' => 'Tender Proposals', 'route' => $layoutNextUnreadTenderUrl ?? route('tender-proposals.index'), 'active' => request()->routeIs('tender-proposals.*'), 'visible' => true, 'badge' => $unreadTenderCount ?: null],
                    ['label' => 'Quotation Requests', 'route' => $layoutNextUnreadQuotationUrl ?? route('quotations.index'), 'active' => request()->routeIs('quotations.*'), 'visible' => true, 'badge' => $unreadQuotationCount ?: null],
                    ['label' => 'Assignments', 'route' => route('assignments.index'), 'active' => request()->routeIs('assignments.*'), 'visible' => $user->canManage(), 'badge' => null],
                    ['label' => 'Submissions', 'route' => route('submissions.index'), 'active' => request()->routeIs('submissions.*'), 'visible' => true, 'badge' => null],
                    ['label' => 'Requisitions', 'route' => route('requisitions.index'), 'active' => request()->routeIs('requisitions.*'), 'visible' => true, 'badge' => null],
                    ['label' => 'Reminders', 'route' => route('reminders.index'), 'active' => request()->routeIs('reminders.*'), 'visible' => true, 'badge' => null],
                ],
                'tasks' => [
                    ['label' => 'Task Register', 'route' => route('tasks.index'), 'active' => request()->routeIs('tasks.index') || request()->routeIs('tasks.show'), 'visible' => true, 'badge' => null],
                    ['label' => 'New Task', 'route' => route('tasks.create'), 'active' => request()->routeIs('tasks.create'), 'visible' => true, 'badge' => null],
                ],
                'attendance' => [
                    ['label' => 'Attendance Register', 'route' => route('attendance.index'), 'active' => request()->routeIs('attendance.*'), 'visible' => true, 'badge' => null],
                ],
                'suppliers' => [
                    ['label' => 'Supplier Register', 'route' => route('suppliers.index'), 'active' => request()->routeIs('suppliers.*'), 'visible' => true, 'badge' => null],
                    ['label' => 'Purchases', 'route' => route('purchases.index'), 'active' => request()->routeIs('purchases.*'), 'visible' => true, 'badge' => null],
                ],
                'documents' => [
                    ['label' => 'Document Registry', 'route' => route('documents.index'), 'active' => request()->routeIs('documents.*'), 'visible' => true, 'badge' => null],
                ],
                'approvals' => [
                    ['label' => 'Approval Inbox', 'route' => route('approvals.index'), 'active' => request()->routeIs('approvals.*'), 'visible' => $user->canViewReports(), 'badge' => null],
                    ['label' => 'Notifications', 'route' => route('notifications.index'), 'active' => request()->routeIs('notifications.*'), 'visible' => true, 'badge' => $unreadCrmNotifications ?: null],
                ],
                'reports' => [
                    ['label' => 'Company Reports', 'route' => route('reports.index'), 'active' => request()->routeIs('reports.*'), 'visible' => $user->canViewReports(), 'badge' => null],
                ],
                'admin' => [
                    ['label' => 'Users', 'route' => route('users.index'), 'active' => request()->routeIs('users.*'), 'visible' => $user->isSuperAdmin(), 'badge' => null],
                    ['label' => 'Departments', 'route' => route('departments.index'), 'active' => request()->routeIs('departments.*'), 'visible' => $user->isSuperAdmin(), 'badge' => null],
                    ['label' => 'Settings', 'route' => route('settings.index'), 'active' => request()->routeIs('settings.*'), 'visible' => $user->isSuperAdmin(), 'badge' => null],
                ],
                default => [
                    ['label' => 'Overview', 'route' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'visible' => true, 'badge' => null],
                ],
            };
        @endphp

        <header class="global-header" aria-label="Application header">
            <div class="global-header-inner">
                <a href="{{ route('dashboard') }}" class="global-brand">
                    <span class="global-logo">
                        @if(file_exists(public_path('images/app-logo.png')))
                            <img src="{{ asset('images/app-logo.png') }}" alt="Company logo">
                        @else
                            <span>OC</span>
                        @endif
                    </span>
                    <span class="global-brand-copy">
                        <strong>Datamatics Eswatini</strong>
                        <small>Business Operations Portal</small>
                    </span>
                </a>

                <div class="global-actions">
                    <a class="global-icon-button" href="{{ route('notifications.index') }}" aria-label="Notifications">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                            <path d="M10 21h4" />
                        </svg>
                        @if($unreadCrmNotifications)
                            <span class="global-badge">{{ $unreadCrmNotifications }}</span>
                        @endif
                    </a>

                    <button class="global-icon-button" type="button" data-assistant-toggle aria-label="Ask MIS">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 3v3" />
                            <path d="M8 3h8" />
                            <path d="M6 8h12a3 3 0 0 1 3 3v5a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-5a3 3 0 0 1 3-3Z" />
                            <path d="M8.5 13h.01M15.5 13h.01" />
                            <path d="M9 17h6" />
                        </svg>
                    </button>

                    <div class="global-user">
                        <strong>{{ $user->name }}</strong>
                        <span>
                            {{ \App\Models\User::ROLES[$user->role] ?? $user->role }}
                            @if($user->department)
                                | {{ $user->department->name }}
                            @endif
                        </span>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn-secondary" type="submit">Log out</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="app-frame">
            <aside class="app-sidebar" aria-label="CRM modules">
                <div class="sidebar-head">
                    <span class="sidebar-nav-title">Navigation</span>

                    <button class="sidebar-toggle" type="button" data-sidebar-toggle aria-label="Collapse sidebar" aria-pressed="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M15 6l-6 6 6 6" />
                        </svg>
                    </button>
                </div>

                <div class="sidebar-section-label">Modules</div>

                <nav class="module-nav" aria-label="Primary modules">
                    @foreach($moduleNav as $moduleItem)
                        @if($moduleItem['visible'])
                            <a class="module-nav-link {{ $activeModule === $moduleItem['key'] ? 'module-nav-link-active' : '' }}" href="{{ $moduleItem['route'] }}" title="{{ $moduleItem['label'] }}">
                                <span class="module-icon">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        @if($moduleItem['icon'] === 'dashboard')
                                            <path d="M4 13h6v7H4zM14 4h6v16h-6zM4 4h6v5H4z" />
                                        @elseif($moduleItem['icon'] === 'clients')
                                            <path d="M8 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21a6 6 0 0 1 12 0M17 10a3 3 0 1 0 0-6M16 14a5 5 0 0 1 5 5" />
                                        @elseif($moduleItem['icon'] === 'finance')
                                            <path d="M4 7h16M6 7V5h12v2M6 7v12h12V7M8 11h8M8 15h5" />
                                        @elseif($moduleItem['icon'] === 'operations')
                                            <path d="M5 5h14v14H5zM8 9h8M8 13h8M8 17h4" />
                                        @elseif($moduleItem['icon'] === 'reports')
                                            <path d="M5 19V5M5 19h15M9 16V9M13 16V7M17 16v-5" />
                                        @else
                                            <path d="M12 4l8 4v8l-8 4-8-4V8zM12 4v16M4 8l8 4 8-4" />
                                        @endif
                                    </svg>
                                </span>
                                <span class="module-label">
                                    <strong>{{ $moduleItem['label'] }}</strong>
                                    <small>{{ $moduleItem['meta'] }}</small>
                                </span>
                                @if($moduleItem['badge'])
                                    <span class="nav-badge" aria-label="{{ $moduleItem['badge'] }} unread operations">{{ $moduleItem['badge'] }}</span>
                                @endif
                            </a>
                        @endif
                    @endforeach
                </nav>

                <div class="sidebar-footer">
                    <span class="sidebar-footer-label">Signed in</span>
                    <strong>{{ $user->name }}</strong>
                    <small>
                        {{ \App\Models\User::ROLES[$user->role] ?? $user->role }}
                        @if($user->department)
                            | {{ $user->department->name }}
                        @endif
                    </small>
                </div>
            </aside>

            <div class="app-main">
                <header class="module-header">
                    <div class="module-header-top">
                        <div class="module-heading">
                            <span>{{ $activeModuleMeta['label'] ?? 'Workspace' }}</span>
                            <strong>{{ $activeModuleMeta['meta'] ?? 'Datamatics Eswatini' }}</strong>
                        </div>
                    </div>

                    <nav class="module-subnav" aria-label="Module navigation">
                        @foreach($subNavItems as $item)
                            @if($item['visible'])
                                <a class="subnav-link {{ $item['active'] ? 'subnav-link-active' : '' }}" href="{{ $item['route'] }}">
                                    {{ $item['label'] }}
                                    @if($item['badge'])
                                        <span class="nav-badge" aria-label="{{ $item['badge'] }} unread">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            @endif
                        @endforeach
                    </nav>
                </header>

                <main class="app-content">
                    @foreach (['success' => 'border-sky-200 bg-sky-50 text-sky-900', 'warning' => 'border-amber-200 bg-amber-50 text-amber-800'] as $key => $classes)
                        @if(session($key))
                            <div class="flash-message mb-4 rounded-md border px-4 py-3 text-sm {{ $classes }}" data-auto-dismiss>{{ session($key) }}</div>
                        @endif
                    @endforeach

                    @if($errors->any())
                        <div class="flash-message mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" data-auto-dismiss>
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>

        @include('components.assistant-drawer')
    @else
        <main class="app-container py-8">
            @foreach (['success' => 'border-sky-200 bg-sky-50 text-sky-900', 'warning' => 'border-amber-200 bg-amber-50 text-amber-800'] as $key => $classes)
                @if(session($key))
                    <div class="flash-message mb-4 rounded-md border px-4 py-3 text-sm {{ $classes }}" data-auto-dismiss>{{ session($key) }}</div>
                @endif
            @endforeach

            @if($errors->any())
                <div class="flash-message mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" data-auto-dismiss>
                    {{ $errors->first() }}
                </div>
            @endif

            @yield('content')
        </main>
    @endif
</body>
</html>
