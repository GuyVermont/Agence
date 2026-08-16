ALTER TABLE llx_sof_facture_link ADD UNIQUE INDEX uk_sof_facture_link_facture (fk_facture);
ALTER TABLE llx_sof_facture_link ADD INDEX idx_sof_facture_link_soc (fk_soc);
ALTER TABLE llx_sof_facture_link ADD INDEX idx_sof_facture_link_agence (fk_agence);
ALTER TABLE llx_sof_facture_link ADD INDEX idx_sof_facture_link_session (fk_session);
ALTER TABLE llx_sof_facture_link ADD INDEX idx_sof_facture_link_source (source_type, source_id);

ALTER TABLE llx_sof_paiement_link ADD INDEX idx_sof_paiement_link_paiement (fk_paiement);
ALTER TABLE llx_sof_paiement_link ADD UNIQUE INDEX uk_sof_paiement_link_payment_invoice (fk_paiement, fk_facture);
ALTER TABLE llx_sof_paiement_link ADD INDEX idx_sof_paiement_link_facture (fk_facture);
ALTER TABLE llx_sof_paiement_link ADD INDEX idx_sof_paiement_link_bank (fk_bank);
ALTER TABLE llx_sof_paiement_link ADD INDEX idx_sof_paiement_link_agence (fk_agence);
ALTER TABLE llx_sof_paiement_link ADD INDEX idx_sof_paiement_link_session (fk_session);
ALTER TABLE llx_sof_paiement_link ADD INDEX idx_sof_paiement_link_status (status);

ALTER TABLE llx_sof_commande_link ADD UNIQUE INDEX uk_sof_commande_link_commande (fk_commande);
ALTER TABLE llx_sof_commande_link ADD INDEX idx_sof_commande_link_soc (fk_soc);
ALTER TABLE llx_sof_commande_link ADD INDEX idx_sof_commande_link_agence (fk_agence);
ALTER TABLE llx_sof_commande_link ADD INDEX idx_sof_commande_link_source (source_type, source_id);

ALTER TABLE llx_sof_takepos_link ADD INDEX idx_sof_takepos_link_terminal (terminal_ref);
ALTER TABLE llx_sof_takepos_link ADD INDEX idx_sof_takepos_link_agence (fk_agence);
ALTER TABLE llx_sof_takepos_link ADD INDEX idx_sof_takepos_link_caisse (fk_caisse);
ALTER TABLE llx_sof_takepos_link ADD INDEX idx_sof_takepos_link_session (fk_session);
ALTER TABLE llx_sof_takepos_link ADD INDEX idx_sof_takepos_link_facture (fk_facture);

ALTER TABLE llx_sof_avoir_tracking ADD UNIQUE INDEX uk_sof_avoir_tracking_ref_entity (ref, entity);
ALTER TABLE llx_sof_avoir_tracking ADD UNIQUE INDEX uk_sof_avoir_tracking_facture (fk_facture_avoir);
ALTER TABLE llx_sof_avoir_tracking ADD INDEX idx_sof_avoir_tracking_soc (fk_soc);
ALTER TABLE llx_sof_avoir_tracking ADD INDEX idx_sof_avoir_tracking_origin (fk_facture_origin);
ALTER TABLE llx_sof_avoir_tracking ADD INDEX idx_sof_avoir_tracking_agence (fk_agence);
ALTER TABLE llx_sof_avoir_tracking ADD INDEX idx_sof_avoir_tracking_status (status);

ALTER TABLE llx_sof_bank_link ADD INDEX idx_sof_bank_link_bank (fk_bank);
ALTER TABLE llx_sof_bank_link ADD INDEX idx_sof_bank_link_bank_account (fk_bank_account);
ALTER TABLE llx_sof_bank_link ADD INDEX idx_sof_bank_link_agence (fk_agence);
ALTER TABLE llx_sof_bank_link ADD INDEX idx_sof_bank_link_session (fk_session);
ALTER TABLE llx_sof_bank_link ADD INDEX idx_sof_bank_link_depot (fk_depot_banque);

ALTER TABLE llx_sof_product_das ADD UNIQUE INDEX uk_sof_product_das_scope (fk_product, fk_das, fk_agence, entity);
ALTER TABLE llx_sof_product_das ADD INDEX idx_sof_product_das_product (fk_product);
ALTER TABLE llx_sof_product_das ADD INDEX idx_sof_product_das_das (fk_das);
ALTER TABLE llx_sof_product_das ADD INDEX idx_sof_product_das_agence (fk_agence);
ALTER TABLE llx_sof_product_das ADD INDEX idx_sof_product_das_status (status);

ALTER TABLE llx_sof_tiers_credit_profile ADD UNIQUE INDEX uk_sof_tiers_credit_profile_soc_entity (fk_soc, entity);
ALTER TABLE llx_sof_tiers_credit_profile ADD INDEX idx_sof_tiers_credit_profile_agence (fk_agence_followup);
ALTER TABLE llx_sof_tiers_credit_profile ADD INDEX idx_sof_tiers_credit_profile_risk (risk_status);
ALTER TABLE llx_sof_tiers_credit_profile ADD INDEX idx_sof_tiers_credit_profile_status (status);
