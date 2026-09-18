<?php

/**
 * Guard for compliance-critical accounting mutations.
 *
 * Dolibarr considers bookkeeping rows with date_validated/date_validation set
 * validated and locked. The guard deliberately treats any non-empty validation
 * timestamp as immutable.
 */
class DkPostingGuard
{
    public function isLockedValue($dateValidated)
    {
        return !empty($dateValidated);
    }

    public function isBookkeepingLocked($db, $bookkeepingId)
    {
        $sql = 'SELECT date_validated';
        $sql .= ' FROM '.$db->prefix().'accounting_bookkeeping';
        $sql .= ' WHERE rowid = '.((int) $bookkeepingId);

        $resql = $db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to determine bookkeeping lock state: '.$db->lasterror());
        }

        $obj = $db->fetch_object($resql);
        if (!$obj) {
            throw new RuntimeException('Bookkeeping row not found');
        }

        return $this->isLockedValue($obj->date_validated);
    }

    public function assertMutationAllowed($dateValidated, $operation)
    {
        if ($this->isLockedValue($dateValidated)) {
            throw new RuntimeException('DK compliance mode forbids '.$operation.' of a validated bookkeeping entry');
        }

        return true;
    }
}
