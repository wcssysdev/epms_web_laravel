-- ============================================================
-- DDL: sagil_mei (source) → epms_l (target)
-- Generated: 2026-09-01 07:30:31
-- Total tables: 145
-- ============================================================


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_ACTIVITY_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_ACTIVITY_OUT (
    activity_id BIGSERIAL NOT NULL DEFAULT nextval('"ZPAY_ACTVT_id_seq"'::regclass),
    ACTVT_NO VARCHAR(255) NULL,
    ACTVT_NAME VARCHAR(255) NULL,
    AMEIN VARCHAR(255) NULL,
    BLOCK VARCHAR(255) NULL,
    COST_CENTER VARCHAR(255) NULL,
    AUC VARCHAR(255) NULL,
    ORDER_NUMBER VARCHAR(255) NULL,
    BLOCK_LC VARCHAR(255) NULL,
    BLOCK_IMMATURE VARCHAR(255) NULL,
    BLOCK_SCOUT VARCHAR(255) NULL,
    BLOCK_MATURE VARCHAR(255) NULL,
    ACTREG VARCHAR(255) NULL,
    AMEIN2 VARCHAR(255) NULL,
    WRK_GRP VARCHAR(255) NULL,
    DTWBS VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_ACTIVITY_OUT_pkey PRIMARY KEY (activity_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_ATDCD_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_ATDCD_OUT (
    attendance_id BIGSERIAL NOT NULL DEFAULT nextval('"ZZATDCD_attendance_id_seq"'::regclass),
    CODE VARCHAR(255) NULL,
    DESC VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_ATDCD_OUT_pkey PRIMARY KEY (attendance_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EMPLOYEE_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EMPLOYEE_OUT (
    employee_id BIGSERIAL NOT NULL DEFAULT nextval('"ZPAY_EMPLOYEE_employee_id_seq"'::regclass),
    BUKRS VARCHAR(255) NULL,
    ESTNR VARCHAR(255) NULL,
    PRFNR VARCHAR(255) NULL,
    EMPNR VARCHAR(255) NULL,
    ENAME VARCHAR(255) NULL,
    CNAME VARCHAR(255) NULL,
    KDATB VARCHAR(255) NULL,
    KDATE VARCHAR(255) NULL,
    JBCDE VARCHAR(255) NULL,
    JBTYP VARCHAR(255) NULL,
    KUNNR VARCHAR(255) NULL,
    RESDT VARCHAR(255) NULL,
    SEX VARCHAR(255) NULL,
    STATS VARCHAR(255) NULL,
    DIVNR VARCHAR(255) NULL,
    WOPXD VARCHAR(255) NULL,
    DEPNR VARCHAR(255) NULL,
    LIFNR VARCHAR(255) NULL DEFAULT ''::character varying,
    CONSTRAINT ZEPMS_EMPLOYEE_OUT_pkey PRIMARY KEY (employee_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_ABW_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_ABW_OUT (
    abw_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_ADJ_KOM_abw_id_seq"'::regclass),
    BUKRS VARCHAR(255) NULL,
    ESTNR VARCHAR(255) NULL,
    SPMON VARCHAR(255) NULL,
    BLOCK VARCHAR(255) NULL,
    CRDAT VARCHAR(255) NULL,
    SAMPLE_DT VARCHAR(255) NULL,
    KOMDL VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_ABW_OUT_pkey PRIMARY KEY (abw_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_BLOCK_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_BLOCK_OUT (
    block_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_BLOCK_block_id_seq"'::regclass),
    BUKRS VARCHAR(255) NULL,
    ESTNR VARCHAR(255) NULL,
    DIVNR VARCHAR(255) NULL,
    BLOCK VARCHAR(255) NULL,
    KDATB VARCHAR(255) NULL,
    KDATE VARCHAR(255) NULL,
    PLBLK VARCHAR(255) NULL,
    BNAME VARCHAR(255) NULL,
    INITL VARCHAR(255) NULL,
    MAINT VARCHAR(255) NULL,
    BSTATE VARCHAR(255) NULL,
    BHA VARCHAR(255) NULL,
    CROP_TYPE VARCHAR(255) NULL,
    POINT VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_BLOCK_OUT_pkey PRIMARY KEY (block_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_COST_CENTER_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_COST_CENTER_OUT (
    cc_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_CC_cc_id_seq"'::regclass),
    BUKRS VARCHAR(255) NULL,
    KOSTL VARCHAR(255) NULL,
    DATAB VARCHAR(255) NULL,
    DATBI VARCHAR(255) NULL,
    GSBER VARCHAR(255) NULL,
    LTEXT VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_COST_CENTER_OUT_pkey PRIMARY KEY (cc_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_DESTINATION_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_DESTINATION_OUT (
    destination_id BIGSERIAL NOT NULL DEFAULT nextval('"T001W_destination_id_seq"'::regclass),
    WERKS VARCHAR(255) NULL,
    NAME1 VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_DESTINATION_OUT_pkey PRIMARY KEY (destination_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_DIVISION_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_DIVISION_OUT (
    division_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_DIVISION_division_id_seq"'::regclass),
    BUKRS VARCHAR(255) NULL,
    ESTNR VARCHAR(255) NULL,
    DIVNR VARCHAR(255) NULL,
    KDATB VARCHAR(255) NULL,
    KDATE VARCHAR(255) NULL,
    NAME1 VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_DIVISION_OUT_pkey PRIMARY KEY (division_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_ESTATE_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_ESTATE_OUT (
    estate_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_ESTATE_estate_id_seq"'::regclass),
    BUKRS VARCHAR(255) NULL,
    ESTNR VARCHAR(255) NULL,
    KDATB VARCHAR(255) NULL,
    KDATE VARCHAR(255) NULL,
    RGNNR VARCHAR(255) NULL,
    NAME1 VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_ESTATE_OUT_pkey PRIMARY KEY (estate_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_GENERAL_ORDER_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_GENERAL_ORDER_OUT (
    mo_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_MO_mo_id_seq"'::regclass),
    EBELN VARCHAR(255) NULL,
    EBELP VARCHAR(255) NULL,
    BSART VARCHAR(255) NULL,
    BEDAT VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    EKORG VARCHAR(255) NULL,
    EKGRP VARCHAR(255) NULL,
    LIFNR VARCHAR(255) NULL,
    NAME1 VARCHAR(255) NULL,
    MATNR VARCHAR(255) NULL,
    TXZ01 VARCHAR(255) NULL,
    MATKL VARCHAR(255) NULL,
    MENGE VARCHAR(255) NULL,
    MEINS VARCHAR(255) NULL,
    WAERS VARCHAR(255) NULL,
    NETPR VARCHAR(255) NULL,
    PEINH VARCHAR(255) NULL,
    BPRME VARCHAR(255) NULL,
    NETWR VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_GENERAL_ORDER_OUT_pkey PRIMARY KEY (mo_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_GL_ACCOUNT_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_GL_ACCOUNT_OUT (
    m_glacc_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_GL_ACCOUNT_gl_account_id_seq"'::regclass),
    BUKRS VARCHAR(255) NULL,
    SAKNR VARCHAR(255) NULL,
    TXT50 VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_GL_ACCOUNT_OUT_pkey PRIMARY KEY (m_glacc_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_MATERIAL_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_MATERIAL_OUT (
    material_id BIGSERIAL NOT NULL DEFAULT nextval('"M_MATERIAL_material_id_seq"'::regclass),
    MATNR VARCHAR(255) NULL,
    MAKTX VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    MEINS VARCHAR(255) NULL,
    LGORT VARCHAR(255) NULL,
    CHARG VARCHAR(255) NULL,
    MATKL VARCHAR(255) NULL,
    MTART VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_MATERIAL_OUT_pkey PRIMARY KEY (material_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_MATERIAL_OUT_NON_PALM
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_MATERIAL_OUT_NON_PALM (
    non_palm_material_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEPMS_EM_MATERIAL_OUT_NON_PALM_non_palm_material_id_seq"'::regclass),
    MATNR VARCHAR(255) NULL,
    MAKTX VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    MEINS VARCHAR(255) NULL,
    CHARG VARCHAR(255) NULL,
    LGORT VARCHAR(255) NULL,
    MATKL VARCHAR(255) NULL,
    MTART VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_MATERIAL_OUT_NON_PALM_pkey PRIMARY KEY (non_palm_material_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_MATERIAL_STOCK_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_MATERIAL_STOCK_OUT (
    material_stock_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_MATERIAL_STOCK_material_stock_id_seq"'::regclass),
    BUKRS VARCHAR(255) NULL,
    ESTNR VARCHAR(255) NULL,
    KDATB VARCHAR(255) NULL,
    KDATE VARCHAR(255) NULL,
    NAME1 VARCHAR(255) NULL,
    ACC VARCHAR(255) NULL,
    ACCGRP VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_MATERIAL_STOCK_OUT_pkey PRIMARY KEY (material_stock_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_MEAS_POINT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_MEAS_POINT (
    meas_point_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_MEAS__POINT_meas_point_id_seq"'::regclass),
    BUKRS VARCHAR(255) NULL,
    ESTNR VARCHAR(255) NULL,
    DIVNR VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    AUFNR VARCHAR(255) NULL,
    EQUNR VARCHAR(255) NULL,
    OBJNR VARCHAR(255) NULL,
    POINT VARCHAR(255) NULL,
    UNITTXT VARCHAR(255) NULL,
    PTTXT VARCHAR(255) NULL,
    MAINT VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_MEAS_POINT_pkey PRIMARY KEY (meas_point_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_PURCHASE_ORDER_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_PURCHASE_ORDER_OUT (
    purchase_order_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_PO_po_id_seq"'::regclass),
    EBELN VARCHAR(255) NULL,
    EBELP VARCHAR(255) NULL,
    BSART VARCHAR(255) NULL,
    BEDAT VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    EKORG VARCHAR(255) NULL,
    EKGRP VARCHAR(255) NULL,
    LIFNR VARCHAR(255) NULL,
    NAME1 VARCHAR(255) NULL,
    MATNR VARCHAR(255) NULL,
    TXZ01 VARCHAR(255) NULL,
    MATKL VARCHAR(255) NULL,
    MENGE VARCHAR(255) NULL,
    MEINS VARCHAR(255) NULL,
    WAERS VARCHAR(255) NULL,
    NETPR VARCHAR(255) NULL,
    PEINH VARCHAR(255) NULL,
    BPRME VARCHAR(255) NULL,
    NETWR VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_PURCHASE_ORDER_OUT_pkey PRIMARY KEY (purchase_order_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_RECEIVING_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_RECEIVING_OUT (
    ramp_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_RAMP_ramp_id_seq"'::regclass),
    VALUE VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_RECEIVING_OUT_pkey PRIMARY KEY (ramp_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_SLOC_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_SLOC_OUT (
    sloc_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_SLOC_sloc_id_seq"'::regclass),
    LGORT VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    LGOBE VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_SLOC_OUT_pkey PRIMARY KEY (sloc_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_VENDOR_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_VENDOR_OUT (
    vendor_id BIGSERIAL NOT NULL DEFAULT nextval('zepms_em_vendor_out_vendor_id_seq'::regclass),
    LIFNR VARCHAR(255) NULL,
    NAME1 VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_VENDOR_OUT_pkey PRIMARY KEY (vendor_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_VRA_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_VRA_OUT (
    vra_id BIGSERIAL NOT NULL DEFAULT nextval('"temp_VRA_vra_id_seq"'::regclass),
    EQUNR VARCHAR(255) NULL,
    DATAB VARCHAR(255) NULL,
    DATBI VARCHAR(255) NULL,
    SWERK VARCHAR(255) NULL,
    LICENSE_NUM VARCHAR(255) NULL,
    AUFNR VARCHAR(255) NULL,
    AUART VARCHAR(255) NULL,
    AUTYP VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    EQART VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_VRA_OUT_pkey PRIMARY KEY (vra_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_EM_WORK_CENTER_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_EM_WORK_CENTER_OUT (
    work_center_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEST_WORK_CENTER_work_center_id_seq"'::regclass),
    BUKRS VARCHAR(255) NULL,
    ESTNR VARCHAR(255) NULL,
    DIVNR VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    ARBPL VARCHAR(255) NULL,
    KTEXT VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_EM_WORK_CENTER_OUT_pkey PRIMARY KEY (work_center_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_GIGR_WBS_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_GIGR_WBS_OUT (
    wbs_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEPMS_WBS_wbs_id_seq"'::regclass),
    PSPNR VARCHAR(255) NULL,
    POSID_EDIT VARCHAR(255) NULL,
    PBUKR VARCHAR(255) NULL,
    PGSBR VARCHAR(255) NULL,
    ESTNR VARCHAR(255) NULL,
    DIVNR VARCHAR(255) NULL,
    BLOCK VARCHAR(255) NULL,
    WRK_TYPE VARCHAR(255) NULL,
    POST1 VARCHAR(255) NULL,
    SAKNR VARCHAR(255) NULL,
    TXT50 VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_GIGR_WBS_OUT_pkey PRIMARY KEY (wbs_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_GRADING_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_GRADING_OUT (
    deduction_code_id BIGSERIAL NOT NULL DEFAULT nextval('"ZPAY_DP_RATE_harvesting_deduction_id_seq"'::regclass),
    DTYPE VARCHAR(255) NULL,
    KDATB VARCHAR(255) NULL,
    KDATE VARCHAR(255) NULL,
    DPDES VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_GRADING_OUT_pkey PRIMARY KEY (deduction_code_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_MAINTENANCE_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_MAINTENANCE_OUT (
    mo_id BIGSERIAL NOT NULL DEFAULT nextval('"temp_MAINT_mo_id_seq"'::regclass),
    AUFNR VARCHAR(255) NULL,
    AUART VARCHAR(255) NULL,
    KTEXT VARCHAR(255) NULL,
    GSBER VARCHAR(255) NULL,
    BUKRS VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_MAINTENANCE_OUT_pkey PRIMARY KEY (mo_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_MEMBER_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_MEMBER_OUT (
    user_assignment_id BIGSERIAL NOT NULL DEFAULT nextval('"ZPAY_MEMB_user_assignment_id_seq"'::regclass),
    EMPNR VARCHAR(255) NULL,
    KDATB VARCHAR(255) NULL,
    KDATE VARCHAR(255) NULL,
    EMPNR_M VARCHAR(255) NULL,
    PRFNR VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_MEMBER_OUT_pkey PRIMARY KEY (user_assignment_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_PM_WORKTYPE_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_PM_WORKTYPE_OUT (
    worktype_id BIGINT NOT NULL,
    GRUND VARCHAR(255) NULL,
    GRDTX VARCHAR(255) NULL,
    WERKS VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_PM_WORKTYPE_OUT_pkey PRIMARY KEY (worktype_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_SD_SORD_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_SD_SORD_OUT (
    sales_order_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEPMS_SD_SORD_OUT_sales_order_id_seq"'::regclass),
    WERKS VARCHAR(255) NULL,
    VBELN VARCHAR(255) NULL,
    POSNR VARCHAR(255) NULL,
    BSTNK VARCHAR(255) NULL,
    KUNNR VARCHAR(255) NULL,
    C_NAME1 VARCHAR(255) NULL,
    C_NAME2 VARCHAR(255) NULL,
    MATNR VARCHAR(255) NULL,
    KWMENG VARCHAR(255) NULL,
    VRKME VARCHAR(255) NULL,
    ZTERM VARCHAR(255) NULL,
    INCO1 VARCHAR(255) NULL,
    INCO2 VARCHAR(255) NULL,
    ABGRU VARCHAR(255) NULL,
    ARKTX VARCHAR(255) NULL,
    AEDAT VARCHAR(255) NULL,
    AENAM VARCHAR(255) NULL,
    AEZET VARCHAR(255) NULL,
    ERDAT VARCHAR(255) NULL,
    ERNAM VARCHAR(255) NULL,
    ERZET VARCHAR(255) NULL,
    MANDT VARCHAR(255) NULL,
    TYPE VARCHAR(255) NULL,
    ZSENT VARCHAR(255) NULL,
    AUDAT VARCHAR(255) NULL,
    REQUEST_ID VARCHAR(255) NULL,
    UNIQUE_ID VARCHAR(255) NULL,
    STATE VARCHAR(255) NULL,
    is_replaced SMALLINT NULL DEFAULT 0,
    CONSTRAINT ZEPMS_SD_SORD_OUT_pkey PRIMARY KEY (sales_order_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: ZEPMS_WBS_OUT
-- ────────────────────────────────────────────────────────
CREATE TABLE ZEPMS_WBS_OUT (
    wbs_id BIGSERIAL NOT NULL DEFAULT nextval('"ZEPMS_WBS_OUT_wbs_id_seq"'::regclass),
    POSID VARCHAR(255) NULL,
    POST1 VARCHAR(255) NULL,
    GRPID VARCHAR(255) NULL,
    GRPDS VARCHAR(255) NULL,
    CONSTRAINT ZEPMS_WBS_OUT_pkey PRIMARY KEY (wbs_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: approval_substitution
-- ────────────────────────────────────────────────────────
CREATE TABLE approval_substitution (
    approval_substitution_id BIGSERIAL NOT NULL DEFAULT nextval('approval_substitution_approval_substitution_id_seq'::regclass),
    approval_substitution_employee_code VARCHAR(255) NULL,
    approval_substitution_employee_name VARCHAR(255) NULL,
    approval_substitution_employee_code_target VARCHAR(255) NULL,
    approval_substitution_employee_name_target VARCHAR(255) NULL,
    approval_substitution_from DATE NULL,
    approval_substitution_to DATE NULL,
    approval_created_by VARCHAR(255) NULL,
    approval_created_date DATE NULL,
    approval_created_time TIME WITHOUT TIME ZONE NULL,
    approval_updated_by VARCHAR(255) NULL,
    approval_updated_date DATE NULL,
    approval_updated_time TIME WITHOUT TIME ZONE NULL,
    approval_substitution_type SMALLINT NULL,
    CONSTRAINT approval_substitution_pkey PRIMARY KEY (approval_substitution_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: audit_trail
-- ────────────────────────────────────────────────────────
CREATE TABLE audit_trail (
    audit_trail_id VARCHAR(255) NOT NULL DEFAULT nextval('audit_trail_audit_trail_id_seq'::regclass),
    audit_trail_transaction SMALLINT NULL,
    audit_trail_type SMALLINT NULL,
    audit_trail_user_code VARCHAR(255) NULL,
    audit_trail_user_name VARCHAR(255) NULL,
    audit_trail_description TEXT NULL,
    audit_trail_created_by VARCHAR(255) NULL,
    audit_trail_created_date DATE NULL,
    audit_trail_created_time TIME WITHOUT TIME ZONE NULL,
    audit_trail_updated_by VARCHAR(255) NULL,
    audit_trail_updated_date DATE NULL,
    audit_trail_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT audit_trail_pkey PRIMARY KEY (audit_trail_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: crop_type
-- ────────────────────────────────────────────────────────
CREATE TABLE crop_type (
    croptypecode VARCHAR(255) NOT NULL,
    croptypename VARCHAR(255) NULL,
    description VARCHAR(255) NULL,
    canharvest VARCHAR(10) NULL,
    CONSTRAINT crop_type_pkey PRIMARY KEY (croptypecode)
);


-- ────────────────────────────────────────────────────────
-- TABLE: int2t_workdone
-- ────────────────────────────────────────────────────────
CREATE TABLE int2t_workdone (
    workdone_id VARCHAR(255) NOT NULL,
    workdone_date DATE NOT NULL,
    workdone_estate_code VARCHAR(255) NULL,
    workdone_plant_code VARCHAR(255) NULL,
    workdone_division_code VARCHAR(255) NULL,
    workdone_activity_code VARCHAR(255) NULL,
    workdone_activity_name VARCHAR(255) NULL,
    workdone_activity_uom VARCHAR(255) NULL,
    workdone_mandays FLOAT8 NULL,
    workdone_qty FLOAT8 NULL,
    workdone_remark VARCHAR(255) NULL,
    workdone_mandor_employee_code VARCHAR(255) NULL,
    workdone_mandor_employee_name VARCHAR(255) NULL,
    workdone_employee_code VARCHAR(255) NULL,
    workdone_employee_name VARCHAR(255) NULL,
    workdone_block_code VARCHAR(255) NULL,
    workdone_order_number VARCHAR(255) NULL,
    workdone_auc_number VARCHAR(255) NULL,
    workdone_cost_center VARCHAR(255) NULL,
    workdone_flexrate FLOAT8 NULL,
    workdone_start_time TIME WITHOUT TIME ZONE NULL,
    workdone_end_time TIME WITHOUT TIME ZONE NULL,
    workdone_duration FLOAT8 NULL,
    workdone_description VARCHAR(255) NULL,
    workdone_customer_code VARCHAR(255) NULL,
    workdone_is_planned SMALLINT NULL,
    workdone_is_approved SMALLINT NULL,
    workdone_approved_by VARCHAR(255) NULL,
    workdone_approved_by_name VARCHAR(255) NULL,
    workdone_approved_date DATE NULL,
    workdone_approved_time TIME WITHOUT TIME ZONE NULL,
    workdone_is_closed SMALLINT NOT NULL DEFAULT 0,
    workdone_created_by VARCHAR(255) NULL,
    workdone_created_date DATE NULL,
    workdone_created_time TIME WITHOUT TIME ZONE NULL,
    workdone_updated_by VARCHAR(255) NULL,
    workdone_updated_date DATE NULL,
    workdone_updated_time TIME WITHOUT TIME ZONE NULL,
    workdone_manday FLOAT8 NULL,
    workdone_target_qty FLOAT8 NULL,
    integration_status SMALLINT NULL,
    CONSTRAINT int2t_workdone_pkey PRIMARY KEY (workdone_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: log_coconut_harvesting_plan_approval
-- ────────────────────────────────────────────────────────
CREATE TABLE log_coconut_harvesting_plan_approval (
    coconut_harvesting_plan_approval_id BIGSERIAL NOT NULL DEFAULT nextval('log_coconut_harvesting_plan_a_coconut_harvesting_plan_appro_seq'::regclass),
    coconut_harvesting_plan_id BIGINT NOT NULL,
    coconut_harvesting_plan_approval_status SMALLINT NULL,
    coconut_harvesting_plan_approval_remark VARCHAR(255) NULL,
    coconut_harvesting_plan_approval_by VARCHAR(255) NOT NULL,
    coconut_harvesting_plan_approval_by_name VARCHAR(255) NOT NULL,
    coconut_harvesting_plan_approval_created_by VARCHAR(255) NULL,
    coconut_harvesting_plan_approval_created_date DATE NULL,
    coconut_harvesting_plan_approval_created_time TIME WITHOUT TIME ZONE NULL,
    coconut_harvesting_plan_approval_updated_by VARCHAR(255) NULL,
    coconut_harvesting_plan_approval_updated_date DATE NULL,
    coconut_harvesting_plan_approval_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT log_coconut_harvesting_plan_approval_pkey PRIMARY KEY (coconut_harvesting_plan_approval_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: log_giplan_approval
-- ────────────────────────────────────────────────────────
CREATE TABLE log_giplan_approval (
    log_giplan_approval_id BIGSERIAL NOT NULL DEFAULT nextval('log_giplan_approval_log_giplan_approval_id_seq'::regclass),
    giplan_id VARCHAR(255) NOT NULL,
    giplan_approval_status SMALLINT NULL,
    giplan_approval_remark VARCHAR(255) NULL,
    giplan_approval_by VARCHAR(255) NOT NULL,
    giplan_approval_by_name VARCHAR(255) NOT NULL,
    giplan_approval_created_by VARCHAR(255) NULL,
    giplan_approval_created_date DATE NULL,
    giplan_approval_created_time TIME WITHOUT TIME ZONE NULL,
    giplan_approval_updated_by VARCHAR(255) NULL,
    giplan_approval_updated_date DATE NULL,
    giplan_approval_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT log_giplan_approval_pkey PRIMARY KEY (log_giplan_approval_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: log_harvesting_plan_approval
-- ────────────────────────────────────────────────────────
CREATE TABLE log_harvesting_plan_approval (
    harvesting_plan_approval_id BIGSERIAL NOT NULL DEFAULT nextval('log_harvesting_plan_approval_harvesting_plan_approval_id_seq'::regclass),
    harvesting_plan_id BIGINT NOT NULL,
    harvesting_plan_approval_status SMALLINT NULL,
    harvesting_plan_approval_remark VARCHAR(255) NULL,
    harvesting_plan_approval_by VARCHAR(255) NOT NULL,
    harvesting_plan_approval_by_name VARCHAR(255) NOT NULL,
    harvesting_plan_approval_created_by VARCHAR(255) NULL,
    harvesting_plan_approval_created_date DATE NULL,
    harvesting_plan_approval_created_time TIME WITHOUT TIME ZONE NULL,
    harvesting_plan_approval_updated_by VARCHAR(255) NULL,
    harvesting_plan_approval_updated_date DATE NULL,
    harvesting_plan_approval_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT log_harvesting_plan_approval_pkey PRIMARY KEY (harvesting_plan_approval_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: log_system_lock
-- ────────────────────────────────────────────────────────
CREATE TABLE log_system_lock (
    lock_id BIGINT NOT NULL,
    is_locked BOOLEAN NOT NULL DEFAULT true,
    locked_at TIMESTAMP NOT NULL DEFAULT now(),
    unlocked_at TIMESTAMP NULL,
    unlocked_by BIGINT NULL,
    unlock_reason TEXT NULL,
    CONSTRAINT log_system_lock_pkey PRIMARY KEY (lock_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: log_workplan_approval
-- ────────────────────────────────────────────────────────
CREATE TABLE log_workplan_approval (
    log_workplan_approval_id BIGSERIAL NOT NULL DEFAULT nextval('log_workplan_approval_log_workplan_approval_id_seq'::regclass),
    workplan_id VARCHAR(255) NOT NULL,
    workplan_approval_status SMALLINT NULL,
    workplan_approval_remark VARCHAR(255) NULL,
    workplan_approval_by VARCHAR(255) NOT NULL,
    workplan_approval_by_name VARCHAR(255) NOT NULL,
    workplan_approval_created_by VARCHAR(255) NULL,
    workplan_approval_created_date DATE NULL,
    workplan_approval_created_time TIME WITHOUT TIME ZONE NULL,
    workplan_approval_updated_by VARCHAR(255) NULL,
    workplan_approval_updated_date DATE NULL,
    workplan_approval_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT log_workplan_approval_pkey PRIMARY KEY (log_workplan_approval_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: login_log
-- ────────────────────────────────────────────────────────
CREATE TABLE login_log (
    login_id BIGSERIAL NOT NULL DEFAULT nextval('login_log_login_id_seq'::regclass),
    login_device_id INTEGER NULL,
    login_employee_code VARCHAR(255) NULL,
    login_employee_name VARCHAR(255) NULL,
    logout_date DATE NULL,
    logout_time TIME WITHOUT TIME ZONE NULL,
    login_created_by VARCHAR(255) NULL,
    login_created_date DATE NULL,
    login_created_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT login_log_pkey PRIMARY KEY (login_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_activity
-- ────────────────────────────────────────────────────────
CREATE TABLE m_activity (
    activity_id BIGSERIAL NOT NULL DEFAULT nextval('m_activity_activity_id_seq'::regclass),
    activity_code VARCHAR(255) NULL,
    activity_name VARCHAR(255) NULL,
    activity_uom VARCHAR(255) NULL,
    activity_created_by VARCHAR(255) NULL,
    activity_created_date DATE NULL,
    activity_created_time TIME WITHOUT TIME ZONE NULL,
    activity_updated_by VARCHAR(255) NULL,
    activity_updated_date DATE NULL,
    activity_updated_time TIME WITHOUT TIME ZONE NULL,
    activity_cost_by_block SMALLINT NULL,
    activity_cost_by_auc SMALLINT NULL,
    activity_cost_by_order_number SMALLINT NULL,
    activity_cost_by_cost_center SMALLINT NULL,
    activity_block_is_lc SMALLINT NULL,
    activity_block_is_immature SMALLINT NULL,
    activity_block_is_mature SMALLINT NULL,
    activity_block_is_scout SMALLINT NULL,
    activity_uom_name VARCHAR(255) NULL,
    activity_group_code VARCHAR(255) NULL,
    activity_is_wbs_required BOOLEAN NULL,
    CONSTRAINT m_activity_pkey PRIMARY KEY (activity_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_assistant_manager_division
-- ────────────────────────────────────────────────────────
CREATE TABLE m_assistant_manager_division (
    assistant_manager_division_id BIGSERIAL NOT NULL DEFAULT nextval('m_assistant_manager_division_assistant_manager_division_id_seq'::regclass),
    assistant_manager_code VARCHAR(255) NULL,
    assistant_manager_name VARCHAR(255) NULL,
    assistant_manager_division_code VARCHAR(255) NULL,
    assistant_manager_division_name VARCHAR(255) NULL,
    assistant_manager_created_by VARCHAR(255) NULL,
    assistant_manager_created_date DATE NULL,
    assistant_manager_created_time TIME WITHOUT TIME ZONE NULL,
    assistant_manager_updated_by VARCHAR(255) NULL,
    assistant_manager_updated_date DATE NULL,
    assistant_manager_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_assistant_manager_division_pkey PRIMARY KEY (assistant_manager_division_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_attendance
-- ────────────────────────────────────────────────────────
CREATE TABLE m_attendance (
    attendance_id BIGSERIAL NOT NULL DEFAULT nextval('m_attendance_attendance_id_seq'::regclass),
    attendance_code VARCHAR(255) NULL,
    attendance_desc VARCHAR(255) NULL,
    attendance_created_by VARCHAR(255) NULL,
    attendance_created_date DATE NULL,
    attendance_created_time TIME WITHOUT TIME ZONE NULL,
    attendance_updated_by VARCHAR(255) NULL,
    attendance_updated_date DATE NULL,
    attendance_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_attendance_pkey PRIMARY KEY (attendance_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_bin
-- ────────────────────────────────────────────────────────
CREATE TABLE m_bin (
    bin_id BIGSERIAL NOT NULL DEFAULT nextval('m_bin_bin_id_seq'::regclass),
    bin_code VARCHAR(255) NULL,
    bin_created_by VARCHAR(255) NULL,
    bin_created_date DATE NULL,
    bin_created_time TIME WITHOUT TIME ZONE NULL,
    bin_updated_by VARCHAR(255) NULL,
    bin_updated_date DATE NULL,
    bin_updated_time TIME WITHOUT TIME ZONE NULL
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_block
-- ────────────────────────────────────────────────────────
CREATE TABLE m_block (
    block_id BIGSERIAL NOT NULL DEFAULT nextval('m_block_block_id_seq'::regclass),
    block_company_code VARCHAR(255) NULL,
    block_estate_code VARCHAR(255) NULL,
    block_division_code VARCHAR(255) NULL,
    block_code VARCHAR(255) NULL,
    block_name VARCHAR(255) NULL,
    block_hectarage FLOAT8 NULL,
    block_planted_date DATE NULL,
    block_valid_from DATE NULL,
    block_valid_to DATE NULL,
    block_created_by VARCHAR(255) NULL,
    block_created_date DATE NULL,
    block_created_time TIME WITHOUT TIME ZONE NULL,
    block_updated_by VARCHAR(255) NULL,
    block_updated_date DATE NULL,
    block_updated_time TIME WITHOUT TIME ZONE NULL,
    block_state VARCHAR(10) NULL,
    block_is_planted SMALLINT NULL,
    block_crop_type VARCHAR(255) NULL,
    block_total_palm BIGINT NOT NULL DEFAULT 0,
    CONSTRAINT m_block_pkey PRIMARY KEY (block_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_coconut_activity_type
-- ────────────────────────────────────────────────────────
CREATE TABLE m_coconut_activity_type (
    coconut_activity_type_id BIGSERIAL NOT NULL DEFAULT nextval('m_coconut_activity_type_coconut_activity_type_id_seq'::regclass),
    coconut_activity_type_code VARCHAR(255) NULL,
    coconut_activity_type_desc VARCHAR(255) NULL,
    coconut_activity_type_created_by VARCHAR(255) NULL,
    coconut_activity_type_created_date DATE NULL,
    coconut_activity_type_created_time TIME WITHOUT TIME ZONE NULL,
    coconut_activity_type_updated_by VARCHAR(255) NULL,
    coconut_activity_type_updated_date DATE NULL,
    coconut_activity_type_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_coconut_activity_type_pkey PRIMARY KEY (coconut_activity_type_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_config
-- ────────────────────────────────────────────────────────
CREATE TABLE m_config (
    config_id BIGSERIAL NOT NULL DEFAULT nextval('m_config_config_id_seq'::regclass),
    company_code VARCHAR(255) NULL,
    company_name VARCHAR(255) NULL,
    profile_code VARCHAR(255) NULL,
    profile_name VARCHAR(255) NULL,
    estate_code VARCHAR(255) NULL,
    estate_name VARCHAR(255) NULL,
    plant_code VARCHAR(255) NULL,
    cutter_distribution_value FLOAT8 NULL,
    carrier_distribution_value FLOAT8 NULL,
    created_by VARCHAR(255) NULL,
    created_date DATE NULL,
    created_time TIME WITHOUT TIME ZONE NULL,
    updated_by VARCHAR(255) NULL,
    updated_date DATE NULL,
    updated_time TIME WITHOUT TIME ZONE NULL,
    attendance_default_value VARCHAR(255) NULL,
    allowed_attandance_codes_for_work_assignment VARCHAR(255) NULL,
    sap_user_id VARCHAR(255) NULL,
    sap_password VARCHAR(255) NULL,
    integration_type SMALLINT NULL,
    have_internet_connection SMALLINT NULL,
    attendance_normal_default_value VARCHAR(255) NULL,
    sap_api_url VARCHAR(500) NULL,
    system_is_palm BOOLEAN NULL DEFAULT false,
    system_is_coconut BOOLEAN NULL DEFAULT false,
    system_is_durian BOOLEAN NULL DEFAULT false,
    system_is_rubber BOOLEAN NULL DEFAULT false,
    carrier_lf_distribution_value INTEGER NULL,
    cutter_lf_distribution_value INTEGER NULL,
    daily_overtime_max_limit SMALLINT NULL DEFAULT 3,
    additional_settings JSON NULL,
    country_code VARCHAR(2) NULL DEFAULT 'MY'::character varying,
    fdn_oph INTEGER NULL DEFAULT 0,
    is_fixed_platform BOOLEAN NULL,
    max_oph_restan SMALLINT NULL DEFAULT 0,
    is_lock_system BOOLEAN NULL DEFAULT false,
    CONSTRAINT m_config_pkey PRIMARY KEY (config_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_confirmation_text
-- ────────────────────────────────────────────────────────
CREATE TABLE m_confirmation_text (
    ctext_id BIGSERIAL NOT NULL DEFAULT nextval('m_confirmation_text_ctext_id_seq'::regclass),
    ctext_company_code VARCHAR(255) NULL,
    ctext_estate_code VARCHAR(255) NULL,
    ctext_code VARCHAR(255) NULL,
    ctext_text VARCHAR(255) NULL,
    ctext_desc VARCHAR(255) NULL,
    ctext_created_by VARCHAR(255) NULL,
    ctext_created_date DATE NULL,
    ctext_created_time TIME WITHOUT TIME ZONE NULL,
    ctext_updated_by VARCHAR(255) NULL,
    ctext_updated_date DATE NULL,
    ctext_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_confirmation_text_pkey PRIMARY KEY (ctext_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_cost_center
-- ────────────────────────────────────────────────────────
CREATE TABLE m_cost_center (
    cc_id BIGINT NOT NULL,
    cc_code VARCHAR(255) NULL,
    cc_desc VARCHAR(255) NULL,
    cc_gsber VARCHAR(255) NULL,
    cc_valid_from DATE NULL,
    cc_valid_to DATE NULL,
    cc_company_code VARCHAR(255) NULL,
    cc_created_by VARCHAR(255) NULL,
    cc_created_timestamp TIMESTAMP NULL,
    cc_updated_by VARCHAR(255) NULL,
    cc_updated_timestamp TIMESTAMP NULL,
    CONSTRAINT m_cost_center_pkey PRIMARY KEY (cc_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_cost_control_mapping
-- ────────────────────────────────────────────────────────
CREATE TABLE m_cost_control_mapping (
    cost_control_id BIGSERIAL NOT NULL DEFAULT nextval('m_cost_control_mapping_cost_control_id_seq'::regclass),
    activity_code_start VARCHAR(255) NULL,
    activity_code_end VARCHAR(255) NULL,
    cost_by_block SMALLINT NULL DEFAULT 0,
    cost_by_auc SMALLINT NULL DEFAULT 0,
    cost_by_order_number SMALLINT NULL DEFAULT 0,
    cost_by_cost_center SMALLINT NULL DEFAULT 0,
    cost_control_mapping_created_by VARCHAR(255) NULL,
    cost_control_mapping_created_date DATE NULL,
    cost_control_mapping_created_time TIME WITHOUT TIME ZONE NULL,
    cost_control_mapping_updated_by VARCHAR(255) NULL,
    cost_control_mapping_updated_date DATE NULL,
    cost_control_mapping_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_cost_control_mapping_pkey PRIMARY KEY (cost_control_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_customer_code
-- ────────────────────────────────────────────────────────
CREATE TABLE m_customer_code (
    customer_code_id BIGSERIAL NOT NULL DEFAULT nextval('m_customer_code_customer_code_id_seq'::regclass),
    customer_code_plant_code VARCHAR(255) NULL,
    customer_code VARCHAR(255) NULL,
    customer_code_created_by VARCHAR(255) NULL,
    customer_code_created_date DATE NULL,
    customer_code_created_time TIME WITHOUT TIME ZONE NULL,
    customer_code_updated_by VARCHAR(255) NULL,
    customer_code_updated_date DATE NULL,
    customer_code_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_customer_code_pkey PRIMARY KEY (customer_code_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_deduction_code
-- ────────────────────────────────────────────────────────
CREATE TABLE m_deduction_code (
    deduction_code_id BIGSERIAL NOT NULL DEFAULT nextval('m_deduction_code_deduction_code_id_seq'::regclass),
    deduction_code VARCHAR(255) NOT NULL,
    deduction_code_description VARCHAR(255) NULL,
    deduction_code_uom VARCHAR(255) NULL,
    deduction_code_rate FLOAT8 NULL,
    deduction_created_by VARCHAR(255) NULL,
    deduction_created_date DATE NULL,
    deduction_created_time TIME WITHOUT TIME ZONE NULL,
    deduction_updated_by VARCHAR(255) NULL,
    deduction_updated_date DATE NULL,
    deduction_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_deduction_code_pkey PRIMARY KEY (deduction_code_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_deduction_type
-- ────────────────────────────────────────────────────────
CREATE TABLE m_deduction_type (
    deduction_type_id BIGSERIAL NOT NULL DEFAULT nextval('m_deduction_type_deduction_type_id_seq'::regclass),
    deduction_type_code VARCHAR(255) NOT NULL,
    deduction_type_description VARCHAR(255) NOT NULL,
    deduction_type_created_by VARCHAR(255) NULL,
    deduction_type_created_date DATE NULL,
    deduction_type_created_time TIME WITHOUT TIME ZONE NULL,
    deduction_type_updated_by VARCHAR(255) NULL,
    deduction_type_updated_date DATE NULL,
    deduction_type_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_deduction_type_pkey PRIMARY KEY (deduction_type_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_destination
-- ────────────────────────────────────────────────────────
CREATE TABLE m_destination (
    destination_id BIGSERIAL NOT NULL DEFAULT nextval('m_destination_destination_id_seq'::regclass),
    destination_code VARCHAR(255) NULL,
    destination_name VARCHAR(255) NULL,
    destination_created_by VARCHAR(255) NULL,
    destination_created_date DATE NULL,
    destination_created_time TIME WITHOUT TIME ZONE NULL,
    destination_updated_by VARCHAR(255) NULL,
    destination_updated_date DATE NULL,
    destination_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_destination_pkey PRIMARY KEY (destination_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_devices
-- ────────────────────────────────────────────────────────
CREATE TABLE m_devices (
    device_id BIGSERIAL NOT NULL DEFAULT nextval('m_devices_device_id_seq'::regclass),
    device_code VARCHAR(255) NULL,
    device_estate_code VARCHAR(255) NULL,
    device_imei VARCHAR(255) NULL,
    device_created_by VARCHAR(255) NULL,
    device_created_date DATE NULL,
    device_created_time TIME WITHOUT TIME ZONE NULL,
    device_updated_by VARCHAR(255) NULL,
    device_updated_date DATE NULL,
    device_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_devices_pkey PRIMARY KEY (device_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_division
-- ────────────────────────────────────────────────────────
CREATE TABLE m_division (
    division_id BIGSERIAL NOT NULL DEFAULT nextval('m_division_division_id_seq'::regclass),
    division_company_code VARCHAR(255) NULL,
    division_estate_code VARCHAR(255) NULL,
    division_code VARCHAR(255) NULL,
    division_name VARCHAR(255) NULL,
    division_valid_from DATE NULL,
    division_valid_to DATE NULL,
    division_created_by VARCHAR(255) NULL,
    division_created_date DATE NULL,
    division_created_time TIME WITHOUT TIME ZONE NULL,
    division_updated_by VARCHAR(255) NULL,
    division_updated_date DATE NULL,
    division_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_division_pkey PRIMARY KEY (division_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_activity
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_activity (
    activity_id SERIAL NOT NULL DEFAULT nextval('m_durian_activity_activity_id_seq'::regclass),
    activity_code VARCHAR(50) NOT NULL,
    activity_group_code VARCHAR(50) NOT NULL,
    activity_name VARCHAR(100) NOT NULL,
    activity_created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    activity_created_by VARCHAR(50) NULL,
    activity_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    activity_updated_by VARCHAR(50) NULL,
    CONSTRAINT m_durian_activity_pkey PRIMARY KEY (activity_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_disease
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_disease (
    disease_id SERIAL NOT NULL DEFAULT nextval('m_durian_disease_disease_id_seq'::regclass),
    disease_code VARCHAR(50) NULL,
    disease_desc VARCHAR(100) NULL,
    disease_created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    disease_created_by VARCHAR(50) NULL,
    disease_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    disease_updated_by VARCHAR(50) NULL,
    CONSTRAINT m_durian_disease_pkey PRIMARY KEY (disease_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_fertilizer
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_fertilizer (
    fertilizer_id SERIAL NOT NULL DEFAULT nextval('m_durian_fertilizer_fertilizer_id_seq'::regclass),
    fertilizer_code VARCHAR(50) NULL,
    fertilizer_desc VARCHAR(100) NULL,
    fertilizer_created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    fertilizer_created_by VARCHAR(50) NULL,
    fertilizer_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    fertilizer_updated_by VARCHAR(50) NULL,
    CONSTRAINT m_durian_fertilizer_pkey PRIMARY KEY (fertilizer_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_grading
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_grading (
    grading_id SERIAL NOT NULL DEFAULT nextval('m_durian_grading_grading_id_seq'::regclass),
    crop_type VARCHAR(50) NULL,
    type_of_variety VARCHAR(50) NULL,
    grading_code VARCHAR(50) NULL,
    grading_weight VARCHAR(50) NULL,
    grading_created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    grading_created_by VARCHAR(50) NULL,
    grading_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    grading_updated_by VARCHAR(50) NULL,
    CONSTRAINT m_durian_grading_pkey PRIMARY KEY (grading_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_pesticide
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_pesticide (
    pesticide_id SERIAL NOT NULL DEFAULT nextval('m_durian_pesticide_pesticide_id_seq'::regclass),
    pesticide_code VARCHAR(50) NOT NULL,
    pesticide_desc TEXT NOT NULL,
    pesticide_created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    pesticide_created_by VARCHAR(50) NULL,
    pesticide_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    pesticide_updated_by VARCHAR(50) NULL,
    CONSTRAINT m_durian_pesticide_pkey PRIMARY KEY (pesticide_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_prunning_manuring
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_prunning_manuring (
    pm_id SERIAL NOT NULL DEFAULT nextval('m_durian_prunning_manuring_pm_id_seq'::regclass),
    activity_group VARCHAR(50) NULL,
    activity_code VARCHAR(10) NULL,
    activity_name VARCHAR(100) NULL,
    activity_created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    activity_created_by VARCHAR(50) NULL,
    activity_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    activity_updated_by VARCHAR(50) NULL,
    CONSTRAINT m_durian_prunning_manuring_pkey PRIMARY KEY (pm_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_soil_condition
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_soil_condition (
    soil_id SERIAL NOT NULL DEFAULT nextval('m_durian_soil_condition_soil_id_seq'::regclass),
    soil_code VARCHAR(50) NULL,
    soil_texture VARCHAR(50) NULL,
    soil_created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    soil_created_by VARCHAR(50) NULL,
    soil_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    soil_updated_by VARCHAR(50) NULL,
    CONSTRAINT m_durian_soil_condition_pkey PRIMARY KEY (soil_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_task
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_task (
    durian_task_id SERIAL NOT NULL DEFAULT nextval('m_durian_task_durian_task_id_seq'::regclass),
    company_code VARCHAR(50) NULL,
    estate_code VARCHAR(50) NULL,
    division VARCHAR(50) NULL,
    block VARCHAR(50) NULL,
    task_no VARCHAR(50) NULL,
    row_no INTEGER NULL,
    task_validity DATE NULL,
    task_created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    task_created_by VARCHAR(50) NULL,
    task_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    task_updated_by VARCHAR(50) NULL,
    CONSTRAINT m_durian_task_pkey PRIMARY KEY (durian_task_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_durian_variety
-- ────────────────────────────────────────────────────────
CREATE TABLE m_durian_variety (
    variety_id SERIAL NOT NULL DEFAULT nextval('m_durian_variety_variety_id_seq'::regclass),
    company_code VARCHAR(50) NULL,
    estate_code VARCHAR(50) NULL,
    division VARCHAR(50) NULL,
    block VARCHAR(50) NULL,
    row_no INTEGER NULL,
    variety VARCHAR(100) NULL,
    variety_created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    variety_created_by VARCHAR(50) NULL,
    variety_updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    variety_updated_by VARCHAR(50) NULL,
    CONSTRAINT m_durian_variety_pkey PRIMARY KEY (variety_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_employee
-- ────────────────────────────────────────────────────────
CREATE TABLE m_employee (
    employee_id BIGSERIAL NOT NULL DEFAULT nextval('m_employee_employee_id_seq'::regclass),
    employee_estate_code VARCHAR(255) NULL,
    employee_division_code VARCHAR(255) NULL,
    employee_code VARCHAR(255) NULL,
    employee_name VARCHAR(255) NULL,
    employee_sex VARCHAR(255) NULL,
    employee_job_code VARCHAR(255) NULL,
    employee_is_internal_estate SMALLINT NULL,
    employee_valid_from DATE NULL,
    employee_valid_to DATE NULL,
    employee_created_by VARCHAR(255) NULL,
    employee_created_date DATE NULL,
    employee_created_time TIME WITHOUT TIME ZONE NULL,
    employee_updated_by VARCHAR(255) NULL,
    employee_updated_date DATE NULL,
    employee_updated_time TIME WITHOUT TIME ZONE NULL,
    employee_profile VARCHAR(255) NULL,
    employee_job_type VARCHAR(255) NULL,
    employee_status VARCHAR(255) NULL,
    employee_work_permit_exp_date VARCHAR(255) NULL,
    employee_company_code VARCHAR(255) NULL,
    employee_stats VARCHAR(255) NULL,
    employee_department VARCHAR(255) NULL,
    employee_vendor VARCHAR(255) NULL DEFAULT ''::character varying,
    CONSTRAINT m_employee_pkey PRIMARY KEY (employee_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_estate
-- ────────────────────────────────────────────────────────
CREATE TABLE m_estate (
    estate_id BIGSERIAL NOT NULL DEFAULT nextval('m_estate_estate_id_seq'::regclass),
    estate_company_code VARCHAR(255) NULL,
    estate_code VARCHAR(255) NULL,
    estate_name VARCHAR(255) NULL,
    estate_plant_code VARCHAR(255) NULL,
    estate_created_by VARCHAR(255) NULL,
    estate_created_date DATE NULL,
    estate_created_time TIME WITHOUT TIME ZONE NULL,
    estate_updated_by VARCHAR(255) NULL,
    estate_updated_date DATE NULL,
    estate_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_estate_pkey PRIMARY KEY (estate_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_field_staff_gang
-- ────────────────────────────────────────────────────────
CREATE TABLE m_field_staff_gang (
    field_staff_gang_id BIGSERIAL NOT NULL DEFAULT nextval('m_field_staff_gang_field_staff_gang_id_seq'::regclass),
    field_staff_gang_code VARCHAR(255) NULL,
    field_staff_employee_code VARCHAR(255) NULL,
    field_staff_employee_name VARCHAR(255) NULL,
    field_staff_gang_created_by VARCHAR(255) NULL,
    field_staff_gang_created_date DATE NULL,
    field_staff_gang_created_time TIME WITHOUT TIME ZONE NULL,
    field_staff_gang_updated_by VARCHAR(255) NULL,
    field_staff_gang_updated_date DATE NULL,
    field_staff_gang_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_field_staff_gang_pkey PRIMARY KEY (field_staff_gang_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_field_staff_kemandoran
-- ────────────────────────────────────────────────────────
CREATE TABLE m_field_staff_kemandoran (
    field_staff_kemandoran_id BIGSERIAL NOT NULL DEFAULT nextval('m_field_staff_kemandoran_field_staff_kemandoran_id_seq'::regclass),
    field_staff_employee_code VARCHAR(255) NULL,
    field_staff_employee_name VARCHAR(255) NULL,
    mandor_employee_code VARCHAR(255) NULL,
    mandor_employee_name VARCHAR(255) NULL,
    field_staff_kemandoran_created_by VARCHAR(255) NULL,
    field_staff_kemandoran_created_date DATE NULL,
    field_staff_kemandoran_created_time TIME WITHOUT TIME ZONE NULL,
    field_staff_kemandoran_updated_by VARCHAR(255) NULL,
    field_staff_kemandoran_updated_date DATE NULL,
    field_staff_kemandoran_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_field_staff_kemandoran_pkey PRIMARY KEY (field_staff_kemandoran_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_gang_employee
-- ────────────────────────────────────────────────────────
CREATE TABLE m_gang_employee (
    gang_employee_id BIGSERIAL NOT NULL DEFAULT nextval('m_gang_employee_gang_employee_id_seq'::regclass),
    gang_employee_code VARCHAR(255) NULL,
    gang_employee_name VARCHAR(255) NULL,
    gang_code VARCHAR(255) NULL,
    gang_employee_created_by VARCHAR(255) NULL,
    gang_employee_created_date DATE NULL,
    gang_employee_created_time TIME WITHOUT TIME ZONE NULL,
    gang_employee_updated_by VARCHAR(255) NULL,
    gang_employee_updated_date DATE NULL,
    gang_employee_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_gang_employee_pkey PRIMARY KEY (gang_employee_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_gigr_wbs
-- ────────────────────────────────────────────────────────
CREATE TABLE m_gigr_wbs (
    wbs_id BIGINT NOT NULL,
    wbs_code VARCHAR(255) NULL,
    wbs_code2 VARCHAR(255) NULL,
    wbs_company_code VARCHAR(255) NULL,
    wbs_gl_acc_code VARCHAR(255) NULL,
    wbs_gl_acc_desc VARCHAR(255) NULL,
    wbs_plant_code VARCHAR(255) NULL,
    wbs_name VARCHAR(255) NULL,
    wbs_created_by VARCHAR(255) NULL,
    wbs_created_timestamp TIMESTAMP NULL,
    wbs_updated_by VARCHAR(255) NULL,
    wbs_updated_timestamp TIMESTAMP NULL,
    CONSTRAINT m_gigr_wbs_pkey PRIMARY KEY (wbs_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_glacc
-- ────────────────────────────────────────────────────────
CREATE TABLE m_glacc (
    m_glacc_id BIGSERIAL NOT NULL DEFAULT nextval('m_glacc_m_glacc_id_seq'::regclass),
    m_glacc_account_number VARCHAR(255) NOT NULL,
    m_glacc_desc VARCHAR(255) NULL,
    m_glacc_company_code VARCHAR(255) NULL,
    m_glacc_created_by VARCHAR(255) NULL,
    m_glacc_created_timestamp TIMESTAMP NULL,
    m_glacc_updated_by VARCHAR(255) NULL,
    m_glacc_updated_timestamp TIMESTAMP NULL
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_glacc_gi_order
-- ────────────────────────────────────────────────────────
CREATE TABLE m_glacc_gi_order (
    m_glacc_id BIGSERIAL NOT NULL DEFAULT nextval('m_glacc_gi_order_m_glacc_id_seq'::regclass),
    m_glacc_account_number VARCHAR(255) NOT NULL,
    m_glacc_desc VARCHAR(255) NULL,
    m_glacc_company_code VARCHAR(255) NULL,
    m_glacc_created_by VARCHAR(255) NULL,
    m_glacc_created_timestamp TIMESTAMP NULL,
    m_glacc_updated_by VARCHAR(255) NULL,
    m_glacc_updated_timestamp TIMESTAMP NULL
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_harvest_method
-- ────────────────────────────────────────────────────────
CREATE TABLE m_harvest_method (
    mhm_id BIGSERIAL NOT NULL DEFAULT nextval('m_harvest_method_mhm_id_seq'::regclass),
    mhm_indicator VARCHAR(1) NULL,
    mhm_abbreviation VARCHAR(15) NULL,
    mhm_description VARCHAR(255) NULL,
    mhm_created_by VARCHAR(255) NULL,
    mhm_created_date DATE NULL,
    mhm_created_time TIME WITHOUT TIME ZONE NULL,
    mhm_updated_by VARCHAR(255) NULL,
    mhm_updated_date DATE NULL,
    mhm_updated_time TIME WITHOUT TIME ZONE NULL,
    mhm_order_number_flag VARCHAR(1) NULL DEFAULT 'N'::character varying,
    CONSTRAINT m_harvest_method_pkey PRIMARY KEY (mhm_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_maintenance_order
-- ────────────────────────────────────────────────────────
CREATE TABLE m_maintenance_order (
    mo_id BIGSERIAL NOT NULL DEFAULT nextval('m_maintenance_order_mo_id_seq'::regclass),
    mo_order_number VARCHAR(255) NULL,
    mo_sales_doc_type VARCHAR(255) NULL,
    mo_order_desc VARCHAR(255) NULL,
    mo_plant_code VARCHAR(255) NULL,
    mo_company_code VARCHAR(255) NULL,
    mo_business_area VARCHAR(255) NULL,
    mo_created_by VARCHAR(255) NULL,
    mo_created_timestamp TIMESTAMP NULL,
    mo_updated_by VARCHAR(255) NULL,
    mo_updated_timestamp TIMESTAMP NULL,
    CONSTRAINT m_maintenance_order_pkey PRIMARY KEY (mo_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_material
-- ────────────────────────────────────────────────────────
CREATE TABLE m_material (
    material_id BIGSERIAL NOT NULL DEFAULT nextval('m_material_material_id_seq'::regclass),
    material_code VARCHAR(255) NULL,
    material_name VARCHAR(255) NULL,
    material_uom VARCHAR(255) NULL,
    material_created_by VARCHAR(255) NULL,
    material_created_date DATE NULL,
    material_created_time TIME WITHOUT TIME ZONE NULL,
    material_updated_by VARCHAR(255) NULL,
    material_updated_date DATE NULL,
    material_updated_time TIME WITHOUT TIME ZONE NULL,
    material_plant_code VARCHAR(255) NULL,
    material_sloc VARCHAR(255) NULL,
    material_batch VARCHAR(255) NULL,
    material_group VARCHAR(255) NULL,
    material_type VARCHAR(255) NULL,
    CONSTRAINT m_material_pkey PRIMARY KEY (material_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_meas_point
-- ────────────────────────────────────────────────────────
CREATE TABLE m_meas_point (
    meas_point_id BIGSERIAL NOT NULL DEFAULT nextval('m_meas_point_meas_point_id_seq'::regclass),
    meas_point_company_code VARCHAR(255) NULL,
    meas_point_plant_code VARCHAR(255) NULL,
    meas_point_vra_order_number VARCHAR(255) NULL,
    meas_point_equipment_code VARCHAR(255) NULL,
    meas_point_equipment_object_number VARCHAR(255) NULL,
    meas_point_point VARCHAR(255) NULL,
    meas_point_unit VARCHAR(255) NULL,
    meas_point_description VARCHAR(255) NULL,
    meas_point_created_by VARCHAR(255) NULL,
    meas_point_created_date DATE NULL,
    meas_point_created_time TIME WITHOUT TIME ZONE NULL,
    meas_point_updated_by VARCHAR(255) NULL,
    meas_point_updated_date DATE NULL,
    meas_point_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_meas_point_pkey PRIMARY KEY (meas_point_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_movement_type
-- ────────────────────────────────────────────────────────
CREATE TABLE m_movement_type (
    mvt_type_id BIGSERIAL NOT NULL DEFAULT nextval('mvt_type_seq'::regclass),
    mvt_type_code VARCHAR(255) NOT NULL,
    mvt_type_doc_type VARCHAR(255) NOT NULL,
    mvt_type_desc VARCHAR(255) NOT NULL,
    mvt_type_created_by VARCHAR(255) NULL,
    mvt_type_created_date DATE NULL,
    mvt_type_created_time TIME WITHOUT TIME ZONE NULL,
    mvt_type_updated_by VARCHAR(255) NULL,
    mvt_type_updated_date DATE NULL,
    mvt_type_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_movement_type_pkey PRIMARY KEY (mvt_type_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_non_palm_material
-- ────────────────────────────────────────────────────────
CREATE TABLE m_non_palm_material (
    non_palm_material_id BIGSERIAL NOT NULL DEFAULT nextval('m_non_palm_material_non_palm_material_id_seq'::regclass),
    non_palm_material_code VARCHAR(255) NULL,
    non_palm_material_desc VARCHAR(255) NULL,
    non_palm_material_uom VARCHAR(255) NULL,
    non_palm_material_plant_code VARCHAR(255) NULL,
    non_palm_material_created_by VARCHAR(255) NULL,
    non_palm_material_created_date DATE NULL,
    non_palm_material_created_time TIME WITHOUT TIME ZONE NULL,
    non_palm_material_updated_by VARCHAR(255) NULL,
    non_palm_material_updated_date DATE NULL,
    non_palm_material_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_non_palm_material_pkey PRIMARY KEY (non_palm_material_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_order_header
-- ────────────────────────────────────────────────────────
CREATE TABLE m_order_header (
    m_order_header_id BIGSERIAL NOT NULL DEFAULT nextval('m_order_header_m_order_header_id_seq'::regclass),
    m_order_header_number VARCHAR(255) NOT NULL,
    m_order_header_type VARCHAR(255) NULL,
    m_order_header_status VARCHAR(255) NULL,
    m_order_header_vendor VARCHAR(255) NULL,
    m_order_header_plant_code VARCHAR(255) NULL,
    m_order_header_sloc_code VARCHAR(255) NULL,
    m_order_header_flag SMALLINT NULL,
    m_order_header_sap_created_date DATE NULL,
    m_order_header_sap_created_by VARCHAR(255) NULL,
    m_order_header_organization VARCHAR(255) NULL,
    m_order_header_supplier_account_number VARCHAR(255) NULL,
    m_order_header_supplier_name VARCHAR(255) NULL,
    m_order_header_group VARCHAR(255) NULL,
    m_order_header_is_deleted BOOLEAN NOT NULL DEFAULT false,
    m_order_detail_material_line_num VARCHAR(255) NULL,
    m_order_detail_material_code VARCHAR(255) NULL,
    m_order_detail_material_name VARCHAR(255) NULL,
    m_order_detail_material_group VARCHAR(255) NULL,
    m_order_detail_qty_order FLOAT8 NULL,
    m_order_detail_uom VARCHAR(255) NULL,
    m_order_detail_currency_key VARCHAR(255) NULL,
    m_order_detail_net_price FLOAT8 NULL,
    m_order_detail_unit_price FLOAT8 NULL,
    m_order_detail_order_price_unit VARCHAR(255) NULL,
    m_order_detail_net_value FLOAT8 NULL,
    m_order_header_created_by VARCHAR(255) NULL,
    m_order_header_created_timestamp TIMESTAMP NULL,
    m_order_header_updated_by VARCHAR(255) NULL,
    m_order_header_updated_timestamp TIMESTAMP NULL,
    CONSTRAINT m_order_header_pkey PRIMARY KEY (m_order_header_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_po_header
-- ────────────────────────────────────────────────────────
CREATE TABLE m_po_header (
    m_po_header_id BIGSERIAL NOT NULL DEFAULT nextval('m_po_header_m_po_header_id_seq'::regclass),
    m_po_header_number VARCHAR(255) NOT NULL,
    m_po_header_type VARCHAR(255) NULL,
    m_po_header_status VARCHAR(255) NULL,
    m_po_header_vendor VARCHAR(255) NULL,
    m_po_header_plant_code VARCHAR(255) NULL,
    m_po_header_sloc_code VARCHAR(255) NULL,
    m_po_header_flag SMALLINT NULL,
    m_po_header_sap_created_date DATE NULL,
    m_po_header_sap_created_by VARCHAR(255) NULL,
    m_po_detail_material_line_num VARCHAR(255) NULL,
    m_po_detail_material_code VARCHAR(255) NULL,
    m_po_detail_material_name VARCHAR(255) NULL,
    m_po_detail_material_group VARCHAR(255) NULL,
    m_po_detail_qty_order FLOAT8 NULL,
    m_po_detail_uom VARCHAR(255) NULL,
    m_po_detail_currency_key VARCHAR(255) NULL,
    m_po_detail_net_price FLOAT8 NULL,
    m_po_detail_unit_price FLOAT8 NULL,
    m_po_detail_order_price_unit VARCHAR(255) NULL,
    m_po_detail_net_value FLOAT8 NULL,
    m_po_header_organization VARCHAR(255) NULL,
    m_po_header_supplier_account_number VARCHAR(255) NULL,
    m_po_header_supplier_name VARCHAR(255) NULL,
    m_po_header_po_group VARCHAR(255) NULL,
    m_po_header_is_deleted BOOLEAN NOT NULL DEFAULT false,
    m_po_header_created_by VARCHAR(255) NULL,
    m_po_header_created_timestamp TIMESTAMP NULL,
    m_po_header_updated_by VARCHAR(255) NULL,
    m_po_header_updated_timestamp TIMESTAMP NULL,
    CONSTRAINT m_po_header_pkey PRIMARY KEY (m_po_header_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_receiving_point
-- ────────────────────────────────────────────────────────
CREATE TABLE m_receiving_point (
    receiving_point_id BIGSERIAL NOT NULL DEFAULT nextval('m_receiving_point_receiving_point_id_seq'::regclass),
    receiving_point_code VARCHAR(255) NULL,
    receiving_point_created_by VARCHAR(255) NULL,
    receiving_point_created_date DATE NULL,
    receiving_point_created_time TIME WITHOUT TIME ZONE NULL,
    receiving_point_updated_by VARCHAR(255) NULL,
    receiving_point_updated_date DATE NULL,
    receiving_point_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_receiving_point_pkey PRIMARY KEY (receiving_point_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_report_oph
-- ────────────────────────────────────────────────────────
CREATE TABLE m_report_oph (
    r_oph_id BIGSERIAL NOT NULL DEFAULT nextval('m_report_oph_r_oph_id_seq'::regclass),
    r_oph_estate_code VARCHAR(20) NULL,
    r_oph_period VARCHAR(20) NULL,
    r_oph_division_code VARCHAR(20) NULL,
    r_oph_block_code VARCHAR(20) NULL,
    r_oph_brondolan_rate_1 FLOAT8 NULL,
    r_oph_brondolan_rate_2 FLOAT8 NULL,
    r_oph_basis FLOAT8 NULL,
    r_oph_gandeng FLOAT8 NULL,
    r_oph_premi_basis FLOAT8 NULL,
    r_oph_premi_non_basis FLOAT8 NULL,
    r_oph_hk_rate FLOAT8 NULL,
    r_oph_created_by VARCHAR(255) NULL,
    r_oph_created_date DATE NULL,
    r_oph_created_time TIME WITHOUT TIME ZONE NULL,
    r_oph_updated_by VARCHAR(255) NULL,
    r_oph_updated_date DATE NULL,
    r_oph_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_report_oph_pkey PRIMARY KEY (r_oph_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_sales_order
-- ────────────────────────────────────────────────────────
CREATE TABLE m_sales_order (
    sales_order_id BIGSERIAL NOT NULL DEFAULT nextval('m_sales_order_sales_order_id_seq'::regclass),
    sales_order_no VARCHAR(255) NOT NULL,
    sales_order_fdn_id VARCHAR(255) NULL,
    sales_order_plant VARCHAR(255) NOT NULL,
    sales_order_item_no VARCHAR(255) NULL,
    sales_order_customer_reference VARCHAR(255) NULL,
    sales_order_customer_code VARCHAR(255) NULL,
    sales_order_customer_description_1 VARCHAR(255) NULL,
    sales_order_material_code VARCHAR(255) NULL,
    sales_order_item_qty VARCHAR(255) NULL,
    sales_order_item_uom VARCHAR(255) NULL,
    sales_order_payment_term VARCHAR(255) NULL,
    sales_order_inco_term_1 VARCHAR(255) NULL,
    sales_order_created_by VARCHAR(255) NULL,
    sales_order_created_date DATE NULL,
    sales_order_created_time TIME WITHOUT TIME ZONE NULL,
    sales_order_updated_by VARCHAR(255) NULL,
    sales_order_updated_date DATE NULL,
    sales_order_updated_time TIME WITHOUT TIME ZONE NULL,
    sales_order_customer_description_2 VARCHAR(255) NULL,
    sales_order_inco_term_2 VARCHAR(255) NULL,
    sales_order_material_desc VARCHAR(255) NULL,
    sales_order_reason_for_rejection VARCHAR(255) NULL,
    sales_order_date VARCHAR(255) NULL,
    sales_order_type VARCHAR(255) NULL,
    sales_order_sap_created_date VARCHAR(255) NULL,
    sales_order_sap_created_time VARCHAR(255) NULL,
    sales_order_sap_created_by VARCHAR(255) NULL,
    sales_order_sap_updated_date VARCHAR(255) NULL,
    sales_order_sap_updated_time VARCHAR(255) NULL,
    sales_order_sap_updated_by VARCHAR(255) NULL,
    sales_order_item_description VARCHAR(255) NULL
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_sloc
-- ────────────────────────────────────────────────────────
CREATE TABLE m_sloc (
    m_sloc_id BIGSERIAL NOT NULL DEFAULT nextval('m_sloc_m_sloc_id_seq'::regclass),
    m_sloc_code VARCHAR(255) NOT NULL,
    m_sloc_plant VARCHAR(255) NULL,
    m_sloc_desc VARCHAR(255) NULL,
    m_sloc_created_by VARCHAR(255) NULL,
    m_sloc_created_timestamp TIMESTAMP NULL,
    m_sloc_updated_by VARCHAR(255) NULL,
    m_sloc_updated_timestamp TIMESTAMP NULL
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_tph
-- ────────────────────────────────────────────────────────
CREATE TABLE m_tph (
    tph_id BIGSERIAL NOT NULL DEFAULT nextval('m_tph_tph_id_seq'::regclass),
    tph_company_code VARCHAR(255) NULL,
    tph_estate_code VARCHAR(255) NULL,
    tph_division_code VARCHAR(255) NULL,
    tph_block_code VARCHAR(255) NULL,
    tph_section_code VARCHAR(255) NULL,
    tph_code VARCHAR(255) NULL,
    tph_valid_from DATE NULL,
    tph_valid_to DATE NULL,
    tph_latitude VARCHAR(255) NULL,
    tph_longitude VARCHAR(255) NULL,
    tph_created_by VARCHAR(255) NULL,
    tph_created_date DATE NULL,
    tph_created_time TIME WITHOUT TIME ZONE NULL,
    tph_updated_by VARCHAR(255) NULL,
    tph_updated_date DATE NULL,
    tph_updated_time TIME WITHOUT TIME ZONE NULL,
    tph_palm_total BIGINT NULL DEFAULT 0,
    CONSTRAINT m_tph_pkey PRIMARY KEY (tph_id),
    CONSTRAINT uq_m_tph_tph_company_code UNIQUE (tph_company_code),
    CONSTRAINT uq_m_tph_tph_estate_code UNIQUE (tph_estate_code),
    CONSTRAINT uq_m_tph_tph_division_code UNIQUE (tph_division_code),
    CONSTRAINT uq_m_tph_tph_block_code UNIQUE (tph_block_code),
    CONSTRAINT uq_m_tph_tph_section_code UNIQUE (tph_section_code),
    CONSTRAINT uq_m_tph_tph_code UNIQUE (tph_code)
);

CREATE UNIQUE INDEX m_tph_unique_code ON public.m_tph USING btree (tph_company_code, tph_estate_code, tph_division_code, tph_block_code, tph_section_code, tph_code);

-- ────────────────────────────────────────────────────────
-- TABLE: m_uom
-- ────────────────────────────────────────────────────────
CREATE TABLE m_uom (
    uom_id BIGSERIAL NOT NULL DEFAULT nextval('m_uom_uom_id_seq'::regclass),
    uom_code VARCHAR(255) NOT NULL,
    uom_desc VARCHAR(255) NOT NULL,
    uom_created_by VARCHAR(255) NULL,
    uom_created_date DATE NULL,
    uom_created_time TIME WITHOUT TIME ZONE NULL,
    uom_updated_by VARCHAR(255) NULL,
    uom_updated_date DATE NULL,
    uom_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_uom_pkey PRIMARY KEY (uom_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_vendor
-- ────────────────────────────────────────────────────────
CREATE TABLE m_vendor (
    vendor_id BIGSERIAL NOT NULL DEFAULT nextval('m_vendor_vendor_id_seq'::regclass),
    vendor_code VARCHAR(255) NULL,
    vendor_name VARCHAR(255) NULL,
    vendor_created_by VARCHAR(255) NULL,
    vendor_created_date DATE NULL,
    vendor_created_time TIME WITHOUT TIME ZONE NULL,
    vendor_updated_by VARCHAR(255) NULL,
    vendor_updated_date DATE NULL,
    vendor_updated_time TIME WITHOUT TIME ZONE NULL,
    vendor_plant_code VARCHAR(255) NULL,
    CONSTRAINT m_vendor_pkey PRIMARY KEY (vendor_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_vra
-- ────────────────────────────────────────────────────────
CREATE TABLE m_vra (
    vra_id BIGSERIAL NOT NULL DEFAULT nextval('m_vra_vra_id_seq'::regclass),
    vra_license_number VARCHAR(255) NULL,
    vra_valid_from DATE NULL,
    vra_valid_to DATE NULL,
    vra_created_by VARCHAR(255) NULL,
    vra_created_date DATE NULL,
    vra_created_time TIME WITHOUT TIME ZONE NULL,
    vra_updated_by VARCHAR(255) NULL,
    vra_updated_date DATE NULL,
    vra_updated_time TIME WITHOUT TIME ZONE NULL,
    vra_order_number VARCHAR(255) NULL,
    vra_equipment_code VARCHAR(50) NOT NULL DEFAULT 'N'::character varying,
    vra_object_type VARCHAR(255) NULL,
    vra_plant_code VARCHAR(255) NULL,
    CONSTRAINT m_vra_pkey PRIMARY KEY (vra_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_wbs
-- ────────────────────────────────────────────────────────
CREATE TABLE m_wbs (
    wbs_id BIGSERIAL NOT NULL DEFAULT nextval('m_wbs_wbs_id_seq'::regclass),
    wbs_code VARCHAR(255) NULL,
    wbs_name VARCHAR(255) NULL,
    wbs_group_code VARCHAR(255) NULL,
    wbs_group_name VARCHAR(255) NULL,
    wbs_created_by VARCHAR(255) NULL,
    wbs_created_date DATE NULL,
    wbs_created_time TIME WITHOUT TIME ZONE NULL,
    wbs_updated_by VARCHAR(255) NULL,
    wbs_updated_date DATE NULL,
    wbs_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_wbs_pkey PRIMARY KEY (wbs_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_work_center
-- ────────────────────────────────────────────────────────
CREATE TABLE m_work_center (
    work_center_id BIGSERIAL NOT NULL DEFAULT nextval('m_work_center_work_center_id_seq'::regclass),
    work_center_company_code VARCHAR(255) NULL,
    work_center_plant_code VARCHAR(255) NULL,
    work_center_estate_code VARCHAR(255) NULL,
    work_center_division_code VARCHAR(255) NULL,
    work_center_code VARCHAR(255) NULL,
    work_center_name VARCHAR(255) NULL,
    work_center_created_by VARCHAR(255) NULL,
    work_center_created_date DATE NULL,
    work_center_created_time TIME WITHOUT TIME ZONE NULL,
    work_center_updated_by VARCHAR(255) NULL,
    work_center_updated_date DATE NULL,
    work_center_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_work_center_pkey PRIMARY KEY (work_center_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: m_worktype
-- ────────────────────────────────────────────────────────
CREATE TABLE m_worktype (
    worktype_id BIGINT NOT NULL,
    worktype_code VARCHAR(255) NULL,
    worktype_name VARCHAR(255) NULL,
    worktype_created_by VARCHAR(255) NULL,
    worktype_created_date DATE NULL,
    worktype_created_time TIME WITHOUT TIME ZONE NULL,
    worktype_updated_by VARCHAR(255) NULL,
    worktype_updated_date DATE NULL,
    worktype_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT m_worktype_pkey PRIMARY KEY (worktype_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: mail_scheduler
-- ────────────────────────────────────────────────────────
CREATE TABLE mail_scheduler (
    mail_scheduler_id BIGSERIAL NOT NULL DEFAULT nextval('mail_scheduler_mail_scheduler_id_seq'::regclass),
    mail_type SMALLINT NULL,
    mail_from VARCHAR(255) NULL,
    mail_to VARCHAR(255) NULL,
    mail_subject VARCHAR(255) NULL,
    mail_content VARCHAR(10000) NULL,
    mail_is_sent SMALLINT NULL DEFAULT 0,
    created_by VARCHAR(255) NULL,
    created_date DATE NULL,
    created_time TIME WITHOUT TIME ZONE NULL,
    updated_by VARCHAR(255) NULL,
    updated_date DATE NULL,
    updated_time TIME WITHOUT TIME ZONE NULL
);


-- ────────────────────────────────────────────────────────
-- TABLE: master_data_log
-- ────────────────────────────────────────────────────────
CREATE TABLE master_data_log (
    master_data_log_id BIGSERIAL NOT NULL DEFAULT nextval('master_data_log_master_data_log_id_seq'::regclass),
    master_data_table_name VARCHAR(255) NULL,
    master_data_last_refresh TIMESTAMP NULL,
    master_data_last_updated TIMESTAMP NULL,
    master_data_last_updated_by SMALLINT NULL,
    master_data_is_replaced SMALLINT NULL DEFAULT 1,
    CONSTRAINT master_data_log_pkey PRIMARY KEY (master_data_log_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: master_data_setting
-- ────────────────────────────────────────────────────────
CREATE TABLE master_data_setting (
    master_data_setting_id BIGSERIAL NOT NULL DEFAULT nextval('master_data_setting_master_data_setting_id_seq'::regclass),
    master_data_menu_name VARCHAR(255) NULL,
    master_data_table_name VARCHAR(255) NULL,
    master_data_sap_params VARCHAR(255) NULL,
    master_data_zepms_table_name VARCHAR(255) NULL,
    master_data_last_refresh TIMESTAMP NULL,
    master_data_last_updated TIMESTAMP NULL,
    master_data_last_updated_by SMALLINT NULL,
    master_data_is_replaced SMALLINT NULL DEFAULT 1,
    master_data_is_enabled SMALLINT NULL DEFAULT 1,
    master_data_refreshed_fields VARCHAR(255) NULL,
    CONSTRAINT master_data_setting_pkey PRIMARY KEY (master_data_setting_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: material_stock
-- ────────────────────────────────────────────────────────
CREATE TABLE material_stock (
    material_stock_id BIGSERIAL NOT NULL DEFAULT nextval('material_stock_material_stock_id_seq'::regclass),
    material_code VARCHAR(255) NULL,
    material_name VARCHAR(255) NULL,
    material_qty BIGINT NOT NULL DEFAULT 0,
    material_uom VARCHAR(255) NULL,
    material_plant_code VARCHAR(255) NULL,
    material_sloc_code VARCHAR(255) NULL,
    material_type_code VARCHAR(20) NULL,
    material_trans_date DATE NULL,
    material_created_by VARCHAR(255) NULL,
    material_created_date DATE NULL,
    material_created_time TIME WITHOUT TIME ZONE NULL,
    material_updated_by VARCHAR(255) NULL,
    material_updated_date DATE NULL,
    material_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT material_stock_pkey PRIMARY KEY (material_stock_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: material_type_group
-- ────────────────────────────────────────────────────────
CREATE TABLE material_type_group (
    mat_type_id BIGSERIAL NOT NULL DEFAULT nextval('material_type_group_mat_type_id_seq'::regclass),
    mat_type_code VARCHAR(255) NOT NULL,
    mat_type_desc VARCHAR(255) NULL,
    mat_code_list TEXT NULL,
    mat_type_created_by VARCHAR(255) NULL,
    mat_type_created_date DATE NULL,
    mat_type_created_time TIME WITHOUT TIME ZONE NULL,
    mat_type_updated_by VARCHAR(255) NULL,
    mat_type_updated_date DATE NULL,
    mat_type_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT material_type_group_pkey PRIMARY KEY (mat_type_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: mc_fdn_card
-- ────────────────────────────────────────────────────────
CREATE TABLE mc_fdn_card (
    fdn_card_id VARCHAR(255) NOT NULL,
    fdn_card_division VARCHAR(255) NOT NULL,
    fdn_card_created_by VARCHAR(255) NULL,
    fdn_card_created_date DATE NULL,
    fdn_card_created_time TIME WITHOUT TIME ZONE NULL,
    fdn_card_updated_by VARCHAR(255) NULL,
    fdn_card_updated_date DATE NULL,
    fdn_card_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT mc_fdn_card_pkey PRIMARY KEY (fdn_card_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: mc_oph_card
-- ────────────────────────────────────────────────────────
CREATE TABLE mc_oph_card (
    oph_card_id VARCHAR(255) NOT NULL,
    oph_card_division VARCHAR(255) NOT NULL,
    oph_card_created_by VARCHAR(255) NULL,
    oph_card_created_date DATE NULL,
    oph_card_created_time TIME WITHOUT TIME ZONE NULL,
    oph_card_updated_by VARCHAR(255) NULL,
    oph_card_updated_date DATE NULL,
    oph_card_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT mc_oph_card_pkey PRIMARY KEY (oph_card_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: res_data
-- ────────────────────────────────────────────────────────
CREATE TABLE res_data (
    res_id BIGSERIAL NOT NULL DEFAULT nextval('res_data_res_id_seq'::regclass),
    res_text VARCHAR(6000000) NOT NULL,
    res_timestamp TIMESTAMP NULL
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_abw
-- ────────────────────────────────────────────────────────
CREATE TABLE t_abw (
    abw_id BIGSERIAL NOT NULL DEFAULT nextval('t_abw_abw_id_seq'::regclass),
    abw_company_code VARCHAR(255) NULL,
    abw_estate_code VARCHAR(255) NULL,
    abw_block_code VARCHAR(255) NULL,
    abw_year VARCHAR(255) NULL,
    abw_month VARCHAR(255) NULL,
    abw_bunch_weight FLOAT8 NULL,
    abw_created_by VARCHAR(255) NULL,
    abw_created_date DATE NULL,
    abw_created_time TIME WITHOUT TIME ZONE NULL,
    abw_updated_by VARCHAR(255) NULL,
    abw_updated_date DATE NULL,
    abw_updated_time TIME WITHOUT TIME ZONE NULL,
    abw_posting_date DATE NULL,
    abw_sample_date DATE NULL,
    CONSTRAINT t_abw_pkey PRIMARY KEY (abw_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_adjustment
-- ────────────────────────────────────────────────────────
CREATE TABLE t_adjustment (
    id_adjustment BIGINT NOT NULL,
    adjustment_type VARCHAR(255) NULL,
    note TEXT NULL,
    date DATE NULL,
    time TIME WITHOUT TIME ZONE NULL,
    d_stts BIGINT NULL DEFAULT 1,
    global_id VARCHAR(50) NOT NULL,
    employee VARCHAR(255) NULL,
    transaction_date DATE NULL,
    ajust_by VARCHAR(30) NULL,
    CONSTRAINT t_adjustment_pkey PRIMARY KEY (id_adjustment)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_attendance
-- ────────────────────────────────────────────────────────
CREATE TABLE t_attendance (
    attendance_id BIGSERIAL NOT NULL DEFAULT nextval('t_attendance_attendance_id_seq'::regclass),
    attendance_date DATE NULL,
    attendance_mandor_employee_code VARCHAR(255) NULL,
    attendance_mandor_employee_name VARCHAR(255) NULL,
    attendance_employee_code VARCHAR(255) NULL,
    attendance_employee_name VARCHAR(255) NULL,
    attendance_code VARCHAR(255) NULL,
    attendance_desc VARCHAR(255) NULL,
    attendance_work_status SMALLINT NULL,
    attendance_is_closed SMALLINT NULL,
    attendance_created_by VARCHAR(255) NULL,
    attendance_created_date DATE NULL,
    attendance_created_time TIME WITHOUT TIME ZONE NULL,
    attendance_updated_by VARCHAR(255) NULL,
    attendance_updated_date DATE NULL,
    attendance_updated_time TIME WITHOUT TIME ZONE NULL,
    request_id VARCHAR(255) NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    REMARK VARCHAR(255) NULL,
    attendance_closing_is_approved SMALLINT NOT NULL DEFAULT 0,
    attendance_closing_is_approved_by VARCHAR(255) NULL,
    attendance_closing_approved_timestamp TIMESTAMP NULL,
    attendance_gang_allotment_code VARCHAR(255) NULL,
    adjustment_status SMALLINT NULL DEFAULT 0,
    CONSTRAINT t_attendance_pkey PRIMARY KEY (attendance_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_checkpoint
-- ────────────────────────────────────────────────────────
CREATE TABLE t_checkpoint (
    cp_id VARCHAR(255) NOT NULL,
    cp_estate_code VARCHAR(255) NULL,
    cp_division_code VARCHAR(255) NULL,
    cp_seal_code VARCHAR(255) NULL,
    cp_receiving_point_code VARCHAR(255) NULL,
    cp_delivery_note VARCHAR(255) NULL,
    cp_lat VARCHAR(255) NOT NULL DEFAULT '0'::character varying,
    cp_long VARCHAR(255) NOT NULL DEFAULT '0'::character varying,
    cp_photo VARCHAR(255) NULL,
    cp_total_nuts INTEGER NULL,
    cp_total_hc INTEGER NULL,
    cp_bruto FLOAT8 NULL,
    cp_tarra FLOAT8 NULL,
    cp_estimate_tonnage FLOAT8 NULL,
    cp_actual_tonnage FLOAT8 NULL,
    cp_is_closed SMALLINT NOT NULL DEFAULT 0,
    cp_kerani_kirim_employee_code VARCHAR(255) NULL,
    cp_kerani_kirim_employee_name VARCHAR(255) NULL,
    cp_is_deleted SMALLINT NOT NULL DEFAULT 0,
    cp_closing_is_approved SMALLINT NOT NULL DEFAULT 0,
    cp_closing_is_approved_by VARCHAR(255) NULL,
    cp_closing_approved_timestamp TIMESTAMP NULL,
    cp_transporter SMALLINT NULL,
    cp_license_number VARCHAR(255) NULL,
    cp_vendor_code TEXT NULL,
    cp_vendor_name TEXT NULL,
    cp_license_number_vendor VARCHAR(255) NULL,
    cp_transporter2 SMALLINT NULL,
    cp_license_number2 VARCHAR(255) NULL,
    cp_license_number_vendor2 VARCHAR(255) NULL,
    adjustment_status SMALLINT NULL DEFAULT 0,
    cp_bin_number INTEGER NULL,
    cp_type SMALLINT NULL,
    cp_sailing_date DATE NULL,
    cp_ship_flag SMALLINT NULL DEFAULT 0,
    cp_cable_way SMALLINT NULL DEFAULT 0,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    REMARK VARCHAR(255) NULL,
    cp_created_by VARCHAR(255) NULL,
    cp_created_date DATE NULL,
    cp_created_time TIME WITHOUT TIME ZONE NULL,
    cp_updated_by VARCHAR(255) NULL,
    cp_updated_date DATE NULL,
    cp_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT t_checkpoint_pkey PRIMARY KEY (cp_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_checkpoint_detail
-- ────────────────────────────────────────────────────────
CREATE TABLE t_checkpoint_detail (
    cp_detail_id BIGSERIAL NOT NULL DEFAULT nextval('t_checkpoint_detail_cp_detail_id_seq'::regclass),
    cp_id VARCHAR(255) NOT NULL,
    cp_hc_id VARCHAR(255) NOT NULL,
    cp_hc_block_code VARCHAR(255) NOT NULL,
    cp_hc_tph_code VARCHAR(255) NOT NULL,
    cp_hc_card_id VARCHAR(255) NULL,
    cp_hc_nuts_delivered INTEGER NOT NULL,
    cp_detail_type SMALLINT NULL,
    REMARK VARCHAR(255) NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    CONSTRAINT t_checkpoint_detail_pkey PRIMARY KEY (cp_detail_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_checkpoint_loader
-- ────────────────────────────────────────────────────────
CREATE TABLE t_checkpoint_loader (
    cp_loader_id BIGSERIAL NOT NULL DEFAULT nextval('t_checkpoint_loader_cp_loader_id_seq'::regclass),
    cp_id VARCHAR(255) NOT NULL,
    cp_loader_employee_code VARCHAR(255) NULL,
    cp_loader_employee_name VARCHAR(255) NOT NULL,
    cp_loader_vendor VARCHAR(255) NULL,
    cp_loader_transporter SMALLINT NULL,
    cp_loader_percentage FLOAT8 NOT NULL DEFAULT 0,
    cp_loader_type SMALLINT NOT NULL DEFAULT 0,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    CONSTRAINT t_checkpoint_loader_pkey PRIMARY KEY (cp_loader_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_coconut_fdn
-- ────────────────────────────────────────────────────────
CREATE TABLE t_coconut_fdn (
    coconut_fdn_id VARCHAR(255) NOT NULL,
    coconut_fdn_company_code VARCHAR(255) NULL,
    coconut_fdn_estate_code VARCHAR(255) NULL,
    coconut_fdn_division_code VARCHAR(255) NULL,
    coconut_fdn_sales_order VARCHAR(255) NULL,
    coconut_fdn_receiving_point_code VARCHAR(255) NULL,
    coconut_fdn_license_number VARCHAR(255) NULL,
    coconut_fdn_driver_name VARCHAR(255) NULL,
    coconut_fdn_vehicle_vendor_code VARCHAR(255) NULL,
    coconut_fdn_kerani_kirim_employee_code VARCHAR(255) NULL,
    coconut_fdn_kerani_kirim_employee_name VARCHAR(255) NULL,
    coconut_fdn_delivery_note VARCHAR(255) NULL,
    coconut_fdn_lat VARCHAR(255) NOT NULL DEFAULT 0,
    coconut_fdn_long VARCHAR(255) NOT NULL DEFAULT 0,
    coconut_fdn_card_id VARCHAR(255) NULL,
    coconut_fdn_photo VARCHAR(255) NULL,
    coconut_fdn_total_oph INTEGER NULL,
    coconut_fdn_bruto FLOAT8 NULL,
    coconut_fdn_tarra FLOAT8 NULL,
    coconut_fdn_actual_tonnage FLOAT8 NULL,
    coconut_fdn_is_closed SMALLINT NOT NULL DEFAULT 0,
    coconut_fdn_closing_is_approved SMALLINT NULL DEFAULT 0,
    coconut_fdn_closing_is_approved_by VARCHAR(255) NULL,
    coconut_fdn_closing_approved_timestamp TIMESTAMP NULL,
    coconut_fdn_created_by VARCHAR(255) NULL,
    coconut_fdn_created_date DATE NULL,
    coconut_fdn_created_time TIME WITHOUT TIME ZONE NULL,
    coconut_fdn_updated_by VARCHAR(255) NULL,
    coconut_fdn_updated_date DATE NULL,
    coconut_fdn_updated_time TIME WITHOUT TIME ZONE NULL,
    coconut_fdn_is_deleted SMALLINT NOT NULL DEFAULT 0,
    request_id VARCHAR(255) NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    REMARK VARCHAR(255) NULL,
    adjustment_status SMALLINT NULL DEFAULT 0,
    coconut_fdn_total_customer_qty FLOAT8 NULL,
    coconut_fdn_destination VARCHAR(255) NULL,
    coconut_fdn_is_nursery SMALLINT NULL,
    coconut_fdn_sales_order_item_code VARCHAR(255) NULL,
    coconut_fdn_is_stock SMALLINT NULL DEFAULT 0,
    CONSTRAINT t_coconut_fdn_pkey PRIMARY KEY (coconut_fdn_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_coconut_fdn_detail
-- ────────────────────────────────────────────────────────
CREATE TABLE t_coconut_fdn_detail (
    coconut_fdn_detail_id BIGSERIAL NOT NULL DEFAULT nextval('t_coconut_fdn_detail_coconut_fdn_detail_id_seq'::regclass),
    coconut_fdn_id VARCHAR(255) NOT NULL,
    coconut_fdn_oph_id VARCHAR(255) NOT NULL,
    coconut_fdn_oph_card_id VARCHAR(255) NULL,
    REMARK VARCHAR(255) NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    coconut_fdn_oph_total_customer_nut_qty FLOAT8 NULL,
    CONSTRAINT t_coconut_fdn_detail_pkey PRIMARY KEY (coconut_fdn_detail_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_coconut_harvesting_plan
-- ────────────────────────────────────────────────────────
CREATE TABLE t_coconut_harvesting_plan (
    coconut_harvesting_plan_id BIGSERIAL NOT NULL DEFAULT nextval('t_coconut_harvesting_plan_coconut_harvesting_plan_id_seq'::regclass),
    coconut_harvesting_plan_date DATE NULL,
    coconut_harvesting_plan_estate_code VARCHAR(255) NULL,
    coconut_harvesting_plan_division_code VARCHAR(255) NULL,
    coconut_harvesting_plan_block_code VARCHAR(255) NULL,
    coconut_harvesting_plan_total_hk INTEGER NULL,
    coconut_harvesting_plan_quantity_target INTEGER NULL,
    coconut_harvesting_plan_ha VARCHAR(255) NULL,
    coconut_harvesting_plan_assistant_employee_code VARCHAR(255) NULL,
    coconut_harvesting_plan_assistant_employee_name VARCHAR(255) NULL,
    coconut_harvesting_plan_approval_remark VARCHAR(255) NULL,
    coconut_harvesting_plan_is_approved SMALLINT NULL,
    coconut_harvesting_plan_approved_by VARCHAR(255) NULL,
    coconut_harvesting_plan_approved_by_name VARCHAR(255) NULL,
    coconut_harvesting_plan_approved_date DATE NULL,
    coconut_harvesting_plan_approved_time TIME WITHOUT TIME ZONE NULL,
    coconut_harvesting_plan_created_by VARCHAR(255) NULL,
    coconut_harvesting_plan_created_date DATE NULL,
    coconut_harvesting_plan_created_time TIME WITHOUT TIME ZONE NULL,
    coconut_harvesting_plan_updated_by VARCHAR(255) NULL,
    coconut_harvesting_plan_updated_date DATE NULL,
    coconut_harvesting_plan_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT t_coconut_harvesting_plan_pkey PRIMARY KEY (coconut_harvesting_plan_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_coconut_oph
-- ────────────────────────────────────────────────────────
CREATE TABLE t_coconut_oph (
    coconut_oph_id VARCHAR(255) NOT NULL,
    coconut_oph_company_code VARCHAR(255) NOT NULL,
    coconut_oph_plant_code VARCHAR(255) NOT NULL,
    coconut_oph_estate_code VARCHAR(255) NOT NULL,
    coconut_oph_division_code VARCHAR(255) NOT NULL,
    coconut_oph_block_code VARCHAR(255) NOT NULL,
    coconut_oph_tph_code VARCHAR(255) NOT NULL,
    coconut_oph_lat VARCHAR(255) NULL DEFAULT NULL::character varying,
    coconut_oph_long VARCHAR(255) NULL DEFAULT NULL::character varying,
    coconut_oph_photo VARCHAR(255) NULL DEFAULT NULL::character varying,
    coconut_oph_card_id VARCHAR(255) NULL DEFAULT NULL::character varying,
    coconut_oph_checker_employee_code VARCHAR(255) NOT NULL,
    coconut_oph_checker_employee_name VARCHAR(255) NULL DEFAULT NULL::character varying,
    coconut_oph_notes VARCHAR(255) NULL DEFAULT NULL::character varying,
    coconut_oph_is_planned SMALLINT NOT NULL DEFAULT 0,
    coconut_oph_is_approved SMALLINT NULL DEFAULT 0,
    coconut_oph_approved_by VARCHAR(255) NULL DEFAULT NULL::character varying,
    coconut_oph_approved_by_name VARCHAR(255) NULL,
    coconut_oph_approved_timestamp TIMESTAMP NULL,
    coconut_oph_is_closed SMALLINT NULL DEFAULT 0,
    coconut_oph_closing_is_approved SMALLINT NULL DEFAULT 0,
    coconut_oph_closing_approved_by VARCHAR(255) NULL DEFAULT NULL::character varying,
    coconut_oph_closing_approved_by_name VARCHAR(255) NULL,
    coconut_oph_closing_approved_timestamp TIMESTAMP NULL,
    coconut_oph_created_by VARCHAR(255) NOT NULL,
    coconut_oph_created_date DATE NOT NULL,
    coconut_oph_created_time TIME WITHOUT TIME ZONE NOT NULL,
    coconut_oph_updated_by VARCHAR(255) NULL DEFAULT NULL::character varying,
    coconut_oph_updated_date DATE NULL,
    coconut_oph_updated_time TIME WITHOUT TIME ZONE NULL,
    coconut_oph_is_deleted SMALLINT NOT NULL DEFAULT 0,
    integration_status SMALLINT NOT NULL DEFAULT '-1'::integer,
    adjustment_status SMALLINT NOT NULL DEFAULT 0,
    REMARK VARCHAR(255) NULL DEFAULT NULL::character varying,
    request_id VARCHAR(255) NULL,
    coconut_oph_gang_code VARCHAR(255) NULL,
    coconut_oph_gang_name VARCHAR(255) NULL,
    coconut_oph_nuts_total INTEGER NULL,
    CONSTRAINT t_coconut_oph_pkey PRIMARY KEY (coconut_oph_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_coconut_oph_detail
-- ────────────────────────────────────────────────────────
CREATE TABLE t_coconut_oph_detail (
    coconut_oph_detail_id BIGSERIAL NOT NULL DEFAULT nextval('t_coconut_oph_detail_coconut_oph_detail_id_seq'::regclass),
    coconut_oph_id VARCHAR(255) NULL,
    coconut_oph_detail_material_code VARCHAR(255) NOT NULL,
    coconut_oph_detail_material_name VARCHAR(255) NOT NULL,
    coconut_oph_detail_customer_nut_qty FLOAT8 NOT NULL,
    coconut_oph_detail_harvesting_deduction_is_locked SMALLINT NULL DEFAULT 0,
    coconut_oph_detail_closing_is_approved SMALLINT NULL DEFAULT 0,
    coconut_oph_detail_closing_approved_by VARCHAR(255) NULL DEFAULT NULL::character varying,
    coconut_oph_detail_closing_approved_by_name VARCHAR(255) NULL,
    coconut_oph_detail_closing_approved_timestamp TIMESTAMP NULL,
    coconut_oph_detail_is_deleted SMALLINT NOT NULL DEFAULT 0,
    integration_status SMALLINT NOT NULL DEFAULT '-1'::integer,
    adjustment_status SMALLINT NOT NULL DEFAULT 0,
    REMARK VARCHAR(255) NULL DEFAULT NULL::character varying,
    request_id VARCHAR(255) NULL,
    CONSTRAINT t_coconut_oph_detail_pkey PRIMARY KEY (coconut_oph_detail_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_coconut_oph_harvesting_deduction
-- ────────────────────────────────────────────────────────
CREATE TABLE t_coconut_oph_harvesting_deduction (
    coconut_oph_grading_id BIGSERIAL NOT NULL DEFAULT nextval('t_coconut_oph_harvesting_deduction_coconut_oph_grading_id_seq'::regclass),
    coconut_oph_grading_date DATE NOT NULL,
    coconut_oph_grading_oph_id VARCHAR(255) NULL,
    coconut_oph_grading_material_code VARCHAR(255) NOT NULL,
    coconut_oph_grading_material_description VARCHAR(255) NULL,
    coconut_oph_grading_qty FLOAT8 NULL,
    coconut_oph_grading_created_by VARCHAR(255) NULL,
    coconut_oph_grading_created_date DATE NULL,
    coconut_oph_grading_created_time TIME WITHOUT TIME ZONE NULL,
    coconut_oph_grading_updated_by VARCHAR(255) NULL,
    coconut_oph_grading_updated_date DATE NULL,
    coconut_oph_grading_updated_time TIME WITHOUT TIME ZONE NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    REMARK VARCHAR(255) NULL,
    coconut_oph_grading_type SMALLINT NULL,
    CONSTRAINT t_coconut_oph_harvesting_deduction_pkey PRIMARY KEY (coconut_oph_grading_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_coconut_oph_persons
-- ────────────────────────────────────────────────────────
CREATE TABLE t_coconut_oph_persons (
    coconut_oph_person_id BIGSERIAL NOT NULL DEFAULT nextval('t_coconut_oph_persons_coconut_oph_person_id_seq'::regclass),
    coconut_oph_id VARCHAR(255) NULL,
    coconut_oph_person_employee_code VARCHAR(255) NULL,
    coconut_oph_person_employee_name VARCHAR(255) NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    REMARK VARCHAR(6000) NULL,
    coconut_oph_person_activity_type CHAR(1) NULL,
    CONSTRAINT t_coconut_oph_persons_pkey PRIMARY KEY (coconut_oph_person_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_cp
-- ────────────────────────────────────────────────────────
CREATE TABLE t_cp (
    cp_id VARCHAR(255) NOT NULL,
    cp_estate_code VARCHAR(255) NULL,
    cp_division_code VARCHAR(255) NULL,
    cp_license_number VARCHAR(255) NULL,
    cp_seal_code VARCHAR(255) NULL,
    cp_receiving_point_code VARCHAR(255) NULL,
    cp_delivery_note VARCHAR(255) NULL,
    cp_lat VARCHAR(255) NOT NULL DEFAULT 0,
    cp_long VARCHAR(255) NOT NULL DEFAULT 0,
    cp_photo VARCHAR(255) NULL,
    cp_total_bunches INTEGER NULL,
    cp_total_oph INTEGER NULL,
    cp_total_loose_fruit FLOAT8 NULL,
    cp_estimate_tonnage FLOAT8 NULL,
    cp_actual_tonnage FLOAT8 NULL,
    cp_is_closed SMALLINT NOT NULL DEFAULT 0,
    cp_created_by VARCHAR(255) NULL,
    cp_created_date DATE NULL,
    cp_created_time TIME WITHOUT TIME ZONE NULL,
    cp_updated_by VARCHAR(255) NULL,
    cp_updated_date DATE NULL,
    cp_updated_time TIME WITHOUT TIME ZONE NULL,
    cp_kerani_kirim_employee_code VARCHAR(255) NULL,
    cp_kerani_kirim_employee_name VARCHAR(255) NULL,
    cp_is_deleted SMALLINT NOT NULL DEFAULT 0,
    cp_bunches_wet INTEGER NULL,
    cp_bunches_ripe INTEGER NULL,
    cp_bunches_overripe INTEGER NULL,
    cp_bunches_underripe INTEGER NULL,
    cp_bunches_unripe INTEGER NULL,
    cp_bunches_rotten INTEGER NULL,
    cp_bunches_long_stalk INTEGER NULL,
    cp_bunches_empty INTEGER NULL,
    cp_bunches_dirty INTEGER NULL,
    cp_bunches_unfresh INTEGER NULL,
    cp_bunches_old INTEGER NULL,
    cp_bunches_pest_damaged_old INTEGER NULL,
    cp_bunches_pest_damaged_new INTEGER NULL,
    cp_bunches_diseased INTEGER NULL,
    cp_bunches_total INTEGER NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    cp_bruto FLOAT8 NULL,
    cp_tarra FLOAT8 NULL,
    REMARK VARCHAR(255) NULL,
    cp_closing_is_approved SMALLINT NOT NULL DEFAULT 0,
    cp_closing_is_approved_by VARCHAR(255) NULL,
    cp_closing_approved_timestamp TIMESTAMP NULL,
    cp_transporter SMALLINT NULL,
    cp_vendor_code TEXT NULL,
    cp_vendor_name TEXT NULL,
    cp_license_number_vendor VARCHAR(255) NULL,
    adjustment_status SMALLINT NULL DEFAULT 0,
    cp_bin_number INTEGER NULL,
    cp_type SMALLINT NULL,
    cp_license_number_vendor2 VARCHAR(255) NULL,
    cp_license_number2 VARCHAR(255) NULL,
    cp_transporter2 SMALLINT NULL,
    cp_sailing_date DATE NULL,
    cp_ship_flag SMALLINT NULL DEFAULT 0,
    cp_cable_way SMALLINT NULL DEFAULT 0,
    CONSTRAINT t_cp_pkey PRIMARY KEY (cp_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_cp_detail
-- ────────────────────────────────────────────────────────
CREATE TABLE t_cp_detail (
    cp_detail_id BIGSERIAL NOT NULL DEFAULT nextval('t_cp_detail_cp_detail_id_seq'::regclass),
    cp_id VARCHAR(255) NOT NULL,
    cp_oph_id VARCHAR(255) NOT NULL,
    cp_oph_bunches_delivered INTEGER NOT NULL,
    cp_oph_loose_fruit_delivered FLOAT8 NOT NULL,
    cp_oph_block_code VARCHAR(255) NOT NULL,
    cp_oph_tph_code VARCHAR(255) NOT NULL,
    cp_oph_card_id VARCHAR(255) NULL,
    REMARK VARCHAR(255) NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    cp_detail_type SMALLINT NULL,
    cp_oph_platform_no VARCHAR(3) NULL,
    CONSTRAINT t_cp_detail_pkey PRIMARY KEY (cp_detail_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_cp_loader
-- ────────────────────────────────────────────────────────
CREATE TABLE t_cp_loader (
    cp_loader_id BIGSERIAL NOT NULL DEFAULT nextval('t_cp_loader_cp_loader_id_seq'::regclass),
    cp_id VARCHAR(255) NOT NULL,
    cp_loader_employee_code VARCHAR(255) NULL,
    cp_loader_employee_name VARCHAR(255) NOT NULL,
    cp_loader_percentage FLOAT8 NOT NULL DEFAULT 0,
    cp_loader_type SMALLINT NOT NULL DEFAULT 0,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    cp_loader_vendor VARCHAR(255) NULL,
    cp_loader_transporter SMALLINT NULL,
    CONSTRAINT t_cp_loader_pkey PRIMARY KEY (cp_loader_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_deleted_oph
-- ────────────────────────────────────────────────────────
CREATE TABLE t_deleted_oph (
    oph_id VARCHAR(255) NOT NULL,
    oph_card_id VARCHAR(255) NULL,
    oph_harvesting_method SMALLINT NULL,
    oph_estate_code VARCHAR(255) NULL,
    oph_plant_code VARCHAR(255) NULL,
    oph_division_code VARCHAR(255) NULL,
    oph_block_code VARCHAR(255) NULL,
    oph_tph_code VARCHAR(255) NULL,
    oph_notes VARCHAR(255) NULL,
    oph_lat VARCHAR(255) NULL,
    oph_long VARCHAR(255) NULL,
    oph_photo VARCHAR(255) NULL,
    mandor_employee_code VARCHAR(255) NULL,
    mandor_employee_name VARCHAR(255) NULL,
    kerani_panen_employee_code VARCHAR(255) NULL,
    kerani_panen_employee_name VARCHAR(255) NULL,
    bunches_wet INTEGER NULL,
    bunches_ripe INTEGER NULL,
    bunches_overripe INTEGER NULL,
    bunches_underripe INTEGER NULL,
    bunches_unripe INTEGER NULL,
    bunches_rotten INTEGER NULL,
    bunches_long_stalk INTEGER NULL,
    bunches_empty INTEGER NULL,
    bunches_dirty INTEGER NULL,
    bunches_unfresh INTEGER NULL,
    bunches_old INTEGER NULL,
    bunches_pest_damaged INTEGER NULL,
    bunches_small INTEGER NULL,
    bunches_diseased INTEGER NULL,
    bunches_dura INTEGER NULL,
    loose_fruits FLOAT8 NULL,
    bunches_total INTEGER NULL,
    bunches_not_sent INTEGER NULL,
    is_planned SMALLINT NULL,
    is_approved SMALLINT NULL,
    is_restant_permanent SMALLINT NULL,
    oph_approved_by VARCHAR(255) NULL,
    oph_approved_by_name VARCHAR(255) NULL,
    oph_approved_date DATE NULL,
    oph_approved_time TIME WITHOUT TIME ZONE NULL,
    oph_customer_code VARCHAR(255) NULL,
    oph_is_closed SMALLINT NOT NULL DEFAULT 0,
    oph_created_by VARCHAR(255) NULL,
    oph_created_date DATE NULL,
    oph_created_time TIME WITHOUT TIME ZONE NULL,
    oph_updated_by VARCHAR(255) NULL,
    oph_updated_date DATE NULL,
    oph_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT t_deleted_oph_pkey PRIMARY KEY (oph_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_deleted_oph_persons
-- ────────────────────────────────────────────────────────
CREATE TABLE t_deleted_oph_persons (
    deleted_oph_person_id BIGSERIAL NOT NULL DEFAULT nextval('t_deleted_oph_persons_deleted_oph_person_id_seq'::regclass),
    oph_id VARCHAR(255) NOT NULL,
    oph_person_employee_code VARCHAR(255) NOT NULL,
    oph_person_employee_name VARCHAR(255) NULL,
    oph_person_percentage FLOAT8 NULL,
    oph_person_total_bunches FLOAT8 NULL,
    oph_person_estimate_tonnage FLOAT8 NULL,
    oph_person_type SMALLINT NULL,
    oph_person_employee_type SMALLINT NULL,
    CONSTRAINT t_deleted_oph_persons_pkey PRIMARY KEY (deleted_oph_person_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_fdn
-- ────────────────────────────────────────────────────────
CREATE TABLE t_fdn (
    fdn_id VARCHAR(255) NOT NULL,
    fdn_card_id VARCHAR(255) NULL,
    fdn_estate_code VARCHAR(255) NULL,
    fdn_division_code VARCHAR(255) NULL,
    fdn_license_number VARCHAR(255) NULL,
    fdn_seal_code VARCHAR(255) NULL,
    fdn_deliver_to_code VARCHAR(255) NULL,
    fdn_deliver_to_name VARCHAR(255) NULL,
    fdn_delivery_note VARCHAR(255) NULL,
    fdn_lat VARCHAR(255) NOT NULL DEFAULT 0,
    fdn_long VARCHAR(255) NOT NULL DEFAULT 0,
    fdn_photo VARCHAR(255) NULL,
    fdn_total_bunches INTEGER NULL,
    fdn_total_oph INTEGER NULL,
    fdn_total_loose_fruit FLOAT8 NULL,
    fdn_estimate_tonnage FLOAT8 NULL,
    fdn_actual_tonnage FLOAT8 NULL,
    fdn_is_closed SMALLINT NOT NULL DEFAULT 0,
    fdn_created_by VARCHAR(255) NULL,
    fdn_created_date DATE NULL,
    fdn_created_time TIME WITHOUT TIME ZONE NULL,
    fdn_updated_by VARCHAR(255) NULL,
    fdn_updated_date DATE NULL,
    fdn_updated_time TIME WITHOUT TIME ZONE NULL,
    fdn_kerani_kirim_employee_code VARCHAR(255) NULL,
    fdn_kerani_kirim_employee_name VARCHAR(255) NULL,
    fdn_is_deleted SMALLINT NOT NULL DEFAULT 0,
    fdn_receiving_point_code VARCHAR(255) NULL,
    fdn_bunches_wet INTEGER NULL,
    fdn_bunches_ripe INTEGER NULL,
    fdn_bunches_overripe INTEGER NULL,
    fdn_bunches_underripe INTEGER NULL,
    fdn_bunches_unripe INTEGER NULL,
    fdn_bunches_rotten INTEGER NULL,
    fdn_bunches_long_stalk INTEGER NULL,
    fdn_bunches_empty INTEGER NULL,
    fdn_bunches_dirty INTEGER NULL,
    fdn_bunches_unfresh INTEGER NULL,
    fdn_bunches_old INTEGER NULL,
    fdn_bunches_pest_damaged_old INTEGER NULL,
    fdn_bunches_pest_damaged_new INTEGER NULL,
    fdn_bunches_diseased INTEGER NULL,
    fdn_bunches_total_old INTEGER NULL,
    fdn_transporter SMALLINT NULL,
    fdn_write_off_qty FLOAT8 NULL,
    request_id VARCHAR(255) NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    fdn_bruto FLOAT8 NULL,
    fdn_tarra FLOAT8 NULL,
    REMARK VARCHAR(255) NULL,
    fdn_write_off SMALLINT NULL,
    fdn_line_number SMALLINT NULL,
    fdn_closing_is_approved SMALLINT NULL DEFAULT 0,
    fdn_closing_is_approved_by VARCHAR(255) NULL,
    fdn_closing_approved_timestamp TIMESTAMP NULL,
    company_code VARCHAR(255) NULL,
    fdn_license_number_vendor VARCHAR(255) NULL,
    fdn_vendor_name VARCHAR(255) NULL,
    adjustment_status SMALLINT NULL DEFAULT 0,
    fdn_license_number_vendor2 VARCHAR(255) NULL,
    fdn_license_number2 VARCHAR(255) NULL,
    fdn_transporter2 SMALLINT NULL,
    fdn_ship_flag SMALLINT NULL DEFAULT 0,
    fdn_cable_way SMALLINT NULL DEFAULT 0,
    CONSTRAINT t_fdn_pkey PRIMARY KEY (fdn_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_fdn_detail
-- ────────────────────────────────────────────────────────
CREATE TABLE t_fdn_detail (
    fdn_detail_id BIGSERIAL NOT NULL DEFAULT nextval('t_fdn_detail_fdn_detail_id_seq'::regclass),
    fdn_id VARCHAR(255) NOT NULL,
    fdn_oph_id VARCHAR(255) NOT NULL,
    fdn_oph_bunches_delivered INTEGER NOT NULL,
    fdn_oph_loose_fruit_delivered FLOAT8 NOT NULL,
    fdn_oph_block_code VARCHAR(255) NOT NULL,
    fdn_oph_tph_code VARCHAR(255) NOT NULL,
    fdn_oph_card_id VARCHAR(255) NULL,
    REMARK VARCHAR(255) NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    fdn_oph_platform_no VARCHAR(3) NULL,
    CONSTRAINT t_fdn_detail_pkey PRIMARY KEY (fdn_detail_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_fdn_loader
-- ────────────────────────────────────────────────────────
CREATE TABLE t_fdn_loader (
    fdn_loader_id BIGSERIAL NOT NULL DEFAULT nextval('t_fdn_loader_fdn_loader_id_seq'::regclass),
    fdn_id VARCHAR(255) NOT NULL,
    fdn_loader_employee_code VARCHAR(255) NULL,
    fdn_loader_employee_name VARCHAR(255) NOT NULL,
    fdn_loader_percentage FLOAT8 NOT NULL DEFAULT 0,
    fdn_loader_type SMALLINT NOT NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    fdn_loader_vendor VARCHAR(255) NULL,
    fdn_loader_transporter SMALLINT NULL,
    CONSTRAINT t_fdn_loader_pkey PRIMARY KEY (fdn_loader_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_general_worker_assignment
-- ────────────────────────────────────────────────────────
CREATE TABLE t_general_worker_assignment (
    general_worker_assignment_id VARCHAR(255) NOT NULL,
    general_worker_assignment_date DATE NOT NULL,
    general_worker_assignment_estate_code VARCHAR(255) NULL,
    general_worker_assignment_division_code VARCHAR(255) NULL,
    general_worker_assignment_activity_code VARCHAR(255) NULL,
    general_worker_assignment_activity_name VARCHAR(255) NULL,
    general_worker_assignment_activity_uom VARCHAR(255) NULL,
    general_worker_assignment_employee_code VARCHAR(255) NULL,
    general_worker_assignment_employee_name VARCHAR(255) NULL,
    general_worker_assignment_remark VARCHAR(255) NULL,
    general_worker_assignment_block_code VARCHAR(255) NULL,
    general_worker_assignment_order_number VARCHAR(255) NULL,
    general_worker_assignment_auc_number VARCHAR(255) NULL,
    general_worker_assignment_cost_center VARCHAR(255) NULL,
    general_worker_assignment_created_by VARCHAR(255) NULL,
    general_worker_assignment_created_date DATE NULL,
    general_worker_assignment_created_time TIME WITHOUT TIME ZONE NULL,
    general_worker_assignment_updated_by VARCHAR(255) NULL,
    general_worker_assignment_updated_date DATE NULL,
    general_worker_assignment_updated_time TIME WITHOUT TIME ZONE NULL,
    general_worker_assignment_target_qty FLOAT8 NULL,
    general_worker_block_status SMALLINT NULL,
    CONSTRAINT t_general_worker_assignment_pkey PRIMARY KEY (general_worker_assignment_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_harvester_assignment
-- ────────────────────────────────────────────────────────
CREATE TABLE t_harvester_assignment (
    harvester_assignment_id BIGSERIAL NOT NULL DEFAULT nextval('t_harvester_assignment_harvester_assignment_id_seq'::regclass),
    harvester_assignment_date DATE NULL,
    harvester_assignment_estate_code VARCHAR(255) NULL,
    harvester_assignment_division_code VARCHAR(255) NULL,
    harvester_assignment_block_code VARCHAR(255) NULL,
    harvester_assignment_employee_code VARCHAR(255) NULL,
    harvester_assignment_employee_name VARCHAR(255) NULL,
    harvester_assignment_remark VARCHAR(255) NULL,
    harvester_assignment_created_by VARCHAR(255) NULL,
    harvester_assignment_created_date DATE NULL,
    harvester_assignment_created_time TIME WITHOUT TIME ZONE NULL,
    harvester_assignment_updated_by VARCHAR(255) NULL,
    harvester_assignment_updated_date DATE NULL,
    harvester_assignment_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT t_harvester_assignment_pkey PRIMARY KEY (harvester_assignment_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_harvesting_deduction
-- ────────────────────────────────────────────────────────
CREATE TABLE t_harvesting_deduction (
    harvesting_deduction_id BIGSERIAL NOT NULL DEFAULT nextval('t_harvesting_deduction_harvesting_deduction_id_seq'::regclass),
    harvesting_deduction_date DATE NOT NULL,
    harvesting_deduction_oph_id VARCHAR(255) NULL,
    harvesting_deduction_code VARCHAR(255) NOT NULL,
    harvesting_deduction_qty FLOAT8 NULL,
    harvesting_deduction_created_by VARCHAR(255) NULL,
    harvesting_deduction_is_closed SMALLINT NOT NULL DEFAULT 0,
    harvesting_deduction_created_date DATE NULL,
    harvesting_deduction_created_time TIME WITHOUT TIME ZONE NULL,
    harvesting_deduction_updated_by VARCHAR(255) NULL,
    harvesting_deduction_updated_date DATE NULL,
    harvesting_deduction_updated_time TIME WITHOUT TIME ZONE NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    harvesting_deduction_description VARCHAR(255) NULL,
    REMARK VARCHAR(255) NULL,
    CONSTRAINT t_harvesting_deduction_pkey PRIMARY KEY (harvesting_deduction_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_harvesting_plan
-- ────────────────────────────────────────────────────────
CREATE TABLE t_harvesting_plan (
    harvesting_plan_id BIGSERIAL NOT NULL DEFAULT nextval('t_harvesting_plan_harvesting_plan_id_seq'::regclass),
    harvesting_plan_date DATE NULL,
    harvesting_plan_estate_code VARCHAR(255) NULL,
    harvesting_plan_division_code VARCHAR(255) NULL,
    harvesting_plan_block_code VARCHAR(255) NULL,
    harvesting_plan_total_hk INTEGER NULL,
    harvesting_plan_ha VARCHAR(255) NULL,
    harvesting_plan_assistant_employee_code VARCHAR(255) NULL,
    harvesting_plan_assistant_employee_name VARCHAR(255) NULL,
    harvesting_plan_approval_remark VARCHAR(255) NULL,
    harvesting_plan_is_approved SMALLINT NULL,
    harvesting_plan_approved_by VARCHAR(255) NULL,
    harvesting_plan_approved_by_name VARCHAR(255) NULL,
    harvesting_plan_approved_date DATE NULL,
    harvesting_plan_approved_time TIME WITHOUT TIME ZONE NULL,
    harvesting_plan_created_by VARCHAR(255) NULL,
    harvesting_plan_created_date DATE NULL,
    harvesting_plan_created_time TIME WITHOUT TIME ZONE NULL,
    harvesting_plan_updated_by VARCHAR(255) NULL,
    harvesting_plan_updated_date DATE NULL,
    harvesting_plan_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT t_harvesting_plan_pkey PRIMARY KEY (harvesting_plan_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_mill_grader_oph
-- ────────────────────────────────────────────────────────
CREATE TABLE t_mill_grader_oph (
    oph_mill_grader_oph_id BIGSERIAL NOT NULL DEFAULT nextval('t_mill_grader_oph_oph_mill_grader_oph_id_seq'::regclass),
    oph_id VARCHAR(255) NOT NULL,
    oph_card_id VARCHAR(255) NULL,
    oph_harvesting_method VARCHAR(1) NULL,
    oph_deduction_indicator VARCHAR(255) NULL,
    oph_estate_code VARCHAR(255) NULL,
    oph_plant_code VARCHAR(255) NULL,
    oph_division_code VARCHAR(255) NULL,
    oph_block_code VARCHAR(255) NULL,
    oph_tph_code VARCHAR(255) NULL,
    oph_notes VARCHAR(255) NULL,
    oph_lat VARCHAR(255) NULL,
    oph_long VARCHAR(255) NULL,
    oph_photo VARCHAR(255) NULL,
    mandor_employee_code VARCHAR(255) NULL,
    mandor_employee_name VARCHAR(255) NULL,
    kerani_panen_employee_code VARCHAR(255) NULL,
    kerani_panen_employee_name VARCHAR(255) NULL,
    bunches_wet INTEGER NULL,
    bunches_ripe INTEGER NULL,
    bunches_overripe INTEGER NULL,
    bunches_underripe INTEGER NULL,
    bunches_unripe INTEGER NULL,
    bunches_rotten INTEGER NULL,
    bunches_long_stalk INTEGER NULL,
    bunches_empty INTEGER NULL,
    bunches_dirty INTEGER NULL,
    bunches_unfresh INTEGER NULL,
    bunches_old INTEGER NULL,
    bunches_pest_damaged_old INTEGER NULL,
    bunches_pest_damaged_new INTEGER NULL,
    bunches_diseased INTEGER NULL,
    loose_fruits INTEGER NULL,
    bunches_total INTEGER NULL,
    oph_created_by VARCHAR(255) NULL,
    oph_created_date DATE NULL,
    oph_created_time TIME WITHOUT TIME ZONE NULL,
    oph_updated_by VARCHAR(255) NULL,
    oph_updated_date DATE NULL,
    oph_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT t_mill_grader_oph_pkey PRIMARY KEY (oph_mill_grader_oph_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_oph
-- ────────────────────────────────────────────────────────
CREATE TABLE t_oph (
    oph_id VARCHAR(255) NOT NULL,
    oph_card_id VARCHAR(255) NULL,
    oph_harvesting_method VARCHAR(1) NULL,
    oph_estate_code VARCHAR(255) NULL,
    oph_plant_code VARCHAR(255) NULL,
    oph_division_code VARCHAR(255) NULL,
    oph_block_code VARCHAR(255) NULL,
    oph_tph_code VARCHAR(255) NULL,
    oph_notes VARCHAR(255) NULL,
    oph_lat VARCHAR(255) NULL,
    oph_long VARCHAR(255) NULL,
    oph_photo VARCHAR(255) NULL,
    mandor_employee_code VARCHAR(255) NULL,
    mandor_employee_name VARCHAR(255) NULL,
    bunches_wet INTEGER NULL,
    bunches_ripe INTEGER NULL,
    bunches_overripe INTEGER NULL,
    bunches_underripe INTEGER NULL,
    bunches_unripe INTEGER NULL,
    bunches_rotten INTEGER NULL,
    bunches_long_stalk INTEGER NULL,
    bunches_empty INTEGER NULL,
    bunches_dirty INTEGER NULL,
    bunches_unfresh INTEGER NULL,
    bunches_old INTEGER NULL,
    bunches_pest_damaged_old INTEGER NULL,
    bunches_pest_damaged_new INTEGER NULL,
    bunches_diseased INTEGER NULL,
    loose_fruits INTEGER NULL,
    bunches_total INTEGER NULL,
    bunches_not_sent INTEGER NULL,
    is_planned SMALLINT NULL,
    is_approved SMALLINT NULL,
    is_restant_permanent SMALLINT NULL,
    oph_approved_by VARCHAR(255) NULL,
    oph_approved_by_name VARCHAR(255) NULL,
    oph_approved_date DATE NULL,
    oph_approved_time TIME WITHOUT TIME ZONE NULL,
    oph_is_closed SMALLINT NOT NULL DEFAULT 0,
    oph_created_by VARCHAR(255) NULL,
    oph_created_date DATE NULL,
    oph_created_time TIME WITHOUT TIME ZONE NULL,
    oph_updated_by VARCHAR(255) NULL,
    oph_updated_date DATE NULL,
    oph_updated_time TIME WITHOUT TIME ZONE NULL,
    kerani_panen_employee_code VARCHAR(255) NULL,
    kerani_panen_employee_name VARCHAR(255) NULL,
    oph_is_deleted SMALLINT NOT NULL DEFAULT 0,
    oph_is_revised SMALLINT NULL DEFAULT 0,
    request_id VARCHAR(255) NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    oph_harvesting_deduction_is_locked SMALLINT NOT NULL DEFAULT 0,
    REMARK VARCHAR(255) NULL,
    loose_fruits_is_in_bag SMALLINT NULL,
    loose_fruits_is_dirty SMALLINT NULL,
    oph_closing_is_approved SMALLINT NULL DEFAULT 0,
    oph_closing_is_approved_by VARCHAR(255) NULL,
    oph_closing_approved_timestamp TIMESTAMP NULL,
    oph_deduction_indicator SMALLINT NULL DEFAULT 0,
    adjustment_status SMALLINT NULL DEFAULT 0,
    vra_order_number VARCHAR(30) NULL,
    oph_platform_no VARCHAR(3) NULL,
    CONSTRAINT t_oph_pkey PRIMARY KEY (oph_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_oph_mill_grader_persons
-- ────────────────────────────────────────────────────────
CREATE TABLE t_oph_mill_grader_persons (
    oph_mill_grader_person_id BIGSERIAL NOT NULL DEFAULT nextval('t_oph_mill_grader_persons_oph_mill_grader_person_id_seq'::regclass),
    oph_id VARCHAR(255) NOT NULL,
    oph_person_employee_code VARCHAR(255) NOT NULL,
    oph_person_employee_name VARCHAR(255) NULL,
    oph_person_type SMALLINT NULL,
    CONSTRAINT t_oph_mill_grader_persons_pkey PRIMARY KEY (oph_mill_grader_person_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_oph_persons
-- ────────────────────────────────────────────────────────
CREATE TABLE t_oph_persons (
    oph_person_id BIGSERIAL NOT NULL DEFAULT nextval('t_oph_persons_oph_person_id_seq'::regclass),
    oph_id VARCHAR(255) NOT NULL,
    oph_person_employee_code VARCHAR(255) NOT NULL,
    oph_person_employee_name VARCHAR(255) NULL,
    oph_person_percentage FLOAT8 NULL,
    oph_person_type SMALLINT NULL,
    oph_person_lf_percentage INTEGER NULL,
    CONSTRAINT t_oph_persons_pkey PRIMARY KEY (oph_person_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_oph_supervise
-- ────────────────────────────────────────────────────────
CREATE TABLE t_oph_supervise (
    oph_supervise_id VARCHAR(255) NOT NULL,
    oph_id VARCHAR(255) NOT NULL,
    oph_card_id VARCHAR(255) NULL,
    bunches_wet INTEGER NULL,
    bunches_ripe INTEGER NULL,
    bunches_overripe INTEGER NULL,
    bunches_underripe INTEGER NULL,
    bunches_unripe INTEGER NULL,
    bunches_rotten INTEGER NULL,
    bunches_long_stalk INTEGER NULL,
    bunches_empty INTEGER NULL,
    bunches_dirty INTEGER NULL,
    bunches_unfresh INTEGER NULL,
    bunches_old INTEGER NULL,
    bunches_pest_damaged_old INTEGER NULL,
    bunches_pest_damaged_new INTEGER NULL,
    bunches_diseased INTEGER NULL,
    loose_fruits INTEGER NULL,
    bunches_total INTEGER NULL,
    bunches_not_sent INTEGER NULL,
    oph_supervise_created_by VARCHAR(255) NULL,
    oph_supervise_created_date DATE NULL,
    oph_supervise_created_time TIME WITHOUT TIME ZONE NULL,
    oph_supervise_updated_by VARCHAR(255) NULL,
    oph_supervise_updated_date DATE NULL,
    oph_supervise_updated_time TIME WITHOUT TIME ZONE NULL,
    oph_estimate_tonage FLOAT8 NULL,
    oph_photo VARCHAR(255) NULL,
    oph_is_revised SMALLINT NULL DEFAULT 0,
    loose_fruits_is_in_bag SMALLINT NULL,
    loose_fruits_is_dirty SMALLINT NULL,
    oph_platform_no VARCHAR(3) NULL,
    CONSTRAINT t_oph_supervise_pkey PRIMARY KEY (oph_supervise_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_overtime
-- ────────────────────────────────────────────────────────
CREATE TABLE t_overtime (
    overtime_id BIGSERIAL NOT NULL DEFAULT nextval('t_overtime_overtime_id_seq'::regclass),
    overtime_date DATE NOT NULL,
    overtime_activity_code VARCHAR(255) NOT NULL,
    overtime_activity_name VARCHAR(255) NOT NULL,
    overtime_employee_code VARCHAR(255) NOT NULL,
    overtime_employee_name VARCHAR(255) NOT NULL,
    overtime_start TIME WITHOUT TIME ZONE NULL,
    overtime_end TIME WITHOUT TIME ZONE NULL,
    overtime_duration FLOAT8 NULL,
    overtime_description VARCHAR(255) NULL,
    overtime_plant_code VARCHAR(255) NULL,
    overtime_block_code VARCHAR(255) NULL,
    overtime_order_number VARCHAR(255) NULL,
    overtime_auc_number VARCHAR(255) NULL,
    overtime_cost_center VARCHAR(255) NULL,
    overtime_qty FLOAT8 NULL,
    overtime_uom VARCHAR(255) NULL,
    overtime_is_approved SMALLINT NULL,
    overtime_approved_by VARCHAR(255) NULL,
    overtime_approved_by_name VARCHAR(255) NULL,
    overtime_approved_date DATE NULL,
    overtime_approved_time TIME WITHOUT TIME ZONE NULL,
    overtime_created_by VARCHAR(255) NULL,
    overtime_created_date DATE NULL,
    overtime_created_time TIME WITHOUT TIME ZONE NULL,
    overtime_updated_by VARCHAR(255) NULL,
    overtime_updated_date DATE NULL,
    overtime_updated_time TIME WITHOUT TIME ZONE NULL,
    overtime_is_closed SMALLINT NOT NULL DEFAULT 0,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    overtime_division_code VARCHAR(255) NULL,
    overtime_mandor_employee_code VARCHAR(255) NULL,
    overtime_mandor_employee_name VARCHAR(255) NULL,
    overtime_estate_code VARCHAR(255) NULL,
    overtime_mandays FLOAT8 NULL,
    REMARK VARCHAR(255) NULL,
    overtime_closing_is_approved SMALLINT NOT NULL DEFAULT 0,
    overtime_closing_is_approved_by VARCHAR(255) NULL,
    overtime_closing_approved_timestamp TIMESTAMP NULL,
    overtime_block_status SMALLINT NULL DEFAULT 0,
    adjustment_status SMALLINT NULL DEFAULT 0,
    overtime_wbs_code VARCHAR(255) NULL,
    overtime_wbs_name VARCHAR(255) NULL,
    CONSTRAINT t_overtime_pkey PRIMARY KEY (overtime_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_platform_checking
-- ────────────────────────────────────────────────────────
CREATE TABLE t_platform_checking (
    id VARCHAR(100) NOT NULL,
    tph_id BIGINT NULL,
    tph_code VARCHAR(255) NOT NULL,
    tph_block_code VARCHAR(255) NOT NULL,
    tph_division_code VARCHAR(255) NOT NULL,
    created_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT NOT NULL,
    updated_date TIMESTAMP NULL,
    updated_by BIGINT NULL,
    CONSTRAINT t_platform_checking_pkey PRIMARY KEY (id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_platform_checking_detail
-- ────────────────────────────────────────────────────────
CREATE TABLE t_platform_checking_detail (
    id BIGINT NOT NULL,
    id_platform_checking VARCHAR(100) NOT NULL,
    oph_id VARCHAR(255) NOT NULL,
    notes VARCHAR(255) NULL,
    is_oph_restan BOOLEAN NULL,
    CONSTRAINT t_platform_checking_detail_pkey PRIMARY KEY (id),
    CONSTRAINT fk_t_platform_checking_detail_id_platform_checking FOREIGN KEY (id_platform_checking) REFERENCES t_platform_checking(id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_user_assignment
-- ────────────────────────────────────────────────────────
CREATE TABLE t_user_assignment (
    mandor_id BIGSERIAL NOT NULL DEFAULT nextval('t_user_assignment_mandor_id_seq'::regclass),
    profile_name VARCHAR(255) NULL,
    mandor_employee_code VARCHAR(255) NULL,
    mandor_employee_name VARCHAR(255) NULL,
    employee_code VARCHAR(255) NULL,
    employee_name VARCHAR(255) NULL,
    start_validity DATE NULL,
    end_validity DATE NULL,
    created_by VARCHAR(255) NULL,
    created_date DATE NULL,
    created_time TIME WITHOUT TIME ZONE NULL,
    updated_by VARCHAR(255) NULL,
    updated_date DATE NULL,
    updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT t_user_assignment_pkey PRIMARY KEY (mandor_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_vra
-- ────────────────────────────────────────────────────────
CREATE TABLE t_vra (
    vra_id VARCHAR(255) NOT NULL,
    vra_code VARCHAR(255) NULL,
    vra_posting_date DATE NOT NULL,
    vra_work_center VARCHAR(255) NULL,
    vra_start_time TIME WITHOUT TIME ZONE NULL,
    vra_end_time TIME WITHOUT TIME ZONE NULL,
    vra_reason VARCHAR(255) NULL,
    vra_measurement VARCHAR(255) NULL,
    vra_confirm_text VARCHAR(255) NULL,
    vra_confirm_text2 VARCHAR(255) NULL,
    vra_actual FLOAT8 NULL,
    vra_is_planned SMALLINT NULL,
    vra_is_approved SMALLINT NULL,
    vra_approved_by VARCHAR(255) NULL,
    vra_approved_by_name VARCHAR(255) NULL,
    vra_approved_date DATE NULL,
    vra_approved_time TIME WITHOUT TIME ZONE NULL,
    vra_is_closed SMALLINT NOT NULL DEFAULT 0,
    vra_created_by VARCHAR(255) NULL,
    vra_created_date DATE NULL,
    vra_created_time TIME WITHOUT TIME ZONE NULL,
    vra_updated_by VARCHAR(255) NULL,
    vra_updated_date DATE NULL,
    vra_updated_time TIME WITHOUT TIME ZONE NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    REMARK VARCHAR(255) NULL,
    vra_closing_is_approved SMALLINT NOT NULL DEFAULT 0,
    vra_closing_is_approved_by VARCHAR(255) NULL,
    vra_closing_approved_timestamp TIMESTAMP NULL,
    adjustment_status SMALLINT NULL DEFAULT 0,
    vra_is_deleted SMALLINT NULL,
    vra_object_type VARCHAR(255) NOT NULL DEFAULT 0,
    CONSTRAINT t_vra_pkey PRIMARY KEY (vra_id)
);

CREATE UNIQUE INDEX t_vra_pkey1 ON public.t_vra USING btree (vra_id);

-- ────────────────────────────────────────────────────────
-- TABLE: t_work_assignment
-- ────────────────────────────────────────────────────────
CREATE TABLE t_work_assignment (
    work_assignment_id VARCHAR(255) NOT NULL,
    work_assignment_date DATE NOT NULL,
    work_assignment_estate_code VARCHAR(255) NULL,
    work_assignment_plant_code VARCHAR(255) NULL,
    work_assignment_division_code VARCHAR(255) NULL,
    work_assignment_activity_code VARCHAR(255) NULL,
    work_assignment_activity_name VARCHAR(255) NULL,
    work_assignment_activity_uom VARCHAR(255) NULL,
    work_assignment_qty FLOAT8 NULL,
    work_assignment_remark VARCHAR(255) NULL,
    work_assignment_mandor_employee_code VARCHAR(255) NULL,
    work_assignment_mandor_employee_name VARCHAR(255) NULL,
    work_assignment_employee_code VARCHAR(255) NULL,
    work_assignment_employee_name VARCHAR(255) NULL,
    work_assignment_block_code VARCHAR(255) NULL,
    work_assignment_order_number VARCHAR(255) NULL,
    work_assignment_auc_number VARCHAR(255) NULL,
    work_assignment_cost_center VARCHAR(255) NULL,
    work_assignment_flexrate FLOAT8 NULL,
    work_assignment_start_time TIME WITHOUT TIME ZONE NULL,
    work_assignment_end_time TIME WITHOUT TIME ZONE NULL,
    work_assignment_duration FLOAT8 NULL,
    work_assignment_description VARCHAR(255) NULL,
    work_assignment_customer_code VARCHAR(255) NULL,
    work_assignment_is_planned SMALLINT NULL,
    work_assignment_is_approved SMALLINT NULL,
    work_assignment_approved_by VARCHAR(255) NULL,
    work_assignment_approved_by_name VARCHAR(255) NULL,
    work_assignment_approved_date DATE NULL,
    work_assignment_approved_time TIME WITHOUT TIME ZONE NULL,
    work_assignment_is_closed SMALLINT NOT NULL DEFAULT 0,
    work_assignment_created_by VARCHAR(255) NULL,
    work_assignment_created_date DATE NULL,
    work_assignment_created_time TIME WITHOUT TIME ZONE NULL,
    work_assignment_updated_by VARCHAR(255) NULL,
    work_assignment_updated_date DATE NULL,
    work_assignment_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT t_work_assignment_pkey PRIMARY KEY (work_assignment_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_workdone
-- ────────────────────────────────────────────────────────
CREATE TABLE t_workdone (
    workdone_id VARCHAR(255) NOT NULL,
    workdone_date DATE NOT NULL,
    workdone_estate_code VARCHAR(255) NULL,
    workdone_plant_code VARCHAR(255) NULL,
    workdone_division_code VARCHAR(255) NULL,
    workdone_activity_code VARCHAR(255) NULL,
    workdone_activity_name VARCHAR(255) NULL,
    workdone_activity_uom VARCHAR(255) NULL,
    workdone_mandays FLOAT8 NULL,
    workdone_qty FLOAT8 NULL,
    workdone_remark VARCHAR(255) NULL,
    workdone_mandor_employee_code VARCHAR(255) NULL,
    workdone_mandor_employee_name VARCHAR(255) NULL,
    workdone_employee_code VARCHAR(255) NULL,
    workdone_employee_name VARCHAR(255) NULL,
    workdone_block_code VARCHAR(255) NULL,
    workdone_order_number VARCHAR(255) NULL,
    workdone_auc_number VARCHAR(255) NULL,
    workdone_cost_center VARCHAR(255) NULL,
    workdone_flexrate FLOAT8 NULL,
    workdone_start_time TIME WITHOUT TIME ZONE NULL,
    workdone_end_time TIME WITHOUT TIME ZONE NULL,
    workdone_duration FLOAT8 NULL,
    workdone_description VARCHAR(255) NULL,
    workdone_customer_code VARCHAR(255) NULL,
    workdone_is_planned SMALLINT NULL,
    workdone_is_approved SMALLINT NULL,
    workdone_approved_by VARCHAR(255) NULL,
    workdone_approved_by_name VARCHAR(255) NULL,
    workdone_approved_date DATE NULL,
    workdone_approved_time TIME WITHOUT TIME ZONE NULL,
    workdone_is_closed SMALLINT NOT NULL DEFAULT 0,
    workdone_created_by VARCHAR(255) NULL,
    workdone_created_date DATE NULL,
    workdone_created_time TIME WITHOUT TIME ZONE NULL,
    workdone_updated_by VARCHAR(255) NULL,
    workdone_updated_date DATE NULL,
    workdone_updated_time TIME WITHOUT TIME ZONE NULL,
    workdone_manday_old FLOAT8 NULL,
    workdone_target_qty FLOAT8 NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    REMARK VARCHAR(255) NULL,
    workdone_closing_is_approved SMALLINT NOT NULL DEFAULT 0,
    workdone_closing_is_approved_by VARCHAR(255) NULL,
    workdone_closing_approved_timestamp TIMESTAMP NULL,
    workdone_block_status SMALLINT NULL,
    adjustment_status SMALLINT NULL DEFAULT 0,
    workdone_wbs_code VARCHAR(255) NULL DEFAULT NULL::character varying,
    workdone_wbs_name VARCHAR(255) NULL DEFAULT NULL::character varying,
    CONSTRAINT t_workdone_pkey PRIMARY KEY (workdone_id)
);

CREATE UNIQUE INDEX t_workdone_pkey1 ON public.t_workdone USING btree (workdone_id);

-- ────────────────────────────────────────────────────────
-- TABLE: t_workdone_material
-- ────────────────────────────────────────────────────────
CREATE TABLE t_workdone_material (
    workdone_material_id BIGSERIAL NOT NULL DEFAULT nextval('t_workdone_material_workdone_material_id_seq'::regclass),
    workdone_id VARCHAR(255) NOT NULL,
    workdone_material_code VARCHAR(255) NULL,
    workdone_material_name VARCHAR(255) NULL,
    workdone_material_uom VARCHAR(255) NULL,
    workdone_material_qty FLOAT8 NULL,
    CONSTRAINT t_workdone_material_pkey PRIMARY KEY (workdone_material_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_workplan
-- ────────────────────────────────────────────────────────
CREATE TABLE t_workplan (
    workplan_id VARCHAR(255) NOT NULL,
    workplan_date DATE NOT NULL,
    workplan_estate_code VARCHAR(255) NULL,
    workplan_division_code VARCHAR(255) NULL,
    workplan_activity_code VARCHAR(255) NULL,
    workplan_activity_name VARCHAR(255) NULL,
    workplan_activity_uom VARCHAR(255) NULL,
    workplan_target FLOAT8 NULL,
    workplan_total_hk INTEGER NULL,
    workplan_remark VARCHAR(255) NULL,
    workplan_approval_remark VARCHAR(255) NULL,
    workplan_assistant_employee_code VARCHAR(255) NULL,
    workplan_assistant_employee_name VARCHAR(255) NULL,
    workplan_block_code VARCHAR(255) NULL,
    workplan_order_number VARCHAR(255) NULL,
    workplan_auc_number VARCHAR(255) NULL,
    workplan_cost_center VARCHAR(255) NULL,
    workplan_is_approved SMALLINT NULL,
    workplan_approved_by VARCHAR(255) NULL,
    workplan_approved_by_name VARCHAR(255) NULL,
    workplan_approved_date DATE NULL,
    workplan_approved_time TIME WITHOUT TIME ZONE NULL,
    workplan_created_by VARCHAR(255) NULL,
    workplan_created_date DATE NULL,
    workplan_created_time TIME WITHOUT TIME ZONE NULL,
    workplan_updated_by VARCHAR(255) NULL,
    workplan_updated_date DATE NULL,
    workplan_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT t_workplan_pkey PRIMARY KEY (workplan_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: t_workplan_material
-- ────────────────────────────────────────────────────────
CREATE TABLE t_workplan_material (
    workplan_material_id BIGSERIAL NOT NULL DEFAULT nextval('t_workplan_material_workplan_material_id_seq'::regclass),
    workplan_id VARCHAR(255) NOT NULL,
    workplan_material_code VARCHAR(255) NULL,
    workplan_material_name VARCHAR(255) NULL,
    workplan_material_uom VARCHAR(255) NULL,
    workplan_material_qty FLOAT8 NULL,
    CONSTRAINT t_workplan_material_pkey PRIMARY KEY (workplan_material_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: tc_user
-- ────────────────────────────────────────────────────────
CREATE TABLE tc_user (
    user_id BIGSERIAL NOT NULL DEFAULT nextval('tc_user_user_id_seq'::regclass),
    user_employee_code VARCHAR(255) NULL,
    user_internal_employee_code VARCHAR(255) NULL,
    user_name VARCHAR(255) NULL,
    user_login VARCHAR(255) NULL,
    user_password VARCHAR(255) NULL,
    user_status VARCHAR(255) NULL,
    user_email VARCHAR(255) NULL,
    user_role SMALLINT NULL,
    user_token VARCHAR(255) NULL,
    user_forget_password_token VARCHAR(255) NULL,
    user_created_by VARCHAR(255) NULL,
    user_created_date DATE NULL,
    user_created_time TIME WITHOUT TIME ZONE NULL,
    user_updated_by VARCHAR(255) NULL,
    user_updated_date DATE NULL,
    user_updated_time TIME WITHOUT TIME ZONE NULL,
    CONSTRAINT tc_user_pkey PRIMARY KEY (user_id)
);


-- ────────────────────────────────────────────────────────
-- TABLE: tr_gi_detail
-- ────────────────────────────────────────────────────────
CREATE TABLE tr_gi_detail (
    tr_gi_detail_id BIGSERIAL NOT NULL DEFAULT nextval('tr_gi_detail_tr_gi_detail_id_seq'::regclass),
    tr_gi_detail_header_id BIGINT NULL,
    tr_gi_detail_material_code VARCHAR(255) NULL,
    tr_gi_detail_material_name VARCHAR(255) NULL,
    tr_gi_detail_batch VARCHAR(255) NULL,
    tr_gi_detail_qty FLOAT8 NULL,
    tr_gi_detail_uom VARCHAR(255) NULL,
    tr_gi_detail_base_qty FLOAT8 NULL,
    tr_gi_detail_base_uom VARCHAR(255) NULL,
    tr_gi_detail_sloc VARCHAR(255) NULL,
    tr_gi_detail_gr_receipient VARCHAR(255) NULL,
    tr_gi_detail_qr_code_number VARCHAR(255) NULL,
    tr_gi_detail_gr_detail_id INTEGER NULL,
    tr_gi_detail_is_cancelled BOOLEAN NULL DEFAULT false,
    tr_gi_detail_order_number VARCHAR(30) NULL,
    tr_gi_detail_line_id VARCHAR(30) NULL,
    tr_gi_detail_notes VARCHAR(255) NULL,
    tr_gi_detail_mobile_qty FLOAT8 NOT NULL DEFAULT 0,
    tr_gi_detail_mobile_uom VARCHAR(255) NULL,
    tr_gi_detail_created_by VARCHAR(255) NULL,
    tr_gi_detail_created_timestamp TIMESTAMP NULL,
    tr_gi_detail_updated_by VARCHAR(255) NULL,
    tr_gi_detail_updated_timestamp TIMESTAMP NULL
);


-- ────────────────────────────────────────────────────────
-- TABLE: tr_gi_header
-- ────────────────────────────────────────────────────────
CREATE TABLE tr_gi_header (
    tr_gi_header_id BIGSERIAL NOT NULL DEFAULT nextval('tr_gi_header_tr_gi_header_id_seq'::regclass),
    tr_gi_header_number VARCHAR(255) NULL,
    tr_gi_header_number_from_mobile VARCHAR(255) NULL,
    tr_gi_header_plant_code VARCHAR(255) NULL,
    tr_gi_header_division_code VARCHAR(255) NULL,
    tr_gi_header_created_plant_code VARCHAR(255) NULL,
    tr_gi_header_sap_doc VARCHAR(255) NULL,
    tr_gi_header_pstg_date VARCHAR(255) NULL,
    tr_gi_header_doc_date VARCHAR(255) NULL,
    tr_gi_header_bol VARCHAR(255) NULL,
    tr_gi_header_txt VARCHAR(255) NULL,
    tr_gi_header_material_slip VARCHAR(255) NULL,
    tr_gi_header_mvt_code VARCHAR(255) NULL,
    tr_gi_header_sloc_code VARCHAR(255) NULL,
    tr_gi_header_cost_center VARCHAR(255) NULL,
    tr_gi_header_sap_year VARCHAR(255) NULL,
    tr_gi_header_status VARCHAR(255) NULL,
    tr_gi_header_error VARCHAR(255) NULL,
    tr_gi_header_order_number VARCHAR(255) NULL,
    tr_gi_header_order_type VARCHAR(2) NULL,
    tr_gi_header_gl_account VARCHAR(255) NULL,
    tr_gi_header_wbs_code VARCHAR(255) NULL,
    tr_gi_header_block_code VARCHAR(255) NULL,
    tr_gi_header_worktype_code VARCHAR(255) NULL,
    tr_gi_header_recipient VARCHAR(50) NULL,
    tr_gi_header_mvt_sap VARCHAR(255) NULL,
    tr_gi_header_notes VARCHAR(255) NULL,
    tr_gi_header_mobile_is_submit BOOLEAN NULL DEFAULT false,
    tr_gi_header_is_approved SMALLINT NULL,
    tr_gi_header_approved_by VARCHAR(255) NULL,
    tr_gi_header_approved_timestamp TIMESTAMP NULL,
    tr_gi_header_is_closed SMALLINT NOT NULL DEFAULT 0,
    tr_gi_header_closing_is_approved SMALLINT NOT NULL DEFAULT 0,
    tr_gi_header_closing_is_approved_by VARCHAR(255) NULL,
    tr_gi_header_closing_approved_timestamp TIMESTAMP NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    remark VARCHAR(255) NULL,
    adjustment_status SMALLINT NULL DEFAULT 0,
    tr_gi_header_material_doc_sap VARCHAR(50) NULL,
    tr_gi_header_material_doc_year_sap VARCHAR(4) NULL,
    tr_gi_header_is_planned SMALLINT NOT NULL DEFAULT 0,
    tr_gi_header_is_deleted SMALLINT NOT NULL DEFAULT 0,
    tr_gi_header_created_by VARCHAR(255) NULL,
    tr_gi_header_created_timestamp TIMESTAMP NULL,
    tr_gi_header_updated_by VARCHAR(255) NULL,
    tr_gi_header_updated_timestamp TIMESTAMP NULL,
    tr_gi_header_is_cancelled BOOLEAN NOT NULL DEFAULT false,
    tr_gi_header_lc VARCHAR(1) NULL DEFAULT 0
);


-- ────────────────────────────────────────────────────────
-- TABLE: tr_gi_plan
-- ────────────────────────────────────────────────────────
CREATE TABLE tr_gi_plan (
    tr_gi_plan_id BIGSERIAL NOT NULL DEFAULT nextval('tr_gi_plan_tr_gi_plan_id_seq'::regclass),
    tr_gi_plan_number VARCHAR(255) NULL,
    tr_gi_plan_number_from_mobile VARCHAR(255) NULL,
    tr_gi_plan_plant_code VARCHAR(255) NULL,
    tr_gi_plan_division_code VARCHAR(255) NULL,
    tr_gi_plan_created_plant_code VARCHAR(255) NULL,
    tr_gi_plan_sap_doc VARCHAR(255) NULL,
    tr_gi_plan_pstg_date VARCHAR(255) NULL,
    tr_gi_plan_doc_date VARCHAR(255) NULL,
    tr_gi_plan_bol VARCHAR(255) NULL,
    tr_gi_plan_txt VARCHAR(255) NULL,
    tr_gi_plan_material_slip VARCHAR(255) NULL,
    tr_gi_plan_mvt_code VARCHAR(255) NULL,
    tr_gi_plan_sloc_code VARCHAR(255) NULL,
    tr_gi_plan_cost_center VARCHAR(255) NULL,
    tr_gi_plan_sap_year VARCHAR(255) NULL,
    tr_gi_plan_status VARCHAR(255) NULL,
    tr_gi_plan_error VARCHAR(255) NULL,
    tr_gi_plan_order_number VARCHAR(255) NULL,
    tr_gi_plan_order_type VARCHAR(2) NOT NULL DEFAULT 'MO'::character varying,
    tr_gi_plan_gl_account VARCHAR(255) NULL,
    tr_gi_plan_wbs_code VARCHAR(255) NULL,
    tr_gi_plan_block_code VARCHAR(255) NULL,
    tr_gi_plan_worktype_code VARCHAR(255) NULL,
    tr_gi_plan_recipient VARCHAR(50) NULL,
    tr_gi_plan_mvt_sap VARCHAR(255) NULL,
    tr_gi_plan_notes VARCHAR(255) NULL,
    tr_gi_plan_mobile_is_submit BOOLEAN NULL DEFAULT false,
    tr_gi_plan_is_approved SMALLINT NULL,
    tr_gi_plan_approved_by VARCHAR(255) NULL,
    tr_gi_plan_approved_timestamp TIMESTAMP NULL,
    tr_gi_plan_is_closed SMALLINT NOT NULL DEFAULT 0,
    tr_gi_plan_closing_is_approved SMALLINT NOT NULL DEFAULT 0,
    tr_gi_plan_closing_is_approved_by VARCHAR(255) NULL,
    tr_gi_plan_closing_approved_timestamp TIMESTAMP NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    remark VARCHAR(255) NULL,
    adjustment_status SMALLINT NULL DEFAULT 0,
    tr_gi_plan_material_doc_sap VARCHAR(50) NULL,
    tr_gi_plan_material_doc_year_sap VARCHAR(4) NULL,
    tr_gi_plan_is_planned SMALLINT NOT NULL DEFAULT 0,
    tr_gi_plan_is_deleted SMALLINT NOT NULL DEFAULT 0,
    tr_gi_plan_created_by VARCHAR(255) NULL,
    tr_gi_plan_created_timestamp TIMESTAMP NULL,
    tr_gi_plan_updated_by VARCHAR(255) NULL,
    tr_gi_plan_updated_timestamp TIMESTAMP NULL,
    tr_gi_plan_is_cancelled BOOLEAN NOT NULL DEFAULT false
);


-- ────────────────────────────────────────────────────────
-- TABLE: tr_gi_plan_detail
-- ────────────────────────────────────────────────────────
CREATE TABLE tr_gi_plan_detail (
    tr_gi_plan_detail_id BIGSERIAL NOT NULL DEFAULT nextval('tr_gi_plan_detail_tr_gi_plan_detail_id_seq'::regclass),
    tr_gi_plan_detail_header_id BIGINT NULL,
    tr_gi_plan_detail_material_code VARCHAR(255) NULL,
    tr_gi_plan_detail_material_name VARCHAR(255) NULL,
    tr_gi_plan_detail_batch VARCHAR(255) NULL,
    tr_gi_plan_detail_qty FLOAT8 NULL,
    tr_gi_plan_detail_uom VARCHAR(255) NULL,
    tr_gi_plan_detail_base_qty FLOAT8 NULL,
    tr_gi_plan_detail_base_uom VARCHAR(255) NULL,
    tr_gi_plan_detail_sloc VARCHAR(255) NULL,
    tr_gi_plan_detail_gr_receipient VARCHAR(255) NULL,
    tr_gi_plan_detail_qr_code_number VARCHAR(255) NULL,
    tr_gi_plan_detail_gr_detail_id INTEGER NULL,
    tr_gi_plan_detail_is_cancelled BOOLEAN NULL DEFAULT false,
    tr_gi_plan_detail_order_number VARCHAR(30) NULL,
    tr_gi_plan_detail_line_id VARCHAR(30) NULL,
    tr_gi_plan_detail_notes VARCHAR(255) NULL,
    tr_gi_plan_detail_mobile_qty FLOAT8 NOT NULL DEFAULT 0,
    tr_gi_plan_detail_mobile_uom VARCHAR(255) NULL,
    tr_gi_plan_detail_created_by VARCHAR(255) NULL,
    tr_gi_plan_detail_created_timestamp TIMESTAMP NULL,
    tr_gi_plan_detail_updated_by VARCHAR(255) NULL,
    tr_gi_plan_detail_updated_timestamp TIMESTAMP NULL
);


-- ────────────────────────────────────────────────────────
-- TABLE: tr_gr_detail
-- ────────────────────────────────────────────────────────
CREATE TABLE tr_gr_detail (
    tr_gr_detail_id BIGSERIAL NOT NULL DEFAULT nextval('tr_gr_detail_tr_gr_detail_id_seq'::regclass),
    tr_gr_detail_header_id BIGINT NULL,
    tr_gr_detail_material_code VARCHAR(255) NULL,
    tr_gr_detail_material_name VARCHAR(255) NULL,
    tr_gr_detail_batch VARCHAR(255) NULL,
    tr_gr_detail_qty FLOAT8 NULL,
    tr_gr_detail_uom VARCHAR(255) NULL,
    tr_gr_detail_base_qty FLOAT8 NULL,
    tr_gr_detail_base_uom VARCHAR(255) NULL,
    tr_gr_detail_sloc VARCHAR(255) NULL,
    tr_gr_detail_gr_receipient VARCHAR(255) NULL,
    tr_gr_detail_qr_code_number VARCHAR(255) NULL,
    tr_gr_detail_gr_detail_id INTEGER NULL,
    tr_gr_detail_is_cancelled BOOLEAN NULL DEFAULT false,
    tr_gr_detail_order_number VARCHAR(30) NULL,
    tr_gr_detail_line_id VARCHAR(30) NULL,
    tr_gr_detail_notes VARCHAR(255) NULL,
    tr_gr_detail_mobile_qty FLOAT8 NOT NULL DEFAULT 0,
    tr_gr_detail_mobile_uom VARCHAR(255) NULL,
    tr_gr_detail_created_by VARCHAR(255) NULL,
    tr_gr_detail_created_timestamp TIMESTAMP NULL,
    tr_gr_detail_updated_by VARCHAR(255) NULL,
    tr_gr_detail_updated_timestamp TIMESTAMP NULL
);


-- ────────────────────────────────────────────────────────
-- TABLE: tr_gr_header
-- ────────────────────────────────────────────────────────
CREATE TABLE tr_gr_header (
    tr_gr_header_id BIGSERIAL NOT NULL DEFAULT nextval('tr_gr_header_tr_gr_header_id_seq'::regclass),
    tr_gr_header_number VARCHAR(255) NULL,
    tr_gr_header_number_from_mobile VARCHAR(255) NULL,
    tr_gr_header_plant_code VARCHAR(255) NULL,
    tr_gr_header_created_plant_code VARCHAR(255) NULL,
    tr_gr_header_sap_doc VARCHAR(255) NULL,
    tr_gr_header_pstg_date VARCHAR(255) NULL,
    tr_gr_header_doc_date VARCHAR(255) NULL,
    tr_gr_header_doc_note VARCHAR(255) NULL,
    tr_gr_header_bill_of_lading VARCHAR(255) NULL,
    tr_gr_header_supplier_name VARCHAR(255) NULL,
    tr_gr_header_bol VARCHAR(255) NULL,
    tr_gr_header_txt VARCHAR(255) NULL,
    tr_gr_header_mvt_code VARCHAR(255) NULL,
    tr_gr_header_sloc_code VARCHAR(255) NULL,
    tr_gr_header_sap_year VARCHAR(255) NULL,
    tr_gr_header_status VARCHAR(255) NULL,
    tr_gr_header_error VARCHAR(255) NULL,
    tr_gr_header_order_number VARCHAR(255) NULL,
    tr_gr_header_gl_account VARCHAR(255) NULL,
    tr_gr_header_recipient VARCHAR(255) NULL,
    tr_gr_header_mvt_sap VARCHAR(255) NULL,
    tr_gr_header_notes VARCHAR(255) NULL,
    tr_gr_header_mobile_is_submit BOOLEAN NULL DEFAULT false,
    tr_gr_header_is_approved SMALLINT NULL,
    tr_gr_header_approved_by VARCHAR(255) NULL,
    tr_gr_header_approved_timestamp TIMESTAMP NULL,
    tr_gr_header_is_closed SMALLINT NOT NULL DEFAULT 0,
    tr_gr_header_closing_is_approved SMALLINT NOT NULL DEFAULT 0,
    tr_gr_header_closing_is_approved_by VARCHAR(255) NULL,
    tr_gr_header_closing_approved_timestamp TIMESTAMP NULL,
    integration_status SMALLINT NULL DEFAULT '-1'::integer,
    request_id VARCHAR(255) NULL,
    remark VARCHAR(255) NULL,
    adjustment_status SMALLINT NULL DEFAULT 0,
    tr_gr_header_material_doc_sap VARCHAR(50) NULL,
    tr_gr_header_material_doc_year_sap VARCHAR(4) NULL,
    tr_gr_header_is_planned SMALLINT NOT NULL DEFAULT 0,
    tr_gr_header_is_deleted SMALLINT NOT NULL DEFAULT 0,
    tr_gr_header_created_by VARCHAR(255) NULL,
    tr_gr_header_created_timestamp TIMESTAMP NULL,
    tr_gr_header_updated_by VARCHAR(255) NULL,
    tr_gr_header_updated_timestamp TIMESTAMP NULL,
    tr_gr_header_is_cancelled BOOLEAN NOT NULL DEFAULT false
);
