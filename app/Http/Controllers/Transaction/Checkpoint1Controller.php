<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Transaction\Cp;

/** CP1 (Palm) — checkpoint type 1. */
class Checkpoint1Controller extends CpEntryController
{
    protected function cpType(): int          { return Cp::TYPE_CP1; }
    protected function title(): string         { return 'CP1 (Palm)'; }
    protected function routePrefix(): string   { return 'transactions.checkpoint_1'; }
}
