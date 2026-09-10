# EPMS Laravel Migration Status — Menu per Role

**Legend:**
- ✅ = Migrated (Laravel screen exists, HTTP 200)
- 🚧 = Partial (controller exists but incomplete / not tested)
- ❌ = Not migrated (CI3 only)
- 🔴 = Out of scope (tabel tidak ada di Laravel DB)

**Last Updated:** 2026-09-09

---

## ROLE: Estate Staff (role_code: `estate_staff`, `staff`)

Menu CI3 yang diakses Estate Staff:

### Dashboard & Overview
| Menu | Status | Notes |
|------|--------|-------|
| Dashboard | ✅ | `/dashboard` |
| Overview | ✅ | Overview per estate |

### Transactions (Entry)
| Menu | Status | Notes |
|------|--------|-------|
| **Attendance** | ✅ | `/transactions/attendance` |
| **Work Completion** | ✅ | `/transactions/workdone` |
| General Worker Assignment | ❌ | CI3: `assignment/general_worker_assignment` |
| Harvester Assignment | ❌ | CI3: `assignment/harvester_assignment` |
| **OPH (Palm)** | ✅ | `/transactions/oph` |
| OPH Mill Grader | ❌ | CI3: `transaction/oph_mill_grader` |
| **CP1** | ✅ | `/transactions/cp1` |
| **CP2** | ✅ | `/transactions/cp2` |
| **FDN (Palm)** | ✅ | `/transactions/fdn` |
| **Work Overtime** | ✅ | `/transactions/overtime` |
| **VRA** | ✅ | `/transactions/vra` |
| **Platform Checking** | ✅ | `/transactions/platform-checking` |
| **Harvesting Chit Coconut** | ❌ | `/transactions/coconut-harvesting-chit` — tabel ADA (t_coconut_oph) |
| **CP Coconut** | ❌ | `/transactions/cp-coconut` — tabel ADA (t_checkpoint) |
| **Grading Coconut** | ❌ | Pakai `t_coconut_oph` (kolom grading_code, grading_weight) |
| **FDN Coconut** | ❌ | `/transactions/coconut-fdn` — tabel ADA (t_coconut_fdn) |

### Closing SAP (Estate Staff)
| Menu | Status | Notes |
|------|--------|-------|
| **Closing → Attendance** | ✅ | `/closing/attendance` |
| **Closing → OPH** | ✅ | `/closing/oph` |
| **Closing → Work Completion** | ✅ | `/closing/workdone` |
| **Closing → CP** | ✅ | `/closing/cp` |
| **Closing → FDN** | ✅ | `/closing/fdn` |
| **Closing → Overtime** | ✅ | `/closing/overtime` |
| **Closing → VRA** | ✅ | `/closing/vra` |
| Closing → Coconut HC | ❌ | `/closing/coconut-chit` — tabel ADA |
| Closing → Coconut FDN | ❌ | `/closing/coconut-fdn` — tabel ADA |
| **Adjustment Log** | ✅ | `/closing/adjustment` |

---

## ROLE: Estate Manager (role_code: `estate_manager`)

Menu CI3 yang diakses Estate Manager:

### Dashboard & Overview
| Menu | Status | Notes |
|------|--------|-------|
| Dashboard | ✅ | `/dashboard` |
| Overview | ✅ | Overview per estate |

### SAP Closing Approval (Estate Manager)
| Menu | Status | Notes |
|------|--------|-------|
| **Approval → Attendance** | ✅ | `/sap-approval/attendance` |
| **Approval → General Work** | ✅ | `/sap-approval/workdone` |
| **Approval → OPH** | ✅ | `/sap-approval/oph` |
| **Approval → Overtime** | ✅ | `/sap-approval/overtime` |

### Planning (Estate Manager)
| Menu | Status | Notes |
|------|--------|-------|
| Workplan | ❌ | CI3: `planning/workplan` |
| Harvesting Plan (Palm) | ❌ | CI3: `planning/harvesting_plan` |
| Harvesting Plan (Coconut) | ❌ | CI3: `planning/harvesting_plan_coconut` — tabel ADA |
| GI Plan | ❌ | CI3: `planning/gi_plan` |

### Reporting (Estate Manager)
| Menu | Status | Notes |
|------|--------|-------|
| All Reports | ❌ | CI3: `reporting/*` folder (20+ reports) |

---

## ROLE: Assistant Manager (role_code: `asst_manager`)

