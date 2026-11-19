# QMS Baseline Regression Evidence

FlinkISO v2 Expansion, Milestone M5, Section 4B baseline.

| | |
|---|---|
| Prepared by | Developer |
| Generated | 27 August 2026 07:56 UTC |
| Application | https://flinkiso.dctrd.us (CakePHP) |
| Schema | `flinkisodb` |
| Controllers | 44 |
| Tables | 49 |
| Reachability | http 200, 8180 bytes |

---

## 1. Phase 1 impact on this application

Phase 1 delivered changes to Rocket LMS only. No file in this application
was modified and no migration was run against this schema. The table below
compares current row counts against the counts recorded in the M1 audit of
20 August 2026, taken before any Phase 1 work began.


**No drift. All 11 tables counted in the M1 audit hold exactly the same counts today.**

### Tables M1 could not resolve

The M1 audit listed the following as absent from a query that returned only
tables holding rows. That wording cannot distinguish an empty table from one
that does not exist. Checked directly:

| Table | Exists | Rows |
|---|:---:|---:|
| `tbl_audit_checklist_0_v0s` | yes | 0 |
| `tbl_audit_findings_0_v0s` | yes | 0 |
| `tbl_audit_schedule_0_v0s` | yes | 0 |
| `tbl_calibration_0_v0s` | yes | 0 |
| `tbl_device_equipment_0_v0s` | yes | 0 |
| `tbl_mrm_0_v0s` | yes | 0 |


---

## 2. Section 4B capability inventory

Grades are derived from what is present and what holds records, not from
names. **(A)** working and reusable, **(B)** present but unproven or partial,
**(C)** present but unsuitable, **(D)** genuinely missing.

| # | Capability | Grade | Basis |
|---:|---|:---:|---|
| 1 | Document Control / controlled documents | **B** | All 12 tables exist; 5 hold records and 7 are empty. |
| 2 | Incidents / NC / Deviation and CAPA | **B** | All 1 tables exist. No record has ever been created in any of them. |
| 3 | Audit Management | **B** | All 4 tables exist; 1 hold records and 3 are empty. |
| 4 | Risk Register / Risk Matrix | **D** | No controller and no table implements this capability. |
| 5 | Workflow Engine and Notifications | **B** | All 5 tables exist; 3 hold records and 2 are empty. |
| 6 | KPI Engine / Dashboard | **B** | 1 of 2 tables exist; 1 absent (pie_chart_panels). |
| 7 | Training / Competency | **D** | The Training model declares $useTable = false, so this module has no persistence of any kind. Nothing is stored and nothing can be reused. |
| 8 | Assets / Calibration | **B** | All 2 tables exist. No record has ever been created in any of them. |
| 9 | HACCP / CCP monitoring | **D** | No controller and no table implements this capability. |
| 10 | GMP / Validation logs | **D** | No controller and no table implements this capability. |
| 11 | Forms / Drag and Drop Form Builder | **B** | All 5 tables exist; 1 hold records and 4 are empty. |

### Detail

**Document Control / controlled documents**

| Table | Rows | Last write |
|---|---:|---|
| `qc_documents` | 10 | 2026-07-08 17:36:12 |
| `qc_document_categories` | 5 | 2026-07-08 17:36:12 |
| `document_downloads` | 0 | 2026-07-08 17:14:28 |
| `files` | 0 | 2026-07-08 17:14:28 |
| `custom_files` | 0 | 2026-07-08 17:14:28 |
| `pdf_templates` | 0 | 2026-07-08 17:14:28 |
| `record_locks` | 0 | 2026-07-08 17:14:28 |
| `histories` | 92 | 2026-08-04 19:12:47 |
| `approvals` | 0 | 2026-07-08 17:14:28 |
| `approval_steps` | 6 | 2026-07-08 17:36:12 |
| `approval_processes` | 2 | 2026-07-08 17:36:12 |
| `approval_comments` | 0 | 2026-07-08 17:14:28 |

**Incidents / NC / Deviation and CAPA**

| Table | Rows | Last write |
|---|---:|---|
| `tbl_customer_complaints_0_v0s` | 0 | 2026-07-08 17:14:28 |

**Audit Management**

| Table | Rows | Last write |
|---|---:|---|
| `tbl_audit_catetory_v0s` | 2 | 2026-07-08 17:36:12 |
| `tbl_audit_checklist_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_audit_findings_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_audit_schedule_0_v0s` | 0 | 2026-07-08 17:14:28 |

**Risk Register / Risk Matrix**

No controller implements this capability in the current codebase.

**Workflow Engine and Notifications**

