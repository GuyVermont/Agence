ALTER TABLE llx_sof_bon_commande_client ADD UNIQUE INDEX uk_sof_bc_ref_entity (ref, entity);
ALTER TABLE llx_sof_bon_commande_client ADD UNIQUE INDEX uk_sof_bc_number_soc_entity (order_number, fk_soc, entity);
ALTER TABLE llx_sof_bon_commande_client ADD INDEX idx_sof_bc_soc (fk_soc);
ALTER TABLE llx_sof_bon_commande_client ADD INDEX idx_sof_bc_agence (fk_agence);
ALTER TABLE llx_sof_bon_commande_client ADD INDEX idx_sof_bc_facture (fk_facture);
ALTER TABLE llx_sof_bon_commande_client ADD INDEX idx_sof_bc_status (status);

ALTER TABLE llx_sof_bst ADD UNIQUE INDEX uk_sof_bst_ref_entity (ref, entity);
ALTER TABLE llx_sof_bst ADD UNIQUE INDEX uk_sof_bst_number_entity (bst_number, entity);
ALTER TABLE llx_sof_bst ADD INDEX idx_sof_bst_soc_payer (fk_soc_payer);
ALTER TABLE llx_sof_bst ADD INDEX idx_sof_bst_agence (fk_agence);
ALTER TABLE llx_sof_bst ADD INDEX idx_sof_bst_facture (fk_facture);
ALTER TABLE llx_sof_bst ADD INDEX idx_sof_bst_status (status);

ALTER TABLE llx_sof_instruction_manageriale ADD UNIQUE INDEX uk_sof_instruction_ref_entity (instruction_ref, entity);
ALTER TABLE llx_sof_instruction_manageriale ADD INDEX idx_sof_instruction_soc (fk_soc);
ALTER TABLE llx_sof_instruction_manageriale ADD INDEX idx_sof_instruction_agence (fk_agence);
ALTER TABLE llx_sof_instruction_manageriale ADD INDEX idx_sof_instruction_facture (fk_facture);
ALTER TABLE llx_sof_instruction_manageriale ADD INDEX idx_sof_instruction_status (status);

ALTER TABLE llx_sof_paiement_differe ADD UNIQUE INDEX uk_sof_differe_ref_entity (ref, entity);
ALTER TABLE llx_sof_paiement_differe ADD INDEX idx_sof_differe_soc (fk_soc);
ALTER TABLE llx_sof_paiement_differe ADD INDEX idx_sof_differe_agence (fk_agence);
ALTER TABLE llx_sof_paiement_differe ADD INDEX idx_sof_differe_session (fk_session);
ALTER TABLE llx_sof_paiement_differe ADD INDEX idx_sof_differe_facture (fk_facture);
ALTER TABLE llx_sof_paiement_differe ADD INDEX idx_sof_differe_source (source_type, source_id);
ALTER TABLE llx_sof_paiement_differe ADD INDEX idx_sof_differe_status (status);