Menu CI3 yang diakses Assistant Manager:

### Dashboard
| Menu | Status | Notes |
|------|--------|-------|
| Dashboard | ✅ | `/dashboard` |

### Approval (Assistant Manager)
| Menu | Status | Notes |
|------|--------|-------|
| Workplan Approval | ❌ | CI3: `approval/workplan` |
| Harvesting Plan Approval | ❌ | CI3: `approval/harvesting_plan` |
| Harvesting Plan Coconut Approval | ❌ | CI3: `approval/harvesting_plan_coconut` — tabel ADA |
| GI Plan Approval | ❌ | CI3: `approval/gi_plan` |
| Unplanned Activity Approval | ❌ | CI3: `approval/unplanned_activity` |
| OPH Approval | ❌ | CI3: `approval/oph` |
| Harvesting Chit Coconut Approval | ❌ | CI3: `approval/harvesting_chit_coconut` — tabel ADA |
| Overtime Approval | ❌ | CI3: `approval/overtime` |

### Planning
| Menu | Status | Notes |
|------|--------|-------|
| Workplan | ❌ | Same as EM |
| Harvesting Plan | ❌ | Same as EM |

### Reporting
| Menu | Status | Notes |
|------|--------|-------|
| All Reports | ❌ | Same as EM |

---

## ROLE: Admin / Company Admin (role_code: `admin`, `company_admin`)

Menu CI3 yang diakses Admin:

### Master Data (Full Access)
| Menu | Status | Notes |
|------|--------|-------|
| **Employee** | ✅ | `/masters/employee` |
| **Division** | ✅ | `/masters/division` |
| **Block** | ✅ | `/masters/block` |
| **Activity** | ✅ | `/masters/activity` |
| **Attendance Code** | ✅ | `/masters/attendance` |
| **Estate** | ✅ | `/masters/estate` |
| Work Center | ❌ | CI3: `masters/work_center` |
| Work Type | ❌ | CI3: `masters/worktype` |
| Meas Point | ❌ | CI3: `masters/meas_point` |
| Task | ❌ | CI3: `masters/task` |
| Report OPH | ❌ | CI3: `masters/report_oph` |
| **VRA** | ✅ | `/masters/vra` |
| Receiving Point | ❌ | CI3: `masters/receiving_point` |
| **Bin** | ✅ | `/masters/bin` |
| **Destination** | ✅ | `/masters/destination` |
| Material | ❌ | CI3: `masters/material` |
| Coconut Material | ❌ | CI3: `masters/coconut_material` — tabel ADA (m_coconut_material) |
| UOM | ❌ | CI3: `masters/uom` |
| **Device** | ✅ | `/masters/device` |
| Deduction Code | ❌ | CI3: `masters/deduction_code` |
| **OPH Card** | ✅ | `/masters/oph_card` |
| **FDN Card** | ✅ | `/masters/fdn_card` |
| Vendor | ❌ | CI3: `masters/vendor` |
| WBS | ❌ | CI3: `masters/wbs` |
| Harvesting Method | ❌ | CI3: `masters/h_method` |
| Confirmation Text | ❌ | CI3: `masters/confirm_text` |
| Sales Order | ❌ | CI3: `masters/sales_order` |
| QR Code | ❌ | CI3: `masters/qrcode` |
| Coconut Activity Type | ❌ | CI3: `masters/coconut_activity_type` — tabel ADA |
| Durian (8 master tables) | ❌ | Tabel ADA: variety, grading, fertilizer, pesticide, disease, soil, task, activity |

### Master Data SAP (Admin only)
| Menu | Status | Notes |
|------|--------|-------|
| SLOC | ❌ | CI3: `masters/sloc` |
| GL Account | ❌ | CI3: `masters/gl_account` |
| GL Acc Order | ❌ | CI3: `masters/glacc_order` |
| Movement Type | ❌ | CI3: `masters/mvt_type` |
| Cost Center | ❌ | CI3: `masters/cost_center` |
| Plant Equipment Order | ❌ | CI3: `masters/pe_o` |
| Maintenance Order | ❌ | CI3: `masters/maint_order` |

### Grouping
| Menu | Status | Notes |
|------|--------|-------|
| **User Assignment** | ✅ | `/grouping/user-assignment` |
| **Gang Employee** | ✅ | `/grouping/gang-employee` |
| **Mapping AM-Division** | ✅ | `/grouping/mapping-am-division` |
| **Mapping Field Staff-Gang** | ✅ | `/grouping/mapping-fs-gang` |

