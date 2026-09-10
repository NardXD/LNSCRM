    <table class="no-border" style="margin-top:10px;">
        <tr>
            <td style="width:16%"><strong>Storer Signature</strong></td>
            <td style="width:44%">
                @if ($data['signature_base64'] ?? null)
                    <img class="signature-img" src="data:image/png;base64,{{ $data['signature_base64'] }}">
                @endif
                <span class="signature-line" style="width:100%;">&nbsp;</span>
            </td>
            <td style="width:8%; white-space:nowrap;">Date :</td>
            <td>{{ $data['signature_signed_at'] ?? '' }} <span class="signature-line" style="width:100%;">&nbsp;</span></td>
        </tr>
        <tr>
            <td><strong>Loc&amp;Stor 24/7, Inc:</strong></td>
            <td><span class="signature-line" style="width:100%;">&nbsp;</span></td>
            <td style="white-space:nowrap;">Date :</td>
            <td>{{ $data['generated_at'] }} <span class="signature-line" style="width:60%;">&nbsp;</span></td>
        </tr>
    </table>
