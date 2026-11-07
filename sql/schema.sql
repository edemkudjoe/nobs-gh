-- National Organ & Blood Bank System: PostgreSQL schema (Supabase)
-- 20 tables = the 19 from the design document + sessions (serverless has no session files).
-- Plain SQL on purpose: only types and AUTO_INCREMENT-style lines would change for MySQL.

DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;

-- ---------- lookups ----------
CREATE TABLE regions (
  id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name  VARCHAR(60) NOT NULL UNIQUE
);

CREATE TABLE region_travel_times (
  from_region_id INT NOT NULL REFERENCES regions(id),
  to_region_id   INT NOT NULL REFERENCES regions(id),
  hours          NUMERIC(4,1) NOT NULL CHECK (hours > 0),
  PRIMARY KEY (from_region_id, to_region_id),
  CHECK (from_region_id < to_region_id)   -- one row per pair, same both ways. Same-region time is a setting
);

CREATE TABLE blood_groups (
  id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  label VARCHAR(3) NOT NULL UNIQUE
);

CREATE TABLE blood_compatibility (
  donor_group_id     INT NOT NULL REFERENCES blood_groups(id),
  recipient_group_id INT NOT NULL REFERENCES blood_groups(id),
  PRIMARY KEY (donor_group_id, recipient_group_id)
);

