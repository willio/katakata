<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Email;

use Katakata\Email\Import\MobileconfigAccountImporter;
use Katakata\Email\Import\SafePlistParser;
use PHPUnit\Framework\TestCase;

final class MobileconfigAccountImporterTest extends TestCase
{
    public function testItImportsSupportedImapPayloadsAndFlagsEmbeddedCredentials(): void
    {
        $profile = $this->profile([
            $this->mailPayload([
                'EmailAccountDescription' => 'Letters',
                'EmailAddress' => 'letters@example.test',
                'IncomingMailServerHostName' => 'imap.example.test',
                'IncomingMailServerPortNumber' => 993,
                'IncomingMailServerUseSSL' => true,
                'IncomingMailServerUsername' => 'letters@example.test',
                'IncomingMailServerPassword' => 'do-not-store',
                'OutgoingMailServerHostName' => 'smtp.example.test',
            ]),
        ]);

        $accounts = (new MobileconfigAccountImporter(new SafePlistParser()))->import($profile);

        self::assertCount(1, $accounts);
        self::assertSame('Letters', $accounts[0]->label);
        self::assertSame('imap.example.test', $accounts[0]->incomingHost);
        self::assertSame('ssl', $accounts[0]->incomingEncryption);
        self::assertTrue($accounts[0]->embeddedCredentialDetected);
        self::assertStringNotContainsString('do-not-store', json_encode($accounts[0]->toArray(), JSON_THROW_ON_ERROR));
    }

    public function testItIgnoresUnrelatedAndPopPayloads(): void
    {
        $profile = $this->profile([
            ['PayloadType' => 'com.apple.wifi.managed', 'SSID_STR' => 'Office'],
            $this->mailPayload(['EmailAccountType' => 'EmailTypePOP']),
            $this->mailPayload(['EmailAccountDescription' => 'Editorial']),
        ]);

        $accounts = (new MobileconfigAccountImporter(new SafePlistParser()))->import($profile);

        self::assertCount(1, $accounts);
        self::assertSame('Editorial', $accounts[0]->label);
    }

    public function testItRejectsDoctypeAndIdentityMaterial(): void
    {
        $parser = new SafePlistParser();
        $this->expectExceptionMessage('unsupported declarations');
        $parser->parse("<?xml version=\"1.0\"?><!DOCTYPE plist SYSTEM \"file:///etc/passwd\"><plist><dict/></plist>");
    }

    public function testItRejectsSignedOrBinaryProfilesExplicitly(): void
    {
        $this->expectExceptionMessage('Signed or binary configuration profiles are not supported');
        (new SafePlistParser())->parse("\x30\x82\x01\x00CMS-SignedData");
    }

    public function testItRejectsMailProfilesWithIdentityMaterial(): void
    {
        $profile = $this->profile([
            $this->mailPayload(['PayloadCertificateUUID' => 'certificate-id']),
        ]);

        $this->expectExceptionMessage('identity material');
        (new MobileconfigAccountImporter(new SafePlistParser()))->import($profile);
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function mailPayload(array $overrides = []): array
    {
        return $overrides + [
            'PayloadType' => 'com.apple.mail.managed',
            'EmailAccountType' => 'EmailTypeIMAP',
            'EmailAccountDescription' => 'Mailbox',
            'IncomingMailServerHostName' => 'imap.example.test',
            'IncomingMailServerPortNumber' => 993,
            'IncomingMailServerUseSSL' => true,
            'IncomingMailServerUsername' => 'reader@example.test',
        ];
    }

    /** @param list<array<string,mixed>> $payloads */
    private function profile(array $payloads): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<plist version="1.0"><dict><key>PayloadContent</key><array>'
            . implode('', array_map(fn (array $payload): string => $this->dict($payload), $payloads))
            . '</array></dict></plist>';
    }

    /** @param array<string,mixed> $values */
    private function dict(array $values): string
    {
        $xml = '<dict>';
        foreach ($values as $key => $value) {
            $xml .= '<key>' . htmlspecialchars((string) $key, ENT_XML1) . '</key>';
            $xml .= match (true) {
                is_bool($value) => $value ? '<true/>' : '<false/>',
                is_int($value) => '<integer>' . $value . '</integer>',
                default => '<string>' . htmlspecialchars((string) $value, ENT_XML1) . '</string>',
            };
        }
        return $xml . '</dict>';
    }
}
