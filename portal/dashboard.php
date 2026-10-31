<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/permissions.php';

// Allow any authenticated system role
Auth::requireRole(SYSTEM_ROLES);
$user = Auth::user();
$userRole = (string)$user['role'];

// Fetch counts for widgets
$userCount = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$catCount = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$distCount = (int)$pdo->query('SELECT COUNT(*) FROM districts')->fetchColumn();
$orderCount = (int)$pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$rideCount = (int)$pdo->query('SELECT COUNT(*) FROM rides')->fetchColumn();
$bookingCount = (int)$pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();

// Fetch recent audits if allowed
$audits = [];
if (hasPermission($userRole, 'view_audits', $pdo)) {
    $auditStmt = $pdo->prepare('SELECT action, entity_type, ip_address, details, created_at FROM audit_logs ORDER BY id DESC LIMIT 5');
    $auditStmt->execute();
    $audits = $auditStmt->fetchAll();
}

require_once __DIR__ . '/../templates/header.php';
?>

<div class="container py-4">
    <!-- User Welcome Banner -->
    <div class="card border-0 shadow-sm p-4 mb-4 bg-white rounded-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Welcome, <?= e($user['name']) ?>!</h3>
                <div class="text-muted small">ID: #<?= (int)$user['id'] ?> &bull; <?= e($user['email']) ?></div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary px-3 py-2 fs-6 text-uppercase">
                    <i class="bi bi-person-badge me-1"></i> <?= e($userRole) ?>
                </span>
                <?php if ($userRole === 'super_admin' || $userRole === 'admin'): ?>
                    <a href="<?= APP_URL ?>/portal/admin/permissions.php" class="btn btn-outline-dark fw-bold btn-sm">
                        <i class="bi bi-sliders me-1"></i> Configure Staff Permissions
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Stats Cards (Filtered by Allowed Role Permissions) -->
    <div class="row g-3 mb-4">
        <?php if (hasPermission($userRole, 'view_users', $pdo)): ?>
            <div class="col-md-4 col-lg-3">
                <div class="card border-0 shadow-sm p-3 bg-white rounded-3 h-100">
                    <div class="text-muted small fw-semibold">REGISTERED USERS</div>
                    <div class="fs-3 fw-bold text-dark"><?= $userCount ?></div>
                    <small class="text-primary"><a href="<?= APP_URL ?>/portal/admin/users.php" class="text-decoration-none">Manage &rarr;</a></small>
                </div>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($userRole, 'view_orders', $pdo)): ?>
            <div class="col-md-4 col-lg-3">
                <div class="card border-0 shadow-sm p-3 bg-white rounded-3 h-100">
                    <div class="text-muted small fw-semibold">MART ORDERS</div>
                    <div class="fs-3 fw-bold text-danger"><?= $orderCount ?></div>
                    <small class="text-danger"><a href="<?= APP_URL ?>/portal/admin/crm_ledger.php?tab=orders" class="text-decoration-none text-danger">Follow-up &rarr;</a></small>
                </div>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($userRole, 'view_rides', $pdo)): ?>
            <div class="col-md-4 col-lg-3">
                <div class="card border-0 shadow-sm p-3 bg-white rounded-3 h-100">
                    <div class="text-muted small fw-semibold">RAPIDO RIDES</div>
                    <div class="fs-3 fw-bold text-warning text-dark"><?= $rideCount ?></div>
                    <small><a href="<?= APP_URL ?>/portal/admin/crm_ledger.php?tab=rides" class="text-decoration-none text-warning text-dark">Radar &rarr;</a></small>
                </div>
            </div>
        <?php endif; ?>

        <?php if (hasPermission($userRole, 'view_bookings', $pdo)): ?>
            <div class="col-md-4 col-lg-3">
                <div class="card border-0 shadow-sm p-3 bg-white rounded-3 h-100">
                    <div class="text-muted small fw-semibold">DOCTOR BOOKINGS</div>
                    <div class="fs-3 fw-bold text-success"><?= $bookingCount ?></div>
                    <small class="text-success"><a href="<?= APP_URL ?>/portal/admin/crm_ledger.php?tab=bookings" class="text-decoration-none text-success">View Queue &rarr;</a></small>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Operations & Management Action Hub -->
    <div class="row g-3 mb-4">
        <!-- CRM & Lenden Console Card -->
        <?php if (hasPermission($userRole, 'view_lenden', $pdo) || hasPermission($userRole, 'add_followups', $pdo)): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm p-4 bg-white rounded-4 border-start border-danger border-4 h-100">
                    <h5 class="fw-bold mb-1 text-danger"><i class="bi bi-journal-check me-2"></i> Orders &amp; Lenden Follow-Up CRM</h5>
                    <p class="text-muted small mb-3">Live ledger of customer orders, trips, doctor bookings &amp; payment gateway settlements.</p>
                    <a href="<?= APP_URL ?>/portal/admin/crm_ledger.php" class="btn btn-danger fw-bold btn-sm align-self-start">Open Follow-Up CRM &rarr;</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Customer & Staff User Manager -->
        <?php if (hasPermission($userRole, 'view_users', $pdo)): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm p-4 bg-white rounded-4 border-start border-primary border-4 h-100">
                    <h5 class="fw-bold mb-1 text-primary"><i class="bi bi-person-gear me-2"></i> User &amp; Customer Management</h5>
                    <p class="text-muted small mb-3">Search customers, edit accounts, suspend users, or update contact numbers.</p>
                    <a href="<?= APP_URL ?>/portal/admin/users.php" class="btn btn-primary fw-bold btn-sm align-self-start">Manage Users &rarr;</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Vendor & KYC Approvals -->
        <?php if (hasPermission($userRole, 'view_vendors', $pdo)): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm p-4 bg-white rounded-4 border-start border-success border-4 h-100">
                    <h5 class="fw-bold mb-1 text-success"><i class="bi bi-building-check me-2"></i> Vendor &amp; Partner Management</h5>
                    <p class="text-muted small mb-3">Approve partner registrations, verify trade documents, and edit business listings.</p>
                    <a href="<?= APP_URL ?>/portal/admin/vendors.php" class="btn btn-success fw-bold btn-sm align-self-start">Manage Vendors &rarr;</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Lead Dispatch Control Room -->
        <?php if (hasPermission($userRole, 'view_leads', $pdo)): ?>
            <div class="col-md-6">
                <div class="card border-0 shadow-sm p-4 bg-white rounded-4 border-start border-dark border-4 h-100">
                    <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-inbox-fill me-2 text-primary"></i> Lead Dispatch Control Room</h5>
                    <p class="text-muted small mb-3">Inspect incoming customer requirements and assign to verified local partners.</p>
                    <a href="<?= APP_URL ?>/portal/admin/leads.php" class="btn btn-dark fw-bold btn-sm align-self-start">Dispatch Leads &rarr;</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Super Admin Master Configurations -->

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100 border-start border-warning border-4">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-badge-ad-fill text-warning me-2"></i> Advertising &amp; Banners</h6>
                <p class="text-muted small mb-3">Place local sponsor client banners or Google AdSense ad units.</p>
                <a href="https://searchkaro.midexa.in/portal/admin/advertisements.php" class="btn btn-outline-warning text-dark fw-bold btn-sm">Manage Ads &rarr;</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100 border-start border-primary border-4">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-palette-fill text-primary me-2"></i> App Visual Builder</h6>
                <p class="text-muted small mb-3">Live customize Super-App colors, banner sliders &amp; feature toggles.</p>
                <a href="https://searchkaro.midexa.in/portal/admin/app_builder.php" class="btn btn-outline-primary fw-bold btn-sm">Build App Live &rarr;</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100 border-start border-success border-4">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-cash-coin text-success me-2"></i> Customer Extra Fees</h6>
                <p class="text-muted small mb-3">Configure platform convenience charges added to customer bills.</p>
                <a href="https://searchkaro.midexa.in/portal/admin/fees.php" class="btn btn-outline-success fw-bold btn-sm">Manage Fees &rarr;</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100 border-start border-primary border-4">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-robot text-primary me-2"></i> B2B API Automation</h6>
                <p class="text-muted small mb-3">PaySprint, BBPS Electricity &amp; RedBus automated APIs.</p>
                <a href="https://searchkaro.midexa.in/portal/admin/api_integrations.php" class="btn btn-outline-primary fw-bold btn-sm">B2B API Hub &rarr;</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100 border-start border-success border-4">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-rocket-takeoff-fill text-primary me-2"></i> Go-Live Center</h6>
                <p class="text-muted small mb-3">System audit, PWA app health &amp; readiness report.</p>
                <a href="https://searchkaro.midexa.in/portal/admin/launch.php" class="btn btn-outline-success fw-bold btn-sm">Launch Center &rarr;</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-palette-fill text-primary me-2"></i> Branding &amp; Templates</h6>
                <p class="text-muted small mb-3">Upload official logo, change website title &amp; switch homepage template.</p>
                <a href="https://searchkaro.midexa.in/portal/admin/branding.php" class="btn btn-outline-primary fw-bold btn-sm">Manage Branding &rarr;</a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-database-fill-check text-success me-2"></i> SQL Backups</h6>
                <p class="text-muted small mb-3">Create instant database dumps &amp; download offline backups.</p>
                <a href="https://searchkaro.midexa.in/portal/admin/backups.php" class="btn btn-outline-success fw-bold btn-sm">SQL Backups &rarr;</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-key-fill text-warning me-2"></i> API Keys (Optional)</h6>
                <p class="text-muted small mb-3">Google Maps &amp; Gemini AI Chat credentials.</p>
                <a href="https://searchkaro.midexa.in/portal/admin/api_keys.php" class="btn btn-outline-warning text-dark fw-bold btn-sm">Configure Keys &rarr;</a>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-heart-pulse-fill text-danger me-2"></i> System Health &amp; Backups</h6>
                <p class="text-muted small mb-3">Server diagnostics &amp; 1-click database backup export.</p>
                <a href="https://searchkaro.midexa.in/portal/admin/health.php" class="btn btn-outline-danger fw-bold btn-sm">Inspect &rarr;</a>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100">
                <h6 class="fw-bold text-dark mb-1"><i class="bi bi-chat-dots-fill text-primary me-2"></i> SMS &amp; WhatsApp</h6>
                <p class="text-muted small mb-3">Configure Fast2SMS &amp; WhatsApp OTP gateway.</p>
                <a href="https://searchkaro.midexa.in/portal/admin/sms_config.php" class="btn btn-outline-primary fw-bold btn-sm">SMS Gateway &rarr;</a>
            </div>
        </div>
    <?php if ($userRole === 'super_admin' || $userRole === 'admin'): ?>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-speedometer text-warning me-2"></i> Ride Fares</h6>
                    <p class="text-muted small mb-3">Manage live per-km rates &amp; base fares.</p>
                    <a href="<?= APP_URL ?>/portal/admin/pricing.php" class="btn btn-outline-warning text-dark fw-bold btn-sm">Edit Fares &rarr;</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-award-fill text-primary me-2"></i> Membership Plans</h6>
                    <p class="text-muted small mb-3">Manage subscription tiers &amp; lead credits.</p>
                    <a href="<?= APP_URL ?>/portal/admin/plans.php" class="btn btn-outline-primary fw-bold btn-sm">Edit Plans &rarr;</a>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-4 bg-white rounded-3 h-100">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-credit-card-2-front-fill text-success me-2"></i> Payment Gateways</h6>
                    <p class="text-muted small mb-3">Razorpay, PhonePe, Cashfree, PayU.</p>
                    <a href="<?= APP_URL ?>/portal/admin/gateways.php" class="btn btn-outline-success fw-bold btn-sm">Gateways &rarr;</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- System Audits Table (Only Visible if Allowed) -->
    <?php if (!empty($audits)): ?>
        <div class="card border-0 shadow-sm bg-white rounded-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0 text-dark"><?= __('recent_audits') ?></h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Action</th>
                            <th>Entity</th>
                            <th>IP Address</th>
                            <th>Details</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audits as $row): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?= e($row['action']) ?></span></td>
                                <td><?= e($row['entity_type'] ?? '-') ?></td>
                                <td><code><?= e($row['ip_address'] ?? '-') ?></code></td>
                                <td><?= e($row['details'] ?? '-') ?></td>
                                <td class="small text-muted"><?= e($row['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>