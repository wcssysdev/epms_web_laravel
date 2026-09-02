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
        $isItStaff = $this->currentUser?->role_code === 'it_staff';

        $cards = [
            // ── Planning (Asst Manager 50 + Estate Manager 40) ──────────────
            [
                'icon'  => '📝',
                'title' => 'Workplan',
                'desc'  => 'Plan daily field activities and submit for approval',
                'route' => 'planning.workplan.index',
                'color' => 'bg-teal-50 dark:bg-teal-900/20',
                'min_level' => 50,
            ],
            [
                'icon'  => '🌾',
                'title' => 'Harvesting Plan',
                'desc'  => 'Set harvesting targets per block',
                'route' => 'planning.harvesting_plan.index',
                'color' => 'bg-lime-50 dark:bg-lime-900/20',
                'min_level' => 50,
            ],
            // ── Approval (Estate Manager 40) ────────────────────────────────
            [
                'icon'  => '✅',
                'title' => 'Workplan Approval',
                'desc'  => 'Review and approve published workplans',
                'route' => 'approval.workplan.index',
                'color' => 'bg-emerald-50 dark:bg-emerald-900/20',
                'min_level' => 40,
            ],
            // ── Master & Admin ──────────────────────────────────────────────
            [
                'icon'  => '📁',
                'title' => 'Master Data',
                'desc'  => 'Manage activities, blocks, divisions, employees, and more',
                'route' => 'masters.estate.index',
                'color' => 'bg-yellow-50 dark:bg-yellow-900/20',
                'min_level' => 40,
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
            // ── Grouping (Asst Manager 50 + IT Staff) ───────────────────────
            [
                'icon'  => '👥',
                'title' => 'Field Assistant Division',
                'desc'  => 'Assign field assistants to divisions',
                'route' => 'grouping.field_assistant_division.index',
                'color' => 'bg-orange-50 dark:bg-orange-900/20',
                'min_level' => 50,
            ],
            [
                'icon'  => '🧑‍🤝‍🧑',
                'title' => 'Field Staff',
                'desc'  => 'Manage field staff and gang assignments',
                'route' => 'grouping.field_staff.index',
                'color' => 'bg-purple-50 dark:bg-purple-900/20',
                'min_level' => 50,
            ],
            [
                'icon'  => '👫',
                'title' => 'Gang Employee',
                'desc'  => 'Manage gang employee groupings',
                'route' => 'grouping.gang_employee.index',
                'color' => 'bg-cyan-50 dark:bg-cyan-900/20',
                'min_level' => 50,
            ],
            [
                'icon'  => '📋',
                'title' => 'Mandor Employee',
                'desc'  => 'Manage mandor (foreman) to employee assignments',
                'route' => 'grouping.mandor_employee.index',
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

        // Filter cards by role level, granting IT Staff master + grouping access
        return array_values(array_filter($cards, function ($card) use ($roleLevel, $isItStaff) {
            if ($roleLevel <= $card['min_level']) {
                return true;
            }
            // IT Staff (level 60) also gets Master Data + Grouping cards
            if ($isItStaff && in_array($card['title'], [
                'Master Data', 'Field Assistant Division', 'Field Staff',
                'Gang Employee', 'Mandor Employee',
            ], true)) {
                return true;
            }
            return false;
        }));
    }
}
