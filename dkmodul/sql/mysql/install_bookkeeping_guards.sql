DROP TRIGGER IF EXISTS llx_dk_bookkeeping_lock_bu;
DROP TRIGGER IF EXISTS llx_dk_bookkeeping_lock_bd;

DELIMITER $$

CREATE TRIGGER llx_dk_bookkeeping_lock_bu
BEFORE UPDATE ON llx_accounting_bookkeeping
FOR EACH ROW
BEGIN
    IF OLD.date_validated IS NOT NULL AND (
        NOT (NEW.entity <=> OLD.entity)
        OR NOT (NEW.ref <=> OLD.ref)
        OR NOT (NEW.piece_num <=> OLD.piece_num)
        OR NOT (NEW.doc_date <=> OLD.doc_date)
        OR NOT (NEW.doc_type <=> OLD.doc_type)
        OR NOT (NEW.doc_ref <=> OLD.doc_ref)
        OR NOT (NEW.fk_doc <=> OLD.fk_doc)
        OR NOT (NEW.fk_docdet <=> OLD.fk_docdet)
        OR NOT (NEW.thirdparty_code <=> OLD.thirdparty_code)
        OR NOT (NEW.subledger_account <=> OLD.subledger_account)
        OR NOT (NEW.subledger_label <=> OLD.subledger_label)
        OR NOT (NEW.numero_compte <=> OLD.numero_compte)
        OR NOT (NEW.label_compte <=> OLD.label_compte)
        OR NOT (NEW.label_operation <=> OLD.label_operation)
        OR NOT (NEW.debit <=> OLD.debit)
        OR NOT (NEW.credit <=> OLD.credit)
        OR NOT (NEW.montant <=> OLD.montant)
        OR NOT (NEW.sens <=> OLD.sens)
        OR NOT (NEW.multicurrency_amount <=> OLD.multicurrency_amount)
        OR NOT (NEW.multicurrency_code <=> OLD.multicurrency_code)
        OR NOT (NEW.matching_general <=> OLD.matching_general)
        OR NOT (NEW.lettering_code <=> OLD.lettering_code)
        OR NOT (NEW.date_lettering <=> OLD.date_lettering)
        OR NOT (NEW.date_lim_reglement <=> OLD.date_lim_reglement)
        OR NOT (NEW.fk_user_author <=> OLD.fk_user_author)
        OR NOT (NEW.fk_user_modif <=> OLD.fk_user_modif)
        OR NOT (NEW.date_creation <=> OLD.date_creation)
        OR NOT (NEW.fk_user <=> OLD.fk_user)
        OR NOT (NEW.code_journal <=> OLD.code_journal)
        OR NOT (NEW.journal_label <=> OLD.journal_label)
        OR NOT (NEW.date_validated <=> OLD.date_validated)
        OR NOT (NEW.import_key <=> OLD.import_key)
        OR NOT (NEW.extraparams <=> OLD.extraparams)
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'DK compliance: validated bookkeeping entries are immutable';
    END IF;
END$$

CREATE TRIGGER llx_dk_bookkeeping_lock_bd
BEFORE DELETE ON llx_accounting_bookkeeping
FOR EACH ROW
BEGIN
    IF OLD.date_validated IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'DK compliance: validated bookkeeping entries cannot be deleted';
    END IF;
END$$

DELIMITER ;
