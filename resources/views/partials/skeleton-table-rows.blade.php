@php
    $rows = (int) ($rows ?? 8);
    $cols = (int) ($cols ?? 6);
    $widths = ['w-55', 'w-80', 'w-40', 'w-70', 'w-50', 'w-60'];
@endphp
@for ($i = 0; $i < $rows; $i++)
    <tr class="page-skel-table-row" aria-hidden="true">
        @for ($c = 0; $c < $cols; $c++)
            <td><span class="page-skel-line {{ $widths[$c % count($widths)] }}"></span></td>
        @endfor
    </tr>
@endfor
