ALTER TABLE llx_sof_caisse_session ADD UNIQUE INDEX uk_sof_caisse_session_ref_entity (ref, entity);
ALTER TABLE llx_sof_caisse_session ADD INDEX idx_sof_caisse_session_entity (entity);
ALTER TABLE llx_sof_caisse_session ADD INDEX idx_sof_caisse_session_agence (fk_agence);
ALTER TABLE llx_sof_caisse_session ADD INDEX idx_sof_caisse_session_caisse (fk_caisse);
ALTER TABLE llx_sof_caisse_session ADD INDEX idx_sof_caisse_session_das (fk_das);
ALTER TABLE llx_sof_caisse_session ADD INDEX idx_sof_caisse_session_cashier (fk_user_cashier);
ALTER TABLE llx_sof_caisse_session ADD INDEX idx_sof_caisse_session_status (status);
ALTER TABLE llx_sof_caisse_session ADD INDEX idx_sof_caisse_session_accounting_status (accounting_status);
