<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The phone's PHP is not the desktop's. Code under app/ must not lean on what it lacks:
 *
 *  - GD: not compiled in. Image functions are undefined, and dompdf throws on any PNG.
 *  - endroid/qr-code: NativePHP leaves vendor/endroid out of the app bundle.
 *  - sys_get_temp_dir(): /tmp, which Android does not have.
 *
 * Passing tests on the desktop prove nothing about these, so they are checked by reading the code.
 */
final class PhoneRuntimeTest extends TestCase
{
    public function test_app_code_avoids_what_the_phone_lacks(): void
    {
        $found = [];

        foreach (['app', 'config'] as $folder) {
            $found = [...$found, ...$this->scan(dirname(__DIR__, 2) . '/' . $folder)];
        }

        $this->assertSame([], $found);
    }

    /** @return list<string> */
    private function scan(string $root): array
    {
        $found = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) file_get_contents($file->getPathname());
            $relative = basename($root) . '/' . substr($file->getPathname(), strlen($root) + 1);

            if (str_contains($code, 'Endroid\\')) {
                $found[] = "$relative uses endroid/qr-code (not in the app bundle; use BaconQrCode)";
            }
            if (str_contains($code, 'sys_get_temp_dir(')) {
                $found[] = "$relative uses sys_get_temp_dir() (no /tmp on Android; use DeviceFiles)";
            }
            // GD is allowed only behind CompanyImageStore's own function_exists() check.
            if ($relative !== 'app/Services/CompanyImageStore.php' && preg_match('/\bimage(create|png|jpeg|copy|fill|sx|sy)\w*\(/', $code) === 1) {
                $found[] = "$relative calls a GD function (the phone's PHP has no GD)";
            }
        }

        return $found;
    }
}