| Table | Rows | Last write |
|---|---:|---|
| `approval_processes` | 2 | 2026-07-08 17:36:12 |
| `approval_steps` | 6 | 2026-07-08 17:36:12 |
| `custom_triggers` | 0 | 2026-07-08 17:14:28 |
| `custom_table_tasks` | 0 | 2026-07-08 17:14:28 |
| `schedules` | 7 | 2026-07-08 17:36:12 |

**KPI Engine / Dashboard**

| Table | Rows | Last write |
|---|---:|---|
| `graph_panels` | 0 | 2026-07-08 17:14:28 |
| `pie_chart_panels` | **absent** | |

**Training / Competency**

| Table | Rows | Last write |
|---|---:|---|
| none | | The Training model declares $useTable = false, so this module has no persistence of any kind. Nothing is stored and nothing can be reused. |

**Assets / Calibration**

| Table | Rows | Last write |
|---|---:|---|
| `tbl_calibration_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_device_equipment_0_v0s` | 0 | 2026-07-08 17:14:28 |

**HACCP / CCP monitoring**

No controller implements this capability in the current codebase.

**GMP / Validation logs**

No controller implements this capability in the current codebase.

**Forms / Drag and Drop Form Builder**

| Table | Rows | Last write |
|---|---:|---|
| `custom_tables` | 11 | 2026-07-08 17:36:12 |
| `custom_table_tasks` | 0 | 2026-07-08 17:14:28 |
| `custom_files` | 0 | 2026-07-08 17:14:28 |
| `custom_triggers` | 0 | 2026-07-08 17:14:28 |
| `records` | 0 | 2026-07-08 17:14:28 |

---

## 3. Every table in the schema

| Table | Rows | Last write |
|---|---:|---|
| `approvals` | 0 | 2026-07-08 17:14:28 |
| `approval_comments` | 0 | 2026-07-08 17:14:28 |
| `approval_processes` | 2 | 2026-07-08 17:36:12 |
| `approval_steps` | 6 | 2026-07-08 17:36:12 |
| `branches` | 1 | 2026-07-08 17:36:12 |
| `chd_mrm0_child_1_v1s` | 0 | 2026-07-08 17:14:28 |
| `clauses` | 53 | 2026-07-08 17:36:12 |
| `companies` | 1 | 2026-07-08 17:36:12 |
| `customers` | 0 | 2026-07-08 17:14:28 |
| `custom_codes` | 0 | 2026-07-08 17:14:28 |
| `custom_files` | 0 | 2026-07-08 17:14:28 |
| `custom_tables` | 11 | 2026-07-08 17:36:12 |
| `custom_table_processes` | 0 | 2026-07-08 17:14:28 |
| `custom_table_tasks` | 0 | 2026-07-08 17:14:28 |
| `custom_triggers` | 0 | 2026-07-08 17:14:28 |
| `departments` | 1 | 2026-07-08 17:36:12 |
| `designations` | 1 | 2026-07-08 17:36:12 |
| `document_change_requests` | 0 | 2026-07-08 17:14:28 |
| `document_downloads` | 0 | 2026-07-08 17:14:28 |
| `employees` | 4 | 2026-07-23 14:12:52 |
| `employee_leaves` | 0 | 2026-07-08 17:14:28 |
| `files` | 0 | 2026-07-08 17:14:28 |
| `graph_panels` | 0 | 2026-07-08 17:14:28 |
| `histories` | 92 | 2026-08-04 19:12:47 |
| `invoices` | 0 | 2026-07-08 17:14:28 |
| `password_settings` | 1 | 2026-07-08 17:14:28 |
| `pdf_templates` | 0 | 2026-07-08 17:14:28 |
| `processes` | 0 | 2026-07-08 17:14:28 |
| `qc_documents` | 10 | 2026-07-08 17:36:12 |
| `qc_document_categories` | 5 | 2026-07-08 17:36:12 |
| `records` | 0 | 2026-07-08 17:14:28 |
| `record_locks` | 0 | 2026-07-08 17:14:28 |
| `schedules` | 7 | 2026-07-08 17:36:12 |
| `standards` | 1 | 2026-07-08 17:36:12 |
| `system_tables` | 0 | 2026-07-08 17:14:28 |
| `tbl_audit_catetory_v0s` | 2 | 2026-07-08 17:36:12 |
| `tbl_audit_checklist_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_audit_findings_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_audit_schedule_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_calibration_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_customer_complaints_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_customer_details_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_device_equipment_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_mrm_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `tbl_supplier_details_0_v0s` | 0 | 2026-07-08 17:14:28 |
| `usage_details` | 0 | 2026-07-08 17:14:28 |
| `users` | 9 | 2026-08-04 19:12:47 |
| `user_access_controls` | 0 | 2026-07-08 17:14:28 |
| `user_sessions` | 11 | 2026-08-04 19:13:04 |
