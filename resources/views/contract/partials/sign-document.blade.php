@php
    $company = $contract->company;
    $client = $contract->client;
@endphp

<div class="contract-document">
    <div class="contract-document-header">
        <div class="contract-document-header-main">
            <div class="contract-document-label">Contract</div>
            <div class="contract-document-ref">{{ $contract->contract_number }}</div>
            <div class="contract-document-meta">
                @if($contract->effective_date)
                    <span><strong>Effective:</strong> {{ $contract->effective_date->format('M d, Y') }}</span>
                @endif
                @if($contract->expiry_date)
                    <span><strong>Expires:</strong> {{ $contract->expiry_date->format('M d, Y') }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="contract-parties">
        <div class="contract-party">
            <div class="contract-section-title">Service Provider</div>
            <div class="contract-party-info">
                <strong>{{ $company->name ?? 'Company' }}</strong>
                @if($company?->email)<br>{{ $company->email }}@endif
                @if($company?->phone)<br>{{ $company->phone }}@endif
                @if($company?->address)<br>{{ $company->address }}@endif
            </div>
        </div>
        <div class="contract-party">
            <div class="contract-section-title">Client</div>
            <div class="contract-party-info">
                <strong>{{ $client->name ?? 'Client' }}</strong>
                @if($client?->contact_person)<br>Attn: {{ $client->contact_person }}@endif
                @if($client?->email)<br>{{ $client->email }}@endif
                @if($client?->phone)<br>{{ $client->phone }}@endif
                @if($client?->address)<br>{{ $client->address }}@endif
            </div>
        </div>
    </div>

    <div class="contract-title-block">
        <h2>{{ $contract->title }}</h2>
    </div>

    <div class="contract-agreement">
        <div class="contract-section-title">Terms of Agreement</div>
        <div class="contract-agreement-body">
            @if(filled(trim(strip_tags($contract->content ?? ''))))
                {!! $contract->content !!}
            @else
                <p class="contract-empty-content">No contract text has been provided.</p>
            @endif
        </div>
    </div>
</div>
