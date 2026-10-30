<?php

namespace App\Chrono;

/** Optional paged read receipt; never a model save command or stored snapshot. */
interface WorkModelCreditPreviewPageReads extends WorkModelCreditPreviewReads
{
    public function workModelCreditPreviewPage(array $command, mixed $page): array;
}
