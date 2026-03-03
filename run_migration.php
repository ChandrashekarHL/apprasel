<?php
/**
 * Run SQL migration to add columns to ad_academic_source
 * Access via browser: http://localhost/apprasel/run_migration.php
 * Delete this file after running!
 */
require_once 'db_config.php';

$sqls = [
    "ALTER TABLE ad_academic_source ADD COLUMN IF NOT EXISTS subject_code VARCHAR(30) NULL AFTER course_title",
    "ALTER TABLE ad_academic_source ADD COLUMN IF NOT EXISTS section VARCHAR(20) NULL AFTER subject_code",
    "ALTER TABLE ad_academic_source ADD COLUMN IF NOT EXISTS semester TINYINT UNSIGNED NULL AFTER section",
    "ALTER TABLE ad_academic_source ADD COLUMN IF NOT EXISTS term VARCHAR(10) NULL AFTER semester",
    "ALTER TABLE ad_academic_source ADD COLUMN IF NOT EXISTS is_cc TINYINT(1) NOT NULL DEFAULT 0 AFTER term",
    "ALTER TABLE ad_academic_source ADD COLUMN IF NOT EXISTS approved TINYINT(1) NOT NULL DEFAULT 1 AFTER is_cc",
    "ALTER TABLE ad_academic_source DROP INDEX IF EXISTS uq_faculty_subject_section_year",
    "ALTER TABLE ad_academic_source ADD UNIQUE KEY uq_faculty_subject_section_year (faculty_id, subject_code, section, academic_year)",
];

echo "<h2>Academic Source Migration</h2><pre>";

$errors = 0;
foreach ($sqls as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ OK: " . substr($sql, 0, 80) . "...\n";
    } catch (PDOException $e) {
        // "Duplicate key name" on ADD UNIQUE is non-fatal if already exists
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️  SKIP (already exists): " . substr($sql, 0, 80) . "...\n";
        } else {
            echo "❌ ERROR: " . $e->getMessage() . "\n   SQL: $sql\n";
            $errors++;
        }
    }
}

echo "\n" . ($errors === 0 ? "✅ Migration complete! Delete this file now." : "❌ $errors error(s). Check above.") . "\n";
echo "</pre>";

// Show current table structure
echo "<h3>Current ad_academic_source columns:</h3><pre>";
$cols = $pdo->query("SHOW COLUMNS FROM ad_academic_source")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $col) {
    echo sprintf("  %-30s %s\n", $col['Field'], $col['Type']);
}
echo "</pre>";
?>