-- ---------- people and facilities ----------
CREATE TABLE facilities (
  id                  INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name                VARCHAR(120) NOT NULL,
  type                VARCHAR(12) NOT NULL CHECK (type IN ('hospital','blood_bank')),
  region_id           INT NOT NULL REFERENCES regions(id),
  license_no          VARCHAR(40),
  verification_status VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (verification_status IN ('pending','approved','rejected')),
  can_request_blood   BOOLEAN NOT NULL DEFAULT FALSE,
  can_report_donors   BOOLEAN NOT NULL DEFAULT FALSE,
  can_transplant      BOOLEAN NOT NULL DEFAULT FALSE,
  active              BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE users (
  id                   INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  full_name            VARCHAR(120) NOT NULL,
  email                VARCHAR(160) NOT NULL UNIQUE,
  password_hash        VARCHAR(255) NOT NULL,
  role                 VARCHAR(10) NOT NULL CHECK (role IN ('admin','center','hospital','donor')),
  facility_id          INT REFERENCES facilities(id),            -- null for admins and donors
  is_facility_admin    BOOLEAN NOT NULL DEFAULT FALSE,           -- can create staff for own facility
  must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
  status               VARCHAR(12) NOT NULL DEFAULT 'active' CHECK (status IN ('active','deactivated')),
  last_login_at        TIMESTAMPTZ,
  created_by           INT REFERENCES users(id),
  created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
  CHECK ((role IN ('center','hospital')) = (facility_id IS NOT NULL)),
  CHECK (NOT is_facility_admin OR role IN ('center','hospital'))
);

CREATE TABLE sessions (
  id         VARCHAR(128) PRIMARY KEY,
  user_id    INT REFERENCES users(id) ON DELETE CASCADE,
  data       TEXT NOT NULL DEFAULT '',
  expires_at TIMESTAMPTZ NOT NULL
);

CREATE TABLE donors (
  user_id                INT PRIMARY KEY REFERENCES users(id),
  blood_group_id         INT REFERENCES blood_groups(id),         -- null = "I do not know" until a center confirms
  date_of_birth          DATE NOT NULL,
  weight_kg              NUMERIC(5,1),
  region_id              INT NOT NULL REFERENCES regions(id),
  phone                  VARCHAR(30),
  last_donation_date     DATE,
  eligibility_status     VARCHAR(10) NOT NULL DEFAULT 'unchecked' CHECK (eligibility_status IN ('unchecked','passed','deferred')),
  eligibility_checked_at TIMESTAMPTZ,
  deferred_until         DATE
);

-- ---------- donations ----------
CREATE TABLE center_slots (
  id          INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  facility_id INT NOT NULL REFERENCES facilities(id),
  slot_date   DATE NOT NULL,
  capacity    INT NOT NULL CHECK (capacity >= 0),
  UNIQUE (facility_id, slot_date)
);

CREATE TABLE appointments (
  id              INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  donor_id        INT NOT NULL REFERENCES donors(user_id),
  facility_id     INT NOT NULL REFERENCES facilities(id),
  slot_id         INT NOT NULL REFERENCES center_slots(id),
  status          VARCHAR(10) NOT NULL DEFAULT 'scheduled' CHECK (status IN ('scheduled','completed','deferred','missed','cancelled')),
  deferral_reason VARCHAR(200),
  deferred_until  DATE,
  created_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
-- one active booking per donor
CREATE UNIQUE INDEX one_active_booking ON appointments (donor_id) WHERE status = 'scheduled';

CREATE TABLE blood_units (
  id             INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  facility_id    INT NOT NULL REFERENCES facilities(id),
  blood_group_id INT NOT NULL REFERENCES blood_groups(id),
  appointment_id INT REFERENCES appointments(id),
  collected_date DATE NOT NULL,
  expiry_date    DATE NOT NULL,
  tested_at      TIMESTAMPTZ,
  tested_by      INT REFERENCES users(id),
  status         VARCHAR(10) NOT NULL DEFAULT 'testing' CHECK (status IN ('testing','available','reserved','allocated','expired','discarded')),
  discard_reason VARCHAR(200)
);

-- ---------- blood requests ----------
CREATE TABLE requests (
  id                 INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  hospital_id        INT NOT NULL REFERENCES facilities(id),
  blood_group_id     INT NOT NULL REFERENCES blood_groups(id),
  quantity           INT NOT NULL CHECK (quantity > 0),
  quantity_fulfilled INT NOT NULL DEFAULT 0 CHECK (quantity_fulfilled >= 0),
  urgency            VARCHAR(8) NOT NULL CHECK (urgency IN ('routine','urgent','critical')),
  accept_compatible  BOOLEAN NOT NULL DEFAULT FALSE,
  patient_ref        VARCHAR(20) NOT NULL,                       -- code, never a name
  status             VARCHAR(10) NOT NULL DEFAULT 'submitted' CHECK (status IN ('submitted','verified','rejected','offered','fulfilled','cancelled')),
  reject_reason      VARCHAR(200),
  created_at         TIMESTAMPTZ NOT NULL DEFAULT now(),
  submitted_by       INT NOT NULL REFERENCES users(id),
  reviewed_by        INT REFERENCES users(id)
);

CREATE TABLE allocations (
  id              INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  request_id      INT NOT NULL REFERENCES requests(id),
  blood_unit_id   INT NOT NULL REFERENCES blood_units(id),
  offered_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  respond_by      TIMESTAMPTZ NOT NULL,
  response        VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (response IN ('pending','accepted','declined','timed_out')),
  responded_at    TIMESTAMPTZ,
  override_reason VARCHAR(300)
);

-- ---------- organs ----------
CREATE TABLE donor_reports (
  id                 INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  hospital_id        INT NOT NULL REFERENCES facilities(id),
  blood_group_id     INT NOT NULL REFERENCES blood_groups(id),
  declared_at        TIMESTAMPTZ NOT NULL,
  consent_confirmed  BOOLEAN NOT NULL,
  consent_reference  VARCHAR(80),
  reported_by        INT NOT NULL REFERENCES users(id)           -- no donor name stored
);

CREATE TABLE organs (
  id              INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  donor_report_id INT NOT NULL REFERENCES donor_reports(id),
  organ_type      VARCHAR(10) NOT NULL CHECK (organ_type IN ('kidney','liver','pancreas','heart','lungs')),
  retrieved_at    TIMESTAMPTZ NOT NULL,
  viable_until    TIMESTAMPTZ NOT NULL,
  status          VARCHAR(12) NOT NULL DEFAULT 'available' CHECK (status IN ('available','offered','allocated','expired','discarded'))
);

CREATE TABLE waiting_list (
  id                   INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  patient_ref          VARCHAR(20) NOT NULL UNIQUE,
  transplant_center_id INT NOT NULL REFERENCES facilities(id),
  organ_type           VARCHAR(10) NOT NULL CHECK (organ_type IN ('kidney','liver','pancreas','heart','lungs')),
  blood_group_id       INT NOT NULL REFERENCES blood_groups(id),
  urgency              VARCHAR(8) NOT NULL CHECK (urgency IN ('routine','urgent','critical')),
  registered_at        TIMESTAMPTZ NOT NULL DEFAULT now(),
  verification_status  VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (verification_status IN ('pending','verified','rejected')),
  verified_by          INT REFERENCES users(id),
  status               VARCHAR(12) NOT NULL DEFAULT 'active' CHECK (status IN ('active','transplanted','removed')),
  removal_reason       VARCHAR(200)
);

CREATE TABLE organ_offers (
  id              INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  organ_id        INT NOT NULL REFERENCES organs(id),
  waiting_list_id INT NOT NULL REFERENCES waiting_list(id),
  rank_position   INT NOT NULL,
  offered_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
  respond_by      TIMESTAMPTZ NOT NULL,
  response        VARCHAR(10) NOT NULL DEFAULT 'pending' CHECK (response IN ('pending','accepted','declined','timed_out')),
  decline_reason  VARCHAR(200),
  override_reason VARCHAR(300),
  offered_by      INT REFERENCES users(id)                       -- null when the system advanced it automatically
);

-- ---------- configuration and audit ----------
CREATE TABLE stock_thresholds (
  id             INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  facility_id    INT REFERENCES facilities(id),                  -- null = national
  blood_group_id INT NOT NULL REFERENCES blood_groups(id),
  min_units      INT NOT NULL CHECK (min_units >= 0),
  UNIQUE NULLS NOT DISTINCT (facility_id, blood_group_id)
);

CREATE TABLE settings (
  id    INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name  VARCHAR(60) NOT NULL UNIQUE,
  value VARCHAR(60) NOT NULL
);

CREATE TABLE audit_log (
  id         BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  user_id    INT REFERENCES users(id),                           -- null for automatic actions
  action     VARCHAR(40) NOT NULL,
  entity     VARCHAR(30),
  entity_id  INT,
  detail     TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------- indexes for the queries we know ----------
CREATE INDEX ix_units_stock     ON blood_units (blood_group_id, status, expiry_date);
CREATE INDEX ix_units_facility  ON blood_units (facility_id, status);
CREATE INDEX ix_requests_queue  ON requests (status, urgency, created_at);
CREATE INDEX ix_alloc_pending   ON allocations (response, respond_by);
CREATE INDEX ix_organs_viable   ON organs (status, viable_until);
CREATE INDEX ix_wait_match      ON waiting_list (organ_type, blood_group_id, status, verification_status);
CREATE INDEX ix_offers_pending  ON organ_offers (response, respond_by);
CREATE INDEX ix_appt_slot       ON appointments (slot_id, status);
CREATE INDEX ix_audit_time      ON audit_log (created_at DESC);
CREATE INDEX ix_sessions_expiry ON sessions (expires_at);