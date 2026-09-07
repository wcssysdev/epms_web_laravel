<?php

namespace App\Http\Controllers\Transaction;

use App\Models\Transaction\Cp;

/** CP2 (Palm) — checkpoint type 2. */
class Checkpoint2Controller extends CpEntryController
{
    protected function cpType(): int          { return Cp::TYPE_CP2; }
    protected function title(): string         { return 'CP2 (Palm)'; }
    protected function routePrefix(): string   { return 'transactions.checkpoint_2'; }
}
