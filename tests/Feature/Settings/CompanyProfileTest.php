<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Services\CompanyImageStore;
use App\Settings\CompanySettings;
use App\Support\Uploads;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

final class CompanyProfileTest extends TestCase
{
    use RefreshDatabase;

    private string $publicPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Logos are written under storage/ on the phone; point that at a scratch folder.
        $this->publicPath = sys_get_temp_dir() . '/zi-storage-' . bin2hex(random_bytes(4));
        mkdir($this->publicPath);
        $this->app->useStoragePath($this->publicPath);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->publicPath);

        parent::tearDown();
    }

    public function test_the_page_renders(): void
    {
        $this->actingAs($this->owner())->get(route('company.edit'))
            ->assertOk()
            ->assertSee('Invoice header preview');
    }

    public function test_details_are_saved_and_tidied(): void
    {
        $this->actingAs($this->owner())
            ->put(route('company.update'), $this->valid([
                'upi_id' => ' 8056556341@PTHDFC ',
                'website' => 'thefivestar.in',
                'address_line2' => '',
            ]))
            ->assertRedirect(route('company.edit'))
            ->assertSessionHasNoErrors();

        $company = app(CompanySettings::class);
        $this->assertSame('Zentro Private Limited', $company->name);
        $this->assertSame('8056556341@pthdfc', $company->upiId);
        $this->assertSame('https://thefivestar.in', $company->website);
        $this->assertNull($company->addressLine2);
        $this->assertSame(['12 Main Road', 'Karaikudi, Tamil Nadu - 630001'], $company->addressLines());
    }

    public function test_a_malformed_upi_id_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->put(route('company.update'), $this->valid(['upi_id' => 'not-a-upi-id']))
            ->assertSessionHasErrors('upi_id');
    }

    public function test_a_test_qr_appears_once_a_upi_id_is_set(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->get(route('company.edit'))->assertDontSee('Test UPI QR code');

        $this->actingAs($user)->put(route('company.update'), $this->valid(['upi_id' => 'shop@ybl']));

        $this->actingAs($user)->get(route('company.edit'))->assertSee('Test UPI QR code');
    }

    public function test_a_logo_is_resized_to_png_and_replaced_cleanly(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->put(route('company.update'), $this->valid([
            'logo' => UploadedFile::fake()->image('logo.jpg', 2000, 1000),
        ]))->assertSessionHasNoErrors();

        $first = app(CompanySettings::class)->logoPath;
        $this->assertStringEndsWith('.png', $first);
        [$width, $height] = getimagesize(\App\Support\Uploads::path($first));
        $this->assertSame(600, $width, 'Scaled to fit 800×300, keeping the 2:1 shape.');
        $this->assertSame(300, $height);

        $this->travel(2)->seconds();
        $this->actingAs($user)->put(route('company.update'), $this->valid([
            'logo' => UploadedFile::fake()->image('new.png', 200, 100),
        ]));

        $second = app(CompanySettings::class)->logoPath;
        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist(\App\Support\Uploads::path($first), 'The replaced logo is deleted.');
        $this->assertFileExists(\App\Support\Uploads::path($second));

        $this->actingAs($user)->put(route('company.update'), $this->valid(['remove_logo' => '1']));
        $this->assertNull(app(CompanySettings::class)->logoPath);
        $this->assertFileDoesNotExist(\App\Support\Uploads::path($second));
    }

    public function test_a_signature_is_stored_beside_the_logo_and_can_be_removed(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->put(route('company.update'), $this->valid([
            'signature' => UploadedFile::fake()->image('sign.png', 1200, 300),
        ]))->assertSessionHasNoErrors();

        $company = app(CompanySettings::class);
        $path = $company->signaturePath;
        $this->assertStringStartsWith('uploads/company/signature-', $path);
        [$width, $height] = getimagesize(\App\Support\Uploads::path($path));
        $this->assertSame([600, 150], [$width, $height], 'Fitted inside 600×200.');
        $this->assertNull($company->logoPath, 'Uploading a signature leaves the logo alone.');

        $this->actingAs($user)->put(route('company.update'), $this->valid(['remove_signature' => '1']));
        $this->assertNull(app(CompanySettings::class)->signaturePath);
        $this->assertFileDoesNotExist(\App\Support\Uploads::path($path));
    }

    public function test_a_picture_from_the_phone_picker_arrives_as_png_data(): void
    {
        ob_start();
        imagepng(imagecreatetruecolor(900, 300));
        $png = 'data:image/png;base64,' . base64_encode((string) ob_get_clean());

        $this->actingAs($this->owner())
            ->put(route('company.update'), $this->valid(['logo_data' => $png]))
            ->assertSessionHasNoErrors();

        $path = app(CompanySettings::class)->logoPath;
        $this->assertStringStartsWith('uploads/company/logo-', $path);
        $this->assertFileExists(\App\Support\Uploads::path($path));
        // Shown in the app through the media route, not a public file.
        $this->assertStringContainsString('/media/logo-', (string) app(CompanySettings::class)->logoUrl());
    }

    public function test_a_jpeg_from_the_phone_picker_is_accepted(): void
    {
        $this->actingAs($this->owner())
            ->put(route('company.update'), $this->valid(['signature_data' => $this->jpegDataUrl(600, 200)]))
            ->assertSessionHasNoErrors();

        $this->assertFileExists(Uploads::path((string) app(CompanySettings::class)->signaturePath));
    }

    /** The phone's PHP has no GD: the page's JPEG is kept as it came, as a .jpg. */
    public function test_without_gd_a_print_size_jpeg_is_stored_as_it_came(): void
    {
        $file = $this->jpegUpload(800, 300);
        $bytes = (string) file_get_contents($file->getRealPath());

        $path = (new CompanyImageStore(gd: false))->store($file, null, 'logo', 800, 300);

        $this->assertMatchesRegularExpression('#^uploads/company/logo-\d+\.jpg$#', $path);
        $this->assertSame($bytes, file_get_contents(Uploads::path($path)));

        // Served to the page with its own type.
        $this->actingAs($this->owner())->get(route('media', basename($path)))
            ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_without_gd_a_png_or_an_oversized_picture_is_refused(): void
    {
        $store = new CompanyImageStore(gd: false);

        ob_start();
        imagepng(imagecreatetruecolor(100, 100));
        $png = UploadedFile::fake()->createWithContent('logo.png', (string) ob_get_clean());

        foreach ([$png, $this->jpegUpload(1600, 600)] as $file) {
            try {
                $store->store($file, null, 'logo', 800, 300);
                $this->fail('A picture the phone cannot keep as it came was stored.');
            } catch (\RuntimeException) {
                // Refused, as it should be.
            }
        }

        $this->assertSame([], glob(Uploads::path('uploads/company') . '/*') ?: [], 'Nothing was written.');
    }

    private function jpegUpload(int $width, int $height): UploadedFile
    {
        ob_start();
        imagejpeg(imagecreatetruecolor($width, $height));

        return UploadedFile::fake()->createWithContent('picked.jpg', (string) ob_get_clean());
    }

    private function jpegDataUrl(int $width, int $height): string
    {
        ob_start();
        imagejpeg(imagecreatetruecolor($width, $height));

        return 'data:image/jpeg;base64,' . base64_encode((string) ob_get_clean());
    }

    public function test_data_that_is_not_a_picture_data_url_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->put(route('company.update'), $this->valid(['logo_data' => 'data:text/html;base64,' . base64_encode('<b>x</b>')]))
            ->assertSessionHasErrors('logo_data');
    }

    public function test_data_that_is_not_a_png_is_refused(): void
    {
        $this->actingAs($this->owner())
            ->put(route('company.update'), $this->valid(['logo_data' => 'data:image/png;base64,' . base64_encode('not a picture')]))
            ->assertSessionHasErrors('logo');

        $this->assertNull(app(CompanySettings::class)->logoPath);
    }

    public function test_a_non_image_upload_is_rejected(): void
    {
        $this->actingAs($this->owner())
            ->put(route('company.update'), $this->valid([
                'logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'),
            ]))
            ->assertSessionHasErrors('logo');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Zentro Private Limited',
            'phone' => '+91 80565 56341',
            'email' => 'zentrohq@proton.me',
            'address_line1' => '12 Main Road',
            'city' => 'Karaikudi',
            'state' => 'Tamil Nadu',
            'pincode' => '630001',
        ], $overrides);
    }
}
