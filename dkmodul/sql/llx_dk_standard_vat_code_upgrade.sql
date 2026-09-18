ALTER TABLE llx_dk_standard_vat_code
    ADD COLUMN IF NOT EXISTS new_tax_code VARCHAR(32) NULL AFTER tax_code,
    ADD COLUMN IF NOT EXISTS legacy_label_code VARCHAR(128) NULL AFTER new_tax_code,
    ADD COLUMN IF NOT EXISTS new_label_code VARCHAR(128) NULL AFTER legacy_label_code,
    ADD COLUMN IF NOT EXISTS tax_group VARCHAR(128) NULL AFTER new_label_code,
    ADD COLUMN IF NOT EXISTS tax_type VARCHAR(64) NULL AFTER tax_group,
    ADD COLUMN IF NOT EXISTS reporting_box VARCHAR(500) NULL AFTER tax_type,
    ADD COLUMN IF NOT EXISTS deduction_right VARCHAR(128) NULL AFTER reporting_box,
    ADD COLUMN IF NOT EXISTS guidance TEXT NULL AFTER deduction_right;
