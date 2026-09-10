# Assistant Manager (Role 3) - Menu Migration Status

**Date**: September 1, 2026  
**Role**: Assistant Manager (role_id = 3 / role_code = 'asst_manager')

---

## CI3 Menu Structure untuk Assistant Manager

Berdasarkan `sidebar.php` CI3, menu untuk **Assistant Manager (role 3)** adalah:

### 1. ✅ Home
- `/home`
- **Status**: Already exists in Laravel (default)

### 2. ✅ Overview
- **Dashboard Harvesting** → `/dashboard/harvesting`
- **Status**: ✅ **MIGRATED** (commit sebelumnya)
- **Laravel Route**: `/dashboard/harvesting`
- **Controller**: `App\Http\Controllers\Dashboard\HarvestingController`

### 3. ✅ Grouping
- **Mandor - Employee** → `/grouping/user_assignment`
- **Status**: ✅ **MIGRATED** (commit sebelumnya)
- **Laravel Route**: `/grouping/user-assignment`
- **Controller**: `App\Http\Controllers\Grouping\UserAssignmentController`

### 4. ❌ Planning (NOT for Assistant Manager)
**Note**: Planning menu **HANYA untuk Estate Manager (role 2)**, BUKAN Assistant Manager!
- Role 3 (Assistant Manager) **TIDAK punya akses Planning**
- Yang punya akses Planning: **Role 2 (Estate Manager) ONLY**

### 5. ✅ Approval (Role 3 - Assistant Manager)
Sub-menu untuk Assistant Manager:

#### ✅ Unplanned Activity
- **CI3**: `/approval/unplanned_activity`
- **Status**: ✅ **MIGRATED** (commit hari ini)
- **Laravel**: `/approval/unplanned-activity`
- **Table**: `t_workdone` where `is_planned=0`

#### ✅ Unplanned HV (Palm) - OPH
- **CI3**: `/approval/oph`
- **Status**: ✅ **MIGRATED** (commit hari ini)
- **Laravel**: `/approval/oph`
- **Table**: `t_oph` where `is_planned=0`

#### ✅ Unplanned HV (Coconut)
- **CI3**: `/approval/harvesting_chit_coconut`
- **Status**: ✅ **MIGRATED** (commit hari ini)
- **Laravel**: `/approval/harvesting-plan?type=coconut` 
- **Note**: **Ini SALAH!** Harusnya pakai table **`t_coconut_harvesting_chit`**, BUKAN `t_coconut_harvesting_plan`!
- **Action**: ⚠️ **NEED FIX** - Buat controller terpisah untuk Coconut Chit

#### ✅ Work Overtime
- **CI3**: `/approval/overtime`
- **Status**: ✅ **MIGRATED** (commit hari ini)
- **Laravel**: `/approval/overtime`
- **Table**: `t_overtime`

---

## Summary Migration Status

### ✅ FULLY MIGRATED (3/4 menus)

| Menu Category | Sub-Menus | Status |
|---------------|-----------|--------|
| Home | 1 item | ✅ Default |
| Overview | Dashboard Harvesting | ✅ Migrated |
| Grouping | Mandor-Employee | ✅ Migrated |
| Approval | 4 items (Unplanned Activity, OPH, Overtime, + 1 WRONG) | ✅ 3/4 Migrated |

### ⚠️ NEED FIX (1 menu)

**Approval → Unplanned HV (Coconut)**:
- **Current Implementation**: Salah menggunakan `/approval/harvesting-plan?type=coconut`
- **Table Used**: `t_coconut_harvesting_plan` (WRONG - ini untuk planned harvesting!)
- **Should Use**: `t_coconut_harvesting_chit` (unplanned dari mobile)
- **Fix Required**: Buat `ApprovalHarvestingChitCoconutController` baru

---

## Detail Analysis: Coconut Harvesting - Plan vs Chit

### 1. Harvesting Plan Coconut (Estate Manager - PLANNED)
- **Controller CI3**: `Planning\Harvesting_plan_coconut.php`
- **Table**: `t_coconut_harvesting_plan`
- **Access**: Estate Manager (role 2)
- **Purpose**: Create/edit planned harvesting schedule
- **Approval**: Estate Manager submit → Assistant Manager approve
- **Status**: ✅ Already migrated correctly

### 2. Harvesting Chit Coconut (Assistant Manager - UNPLANNED)
- **Controller CI3**: `Approval\Harvesting_chit_coconut.php`
- **Table**: `t_coconut_harvesting_chit` (NOT `t_coconut_harvesting_plan`!)
- **Access**: Assistant Manager (role 3)
- **Purpose**: Approve unplanned harvesting dari mobile app
- **Pattern**: Similar to OPH (unplanned, bulk approval)
- **Status**: ❌ **NOT MIGRATED** - current implementation salah table

---

## Investigation: t_coconut_harvesting_chit

