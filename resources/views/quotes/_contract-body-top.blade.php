    <p><strong>Storer Details:</strong></p>
    <table class="no-border indent">
        <tr>
            <td style="width:20%"><strong>Business Name (If business):</strong></td>
            <td style="width:46%" colspan="3">{{ $data['tenant']['company'] }}</td>
            <td style="width:12%"><strong>TIN:</strong></td>
            <td>{{ $data['tenant']['tin'] }}</td>
        </tr>
        <tr>
            <td><strong>Ms/Mrs/Mr:</strong></td>
            <td style="width:18%">{{ $data['tenant']['mr_mrs'] }}</td>
            <td style="width:8%"><strong>First:</strong></td>
            <td style="width:20%">{{ $data['tenant']['first_name'] }}</td>
            <td><strong>Surname:</strong></td>
            <td>{{ $data['tenant']['last_name'] }}</td>
        </tr>
        <tr>
            <td><strong>Address:</strong></td>
            <td colspan="3">{{ $data['tenant']['address'] }}</td>
            <td><strong>Postal:</strong></td>
            <td>{{ $data['tenant']['postal'] }}</td>
        </tr>
        <tr>
            <td><strong>Home Tel:</strong></td>
            <td></td>
            <td><strong>Mobile Tel:</strong></td>
            <td>{{ $data['tenant']['phone'] }}</td>
            <td><strong>Work Tel:</strong></td>
            <td></td>
        </tr>
        <tr>
            <td><strong>Email:</strong></td>
            <td colspan="3">{{ $data['tenant']['email'] }}</td>
            <td><strong>Fax Num:</strong></td>
            <td></td>
        </tr>
    </table>
    <p class="text-center indent">I (Storer) consent to receiving all Notices on the email provided above. By consenting, I agree that no correspondence will be sent<br>by traditional mail. It is my obligation to update my email address when necessary.<br>Yes, I agree to advise you immediately on changes on my address or contact details and those of my alternate contact.</p>

    <p><strong>Alternative Contact Person:</strong></p>
    <table class="no-border indent">
        <tr>
            <td style="width:20%"><strong>Ms/Mrs/Mr:</strong></td>
            <td style="width:18%">{{ $data['alt_contact']['mr_mrs'] }}</td>
            <td style="width:8%"><strong>First:</strong></td>
            <td style="width:20%">{{ $data['alt_contact']['first_name'] }}</td>
            <td style="width:12%"><strong>Surname:</strong></td>
            <td>{{ $data['alt_contact']['last_name'] }}</td>
        </tr>
        <tr>
            <td><strong>Address:</strong></td>
            <td colspan="3">{{ $data['alt_contact']['address'] }}</td>
            <td><strong>Postal:</strong></td>
            <td>{{ $data['alt_contact']['postal'] }}</td>
        </tr>
        <tr>
            <td><strong>Home Tel:</strong></td>
            <td></td>
            <td><strong>Mobile Tel:</strong></td>
            <td colspan="3">{{ $data['alt_contact']['phone'] }}</td>
        </tr>
        <tr>
            <td><strong>Email:</strong></td>
            <td colspan="5">{{ $data['alt_contact']['email'] }}</td>
        </tr>
    </table>

    <p><strong>Facility :</strong> {{ $data['banking']['address'] ?: $data['facility_label'] }}</p>
    <p><strong>Term :</strong> Initial Storage Period : From <strong>{{ $data['terms']['start_date_display'] }}</strong> To <strong>{{ $data['terms']['end_date_display'] }}</strong>.<br>
    The Storage Period shall automatically extend on a month-to-month basis until a fourteen (14)-day notice of termination is given by either party.</p>

    <table class="bordered">
        <tr>
            <td rowspan="3" style="width:15%"><strong>FEE SCHEDULE (Peso)</strong></td>
            <td>Insurance Coverage</td>
            @for ($i = 0; $i < 4; $i++)
                <td class="text-center">{{ $data['all_units'][$i]['insurance_coverage'] }}</td>
            @endfor
            <td class="text-center">Value</td>
        </tr>
        <tr>
            <td>Unit Number</td>
            @for ($i = 0; $i < 4; $i++)
                <td class="text-center">{{ $data['all_units'][$i]['code'] }}</td>
            @endfor
            <td class="text-center">Unit</td>
        </tr>
        <tr>
            <td>Size (SQM)</td>
            @for ($i = 0; $i < 4; $i++)
                <td class="text-center">{{ $data['all_units'][$i]['sqm'] }}</td>
            @endfor
            <td class="text-center">SQM</td>
        </tr>
        <tr>
            <td colspan="2">Initial Storage Period</td>
            <td class="text-center">{{ $data['terms']['initial_period'] }}</td>
            <td colspan="4">Months</td>
        </tr>
        <tr>
            <td colspan="2">Storage Service Fee (PHP/Month)</td>
            @for ($i = 0; $i < 4; $i++)
                <td class="text-center">{{ number_format($data['all_units'][$i]['price'], 2) }}</td>
            @endfor
            <td class="text-center">{{ number_format($data['totals']['storage_fee'], 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Insurance Fee</td>
            @for ($i = 0; $i < 4; $i++)
                <td class="text-center">{{ number_format($data['all_units'][$i]['insurance_fee'], 2) }}</td>
            @endfor
            <td class="text-center">{{ number_format($data['totals']['insurance_total'], 2) }}</td>
        </tr>
    </table>

    <table class="bordered">
        <tr>
            <td colspan="3"><strong>Amount Payable for Initial Storage Period</strong></td>
        </tr>
        <tr>
            <td style="width:40%">Security Deposit (non-VAT)</td>
            <td style="width:15%" class="text-center">{{ number_format($data['totals']['deposit_notax'], 2) }}</td>
            <td>1 month standard Storage Fee net of VAT</td>
        </tr>
        <tr>
            <td>Total Insurance Fee</td>
            <td class="text-center">{{ number_format($data['totals']['insurance_computation'], 2) }}</td>
            <td>Initial Storage Period</td>
        </tr>
        <tr>
            <td>Total Storage Service Fee</td>
            <td class="text-center">{{ number_format($data['totals']['final_storage_fee'], 2) }}</td>
            <td>Initial Storage Period</td>
        </tr>
        <tr>
            <td>Promo/Discount</td>
            <td class="text-center">({{ number_format($data['totals']['reduction'], 2) }})</td>
            <td>From Prescribed Discount Plans Only</td>
        </tr>
        <tr>
            <td>Admin Fee</td>
            <td class="text-center">{{ number_format($data['totals']['admin_fee'], 2) }}</td>
            <td>Documentation and Processing Fee</td>
        </tr>
        @foreach ($data['terms']['adjustments'] as $index => $amount)
            <tr>
                <td>Other Adjustments {{ $index + 1 }}</td>
                <td class="text-center">{{ $amount != 0 ? number_format($amount, 2) : '' }}</td>
                <td>{{ $data['terms']['adjustment_remarks'][$index] }}</td>
            </tr>
        @endforeach
        <tr>
            <td>Other Adjust (non-VAT)</td>
            <td class="text-center">{{ $data['terms']['adjustments_nonvat'] != 0 ? number_format($data['terms']['adjustments_nonvat'], 2) : '' }}</td>
            <td>{{ $data['terms']['adjustments_nonvat_remarks'] }}</td>
        </tr>
        <tr>
            <td>Withholding tax</td>
            <td class="text-center">{{ number_format($data['totals']['withholding_tax_amount'], 2) }}</td>
            <td>If applicable</td>
        </tr>
        <tr class="total-row">
            <td>Total Amount Payable (VAT inclusive)</td>
            <td class="text-center">{{ number_format($data['totals']['total_due'], 2) }}</td>
            <td>Due at move-in day</td>
        </tr>
        <tr>
            <td>VAT amount</td>
            <td class="text-center">{{ number_format($data['totals']['vat_amount'], 2) }}</td>
            <td>Included in the total amount payable</td>
        </tr>
    </table>

    @php $initialStamp = ($data['signature_base64'] ?? null) ? '<img src="data:image/png;base64,'.$data['signature_base64'].'" style="max-height:14px;">' : ''; @endphp
    <table class="bordered">
        <tr class="total-row">
            <td colspan="2">Other Fees and Conditions (include, but not limited to):</td>
            <td style="width:12%">Initial</td>
        </tr>
        <tr>
            <td colspan="2">Late Payment Fee of: P{{ number_format($data['totals']['late_fee'], 2) }} (10% of storage fee) will be charged every 10 days until outstanding balance is fully paid.</td>
            <td>{!! $initialStamp !!}</td>
        </tr>
        <tr>
            <td colspan="2">Access system will lock Storer out of facility if payment is overdue and may re-enter when Storer pays outstanding balance.</td>
            <td>{!! $initialStamp !!}</td>
        </tr>
        <tr>
            <td colspan="2">Payment 30 days overdue, Insurance is suspended. Overdue 42 days, stored items to be auctioned and/or disposed.</td>
            <td>{!! $initialStamp !!}</td>
        </tr>
        <tr>
            <td colspan="2">Incidental charges will apply on damages/lost Loc&amp;Stor 24/7 property.</td>
            <td>{!! $initialStamp !!}</td>
        </tr>
        <tr>
            <td colspan="2">Return Check fee P1,000.</td>
            <td>{!! $initialStamp !!}</td>
        </tr>
        <tr>
            <td colspan="2"><strong>14-Day Notice required when moving out. Failure to do so will result in additional 14 days charges.</strong></td>
            <td>{!! $initialStamp !!}</td>
        </tr>
    </table>

    <p class="text-center"><strong>PLEASE READ CONDITIONS ON THIS AND THE FOLLOWING PAGES CAREFULLY,<br>AS BY SIGNING THIS AGREEMENT YOU AND LOC&amp;STOR 24/7, INC WILL BE BOUND BY THEM,</strong></p>
    <p class="text-center"><em>I am disclosing complete and correct information<br>and deviation from such disclosure, Loc&amp;Stor 24/7, Inc. will not be held liable. I agree to submit/present documents to<br>support the legality of our business (if applicable) and to re-submit updated documents during the effectivity of this agreement.</em></p>
