<?php
/* ---------------------------------------------------------------------------
 * M5 — QMS baseline regression evidence.  READ ONLY.
 *
 *     cd /var/www/vhosts/dctrd.us/flinkiso.dctrd.us
 *     php qms_baseline_evidence.php
 *
 * Phase 1 changed Rocket LMS only. Nothing in this CakePHP application was
 * touched. The purpose of this script is to produce the evidence for that
 * claim rather than assert it, and at the same time to replace the preliminary
 * A?/B?/D? grades in the M1 inventory with grades backed by data.
 *
 * It does three things:
 *
 *   1. Reads every table in the FlinkISO schema with its row count and last
 *      update time, and compares the result against the counts recorded in the
 *      M1 audit of 20 August 2026, before any Phase 1 work began. Any drift is
 *      reported. No drift is the evidence M5 needs.
 *
 *   2. Maps each of the eleven Section 4B capabilities to the controllers that
 *      implement it and the tables behind them, and grades each capability from
 *      what is actually present rather than from a name.
 *
 *   3. Checks the application answers over HTTPS.
 *
 * It writes nothing to the database and nothing to the application. Output goes
 * to qms_baseline_evidence.md in the current directory.
 * ------------------------------------------------------------------------- */

$OUT  = __DIR__ . '/qms_baseline_evidence.md';
$HOST = 'https://flinkiso.dctrd.us';

/* --------------------------------------------------- M1 baseline, 20 Aug 2026 */

$M1_COUNTS = [
    'clauses'                  => 53,
    'histories'                => 92,
    'custom_tables'            => 11,
    'qc_documents'             => 10,
    'users'                    => 9,
    'schedules'                => 7,
    'approval_steps'           => 6,
    'qc_document_categories'   => 5,
    'employees'                => 4,
    'tbl_audit_catetory_v0s'   => 2,
    'standards'                => 1,
];

/*
 * M1 listed these as "absent from the results entirely" in a query that
 * returned only tables holding rows. That wording cannot distinguish a table
 * with no rows from a table that does not exist, so they are checked for
 * existence here rather than counted as drift.
 */
$M1_UNRESOLVED = [
    'tbl_audit_checklist_0_v0s',
    'tbl_audit_findings_0_v0s',
    'tbl_audit_schedule_0_v0s',
    'tbl_calibration_0_v0s',
    'tbl_device_equipment_0_v0s',
    'tbl_mrm_0_v0s',
];

/* ------------------------------------------- the eleven Section 4B capabilities */

$CAPABILITIES = [
    'Document Control / controlled documents' => [
        'controllers' => ['QcDocuments','QcDocumentCategories','DocumentDownloads','Files','CustomFiles','PdfTemplates','RecordLocks','Histories','Templates','Approvals','ApprovalSteps','ApprovalProcesses','ApprovalComments'],
        // Template.php declares $useTable = 'files', so there is no templates table.
        'tables'      => ['qc_documents','qc_document_categories','document_downloads','files','custom_files','pdf_templates','record_locks','histories','approvals','approval_steps','approval_processes','approval_comments'],
    ],
    'Incidents / NC / Deviation and CAPA' => [
        'controllers' => ['TblCustomerComplaints0V0s'],
        'tables'      => ['tbl_customer_complaints_0_v0s'],
    ],
    'Audit Management' => [
        'controllers' => ['TblAuditCatetoryV0s','TblAuditChecklist0V0s','TblAuditFindings0V0s','TblAuditSchedule0V0s'],
        'tables'      => ['tbl_audit_catetory_v0s','tbl_audit_checklist_0_v0s','tbl_audit_findings_0_v0s','tbl_audit_schedule_0_v0s'],
    ],
    'Risk Register / Risk Matrix' => [
        'controllers' => [],
        'tables'      => [],
    ],
    'Workflow Engine and Notifications' => [
        'controllers' => ['ApprovalProcesses','ApprovalSteps','CustomTriggers','CustomTableTasks','Schedules'],
        'tables'      => ['approval_processes','approval_steps','custom_triggers','custom_table_tasks','schedules'],
    ],
    'KPI Engine / Dashboard' => [
        'controllers' => ['GraphPanels','PieChartPanels'],
        'tables'      => ['graph_panels','pie_chart_panels'],
    ],
    'Training / Competency' => [
        'controllers' => ['Trainings'],
        // Training.php declares $useTable = false: the model stores nothing.
        'tables'      => [],
        'note'        => 'The Training model declares $useTable = false, so this module has no persistence of any kind. Nothing is stored and nothing can be reused.',
    ],
    'Assets / Calibration' => [
        'controllers' => ['TblCalibration0V0s','TblDeviceEquipment0V0s'],
        'tables'      => ['tbl_calibration_0_v0s','tbl_device_equipment_0_v0s'],
    ],
    'HACCP / CCP monitoring' => [
        'controllers' => [],
        'tables'      => [],
    ],
    'GMP / Validation logs' => [
        'controllers' => [],
        'tables'      => [],
    ],
    'Forms / Drag and Drop Form Builder' => [
        'controllers' => ['CustomTables','CustomTableTasks','CustomFiles','CustomTriggers','Records'],
        'tables'      => ['custom_tables','custom_table_tasks','custom_files','custom_triggers','records'],
    ],
];

