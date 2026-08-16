ALTER TABLE llx_sof_caisse_auditlog ADD INDEX idx_sof_auditlog_entity (entity);
ALTER TABLE llx_sof_caisse_auditlog ADD INDEX idx_sof_auditlog_event_date (event_date);
ALTER TABLE llx_sof_caisse_auditlog ADD INDEX idx_sof_auditlog_user (fk_user);
ALTER TABLE llx_sof_caisse_auditlog ADD INDEX idx_sof_auditlog_agence (fk_agence);
ALTER TABLE llx_sof_caisse_auditlog ADD INDEX idx_sof_auditlog_session (fk_session);
ALTER TABLE llx_sof_caisse_auditlog ADD INDEX idx_sof_auditlog_action (action_code);
ALTER TABLE llx_sof_caisse_auditlog ADD INDEX idx_sof_auditlog_object (object_type, object_id);
