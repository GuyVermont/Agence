ALTER TABLE llx_sof_webhook_endpoint ADD UNIQUE INDEX uk_sof_webhook_endpoint_ref (entity, ref);
ALTER TABLE llx_sof_webhook_endpoint ADD INDEX idx_sof_webhook_endpoint_status (entity, status);
ALTER TABLE llx_sof_webhook_endpoint ADD CONSTRAINT fk_sof_webhook_endpoint_agence FOREIGN KEY (fk_agence) REFERENCES llx_sof_agence(rowid);

ALTER TABLE llx_sof_webhook_delivery ADD UNIQUE INDEX uk_sof_webhook_delivery_endpoint_event (entity, fk_endpoint, event_id);
ALTER TABLE llx_sof_webhook_delivery ADD INDEX idx_sof_webhook_delivery_queue (entity, status, next_attempt);
ALTER TABLE llx_sof_webhook_delivery ADD INDEX idx_sof_webhook_delivery_event (entity, event_code, date_creation);
ALTER TABLE llx_sof_webhook_delivery ADD CONSTRAINT fk_sof_webhook_delivery_endpoint FOREIGN KEY (fk_endpoint) REFERENCES llx_sof_webhook_endpoint(rowid);
ALTER TABLE llx_sof_webhook_delivery ADD CONSTRAINT fk_sof_webhook_delivery_agence FOREIGN KEY (fk_agence) REFERENCES llx_sof_agence(rowid);

ALTER TABLE llx_sof_integration_connector ADD UNIQUE INDEX uk_sof_integration_connector_ref (entity, ref);
ALTER TABLE llx_sof_integration_connector ADD INDEX idx_sof_integration_connector_due (entity, status, date_next_sync);
ALTER TABLE llx_sof_integration_connector ADD CONSTRAINT fk_sof_integration_connector_agence FOREIGN KEY (fk_agence) REFERENCES llx_sof_agence(rowid);

ALTER TABLE llx_sof_integration_sync ADD UNIQUE INDEX uk_sof_integration_sync_ref (entity, ref);
ALTER TABLE llx_sof_integration_sync ADD INDEX idx_sof_integration_sync_connector (entity, fk_connector, date_start);
ALTER TABLE llx_sof_integration_sync ADD CONSTRAINT fk_sof_integration_sync_connector FOREIGN KEY (fk_connector) REFERENCES llx_sof_integration_connector(rowid);

ALTER TABLE llx_sof_config_transfer ADD UNIQUE INDEX uk_sof_config_transfer_ref (entity, ref);
ALTER TABLE llx_sof_config_transfer ADD INDEX idx_sof_config_transfer_date (entity, date_creation);
