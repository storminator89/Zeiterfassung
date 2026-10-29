<?php

namespace App\Chrono;

/** Optional bounded read capability, not a model command or Runtime admission. */
interface WorkModelCreditPreviewReads
{
    public function workModelCreditPreview(array $command): array;
}