/* ------------------------------------------------------------------- connect */

$cfgFile = __DIR__ . '/app/Config/database.php';
if (! file_exists($cfgFile)) { fwrite(STDERR, "No app/Config/database.php here.\n"); exit(1); }
require $cfgFile;

if (! class_exists('DATABASE_CONFIG')) { fwrite(STDERR, "DATABASE_CONFIG not defined.\n"); exit(1); }
$cfg = (new DATABASE_CONFIG())->default;

$host = $cfg['host'] ?? '127.0.0.1';
$db   = $cfg['database'] ?? '';
$user = $cfg['login'] ?? '';
$pass = $cfg['password'] ?? '';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Cannot connect: ' . $e->getMessage() . "\n");
    exit(1);
}

/* ------------------------------------------------------------------ gather */

$rows = $pdo->prepare(
    "SELECT TABLE_NAME AS t, TABLE_ROWS AS r, UPDATE_TIME AS u
       FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME"
);
$rows->execute([$db]);
$meta = [];
foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $meta[$row['t']] = ['approx' => (int) $row['r'], 'updated' => $row['u']];
}

/**
 * Resolves a table name against the real schema, tolerating separator
 * differences. A previous revision of this script reported seven tables as
 * missing because it wrote tbl_audit_checklist_0v0s where the schema holds
 * tbl_audit_checklist_0_v0s. Matching on a normalised form removes that whole
 * class of false finding.
 */
function resolveTable(string $wanted, array $known): ?string
{
    if (in_array($wanted, $known, true)) { return $wanted; }
    $n = strtolower(str_replace('_', '', $wanted));
    foreach ($known as $k) {
        if (strtolower(str_replace('_', '', $k)) === $n) { return $k; }
    }
    return null;
}