### GI/GR Transactions (Admin + Warehouse Clerk mobile)
| Menu | Status | Notes |
|------|--------|-------|
| GR Form | ❌ | CI3: `trans_gi_gr/gr_form` |
| GI Form | ❌ | CI3: `trans_gi_gr/gi_form` |
| GR Closing | ❌ | CI3: `trans_gi_gr/gr_closing` |
| GI Closing | ❌ | CI3: `trans_gi_gr/gi_closing` |

### Audit & Config
| Menu | Status | Notes |
|------|--------|-------|
| **Activity Log** | ✅ | `/admin/audit` |
| Config (Company Settings) | 🚧 | Partial: SAP config exists in DB |

---

## ROLE: IT Staff (role_code: `it_staff`)

IT Staff memiliki akses ke:
- Semua menu **Closing SAP** (sama dengan Estate Staff) ✅
- **Adjustment Log** ✅
- Diagnostic/debugging tools (ad-hoc, tidak ada menu tetap)

---

## Summary — Migration Progress

### ✅ Fully Migrated (Ready to Use)
1. **Dashboard** — all roles
2. **Transactions Entry** — Attendance, Workdone, OPH, CP1, CP2, FDN, Overtime, VRA, Platform Checking
3. **Closing SAP** — 9 screens (Attendance, OPH, Workdone, CP, FDN, Overtime, VRA, Coconut HC, Coconut FDN)
4. **SAP Closing Approval** — 4 screens (Attendance, Workdone, OPH, Overtime)
5. **Adjustment Log** — read-only audit trail
6. **Master Data** — Employee, Division, Block, Activity, Attendance, Estate, VRA, Bin, Destination, Device, OPH Card, FDN Card
7. **Grouping** — User Assignment, Gang Employee, Mapping AM-Division, Mapping FS-Gang

**Total:** ~40 screens migrated

### ❌ Not Yet Migrated (CI3 Only)
1. **Planning** — Workplan, Harvesting Plan, GI Plan (3 screens)
2. **Approval** (Asst Manager) — Workplan, Harvesting Plan, GI Plan, Unplanned Activity, OPH, Overtime (6 screens)
3. **Master Data** (remaining) — Work Center, Meas Point, Task, Material, UOM, Vendor, WBS, etc. (~15 screens)
4. **Master Data SAP** — SLOC, GL Account, Movement Type, Cost Center, etc. (7 screens)
5. **GI/GR Transactions** — GR/GI Form, GR/GI Closing (4 screens)
6. **Reporting** — All reports (~20 screens)
7. **Assignment** — General Worker, Harvester (2 screens)
8. **OPH Mill Grader** (1 screen)

**Total:** ~66 screens not migrated

### 🔴 Out of Scope
- (none — semua tabel sudah ada atau di-migrate)

---

## Priority Recommendation (Next Sprints)

**KOREKSI PENTING:** Coconut dan Durian **BUKAN out of scope** — tabel ada di Laravel DB!

**Sprint berikutnya (prioritas tinggi):**
1. **Planning** — Workplan, Harvesting Plan, GI Plan (3 screens) → dibutuhkan Estate Manager
2. **Approval** (Asst Manager) — 6 screens → workflow approval chain
3. **Reporting** — pilih 5-10 report paling penting dulu (daily usage)
4. **Master Data SAP** — SLOC, GL Account, Movement Type (untuk GI/GR)
5. **GI/GR Transactions** — 4 screens (warehouse operations)

**Sprint lanjutan (prioritas medium):**
6. Master Data sisanya (Work Center, Material, UOM, Vendor, WBS)
7. Assignment screens (General Worker, Harvester)
8. Reporting sisanya

---

## Notes
- Mobile API sudah complete (v1_1 BATCH 1-4) ✅
- SAP integration framework sudah ada (SapService) ✅
- Role-based access control sudah bekerja ✅
- **Semua tabel Coconut & Durian sudah ada** di Laravel DB
- Coconut: 9 tabel (t_coconut_oph, t_coconut_fdn, t_coconut_harvesting_plan, **t_checkpoint**, dll)
- Durian: 8 tabel master (m_durian_variety, m_durian_grading, m_durian_activity, dll)
- **t_checkpoint** baru di-migrate (2026-09-09) — untuk CP Coconut
