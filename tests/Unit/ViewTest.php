<?php

declare(strict_types=1);

namespace Katakata\Tests\Unit;

use Katakata\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ViewTest extends TestCase
{
    private string $viewsPath;

    protected function setUp(): void
    {
        $this->viewsPath = sys_get_temp_dir() . '/katakata-views-' . bin2hex(random_bytes(6));
        mkdir($this->viewsPath, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->viewsPath . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->viewsPath);
    }

    public function testItRendersExplicitDataAndEscapesOutput(): void
    {
        file_put_contents(
            $this->viewsPath . '/greeting.php',
            '<?= e($greeting) ?>',
        );

        $output = (new View($this->viewsPath))->render(
            'greeting',
            ['greeting' => '<script>alert("xss")</script>'],
        );

        self::assertSame(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            $output,
        );
    }

    public function testIncludedViewDoesNotReceiveTheViewInstance(): void
    {
        file_put_contents(
            $this->viewsPath . '/scope.php',
            '<?= isset($this) ? "exposed" : "isolated" ?>',
        );

        self::assertSame('isolated', (new View($this->viewsPath))->render('scope'));
    }

    public function testItRejectsMissingAndUnsafeViewNames(): void
    {
        $view = new View($this->viewsPath);

        $this->expectException(RuntimeException::class);
        $view->render('../secret');
    }
}