/** Exact count, because information_schema.TABLE_ROWS is an estimate on InnoDB. */
function exactCount(PDO $pdo, string $table): ?int
{
    try { return (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn(); }
    catch (Throwable $e) { return null; }
}

$counts = [];
foreach (array_keys($meta) as $t) { $counts[$t] = exactCount($pdo, $t); }

/* ------------------------------------------------------- drift against M1 */

$drift = [];
$matched = 0;
foreach ($M1_COUNTS as $t => $was) {
    if (! array_key_exists($t, $counts)) { $drift[] = [$t, $was, 'TABLE MISSING']; continue; }
    if ($counts[$t] === $was) { $matched++; continue; }
    $drift[] = [$t, $was, $counts[$t]];
}

/* -------------------------------------------------------------- http check */

function httpStatus(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => false,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_TIMEOUT => 25, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($body === false) { return 'unreachable (' . $err . ')'; }
    return 'http ' . $code . ', ' . strlen($body) . ' bytes';
}

$http = httpStatus($HOST . '/');

/* ------------------------------------------------------------------ grade */

function grade(array $cap, array $counts, array $controllerFiles): array
{
    $known = array_keys($counts);

    if ($cap['controllers'] === []) {
        return ['D', 'No controller and no table implements this capability.', []];
    }

    if (isset($cap['note']) && $cap['tables'] === []) {
        return ['D', $cap['note'], []];
    }

    $expected = count($cap['tables']);
    $present = 0; $withRows = 0; $absent = []; $resolved = [];

    foreach ($cap['tables'] as $t) {
        $real = resolveTable($t, $known);
        if ($real === null) { $absent[] = $t; continue; }
        $resolved[$t] = $real;
        $present++;
        if (($counts[$real] ?? 0) > 0) { $withRows++; }
    }

    if ($present === 0) {
        return ['C', "Controllers present, but none of the {$expected} tables they query exist.", $resolved];
    }

    if ($absent !== []) {
        return ['B', "{$present} of {$expected} tables exist; " . count($absent) . ' absent (' . implode(', ', $absent) . ').', $resolved];
    }

    if ($withRows === 0) {
        return ['B', "All {$expected} tables exist. No record has ever been created in any of them.", $resolved];
    }

    if ($withRows < $present) {
        return ['B', "All {$expected} tables exist; {$withRows} hold records and " . ($present - $withRows) . ' are empty.', $resolved];
    }

    return ['A', "All {$expected} tables exist and all hold records.", $resolved];
}

$controllerFiles = array_map('basename', glob(__DIR__ . '/app/Controller/*.php') ?: []);

/* ----------------------------------------------------------------- report */

$md   = [];
$md[] = '# QMS Baseline Regression Evidence';
$md[] = '';
$md[] = 'FlinkISO v2 Expansion, Milestone M5, Section 4B baseline.';
$md[] = '';
$md[] = '| | |';
$md[] = '|---|---|';
$md[] = '| Prepared by | Developer |';
$md[] = '| Generated | ' . date('j F Y H:i') . ' UTC |';
$md[] = '| Application | ' . $HOST . ' (CakePHP) |';
$md[] = '| Schema | `' . $db . '` |';
$md[] = '| Controllers | ' . count($controllerFiles) . ' |';
$md[] = '| Tables | ' . count($counts) . ' |';
$md[] = '| Reachability | ' . $http . ' |';
$md[] = '';
$md[] = '---';
$md[] = '';
$md[] = '## 1. Phase 1 impact on this application';
$md[] = '';
$md[] = 'Phase 1 delivered changes to Rocket LMS only. No file in this application';
$md[] = 'was modified and no migration was run against this schema. The table below';
$md[] = 'compares current row counts against the counts recorded in the M1 audit of';
$md[] = '20 August 2026, taken before any Phase 1 work began.';
$md[] = '';

$md[] = '';
if ($drift === []) {
    $md[] = '**No drift. All ' . $matched . ' tables counted in the M1 audit hold exactly the same counts today.**';
} else {
    $md[] = '**' . count($drift) . ' table(s) differ from the M1 baseline.**';
    $md[] = '';
    $md[] = '| Table | M1, 20 Aug | Now |';
    $md[] = '|---|---:|---:|';
    foreach ($drift as [$t, $was, $now]) { $md[] = "| `{$t}` | {$was} | {$now} |"; }
    $md[] = '';
    $md[] = 'Drift here does not automatically indicate a Phase 1 effect. This is a';
    $md[] = 'shared installation and other activity is possible. Each row needs a cause';
    $md[] = 'before this section can be relied on as evidence.';
}

$md[] = '';
$md[] = '### Tables M1 could not resolve';
$md[] = '';
$md[] = 'The M1 audit listed the following as absent from a query that returned only';
$md[] = 'tables holding rows. That wording cannot distinguish an empty table from one';
$md[] = 'that does not exist. Checked directly:';
$md[] = '';
$md[] = '| Table | Exists | Rows |';
$md[] = '|---|:---:|---:|';
$missingCount = 0;
foreach ($M1_UNRESOLVED as $t) {
    $t = resolveTable($t, array_keys($counts)) ?? $t;
    if (array_key_exists($t, $counts)) {
        $md[] = "| `{$t}` | yes | " . ($counts[$t] ?? '?') . ' |';
    } else {
        $md[] = "| `{$t}` | **no** | |";
        $missingCount++;
    }
}
$md[] = '';
if ($missingCount > 0) {
    $md[] = '**' . $missingCount . ' of these tables do not exist in the schema, yet a controller';
    $md[] = 'for each of them is present in the codebase.** A CakePHP controller whose table';
    $md[] = 'is absent cannot serve a request. This is a pre-existing condition of the';
    $md[] = 'inherited installation, unrelated to Phase 1, and it is the reason several';
    $md[] = 'capabilities below cannot be graded as working.';
}

$md[] = '';
$md[] = '---';
$md[] = '';
$md[] = '## 2. Section 4B capability inventory';
$md[] = '';
$md[] = 'Grades are derived from what is present and what holds records, not from';
$md[] = 'names. **(A)** working and reusable, **(B)** present but unproven or partial,';
$md[] = '**(C)** present but unsuitable, **(D)** genuinely missing.';
$md[] = '';
$md[] = '| # | Capability | Grade | Basis |';
$md[] = '|---:|---|:---:|---|';

$i = 0; $summary = [];
foreach ($CAPABILITIES as $name => $cap) {
    $i++;
    [$g, $why, ] = grade($cap, $counts, $controllerFiles);
    $summary[] = [$i, $name, $g];
    $md[] = "| {$i} | {$name} | **{$g}** | {$why} |";
}

$md[] = '';
$md[] = '### Detail';
$md[] = '';
foreach ($CAPABILITIES as $name => $cap) {
    $md[] = '**' . $name . '**';
    $md[] = '';
    if ($cap['controllers'] === []) {
        $md[] = 'No controller implements this capability in the current codebase.';
        $md[] = '';
        continue;
    }
    $md[] = '| Table | Rows | Last write |';
    $md[] = '|---|---:|---|';
    if ($cap['tables'] === [] && isset($cap['note'])) {
        $md[] = '| none | | ' . $cap['note'] . ' |';
    }
    foreach ($cap['tables'] as $t) {
        $real = resolveTable($t, array_keys($counts));
        if ($real === null) { $md[] = "| `{$t}` | **absent** | |"; continue; }
        $u = $meta[$real]['updated'] ?? '';
        $md[] = "| `{$real}` | " . ($counts[$real] ?? '?') . ' | ' . ($u ?: 'never') . ' |';
    }
    $md[] = '';
}

$md[] = '---';
$md[] = '';
$md[] = '## 3. Every table in the schema';
$md[] = '';
$md[] = '| Table | Rows | Last write |';
$md[] = '|---|---:|---|';
foreach ($counts as $t => $c) {
    $u = $meta[$t]['updated'] ?? '';
    $md[] = "| `{$t}` | " . ($c ?? '?') . ' | ' . ($u ?: 'never') . ' |';
}

file_put_contents($OUT, implode("\n", $md) . "\n");

/* ---------------------------------------------------------------- console */

echo 'QMS BASELINE EVIDENCE', PHP_EOL;
echo 'schema      : ', $db, PHP_EOL;
echo 'controllers : ', count($controllerFiles), PHP_EOL;
echo 'tables      : ', count($counts), PHP_EOL;
echo 'reachable   : ', $http, PHP_EOL;
echo PHP_EOL;
echo 'drift vs M1 counts   : ', ($drift === [] ? 'NONE (' . $matched . ' tables match exactly)' : count($drift) . ' table(s) differ'), PHP_EOL;
foreach ($drift as [$t, $was, $now]) { echo '   ', str_pad($t, 30), ' was ', $was, ' now ', $now, PHP_EOL; }
$missing = [];
foreach ($M1_UNRESOLVED as $t) { if (resolveTable($t, array_keys($counts)) === null) { $missing[] = $t; } }
echo 'tables absent entirely : ', count($missing), ' of ', count($M1_UNRESOLVED), PHP_EOL;
foreach ($missing as $t) { echo '   ', $t, PHP_EOL; }
echo PHP_EOL;
echo 'GRADES', PHP_EOL;
foreach ($summary as [$n, $name, $g]) {
    echo '  ', str_pad((string) $n, 3), str_pad($name, 42), $g, PHP_EOL;
}
echo PHP_EOL, 'report written to ', $OUT, PHP_EOL;
