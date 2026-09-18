DROP TABLE IF EXISTS llx_accounting_bookkeeping;

CREATE TABLE llx_accounting_bookkeeping (
    rowid BIGINT AUTO_INCREMENT PRIMARY KEY,
    doc_date DATE NULL,
    doc_type VARCHAR(32) NULL,
    doc_ref VARCHAR(300) NULL,
    fk_doc BIGINT NULL,
    fk_docdet BIGINT NULL,
    thirdparty_code VARCHAR(64) NULL,
    subledger_account VARCHAR(64) NULL,
    subledger_label VARCHAR(255) NULL,
    numero_compte VARCHAR(64) NULL,
    label_compte VARCHAR(255) NULL,
    label_operation VARCHAR(255) NULL,
    debit DECIMAL(24,8) DEFAULT 0,
    credit DECIMAL(24,8) DEFAULT 0,
    montant DECIMAL(24,8) DEFAULT 0,
    sens CHAR(1) NULL,
    code_journal VARCHAR(32) NULL,
    journal_label VARCHAR(255) NULL,
    piece_num BIGINT NULL,
    ref VARCHAR(30) NULL,
    date_export DATETIME NULL,
    date_validated DATETIME NULL,
    lettering_code VARCHAR(32) NULL
);

DELIMITER //

CREATE TRIGGER dkmodul_bookkeeping_immutable_update
BEFORE UPDATE ON llx_accounting_bookkeeping
FOR EACH ROW
BEGIN
    IF OLD.date_validated IS NOT NULL AND (
        NOT (OLD.doc_date <=> NEW.doc_date) OR
        NOT (OLD.doc_type <=> NEW.doc_type) OR
        NOT (OLD.doc_ref <=> NEW.doc_ref) OR
        NOT (OLD.fk_doc <=> NEW.fk_doc) OR
        NOT (OLD.fk_docdet <=> NEW.fk_docdet) OR
        NOT (OLD.thirdparty_code <=> NEW.thirdparty_code) OR
        NOT (OLD.subledger_account <=> NEW.subledger_account) OR
        NOT (OLD.subledger_label <=> NEW.subledger_label) OR
        NOT (OLD.numero_compte <=> NEW.numero_compte) OR
        NOT (OLD.label_compte <=> NEW.label_compte) OR
        NOT (OLD.label_operation <=> NEW.label_operation) OR
        NOT (OLD.debit <=> NEW.debit) OR
        NOT (OLD.credit <=> NEW.credit) OR
        NOT (OLD.montant <=> NEW.montant) OR
        NOT (OLD.sens <=> NEW.sens) OR
        NOT (OLD.code_journal <=> NEW.code_journal) OR
        NOT (OLD.journal_label <=> NEW.journal_label) OR
        NOT (OLD.piece_num <=> NEW.piece_num) OR
        NOT (OLD.ref <=> NEW.ref)
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: validated bookkeeping financial fields are immutable';
    END IF;
END//

CREATE TRIGGER dkmodul_bookkeeping_immutable_delete
BEFORE DELETE ON llx_accounting_bookkeeping
FOR EACH ROW
BEGIN
    IF OLD.date_validated IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DK compliance: validated bookkeeping entries cannot be deleted';
    END IF;
END//

DELIMITER ;

INSERT INTO llx_accounting_bookkeeping
(doc_date, doc_type, doc_ref, numero_compte, debit, credit, montant, sens, code_journal, piece_num, ref)
VALUES
('2026-09-18', 'customer_invoice', 'INV-1', '1000', 100.00, 0, 100.00, 'D', 'VT', 1, 'DK-1');

UPDATE llx_accounting_bookkeeping SET debit=110.00, montant=110.00 WHERE rowid=1;
UPDATE llx_accounting_bookkeeping SET date_validated=NOW() WHERE rowid=1;
UPDATE llx_accounting_bookkeeping SET date_export=NOW(), lettering_code='A1' WHERE rowid=1;
