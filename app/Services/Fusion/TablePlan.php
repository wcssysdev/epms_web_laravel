<?php

namespace App\Services\Fusion;

/**
 * Immutable per-table classification result produced by {@see TableTaxonomy}.
 *
 * Shape per design Data Models / requirements glossary:
 *   TablePlan { table, category, pkStrategy, injectCompanyId, injectCountryId, dateColumn? }
 *
 * NOTE (task 2.1 reconciliation): this value object is a deliverable of task
 * 2.1. At the time task 2.2 (TableTaxonomy) was implemented, only
 * app/Services/Fusion/SourceConnection.php existed, so TablePlan was created
 * here as the minimal fallback the taxonomy returns. When task 2.1 lands,
 * keep ONE definition — prefer task 2.1's if its constructor is
 * shape-compatible with this one (table, category, pkStrategy, injectCompanyId,
 * injectCountryId, dateColumn). The `dateColumn` is nullable and is normally
 * resolved by the DateColumnRegistry (task 2.4); the taxonomy leaves it null
 * unless a resolver is supplied, so the caller may fill it in.
 */
final class TablePlan
{
    public function __construct(
        public readonly string $table,
        public readonly TableCategory $category,
        public readonly PkStrategy $pkStrategy,
        public readonly bool $injectCompanyId = false,
        public readonly bool $injectCountryId = false,
        public readonly ?string $dateColumn = null,
    ) {
    }

    /**
     * Return a copy of this plan with the date column set (e.g. once the
     * DateColumnRegistry from task 2.4 has resolved it for a header table).
     */
    public function withDateColumn(?string $dateColumn): self
    {
        return new self(
            $this->table,
            $this->category,
            $this->pkStrategy,
            $this->injectCompanyId,
            $this->injectCountryId,
            $dateColumn,
        );
    }

    /** True for categories the generic copiers skip (handled by dedicated components). */
    public function isHandledElsewhere(): bool
    {
        return in_array($this->category, [
            TableCategory::EXCLUDE,
            TableCategory::RBAC,
            TableCategory::TENANT_META,
        ], true);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'table' => $this->table,
            'category' => $this->category->value,
            'pkStrategy' => $this->pkStrategy->value,
            'injectCompanyId' => $this->injectCompanyId,
            'injectCountryId' => $this->injectCountryId,
            'dateColumn' => $this->dateColumn,
        ];
    }
}
