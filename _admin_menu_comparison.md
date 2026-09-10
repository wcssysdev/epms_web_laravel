# Admin (Role 1) - Menu Comparison CI3/CI4 vs Laravel

**Analysis Date**: September 1, 2026

---

## CI3/CI4 - Admin (Role 1) Menu Structure

### 1. ✅ **Overview** (roles 1,2,3,4)
- Dashboard Harvesting → `/dashboard/harvesting`
- **Laravel Status**: ❌ Not migrated

### 2. ✅ **Master Data** (role 1 ONLY)

**Company-Scoped Masters** (31 items):
1. Master Estate → `/masters/estate`
2. Master Division → `/masters/division`
3. Master Work Center → `/masters/work_center`
4. Master Work Type → `/masters/worktype`
5. Master Measurement Point → `/masters/meas_point`
6. Master Block → `/masters/block`
7. Master Task (TPH) → `/masters/task`
8. Master Employee → `/masters/employee`
9. Master Activity → `/masters/activity`
10. Master Attendance Type → `/masters/attendance`
11. Master OPH → `/masters/report_oph`
12. Master License Number (VRA) → `/masters/vra`
13. Master Ramp → `/masters/receiving_point`
14. Master BIN → `/masters/bin`
15. Master Delivery Destination → `/masters/destination`
16. Master Material → `/masters/material`
17. Master Coconut Material → `/masters/coconut_material` (if coconut)
18. Master UOM → `/masters/uom`
19. Master Device → `/masters/device`
20. Master Grading → `/masters/grading`
21. Master OPH Cards → `/masters/oph_card` (if palm)
22. Master FDN Cards → `/masters/fdn_card` (if palm)
23. Master Vendor → `/masters/vendor`
24. Master WBS → `/masters/wbs`
25. Master Harvest Method → `/masters/harvest_method`
26. Master Confirmation Text → `/masters/confirmation_text`
27. Master Sales Order → `/masters/sales_order`
28. QR Code Generator → `/masters/qrcode`
29. Master Durian Variety → `/masters/durian/variety` (if durian, 8 sub-items)
30. Master Coconut Activity Type → `/masters/coconut_activity_type` (if coconut)

**Laravel Status**: ✅ **MOST MIGRATED** (need verification)

### 3. ✅ **Grouping** (role 1,2,3)

**Role 1 (Admin) Gets ALL 4 items**:
1. Mandor - Employee → `/grouping/user_assignment`
2. Assistant Manager - Division → `/grouping/mapping_assistant_manager_division`
3. Gang - Employee → `/grouping/gang_employee`
4. Field Staff - Gang → `/grouping/mapping_field_staff_gang`

**Laravel Status**: ✅ **ALL 4 EXIST** (verified from screenshot)

### 4. ❌ **Planning** (role 3 ONLY)
- **Admin does NOT have Planning menu**

### 5. ❌ **Approval** (roles 2,3 ONLY or substituted)
- **Admin does NOT have Approval menu** (unless substituted)

### 6. ✅ **Master GI & GR** (roles 1,23,33)

**Admin (role 1) gets**:
1. Storage Location → `/masters/sloc`
2. Purchase Order List → `/masters/purchase_order`
3. Maintenance Order List → `/masters/maint_order`
4. GL Account [Cost Center] → `/masters/gl_account`
5. GL Account [Order] → `/masters/gla_order`
6. Master Movement Type → `/masters/movement_type`
7. Master Cost Center → `/masters/cost_center`

**Laravel Status**: ✅ **ALL MIGRATED** (visible in sidebar)

### 7. ❌ **Trans GI & GR** (roles 23,33 ONLY)
- **Admin does NOT have Trans GI & GR**

### 8. ❌ **SAP Closing Approval** (role 2 only or substituted role 3)
- **Admin does NOT have SAP Closing Approval** (Estate Manager only)

### 9. ✅ **Manager Substitution** (role 1 ONLY)
- `/approval/substitution`
- **Laravel Status**: ❓ Need check

### 10. ✅ **Master Data Substitution** (role 1 ONLY)
- `/approval/master_data_substitution`
- **Laravel Status**: ❓ Need check

### 11. ❌ **Transaction** (roles 2,3,4 ONLY)
- **Admin does NOT have Transaction menu**

### 12. ✅ **Estate Settings** (role 1 ONLY - from routes, not visible in sidebar excerpt)
- Estate configuration
- **Laravel Status**: ❓ Need check

### 13. ✅ **User Management** (role 1 + super admins)
- User CRUD
- **Laravel Status**: ✅ Likely exists (admin function)

---

## Summary: Admin (Role 1) Menu Status

### ✅ **COMPLETE / MIGRATED**

| Category | Items | Laravel Status |
|----------|-------|----------------|
| **Master Data** | 31 masters | ✅ Most/All migrated |
| **Grouping** | 4 items | ✅ ALL exist |
| **Master GI & GR** | 7 items | ✅ ALL exist |
| **User Management** | CRUD | ✅ Likely exists |

### ❓ **NEED VERIFICATION**

| Category | Items | Need Check |
|----------|-------|------------|
| **Substitution** | 2 screens | Check if exists |
| **Estate Settings** | Config | Check if exists |
| **Dashboard Harvesting** | 1 screen | ❌ Confirmed not migrated |

### ❌ **NOT FOR ADMIN**

Admin does NOT get:
- Planning (role 3 only)
- Approval (roles 2,3 only)
- Trans GI & GR (roles 23,33 only)
- Transaction (roles 2,3,4 only)
- SAP Closing Approval (role 2 only)

---

## Verification Checklist for Laravel

Run these tests for Admin (role 1 / company_admin):

### Master Data (Sample Check - 5 critical ones)
```
✓ /masters/estate
✓ /masters/division
✓ /masters/block
✓ /masters/employee
✓ /masters/activity
```

### Grouping (ALL 4)
```
✓ /grouping/mandor_employee
✓ /grouping/field_assistant_division
✓ /grouping/gang_employee
✓ /grouping/field_staff
```

### Master GI & GR (ALL 7)
```
? /masters/sloc
? /masters/purchase_order
? /masters/maint_order
? /masters/gl_account
? /masters/gla_order
? /masters/movement_type
? /masters/cost_center
```

### Substitution (2 screens)
```
? /approval/substitution
? /approval/master_data_substitution
```

### Estate Settings
```
? /admin/config
? /admin/estate-settings
```

### User Management
```
? /admin/users
```

---

## Recommendation

**Admin menu is mostly complete**, need to verify:

1. **Run test** untuk Master Data screens (sampel 5-10 masters)
2. **Check** Substitution screens exist
3. **Check** Estate Settings/Config screen
4. **Check** User Management CRUD
5. **Skip** Dashboard Harvesting (low priority, monitoring only)

If all verified, **Admin (Role 1) is basically COMPLETE** ✅
