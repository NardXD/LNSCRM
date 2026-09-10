@if(($contract->content_type ?? 'html') === 'storage_quote' && isset($data))
    <div class="storage-quote-agreement">
        @include('quotes._contract-body-top', ['data' => $data])
        @include('quotes._contract-body-signature', ['data' => $data])
        <div class="page-break"></div>
        @include('quotes._contract-body-clauses', ['data' => $data])
    </div>
@elseif(filled(trim(strip_tags($contract->content ?? ''))))
    {!! $contract->content !!}
@else
    <p class="contract-empty-content">No contract text has been provided.</p>
@endif
