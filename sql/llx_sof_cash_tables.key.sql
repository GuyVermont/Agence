ALTER TABLE llx_sof_caisse_cloture ADD UNIQUE INDEX uk_sof_caisse_cloture_ref_entity (ref, entity);
ALTER TABLE llx_sof_caisse_cloture ADD INDEX idx_sof_caisse_cloture_session (fk_session);
ALTER TABLE llx_sof_caisse_cloture ADD INDEX idx_sof_caisse_cloture_agence (fk_agence);
ALTER TABLE llx_sof_caisse_cloture ADD INDEX idx_sof_caisse_cloture_caisse (fk_caisse);
ALTER TABLE llx_sof_caisse_cloture ADD INDEX idx_sof_caisse_cloture_status (status);

ALTER TABLE llx_sof_caisse_comptage ADD INDEX idx_sof_caisse_comptage_session (fk_session);
ALTER TABLE llx_sof_caisse_comptage ADD INDEX idx_sof_caisse_comptage_cloture (fk_cloture);
ALTER TABLE llx_sof_caisse_comptage ADD INDEX idx_sof_caisse_comptage_controle (fk_controle);

ALTER TABLE llx_sof_caisse_ecart ADD UNIQUE INDEX uk_sof_caisse_ecart_ref_entity (ref, entity);
ALTER TABLE llx_sof_caisse_ecart ADD INDEX idx_sof_caisse_ecart_session (fk_session);
ALTER TABLE llx_sof_caisse_ecart ADD INDEX idx_sof_caisse_ecart_agence (fk_agence);
ALTER TABLE llx_sof_caisse_ecart ADD INDEX idx_sof_caisse_ecart_caisse (fk_caisse);
ALTER TABLE llx_sof_caisse_ecart ADD INDEX idx_sof_caisse_ecart_status (status);

ALTER TABLE llx_sof_caisse_controle ADD UNIQUE INDEX uk_sof_caisse_controle_ref_entity (ref, entity);
ALTER TABLE llx_sof_caisse_controle ADD INDEX idx_sof_caisse_controle_agence (fk_agence);
ALTER TABLE llx_sof_caisse_controle ADD INDEX idx_sof_caisse_controle_caisse (fk_caisse);
ALTER TABLE llx_sof_caisse_controle ADD INDEX idx_sof_caisse_controle_session (fk_session);
ALTER TABLE llx_sof_caisse_controle ADD INDEX idx_sof_caisse_controle_controller (fk_user_controller);
ALTER TABLE llx_sof_caisse_controle ADD INDEX idx_sof_caisse_controle_status (status);

ALTER TABLE llx_sof_caisse_validation ADD INDEX idx_sof_caisse_validation_object (object_type, object_id);
ALTER TABLE llx_sof_caisse_validation ADD INDEX idx_sof_caisse_validation_workflow (workflow_code);
ALTER TABLE llx_sof_caisse_validation ADD INDEX idx_sof_caisse_validation_validator (fk_user_validator);
ALTER TABLE llx_sof_caisse_validation ADD INDEX idx_sof_caisse_validation_status (status);

ALTER TABLE llx_sof_caisse_transfert ADD UNIQUE INDEX uk_sof_caisse_transfert_ref_entity (ref, entity);
ALTER TABLE llx_sof_caisse_transfert ADD INDEX idx_sof_caisse_transfert_agence (fk_agence);
ALTER TABLE llx_sof_caisse_transfert ADD INDEX idx_sof_caisse_transfert_source (fk_caisse_source);
ALTER TABLE llx_sof_caisse_transfert ADD INDEX idx_sof_caisse_transfert_status (status);

ALTER TABLE llx_sof_caisse_depot_banque ADD UNIQUE INDEX uk_sof_caisse_depot_ref_entity (ref, entity);
ALTER TABLE llx_sof_caisse_depot_banque ADD INDEX idx_sof_caisse_depot_agence (fk_agence);
ALTER TABLE llx_sof_caisse_depot_banque ADD INDEX idx_sof_caisse_depot_caisse (fk_caisse_source);
ALTER TABLE llx_sof_caisse_depot_banque ADD INDEX idx_sof_caisse_depot_bank_account (fk_bank_account);
ALTER TABLE llx_sof_caisse_depot_banque ADD INDEX idx_sof_caisse_depot_bank (fk_bank);
ALTER TABLE llx_sof_caisse_depot_banque ADD INDEX idx_sof_caisse_depot_status (status);

ALTER TABLE llx_sof_caisse_workflow ADD UNIQUE INDEX uk_sof_caisse_workflow_code_entity (code, entity);
ALTER TABLE llx_sof_caisse_workflow ADD INDEX idx_sof_caisse_workflow_object_type (object_type);
ALTER TABLE llx_sof_caisse_workflow ADD INDEX idx_sof_caisse_workflow_status (status);

ALTER TABLE llx_sof_caisse_alerte ADD INDEX idx_sof_caisse_alerte_type (alert_type);
ALTER TABLE llx_sof_caisse_alerte ADD INDEX idx_sof_caisse_alerte_agence (fk_agence);
ALTER TABLE llx_sof_caisse_alerte ADD INDEX idx_sof_caisse_alerte_object (object_type, object_id);
ALTER TABLE llx_sof_caisse_alerte ADD INDEX idx_sof_caisse_alerte_status (status);

ALTER TABLE llx_sof_mapping_comptable ADD UNIQUE INDEX uk_sof_mapping_comptable_code_entity (code, entity);
ALTER TABLE llx_sof_mapping_comptable ADD INDEX idx_sof_mapping_comptable_operation (operation_type);
ALTER TABLE llx_sof_mapping_comptable ADD INDEX idx_sof_mapping_comptable_agence (fk_agence);
ALTER TABLE llx_sof_mapping_comptable ADD INDEX idx_sof_mapping_comptable_das (fk_das);
