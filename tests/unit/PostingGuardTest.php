<?php

require_once __DIR__.'/../../dkmodul/class/Compliance/PostingGuard.php';

$guard = new DkPostingGuard();

assert($guard->isLockedValue(null) === false);
assert($guard->isLockedValue('') === false);
assert($guard->isLockedValue('2026-09-18 10:00:00') === true);
assert($guard->assertMutationAllowed(null, 'modify') === true);

$blocked = false;
try {
    $guard->assertMutationAllowed('2026-09-18 10:00:00', 'modify');
} catch (RuntimeException $e) {
    $blocked = true;
}
assert($blocked === true);

$blocked = false;
try {
    $guard->assertMutationAllowed('2026-09-18 10:00:00', 'delete');
} catch (RuntimeException $e) {
    $blocked = true;
}
assert($blocked === true);

echo "PostingGuard tests passed\n";
