<?php

namespace App\Chrono;

use App\Api\Management\ApiManagementStatus;
use App\Api\Management\ApiPolicyState;

interface LocalWebApiManagement
{
    public function status(?string $notice = null, string $language = 'de'): ApiManagementStatus;
    public function change(#[\SensitiveParameter] array $form, string $remoteAddress): ApiPolicyState;
}
