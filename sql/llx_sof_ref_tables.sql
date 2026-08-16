-- Reference and perimeter tables for the Agence module.

CREATE TABLE llx_sof_das (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  ref varchar(64) NOT NULL,
  label varchar(255) NOT NULL,
  description text,
  accountancy_code varchar(64),
  analytic_code varchar(128),
  validation_rules text,
  refund_rules text,
  credit_note_rules text,
  allowed_payment_modes text,
  required_documents text,
  dashboard_config text,
  status integer DEFAULT 1 NOT NULL,
  date_creation datetime NOT NULL,
  tms timestamp,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer,
  import_key varchar(14)
);

CREATE TABLE llx_sof_agence_user (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  fk_agence integer NOT NULL,
  fk_user integer NOT NULL,
  fk_caisse integer,
  fk_das integer,
  role_code varchar(64) NOT NULL,
  scope_type varchar(64) DEFAULT 'agency',
  scope_value varchar(255),
  validation_limit double(24,8) DEFAULT 0,
  is_default integer DEFAULT 0,
  is_substitute integer DEFAULT 0,
  date_start datetime,
  date_end datetime,
  status integer DEFAULT 1 NOT NULL,
  date_creation datetime NOT NULL,
  tms timestamp,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer,
  import_key varchar(14)
);

CREATE TABLE llx_sof_role_transversal (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  fk_user integer NOT NULL,
  role_code varchar(64) NOT NULL,
  scope_type varchar(64) NOT NULL,
  scope_value text,
  allowed_operation_types text,
  financial_threshold double(24,8) DEFAULT 0,
  date_start datetime,
  date_end datetime,
  status integer DEFAULT 1 NOT NULL,
  date_creation datetime NOT NULL,
  tms timestamp,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer,
  import_key varchar(14)
);

CREATE TABLE llx_sof_parametre (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  code varchar(128) NOT NULL,
  label varchar(255),
  value_text text,
  value_number double(24,8),
  scope_type varchar(64),
  scope_id integer,
  status integer DEFAULT 1 NOT NULL,
  date_creation datetime NOT NULL,
  tms timestamp,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer,
  import_key varchar(14)
);
