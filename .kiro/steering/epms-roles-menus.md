# EPMS Role–Menu Mapping (Referensi Utama)

## Prinsip Penting
- **Selalu verifikasi ke source CI3/CI4** sebelum implementasi. Jangan mengarang.
- Kontrak JSON API mobile: **byte-for-byte sama** dengan CI3 v1_1.
- ID transaksi: VARCHAR application-generated (bukan auto-increment) untuk t_oph, t_cp, t_fdn, t_workdone, t_vra, t_overtime, t_coconut_oph, t_coconut_fdn, tr_gi_header, tr_gr_header.

---

## Alur SAP Closing — Dua Role Berbeda

```
Estate Staff ──► [Closing SAP]        : Lock data → Send to SAP
                                         (menu: /closing/*)

Estate Manager ──► [SAP Closing Approval] : Approve / Reject data
                                            yang sudah di-lock staff
                                            (menu: /sap-closing-approval/*)
```

### Detail Alur
1. **Estate Staff** (role_code: `estate_staff`, `staff`, `pc`, `cs`)
   - Memproses transaksi harian (Attendance, OPH, Workdone, CP, FDN, Overtime, VRA, dll)
   - Menu **Closing SAP**: lock data (`integration_status=0`), submit ke SAP
   - Field: `closing_is_approved` diset oleh Estate Manager (bukan Estate Staff)

2. **Estate Manager** (role_code: `estate_manager`)
   - Menu **SAP Closing Approval**: review data yang di-lock staff
   - Approve → `closing_is_approved=1` → data siap dikirim ke SAP oleh staff
   - Reject → `closing_is_approved=-1` → staff perlu perbaiki
   - Tabel: `t_attendance`, `t_workdone`, `t_oph`, `t_overtime`, dll — kolom `closing_is_approved`

3. **IT Staff** (role_code: `it_staff`)
   - Akses ke Closing SAP (sama dengan Estate Staff untuk keperluan monitoring/troubleshoot)
   - Akses ke Adjustment Log

### Integration Status Values
| Nilai | Arti |
|-------|------|
| -1 | Draft — editable, belum di-lock |
| 0 | Locked / Queued — siap kirim SAP |
| 2 | Success — berhasil dikirim ke SAP |
| 3 | Lost Connection — koneksi gagal |
| 4 | Failed — SAP tolak (ada REMARK error) |
| 5 | Adjustment — dikembalikan untuk koreksi |

### Closing Is Approved Values
| Nilai | Arti |
|-------|------|
| 0 | Pending — belum di-approve EM |
| 1 | Approved — EM setuju, siap kirim SAP |
| -1 | Rejected — EM tolak, perlu koreksi |

---

## Role Mapping: CI3 Numerik → Laravel role_code

| CI3 user_role | Laravel role_code | Mobile role string | Keterangan |
|---|---|---|---|
| 1 | `admin` (estate admin) | — | Estate Admin / web |
| 2 | `estate_manager` | `estate_manager` | EM, approval |
| 3 | `asst_manager` | `assistant_manager` | Asst Manager |
| 4 | `estate_staff` | — | Estate Staff (kantor), web only |
| 5 | `checker_palm` | `harvest_clerk` | Kerani panen sawit, mobile |
| 6 | `ramp_dispatch_palm` | `transport_clerk` | Kerani transport sawit, mobile |
| 7 | `staff` | `field_staff` | **ORANG LAPANGAN** (bukan kantor), mobile |
| 8 | `it_staff` | — | IT Staff |
| 9 | `checker_coconut` | `harvest_clerk_coconut` | Kerani panen kelapa, mobile |
| 10 | `ramp_dispatch_coconut` | `transport_clerk_coconut` | Kerani transport kelapa, mobile |
| 11 | `mill_grader` | `mill_grader` | Mill grader, mobile |
| 23 | `warehouse_clerk` | `wh_clerk` | GR/GI, mobile |
| 33 | `store_clerk` | `store_clerk` | GR/GI, mobile |

> **PENTING**: `staff` = orang **lapangan** (field_staff mobile). `estate_staff` = orang **kantor** (bukan mobile field staff).

---

## Menu per Role (dari sidebar CI3)

### Estate Staff (role 4 → estate_staff + staff)
- Dashboard, Overview
- **Transactions (Entry)**: Attendance, Work Completion, General Worker Assignment, Harvester Assignment, OPH, OPH Mill Grader, CP1, CP2, FDN, Harvesting Chit Coconut, CP Coconut, Grading Coconut, FDN Coconut, Work Overtime, VRA, Platform Checking
- **Closing SAP**: Attendance, OPH, Workdone, CP, FDN, Overtime, VRA, Coconut HC, Coconut FDN
- Adjustment Log

### Estate Manager (role 2 → estate_manager)
- Dashboard, Overview
- **SAP Closing Approval**: Attendance, OPH, General Work (Workdone), Overtime
- Planning (workplan, harvesting plan)
- Report (semua)

### Assistant Manager (role 3 → asst_manager)
- Dashboard
- Approval (unplanned activity, OPH, overtime)
- Planning
- Report

### Admin (role 1 → admin, company_admin, super_admin)
- Master Data (lengkap)
- Grouping
- Semua transaksi (monitoring)

---

## Catatan Scope Coconut
- **IN SCOPE**: Harvesting Chit Coconut, CP Coconut, Grading Coconut, FDN Coconut
- **OUT OF SCOPE** (tabel tidak ada di DB Laravel): t_coconut_cp, t_coconut_grading
- CP Coconut di CI3 → `t_checkpoint` cp_type=2 → Laravel: `t_cp` cp_type=2

---

## SAP API
- URL, User ID, Password disimpan di `m_company_config` kolom `sap_api_url`, `sap_user_id`, `sap_password`
- Format: HTTP POST, Basic Auth, Content-Type: application/xml, body: JSON
- Body: `{"urn:ZEPMS_XXXXX_IN": {"IM_XXX": {"item": [...]}}}`
- Response: XML `<EX_EXPORT><item><UNIQUE_ID>...<REMARK>...`
- REMARK kosong = sukses (status→2), ada isi = gagal (status→4)
- Implementasi: `App\Services\SapService`
