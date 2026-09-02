<?php

namespace App\Traits;

use App\Models\Master\AssistantManagerDivision;

/**
 * Resolves the set of division codes an approver is allowed to act on.
 *
 * Mirrors the CI4 `divisionCodes()` pattern:
 *   - Assistant Manager (role_code = asst_manager): limited to their mapped
 *     divisions from m_assistant_manager_division. Empty array = no divisions
 *     (approver sees / can act on nothing).
 *   - Estate Manager and above: null = no division filter (see all divisions).
 *
 * The mapping key is stored as a varchar (assistant_manager_code) while the
 * user id is numeric, so we always compare as strings.
 *
 * Requires the consuming controller to expose currentUser (BaseController does).
 */
trait ScopesToAssistantDivisions
{
    /**
     * @return array<int,string>|null  null = all divisions, array = restricted set
     */
    protected function approverDivisions(): ?array
    {
        $user = $this->currentUser;
        if (! $user) {
            return [];
        }

        // Only the Assistant Manager role is division-scoped. Everyone above sees all.
        if ($user->role_code !== 'asst_manager') {
            return null;
        }

        return AssistantManagerDivision::where('assistant_manager_code', (string) $user->id)
            ->pluck('division_code')
            ->filter(fn ($code) => is_string($code) && $code !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * True when the approver is division-restricted but has no divisions mapped.
     * In that case they should be shown / allowed nothing.
     */
    protected function hasNoApproverDivisions(?array $divisions): bool
    {
        return is_array($divisions) && $divisions === [];
    }
}
