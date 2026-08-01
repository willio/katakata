<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\Email\Mailbox;
use Katakata\Email\Providers\UnavailableMailboxProvider;
use PHPUnit\Framework\TestCase;

final class MailboxTest extends TestCase
{
    public function test_unavailable_provider_is_empty_and_reports_deployment_setup(): void
    {
        $mailbox = new Mailbox(new UnavailableMailboxProvider());

        $this->assertSame([], $mailbox->inbox());
        $this->assertSame(0, $mailbox->unreadCount());
        $this->assertSame([
            'status' => 'needs_setup',
            'reason' => 'IMAP inbox configuration is deployment-only and has not been enabled.',
            'last_synced_at' => null,
        ], $mailbox->readiness());
    }
}
