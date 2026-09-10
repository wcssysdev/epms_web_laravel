# Next Role Migration Priority

**Current Status**: Assistant Manager (Role 3) ✅ COMPLETE

---

## Roles Overview (CI3)

| Role ID | Role Name | Primary Function | Status |
|---------|-----------|------------------|--------|
| **1** | Estate Admin | Master data, config, full access | ✓ Partially migrated |
| **2** | Estate Manager | Planning, approval, SAP closing approval | 🔄 Planning done, need Approval |
| **3** | Assistant Manager | Approval (unplanned transactions) | ✅ **COMPLETE** |
| **4** | Estate Staff | Data entry, SAP closing, operational | ❌ Not started |
| **23** | GI/GR Staff | Warehouse (Goods Issue/Receipt) | ❌ Not started |
| **33** | Material Staff | Material management | ❌ Not started |

---

## Priority Analysis

### 🔴 HIGH PRIORITY

#### 1. **Estate Manager (Role 2)** - 50% Complete

**What's Done**:
- ✅ Planning screens (Workplan, Harvesting Plan, GI Plan)
- ✅ Some approval screens (from commits sebelumnya)

**What's Missing**:
- ❌ Approval untuk Planning yang disubmit Estate Manager lain
- ❌ SAP Closing Approval screens
- ❌ Dashboard/Monitoring

**Business Impact**: HIGH - Estate Manager approve planning dari team, approve SAP closing.

**Estimated Effort**: Medium (sudah ada base dari Planning)

---

#### 2. **Estate Staff (Role 4)** - 0% Complete

**Primary Functions**:
- ❌ Operational data entry (Attendance, Workdone, OPH, etc.)
- ❌ SAP Closing process (close transactions before SAP integration)
- ❌ VRA (Vehicle Registration)
- ❌ Platform Checking
- ❌ Overtime entry

**What Exists**:
- Beberapa transaction entry screens sudah ada (dari commit sebelumnya: Overtime, VRA, Platform)
- SAP Closing screens mungkin sudah ada

**Business Impact**: **CRITICAL** - Estate Staff adalah operational role yang paling banyak.

**Estimated Effort**: High (banyak screens, tapi mungkin sudah banyak yang dimigrate)

---

### 🟡 MEDIUM PRIORITY

#### 3. **GI/GR Staff (Role 23)** - Unknown

**Primary Functions**:
- Goods Receipt (GR) form entry
- Goods Issue (GI) form entry
- Warehouse material management

**Business Impact**: Medium - Important but specific to warehouse operations.

**Estimated Effort**: Medium

---

### 🟢 LOW PRIORITY

#### 4. **Material Staff (Role 33)** - Unknown

**Primary Functions**:
- Material master data management (limited scope)

**Business Impact**: Low - Limited scope, specific function.

**Estimated Effort**: Low

---

## Recommendation: Estate Staff (Role 4) FIRST

### Why Estate Staff?

1. **Most Critical Role**: 
   - Operational role dengan jumlah user terbanyak
   - Daily data entry untuk production

2. **May Already Be Partially Complete**:
   - Dari commits sebelumnya ada: Overtime entry, VRA entry, Platform Checking
   - SAP Closing screens mungkin sudah ada
   - Tinggal verify & test

3. **High Business Value**:
   - Tanpa Estate Staff, tidak ada data masuk ke sistem
   - Blocking untuk workflow approval (AM & EM approve data dari ES)

### Investigation Needed

Check apa yang sudah ada untuk Estate Staff:
1. Transaction entry screens (Attendance, Workdone, OPH entry, dll)
2. SAP Closing screens
3. VRA, Platform, Overtime entry
4. Test accessibility untuk role Estate Staff

---

## Alternative: Estate Manager (Role 2)

### Why Estate Manager?

1. **Already 50% Complete**:
   - Planning screens done
   - Tinggal approval + SAP closing approval

2. **Logical Flow**:
   - Estate Manager submit planning → Assistant Manager approve
   - Complete the approval workflow

3. **Manageable Scope**:
   - Focused on approval + SAP closing approval
   - Less screens than Estate Staff

### Investigation Needed

1. What approval screens Estate Manager needs (approve planning dari EM lain)
2. SAP Closing Approval status (may already exist)
3. Dashboard/monitoring requirements

---

## Action Plan

### Option A: Estate Staff (Recommended)

```
1. Investigate existing transaction entry screens
2. Test Estate Staff role accessibility
3. Identify gaps vs CI3
4. Migrate missing screens
5. Comprehensive testing
```

### Option B: Estate Manager

```
1. Check existing approval screens for EM
2. Verify SAP Closing Approval screens
3. Add missing approval workflows
4. Test end-to-end planning → approval flow
5. Add dashboard/monitoring if needed
```

---

## Decision Needed

**Question for User**: 
- Focus on **Estate Staff** (operational, critical, check existing screens)?
- Or **Estate Manager** (complete approval workflow, smaller scope)?

Both are high priority. Estate Staff potentially has more screens but some may already exist.
