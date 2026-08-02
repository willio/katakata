<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit\Http;

use Katakata\Http\Request;
use Katakata\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

final class RequestUploadedFileTest extends TestCase
{
    public function testItExposesUploadedFilesThroughTheRequestContract(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'katakata-upload-');
        self::assertIsString($path);
        file_put_contents($path, '<plist/>');

        try {
            $request = new Request(
                method: 'POST',
                path: '/dashboard/settings/mailboxes/import',
                files: [
                    'profile' => new UploadedFile(
                        name: 'mail.mobileconfig',
                        temporaryPath: $path,
                        size: (int) filesize($path),
                        error: UPLOAD_ERR_OK,
                        mediaType: 'application/x-apple-aspen-config',
                    ),
                ],
            );

            $upload = $request->file('profile');
            self::assertInstanceOf(UploadedFile::class, $upload);
            self::assertTrue($upload->valid());
            self::assertSame('<plist/>', $upload->contents());
            self::assertNull($request->file('missing'));
        } finally {
            @unlink($path);
        }
    }

    public function testInvalidUploadCannotBeRead(): void
    {
        $upload = new UploadedFile('mail.mobileconfig', '', 0, UPLOAD_ERR_NO_FILE);

        self::assertFalse($upload->valid());
        self::assertNull($upload->contents());
    }
}
