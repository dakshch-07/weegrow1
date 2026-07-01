<?php
session_start();
require_once 'php/db.php';

// 1. Database connection & Auto-seeding
try {
    $db = DB::getInstance()->getConnection();
    
    // Seed default admin if table is empty
    $stmt = $db->query("SELECT COUNT(*) FROM webgrowth_admin");
    if ($stmt->fetchColumn() == 0) {
        $default_hash = password_hash('weegrow_admin_2026', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO webgrowth_admin (username, password_hash) VALUES (?, ?)");
        $stmt->execute(['admin', $default_hash]);
    }
} catch (Exception $e) {
    die("Database Connection Error: " . htmlspecialchars($e->getMessage()));
}

// 2. Authentication Logic
$error_msg = '';
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (!empty($username) && !empty($password)) {
        $stmt = $db->prepare("SELECT * FROM webgrowth_admin WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $user['username'];
            header("Location: admin.php");
            exit;
        } else {
            $error_msg = "Invalid username or password.";
        }
    } else {
        $error_msg = "Please enter both fields.";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    header("Location: admin.php");
    exit;
}

// 3. Export CSV Logic
if (isset($_GET['action']) && $_GET['action'] === 'export' && isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=weegrow_leads_' . date('Ymd_His') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Name', 'Email', 'Phone', 'Business Type', 'Package', 'Message', 'Hashed IP', 'Submitted At']);
    
    $stmt = $db->query("SELECT id, name, email, phone, business_type, package, message, ip_address, submitted_at FROM webgrowth_leads ORDER BY id DESC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// 4. Fetch metrics and leads if logged in
$leads = [];
$stats = [
    'total' => 0,
    'starter' => 0,
    'growth' => 0,
    'scale' => 0,
    'premium' => 0,
    'other' => 0
];

if ($is_logged_in) {
    // Search & Filter options
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filter_biz = isset($_GET['biz_type']) ? trim($_GET['biz_type']) : '';
    $filter_pkg = isset($_GET['package']) ? trim($_GET['package']) : '';

    // Build lead query
    $query = "SELECT * FROM webgrowth_leads WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR message LIKE ?)";
        $search_param = "%$search%";
        array_push($params, $search_param, $search_param, $search_param, $search_param);
    }

    if ($filter_biz !== '') {
        $query .= " AND business_type = ?";
        $params[] = $filter_biz;
    }

    if ($filter_pkg !== '') {
        $query .= " AND package = ?";
        $params[] = $filter_pkg;
    }

    $query .= " ORDER BY id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $leads = $stmt->fetchAll();

    // Calculate metrics
    $total_stmt = $db->query("SELECT COUNT(*) FROM webgrowth_leads");
    $stats['total'] = $total_stmt->fetchColumn();

    $pkg_stmt = $db->query("SELECT package, COUNT(*) as count FROM webgrowth_leads GROUP BY package");
    while ($p_row = $pkg_stmt->fetch()) {
        $pkg_name = strtolower($p_row['package']);
        if (strpos($pkg_name, 'starter') !== false) {
            $stats['starter'] += $p_row['count'];
        } elseif (strpos($pkg_name, 'growth') !== false) {
            $stats['growth'] += $p_row['count'];
        } elseif (strpos($pkg_name, 'scale') !== false) {
            $stats['scale'] += $p_row['count'];
        } elseif (strpos($pkg_name, 'premium') !== false) {
            $stats['premium'] += $p_row['count'];
        } else {
            $stats['other'] += $p_row['count'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeeGROW Founders Portal | Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #080b11;
            --surface-color: #0f1524;
            --surface-border: rgba(255, 255, 255, 0.08);
            --accent-primary: #7c3aed;
            --accent-secondary: #ec4899;
            --accent-gradient: linear-gradient(135deg, #7c3aed 0%, #ec4899 100%);
            --text-main: #f3f4f6;
            --text-secondary: #9ca3af;
            --text-dim: #6b7280;
            --success: #10b981;
            --error: #f43f5e;
            --radius-lg: 16px;
            --radius-md: 10px;
            --shadow-primary: 0 0 20px rgba(124, 58, 237, 0.2);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Ambient Glow Blobs */
        .ambient-glow {
            position: fixed;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(124,58,237,0.15) 0%, rgba(0,0,0,0) 70%);
            z-index: -1;
            filter: blur(40px);
            pointer-events: none;
        }
        .glow-top-right {
            top: -100px;
            right: -100px;
        }
        .glow-bottom-left {
            bottom: -150px;
            left: -150px;
        }

        /* Glassmorphism Header */
        header {
            background: rgba(15, 21, 36, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--surface-border);
            padding: 1.25rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 1.4rem;
            letter-spacing: -0.5px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-dot {
            width: 10px;
            height: 10px;
            background: var(--accent-gradient);
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 10px rgba(236, 72, 153, 0.6);
        }

        .user-nav {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-info {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .user-info strong {
            color: var(--text-main);
        }

        .btn {
            background: var(--surface-color);
            border: 1px solid var(--surface-border);
            color: var(--text-main);
            padding: 0.6rem 1.2rem;
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn:hover {
            border-color: var(--accent-primary);
            box-shadow: 0 0 12px rgba(124, 58, 237, 0.2);
            transform: translateY(-1px);
        }

        .btn-primary {
            background: var(--accent-gradient);
            border: none;
            color: #fff;
        }

        .btn-primary:hover {
            box-shadow: 0 0 15px rgba(236, 72, 153, 0.4);
            filter: brightness(1.1);
        }

        /* Container Layout */
        .container {
            max-width: 1400px;
            width: 100%;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex-grow: 1;
        }

        /* LOGIN CONTAINER */
        .login-wrapper {
            margin: auto;
            max-width: 440px;
            width: 100%;
            background: rgba(15, 21, 36, 0.65);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg);
            padding: 2.5rem;
            box-shadow: var(--shadow-primary);
            text-align: center;
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .login-wrapper h2 {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .login-wrapper p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 2rem;
        }

        .input-group {
            margin-bottom: 1.25rem;
            text-align: left;
        }

        .input-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            background: rgba(8, 11, 17, 0.8);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-md);
            padding: 0.8rem 1rem;
            color: var(--text-main);
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 10px rgba(124, 58, 237, 0.25);
        }

        .btn-block {
            width: 100%;
            justify-content: center;
            padding: 0.9rem;
            margin-top: 1rem;
            font-size: 1rem;
        }

        .error-alert {
            background: rgba(244, 63, 94, 0.1);
            border: 1px solid var(--error);
            color: var(--error);
            border-radius: var(--radius-md);
            padding: 0.8rem;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
        }

        /* METRICS BLOCK */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .metric-card {
            background: rgba(15, 21, 36, 0.6);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
        }

        .metric-card:hover {
            transform: translateY(-2px);
            border-color: rgba(124, 58, 237, 0.3);
        }

        .metric-card .label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .metric-card .value {
            font-size: 2rem;
            font-weight: 800;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* FILTER SYSTEM */
        .filters-card {
            background: rgba(15, 21, 36, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filters-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }

        .filter-item {
            flex: 1 1 200px;
        }

        .filter-item label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 0.4rem;
        }

        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        /* LEADS LIST */
        .leads-card {
            background: rgba(15, 21, 36, 0.6);
            backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--surface-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
        }

        .table-responsive {
            overflow-x: auto;
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: rgba(8, 11, 17, 0.5);
            padding: 1rem 1.5rem;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--surface-border);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--surface-border);
            font-size: 0.92rem;
            vertical-align: top;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: rgba(255, 255, 255, 0.015);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .badge-biz {
            background: rgba(124, 58, 237, 0.15);
            color: #a78bfa;
            border: 1px solid rgba(124, 58, 237, 0.3);
        }

        .badge-pkg {
            background: rgba(236, 72, 153, 0.15);
            color: #f472b6;
            border: 1px solid rgba(236, 72, 153, 0.3);
        }

        .lead-message {
            max-width: 350px;
            white-space: pre-wrap;
            color: var(--text-secondary);
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .lead-contact {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .lead-contact a {
            color: var(--text-main);
            text-decoration: none;
            transition: var(--transition);
        }

        .lead-contact a:hover {
            color: var(--accent-primary);
        }

        .lead-date {
            color: var(--text-dim);
            font-size: 0.8rem;
        }

        .empty-state {
            padding: 4rem 2rem;
            text-align: center;
            color: var(--text-secondary);
        }

        .empty-state svg {
            width: 60px;
            height: 60px;
            margin-bottom: 1rem;
            color: var(--text-dim);
        }

        /* Footer */
        footer {
            margin-top: auto;
            border-top: 1px solid var(--surface-border);
            padding: 1.5rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-dim);
        }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Responsive Clamps */
        @media (max-width: 768px) {
            header {
                flex-direction: column;
                gap: 1rem;
                padding: 1rem;
            }
            .user-nav {
                width: 100%;
                justify-content: space-between;
            }
            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-buttons {
                justify-content: flex-end;
                margin-top: 0.5rem;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="ambient-glow glow-top-right"></div>
    <div class="ambient-glow glow-bottom-left"></div>

    <?php if ($is_logged_in): ?>
        <header>
            <div class="logo">
                <span class="logo-dot"></span> WeeGROW Founders
            </div>
            <div class="user-nav">
                <div class="user-info">Logged in as <strong><?php echo htmlspecialchars($_SESSION['admin_user']); ?></strong></div>
                <a href="admin.php?action=logout" class="btn">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Logout
                </a>
            </div>
        </header>

        <main class="container">
            <!-- Metrics Grid -->
            <section class="metrics-grid">
                <div class="metric-card">
                    <span class="label">Total Leads Received</span>
                    <span class="value"><?php echo $stats['total']; ?></span>
                </div>
                <div class="metric-card">
                    <span class="label">Starter Leads</span>
                    <span class="value"><?php echo $stats['starter']; ?></span>
                </div>
                <div class="metric-card">
                    <span class="label">Growth/Scale Leads</span>
                    <span class="value"><?php echo ($stats['growth'] + $stats['scale']); ?></span>
                </div>
                <div class="metric-card">
                    <span class="label">Premium Leads</span>
                    <span class="value"><?php echo $stats['premium']; ?></span>
                </div>
            </section>

            <!-- Filters Area -->
            <section class="filters-card">
                <form method="GET" action="admin.php" class="filters-form">
                    <div class="filter-item">
                        <label for="search">Keyword Search</label>
                        <input type="text" id="search" name="search" class="form-control" placeholder="Name, email, message..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    </div>
                    <div class="filter-item">
                        <label for="biz_type">Business Type</label>
                        <select id="biz_type" name="biz_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="Restaurant" <?php echo (isset($_GET['biz_type']) && $_GET['biz_type'] === 'Restaurant') ? 'selected' : ''; ?>>Restaurant</option>
                            <option value="Salon & Spa" <?php echo (isset($_GET['biz_type']) && $_GET['biz_type'] === 'Salon & Spa') ? 'selected' : ''; ?>>Salon & Spa</option>
                            <option value="Retail Shop" <?php echo (isset($_GET['biz_type']) && $_GET['biz_type'] === 'Retail Shop') ? 'selected' : ''; ?>>Retail Shop</option>
                            <option value="Gym & Fitness" <?php echo (isset($_GET['biz_type']) && $_GET['biz_type'] === 'Gym & Fitness') ? 'selected' : ''; ?>>Gym & Fitness</option>
                            <option value="Clinic" <?php echo (isset($_GET['biz_type']) && $_GET['biz_type'] === 'Clinic') ? 'selected' : ''; ?>>Clinic</option>
                            <option value="Home Services" <?php echo (isset($_GET['biz_type']) && $_GET['biz_type'] === 'Home Services') ? 'selected' : ''; ?>>Home Services</option>
                            <option value="Other" <?php echo (isset($_GET['biz_type']) && $_GET['biz_type'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="filter-item">
                        <label for="package">Target Package</label>
                        <select id="package" name="package" class="form-control">
                            <option value="">All Packages</option>
                            <option value="Starter ₹2,999" <?php echo (isset($_GET['package']) && $_GET['package'] === 'Starter ₹2,999') ? 'selected' : ''; ?>>Starter (₹2,999)</option>
                            <option value="Growth ₹7,999" <?php echo (isset($_GET['package']) && $_GET['package'] === 'Growth ₹7,999') ? 'selected' : ''; ?>>Growth (₹7,999)</option>
                            <option value="Scale ₹14,999" <?php echo (isset($_GET['package']) && $_GET['package'] === 'Scale ₹14,999') ? 'selected' : ''; ?>>Scale (₹14,999)</option>
                            <option value="Premium ₹24,999" <?php echo (isset($_GET['package']) && $_GET['package'] === 'Premium ₹24,999') ? 'selected' : ''; ?>>Premium (₹24,999)</option>
                            <option value="Custom" <?php echo (isset($_GET['package']) && $_GET['package'] === 'Custom') ? 'selected' : ''; ?>>Custom</option>
                            <option value="Not sure" <?php echo (isset($_GET['package']) && $_GET['package'] === 'Not sure') ? 'selected' : ''; ?>>Not Sure</option>
                        </select>
                    </div>
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="admin.php" class="btn">Clear</a>
                    </div>
                </form>
            </section>

            <!-- Leads List Card -->
            <section class="leads-card">
                <div class="card-header">
                    <span class="card-title">Lead Submissions (<?php echo count($leads); ?> showing)</span>
                    <a href="admin.php?action=export" class="btn btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export to CSV
                    </a>
                </div>
                <div class="table-responsive">
                    <?php if (count($leads) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client / Contacts</th>
                                    <th>Context</th>
                                    <th>Message Details</th>
                                    <th>Hashed IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leads as $lead): ?>
                                    <tr>
                                        <td><strong>#<?php echo $lead['id']; ?></strong></td>
                                        <td>
                                            <div class="lead-contact">
                                                <strong><?php echo htmlspecialchars($lead['name']); ?></strong>
                                                <a href="mailto:<?php echo htmlspecialchars($lead['email']); ?>"><?php echo htmlspecialchars($lead['email']); ?></a>
                                                <?php if (!empty($lead['phone'])): ?>
                                                    <a href="tel:<?php echo htmlspecialchars($lead['phone']); ?>"><?php echo htmlspecialchars($lead['phone']); ?></a>
                                                <?php endif; ?>
                                                <span class="lead-date"><?php echo date('d M Y, h:i A', strtotime($lead['submitted_at'])); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="display: flex; flex-direction: column; gap: 6px;">
                                                <?php if (!empty($lead['business_type'])): ?>
                                                    <div><span class="badge badge-biz"><?php echo htmlspecialchars($lead['business_type']); ?></span></div>
                                                <?php endif; ?>
                                                <?php if (!empty($lead['package'])): ?>
                                                    <div><span class="badge badge-pkg"><?php echo htmlspecialchars($lead['package']); ?></span></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="lead-message"><?php echo htmlspecialchars($lead['message']); ?></div>
                                        </td>
                                        <td style="font-family: monospace; font-size: 0.75rem; color: var(--text-dim);">
                                            <?php echo htmlspecialchars(substr($lead['ip_address'], 0, 16)) . '...'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                            <p>No leads found matching your criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    <?php else: ?>
        <!-- Login Form -->
        <main style="display: flex; align-items: center; justify-content: center; min-height: 100vh; width: 100%; padding: 1.5rem;">
            <div class="login-wrapper">
                <div class="logo" style="justify-content: center; margin-bottom: 0.75rem;">
                    <span class="logo-dot"></span> WeeGROW Founders
                </div>
                <p>Welcome back! Sign in to manage your growth pipeline.</p>

                <?php if ($error_msg): ?>
                    <div class="error-alert">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <?php echo htmlspecialchars($error_msg); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="input-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Enter username" required autocomplete="username">
                    </div>

                    <div class="input-group" style="margin-bottom: 2rem;">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                </form>
            </div>
        </main>
    <?php endif; ?>

    <footer>
        &copy; <?php echo date('Y'); ?> WeeGROW Agency India. Secured Administrative Terminal.
    </footer>
</body>
</html>
