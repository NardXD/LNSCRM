<?php

return [

    'vat_rate' => 0.12,

    'sites' => [
        'L001' => [
            'name' => 'Pasig',
            'code' => env('STOREGANISE_SITE_L001', 'L001'),
        ],
        'L002' => [
            'name' => 'Urban Makati',
            'code' => env('STOREGANISE_SITE_L002', 'L002'),
        ],
        'L003' => [
            'name' => 'JP Rizal',
            'code' => env('STOREGANISE_SITE_L003', 'L003'),
        ],
        'L005' => [
            'name' => 'North Edsa',
            'code' => env('STOREGANISE_SITE_L005', 'L005'),
        ],
        'L006' => [
            'name' => 'Sucat',
            'code' => env('STOREGANISE_SITE_L006', 'L006'),
        ],
        'L007' => [
            'name' => 'Newport City',
            'code' => env('STOREGANISE_SITE_L007', 'L007'),
        ],
    ],

    'banking' => [
        'L001' => [
            'facility' => 'Pasig',
            'city' => 'Pasig',
            'address' => '54 Eulogio Rodriguez Jr, Bagong Ilog, Pasig City, 1600. Tel +63 2 570 2561, Cell +63 (0) 916 567 3004',
            'bank_name' => 'BDO',
            'branch' => 'Pasig E. Rodriguez Jr. Ave.',
            'account_type' => 'Peso Savings Account',
            'account_number' => '0080 8001 1171',
            'account_name' => 'LOC&STOR 24/7, INC.',
            'viber' => '+639165673004',
        ],
        'L002' => [
            'facility' => 'Urban Makati',
            'city' => 'Makati',
            'address' => '7192A Urban Avenue, Pio del Pilar, Makati City, 1230. Tel +63 2 810 9556, Cell +63 (0) 917 706 9362 / +63 (0) 919 912 6800',
            'bank_name' => 'BDO',
            'branch' => 'Pasig E. Rodriguez Jr. Ave.',
            'account_type' => 'Savings',
            'account_number' => '0080 8003 6239',
            'account_name' => 'LOC&STOR 24/7, INC.',
            'viber' => '+639177069362',
        ],
        'L003' => [
            'facility' => 'JP Rizal Makati',
            'city' => 'Makati',
            'address' => 'Space Solutions Bldg., 155 Dr. JP Rizal Ave., corner del Pan, Tejeros, Makati City, 1204. Cell +63 (0) 917 709 5390',
            'bank_name' => 'BDO',
            'branch' => 'Pasig E. Rodriguez Jr. Ave.',
            'account_type' => 'Current',
            'account_number' => '0080 8800 4024',
            'account_name' => 'LOC&STOR 24/7, INC.',
            'viber' => '+639177095390',
        ],
        'L005' => [
            'facility' => 'QC Edsa',
            'city' => 'Quezon',
            'address' => '1238 Epifanio de los Santos Avenue, Brgy, Apolonio Samson, Balintawak, Quezon City',
            'bank_name' => 'BDO',
            'branch' => 'Pasig E. Rodriguez Jr. Ave.',
            'account_type' => 'Checking Account',
            'account_number' => '0080 8800 5802',
            'account_name' => 'LOC&STOR 24/7, INC.',
            'viber' => '+639177034159',
        ],
        'SLEX' => [
            'facility' => 'Taguig',
            'city' => 'Taguig',
            'address' => 'AFP-RSBS Industrial Park (Block 1) Km. 12 East Service Road, Western Bicutan, Taguig City',
            'bank_name' => 'BDO',
            'branch' => 'Pasig E. Rodriguez Jr. Ave.',
            'account_type' => 'Checking Account',
            'account_number' => '0080 8800 4733',
            'account_name' => 'LOC&STOR 24/7, INC.',
            'viber' => '+639177095836',
        ],
        'L006' => [
            'facility' => 'Sucat',
            'city' => 'Muntinlupa',
            'address' => 'RLX Warehouse, Meralco Road, Sucat, Muntinlupa City',
            'bank_name' => 'BDO',
            'branch' => 'Pasig E. Rodriguez Jr. Ave.',
            'account_type' => 'Current Account',
            'account_number' => '0080 8800 4733',
            'account_name' => 'LOC&STOR 24/7, INC.',
            'viber' => '+639177118854',
        ],
        'L007' => [
            'facility' => 'Newport City',
            'city' => '',
            'address' => '',
            'bank_name' => 'BDO',
            'branch' => 'Pasig E. Rodriguez Jr. Ave.',
            'account_type' => 'Current Account',
            'account_number' => '',
            'account_name' => 'LOC&STOR 24/7, INC.',
            'viber' => '',
        ],
    ],

    'discounts' => [
        'L001' => [
            '0' => '1 - 2 Months (0%)',
            '0.025' => '3 - 5 Months (2.5%)',
            '0.05' => '6 - 8 Months (5%)',
            '0.075' => '9 -18 Months (7.5%)',
            '0.06' => 'Renewal (6%)',
        ],
        'L002' => [
            '0' => '1 - 2 Months (0%)',
            '0.05' => '3 Months (5%)',
            '0.08' => '6 Months (8%)',
            '0.06' => 'Renewal (6%)',
        ],
        'default' => [
            '0.05' => '3 Months (5%)',
            '0.08' => '6 Months (8%)',
            '0.06' => 'Renewal (6%)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storeganise User custom field codes
    |--------------------------------------------------------------------------
    |
    | These codes must match User custom fields created in Storeganise:
    |   Admin → Settings → Custom fields → User
    |
    */

    'user_custom_fields' => [
        'mrms' => 'lns_mrms',
        'city' => 'lns_city',
        'postal' => 'lns_postal',
        'pin_code' => 'lns_pinCode',
        'tin' => 'lns_tin',
        'dob' => 'lns_dob',
        'hear_about' => 'lns_hearAbout',
        'customer_type' => 'lns_customerType',
        'residential_type' => 'lns_residentialType',
        'residential_reason' => 'lns_residentialReason',
        'commercial_type' => 'lns_commercialType',
        'commercial_reason' => 'lns_commercialReason',
        'site_code' => 'lns_siteCode',
        'alt_title' => 'lns_altTitle',
        'alt_first_name' => 'lns_altFirstName',
        'alt_last_name' => 'lns_altLastName',
        'alt_address' => 'lns_altAddress',
        'alt_city' => 'lns_altCity',
        'alt_postal' => 'lns_altPostal',
        'alt_phone' => 'lns_altPhone',
        'alt_email' => 'lns_altEmail',
    ],

];
