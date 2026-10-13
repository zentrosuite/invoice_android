<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who is using this installation. Picked once in the setup wizard and changeable later.
 *
 * The profile does not lock anything: it only decides the DEFAULTS an invoice starts from —
 * which columns the line grid shows, what they are called, and which design is used. A shop
 * sells "2 × Rice bag @ 1,250"; a freelancer bills "Website design — 25,000" with no quantity
 * at all; a studio bills hours. Every one of those defaults can be changed in Settings, and
 * "Other" starts from the most general grid so the owner can switch columns off.
 */
enum UsageProfile: string
{
    case Shop = 'shop';
    case Freelancer = 'freelancer';
    case Studio = 'studio';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Shop => 'Shop / Retail',
            self::Freelancer => 'Freelancer',
            self::Studio => 'Studio / Agency',
            self::Other => 'Something else',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Shop => 'Counter bills and digital receipts — items with quantity and rate.',
            self::Freelancer => 'Developers, designers, consultants — services billed as a fixed amount or package.',
            self::Studio => 'Photo, video, design or dev studios — services billed by hours or days.',
            self::Other => 'Start from a general layout and choose your own columns.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Shop => 'ri-store-2-line',
            self::Freelancer => 'ri-code-s-slash-line',
            self::Studio => 'ri-camera-lens-line',
            self::Other => 'ri-apps-2-line',
        };
    }

    /**
     * What a new invoice looks like for this profile, as InvoiceSettings property values.
     *
     * @return array{itemLabel: string, showQuantity: bool, quantityLabel: string, rateLabel: string, template: string}
     */
    public function defaults(): array
    {
        return match ($this) {
            self::Shop => [
                'itemLabel' => 'Item',
                'showQuantity' => true,
                'quantityLabel' => 'Qty',
                'rateLabel' => 'Rate',
                'template' => 'classic',
            ],
            self::Freelancer => [
                'itemLabel' => 'Service description',
                'showQuantity' => false,
                'quantityLabel' => 'Qty',
                'rateLabel' => 'Amount',
                'template' => 'modern',
            ],
            self::Studio => [
                'itemLabel' => 'Service',
                'showQuantity' => true,
                'quantityLabel' => 'Hours',
                'rateLabel' => 'Rate / hr',
                'template' => 'modern',
            ],
            self::Other => [
                'itemLabel' => 'Description',
                'showQuantity' => true,
                'quantityLabel' => 'Qty',
                'rateLabel' => 'Rate',
                'template' => 'classic',
            ],
        };
    }
}
