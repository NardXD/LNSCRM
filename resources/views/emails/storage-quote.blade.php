<p>Hi {{ trim($data['tenant']['first_name'].' '.$data['tenant']['last_name']) ?: 'there' }},</p>

<p>Thank you for your interest in {{ $data['facility_label'] }}. Please find your storage quote attached as a PDF.</p>

<table style="width:60%">
    <tr>
        <td>Total Storage Fee (Monthly)</td>
        <td><strong>PHP {{ number_format($data['totals']['storage_fee'], 2) }}</strong></td>
    </tr>
    <tr>
        <td>Total Amount Payable (VAT inclusive)</td>
        <td><strong>PHP {{ number_format($data['totals']['total_due'], 2) }}</strong></td>
    </tr>
</table>

<p>This is an estimate only and does not constitute a binding contract. Our sales staff will follow up to confirm the final terms.</p>

<p>Thank you,<br>{{ $data['facility_label'] }}</p>
