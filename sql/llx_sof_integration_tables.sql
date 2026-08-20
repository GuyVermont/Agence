-- PowerERP / Agence integration layer (webhooks, connectors and configuration transport).

CREATE TABLE llx_sof_webhook_endpoint (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  ref varchar(64) NOT NULL,
  label varchar(255) NOT NULL,
  endpoint_url varchar(1024) NOT NULL,
  event_filter varchar(1024) NOT NULL,
  fk_agence integer,
  secret_encrypted text NOT NULL,
  max_attempts integer DEFAULT 8 NOT NULL,
  status integer DEFAULT 1 NOT NULL,
  date_creation datetime NOT NULL,
  tms timestamp,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer
);

CREATE TABLE llx_sof_webhook_delivery (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  delivery_ref varchar(64) NOT NULL,
  event_id varchar(64) NOT NULL,
  event_code varchar(128) NOT NULL,
  fk_endpoint integer NOT NULL,
  fk_agence integer,
  object_type varchar(128) NOT NULL,
  object_id integer NOT NULL,
  payload text NOT NULL,
  attempts integer DEFAULT 0 NOT NULL,
  next_attempt datetime,
  date_sent datetime,
  http_status integer,
  response_excerpt text,
  last_error text,
  status integer DEFAULT 0 NOT NULL,
  date_creation datetime NOT NULL,
  tms timestamp
);

CREATE TABLE llx_sof_integration_connector (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  ref varchar(64) NOT NULL,
  label varchar(255) NOT NULL,
  connector_type varchar(64) NOT NULL,
  endpoint_url varchar(1024) NOT NULL,
  auth_type varchar(32) DEFAULT 'bearer' NOT NULL,
  credential_encrypted text,
  fk_agence integer,
  fk_bank_account integer,
  polling_minutes integer DEFAULT 15 NOT NULL,
  remote_cursor varchar(1024),
  date_last_sync datetime,
  date_next_sync datetime,
  last_error text,
  status integer DEFAULT 1 NOT NULL,
  date_creation datetime NOT NULL,
  tms timestamp,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer
);

CREATE TABLE llx_sof_integration_sync (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  ref varchar(64) NOT NULL,
  fk_connector integer NOT NULL,
  direction varchar(16) DEFAULT 'pull' NOT NULL,
  remote_cursor_before varchar(1024),
  remote_cursor_after varchar(1024),
  imported_count integer DEFAULT 0 NOT NULL,
  rejected_count integer DEFAULT 0 NOT NULL,
  response_checksum varchar(64),
  error_message text,
  date_start datetime NOT NULL,
  date_end datetime,
  status integer DEFAULT 0 NOT NULL,
  date_creation datetime NOT NULL,
  fk_user_creat integer
);

CREATE TABLE llx_sof_config_transfer (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  ref varchar(64) NOT NULL,
  direction varchar(16) NOT NULL,
  source_environment varchar(32),
  target_environment varchar(32),
  package_version varchar(32) NOT NULL,
  package_checksum varchar(64) NOT NULL,
  dry_run integer DEFAULT 1 NOT NULL,
  summary_json text,
  status integer DEFAULT 0 NOT NULL,
  date_creation datetime NOT NULL,
  fk_user_creat integer NOT NULL
);
