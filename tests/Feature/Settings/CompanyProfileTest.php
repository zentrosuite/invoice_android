<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Settings\CompanySettings;
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

        // Logos are written under public/; point that at a scratch folder.
        $this->publicPath = sys_get_temp_dir() . '/zi-public-' . bin2hex(random_bytes(4));
        mkdir($this->publicPath);
        $this->app->usePublicPath($this->publicPath);
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
        [$width, $height] = getimagesize(public_path($first));
        $this->assertSame(600, $width, 'Scaled to fit 800×300, keeping the 2:1 shape.');
        $this->assertSame(300, $height);

        $this->travel(2)->seconds();
        $this->actingAs($user)->put(route('company.update'), $this->valid([
            'logo' => UploadedFile::fake()->image('new.png', 200, 100),
        ]));

        $second = app(CompanySettings::class)->logoPath;
        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist(public_path($first), 'The replaced logo is deleted.');
        $this->assertFileExists(public_path($second));

        $this->actingAs($user)->put(route('company.update'), $this->valid(['remove_logo' => '1']));
        $this->assertNull(app(CompanySettings::class)->logoPath);
        $this->assertFileDoesNotExist(public_path($second));
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
