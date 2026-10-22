<?php

namespace App\Services\Fusion;

use RuntimeException;
use Throwable;

/**
 * Thrown by {@see RowTransformer::transform()} when a single source row cannot be
 * coerced from PostgreSQL to the SQL Server target shape (a type or encoding
 * error, per requirement R13.5).
 *
 * The transformer itself stays PURE: it never touches the database and never
 * writes to the error ledger. Instead it throws this typed exception so the
 * CALLER (MasterCopier / TransactionCopier) can catch it, record the offending
 * row to the error ledger, and CONTINUE the batch — satisfying R13.5 at the
 * copier level ("record the row to the error ledger and continue the batch").
 *
 * The exception carries the original row and the owning table so the ledger
 * entry is self-describing without the copier re-deriving context.
 *
 * @see .kiro/specs/estate-db-fusion/design.md  LLD-10 (RowTransformer::transform)
 */
class RowTransformException extends RuntimeException
{
    /**
     * @param string               $table  Source/target table the row belongs to.
     * @param array<string,mixed>  $row    The offending row (as received by transform()).
     * @param string               $column The column whose value could not be coerced.
     * @param string               $reason Human-readable cause (type/encoding detail).
     */
    public function __construct(
        public readonly string $table,
        public readonly array $row,
        public readonly string $column,
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Row transform failed for %s.%s: %s', $table, $column, $reason),
            0,
            $previous,
        );
    }
}
