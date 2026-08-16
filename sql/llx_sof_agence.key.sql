ALTER TABLE llx_sof_agence ADD UNIQUE INDEX uk_sof_agence_ref_entity (ref, entity);
ALTER TABLE llx_sof_agence ADD INDEX idx_sof_agence_entity (entity);
ALTER TABLE llx_sof_agence ADD INDEX idx_sof_agence_status (status);
ALTER TABLE llx_sof_agence ADD INDEX idx_sof_agence_responsible (fk_user_responsible);
ALTER TABLE llx_sof_agence ADD INDEX idx_sof_agence_cash_chief (fk_user_cash_chief);
