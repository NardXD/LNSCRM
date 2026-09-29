<?php

namespace App\Support;

/**
 * Named queue for outbound SMS/WhatsApp sends (the Twilio API call itself),
 * kept separate from the inbox queues so a burst of messages doesn't delay
 * mail sync, and vice versa. See docs/INBOX_QUEUE_SETUP.md for the worker setup.
 */
final class MessagingQueue
{
    public const SEND = 'messaging';
}
