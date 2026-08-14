<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Single company
    |--------------------------------------------------------------------------
    |
    | This app runs as one company on the main domain (no subdomains).
    | Set COMPANY_ID to pin a specific company. When empty, the first
    | company in the database (lowest id) is used.
    |
    */

    'id' => env('COMPANY_ID') !== null && env('COMPANY_ID') !== ''
        ? (int) env('COMPANY_ID')
        : null,

];
