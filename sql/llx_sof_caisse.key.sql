ALTER TABLE llx_sof_caisse ADD UNIQUE INDEX uk_sof_caisse_ref_entity (ref, entity);
ALTER TABLE llx_sof_caisse ADD INDEX idx_sof_caisse_entity (entity);
ALTER TABLE llx_sof_caisse ADD INDEX idx_sof_caisse_agence (fk_agence);
ALTER TABLE llx_sof_caisse ADD INDEX idx_sof_caisse_bank_account (fk_bank_account);
ALTER TABLE llx_sof_caisse ADD INDEX idx_sof_caisse_bank_card (fk_bank_account_card);
ALTER TABLE llx_sof_caisse ADD INDEX idx_sof_caisse_bank_cheque (fk_bank_account_cheque);
ALTER TABLE llx_sof_caisse ADD INDEX idx_sof_caisse_bank_mobile (fk_bank_account_mobile);
ALTER TABLE llx_sof_caisse ADD INDEX idx_sof_caisse_status (status);
ALTER TABLE llx_sof_caisse ADD INDEX idx_sof_caisse_main_cashier (fk_user_main_cashier);
