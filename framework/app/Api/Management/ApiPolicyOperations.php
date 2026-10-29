<?php
namespace App\Api\Management;
use App\Accounts\AccountLifecycleAuthority;
interface ApiPolicyOperations { public function apply(AccountLifecycleAuthority $actor, ApiPolicyChange $change): ApiPolicyState; }
