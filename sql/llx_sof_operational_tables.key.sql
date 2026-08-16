ALTER TABLE llx_sof_caisse_mouvement ADD UNIQUE INDEX uk_sof_mouvement_ref_entity (ref, entity);
ALTER TABLE llx_sof_caisse_mouvement ADD INDEX idx_sof_mouvement_session (fk_session);
ALTER TABLE llx_sof_caisse_mouvement ADD INDEX idx_sof_mouvement_caisse (fk_caisse);
ALTER TABLE llx_sof_caisse_mouvement ADD INDEX idx_sof_mouvement_facture (fk_facture);
ALTER TABLE llx_sof_caisse_mouvement ADD INDEX idx_sof_mouvement_paiement (fk_paiement);
ALTER TABLE llx_sof_caisse_mouvement ADD INDEX idx_sof_mouvement_date (transaction_date);
ALTER TABLE llx_sof_caisse_mouvement ADD INDEX idx_sof_mouvement_status (status);

ALTER TABLE llx_sof_remboursement ADD UNIQUE INDEX uk_sof_remboursement_ref_entity (ref, entity);
ALTER TABLE llx_sof_remboursement ADD INDEX idx_sof_remboursement_soc (fk_soc);
ALTER TABLE llx_sof_remboursement ADD INDEX idx_sof_remboursement_origin (fk_facture_origin);
ALTER TABLE llx_sof_remboursement ADD INDEX idx_sof_remboursement_session (fk_session);
ALTER TABLE llx_sof_remboursement ADD INDEX idx_sof_remboursement_status (status);
