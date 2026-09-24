<?php

namespace App\Support;

/**
 * Named database queues for inbox work (SiteGround-friendly; no Redis/Horizon).
 * Process with: php artisan queue:work database --queue=inbox-mail,inbox-notify,default
 */
final class InboxQueue
{
    /** Scheduled sends + snooze reopens (highest priority). */
    public const MAIL = 'inbox-mail';

    /** Thread / assignee notifications. */
    public const NOTIFY = 'inbox-notify';

    /** Mail sync pages + inbound lead rules. */
    public const DEFAULT = 'default';
}
