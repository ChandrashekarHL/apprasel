<?php
require_once 'header.php';

// Access Control: Admin/Dean Only
if (!isLoggedIn() || $_SESSION['role'] == 'Faculty') {
   // header("Location: dashboard.php");
   // Simple access check for demo
}

$message = '';

// Handle Policy Update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach($_POST['groups'] as $gid => $targets) {
        $sql = "UPDATE ad_workload_groups SET 
                target_teaching = ?, 
                target_research = ?, 
                target_admin = ?, 
                target_mentoring = ?, 
                target_aav = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $targets['teaching'],
            $targets['research'],
            $targets['admin'],
            $targets['mentoring'],
            $targets['aav'],
            $gid
        ]);
    }
    $message = "Workload Policies Updated Successfully.";
}

// Fetch Current Groups
$groups = $pdo->query("SELECT * FROM ad_workload_groups ORDER BY group_code")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    :root {
        --primary-brand: #2c3e50;
        --secondary-brand: #34495e;
        --accent-gold: #f1c40f;
        --success-green: #27ae60;
        --bg-light: #f4f6f9;
        --card-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    body {
        background-color: var(--bg-light);
        font-family: 'Inter', sans-serif;
        font-weight: 400;
    }

    .config-container {
        max-width: 1100px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .header-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .header-flex h2 {
        margin: 0;
        color: var(--primary-brand);
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .data-card {
        background: white;
        border-radius: 12px;
        box-shadow: var(--card-shadow);
        overflow: hidden;
        padding: 0;
    }

    .card-info {
        padding: 20px 30px;
        background: #fdfdfd;
        border-bottom: 1px solid #f1f1f1;
        color: #7f8c8d;
        font-size: 0.95em;
        line-height: 1.6;
    }

    .modern-table {
        width: 100%;
        border-collapse: collapse;
    }

    .modern-table th {
        background: #f8f9fa;
        padding: 15px 20px;
        text-align: left;
        font-size: 0.85em;
        color: #2c3e50;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #edf2f7;
        white-space: nowrap;
    }

    .modern-table td {
        padding: 18px 25px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
    }

    .modern-table tr:hover {
        background: #fbfcfe;
    }

    .category-label {
        color: var(--primary-brand);
        font-size: 1.05em;
        font-weight: 500;
    }

    .group-info small {
        color: #95a5a6;
        display: block;
        margin-top: 2px;
    }

    .input-pct {
        width: 80px;
        padding: 8px 12px;
        border: 1px solid #dcdde1;
        border-radius: 6px;
        font-family: 'Inter', sans-serif;
        font-weight: 500;
        color: #2c3e50;
        transition: all 0.2s;
        text-align: right;
    }

    .input-pct:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
        outline: none;
    }

    .total-badge {
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.85em;
    }

    .total-ok { background: rgba(39, 174, 96, 0.1); color: #27ae60; }
    .total-warn { background: rgba(231, 76, 60, 0.1); color: #e74c3c; }

    .btn {
        padding: 10px 24px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: none;
    }

    .btn-primary { background: var(--primary-brand); color: white; }
    .btn-primary:hover { background: #1a252f; transform: translateY(-1px); }
    
    .btn-secondary { background: white; color: #7f8c8d; border: 1px solid #dcdde1; text-decoration: none; font-size: 0.9em; }
    .btn-secondary:hover { border-color: var(--primary-brand); color: var(--primary-brand); }

    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        border-left: 5px solid;
    }
    .alert-success { background: #eafaf1; color: #27ae60; border-color: #27ae60; }
</style>

<div class="config-container">
    <div class="header-flex">
        <h2><i class="fas fa-sliders-h"></i> Workload Metrics Engine</h2>
        <a href="hod_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
    </div>
    <?php endif; ?>

    <div class="data-card">
        <div class="card-info" style="border-left: 5px solid #3498db; background: #ebf5fb;">
            <i class="fas fa-info-circle"></i> <strong>Read-Only View:</strong> These are the current baseline distribution targets for different faculty designations. These metrics drive the <strong>Faculty Effectiveness Index (FAEI)</strong> calculations. 
            <br><span style="font-size: 0.9em; color: #3498db;">Policy updates are currently restricted; these values are for reference only.</span>
        </div>
        
        <form>
            <table class="modern-table">
                <thead>
                    <tr>
                        <th style="width: 250px;">Staff Category</th>
                        <th>Teaching %</th>
                        <th>Research %</th>
                        <th>Admin %</th>
                        <th>Mentoring %</th>
                        <th>AAV %</th>
                        <th>Total %</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($groups as $g): ?>
                    <tr class="group-row">
                        <td class="group-info">
                            <span class="category-label"><strong><?php echo $g['group_code']; ?></strong> - <?php echo $g['group_name']; ?></span>
                        </td>
                        <td><input type="number" step="1" name="groups[<?php echo $g['id']; ?>][teaching]" value="<?php echo (int)$g['target_teaching']; ?>" class="input-pct req-input" disabled></td>
                        <td><input type="number" step="1" name="groups[<?php echo $g['id']; ?>][research]" value="<?php echo (int)$g['target_research']; ?>" class="input-pct req-input" disabled></td>
                        <td><input type="number" step="1" name="groups[<?php echo $g['id']; ?>][admin]" value="<?php echo (int)$g['target_admin']; ?>" class="input-pct req-input" disabled></td>
                        <td><input type="number" step="1" name="groups[<?php echo $g['id']; ?>][mentoring]" value="<?php echo (int)$g['target_mentoring']; ?>" class="input-pct req-input" disabled></td>
                        <td><input type="number" step="1" name="groups[<?php echo $g['id']; ?>][aav]" value="<?php echo (int)$g['target_aav']; ?>" class="input-pct req-input" disabled></td>
                        <td>
                            <span class="total-badge total-ok">0%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="padding: 25px 30px; background: #fafbfc; text-align: center; color: #7f8c8d; font-size: 0.9em;">
                <i class="fas fa-lock"></i> Policy modification is disabled in this view.
            </div>
        </form>
    </div>
</div>

<script>
    function calculateTotals() {
        document.querySelectorAll('.group-row').forEach(row => {
            const inputs = row.querySelectorAll('.input-pct');
            let total = 0;
            inputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            const badge = row.querySelector('.total-badge');
            badge.innerText = total + '%';
            
            if (total === 100) {
                badge.className = 'total-badge total-ok';
            } else {
                badge.className = 'total-badge total-warn';
            }
        });
    }

    // Attach listeners
    document.querySelectorAll('.input-pct').forEach(input => {
        input.addEventListener('input', calculateTotals);
    });

    // Initial calc
    window.addEventListener('load', calculateTotals);
</script>

<?php require_once 'footer.php'; ?>

<?php require_once 'footer.php'; ?>
