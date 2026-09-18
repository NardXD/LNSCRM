<?php

namespace Tests\Unit;

use App\Support\EmailQuotedHistory;
use PHPUnit\Framework\TestCase;

class EmailQuotedHistoryTest extends TestCase
{
    public function test_forwarded_subject_prefixes_once(): void
    {
        $this->assertSame('Fwd: Need a quote', EmailQuotedHistory::forwardedSubject('Need a quote'));
        $this->assertSame('Fwd: Need a quote', EmailQuotedHistory::forwardedSubject('Fwd: Need a quote'));
        $this->assertSame('fwd: Need a quote', EmailQuotedHistory::forwardedSubject('fwd: Need a quote'));
        $this->assertSame('Fwd: (no subject)', EmailQuotedHistory::forwardedSubject(''));
        $this->assertSame('Fwd: (no subject)', EmailQuotedHistory::forwardedSubject(null));
    }
}
