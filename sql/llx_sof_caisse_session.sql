-- Session de caisse SOFITOUL.
-- All real payments remain Dolibarr payments; this table carries agency/cash/session context.

CREATE TABLE llx_sof_caisse_session (
  rowid integer AUTO_INCREMENT PRIMARY KEY,
  entity integer DEFAULT 1 NOT NULL,
  ref varchar(64) NOT NULL,
  fk_agence integer NOT NULL,
  fk_caisse integer NOT NULL,
  fk_das integer,
  fk_user_cashier integer NOT NULL,
  session_type varchar(64) NOT NULL,
  date_opening datetime NOT NULL,
  date_closing datetime,
  date_validation datetime,
  opening_amount double(24,8) DEFAULT 0,
  theoretical_amount double(24,8) DEFAULT 0,
  physical_amount double(24,8) DEFAULT 0,
  gap_amount double(24,8) DEFAULT 0,
  accounting_status integer DEFAULT 0 NOT NULL,
  freeze_status integer DEFAULT 0 NOT NULL,
  status integer DEFAULT 0 NOT NULL,
  fk_user_validator integer,
  report_ref varchar(255),
  reopening_reason text,
  note_public text,
  note_private text,
  date_creation datetime NOT NULL,
  tms timestamp,
  fk_user_creat integer NOT NULL,
  fk_user_modif integer,
  import_key varchar(14)
);
