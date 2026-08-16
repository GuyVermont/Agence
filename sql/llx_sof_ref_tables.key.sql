ALTER TABLE llx_sof_das ADD UNIQUE INDEX uk_sof_das_ref_entity (ref, entity);
ALTER TABLE llx_sof_das ADD INDEX idx_sof_das_entity (entity);
ALTER TABLE llx_sof_das ADD INDEX idx_sof_das_status (status);

ALTER TABLE llx_sof_agence_user ADD UNIQUE INDEX uk_sof_agence_user_scope (fk_agence, fk_user, role_code, fk_caisse, fk_das);
ALTER TABLE llx_sof_agence_user ADD INDEX idx_sof_agence_user_entity (entity);
ALTER TABLE llx_sof_agence_user ADD INDEX idx_sof_agence_user_agence (fk_agence);
ALTER TABLE llx_sof_agence_user ADD INDEX idx_sof_agence_user_user (fk_user);
ALTER TABLE llx_sof_agence_user ADD INDEX idx_sof_agence_user_role (role_code);
ALTER TABLE llx_sof_agence_user ADD INDEX idx_sof_agence_user_status (status);

ALTER TABLE llx_sof_role_transversal ADD INDEX idx_sof_role_transversal_entity (entity);
ALTER TABLE llx_sof_role_transversal ADD INDEX idx_sof_role_transversal_user (fk_user);
ALTER TABLE llx_sof_role_transversal ADD INDEX idx_sof_role_transversal_role (role_code);
ALTER TABLE llx_sof_role_transversal ADD INDEX idx_sof_role_transversal_status (status);

ALTER TABLE llx_sof_parametre ADD UNIQUE INDEX uk_sof_parametre_code_scope (code, entity, scope_type, scope_id);
ALTER TABLE llx_sof_parametre ADD INDEX idx_sof_parametre_entity (entity);
ALTER TABLE llx_sof_parametre ADD INDEX idx_sof_parametre_scope (scope_type, scope_id);
