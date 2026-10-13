<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * The sidebar menu.
 *
 * A link appears only once its route exists, so the menu grows phase by phase without a link
 * to a page that is not built yet.
 */
final class Navigation
{
    /**
     * @var list<array{route?: string, label: string, icon?: string, match?: string, heading?: bool}>
     */
    private const MENU = [
        ['route' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'ri-dashboard-2-line', 'match' => 'dashboard'],
        ['route' => 'invoices.index', 'label' => 'Invoices', 'icon' => 'ri-file-list-3-line', 'match' => 'invoices.*'],
        ['route' => 'customers.index', 'label' => 'Customers', 'icon' => 'ri-user-3-line', 'match' => 'customers.*'],
        ['heading' => true, 'label' => 'Setup'],
        ['route' => 'company.edit', 'label' => 'Company profile', 'icon' => 'ri-building-line', 'match' => 'company.*'],
        ['route' => 'settings.edit', 'label' => 'Invoice settings', 'icon' => 'ri-settings-3-line', 'match' => 'settings.*'],
        ['route' => 'profile.edit', 'label' => 'My profile', 'icon' => 'ri-account-circle-line', 'match' => 'profile.*'],
    ];

    /**
     * @return list<array{label: string, heading?: bool, url?: string, icon?: string, active?: bool}>
     */
    public function items(): array
    {
        $items = [];

        foreach (self::MENU as $entry) {
            if ($entry['heading'] ?? false) {
                $items[] = ['label' => $entry['label'], 'heading' => true];

                continue;
            }

            if (! Route::has($entry['route'])) {
                continue;
            }

            $items[] = [
                'label' => $entry['label'],
                'url' => route($entry['route']),
                'icon' => $entry['icon'],
                'active' => request()->routeIs($entry['match']),
            ];
        }

        return $items;
    }
}
