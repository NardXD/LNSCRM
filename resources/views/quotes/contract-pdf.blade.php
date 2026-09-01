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
            <td class="text-center">{{ number_format($data['totals']['storage_fee'], 2) }}</td>
            @for ($i = 0; $i < 4; $i++)
                <td class="text-center">{{ number_format($data['all_units'][$i]['price'], 2) }}</td>
            @endfor
        </tr>
        <tr>
            <td colspan="2">Insurance Fee</td>
            <td class="text-center">{{ number_format($data['totals']['insurance_total'], 2) }}</td>
            @for ($i = 0; $i < 4; $i++)
                <td class="text-center">{{ number_format($data['all_units'][$i]['insurance_fee'], 2) }}</td>
            @endfor
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

    <table class="bordered">
        <tr class="total-row">
            <td colspan="2">Other Fees and Conditions (include, but not limited to):</td>
            <td style="width:12%">Initial</td>
        </tr>
        <tr>
            <td colspan="2">Late Payment Fee of: P{{ number_format($data['totals']['late_fee'], 2) }} (10% of storage fee) will be charged every 10 days until outstanding balance is fully paid.</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Access system will lock Storer out of facility if payment is overdue and may re-enter when Storer pays outstanding balance.</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Payment 30 days overdue, Insurance is suspended. Overdue 42 days, stored items to be auctioned and/or disposed.</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Incidental charges will apply on damages/lost Loc&amp;Stor 24/7 property.</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Return Check fee P1,000.</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2"><strong>14-Day Notice required when moving out. Failure to do so will result in additional 14 days charges.</strong></td>
            <td></td>
        </tr>
    </table>

    <p class="text-center"><strong>PLEASE READ CONDITIONS ON THIS AND THE FOLLOWING PAGES CAREFULLY,<br>AS BY SIGNING THIS AGREEMENT YOU AND LOC&amp;STOR 24/7, INC WILL BE BOUND BY THEM,</strong></p>
    <p class="text-center"><em>I am disclosing complete and correct information<br>and deviation from such disclosure, Loc&amp;Stor 24/7, Inc. will not be held liable. I agree to submit/present documents to<br>support the legality of our business (if applicable) and to re-submit updated documents during the effectivity of this agreement.</em></p>

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

    <div class="columns">
        <h3>The Agreement</h3>

        <p class="clause">1. Storer and Loc&amp;Stor 24/7, Inc. (L&amp;S) agree that the Agreement is entirely contained within this document and the Fee Schedule (on front page of this Agreement) as updated from time to time.</p>

        <p class="clause">2. Storer:</p>
        <ul class="clause-list">
            <li>2.1. has the right to store Goods in the Space allocated by L&amp;S pursuant to the terms and conditions in this Agreement;</li>
            <li>2.2. is deemed to have knowledge of the Goods in the Space;</li>
            <li>2.3. warrants that it is the owner of the Goods in the Space and/or is entitled at law to deal with the Goods in accordance with and to fulfill all aspects of this Agreement; and</li>
            <li>2.4. acknowledges and agrees the measurements stated for the Space are an approximation only, that the Space size is closely estimated for ease of illustration and comparison, and the Storer has inspected the Space and/or agrees the Space is satisfactory for storage of the type, nature, and volume of the stored Goods, including but not limited to any need for climate control, air conditioning or unique measurement requirements.</li>
        </ul>

        <p class="clause">3. L&amp;S:</p>
        <ul class="clause-list">
            <li>3.1. does not have, and will not be deemed to have knowledge of the Goods;</li>
            <li>3.2. is not a landlord or lessor; and</li>
            <li>3.3. is not a creditor, mortgagee, pledgee, bailee nor a warehouseman of the Goods and Storer acknowledges that L&amp;S does not take possession of the Goods other than as stipulated in Clause 7.</li>
        </ul>

        <p class="clause">4. Notwithstanding any other provision of this Agreement, Storer unconditionally and irrevocably agrees L&amp;S shall have the right from time to time to make amendments to the terms of the Agreement as may be appropriate for ensuring compliance with applicable law, for administrative purposes, or any other reason deemed necessary at the discretion of L&amp;S, including periodic change to any Fee(s). In the event of any change to the Agreement, Storer will be given advance Notice, and the changes will apply to this Agreement from the date specified in the Notice. However, the Storage Fee shall not be changed during the Initial Storage Period. Upon the expiration of the Initial Storage Period, any changes in the Storage Fee shall take effect by giving the Storer at least thirty (30) days prior written notice.</p>

        <h3>Cost</h3>

        <p class="clause">5. The Storer must, upon signing the Agreement, pay to L&amp;S:</p>
        <ul class="clause-list">
            <li>5.1. The Security Deposit, which L&amp;S may deduct against for damage to the Space or the Facility, unpaid fees or any other expenses or costs outstanding (any remaining Security Deposit will be made available to Storer for collection within fourteen (14) days of termination of this Agreement and fulfillment by Storer of its obligations upon termination). It is the obligation of the storer to provide details for the refund (bank account or check payment details). Unclaimed Security Deposit refund will be forfeited after 180 days from Move-out date.</li>
            <li>5.2. the Initial Storage Period Storage Fee (i.e., Initial Storage Period (months) &times; Storage Fee (Peso per month));</li>
            <li>5.3. the Administration Fee, if included in the Fee Schedule;</li>
            <li>5.4. other Deposit Fee(s), if included in the Fee Schedule;</li>
            <li>5.5. all other Fee(s), if included in the Fee Schedule;</li>
            <li>5.6. less any discount, promotion, and/or deduction provided by L&amp;S;</li>
            <li>5.7. and, other than the Deposit(s) under Clauses 5.1 and 5.4, the amounts paid under this Clause 5 are not refundable.</li>
        </ul>

        <p class="clause">6. Storer is responsible to pay:</p>
        <ul class="clause-list">
            <li>6.1. After the Initial Storage Period, the Storage Fee, being the amount indicated in this Agreement or the amount Notified to the Storer in writing by L&amp;S from time-to-time. The Storage Fee is payable in advance on the day of the month indicated in the monthly billing statement, and it is Storer's responsibility to see that payment is made directly to L&amp;S, on time, in full, throughout the period of storage. In the event that the Agreement is applicable to a partial month, e.g., termination part way through the final month, the Storage Fee shall be calculated by L&amp;S on a pro-rata basis, and paid by the Storer;</li>
            <li>6.2. a Cleaning Fee, if required, which may be payable at L&amp;S's discretion;</li>
            <li>6.3. a Late Payment Fee, as indicated on the front page of this Agreement, which becomes payable every ten (10) days a payment is late and is considered in the nature of a penalty;</li>
            <li>6.4. a Returned Check Fee, as indicated on the front page of this Agreement, which becomes payable for each returned check used for payment by Storer and is considered in the nature of a penalty;</li>
            <li>6.5. any costs incurred by L&amp;S in collecting late or unpaid Fees, or in enforcing this Agreement in any way, including but not limited to postal, telephone, debt collection, transportation, and/or the default action costs;</li>
            <li>6.6. any cost of removing and/or disposing of Goods (Disposal Fee) in the event of Default;</li>
            <li>6.7. any other fee(s) in this Agreement or fee(s) that are not included hereunder, but for which the Storer has been notified in advance and may be applicable under the terms of this Agreement;</li>
            <li>6.8. any government taxes or charges (including any goods and services tax) being levied on this Agreement, or any supplies pursuant to this Agreement;</li>
            <li>6.9. If making a payment by direct debit or credit card, Storer must forward a copy of the deposit slip or banking details to L&amp;S, clearly identifying Storer's name, the Space number and the Facility, e.g., Pasig. Failure to comply with this provision may result in L&amp;S enforcing its rights outlined in Clause 7, and the Storer authorizes L&amp;S to do so;</li>
            <li>6.10. Payment of Storage Fee and all other Fee(s) (if applicable) are due as indicated in the monthly billing statement. Such due date is referred to as "Due Date" hereunder; and</li>
            <li>6.11. Any payment received may, at the discretion of L&amp;S, be applied to the oldest delinquency first, including late charges and other fees which have become due.</li>
            <li>6.12. An invoice will be issued upon receipt of payment for storage service fees.</li>
        </ul>

        <h3>Default</h3>

        <p class="clause">7. In addition to the rights of L&amp;S under clause 23, Storer agrees that in the event of the Storage Fee, or other moneys owing under this Agreement, not being paid in full within forty-two (42) days of the Due Date, and L&amp;S has sent at least two (2) notices of late payment to the Storer, L&amp;S may, without further notice, enter the Space, by force or otherwise, retain the Deposit and/or sell or dispose of the Goods in the Space on such terms that L&amp;S may in its sole discretion determine. In such event, Storer agrees that possession of the Goods shall pass from Storer to L&amp;S from the moment the latter enters the Space. The Storer consents to and authorizes the sale or disposal of all Goods regardless of their nature or value, and the proceeds of the sale shall be applied by L&amp;S as payment for the unpaid Storage Fee, cost of sale and any such other charges, fees and penalties, which the Storer may be held liable under this Agreement, the law or in equity. The Storer hereby waives all legal rights which the Storer now has or may have to hold or retain such properties in the Space at the time L&amp;S exercises its rights under this clause 7. L&amp;S shall not be liable for identity theft or other harm resulting from the misuse of information contained in a document or electronic storage media that are part of the Storer's Goods in the Space that are sold or otherwise disposed pursuant to this clause 7. The Storer will be fully responsible for payment of all costs incurred by L&amp;S in enforcing its rights under this clause 7.</p>

        <p class="clause">8. If Storer has more than one Space, any breach or default in regards to one Space will authorize L&amp;S to enforce default action hereunder with regards to all Storer's Spaces and Storer's Goods, including, but not limited to, such default actions stipulated in Clause 7 hereunder, and/or refusing Storer further access to the Spaces and/or Facility.</p>
    </div>

    <p class="initial-line">Initial <span class="signature-line" style="width:100px;">&nbsp;</span>/<span class="signature-line" style="width:100px;">&nbsp;</span></p>

    <div class="columns">
        <h3>Access and Conditions</h3>

        <p class="clause">9. Storer:</p>
        <ul class="clause-list">
            <li>9.1. shall be solely responsible for securing of the Space and shall so secure the Space with a single device at all times when Storer is not in the Space in a manner which is acceptable to L&amp;S. The Storer shall not attach a second device and any violation hereof shall entitle L&amp;S, for the account of the Storer, to forcefully remove such second device without need of prior notice to the Storer;</li>
            <li>9.2. shall not store any Goods that are illegal or hazardous (hazardous shall include, but not be limited to any hazardous or toxic chemical, gas, liquid, substance, material or waste including vehicle tires), illegal under or contrary to applicable Philippine law or regulations, stolen, inflammable, explosive, environmentally harmful, noxious (strong smelling), food items, liquids (unless requested by the Storer, and approved in writing by L&amp;S), perishable, pest or vermin infested, mold or mildew infested, including animals, or any Goods that are a risk to life, property, or any person;</li>
            <li>9.3. shall not store items which are irreplaceable, such as currency, fine art, jewelry, furs, deeds, and items of personal sentimental value;</li>
            <li>9.4. shall use the Space solely for the purpose of storage and shall not carry on any business or other activity in the Space;</li>
            <li>9.5. shall not inhabit, reside, or live in the Space;</li>
            <li>9.6. shall not attach any fixture(s), e.g., nails, screws, tape, glue, rope, wire, and any other similar improvement, to any part of the Space or Facility and must maintain the Space by ensuring it is clean and in a state of good repair and must not damage or alter the Space or Facility without L&amp;S consent. In the event of uncleanliness of or damage to the Space or Facility, L&amp;S will be entitled to retain Storer's deposit, charge a cleaning fee, and/or charge full reimbursement from Storer to the value of the repairs required;</li>
            <li>9.7. shall not leave any items, including boxes, wrapping, rubbish or other items, in communal areas or in or around the Facility or access thereto. Any such items will be disposed of and Storer will be charged a Disposal Fee;</li>
            <li>9.8. shall not assign this Agreement without the prior written approval of L&amp;S, and any purported assignment shall be legally ineffective and shall also constitute a fundamental breach of this Agreement;</li>
            <li>9.9. shall give Notice to L&amp;S in writing of any change to the contact details of the Storer or Alternative Contact Person as identified on the front page of this Agreement. Such Notice shall be provided by the Storer to L&amp;S within forty-eight (48) hours of any change;</li>
            <li>9.10. shall not store Goods in excess of a combined value of Peso Five-Hundred-Thousand (P500,000), unless a higher amount is requested by Storer and agreed in writing by L&amp;S;</li>
            <li>9.11. shall not store goods in excess of four-hundred-fifty (450) kilograms (kgs) per square meter (equivalent of 92 pounds per square foot) of storage space;</li>
            <li>9.12. warrants that the Alternate Contact Person registered on the front page of this Agreement has full authority to deal with L&amp;S to discuss and authorize any action on any default of the Storer and/or act fully on behalf of Storer in the event Storer is not responding to communication from L&amp;S;</li>
            <li>9.13. acknowledges and agrees that the Storer has a contractual interest in the Space only and does not and will not have an interest in land and/or property;</li>
            <li>9.14. acknowledges and agrees that Goods left in the common area of the Facility are deemed abandoned and may be destroyed or disposed of within forty-eight (48) hours of being found in the Facility. Storer may be charged a Cleaning and/or Disposal Fee for this service if it is determined that the Goods were left behind by the Storer; and</li>
            <li>9.15. acknowledges and agrees that the contractual right to use the Space is personal to Storer only and, if Storer is an individual, the right to use the Space will automatically terminate upon the death of Storer. If Storer is a corporate or business entity, the right to use the Space will automatically terminate upon commencement of liquidation or similar proceedings related to the Storer. In such an event, the Goods will be held over for a further period of sixty (60) days ("Collection Period") pending collection by the person entitled in law to receive the same on behalf of Storer, as determined by L&amp;S in its discretion and on such terms as L&amp;S may determine. After expiry of such Collection Period, the Goods will be sold or disposed of on such terms as L&amp;S may determine, the Agreement terminated, and the proceeds used to settle any outstanding fees owing to L&amp;S under the procedure outlined in clause 7. For the avoidance of doubt, all Fees hereunder shall be applicable during the Collection Period;</li>
            <li>9.16. and has the right to access the Space during the access hours of the Facility as posted by L&amp;S and subject to the terms of this Agreement.</li>
        </ul>

        <p class="clause">10. L&amp;S may refuse Storer or its representatives access to the Facility and/or Space when moneys are owing by Storer to L&amp;S, whether or not a formal demand for payment of such moneys has been made, further, that L&amp;S may refuse Storer access to the Facility and/or Space if L&amp;S believes the Storer is a risk to the Facility, L&amp;S employees (e.g., verbal abuse), or anyone else using or servicing the Facility, and if such risk is deemed severe in the opinion of L&amp;S, the same shall be a ground for L&amp;S to terminate this Agreement.</p>

        <p class="clause">11. L&amp;S shall not be liable for any loss or damages suffered by the Storer resulting from an inability to access the Facility or the Space, regardless of the cause.</p>

        <p class="clause">12. L&amp;S reserves the right to relocate Storer to another Space under certain circumstances, including but not limited to, damage to the Facility or Space, maintenance work, or any other reason L&amp;S deems reasonable.</p>

        <p class="clause">13. If Storer is a corporation or other juridical entity, it will submit to L&amp;S, within ten (10) days from execution of this Agreement, a Secretary's Certificate attesting to a resolution of its Board of Directors (or equivalent governing body) authorizing the execution of this Agreement and appointing its signatory/ies hereto.</p>

        <h3>Risk</h3>

        <p class="clause">14. Goods are stored at the sole risk and responsibility of Storer who shall be responsible for any and all theft, damage to, and deterioration of the Goods, and shall bear the risk of any and all damage or loss caused by flood or fire or leakage or overflow of water, mildew, heat, spillage of material from their own Space or any other space, removal or delivery of the Goods, pest or vermin or any other reason whatsoever including acts or omissions (negligent, deliberate, or otherwise), of L&amp;S or persons under its control. In any event, and notwithstanding anything contained in this Agreement, in no circumstances shall L&amp;S be liable, in contract, tort (including negligence or breach of any statutory duty) or otherwise howsoever, and whatever the cause hereof:</p>
        <ul class="clause-list">
            <li>i. for any loss or damage to the Goods;</li>
            <li>ii. for any increased costs or expenses;</li>
            <li>iii. for any loss of profit, business, contracts, revenues, or anticipated savings; or</li>
            <li>iv. for any special, indirect and/or consequential damage of any nature whatsoever.</li>
        </ul>

        <p class="clause">15. Storer agrees to indemnify, and keep indemnified, L&amp;S from all claims for any loss of, or damage to the property of, or personal injury to, third parties resulting from or incidental to the use of the Space by Storer.</p>

        <p class="clause">16. Storer agrees that it shall be solely responsible for all conduct of any third persons that it engages, whether in the capacity of an agent or employee, to manage, in any way whatsoever, its affairs in the Space, including but not limited to the movement, placing, and removal of the deposited Goods. Any damage to the deposited Goods shall be for the sole account of the Storer. Any damage whatsoever to the Space, the property, and any other property belonging to L&amp;S shall be borne by the Storer, which shall immediately, without need for demand, indemnify L&amp;S for the loss or damage. Should it fail to do so within fifteen (15) days, Storer shall be considered in default under Clause 7.</p>
    </div>

    <p class="initial-line">Initial <span class="signature-line" style="width:100px;">&nbsp;</span>/<span class="signature-line" style="width:100px;">&nbsp;</span></p>

    <div class="page-break"></div>

    <div class="columns">
        <p class="clause">17. Storer acknowledges and agrees to comply with all relevant laws, decrees, orders, rules and regulations of the Government of the Republic of the Philippines, or any political subdivision or agency thereof, and any entity that exercises executive, legislative, judicial, regulatory or administrative functions of or pertaining to said government, as are or may be applicable to the use of the Space. This includes laws relating to the material which is stored, and the manner in which it is stored. The liability for any and all breach of such laws rests absolutely with Storer, and includes any and all costs resulting from such a breach.</p>

        <p class="clause">18. In addition to any other remedies as may become available to it, if L&amp;S has reason to believe that Storer is not complying with all relevant laws, L&amp;S may take any action L&amp;S believes to be necessary, including but not limited to the action outlined in clauses 21 and 23, contacting, cooperating with, and/or submitting Goods to the relevant authorities, and/or immediately disposing of or removing the Goods at Storer's expense. Storer agrees that L&amp;S may take such action at any time even though L&amp;S could have acted earlier.</p>

        <p class="clause">19. The Storer hereby authorizes L&amp;S to dispose of the Storer's Goods in the event that the Goods are damaged due to fire, flood, or other event that has rendered the Goods, in the sole opinion of L&amp;S, severely damaged, of no commercial value, or dangerous to the Facility, any persons, or other Storers and/or their Goods.</p>

        <h3>Inspection</h3>

        <p class="clause">20. Subject to clause 21, Storer consents to inspection and entry of the Space by L&amp;S subject to fourteen (14) days written Notice from L&amp;S to the Storer.</p>

        <p class="clause">21. In the event of an emergency, that is where property, the environment, or animal or human life is, in the opinion of L&amp;S, threatened, L&amp;S may enter the Space using all necessary force without the written consent of the Storer and take any actions that L&amp;S may deem necessary to abate the threat, but L&amp;S shall notify Storer as soon as practicable thereafter, and Storer irrevocably and unconditionally consents to such entry under this clause 21.</p>

        <h3>Notice</h3>

        <p class="clause">22. Written notices will usually be given by email and/or SMS message, or left at, or posted to the latest address of Storer and/or Alternate Contact Person provided to L&amp;S. In relation to the giving of Notices to L&amp;S, Notices must actually be received in written form to be valid. In the event of not being able to contact Storer, Notice is deemed to have been given to Storer by L&amp;S if L&amp;S serves that Notice on the Alternate Contact Person as identified on the front page of this Agreement, or has sent Notices to the last notified address of Storer or Alternate Contact Person.</p>

        <h3>Termination</h3>

        <p class="clause">23. Once the Initial Storage Period has ended, the Storage Period is automatically renewed until terminated by either party upon giving the other party fourteen (14) days Termination Notice. In the event of illegal or environmentally harmful activities on the part of Storer, L&amp;S may terminate the Agreement without Notice. L&amp;S is entitled to retain a portion of the deposit, if less than the requisite period of Notice is given by Storer. At the end of the Storage Period, Storer must remove all Goods in the Space and leave the Space in a clean condition and in a good state of repair to the satisfaction of L&amp;S by the date specified. Storer must pay any outstanding moneys and any expenses on default owed to L&amp;S up to the date of termination, or clause 7 may apply. Any calculation of the outstanding fees will be by L&amp;S and such calculation will be final.</p>

        <p class="clause">24. If L&amp;S enters or observes the Space for any reason and there are no Goods stored therein and the Storer is in default (e.g., late payment), L&amp;S may terminate the Agreement without giving prior Notice, but L&amp;S will send Notice to Storer in writing within fourteen (14) days.</p>

        <p class="clause">25. The Parties' liability for outstanding moneys, property damage, personal injury, environmental damage and legal responsibility under this Agreement continues to run beyond the termination of this Agreement and the Storage Period unless otherwise stipulated in writing.</p>

        <p class="clause">26. If Goods are left in the Space at the end of the Storage Period and are not collected within Forty-Eight (48) hours, the Storage Period shall be deemed extended and all conditions hereunder shall be applicable.</p>

        <h3>Data Privacy</h3>

        <p class="clause">27. Subject to clause 7 hereof, all personal data acquired by L&amp;S from Storer during the registration process, shall only be used for the purposes of this Agreement and shall not be further processed or disclosed without the consent of Storer as stipulated in the Data Privacy Act of 2012.</p>

        <h3>Limitation of Liability</h3>

        <p class="clause">28. Storer agrees that the terms of this document constitute the whole contract with L&amp;S, and that in entering this contract, Storer relies upon no representations other than those contained in this Agreement and acknowledges that it has raised all queries relevant to its decision to enter this Agreement with L&amp;S and that L&amp;S has, prior to Storer entering into this Agreement, answered all such queries to the satisfaction of Storer.</p>

        <p class="clause">29. L&amp;S shall not be liable in the event that it is unable to uphold or perform any aspect of this Agreement, including the ability to access the Facility, or any loss or damage to Goods, if its ability to carry out its obligations hereunder is hampered due to a condition beyond its reasonable control, including force majeure, natural calamities and disasters, flood, storms, earthquake, wars, riots, insurrections, terrorist acts, and/or any other cause beyond the reasonable control of L&amp;S.</p>

        <p class="clause">30. Any damages, whether for physical and/or economic loss or damage, which L&amp;S is liable to pay to Storer pursuant to this Agreement or performance of this Agreement (including damages for negligence or damages for consequential loss) are limited in all cases to a maximum of Peso Fifty-Thousand (P50,000).</p>

        <p class="clause">31. The Storer acknowledges that it has raised all queries relevant to its decision to enter this Agreement with L&amp;S and that L&amp;S has, prior to the Storer entering into this Agreement, answered all such queries to the full satisfaction of the Storer.</p>

        <h3>Miscellaneous</h3>

        <p class="clause">32. This Agreement represents the total agreement of L&amp;S and the Storer, and supersedes all previous agreements. No oral statements made by L&amp;S, its employees, officers, or representatives shall form part of this Agreement. Any subsequent modification of the terms of this Agreement, whether for its novation or reformation, shall require the written agreement of L&amp;S.</p>

        <p class="clause">33. No failure or delay of L&amp;S to exercise its rights under this Agreement shall be construed as a waiver of those rights.</p>

        <p class="clause">34. Any dispute, controversy or claim arising out of or relating to this Agreement, or the breach, termination or invalidity thereof, shall be settled by arbitration in accordance with the Philippine Dispute Resolution Center Inc. (PDRCI) Arbitration Rules then in force. The dispute shall be settled by a sole arbitrator, who shall be appointed in accordance with the PDRCI Arbitration Rules. The place of arbitration shall be in {{ $data['banking']['city'] ?: 'Pasig' }} City. The language to be used in the arbitral proceedings shall be English.</p>

        <p class="clause">35. If any provision under this Agreement or any document executed in connection herewith is declared invalid, illegal or unenforceable in any respect by a court of competent jurisdiction, the validity, legality or enforceability of the remaining provisions of such documents shall not in any way be affected or impaired.</p>

        <p class="clause">36. This Agreement shall be governed by and construed in accordance with the laws of the Philippines.</p>
    </div>

    <p class="initial-line">Initial <span class="signature-line" style="width:100px;">&nbsp;</span>/<span class="signature-line" style="width:100px;">&nbsp;</span></p>
</body>
</html>
