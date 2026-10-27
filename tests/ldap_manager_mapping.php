<?php
require_once __DIR__ . '/../lib/ldap_manager_mapping.php';

// Deliberate parser boundary fixture, not a substitute implementation of LDAP.
// No ext-ldap/network/config/session/database/account operations are invoked.
$suffix = ',ou=People,ou=Region,dc=example,dc=test';
$manager = 'uid=Boss\\,Plus\\+Eq\\=Slash\\\\End+cn=Lead' . $suffix;
$managerAlias = 'CN=Lead+UID=Boss\\2cPlus\\2bEq\\3dSlash\\5cEnd' . $suffix;
$employee = 'uid=Employee' . $suffix;
$cnManager = 'cn=NotAUsername' . $suffix;
$other = 'uid=Other' . $suffix;
$unknown = 'uid=Unknown' . $suffix;
$invalid = 'uid=Bad\\';
$rdns = ['ou=People', 'ou=Region', 'dc=example', 'dc=test'];
$parsed = [
    $manager => array_merge(['uid=Boss\\,Plus\\+Eq\\=Slash\\\\End+cn=Lead'], $rdns),
    $managerAlias => array_merge(['CN=Lead+UID=Boss\\2cPlus\\2bEq\\3dSlash\\5cEnd'], $rdns),
    $employee => array_merge(['uid=Employee'], $rdns),
    $cnManager => array_merge(['cn=NotAUsername'], $rdns),
    $other => array_merge(['uid=Other'], $rdns),
    $unknown => array_merge(['uid=Unknown'], $rdns),
];
$parser = static function (string $dn, int $attributes) use ($parsed): array|false {
    if ($attributes !== 0) { throw new RuntimeException('Attribute names must be retained.'); }
    return isset($parsed[$dn]) ? ['count' => count($parsed[$dn])] + $parsed[$dn] : false;
};
$check = static function (bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
};
$row = static fn(int $id, string $dn, string $managerDn = '') => ['id' => $id, 'dn' => $dn, 'manager_dn' => $managerDn];
$check(chrono_ldap_dn_key($manager, $parser) === chrono_ldap_dn_key($managerAlias, $parser), 'Escapes/attribute case/multivalue order');
$check(chrono_ldap_dn_key($invalid, $parser) === null, 'Malformed DN rejected');
$check(chrono_ldap_dn_key($invalid, static fn() => ['count' => 1, 'uid=Bad\\']) === null, 'Truncated parser component rejected');
$check(chrono_ldap_dn_key("uid=Bad\0", $parser) === null, 'NUL rejected before parser');
if (!function_exists('ldap_explode_dn')) {
    $check(chrono_ldap_dn_key($manager) === null, 'Unavailable extension must fail closed');
}

// Employee arrives before a new manager; reorder and repeat give the same plan.
$identities = [$row(10, $employee, $managerAlias), $row(20, $manager), $row(30, $other, $cnManager), $row(40, $cnManager)];
$current = [10 => 99, 20 => null, 30 => null, 40 => null, 99 => null];
$expected = ['assignments' => [10 => 20, 20 => null, 30 => 40, 40 => null], 'conflicts' => []];
$plan = chrono_ldap_manager_plan($identities, $current, $parser);
$check($plan === $expected, 'Second-pass exact DN plan');
$check(chrono_ldap_manager_plan(array_reverse($identities), $current, $parser) === $expected, 'Order independence');
$check(chrono_ldap_manager_plan($identities, array_replace($current, $plan['assignments']), $parser) === $expected, 'Repeat stability');

// Unknown/malformed references must not overwrite an old supervisor or guess UID.
$plan = chrono_ldap_manager_plan([$row(10, $employee, $unknown), $row(20, $manager, $invalid)], $current, $parser);
$check($plan === ['assignments' => [], 'conflicts' => [10 => 'unknown_manager', 20 => 'invalid_manager_dn']], 'Unknown/invalid manager conflict');
$check(array_replace($current, $plan['assignments']) === $current, 'Conflict retains previous assignments');
$plan = chrono_ldap_manager_plan([$row(10, $invalid)], $current, $parser);
$check($plan === ['assignments' => [], 'conflicts' => [10 => 'invalid_identity_dn']], 'Invalid identity cannot silently clear manager');
$plan = chrono_ldap_manager_plan([$row(10, $employee, $manager), $row(20, $manager), $row(21, $manager)], $current, $parser);
$check($plan['assignments'] === [] && $plan['conflicts'][10] === 'ambiguous_manager', 'Duplicate DN cannot choose an arbitrary manager');
$plan = chrono_ldap_manager_plan([$row(10, $employee, $manager), $row(20, $manager), $row(20, $other)], $current, $parser);
$check(!isset($plan['assignments'][10]) && $plan['conflicts'][20] === 'ambiguous_identity', 'Duplicate account cannot choose an arbitrary identity');

// Self, imported cycle and links into preserved cycles are report-only conflicts.
$plan = chrono_ldap_manager_plan([$row(10, $employee, $employee)], $current, $parser);
$check($plan['conflicts'] === [10 => 'self_manager'], 'Self relationship rejected');
$plan = chrono_ldap_manager_plan([$row(10, $employee, $manager), $row(20, $manager, $employee)], $current, $parser);
$check($plan === ['assignments' => [], 'conflicts' => [10 => 'cyclic_manager', 20 => 'cyclic_manager']], 'Cycle must preserve old relationships');
$plan = chrono_ldap_manager_plan([$row(10, $employee, $manager), $row(20, $manager, $invalid)], [10 => null, 20 => 30, 30 => 20], $parser);
$check($plan === ['assignments' => [], 'conflicts' => [10 => 'cyclic_manager', 20 => 'invalid_manager_dn']], 'Preserved graph included in cycle check');
echo "PASS: synthetic two-pass manager mapping, order/repeat, DN normalization boundary, conflicts and cycles\n";
