<?php

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/dkmodul/class/Compliance/PostingGuard.php');
dol_include_once('/dkmodul/class/Compliance/ComplianceLock.php');
dol_include_once('/dkmodul/class/Audit/AuditLedger.php');

class InterfaceDkmodulTriggers extends DolibarrTriggers
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->family = 'financial';
        $this->description = 'Dolibarr DK compliance triggers';
        $this->version = self::VERSIONS['dev'];
        $this->picto = 'accounting';
    }

    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (!in_array($action, array('BOOKKEEPING_CREATE', 'BOOKKEEPING_MODIFY', 'BOOKKEEPING_DELETE'), true)) {
            return 0;
        }

        try {
            $registeredProfile = (bool) getDolGlobalInt('DKMODUL_REGISTERED_PROFILE');
            $decision = DkComplianceLock::triggerDecision(
                isModEnabled('dkmodul'),
                $registeredProfile,
                (bool) getDolGlobalInt('DKMODUL_COMPLIANCE_MODE')
            );
            DkComplianceLock::assertDeploymentReady(
                dirname(__DIR__, 2).'/product-manifest.json',
                getDolGlobalString('DKMODUL_DEPLOYMENT_ATTESTATION_PATH'),
                $registeredProfile,
                getDolGlobalString('DKMODUL_ATTESTATION_TRUST_STORE_PATH')
            );
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }

        if ($decision !== 'enforce') {
            return 0;
        }

        $guard = new DkPostingGuard();
        $ledger = new DkAuditLedger($this->db);
        $objectId = isset($object->id) ? (int) $object->id : 0;

        try {
            if ($action === 'BOOKKEEPING_MODIFY' || $action === 'BOOKKEEPING_DELETE') {
                if ($guard->isBookkeepingLocked($this->db, $objectId)) {
                    // Returning < 0 makes Dolibarr roll back the surrounding bookkeeping transaction.
                    $this->error = 'DK compliance: validated bookkeeping entries are immutable';
                    return -1;
                }
            }

            if ($action === 'BOOKKEEPING_CREATE') {
                $ledger->append(
                    (int) $conf->entity,
                    'bookkeeping.created',
                    'accounting_bookkeeping',
                    $objectId,
                    (int) $user->id,
                    array(
                        'piece_num' => isset($object->piece_num) ? $object->piece_num : null,
                        'doc_type' => isset($object->doc_type) ? $object->doc_type : null,
                        'doc_ref' => isset($object->doc_ref) ? $object->doc_ref : null,
                        'account' => isset($object->numero_compte) ? $object->numero_compte : null,
                        'debit' => isset($object->debit) ? $object->debit : null,
                        'credit' => isset($object->credit) ? $object->credit : null,
                    )
                );
            }
        } catch (Throwable $e) {
            $this->error = 'DK compliance trigger failed: '.$e->getMessage();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }

        return 0;
    }
}
