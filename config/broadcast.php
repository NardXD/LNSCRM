<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum recipients per broadcast campaign
    |--------------------------------------------------------------------------
    */
    'max_recipients' => (int) env('BROADCAST_MAX_RECIPIENTS', 10000),

    /*
    |--------------------------------------------------------------------------
    | Outbound send batch size (messages per DB batch)
    |--------------------------------------------------------------------------
    */
    'batch_size' => (int) env('BROADCAST_BATCH_SIZE', 25),

    /*
    |--------------------------------------------------------------------------
    | Initial HTTP request processing (before queue takes over)
    |--------------------------------------------------------------------------
    */
    'initial_message_limit' => (int) env('BROADCAST_INITIAL_MESSAGE_LIMIT', 48),
    'initial_max_seconds' => (int) env('BROADCAST_INITIAL_MAX_SECONDS', 45),

    /*
    |--------------------------------------------------------------------------
    | Queue job processing limits
    |--------------------------------------------------------------------------
    */
    'job_message_limit' => (int) env('BROADCAST_JOB_MESSAGE_LIMIT', 100),
    'job_max_seconds' => (int) env('BROADCAST_JOB_MAX_SECONDS', 90),
    'batch_delay_seconds' => (int) env('BROADCAST_BATCH_DELAY_SECONDS', 1),

    /*
    |--------------------------------------------------------------------------
    | Bulk insert chunk size when creating recipient rows
    |--------------------------------------------------------------------------
    */
    'insert_chunk_size' => (int) env('BROADCAST_INSERT_CHUNK_SIZE', 500),

];
