@php
    $faviconUrl = null;
    $company = $currentCompany ?? null;
    if (! $company && Auth::guard('client')->check()) {
        $company = Auth::guard('client')->user()?->client?->company;
    }
    if (! $company) {
        $company = request('company') ?? (app()->bound('company') ? app('company') : null);
    }
    if ($company && $company->logo) {
        $faviconUrl = asset('storage/' . $company->logo);
    } else {
        $faviconUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }
@endphp
<link rel="icon" href="{{ $faviconUrl }}">
