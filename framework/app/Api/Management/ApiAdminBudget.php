<?php
namespace App\Api\Management;
interface ApiAdminBudget { public function consume(int $user, string $remoteAddress): void; }
