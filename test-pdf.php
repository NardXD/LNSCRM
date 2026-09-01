<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Quote\QuoteDocumentData;
use Barryvdh\DomPDF\Facade\Pdf;

$data = QuoteDocumentData::fromArray([
    'lo_code' => 'L001',
    'fname' => 'Test',
    'lname' => 'User',
    'email' => 'test@example.com',
    'initial_period_hdn' => 3,
    'start_date' => '2026-09-01',
    'unit1_print_hdn' => 'A101',
    'unit1_price_hdn' => 5000,
    'total_storage_fee_final' => 5000,
    'total_final_hdn' => 10000,
    'reserved' => 'No',
    'unit_size' => '10 sqm',
]);

try {
    $pdf = Pdf::loadView('quotes.contract-pdf', ['data' => $data])->setPaper('a4');
    $pdf->render();
    echo 'PDF OK, bytes: '.strlen($pdf->output()).PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage().PHP_EOL;
    echo $e->getTraceAsString().PHP_EOL;
}
