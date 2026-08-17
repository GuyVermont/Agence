-- Audit trail SOFITOUL.
-- Append-only from business UI; sensitive operations must not be physically deleted.

CREATE TABLE llx_sof_caisse_auditlog (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  fk_user integer,
  user_role varchar(128),
  fk_agence integer,
  fk_caisse integer,
  fk_session integer,
  action_code varchar(128) NOT NULL,
  object_type varchar(128) NOT NULL,
  object_id integer,
  event_date datetime NOT NULL,
  ip_address varchar(64),
  terminal varchar(128),
  old_value text,
  new_value text,
  reason text,
  attachment_ref varchar(255),
  archive_status integer DEFAULT 0 NOT NULL,
  date_archive datetime,
  purge_after datetime,
  status integer DEFAULT 1 NOT NULL,
  date_creation datetime NOT NULL,
  import_key varchar(14)
);
