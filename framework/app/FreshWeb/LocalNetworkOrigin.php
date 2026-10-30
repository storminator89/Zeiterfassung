<?php
namespace App\FreshWeb;

/** HTTP is supported only for literal private/loopback IPv4 and localhost. */
final class LocalNetworkOrigin
{
    public static function allowsHttp(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) return true;
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return false;
        $ip=array_map('intval',explode('.', $host));
        return $ip[0]===127 || $ip[0]===10 || ($ip[0]===172 && $ip[1]>=16 && $ip[1]<=31)
            || ($ip[0]===192 && $ip[1]===168);
    }
}
