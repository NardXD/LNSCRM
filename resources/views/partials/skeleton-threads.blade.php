@php $count = (int) ($count ?? 8); @endphp
<div class="page-skel-list" aria-hidden="true">
    @for ($i = 0; $i < $count; $i++)
        <div class="page-skel-thread">
            <div class="page-skel-avatar"></div>
            <div class="page-skel-lines">
                <span class="page-skel-line w-55"></span>
                <span class="page-skel-line w-80"></span>
            </div>
        </div>
    @endfor
</div>