Perlu check:
1. Table structure `t_coconut_harvesting_chit`
2. Column names (kemungkinan mirip `t_oph`)
3. Approval pattern (kemungkinan `is_planned=0`, `is_approved=0`)
4. CI3 controller logic untuk reference

**Expected Structure**:
```sql
t_coconut_harvesting_chit:
  - id
  - division_code
  - block_code
  - employee_code / harvester_code
  - qty / bunches
  - is_planned (0 = unplanned)
  - is_approved (0 = pending, 1 = approved, -1 = rejected)
  - created_at
```

---

## What Was Migrated Today (Incorrect)

**Commit `d8cdede`** migrated:
- `ApprovalHarvestingPlanController` dengan parameter `type=coconut`
- Route: `/approval/harvesting-plan?type=coconut`
- Table: `t_coconut_harvesting_plan`

**Problem**: Ini untuk **PLANNED** harvesting (approval dari Estate Manager submit), BUKAN untuk **UNPLANNED** chit dari mobile!

---

## Action Items

### 🔴 HIGH PRIORITY - Fix Coconut Chit

1. **Investigate** `t_coconut_harvesting_chit` structure
2. **Create** `ApprovalHarvestingChitCoconutController`
3. **Create** view `approval/harvesting_chit_coconut/index.blade.php`
4. **Add** route `/approval/harvesting-chit-coconut`
5. **Update** sidebar menu untuk Assistant Manager
6. **Test** HTTP 200

### ✅ CORRECT - Keep Harvesting Plan Approval

**Harvesting Plan Coconut Approval** tetap pakai controller existing:
- Route: `/approval/harvesting-plan?type=coconut`
- Table: `t_coconut_harvesting_plan`
- Purpose: Approve **PLANNED** harvesting dari Estate Manager
- Access: **Assistant Manager** (untuk approve), **Estate Manager** (untuk submit)

**Note**: Estate Manager submit plan → Assistant Manager approve plan. Ini BERBEDA dengan unplanned chit dari mobile!

---

## Corrected Menu Structure for Assistant Manager

### Approval Menu (should have 5 items):

1. ✅ **Unplanned Activity** → `/approval/unplanned-activity` (t_workdone)
2. ✅ **Unplanned HV (Palm)** → `/approval/oph` (t_oph)
3. ⚠️ **Unplanned HV (Coconut)** → `/approval/harvesting-chit-coconut` (t_coconut_harvesting_chit) - **NEED CREATE**
4. ✅ **Work Overtime** → `/approval/overtime` (t_overtime)
5. *(Optional)* **Harvesting Plan Approval** - jika Assistant Manager juga approve planned harvesting

---

## Clarification: Estate Manager vs Assistant Manager

### Estate Manager (Role 2)

**Planning**:
- Create/edit Workplan
- Create/edit Harvesting Plan Palm
- Create/edit Harvesting Plan Coconut
- Create/edit GI Plan
- Submit for approval (set `is_approved=0`)

**Approval** (when substituted):
- Approve Workplan from other Estate Managers
- Approve Harvesting Plan from other Estate Managers
- Approve GI Plan from other Estate Managers

### Assistant Manager (Role 3)

**NO Planning** - Assistant Manager tidak buat plan!

**Approval ONLY**:
- Approve **PLANNED** transactions (dari Estate Manager submit):
  - Daily Work Plan
  - Daily Harvesting Plan (Palm)
  - Daily Harvesting Plan (Coconut)
  - GI Plan
- Approve **UNPLANNED** transactions (dari Mobile App):
  - Unplanned Activity (t_workdone)
  - Unplanned HV Palm (t_oph)
  - Unplanned HV Coconut (t_coconut_harvesting_chit) ← **NEED FIX**
  - Work Overtime (t_overtime)

---

## Conclusion

### ✅ What's Complete

**Assistant Manager menus yang sudah complete**:
- Home (default)
- Overview → Dashboard Harvesting ✅
- Grouping → Mandor-Employee ✅
- Approval:
  - Unplanned Activity ✅
  - Unplanned HV (Palm) - OPH ✅
  - Work Overtime ✅

**Total**: 6/7 menu items complete

### ⚠️ What's Missing

1. **Unplanned HV (Coconut)** - Harvesting Chit Coconut
   - Need to create new controller + view
   - Table: `t_coconut_harvesting_chit`
   - Pattern: Similar to OPH approval

### 📊 Progress

```
Assistant Manager Menu Migration: 6/7 (85.7%)
- Complete: 6 menus ✅
- Missing: 1 menu (Coconut Chit) ⚠️
```

---

## Next Steps

1. ✅ Confirm dengan user apakah Assistant Manager perlu approve **PLANNED** Harvesting Plan Coconut juga
2. ⚠️ Investigate & migrate **Unplanned HV (Coconut)** - Harvesting Chit
3. ✅ Test all Assistant Manager menus end-to-end
4. ✅ Update sidebar sesuai role access

**Recommended**: Tanyakan ke user apakah perlu migrate Coconut Chit atau skip dulu untuk fokus ke role lain.
