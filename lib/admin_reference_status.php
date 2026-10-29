<?php
// Definitions-only read presentation. No query, graph traversal or repair.
require_once __DIR__.'/admin_users.php';

/** The assigned ID and matched JOIN ID must come from the same authorized row. */
function admin_reference_status(mixed $assigned, mixed $resolved): string {
    if($assigned===null){return 'unassigned';}
    try{$id=admin_assignment_id($assigned,'Invalid reference.');}
    catch(InvalidArgumentException){return 'invalid';}
    if($resolved===null){return 'missing';}
    try{$matched=admin_assignment_id($resolved,'Invalid reference.');}
    catch(InvalidArgumentException){return 'unknown';}
    return $id===$matched?'resolved':'unknown';
}
