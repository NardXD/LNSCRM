<style>
    /* Styles for the structured storage-quote agreement body (fee schedule + clauses),
       shared by contract/pdf.blade.php and contract/sign.blade.php. Every selector is
       scoped under .storage-quote-agreement so it never collides with those pages' own
       rules (e.g. their own unrelated .signature-line used for e-sign signature cards). */
    .storage-quote-agreement { font-family: Arial, sans-serif; font-size: 9.5px; color: #000; }
    .storage-quote-agreement h3 { font-size: 9.5px; margin: 8px 0 4px; font-weight: bold; }
    .storage-quote-agreement p { margin: 3px 0; }
    .storage-quote-agreement table { width: 100%; border-collapse: collapse; margin-bottom: 5px; }
    .storage-quote-agreement th,
    .storage-quote-agreement td { padding: 2px 5px; text-align: left; vertical-align: top; }
    .storage-quote-agreement .bordered th,
    .storage-quote-agreement .bordered td { border: 0.5pt solid #000; }
    .storage-quote-agreement .text-right { text-align: right; }
    .storage-quote-agreement .text-center { text-align: center; }
    .storage-quote-agreement .no-border td { border: none; padding: 1px 5px; }
    .storage-quote-agreement .total-row td { font-weight: bold; }
    .storage-quote-agreement .signature-line { border-top: 1px solid #000; display: inline-block; margin: 0 6px; }
    .storage-quote-agreement .signature-img { max-height: 45px; }
    .storage-quote-agreement .indent { margin-left: 25px; }
    .storage-quote-agreement .clause { margin: 0 0 8px; text-align: justify; }
    .storage-quote-agreement .clause-list { margin: 0 0 6px 14px; padding: 0; list-style: none; }
    .storage-quote-agreement .clause-list li { margin-bottom: 4px; text-align: justify; }
    .storage-quote-agreement .page-break { page-break-after: always; }
    .storage-quote-agreement .columns { column-count: 2; column-gap: 18px; }
    .storage-quote-agreement .initial-line { text-align: right; margin-top: 20px; }
</style>
