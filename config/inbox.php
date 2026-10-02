<?php

return [

    /*
    | Front-style threading: every email exchanged with the same address in an
    | inbox lands in one conversation (see InboxContactThreadService).
    */
    'group_by_contact' => env('INBOX_GROUP_BY_CONTACT', true),

    /*
    | Addresses with more separate threads than this are treated as bulk senders
    | (banks, PayPal, OTP services…) and never grouped.
    */
    'group_by_contact_max_threads' => (int) env('INBOX_GROUP_BY_CONTACT_MAX_THREADS', 40),

    /*
    | Addresses (full address or "@domain") that always keep separate threads,
    | e.g. web-form relays and notification senders that carry many unrelated
    | customers. The inbox's own mailbox domain is always excluded as well.
    | Comma-separated in the env.
    */
    'group_by_contact_exclude' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'INBOX_GROUP_BY_CONTACT_EXCLUDE',
            '@locnstor247.com,@sezpr02mb6201.apcprd02.prod.outlook.com,@c128513.sgvps.net'
        ))
    ))),

];
