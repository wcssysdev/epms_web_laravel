<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends BaseController
{
    public function index(): View
    {
        $roleLevel = $this->roleLevel();

        // Quick stats — only query if user has company scope
        $stats = $this->getStats();

        // Quick access cards — role-aware
        $quickAccess = $this->getQuickAccessCards($roleLevel);

        return view('admin.dashboard.index', compact('stats', 'quickAccess', 'roleLevel'));
    }

    private function getStats(): array
    {
        $companyId = $this->companyId();

        if (! $companyId && ! $this->isSuperAdmin()) {
            return [];
        }

        try {
            $where = $companyId ? ['company_id' => $companyId] : [];

            return [
                'employees' => DB::table('m_employee')->where($where)->count(),
                'estates'   => DB::table('m_estate')->where($where)->count(),
                'divisions' => DB::table('m_division')->where($where)->count(),
                'devices'   => DB::table('m_devices')->where($where)->count(),
            ];
        } catch (\Exception $e) {
            return ['employees' => 0, 'estates' => 0, 'divisions' => 0, 'devices' => 0];
        }
    }

    private function getQuickAccessCards(int $roleLevel): array
    {
        $cards = [
            [
                'icon'  => '📁',
                'title' => 'Master Data',
                'desc'  => 'Manage master data: activities, blocks, divisions, employees, and more',
                'route' => '#',
                'color' => 'bg-yellow-50 dark:bg-yellow-900/20',
                'min_level' => 70,
            ],
            [
                'icon'  => '🏠',
                'title' => 'Estate Settings',
                'desc'  => 'Configure estate, integration type, and system settings',
                'route' => 'admin.config.index',
                'color' => 'bg-green-50 dark:bg-green-900/20',
                'min_level' => 30,
            ],
            [
                'icon'  => '⚙️',
                'title' => 'Account Settings',
                'desc'  => 'Manage user accounts and access levels',
                'route' => 'admin.users.index',
                'color' => 'bg-blue-50 dark:bg-blue-900/20',
                'min_level' => 30,
            ],
            [
                'icon'  => '👥',
                'title' => 'Field Assistant Division',
                'desc'  => 'Assign field assistants to divisions',
                'route' => '#',
                'color' => 'bg-orange-50 dark:bg-orange-900/20',
                'min_level' => 50,
            ],
            [
                'icon'  => '🧑‍🤝‍🧑',
                'title' => 'Field Staff',
                'desc'  => 'Manage field staff and gang assignments',
                'route' => '#',
                'color' => 'bg-purple-50 dark:bg-purple-900/20',
                'min_level' => 50,
            ],
            [
                'icon'  => '👫',
                'title' => 'Gang Employee',
                'desc'  => 'Manage gang employee groupings',
                'route' => '#',
                'color' => 'bg-cyan-50 dark:bg-cyan-900/20',
                'min_level' => 50,
            ],
            [
                'icon'  => '📋',
                'title' => 'Mandor Employee',
                'desc'  => 'Manage mandor (foreman) to employee assignments',
                'route' => '#',
                'color' => 'bg-indigo-50 dark:bg-indigo-900/20',
                'min_level' => 50,
            ],
            [
                'icon'  => '🔄',
                'title' => 'Manager Substitution',
                'desc'  => 'Set approval substitutions for estate managers',
                'route' => '#',
                'color' => 'bg-pink-50 dark:bg-pink-900/20',
                'min_level' => 40,
            ],
        ];

        // Filter cards by role level
        return array_filter($cards, fn($card) => $roleLevel <= $card['min_level']);
    }
}
