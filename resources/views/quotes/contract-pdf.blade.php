<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page {
        margin: 95px 20px 55px 20px;
    }
    body { font-family: Arial, sans-serif; font-size: 9.5px; color: #000; }
    h1 { font-size: 14px; margin: 0; text-align: center; }
    h3 { font-size: 9.5px; margin: 8px 0 4px; font-weight: bold; }
    p { margin: 3px 0; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
    th, td { padding: 2px 5px; text-align: left; vertical-align: top; }
    .bordered th, .bordered td { border: 0.5pt solid #000; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .no-border td { border: none; padding: 1px 5px; }
    .total-row td { font-weight: bold; }
    .signature-line { border-top: 1px solid #000; display: inline-block; margin: 0 6px; }
    .signature-img { max-height: 45px; }
    .indent { margin-left: 25px; }
    .clause { margin: 0 0 8px; text-align: justify; }
    .clause-list { margin: 0 0 6px 14px; padding: 0; list-style: none; }
    .clause-list li { margin-bottom: 4px; text-align: justify; }
    .page-break { page-break-after: always; }
    .columns { column-count: 2; column-gap: 18px; }
    .initial-line { text-align: right; margin-top: 20px; }

    #page-header {
        position: fixed;
        top: -80px;
        left: 0px;
        right: 0px;
        height: 70px;
    }
</style>
</head>
<body>
    <div id="page-header">
        <table class="no-border">
            <tr>
                <td style="width:70px;">@php $logoPath = resource_path('images/loc-stor-logo.jpg'); @endphp
                    @if (is_file($logoPath))
                        <img src="data:image/jpeg;base64,{{ base64_encode(file_get_contents($logoPath)) }}" style="height:55px;">
                    @endif
                </td>
                <td class="text-center"><h1>THE SELF STORAGE AGREEMENT</h1></td>
                <td style="width:70px;"></td>
            </tr>
        </table>
    </div>

    @include('quotes._contract-body-top', ['data' => $data])

    <table class="no-border" style="margin-top:10px;">
        <tr>
            <td style="width:16%"><strong>Storer Signature</strong></td>
            <td style="width:44%">
                @if ($data['signature_base64'])
                    <img class="signature-img" src="data:image/png;base64,{{ $data['signature_base64'] }}">
                @endif
                <span class="signature-line" style="width:100%;">&nbsp;</span>
            </td>
            <td style="width:8%; white-space:nowrap;">Date :</td>
            <td><span class="signature-line" style="width:100%;">&nbsp;</span></td>
        </tr>
        <tr>
            <td><strong>Loc&amp;Stor 24/7, Inc:</strong></td>
            <td><span class="signature-line" style="width:100%;">&nbsp;</span></td>
            <td style="white-space:nowrap;">Date :</td>
            <td>{{ $data['generated_at'] }} <span class="signature-line" style="width:60%;">&nbsp;</span></td>
        </tr>
    </table>

    <div class="page-break"></div>

    @include('quotes._contract-body-clauses', ['data' => $data])
</body>
</html>
