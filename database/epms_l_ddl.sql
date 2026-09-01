-- ============================================================
-- DATABASE: epms_l
-- Migrated & Normalized from: sagil_mei
-- Multi-Tenant: Shared DB + Shared Schema
-- Generated: 2026-09-01
-- ============================================================
-- NORMALIZATION NOTES:
-- 1. created_by/date/time + updated_by/date/time → created_at TIMESTAMP, updated_at TIMESTAMP
-- 2. All company-scoped tables → add company_id FK
-- 3. m_config (1 row) → m_company_config (1 row per company)
-- 4. tc_user.user_role → removed, replaced by tc_user_access
-- 5. ZEPMS_* staging tables → kept as-is (SAP buffer, per company)
-- 6. SMALLINT flags (0/1) → BOOLEAN where appropriate
-- 7. Separate DATE+TIME columns → single TIMESTAMP column
-- ============================================================

-- ============================================================
-- TIER 0: EXTENSIONS
-- ============================================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ============================================================
-- TIER 1: GLOBAL TENANT TABLES (no company_id)
-- ============================================================

-- ────────────────────────────────────────────────────────
-- TABLE: m_country (NEW)
-- ────────────────────────────────────────────────────────
CREATE TABLE m_country (
    id          BIGSERIAL PRIMARY KEY,
    code        VARCHAR(5)   NOT NULL UNIQUE,   -- 'MY', 'ID'
    name        VARCHAR(100) NOT NULL,           -- 'Malaysia', 'Indonesia'
    prefix      CHAR(1)      NOT NULL UNIQUE,    -- '1', '2'
    is_active   BOOLEAN      NOT NULL DEFAULT true,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_company (NEW)
-- ────────────────────────────────────────────────────────
CREATE TABLE m_company (
    id           BIGSERIAL PRIMARY KEY,
    country_id   BIGINT       NOT NULL REFERENCES m_country(id),
    company_code VARCHAR(20)  NOT NULL UNIQUE,  -- '1PPP', '2PPP'
    company_name VARCHAR(150) NOT NULL,
    is_active    BOOLEAN      NOT NULL DEFAULT true,
    created_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_roles (NEW)
-- ────────────────────────────────────────────────────────
CREATE TABLE m_roles (
    id                   BIGSERIAL PRIMARY KEY,
    role_code            VARCHAR(50)  NOT NULL UNIQUE,
    role_name            VARCHAR(100) NOT NULL,
    level                SMALLINT     NOT NULL,
    -- 10=super_admin, 20=country_admin, 30=company_admin
    -- 40=estate_manager, 50=asst_manager, 60=staff, 70=operational
    required_system_type VARCHAR(20)  NULL,
    -- NULL=always available, 'palm','coconut','durian','rubber'
    is_active            BOOLEAN      NOT NULL DEFAULT true,
    created_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_attendance (GLOBAL - no company_id)
-- Source: m_attendance
-- ────────────────────────────────────────────────────────
CREATE TABLE m_attendance (
    id              BIGSERIAL PRIMARY KEY,
    attendance_code VARCHAR(50)  NOT NULL UNIQUE,
    attendance_desc VARCHAR(255) NOT NULL,
    created_by      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by      VARCHAR(100) NULL,
    updated_at      TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_uom (GLOBAL)
-- Source: m_uom
-- ────────────────────────────────────────────────────────
CREATE TABLE m_uom (
    id         BIGSERIAL PRIMARY KEY,
    uom_code   VARCHAR(50)  NOT NULL UNIQUE,
    uom_desc   VARCHAR(255) NOT NULL,
    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by VARCHAR(100) NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_movement_type (GLOBAL)
-- Source: m_movement_type
-- ────────────────────────────────────────────────────────
CREATE TABLE m_movement_type (
    id              BIGSERIAL PRIMARY KEY,
    mvt_type_code   VARCHAR(50)  NOT NULL UNIQUE,
    mvt_type_doc_type VARCHAR(50) NOT NULL,
    mvt_type_desc   VARCHAR(255) NOT NULL,
    created_by      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by      VARCHAR(100) NULL,
    updated_at      TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_harvest_method (GLOBAL)
-- Source: m_harvest_method
-- ────────────────────────────────────────────────────────
CREATE TABLE m_harvest_method (
    id                    BIGSERIAL PRIMARY KEY,
    mhm_indicator         CHAR(1)      NULL,
    mhm_abbreviation      VARCHAR(15)  NULL,
    mhm_description       VARCHAR(255) NOT NULL,
    mhm_order_number_flag CHAR(1)      NOT NULL DEFAULT 'N',
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_coconut_activity_type (GLOBAL)
-- Source: m_coconut_activity_type
-- ────────────────────────────────────────────────────────
CREATE TABLE m_coconut_activity_type (
    id                          BIGSERIAL PRIMARY KEY,
    coconut_activity_type_code  VARCHAR(50)  NOT NULL UNIQUE,
    coconut_activity_type_desc  VARCHAR(255) NOT NULL,
    created_by                  VARCHAR(100) NULL,
    created_at                  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                  VARCHAR(100) NULL,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_disease (GLOBAL)
-- Source: m_durian_disease
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_disease (
    id           BIGSERIAL PRIMARY KEY,
    disease_code VARCHAR(50)  NOT NULL UNIQUE,
    disease_desc VARCHAR(255) NOT NULL,
    created_by   VARCHAR(100) NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by   VARCHAR(100) NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_fertilizer (GLOBAL)
-- Source: m_durian_fertilizer
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_fertilizer (
    id               BIGSERIAL PRIMARY KEY,
    fertilizer_code  VARCHAR(50)  NOT NULL UNIQUE,
    fertilizer_desc  VARCHAR(255) NOT NULL,
    created_by       VARCHAR(100) NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by       VARCHAR(100) NULL,
    updated_at       TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_pesticide (GLOBAL)
-- Source: m_durian_pesticide
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_pesticide (
    id              BIGSERIAL PRIMARY KEY,
    pesticide_code  VARCHAR(50)  NOT NULL UNIQUE,
    pesticide_desc  TEXT         NOT NULL,
    created_by      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by      VARCHAR(100) NULL,
    updated_at      TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_soil_condition (GLOBAL)
-- Source: m_durian_soil_condition
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_soil_condition (
    id           BIGSERIAL PRIMARY KEY,
    soil_code    VARCHAR(50) NOT NULL UNIQUE,
    soil_texture VARCHAR(100) NULL,
    created_by   VARCHAR(100) NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by   VARCHAR(100) NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: crop_type (GLOBAL - lookup)
-- Source: crop_type
-- ────────────────────────────────────────────────────────
CREATE TABLE crop_type (
    id            BIGSERIAL PRIMARY KEY,
    code          VARCHAR(50)  NOT NULL UNIQUE,
    name          VARCHAR(100) NOT NULL,
    description   VARCHAR(255) NULL,
    can_harvest   BOOLEAN      NOT NULL DEFAULT true,
    created_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ============================================================
-- TIER 2: USER MANAGEMENT
-- ============================================================

-- ────────────────────────────────────────────────────────
-- TABLE: tc_user
-- Source: tc_user (refactored - removed user_role column)
-- ────────────────────────────────────────────────────────
CREATE TABLE tc_user (
    id                             BIGSERIAL    PRIMARY KEY,
    username                       VARCHAR(100) NOT NULL UNIQUE,
    email                          VARCHAR(150) NOT NULL UNIQUE,
    password                       VARCHAR(255) NOT NULL,
    user_name                      VARCHAR(150) NOT NULL,          -- display name
    user_employee_code             VARCHAR(100) NULL UNIQUE,       -- global unique
    user_internal_employee_code    VARCHAR(100) NULL,
    user_token                     VARCHAR(255) NULL,              -- mobile API token
    is_active                      BOOLEAN      NOT NULL DEFAULT true,
    last_login_at                  TIMESTAMP    NULL,
    created_by                     VARCHAR(100) NULL,
    created_at                     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                     VARCHAR(100) NULL,
    updated_at                     TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: tc_user_access (NEW - replaces user_role in tc_user)
-- Multi-tenant pivot: user + role + scope
-- ────────────────────────────────────────────────────────
CREATE TABLE tc_user_access (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT    NOT NULL REFERENCES tc_user(id) ON DELETE CASCADE,
    role_id     BIGINT    NOT NULL REFERENCES m_roles(id),
    country_id  BIGINT    NULL REFERENCES m_country(id),   -- filled for country_admin
    company_id  BIGINT    NULL REFERENCES m_company(id),   -- filled for company-level
    -- NULL both = super_admin
    -- country_id filled only = country_admin
    -- company_id filled = company-level user
    is_active   BOOLEAN   NOT NULL DEFAULT true,
    created_by  VARCHAR(100) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_by  VARCHAR(100) NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_user_access UNIQUE (user_id)
    -- 1 user = 1 access scope only
);

-- ────────────────────────────────────────────────────────
-- TABLE: login_log (per company)
-- Source: login_log
-- ────────────────────────────────────────────────────────
CREATE TABLE login_log (
    id                  BIGSERIAL    PRIMARY KEY,
    company_id          BIGINT       NOT NULL REFERENCES m_company(id),
    user_id             BIGINT       NULL REFERENCES tc_user(id),
    login_device_id     BIGINT       NULL,
    login_employee_code VARCHAR(100) NULL,
    login_employee_name VARCHAR(150) NULL,
    logout_at           TIMESTAMP    NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ============================================================
-- TIER 3: COMPANY CONFIG (1 per company)
-- Source: m_config (was 1 global row → now 1 row per company)
-- ============================================================
CREATE TABLE m_company_config (
    id                                        BIGSERIAL    PRIMARY KEY,
    company_id                                BIGINT       NOT NULL UNIQUE REFERENCES m_company(id),
    -- Identity
    profile_code                              VARCHAR(50)  NULL,
    profile_name                              VARCHAR(100) NULL,
    estate_code                               VARCHAR(20)  NULL,
    estate_name                               VARCHAR(100) NULL,
    plant_code                                VARCHAR(20)  NULL,
    -- SAP Client (for topbar badge)
    sap_client                                VARCHAR(10)  NULL,
    -- System Type Flags
    system_is_palm                            BOOLEAN      NOT NULL DEFAULT false,
    system_is_coconut                         BOOLEAN      NOT NULL DEFAULT false,
    system_is_rubber                          BOOLEAN      NOT NULL DEFAULT false,
    system_is_durian                          BOOLEAN      NOT NULL DEFAULT false,
    -- SAP / Integration
    integration_type                          SMALLINT     NOT NULL DEFAULT 1,  -- 1=SAP, 2=Pinfosys
    have_internet_connection                  BOOLEAN      NOT NULL DEFAULT true,
    sap_api_url                               VARCHAR(500) NULL,
    sap_user_id                               VARCHAR(100) NULL,
    sap_password                              VARCHAR(255) NULL,                -- encrypted
    -- Operational Settings
    cutter_distribution_value                 NUMERIC(5,2) NULL,
    carrier_distribution_value                NUMERIC(5,2) NULL,
    cutter_lf_distribution_value              NUMERIC(5,2) NULL,
    carrier_lf_distribution_value             NUMERIC(5,2) NULL,
    attendance_default_value                  VARCHAR(20)  NULL,
    attendance_normal_default_value           VARCHAR(20)  NULL,
    allowed_attendance_codes                  JSONB        NULL,
    daily_overtime_max_limit                  SMALLINT     NOT NULL DEFAULT 3,
    max_oph_restan                            SMALLINT     NOT NULL DEFAULT 0,
    fdn_oph                                   BOOLEAN      NOT NULL DEFAULT false,
    is_fixed_platform                         BOOLEAN      NOT NULL DEFAULT false,
    -- System Lock (per company)
    is_lock_system                            BOOLEAN      NOT NULL DEFAULT false,
    -- Additional flexible settings (take_picture, take_location, scan flags)
    additional_settings                       JSONB        NULL,
    created_by                                VARCHAR(100) NULL,
    created_at                                TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                                VARCHAR(100) NULL,
    updated_at                                TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: log_system_lock (per company)
-- Source: log_system_lock
-- ────────────────────────────────────────────────────────
CREATE TABLE log_system_lock (
    id           BIGSERIAL PRIMARY KEY,
    company_id   BIGINT    NOT NULL REFERENCES m_company(id),
    is_locked    BOOLEAN   NOT NULL DEFAULT true,
    locked_at    TIMESTAMP NOT NULL DEFAULT NOW(),
    unlocked_at  TIMESTAMP NULL,
    unlocked_by  BIGINT    NULL REFERENCES tc_user(id),
    unlock_reason TEXT     NULL
);

-- ============================================================
-- TIER 4: COMPANY-SCOPED MASTER DATA
-- All tables below have company_id FK
-- ============================================================

-- ────────────────────────────────────────────────────────
-- TABLE: m_estate (per company)
-- Source: m_estate
-- ────────────────────────────────────────────────────────
CREATE TABLE m_estate (
    id                 BIGSERIAL    PRIMARY KEY,
    company_id         BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code        VARCHAR(50)  NOT NULL,
    estate_name        VARCHAR(150) NOT NULL,
    estate_plant_code  VARCHAR(50)  NULL,
    created_by         VARCHAR(100) NULL,
    created_at         TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by         VARCHAR(100) NULL,
    updated_at         TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_estate_company UNIQUE (company_id, estate_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_division (per company)
-- Source: m_division
-- ────────────────────────────────────────────────────────
CREATE TABLE m_division (
    id             BIGSERIAL    PRIMARY KEY,
    company_id     BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code    VARCHAR(50)  NOT NULL,
    division_code  VARCHAR(50)  NOT NULL,
    division_name  VARCHAR(150) NOT NULL,
    valid_from     DATE         NULL,
    valid_to       DATE         NULL,
    created_by     VARCHAR(100) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by     VARCHAR(100) NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_division_company UNIQUE (company_id, estate_code, division_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_block (per company)
-- Source: m_block
-- ────────────────────────────────────────────────────────
CREATE TABLE m_block (
    id               BIGSERIAL    PRIMARY KEY,
    company_id       BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code      VARCHAR(50)  NOT NULL,
    division_code    VARCHAR(50)  NOT NULL,
    block_code       VARCHAR(50)  NOT NULL,
    block_name       VARCHAR(150) NULL,
    block_hectarage  FLOAT8       NULL,
    block_planted_date DATE       NULL,
    valid_from       DATE         NULL,
    valid_to         DATE         NULL,
    block_state      VARCHAR(20)  NULL,
    is_planted       BOOLEAN      NOT NULL DEFAULT false,
    crop_type        VARCHAR(50)  NULL,
    total_palm       BIGINT       NOT NULL DEFAULT 0,
    created_by       VARCHAR(100) NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by       VARCHAR(100) NULL,
    updated_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_block_company UNIQUE (company_id, estate_code, division_code, block_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_tph (per company)
-- Source: m_tph
-- ────────────────────────────────────────────────────────
CREATE TABLE m_tph (
    id             BIGSERIAL    PRIMARY KEY,
    company_id     BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code    VARCHAR(50)  NOT NULL,
    division_code  VARCHAR(50)  NOT NULL,
    block_code     VARCHAR(50)  NOT NULL,
    section_code   VARCHAR(50)  NULL,
    tph_code       VARCHAR(50)  NOT NULL,
    valid_from     DATE         NULL,
    valid_to       DATE         NULL,
    latitude       VARCHAR(50)  NULL,
    longitude      VARCHAR(50)  NULL,
    tph_palm_total BIGINT       NOT NULL DEFAULT 0,
    created_by     VARCHAR(100) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by     VARCHAR(100) NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_tph_company UNIQUE (company_id, estate_code, division_code, block_code, section_code, tph_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_employee (per company, employee_code unique global)
-- Source: m_employee
-- ────────────────────────────────────────────────────────
CREATE TABLE m_employee (
    id                     BIGSERIAL    PRIMARY KEY,
    company_id             BIGINT       NOT NULL REFERENCES m_company(id),
    employee_code          VARCHAR(100) NOT NULL UNIQUE,  -- globally unique
    employee_name          VARCHAR(150) NOT NULL,
    employee_estate_code   VARCHAR(50)  NULL,
    employee_division_code VARCHAR(50)  NULL,
    employee_sex           VARCHAR(10)  NULL,
    employee_job_code      VARCHAR(50)  NULL,
    employee_job_type      VARCHAR(50)  NULL,
    employee_status        VARCHAR(20)  NULL,
    employee_stats         VARCHAR(20)  NULL,
    employee_profile       VARCHAR(50)  NULL,
    employee_department    VARCHAR(100) NULL,
    employee_vendor        VARCHAR(100) NULL,
    is_internal_estate     BOOLEAN      NOT NULL DEFAULT false,
    valid_from             DATE         NULL,
    valid_to               DATE         NULL,
    work_permit_exp_date   DATE         NULL,
    created_by             VARCHAR(100) NULL,
    created_at             TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by             VARCHAR(100) NULL,
    updated_at             TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_meas_point (per company)
-- Source: m_meas_point
-- ────────────────────────────────────────────────────────
CREATE TABLE m_meas_point (
    id                       BIGSERIAL    PRIMARY KEY,
    company_id               BIGINT       NOT NULL REFERENCES m_company(id),
    plant_code               VARCHAR(50)  NULL,
    vra_order_number         VARCHAR(100) NULL,
    equipment_code           VARCHAR(100) NULL,
    equipment_object_number  VARCHAR(100) NULL,
    point                    VARCHAR(100) NULL,
    unit                     VARCHAR(50)  NULL,
    description              VARCHAR(255) NULL,
    created_by               VARCHAR(100) NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by               VARCHAR(100) NULL,
    updated_at               TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_vendor (per company)
-- Source: m_vendor
-- ────────────────────────────────────────────────────────
CREATE TABLE m_vendor (
    id           BIGSERIAL    PRIMARY KEY,
    company_id   BIGINT       NOT NULL REFERENCES m_company(id),
    vendor_code  VARCHAR(50)  NOT NULL,
    vendor_name  VARCHAR(150) NOT NULL,
    plant_code   VARCHAR(50)  NULL,
    created_by   VARCHAR(100) NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by   VARCHAR(100) NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_vendor_company UNIQUE (company_id, vendor_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_devices (per company)
-- Source: m_devices
-- ────────────────────────────────────────────────────────
CREATE TABLE m_devices (
    id            BIGSERIAL    PRIMARY KEY,
    company_id    BIGINT       NOT NULL REFERENCES m_company(id),
    device_code   VARCHAR(100) NOT NULL,
    estate_code   VARCHAR(50)  NULL,
    device_imei   VARCHAR(100) NULL,
    created_by    VARCHAR(100) NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by    VARCHAR(100) NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_device_company UNIQUE (company_id, device_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_destination (per company)
-- Source: m_destination
-- ────────────────────────────────────────────────────────
CREATE TABLE m_destination (
    id               BIGSERIAL    PRIMARY KEY,
    company_id       BIGINT       NOT NULL REFERENCES m_company(id),
    destination_code VARCHAR(50)  NOT NULL,
    destination_name VARCHAR(150) NOT NULL,
    created_by       VARCHAR(100) NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by       VARCHAR(100) NULL,
    updated_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_destination_company UNIQUE (company_id, destination_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_cost_center (per company)
-- Source: m_cost_center
-- ────────────────────────────────────────────────────────
CREATE TABLE m_cost_center (
    id          BIGSERIAL    PRIMARY KEY,
    company_id  BIGINT       NOT NULL REFERENCES m_company(id),
    cc_code     VARCHAR(50)  NOT NULL,
    cc_desc     VARCHAR(255) NULL,
    cc_gsber    VARCHAR(50)  NULL,
    valid_from  DATE         NULL,
    valid_to    DATE         NULL,
    created_by  VARCHAR(100) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by  VARCHAR(100) NULL,
    updated_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_cc_company UNIQUE (company_id, cc_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_work_center (per company)
-- Source: m_work_center
-- ────────────────────────────────────────────────────────
CREATE TABLE m_work_center (
    id               BIGSERIAL    PRIMARY KEY,
    company_id       BIGINT       NOT NULL REFERENCES m_company(id),
    plant_code       VARCHAR(50)  NULL,
    estate_code      VARCHAR(50)  NULL,
    division_code    VARCHAR(50)  NULL,
    work_center_code VARCHAR(50)  NOT NULL,
    work_center_name VARCHAR(150) NULL,
    created_by       VARCHAR(100) NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by       VARCHAR(100) NULL,
    updated_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_wc_company UNIQUE (company_id, work_center_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_worktype (per company)
-- Source: m_worktype
-- ────────────────────────────────────────────────────────
CREATE TABLE m_worktype (
    id             BIGSERIAL    PRIMARY KEY,
    company_id     BIGINT       NOT NULL REFERENCES m_company(id),
    worktype_code  VARCHAR(50)  NOT NULL,
    worktype_name  VARCHAR(150) NULL,
    created_by     VARCHAR(100) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by     VARCHAR(100) NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_worktype_company UNIQUE (company_id, worktype_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_activity (per company)
-- Source: m_activity
-- ────────────────────────────────────────────────────────
CREATE TABLE m_activity (
    id                        BIGSERIAL    PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    activity_code             VARCHAR(50)  NOT NULL,
    activity_name             VARCHAR(150) NOT NULL,
    activity_uom              VARCHAR(50)  NULL,
    activity_uom_name         VARCHAR(100) NULL,
    activity_group_code       VARCHAR(50)  NULL,
    cost_by_block             BOOLEAN      NOT NULL DEFAULT false,
    cost_by_auc               BOOLEAN      NOT NULL DEFAULT false,
    cost_by_order_number      BOOLEAN      NOT NULL DEFAULT false,
    cost_by_cost_center       BOOLEAN      NOT NULL DEFAULT false,
    block_is_lc               BOOLEAN      NOT NULL DEFAULT false,
    block_is_immature         BOOLEAN      NOT NULL DEFAULT false,
    block_is_mature           BOOLEAN      NOT NULL DEFAULT false,
    block_is_scout            BOOLEAN      NOT NULL DEFAULT false,
    is_wbs_required           BOOLEAN      NOT NULL DEFAULT false,
    created_by                VARCHAR(100) NULL,
    created_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                VARCHAR(100) NULL,
    updated_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_activity_company UNIQUE (company_id, activity_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_task (per company) - previously split from m_activity context
-- ────────────────────────────────────────────────────────
CREATE TABLE m_task (
    id             BIGSERIAL    PRIMARY KEY,
    company_id     BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code    VARCHAR(50)  NULL,
    division_code  VARCHAR(50)  NULL,
    block_code     VARCHAR(50)  NULL,
    task_code      VARCHAR(50)  NOT NULL,
    task_name      VARCHAR(150) NULL,
    valid_from     DATE         NULL,
    valid_to       DATE         NULL,
    created_by     VARCHAR(100) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by     VARCHAR(100) NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_task_company UNIQUE (company_id, estate_code, task_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_bin (per company)
-- Source: m_bin
-- ────────────────────────────────────────────────────────
CREATE TABLE m_bin (
    id         BIGSERIAL    PRIMARY KEY,
    company_id BIGINT       NOT NULL REFERENCES m_company(id),
    bin_code   VARCHAR(50)  NOT NULL,
    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by VARCHAR(100) NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_bin_company UNIQUE (company_id, bin_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_sloc (per company)
-- Source: m_sloc
-- ────────────────────────────────────────────────────────
CREATE TABLE m_sloc (
    id          BIGSERIAL    PRIMARY KEY,
    company_id  BIGINT       NOT NULL REFERENCES m_company(id),
    sloc_code   VARCHAR(50)  NOT NULL,
    plant_code  VARCHAR(50)  NULL,
    sloc_desc   VARCHAR(255) NULL,
    created_by  VARCHAR(100) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by  VARCHAR(100) NULL,
    updated_at  TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_sloc_company UNIQUE (company_id, sloc_code, plant_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_wbs (per company)
-- Source: m_wbs
-- ────────────────────────────────────────────────────────
CREATE TABLE m_wbs (
    id           BIGSERIAL    PRIMARY KEY,
    company_id   BIGINT       NOT NULL REFERENCES m_company(id),
    wbs_code     VARCHAR(100) NOT NULL,
    wbs_name     VARCHAR(255) NULL,
    wbs_group_code VARCHAR(50) NULL,
    wbs_group_name VARCHAR(100) NULL,
    created_by   VARCHAR(100) NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by   VARCHAR(100) NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_wbs_company UNIQUE (company_id, wbs_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_glacc (per company) - GL Account
-- Source: m_glacc
-- ────────────────────────────────────────────────────────
CREATE TABLE m_glacc (
    id             BIGSERIAL    PRIMARY KEY,
    company_id     BIGINT       NOT NULL REFERENCES m_company(id),
    account_number VARCHAR(100) NOT NULL,
    account_desc   VARCHAR(255) NULL,
    created_by     VARCHAR(100) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by     VARCHAR(100) NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_glacc_company UNIQUE (company_id, account_number)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_glacc_gi_order (per company)
-- Source: m_glacc_gi_order
-- ────────────────────────────────────────────────────────
CREATE TABLE m_glacc_gi_order (
    id             BIGSERIAL    PRIMARY KEY,
    company_id     BIGINT       NOT NULL REFERENCES m_company(id),
    account_number VARCHAR(100) NOT NULL,
    account_desc   VARCHAR(255) NULL,
    created_by     VARCHAR(100) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by     VARCHAR(100) NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_glacc_gi_company UNIQUE (company_id, account_number)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_gigr_wbs (per company)
-- Source: m_gigr_wbs
-- ────────────────────────────────────────────────────────
CREATE TABLE m_gigr_wbs (
    id              BIGSERIAL    PRIMARY KEY,
    company_id      BIGINT       NOT NULL REFERENCES m_company(id),
    wbs_code        VARCHAR(100) NOT NULL,
    wbs_code2       VARCHAR(100) NULL,
    wbs_gl_acc_code VARCHAR(100) NULL,
    wbs_gl_acc_desc VARCHAR(255) NULL,
    plant_code      VARCHAR(50)  NULL,
    wbs_name        VARCHAR(255) NULL,
    created_by      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by      VARCHAR(100) NULL,
    updated_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_gigr_wbs_company UNIQUE (company_id, wbs_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_material (per company)
-- Source: m_material
-- ────────────────────────────────────────────────────────
CREATE TABLE m_material (
    id              BIGSERIAL    PRIMARY KEY,
    company_id      BIGINT       NOT NULL REFERENCES m_company(id),
    material_code   VARCHAR(100) NOT NULL,
    material_name   VARCHAR(255) NOT NULL,
    material_uom    VARCHAR(50)  NULL,
    plant_code      VARCHAR(50)  NULL,
    sloc_code       VARCHAR(50)  NULL,
    material_batch  VARCHAR(100) NULL,
    material_group  VARCHAR(100) NULL,
    material_type   VARCHAR(50)  NULL,
    created_by      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by      VARCHAR(100) NULL,
    updated_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_material_company UNIQUE (company_id, material_code, plant_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: material_type_group (per company)
-- Source: material_type_group
-- ────────────────────────────────────────────────────────
CREATE TABLE material_type_group (
    id            BIGSERIAL    PRIMARY KEY,
    company_id    BIGINT       NOT NULL REFERENCES m_company(id),
    mat_type_code VARCHAR(50)  NOT NULL,
    mat_type_desc VARCHAR(255) NULL,
    mat_code_list TEXT         NULL,
    created_by    VARCHAR(100) NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by    VARCHAR(100) NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_mat_type_company UNIQUE (company_id, mat_type_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: material_stock (per company)
-- Source: material_stock
-- ────────────────────────────────────────────────────────
CREATE TABLE material_stock (
    id                  BIGSERIAL    PRIMARY KEY,
    company_id          BIGINT       NOT NULL REFERENCES m_company(id),
    material_code       VARCHAR(100) NOT NULL,
    material_name       VARCHAR(255) NULL,
    material_qty        BIGINT       NOT NULL DEFAULT 0,
    material_uom        VARCHAR(50)  NULL,
    plant_code          VARCHAR(50)  NULL,
    sloc_code           VARCHAR(50)  NULL,
    material_type_code  VARCHAR(20)  NULL,
    trans_date          DATE         NULL,
    created_by          VARCHAR(100) NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by          VARCHAR(100) NULL,
    updated_at          TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_receiving_point (per company)
-- Source: m_receiving_point
-- ────────────────────────────────────────────────────────
CREATE TABLE m_receiving_point (
    id                    BIGSERIAL    PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    receiving_point_code  VARCHAR(50)  NOT NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_rp_company UNIQUE (company_id, receiving_point_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_deduction_code / m_grading (per company)
-- Source: m_deduction_code (grading/deduction for FFB)
-- ────────────────────────────────────────────────────────
CREATE TABLE m_grading (
    id                    BIGSERIAL    PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    deduction_code        VARCHAR(50)  NOT NULL,
    deduction_desc        VARCHAR(255) NULL,
    deduction_uom         VARCHAR(50)  NULL,
    deduction_rate        FLOAT8       NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_grading_company UNIQUE (company_id, deduction_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_deduction_type (per company)
-- Source: m_deduction_type
-- ────────────────────────────────────────────────────────
CREATE TABLE m_deduction_type (
    id                     BIGSERIAL    PRIMARY KEY,
    company_id             BIGINT       NOT NULL REFERENCES m_company(id),
    deduction_type_code    VARCHAR(50)  NOT NULL,
    deduction_type_desc    VARCHAR(255) NOT NULL,
    created_by             VARCHAR(100) NULL,
    created_at             TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by             VARCHAR(100) NULL,
    updated_at             TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_ded_type_company UNIQUE (company_id, deduction_type_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_confirmation_text (per company, seeded default)
-- Source: m_confirmation_text (for VRA transactions)
-- ────────────────────────────────────────────────────────
CREATE TABLE m_confirmation_text (
    id         BIGSERIAL    PRIMARY KEY,
    company_id BIGINT       NOT NULL REFERENCES m_company(id),
    ctext_code VARCHAR(50)  NOT NULL,
    ctext_text VARCHAR(255) NOT NULL,
    ctext_desc VARCHAR(255) NULL,
    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by VARCHAR(100) NULL,
    updated_at TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_ctext_company UNIQUE (company_id, ctext_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: mc_fdn_card (per company)
-- Source: mc_fdn_card
-- ────────────────────────────────────────────────────────
CREATE TABLE mc_fdn_card (
    id             BIGSERIAL    PRIMARY KEY,
    company_id     BIGINT       NOT NULL REFERENCES m_company(id),
    fdn_card_id    VARCHAR(100) NOT NULL,
    division_code  VARCHAR(50)  NOT NULL,
    created_by     VARCHAR(100) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by     VARCHAR(100) NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_fdn_card_company UNIQUE (company_id, fdn_card_id)
);

-- ────────────────────────────────────────────────────────
-- TABLE: mc_oph_card (per company)
-- Source: mc_oph_card
-- ────────────────────────────────────────────────────────
CREATE TABLE mc_oph_card (
    id            BIGSERIAL    PRIMARY KEY,
    company_id    BIGINT       NOT NULL REFERENCES m_company(id),
    oph_card_id   VARCHAR(100) NOT NULL,
    division_code VARCHAR(50)  NOT NULL,
    created_by    VARCHAR(100) NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by    VARCHAR(100) NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_oph_card_company UNIQUE (company_id, oph_card_id)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_vra (per company) - Vehicle Registration
-- Source: m_vra
-- ────────────────────────────────────────────────────────
CREATE TABLE m_vra (
    id               BIGSERIAL    PRIMARY KEY,
    company_id       BIGINT       NOT NULL REFERENCES m_company(id),
    license_number   VARCHAR(100) NOT NULL,
    equipment_code   VARCHAR(100) NOT NULL DEFAULT 'N',
    object_type      VARCHAR(100) NULL,
    plant_code       VARCHAR(50)  NULL,
    vra_order_number VARCHAR(100) NULL,
    valid_from       DATE         NULL,
    valid_to         DATE         NULL,
    created_by       VARCHAR(100) NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by       VARCHAR(100) NULL,
    updated_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_vra_company UNIQUE (company_id, license_number)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_po_header / m_order_header (per company)
-- Source: m_po_header + m_order_header (same structure, different purpose)
-- ────────────────────────────────────────────────────────
CREATE TABLE m_purchase_order (
    id                    BIGSERIAL    PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    po_number             VARCHAR(100) NOT NULL,
    po_type               VARCHAR(50)  NULL,
    po_status             VARCHAR(50)  NULL,
    vendor_code           VARCHAR(100) NULL,
    vendor_name           VARCHAR(255) NULL,
    plant_code            VARCHAR(50)  NULL,
    sloc_code             VARCHAR(50)  NULL,
    organization          VARCHAR(100) NULL,
    supplier_account      VARCHAR(100) NULL,
    po_group              VARCHAR(50)  NULL,
    is_deleted            BOOLEAN      NOT NULL DEFAULT false,
    sap_created_date      DATE         NULL,
    sap_created_by        VARCHAR(100) NULL,
    -- Detail (denormalized, 1 PO = 1 line as in source)
    material_line_num     VARCHAR(50)  NULL,
    material_code         VARCHAR(100) NULL,
    material_name         VARCHAR(255) NULL,
    material_group        VARCHAR(100) NULL,
    qty_order             FLOAT8       NULL,
    uom                   VARCHAR(50)  NULL,
    currency_key          VARCHAR(10)  NULL,
    net_price             FLOAT8       NULL,
    unit_price            FLOAT8       NULL,
    order_price_unit      VARCHAR(50)  NULL,
    net_value             FLOAT8       NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_maintenance_order (per company)
-- Source: m_maintenance_order
-- ────────────────────────────────────────────────────────
CREATE TABLE m_maintenance_order (
    id              BIGSERIAL    PRIMARY KEY,
    company_id      BIGINT       NOT NULL REFERENCES m_company(id),
    order_number    VARCHAR(100) NOT NULL,
    sales_doc_type  VARCHAR(50)  NULL,
    order_desc      VARCHAR(255) NULL,
    plant_code      VARCHAR(50)  NULL,
    business_area   VARCHAR(50)  NULL,
    created_by      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by      VARCHAR(100) NULL,
    updated_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_mo_company UNIQUE (company_id, order_number)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_sales_order (per company)
-- Source: m_sales_order
-- ────────────────────────────────────────────────────────
CREATE TABLE m_sales_order (
    id                       BIGSERIAL    PRIMARY KEY,
    company_id               BIGINT       NOT NULL REFERENCES m_company(id),
    sales_order_no           VARCHAR(100) NOT NULL,
    plant_code               VARCHAR(50)  NOT NULL,
    item_no                  VARCHAR(50)  NULL,
    customer_reference       VARCHAR(100) NULL,
    customer_code            VARCHAR(100) NULL,
    customer_desc_1          VARCHAR(255) NULL,
    customer_desc_2          VARCHAR(255) NULL,
    material_code            VARCHAR(100) NULL,
    material_desc            VARCHAR(255) NULL,
    item_qty                 VARCHAR(50)  NULL,
    item_uom                 VARCHAR(50)  NULL,
    payment_term             VARCHAR(50)  NULL,
    inco_term_1              VARCHAR(50)  NULL,
    inco_term_2              VARCHAR(100) NULL,
    reason_for_rejection     VARCHAR(100) NULL,
    sales_order_type         VARCHAR(50)  NULL,
    sales_order_date         VARCHAR(50)  NULL,
    item_description         VARCHAR(255) NULL,
    sap_created_date         VARCHAR(50)  NULL,
    sap_created_by           VARCHAR(100) NULL,
    created_by               VARCHAR(100) NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by               VARCHAR(100) NULL,
    updated_at               TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_non_palm_material (per company)
-- Source: m_non_palm_material
-- ────────────────────────────────────────────────────────
CREATE TABLE m_non_palm_material (
    id                   BIGSERIAL    PRIMARY KEY,
    company_id           BIGINT       NOT NULL REFERENCES m_company(id),
    material_code        VARCHAR(100) NOT NULL,
    material_desc        VARCHAR(255) NULL,
    material_uom         VARCHAR(50)  NULL,
    plant_code           VARCHAR(50)  NULL,
    created_by           VARCHAR(100) NULL,
    created_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by           VARCHAR(100) NULL,
    updated_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_nonpalm_company UNIQUE (company_id, material_code, plant_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_coconut_material (per company)
-- Source: (derived from non-palm material for coconut)
-- ────────────────────────────────────────────────────────
CREATE TABLE m_coconut_material (
    id             BIGSERIAL    PRIMARY KEY,
    company_id     BIGINT       NOT NULL REFERENCES m_company(id),
    material_code  VARCHAR(100) NOT NULL,
    material_desc  VARCHAR(255) NULL,
    material_uom   VARCHAR(50)  NULL,
    plant_code     VARCHAR(50)  NULL,
    created_by     VARCHAR(100) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by     VARCHAR(100) NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_coconut_mat_company UNIQUE (company_id, material_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_task (per company)
-- Source: m_durian_task
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_task (
    id             BIGSERIAL    PRIMARY KEY,
    company_id     BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code    VARCHAR(50)  NULL,
    division_code  VARCHAR(50)  NULL,
    block_code     VARCHAR(50)  NULL,
    task_no        VARCHAR(50)  NOT NULL,
    row_no         INTEGER      NULL,
    task_validity  DATE         NULL,
    created_by     VARCHAR(100) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by     VARCHAR(100) NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_variety (per company)
-- Source: m_durian_variety
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_variety (
    id             BIGSERIAL    PRIMARY KEY,
    company_id     BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code    VARCHAR(50)  NULL,
    division_code  VARCHAR(50)  NULL,
    block_code     VARCHAR(50)  NULL,
    row_no         INTEGER      NULL,
    variety        VARCHAR(100) NOT NULL,
    created_by     VARCHAR(100) NULL,
    created_at     TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by     VARCHAR(100) NULL,
    updated_at     TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_grading (per company, seeded default)
-- Source: m_durian_grading
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_grading (
    id               BIGSERIAL    PRIMARY KEY,
    company_id       BIGINT       NOT NULL REFERENCES m_company(id),
    crop_type        VARCHAR(50)  NULL,
    type_of_variety  VARCHAR(50)  NULL,
    grading_code     VARCHAR(50)  NOT NULL,
    grading_weight   VARCHAR(50)  NULL,
    created_by       VARCHAR(100) NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by       VARCHAR(100) NULL,
    updated_at       TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_durian_grading_company UNIQUE (company_id, grading_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_customer_code (per company)
-- Source: m_customer_code
-- ────────────────────────────────────────────────────────
CREATE TABLE m_customer_code (
    id            BIGSERIAL    PRIMARY KEY,
    company_id    BIGINT       NOT NULL REFERENCES m_company(id),
    plant_code    VARCHAR(50)  NULL,
    customer_code VARCHAR(100) NOT NULL,
    created_by    VARCHAR(100) NULL,
    created_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by    VARCHAR(100) NULL,
    updated_at    TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_customer_company UNIQUE (company_id, customer_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_cost_control_mapping (per company)
-- Source: m_cost_control_mapping
-- ────────────────────────────────────────────────────────
CREATE TABLE m_cost_control_mapping (
    id                    BIGSERIAL    PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    activity_code_start   VARCHAR(50)  NULL,
    activity_code_end     VARCHAR(50)  NULL,
    cost_by_block         BOOLEAN      NOT NULL DEFAULT false,
    cost_by_auc           BOOLEAN      NOT NULL DEFAULT false,
    cost_by_order_number  BOOLEAN      NOT NULL DEFAULT false,
    cost_by_cost_center   BOOLEAN      NOT NULL DEFAULT false,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_report_oph (per company)
-- Source: m_report_oph
-- ────────────────────────────────────────────────────────
CREATE TABLE m_report_oph (
    id                   BIGSERIAL    PRIMARY KEY,
    company_id           BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code          VARCHAR(20)  NULL,
    period               VARCHAR(20)  NULL,
    division_code        VARCHAR(20)  NULL,
    block_code           VARCHAR(20)  NULL,
    brondolan_rate_1     FLOAT8       NULL,
    brondolan_rate_2     FLOAT8       NULL,
    basis                FLOAT8       NULL,
    gandeng              FLOAT8       NULL,
    premi_basis          FLOAT8       NULL,
    premi_non_basis      FLOAT8       NULL,
    hk_rate              FLOAT8       NULL,
    created_by           VARCHAR(100) NULL,
    created_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by           VARCHAR(100) NULL,
    updated_at           TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ============================================================
-- TIER 5: GROUPING TABLES (per company)
-- ============================================================

-- ────────────────────────────────────────────────────────
-- TABLE: m_gang_employee (per company)
-- Source: m_gang_employee
-- ────────────────────────────────────────────────────────
CREATE TABLE m_gang_employee (
    id                   BIGSERIAL    PRIMARY KEY,
    company_id           BIGINT       NOT NULL REFERENCES m_company(id),
    gang_code            VARCHAR(50)  NOT NULL,
    gang_employee_code   VARCHAR(100) NOT NULL,
    gang_employee_name   VARCHAR(150) NOT NULL,
    created_by           VARCHAR(100) NULL,
    created_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by           VARCHAR(100) NULL,
    updated_at           TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_gang_emp_company UNIQUE (company_id, gang_code, gang_employee_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_field_staff_gang (per company)
-- Source: m_field_staff_gang
-- ────────────────────────────────────────────────────────
CREATE TABLE m_field_staff_gang (
    id                      BIGSERIAL    PRIMARY KEY,
    company_id              BIGINT       NOT NULL REFERENCES m_company(id),
    field_staff_gang_code   VARCHAR(50)  NOT NULL,
    field_staff_employee_code VARCHAR(100) NOT NULL,
    field_staff_employee_name VARCHAR(150) NULL,
    created_by              VARCHAR(100) NULL,
    created_at              TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by              VARCHAR(100) NULL,
    updated_at              TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_fsg_company UNIQUE (company_id, field_staff_gang_code, field_staff_employee_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_field_staff_kemandoran (per company)
-- Source: m_field_staff_kemandoran
-- ────────────────────────────────────────────────────────
CREATE TABLE m_field_staff_kemandoran (
    id                        BIGSERIAL    PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    field_staff_employee_code VARCHAR(100) NOT NULL,
    field_staff_employee_name VARCHAR(150) NULL,
    mandor_employee_code      VARCHAR(100) NOT NULL,
    mandor_employee_name      VARCHAR(150) NULL,
    created_by                VARCHAR(100) NULL,
    created_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                VARCHAR(100) NULL,
    updated_at                TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: m_assistant_manager_division (per company)
-- Source: m_assistant_manager_division
-- ────────────────────────────────────────────────────────
CREATE TABLE m_assistant_manager_division (
    id                          BIGSERIAL    PRIMARY KEY,
    company_id                  BIGINT       NOT NULL REFERENCES m_company(id),
    assistant_manager_code      VARCHAR(100) NOT NULL,
    assistant_manager_name      VARCHAR(150) NULL,
    division_code               VARCHAR(50)  NOT NULL,
    division_name               VARCHAR(150) NULL,
    created_by                  VARCHAR(100) NULL,
    created_at                  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                  VARCHAR(100) NULL,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_amd_company UNIQUE (company_id, assistant_manager_code, division_code)
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_user_assignment / mandor assignment (per company)
-- Source: t_user_assignment
-- ────────────────────────────────────────────────────────
CREATE TABLE t_user_assignment (
    id                          BIGSERIAL    PRIMARY KEY,
    company_id                  BIGINT       NOT NULL REFERENCES m_company(id),
    mandor_employee_code        VARCHAR(100) NOT NULL,
    mandor_employee_name        VARCHAR(150) NULL,
    worker_employee_code        VARCHAR(100) NOT NULL,
    worker_employee_name        VARCHAR(150) NULL,
    assignment_valid_from       DATE         NULL,
    assignment_valid_to         DATE         NULL,
    created_by                  VARCHAR(100) NULL,
    created_at                  TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                  VARCHAR(100) NULL,
    updated_at                  TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ============================================================
-- TIER 6: SAP STAGING TABLES (per company, ZEPMS_*)
-- These tables are SAP data buffer - receive raw SAP data
-- before being processed into operational tables
-- Naming: kept similar to source but add company_id
-- ============================================================

CREATE TABLE sap_activity_out (
    id           BIGSERIAL    PRIMARY KEY,
    company_id   BIGINT       NOT NULL REFERENCES m_company(id),
    actvt_no     VARCHAR(255) NULL,
    actvt_name   VARCHAR(255) NULL,
    amein        VARCHAR(255) NULL,
    block        VARCHAR(255) NULL,
    cost_center  VARCHAR(255) NULL,
    auc          VARCHAR(255) NULL,
    order_number VARCHAR(255) NULL,
    block_lc     VARCHAR(255) NULL,
    block_immature VARCHAR(255) NULL,
    block_scout  VARCHAR(255) NULL,
    block_mature VARCHAR(255) NULL,
    actreg       VARCHAR(255) NULL,
    amein2       VARCHAR(255) NULL,
    wrk_grp      VARCHAR(255) NULL,
    dtwbs        VARCHAR(255) NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE sap_employee_out (
    id           BIGSERIAL    PRIMARY KEY,
    company_id   BIGINT       NOT NULL REFERENCES m_company(id),
    bukrs        VARCHAR(255) NULL,
    estnr        VARCHAR(255) NULL,
    prfnr        VARCHAR(255) NULL,
    empnr        VARCHAR(255) NULL,
    ename        VARCHAR(255) NULL,
    cname        VARCHAR(255) NULL,
    kdatb        VARCHAR(255) NULL,
    kdate        VARCHAR(255) NULL,
    jbcde        VARCHAR(255) NULL,
    jbtyp        VARCHAR(255) NULL,
    kunnr        VARCHAR(255) NULL,
    resdt        VARCHAR(255) NULL,
    sex          VARCHAR(255) NULL,
    stats        VARCHAR(255) NULL,
    divnr        VARCHAR(255) NULL,
    wopxd        VARCHAR(255) NULL,
    depnr        VARCHAR(255) NULL,
    lifnr        VARCHAR(255) NULL,
    created_at   TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE sap_block_out (
    id          BIGSERIAL    PRIMARY KEY,
    company_id  BIGINT       NOT NULL REFERENCES m_company(id),
    bukrs       VARCHAR(255) NULL,
    estnr       VARCHAR(255) NULL,
    divnr       VARCHAR(255) NULL,
    block       VARCHAR(255) NULL,
    kdatb       VARCHAR(255) NULL,
    kdate       VARCHAR(255) NULL,
    plblk       VARCHAR(255) NULL,
    bname       VARCHAR(255) NULL,
    initl       VARCHAR(255) NULL,
    maint       VARCHAR(255) NULL,
    bstate      VARCHAR(255) NULL,
    bha         VARCHAR(255) NULL,
    crop_type   VARCHAR(255) NULL,
    point       VARCHAR(255) NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE sap_estate_out (
    id         BIGSERIAL    PRIMARY KEY,
    company_id BIGINT       NOT NULL REFERENCES m_company(id),
    bukrs      VARCHAR(255) NULL,
    estnr      VARCHAR(255) NULL,
    kdatb      VARCHAR(255) NULL,
    kdate      VARCHAR(255) NULL,
    rgnnr      VARCHAR(255) NULL,
    name1      VARCHAR(255) NULL,
    werks      VARCHAR(255) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE sap_division_out (
    id         BIGSERIAL    PRIMARY KEY,
    company_id BIGINT       NOT NULL REFERENCES m_company(id),
    bukrs      VARCHAR(255) NULL,
    estnr      VARCHAR(255) NULL,
    divnr      VARCHAR(255) NULL,
    kdatb      VARCHAR(255) NULL,
    kdate      VARCHAR(255) NULL,
    name1      VARCHAR(255) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE sap_material_out (
    id         BIGSERIAL    PRIMARY KEY,
    company_id BIGINT       NOT NULL REFERENCES m_company(id),
    matnr      VARCHAR(255) NULL,
    maktx      VARCHAR(255) NULL,
    werks      VARCHAR(255) NULL,
    meins      VARCHAR(255) NULL,
    lgort      VARCHAR(255) NULL,
    charg      VARCHAR(255) NULL,
    matkl      VARCHAR(255) NULL,
    mtart      VARCHAR(255) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE sap_vendor_out (
    id         BIGSERIAL    PRIMARY KEY,
    company_id BIGINT       NOT NULL REFERENCES m_company(id),
    lifnr      VARCHAR(255) NULL,
    name1      VARCHAR(255) NULL,
    werks      VARCHAR(255) NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ============================================================
-- TIER 7: MASTER DATA TRACKING
-- ============================================================

-- ────────────────────────────────────────────────────────
-- TABLE: master_data_setting (per company)
-- Source: master_data_setting
-- ────────────────────────────────────────────────────────
CREATE TABLE master_data_setting (
    id                       BIGSERIAL    PRIMARY KEY,
    company_id               BIGINT       NOT NULL REFERENCES m_company(id),
    menu_name                VARCHAR(255) NULL,
    table_name               VARCHAR(255) NULL,
    sap_params               VARCHAR(255) NULL,
    sap_table_name           VARCHAR(255) NULL,
    last_refresh_at          TIMESTAMP    NULL,
    last_updated_at          TIMESTAMP    NULL,
    last_updated_by          BIGINT       NULL,
    is_replaced              BOOLEAN      NOT NULL DEFAULT true,
    is_enabled               BOOLEAN      NOT NULL DEFAULT true,
    refreshed_fields         VARCHAR(255) NULL
);

-- ────────────────────────────────────────────────────────
-- TABLE: master_data_log (per company)
-- Source: master_data_log
-- ────────────────────────────────────────────────────────
CREATE TABLE master_data_log (
    id                  BIGSERIAL    PRIMARY KEY,
    company_id          BIGINT       NOT NULL REFERENCES m_company(id),
    table_name          VARCHAR(255) NULL,
    last_refresh_at     TIMESTAMP    NULL,
    last_updated_at     TIMESTAMP    NULL,
    last_updated_by     BIGINT       NULL,
    is_replaced         BOOLEAN      NOT NULL DEFAULT true
);

-- ============================================================
-- TIER 8: PLANNING TABLES (per company)
-- ============================================================

-- ────────────────────────────────────────────────────────
-- TABLE: t_workplan (per company)
-- Source: t_workplan
-- ────────────────────────────────────────────────────────
CREATE TABLE t_workplan (
    id                        VARCHAR(100) PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    workplan_date             DATE         NULL,
    estate_code               VARCHAR(50)  NULL,
    division_code             VARCHAR(50)  NULL,
    activity_code             VARCHAR(50)  NULL,
    activity_name             VARCHAR(255) NULL,
    block_code                VARCHAR(50)  NULL,
    order_number              VARCHAR(100) NULL,
    auc_number                VARCHAR(100) NULL,
    cost_center               VARCHAR(100) NULL,
    wbs_code                  VARCHAR(100) NULL,
    mandor_employee_code      VARCHAR(100) NULL,
    mandor_employee_name      VARCHAR(150) NULL,
    total_hk                  INTEGER      NULL,
    total_qty_target          FLOAT8       NULL,
    is_approved               SMALLINT     NOT NULL DEFAULT 0,
    approved_by               VARCHAR(100) NULL,
    approved_by_name          VARCHAR(150) NULL,
    approved_at               TIMESTAMP    NULL,
    approval_remark           VARCHAR(255) NULL,
    is_closed                 BOOLEAN      NOT NULL DEFAULT false,
    created_by                VARCHAR(100) NULL,
    created_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                VARCHAR(100) NULL,
    updated_at                TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE t_workplan_material (
    id              BIGSERIAL    PRIMARY KEY,
    company_id      BIGINT       NOT NULL REFERENCES m_company(id),
    workplan_id     VARCHAR(100) NOT NULL,
    material_code   VARCHAR(100) NOT NULL,
    material_name   VARCHAR(255) NULL,
    qty             FLOAT8       NULL
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_harvesting_plan (per company)
-- Source: t_harvesting_plan
-- ────────────────────────────────────────────────────────
CREATE TABLE t_harvesting_plan (
    id                    BIGSERIAL    PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    plan_date             DATE         NULL,
    estate_code           VARCHAR(50)  NULL,
    division_code         VARCHAR(50)  NULL,
    block_code            VARCHAR(50)  NULL,
    total_hk              INTEGER      NULL,
    qty_target            INTEGER      NULL,
    ha                    VARCHAR(50)  NULL,
    assistant_emp_code    VARCHAR(100) NULL,
    assistant_emp_name    VARCHAR(150) NULL,
    is_approved           SMALLINT     NULL,
    approved_by           VARCHAR(100) NULL,
    approved_by_name      VARCHAR(150) NULL,
    approval_remark       VARCHAR(255) NULL,
    approved_at           TIMESTAMP    NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_coconut_harvesting_plan (per company)
-- Source: t_coconut_harvesting_plan
-- ────────────────────────────────────────────────────────
CREATE TABLE t_coconut_harvesting_plan (
    id                    BIGSERIAL    PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    plan_date             DATE         NULL,
    estate_code           VARCHAR(50)  NULL,
    division_code         VARCHAR(50)  NULL,
    block_code            VARCHAR(50)  NULL,
    total_hk              INTEGER      NULL,
    qty_target            INTEGER      NULL,
    ha                    VARCHAR(50)  NULL,
    assistant_emp_code    VARCHAR(100) NULL,
    assistant_emp_name    VARCHAR(150) NULL,
    is_approved           SMALLINT     NULL,
    approved_by           VARCHAR(100) NULL,
    approved_by_name      VARCHAR(150) NULL,
    approval_remark       VARCHAR(255) NULL,
    approved_at           TIMESTAMP    NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: tr_gi_plan (per company) - GI Plan header
-- Source: tr_gi_plan
-- ────────────────────────────────────────────────────────
CREATE TABLE tr_gi_plan (
    id                    VARCHAR(100) PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    plan_date             DATE         NULL,
    estate_code           VARCHAR(50)  NULL,
    division_code         VARCHAR(50)  NULL,
    plant_code            VARCHAR(50)  NULL,
    sloc_code             VARCHAR(50)  NULL,
    movement_type         VARCHAR(50)  NULL,
    is_approved           SMALLINT     NOT NULL DEFAULT 0,
    approved_by           VARCHAR(100) NULL,
    approved_by_name      VARCHAR(150) NULL,
    approved_at           TIMESTAMP    NULL,
    approval_remark       VARCHAR(255) NULL,
    integration_status    SMALLINT     NOT NULL DEFAULT -1,
    request_id            VARCHAR(100) NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE tr_gi_plan_detail (
    id              BIGSERIAL    PRIMARY KEY,
    company_id      BIGINT       NOT NULL REFERENCES m_company(id),
    gi_plan_id      VARCHAR(100) NOT NULL,
    material_code   VARCHAR(100) NULL,
    material_name   VARCHAR(255) NULL,
    qty             FLOAT8       NULL,
    uom             VARCHAR(50)  NULL,
    cost_center     VARCHAR(100) NULL,
    wbs_code        VARCHAR(100) NULL,
    order_number    VARCHAR(100) NULL
);

-- ============================================================
-- TIER 9: TRANSACTION TABLES (per company)
-- ============================================================

-- ────────────────────────────────────────────────────────
-- TABLE: t_attendance (per company)
-- Source: t_attendance
-- ────────────────────────────────────────────────────────
CREATE TABLE t_attendance (
    id                           BIGSERIAL    PRIMARY KEY,
    company_id                   BIGINT       NOT NULL REFERENCES m_company(id),
    attendance_date              DATE         NOT NULL,
    mandor_employee_code         VARCHAR(100) NULL,
    mandor_employee_name         VARCHAR(150) NULL,
    employee_code                VARCHAR(100) NOT NULL,
    employee_name                VARCHAR(150) NULL,
    attendance_code              VARCHAR(50)  NULL,
    attendance_desc              VARCHAR(255) NULL,
    work_status                  SMALLINT     NULL,
    gang_allotment_code          VARCHAR(50)  NULL,
    is_closed                    BOOLEAN      NOT NULL DEFAULT false,
    closing_is_approved          BOOLEAN      NOT NULL DEFAULT false,
    closing_approved_by          VARCHAR(100) NULL,
    closing_approved_at          TIMESTAMP    NULL,
    adjustment_status            SMALLINT     NOT NULL DEFAULT 0,
    integration_status           SMALLINT     NOT NULL DEFAULT -1,
    remark                       VARCHAR(255) NULL,
    request_id                   VARCHAR(100) NULL,
    created_by                   VARCHAR(100) NULL,
    created_at                   TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                   VARCHAR(100) NULL,
    updated_at                   TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_oph (per company) - OPH Palm
-- Source: t_oph (62 cols → normalized)
-- ────────────────────────────────────────────────────────
CREATE TABLE t_oph (
    id                        VARCHAR(100) PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    oph_card_id               VARCHAR(100) NULL,
    harvest_method            SMALLINT     NULL,
    estate_code               VARCHAR(50)  NOT NULL,
    plant_code                VARCHAR(50)  NULL,
    division_code             VARCHAR(50)  NOT NULL,
    block_code                VARCHAR(50)  NOT NULL,
    tph_code                  VARCHAR(50)  NULL,
    platform_no               VARCHAR(10)  NULL,
    lat                       VARCHAR(50)  NULL,
    long                      VARCHAR(50)  NULL,
    photo                     VARCHAR(255) NULL,
    notes                     VARCHAR(255) NULL,
    mandor_employee_code      VARCHAR(100) NULL,
    mandor_employee_name      VARCHAR(150) NULL,
    kerani_panen_employee_code VARCHAR(100) NULL,
    kerani_panen_employee_name VARCHAR(150) NULL,
    customer_code             VARCHAR(100) NULL,
    -- Bunch counts
    bunches_wet               INTEGER      NULL,
    bunches_ripe              INTEGER      NULL,
    bunches_overripe          INTEGER      NULL,
    bunches_underripe         INTEGER      NULL,
    bunches_unripe            INTEGER      NULL,
    bunches_rotten            INTEGER      NULL,
    bunches_long_stalk        INTEGER      NULL,
    bunches_empty             INTEGER      NULL,
    bunches_dirty             INTEGER      NULL,
    bunches_unfresh           INTEGER      NULL,
    bunches_old               INTEGER      NULL,
    bunches_pest_damaged      INTEGER      NULL,
    bunches_small             INTEGER      NULL,
    bunches_diseased          INTEGER      NULL,
    bunches_dura              INTEGER      NULL,
    bunches_total             INTEGER      NULL,
    bunches_not_sent          INTEGER      NULL,
    loose_fruits              FLOAT8       NULL,
    -- Status flags
    is_planned                BOOLEAN      NOT NULL DEFAULT false,
    is_approved               BOOLEAN      NOT NULL DEFAULT false,
    approved_by               VARCHAR(100) NULL,
    approved_by_name          VARCHAR(150) NULL,
    approved_at               TIMESTAMP    NULL,
    is_restant_permanent      BOOLEAN      NOT NULL DEFAULT false,
    is_closed                 BOOLEAN      NOT NULL DEFAULT false,
    closing_is_approved       BOOLEAN      NOT NULL DEFAULT false,
    closing_approved_by       VARCHAR(100) NULL,
    closing_approved_at       TIMESTAMP    NULL,
    is_deleted                BOOLEAN      NOT NULL DEFAULT false,
    adjustment_status         SMALLINT     NOT NULL DEFAULT 0,
    integration_status        SMALLINT     NOT NULL DEFAULT -1,
    remark                    VARCHAR(255) NULL,
    request_id                VARCHAR(100) NULL,
    created_by                VARCHAR(100) NOT NULL,
    created_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                VARCHAR(100) NULL,
    updated_at                TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE t_oph_persons (
    id                        BIGSERIAL    PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    oph_id                    VARCHAR(100) NOT NULL,
    employee_code             VARCHAR(100) NOT NULL,
    employee_name             VARCHAR(150) NULL,
    percentage                FLOAT8       NULL,
    total_bunches             FLOAT8       NULL,
    estimate_tonnage          FLOAT8       NULL,
    person_type               SMALLINT     NULL,
    employee_type             SMALLINT     NULL
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_cp / t_checkpoint (per company) - Checkpoint Palm
-- Source: t_cp (61 cols → normalized)
-- ────────────────────────────────────────────────────────
CREATE TABLE t_cp (
    id                        VARCHAR(100) PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code               VARCHAR(50)  NULL,
    division_code             VARCHAR(50)  NULL,
    license_number            VARCHAR(100) NULL,
    license_number2           VARCHAR(100) NULL,
    seal_code                 VARCHAR(100) NULL,
    receiving_point_code      VARCHAR(50)  NULL,
    delivery_note             VARCHAR(100) NULL,
    lat                       VARCHAR(50)  NULL,
    long                      VARCHAR(50)  NULL,
    photo                     VARCHAR(255) NULL,
    total_bunches             INTEGER      NULL,
    total_oph                 INTEGER      NULL,
    total_loose_fruit         FLOAT8       NULL,
    estimate_tonnage          FLOAT8       NULL,
    actual_tonnage            FLOAT8       NULL,
    bruto                     FLOAT8       NULL,
    tarra                     FLOAT8       NULL,
    bin_number                INTEGER      NULL,
    cp_type                   SMALLINT     NULL,
    kerani_kirim_emp_code     VARCHAR(100) NULL,
    kerani_kirim_emp_name     VARCHAR(150) NULL,
    -- Grading bunches
    bunches_wet               INTEGER      NULL,
    bunches_ripe              INTEGER      NULL,
    bunches_overripe          INTEGER      NULL,
    bunches_underripe         INTEGER      NULL,
    bunches_unripe            INTEGER      NULL,
    bunches_rotten            INTEGER      NULL,
    bunches_long_stalk        INTEGER      NULL,
    bunches_empty             INTEGER      NULL,
    bunches_dirty             INTEGER      NULL,
    bunches_unfresh           INTEGER      NULL,
    bunches_old               INTEGER      NULL,
    bunches_pest_damaged_old  INTEGER      NULL,
    bunches_pest_damaged_new  INTEGER      NULL,
    bunches_diseased          INTEGER      NULL,
    bunches_total             INTEGER      NULL,
    -- Transporter
    transporter               SMALLINT     NULL,
    transporter2              SMALLINT     NULL,
    vendor_code               TEXT         NULL,
    vendor_name               TEXT         NULL,
    license_number_vendor     VARCHAR(100) NULL,
    license_number_vendor2    VARCHAR(100) NULL,
    sailing_date              DATE         NULL,
    ship_flag                 BOOLEAN      NOT NULL DEFAULT false,
    cable_way                 BOOLEAN      NOT NULL DEFAULT false,
    -- Status
    is_closed                 BOOLEAN      NOT NULL DEFAULT false,
    closing_is_approved       BOOLEAN      NOT NULL DEFAULT false,
    closing_approved_by       VARCHAR(100) NULL,
    closing_approved_at       TIMESTAMP    NULL,
    is_deleted                BOOLEAN      NOT NULL DEFAULT false,
    adjustment_status         SMALLINT     NOT NULL DEFAULT 0,
    integration_status        SMALLINT     NOT NULL DEFAULT -1,
    remark                    VARCHAR(255) NULL,
    request_id                VARCHAR(100) NULL,
    created_by                VARCHAR(100) NULL,
    created_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                VARCHAR(100) NULL,
    updated_at                TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE t_cp_detail (
    id                       BIGSERIAL    PRIMARY KEY,
    company_id               BIGINT       NOT NULL REFERENCES m_company(id),
    cp_id                    VARCHAR(100) NOT NULL,
    oph_id                   VARCHAR(100) NOT NULL,
    oph_block_code           VARCHAR(50)  NOT NULL,
    oph_tph_code             VARCHAR(50)  NOT NULL,
    oph_card_id              VARCHAR(100) NULL,
    oph_platform_no          VARCHAR(10)  NULL,
    bunches_delivered        INTEGER      NOT NULL,
    loose_fruit_delivered    FLOAT8       NOT NULL,
    detail_type              SMALLINT     NULL,
    integration_status       SMALLINT     NOT NULL DEFAULT -1,
    remark                   VARCHAR(255) NULL
);

CREATE TABLE t_cp_loader (
    id                  BIGSERIAL    PRIMARY KEY,
    company_id          BIGINT       NOT NULL REFERENCES m_company(id),
    cp_id               VARCHAR(100) NOT NULL,
    employee_code       VARCHAR(100) NULL,
    employee_name       VARCHAR(150) NOT NULL,
    vendor_code         VARCHAR(100) NULL,
    transporter         SMALLINT     NULL,
    percentage          FLOAT8       NOT NULL DEFAULT 0,
    loader_type         SMALLINT     NOT NULL DEFAULT 0,
    integration_status  SMALLINT     NOT NULL DEFAULT -1,
    request_id          VARCHAR(100) NULL
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_fdn (per company) - FDN Palm
-- Source: t_fdn (64 cols → normalized)
-- ────────────────────────────────────────────────────────
CREATE TABLE t_fdn (
    id                        VARCHAR(100) PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    fdn_card_id               VARCHAR(100) NULL,
    estate_code               VARCHAR(50)  NULL,
    division_code             VARCHAR(50)  NULL,
    license_number            VARCHAR(100) NULL,
    license_number2           VARCHAR(100) NULL,
    seal_code                 VARCHAR(100) NULL,
    deliver_to_code           VARCHAR(100) NULL,
    deliver_to_name           VARCHAR(150) NULL,
    delivery_note             VARCHAR(100) NULL,
    lat                       VARCHAR(50)  NULL,
    long                      VARCHAR(50)  NULL,
    photo                     VARCHAR(255) NULL,
    driver_name               VARCHAR(150) NULL,
    total_bunches             INTEGER      NULL,
    total_oph                 INTEGER      NULL,
    total_loose_fruit         FLOAT8       NULL,
    estimate_tonnage          FLOAT8       NULL,
    actual_tonnage            FLOAT8       NULL,
    bruto                     FLOAT8       NULL,
    tarra                     FLOAT8       NULL,
    bin_number                INTEGER      NULL,
    fdn_type                  SMALLINT     NULL,
    kerani_kirim_emp_code     VARCHAR(100) NULL,
    kerani_kirim_emp_name     VARCHAR(150) NULL,
    vendor_code               TEXT         NULL,
    vendor_name               TEXT         NULL,
    license_number_vendor     VARCHAR(100) NULL,
    license_number_vendor2    VARCHAR(100) NULL,
    transporter               SMALLINT     NULL,
    transporter2              SMALLINT     NULL,
    sailing_date              DATE         NULL,
    ship_flag                 BOOLEAN      NOT NULL DEFAULT false,
    cable_way                 BOOLEAN      NOT NULL DEFAULT false,
    sales_order_no            VARCHAR(100) NULL,
    sales_order_item          VARCHAR(50)  NULL,
    is_closed                 BOOLEAN      NOT NULL DEFAULT false,
    closing_is_approved       BOOLEAN      NOT NULL DEFAULT false,
    closing_approved_by       VARCHAR(100) NULL,
    closing_approved_at       TIMESTAMP    NULL,
    is_deleted                BOOLEAN      NOT NULL DEFAULT false,
    adjustment_status         SMALLINT     NOT NULL DEFAULT 0,
    integration_status        SMALLINT     NOT NULL DEFAULT -1,
    remark                    VARCHAR(255) NULL,
    request_id                VARCHAR(100) NULL,
    created_by                VARCHAR(100) NULL,
    created_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                VARCHAR(100) NULL,
    updated_at                TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE t_fdn_detail (
    id                       BIGSERIAL    PRIMARY KEY,
    company_id               BIGINT       NOT NULL REFERENCES m_company(id),
    fdn_id                   VARCHAR(100) NOT NULL,
    oph_id                   VARCHAR(100) NOT NULL,
    oph_block_code           VARCHAR(50)  NOT NULL,
    oph_tph_code             VARCHAR(50)  NOT NULL,
    oph_card_id              VARCHAR(100) NULL,
    bunches_delivered        INTEGER      NOT NULL,
    loose_fruit_delivered    FLOAT8       NOT NULL,
    detail_type              SMALLINT     NULL,
    integration_status       SMALLINT     NOT NULL DEFAULT -1,
    remark                   VARCHAR(255) NULL
);

CREATE TABLE t_fdn_loader (
    id                  BIGSERIAL    PRIMARY KEY,
    company_id          BIGINT       NOT NULL REFERENCES m_company(id),
    fdn_id              VARCHAR(100) NOT NULL,
    employee_code       VARCHAR(100) NULL,
    employee_name       VARCHAR(150) NOT NULL,
    vendor_code         VARCHAR(100) NULL,
    transporter         SMALLINT     NULL,
    percentage          FLOAT8       NOT NULL DEFAULT 0,
    loader_type         SMALLINT     NOT NULL DEFAULT 0,
    integration_status  SMALLINT     NOT NULL DEFAULT -1,
    request_id          VARCHAR(100) NULL
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_harvesting_deduction (per company)
-- Source: t_harvesting_deduction
-- ────────────────────────────────────────────────────────
CREATE TABLE t_harvesting_deduction (
    id                        BIGSERIAL    PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    deduction_date            DATE         NOT NULL,
    oph_id                    VARCHAR(100) NULL,
    deduction_code            VARCHAR(50)  NOT NULL,
    deduction_description     VARCHAR(255) NULL,
    deduction_qty             FLOAT8       NULL,
    created_by                VARCHAR(100) NULL,
    created_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                VARCHAR(100) NULL,
    updated_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    integration_status        SMALLINT     NOT NULL DEFAULT -1,
    request_id                VARCHAR(100) NULL,
    remark                    VARCHAR(255) NULL
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_workdone / t_work_assignment (per company)
-- Source: t_workdone, t_work_assignment
-- ────────────────────────────────────────────────────────
CREATE TABLE t_workdone (
    id                    VARCHAR(100) PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    workdone_date         DATE         NOT NULL,
    estate_code           VARCHAR(50)  NULL,
    plant_code            VARCHAR(50)  NULL,
    division_code         VARCHAR(50)  NULL,
    activity_code         VARCHAR(50)  NULL,
    activity_name         VARCHAR(255) NULL,
    activity_uom          VARCHAR(50)  NULL,
    block_code            VARCHAR(50)  NULL,
    order_number          VARCHAR(100) NULL,
    auc_number            VARCHAR(100) NULL,
    cost_center           VARCHAR(100) NULL,
    wbs_code              VARCHAR(100) NULL,
    mandor_employee_code  VARCHAR(100) NULL,
    mandor_employee_name  VARCHAR(150) NULL,
    employee_code         VARCHAR(100) NULL,
    employee_name         VARCHAR(150) NULL,
    mandays               FLOAT8       NULL,
    manday                FLOAT8       NULL,
    qty                   FLOAT8       NULL,
    target_qty            FLOAT8       NULL,
    flexrate              FLOAT8       NULL,
    start_time            TIME         NULL,
    end_time              TIME         NULL,
    duration              FLOAT8       NULL,
    description           VARCHAR(255) NULL,
    customer_code         VARCHAR(100) NULL,
    is_planned            BOOLEAN      NOT NULL DEFAULT false,
    is_approved           BOOLEAN      NOT NULL DEFAULT false,
    approved_by           VARCHAR(100) NULL,
    approved_by_name      VARCHAR(150) NULL,
    approved_at           TIMESTAMP    NULL,
    is_closed             BOOLEAN      NOT NULL DEFAULT false,
    integration_status    SMALLINT     NOT NULL DEFAULT -1,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE t_workdone_material (
    id              BIGSERIAL    PRIMARY KEY,
    company_id      BIGINT       NOT NULL REFERENCES m_company(id),
    workdone_id     VARCHAR(100) NOT NULL,
    material_code   VARCHAR(100) NOT NULL,
    material_name   VARCHAR(255) NULL,
    qty             FLOAT8       NULL
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_overtime (per company)
-- Source: t_overtime (44 cols)
-- ────────────────────────────────────────────────────────
CREATE TABLE t_overtime (
    id                    VARCHAR(100) PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    overtime_date         DATE         NOT NULL,
    estate_code           VARCHAR(50)  NULL,
    division_code         VARCHAR(50)  NULL,
    mandor_employee_code  VARCHAR(100) NULL,
    mandor_employee_name  VARCHAR(150) NULL,
    employee_code         VARCHAR(100) NOT NULL,
    employee_name         VARCHAR(150) NULL,
    activity_code         VARCHAR(50)  NULL,
    activity_name         VARCHAR(255) NULL,
    block_code            VARCHAR(50)  NULL,
    order_number          VARCHAR(100) NULL,
    cost_center           VARCHAR(100) NULL,
    start_time            TIME         NULL,
    end_time              TIME         NULL,
    duration_hours        FLOAT8       NULL,
    is_approved           BOOLEAN      NOT NULL DEFAULT false,
    approved_by           VARCHAR(100) NULL,
    approved_at           TIMESTAMP    NULL,
    is_closed             BOOLEAN      NOT NULL DEFAULT false,
    integration_status    SMALLINT     NOT NULL DEFAULT -1,
    remark                VARCHAR(255) NULL,
    request_id            VARCHAR(100) NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_vra (per company) - VRA transaction
-- Source: t_vra
-- ────────────────────────────────────────────────────────
CREATE TABLE t_vra (
    id                    VARCHAR(100) PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    vra_date              DATE         NULL,
    estate_code           VARCHAR(50)  NULL,
    license_number        VARCHAR(100) NULL,
    equipment_code        VARCHAR(100) NULL,
    order_number          VARCHAR(100) NULL,
    meas_point            VARCHAR(100) NULL,
    reading_value         FLOAT8       NULL,
    confirmation_text     VARCHAR(255) NULL,
    plant_code            VARCHAR(50)  NULL,
    is_approved           BOOLEAN      NOT NULL DEFAULT false,
    approved_by           VARCHAR(100) NULL,
    approved_at           TIMESTAMP    NULL,
    integration_status    SMALLINT     NOT NULL DEFAULT -1,
    remark                VARCHAR(255) NULL,
    request_id            VARCHAR(100) NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_abw (per company)
-- Source: t_abw
-- ────────────────────────────────────────────────────────
CREATE TABLE t_abw (
    id              BIGSERIAL    PRIMARY KEY,
    company_id      BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code     VARCHAR(50)  NULL,
    block_code      VARCHAR(50)  NULL,
    period_year     VARCHAR(10)  NULL,
    period_month    VARCHAR(10)  NULL,
    bunch_weight    FLOAT8       NULL,
    posting_date    DATE         NULL,
    sample_date     DATE         NULL,
    created_by      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by      VARCHAR(100) NULL,
    updated_at      TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_platform_checking (per company)
-- Source: t_platform_checking
-- ────────────────────────────────────────────────────────
CREATE TABLE t_platform_checking (
    id              BIGSERIAL    PRIMARY KEY,
    company_id      BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code     VARCHAR(50)  NULL,
    division_code   VARCHAR(50)  NULL,
    block_code      VARCHAR(50)  NULL,
    tph_code        VARCHAR(50)  NULL,
    check_date      DATE         NULL,
    check_status    VARCHAR(50)  NULL,
    notes           TEXT         NULL,
    created_by      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by      VARCHAR(100) NULL,
    updated_at      TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE t_platform_checking_detail (
    id                      BIGSERIAL    PRIMARY KEY,
    company_id              BIGINT       NOT NULL REFERENCES m_company(id),
    platform_checking_id    BIGINT       NOT NULL REFERENCES t_platform_checking(id),
    detail_type             VARCHAR(50)  NULL,
    detail_value            VARCHAR(255) NULL
);

-- ============================================================
-- TIER 10: COCONUT TRANSACTIONS (per company)
-- ============================================================

CREATE TABLE t_coconut_oph (
    id                         VARCHAR(100) PRIMARY KEY,
    company_id                 BIGINT       NOT NULL REFERENCES m_company(id),
    plant_code                 VARCHAR(50)  NOT NULL,
    estate_code                VARCHAR(50)  NOT NULL,
    division_code              VARCHAR(50)  NOT NULL,
    block_code                 VARCHAR(50)  NOT NULL,
    tph_code                   VARCHAR(50)  NOT NULL,
    oph_card_id                VARCHAR(100) NULL,
    gang_code                  VARCHAR(50)  NULL,
    gang_name                  VARCHAR(150) NULL,
    checker_employee_code      VARCHAR(100) NOT NULL,
    checker_employee_name      VARCHAR(150) NULL,
    notes                      VARCHAR(255) NULL,
    lat                        VARCHAR(50)  NULL,
    long                       VARCHAR(50)  NULL,
    photo                      VARCHAR(255) NULL,
    nuts_total                 INTEGER      NULL,
    is_planned                 BOOLEAN      NOT NULL DEFAULT false,
    is_approved                BOOLEAN      NOT NULL DEFAULT false,
    approved_by                VARCHAR(100) NULL,
    approved_by_name           VARCHAR(150) NULL,
    approved_at                TIMESTAMP    NULL,
    is_closed                  BOOLEAN      NOT NULL DEFAULT false,
    closing_is_approved        BOOLEAN      NOT NULL DEFAULT false,
    closing_approved_by        VARCHAR(100) NULL,
    closing_approved_at        TIMESTAMP    NULL,
    is_deleted                 BOOLEAN      NOT NULL DEFAULT false,
    adjustment_status          SMALLINT     NOT NULL DEFAULT 0,
    integration_status         SMALLINT     NOT NULL DEFAULT -1,
    remark                     VARCHAR(255) NULL,
    request_id                 VARCHAR(100) NULL,
    created_by                 VARCHAR(100) NOT NULL,
    created_at                 TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                 VARCHAR(100) NULL,
    updated_at                 TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE t_coconut_oph_detail (
    id                              BIGSERIAL    PRIMARY KEY,
    company_id                      BIGINT       NOT NULL REFERENCES m_company(id),
    coconut_oph_id                  VARCHAR(100) NOT NULL,
    material_code                   VARCHAR(100) NOT NULL,
    material_name                   VARCHAR(255) NOT NULL,
    customer_nut_qty                FLOAT8       NOT NULL,
    is_locked                       BOOLEAN      NOT NULL DEFAULT false,
    closing_is_approved             BOOLEAN      NOT NULL DEFAULT false,
    closing_approved_by             VARCHAR(100) NULL,
    closing_approved_at             TIMESTAMP    NULL,
    is_deleted                      BOOLEAN      NOT NULL DEFAULT false,
    adjustment_status               SMALLINT     NOT NULL DEFAULT 0,
    integration_status              SMALLINT     NOT NULL DEFAULT -1,
    remark                          VARCHAR(255) NULL,
    request_id                      VARCHAR(100) NULL
);

CREATE TABLE t_coconut_oph_persons (
    id                        BIGSERIAL    PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    coconut_oph_id            VARCHAR(100) NOT NULL,
    employee_code             VARCHAR(100) NULL,
    employee_name             VARCHAR(150) NULL,
    activity_type             CHAR(1)      NULL,
    integration_status        SMALLINT     NOT NULL DEFAULT -1,
    remark                    VARCHAR(255) NULL
);

CREATE TABLE t_coconut_oph_harvesting_deduction (
    id                        BIGSERIAL    PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    deduction_date            DATE         NOT NULL,
    coconut_oph_id            VARCHAR(100) NULL,
    material_code             VARCHAR(100) NOT NULL,
    material_description      VARCHAR(255) NULL,
    qty                       FLOAT8       NULL,
    deduction_type            SMALLINT     NULL,
    created_by                VARCHAR(100) NULL,
    created_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                VARCHAR(100) NULL,
    updated_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    integration_status        SMALLINT     NOT NULL DEFAULT -1,
    request_id                VARCHAR(100) NULL,
    remark                    VARCHAR(255) NULL
);

CREATE TABLE t_coconut_fdn (
    id                            VARCHAR(100) PRIMARY KEY,
    company_id                    BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code                   VARCHAR(50)  NULL,
    division_code                 VARCHAR(50)  NULL,
    sales_order_no                VARCHAR(100) NULL,
    sales_order_item              VARCHAR(50)  NULL,
    receiving_point_code          VARCHAR(50)  NULL,
    license_number                VARCHAR(100) NULL,
    driver_name                   VARCHAR(150) NULL,
    vehicle_vendor_code           VARCHAR(100) NULL,
    kerani_kirim_emp_code         VARCHAR(100) NULL,
    kerani_kirim_emp_name         VARCHAR(150) NULL,
    delivery_note                 VARCHAR(100) NULL,
    fdn_card_id                   VARCHAR(100) NULL,
    lat                           VARCHAR(50)  NULL,
    long                          VARCHAR(50)  NULL,
    photo                         VARCHAR(255) NULL,
    total_oph                     INTEGER      NULL,
    bruto                         FLOAT8       NULL,
    tarra                         FLOAT8       NULL,
    actual_tonnage                FLOAT8       NULL,
    total_customer_qty            FLOAT8       NULL,
    destination                   VARCHAR(100) NULL,
    is_nursery                    BOOLEAN      NULL,
    is_stock                      BOOLEAN      NOT NULL DEFAULT false,
    is_closed                     BOOLEAN      NOT NULL DEFAULT false,
    closing_is_approved           BOOLEAN      NOT NULL DEFAULT false,
    closing_approved_by           VARCHAR(100) NULL,
    closing_approved_at           TIMESTAMP    NULL,
    is_deleted                    BOOLEAN      NOT NULL DEFAULT false,
    adjustment_status             SMALLINT     NOT NULL DEFAULT 0,
    integration_status            SMALLINT     NOT NULL DEFAULT -1,
    remark                        VARCHAR(255) NULL,
    request_id                    VARCHAR(100) NULL,
    created_by                    VARCHAR(100) NULL,
    created_at                    TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                    VARCHAR(100) NULL,
    updated_at                    TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE t_coconut_fdn_detail (
    id                               BIGSERIAL    PRIMARY KEY,
    company_id                       BIGINT       NOT NULL REFERENCES m_company(id),
    coconut_fdn_id                   VARCHAR(100) NOT NULL,
    coconut_oph_id                   VARCHAR(100) NOT NULL,
    coconut_oph_card_id              VARCHAR(100) NULL,
    total_customer_nut_qty           FLOAT8       NULL,
    integration_status               SMALLINT     NOT NULL DEFAULT -1,
    remark                           VARCHAR(255) NULL
);

-- ============================================================
-- TIER 11: MILL GRADER & GI/GR TRANSACTIONS (per company)
-- ============================================================

CREATE TABLE t_mill_grader_oph (
    id                        VARCHAR(100) PRIMARY KEY,
    company_id                BIGINT       NOT NULL REFERENCES m_company(id),
    estate_code               VARCHAR(50)  NULL,
    plant_code                VARCHAR(50)  NULL,
    division_code             VARCHAR(50)  NULL,
    block_code                VARCHAR(50)  NULL,
    tph_code                  VARCHAR(50)  NULL,
    grader_employee_code      VARCHAR(100) NULL,
    grader_employee_name      VARCHAR(150) NULL,
    is_approved               BOOLEAN      NOT NULL DEFAULT false,
    approved_by               VARCHAR(100) NULL,
    approved_at               TIMESTAMP    NULL,
    is_closed                 BOOLEAN      NOT NULL DEFAULT false,
    integration_status        SMALLINT     NOT NULL DEFAULT -1,
    remark                    VARCHAR(255) NULL,
    request_id                VARCHAR(100) NULL,
    created_by                VARCHAR(100) NULL,
    created_at                TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                VARCHAR(100) NULL,
    updated_at                TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE t_oph_mill_grader_persons (
    id                  BIGSERIAL    PRIMARY KEY,
    company_id          BIGINT       NOT NULL REFERENCES m_company(id),
    mill_grader_oph_id  VARCHAR(100) NOT NULL,
    employee_code       VARCHAR(100) NOT NULL,
    employee_name       VARCHAR(150) NULL
);

-- GI (Goods Issue) transaction
CREATE TABLE tr_gi_header (
    id                    VARCHAR(100) PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    gi_date               DATE         NULL,
    estate_code           VARCHAR(50)  NULL,
    plant_code            VARCHAR(50)  NULL,
    sloc_code             VARCHAR(50)  NULL,
    movement_type         VARCHAR(50)  NULL,
    gi_document_number    VARCHAR(100) NULL,
    is_approved           BOOLEAN      NOT NULL DEFAULT false,
    approved_by           VARCHAR(100) NULL,
    approved_at           TIMESTAMP    NULL,
    integration_status    SMALLINT     NOT NULL DEFAULT -1,
    request_id            VARCHAR(100) NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE tr_gi_detail (
    id                  BIGSERIAL    PRIMARY KEY,
    company_id          BIGINT       NOT NULL REFERENCES m_company(id),
    gi_header_id        VARCHAR(100) NOT NULL,
    material_code       VARCHAR(100) NULL,
    material_name       VARCHAR(255) NULL,
    qty                 FLOAT8       NULL,
    uom                 VARCHAR(50)  NULL,
    cost_center         VARCHAR(100) NULL,
    wbs_code            VARCHAR(100) NULL,
    order_number        VARCHAR(100) NULL,
    gl_account          VARCHAR(100) NULL,
    integration_status  SMALLINT     NOT NULL DEFAULT -1
);

-- GR (Goods Receipt) transaction
CREATE TABLE tr_gr_header (
    id                    VARCHAR(100) PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    gr_date               DATE         NULL,
    plant_code            VARCHAR(50)  NULL,
    sloc_code             VARCHAR(50)  NULL,
    po_number             VARCHAR(100) NULL,
    gr_document_number    VARCHAR(100) NULL,
    integration_status    SMALLINT     NOT NULL DEFAULT -1,
    request_id            VARCHAR(100) NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE tr_gr_detail (
    id                  BIGSERIAL    PRIMARY KEY,
    company_id          BIGINT       NOT NULL REFERENCES m_company(id),
    gr_header_id        VARCHAR(100) NOT NULL,
    material_code       VARCHAR(100) NULL,
    material_name       VARCHAR(255) NULL,
    qty                 FLOAT8       NULL,
    uom                 VARCHAR(50)  NULL,
    integration_status  SMALLINT     NOT NULL DEFAULT -1
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_harvester_assignment (per company)
-- ────────────────────────────────────────────────────────
CREATE TABLE t_harvester_assignment (
    id                       BIGSERIAL    PRIMARY KEY,
    company_id               BIGINT       NOT NULL REFERENCES m_company(id),
    assignment_date          DATE         NOT NULL,
    estate_code              VARCHAR(50)  NULL,
    division_code            VARCHAR(50)  NULL,
    mandor_employee_code     VARCHAR(100) NULL,
    mandor_employee_name     VARCHAR(150) NULL,
    harvester_employee_code  VARCHAR(100) NOT NULL,
    harvester_employee_name  VARCHAR(150) NULL,
    block_code               VARCHAR(50)  NULL,
    tph_code                 VARCHAR(50)  NULL,
    created_by               VARCHAR(100) NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by               VARCHAR(100) NULL,
    updated_at               TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_general_worker_assignment (per company)
-- ────────────────────────────────────────────────────────
CREATE TABLE t_general_worker_assignment (
    id                       BIGSERIAL    PRIMARY KEY,
    company_id               BIGINT       NOT NULL REFERENCES m_company(id),
    assignment_date          DATE         NOT NULL,
    estate_code              VARCHAR(50)  NULL,
    division_code            VARCHAR(50)  NULL,
    mandor_employee_code     VARCHAR(100) NULL,
    mandor_employee_name     VARCHAR(150) NULL,
    worker_employee_code     VARCHAR(100) NOT NULL,
    worker_employee_name     VARCHAR(150) NULL,
    activity_code            VARCHAR(50)  NULL,
    activity_name            VARCHAR(255) NULL,
    block_code               VARCHAR(50)  NULL,
    order_number             VARCHAR(100) NULL,
    created_by               VARCHAR(100) NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by               VARCHAR(100) NULL,
    updated_at               TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ────────────────────────────────────────────────────────
-- TABLE: t_oph_supervise (per company)
-- ────────────────────────────────────────────────────────
CREATE TABLE t_oph_supervise (
    id                       BIGSERIAL    PRIMARY KEY,
    company_id               BIGINT       NOT NULL REFERENCES m_company(id),
    supervise_date           DATE         NOT NULL,
    estate_code              VARCHAR(50)  NULL,
    division_code            VARCHAR(50)  NULL,
    supervisor_employee_code VARCHAR(100) NULL,
    supervisor_employee_name VARCHAR(150) NULL,
    oph_id                   VARCHAR(100) NULL,
    block_code               VARCHAR(50)  NULL,
    notes                    TEXT         NULL,
    is_approved              BOOLEAN      NOT NULL DEFAULT false,
    approved_by              VARCHAR(100) NULL,
    approved_at              TIMESTAMP    NULL,
    integration_status       SMALLINT     NOT NULL DEFAULT -1,
    created_by               VARCHAR(100) NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by               VARCHAR(100) NULL,
    updated_at               TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ============================================================
-- TIER 12: APPROVAL LOGS (per company)
-- ============================================================

CREATE TABLE log_workplan_approval (
    id                    BIGSERIAL    PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    workplan_id           VARCHAR(100) NOT NULL,
    approval_status       SMALLINT     NULL,
    approval_remark       VARCHAR(255) NULL,
    approved_by           VARCHAR(100) NOT NULL,
    approved_by_name      VARCHAR(150) NOT NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE log_harvesting_plan_approval (
    id                    BIGSERIAL    PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    harvesting_plan_id    BIGINT       NOT NULL,
    approval_status       SMALLINT     NULL,
    approval_remark       VARCHAR(255) NULL,
    approved_by           VARCHAR(100) NOT NULL,
    approved_by_name      VARCHAR(150) NOT NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE log_coconut_harvesting_plan_approval (
    id                           BIGSERIAL    PRIMARY KEY,
    company_id                   BIGINT       NOT NULL REFERENCES m_company(id),
    coconut_harvesting_plan_id   BIGINT       NOT NULL,
    approval_status              SMALLINT     NULL,
    approval_remark              VARCHAR(255) NULL,
    approved_by                  VARCHAR(100) NOT NULL,
    approved_by_name             VARCHAR(150) NOT NULL,
    created_by                   VARCHAR(100) NULL,
    created_at                   TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by                   VARCHAR(100) NULL,
    updated_at                   TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE log_giplan_approval (
    id                    BIGSERIAL    PRIMARY KEY,
    company_id            BIGINT       NOT NULL REFERENCES m_company(id),
    giplan_id             VARCHAR(100) NOT NULL,
    approval_status       SMALLINT     NULL,
    approval_remark       VARCHAR(255) NULL,
    approved_by           VARCHAR(100) NOT NULL,
    approved_by_name      VARCHAR(150) NOT NULL,
    created_by            VARCHAR(100) NULL,
    created_at            TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by            VARCHAR(100) NULL,
    updated_at            TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE approval_substitution (
    id                       BIGSERIAL    PRIMARY KEY,
    company_id               BIGINT       NOT NULL REFERENCES m_company(id),
    employee_code            VARCHAR(100) NULL,
    employee_name            VARCHAR(150) NULL,
    target_employee_code     VARCHAR(100) NULL,
    target_employee_name     VARCHAR(150) NULL,
    substitution_from        DATE         NULL,
    substitution_to          DATE         NULL,
    substitution_type        SMALLINT     NULL,
    created_by               VARCHAR(100) NULL,
    created_at               TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by               VARCHAR(100) NULL,
    updated_at               TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ============================================================
-- TIER 13: AUDIT & SYSTEM LOGS (per company)
-- ============================================================

CREATE TABLE audit_trail (
    id                  BIGSERIAL    PRIMARY KEY,
    company_id          BIGINT       NOT NULL REFERENCES m_company(id),
    transaction_type    SMALLINT     NULL,
    action_type         SMALLINT     NULL,
    user_code           VARCHAR(100) NULL,
    user_name           VARCHAR(150) NULL,
    description         TEXT         NULL,
    created_by          VARCHAR(100) NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT NOW()
);

CREATE TABLE mail_scheduler (
    id              BIGSERIAL    PRIMARY KEY,
    company_id      BIGINT       NOT NULL REFERENCES m_company(id),
    mail_type       SMALLINT     NULL,
    mail_from       VARCHAR(255) NULL,
    mail_to         VARCHAR(255) NULL,
    mail_subject    VARCHAR(255) NULL,
    mail_content    TEXT         NULL,
    is_sent         BOOLEAN      NOT NULL DEFAULT false,
    created_by      VARCHAR(100) NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT NOW(),
    updated_by      VARCHAR(100) NULL,
    updated_at      TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- Temporary response data buffer (no company needed)
CREATE TABLE res_data (
    id            BIGSERIAL    PRIMARY KEY,
    res_text      TEXT         NOT NULL,
    res_timestamp TIMESTAMP    NOT NULL DEFAULT NOW()
);

-- ============================================================
-- TIER 14: DEFAULT SEED DATA
-- ============================================================

-- Countries
INSERT INTO m_country (code, name, prefix) VALUES
    ('MY', 'Malaysia',   '1'),
    ('ID', 'Indonesia',  '2');

-- Roles
INSERT INTO m_roles (role_code, role_name, level, required_system_type) VALUES
    ('super_admin',            'Super Admin',              10, NULL),
    ('country_admin',          'Country Admin',            20, NULL),
    ('company_admin',          'Company Admin',            30, NULL),
    ('estate_manager',         'Estate Manager',           40, NULL),
    ('asst_manager',           'Assistant Manager',        50, NULL),
    ('estate_staff',           'Estate Staff',             60, NULL),
    ('staff',                  'Staff',                    60, NULL),
    ('it_staff',               'IT Staff',                 60, NULL),
    ('mill_grader',            'Mill Grader',              70, NULL),
    ('warehouse_clerk',        'Warehouse Clerk',          70, NULL),
    ('store_clerk',            'Store Clerk',              70, NULL),
    ('store_it',               'Store IT',                 70, NULL),
    ('checker_palm',           'Checker (Palm)',           70, 'palm'),
    ('ramp_dispatch_palm',     'Ramp - Dispatch (Palm)',   70, 'palm'),
    ('checker_coconut',        'Checker (Coconut)',        70, 'coconut'),
    ('ramp_dispatch_coconut',  'Ramp - Dispatch (Coconut)',70, 'coconut');

-- Crop types
INSERT INTO crop_type (code, name, can_harvest) VALUES
    ('PALM',    'Palm Oil',  true),
    ('COCONUT', 'Coconut',   true),
    ('RUBBER',  'Rubber',    true),
    ('DURIAN',  'Durian',    true);

-- ============================================================
-- END OF DDL: epms_l
-- ============================================================
