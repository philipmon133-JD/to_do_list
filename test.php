<?php
require_once __DIR__ . '/../includes/user_line_managers.php';
require_once __DIR__ . '/../config/security.php';

fs_enable_csrf_auto_injection();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    fs_verify_csrf_or_fail(false);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/role_policy.php';
require_once __DIR__ . '/../config/push_notifications.php';
require_once __DIR__ . '/../includes/gate_pass_policy.php';

date_default_timezone_set('Asia/Yangon');

// Auth Check
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int) $_SESSION['user_id'];
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

// User Authorization & Identity Check
$meq = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$meq->execute([$uid]);
$me = $meq->fetch(PDO::FETCH_ASSOC) ?: [];
$isHr = fs_identity_can_manage_hr_module($me);

// Fetch Gate Pass Request Data
$sql = "SELECT g.*, 
               r.full_name AS requester_name,
               r.staff_id, 
               r.department, 
               lm.full_name AS lm_name, 
               hr.full_name AS hr_name, 
               GROUP_CONCAT(pu.full_name ORDER BY pu.full_name SEPARATOR '၊ ') AS participant_names, 
               COUNT(gp.user_id) AS participant_count 
        FROM gate_pass_requests g 
        JOIN users r ON r.id = g.requester_id 
        LEFT JOIN users lm ON lm.id = g.lm_action_by 
        LEFT JOIN users hr ON hr.id = g.hr_action_by 
        JOIN gate_pass_participants gp ON gp.gate_pass_id = g.id 
        JOIN users pu ON pu.id = gp.user_id 
        WHERE g.id = ? 
        GROUP BY g.id";

$q = $pdo->prepare($sql);
$q->execute([$id]);
$r = $q->fetch(PDO::FETCH_ASSOC);

// Permission Validations
if (!$r || ($uid !== (int) $r['requester_id'] && !fs_is_user_line_manager($pdo, $uid, (int) $r['requester_id'], (int) $r['line_manager_id']) && !$isHr)) {
    http_response_code(403);
    exit('Access denied');
}

if ($r['lm_status'] !== 'Approved' && $uid === (int) $r['requester_id']) {
    http_response_code(403);
    exit('Gate Pass form is available after Line Manager approval.');
}

// Handle Form Submission (Return Action)
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'returned') {
    $lat = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $lng = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
    $acc = filter_var($_POST['accuracy'] ?? null, FILTER_VALIDATE_FLOAT);

    if ($uid !== (int) $r['requester_id']) {
        $error = 'Requester ကိုယ်တိုင်သာ ပြန်ရောက်ကြောင်း အတည်ပြုနိုင်ပါသည်။';
    } elseif ($r['lm_status'] !== 'Approved') {
        $error = 'Line Manager approval မရှိသေးပါ။';
    } elseif ($r['return_status'] === 'Returned') {
        $error = 'ပြန်ရောက်ကြောင်း အတည်ပြုပြီးပါပြီ။';
    } elseif ($lat === false || $lng === false || $acc === false || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || $acc < 0 || $acc > 100) {
        $error = 'GPS accuracy 100m အတွင်းရောက်မှ ပြန်စမ်းပါ။';
    } else {
        $u = $pdo->prepare("UPDATE gate_pass_requests 
                            SET return_status = 'Returned', 
                                returned_at = NOW(), 
                                return_latitude = ?, 
                                return_longitude = ?, 
                                return_accuracy = ? 
                            WHERE id = ? AND requester_id = ? AND return_status = 'Outside'");
        $u->execute([$lat, $lng, $acc, $id, $uid]);

        if ($u->rowCount() === 1) {
            $targets = array_values(array_unique(array_merge(
                fs_user_line_manager_ids($pdo, (int) $r['requester_id'], isset($r['line_manager_id']) ? (int) $r['line_manager_id'] : null),
                fs_push_hr_user_ids($pdo)
            )));

            fs_gate_pass_notify(
                $pdo,
                $targets,
                'Gate Pass Returned',
                $r['requester_name'] . ' returned to office at ' . date('h:i A') . '.',
                '/fs_software/mobile/gate_pass_form.php?id=' . $id
            );

            header('Location: gate_pass_form.php?id=' . $id . '&returned=1');
            exit;
        }

        $error = 'Unable to complete Gate Pass.';
    }
}
?>
<!doctype html>
<html lang="my">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title>Gate Pass Form</title>
    <link rel="stylesheet" href="../assets/css/output.css">
    <script defer src="../assets/js/gate-pass-return.js?v=20260810-1"></script>
    <style>
        html, body {
            margin: 0;
            background: #050c06;
            color: #fff;
        }
        .safe {
            min-height: 100dvh;
            padding: max(20px, env(safe-area-inset-top)) 14px calc(30px + env(safe-area-inset-bottom));
        }
        .panel {
            background: #0d1c11;
            border: 1px solid rgba(255, 255, 255, .1);
        }
        .paper {
            background: #fff;
            color: #17211a;
        }
        .sign {
            font-family: cursive;
            color: #21612d;
        }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .safe { padding: 0; }
            .paper { border: 1px solid #333; box-shadow: none; }
        }
    </style>
</head>
<body>
    <main class="safe max-w-lg mx-auto">
        <!-- Header Section -->
        <header class="no-print flex items-center gap-3 mb-4">
            <a href="gate_pass_request.php" class="panel w-11 h-11 rounded-xl flex items-center justify-center">←</a>
            <div>
                <h1 class="text-lg font-black">Gate Pass Form</h1>
                <p class="text-[10px] text-[#A5D6A7]">#GP-<?= str_pad((string) $r['id'], 6, '0', STR_PAD_LEFT) ?></p>
            </div>
        </header>

        <!-- Error Notification -->
        <?php if ($error): ?>
            <div class="no-print p-4 mb-4 rounded-xl bg-red-500/15 text-red-200 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Printable Document Section -->
        <article class="paper rounded-2xl shadow-2xl p-5">
            <div class="flex items-center gap-3 border-b-2 pb-4">
                <img src="../assets/images/logo.png" class="w-12 h-12 object-contain" alt="Company Logo">
                <div>
                    <h1 class="text-base font-black">FOCUS STAR CO., LTD.</h1>
                    <p class="text-xs font-bold text-[#2b5329]">အပြင်ထွက်ခွင့်လက်မှတ် (GATE PASS)</p>
                </div>
            </div>

            <div class="py-5 text-sm leading-8">
                <p><b>ရက်စွဲ —</b> <?= date('d-m-Y', strtotime($r['request_date'])) ?></p>
                <p><b>အချိန် —</b> <?= date('h:i A', strtotime($r['start_time'])) ?> မှ <?= date('h:i A', strtotime($r['expected_return_time'])) ?> အထိ</p>
                <p><b>သွားမည့်သူ (<?= $r['participant_count'] ?> ဦး) —</b> <?= htmlspecialchars($r['participant_names']) ?></p>
                
                <p class="mt-3">အောက်ဖော်ပြပါ အကြောင်းအရာဖြင့် ရုံးချိန်အတွင်း အပြင်ထွက်ခွင့်ပြုပါရန် လေးစားစွာ တင်ပြအပ်ပါသည်။</p>
                
                <div class="mt-3 p-4 bg-slate-100 rounded-xl">
                    <b>အကြောင်းအရာ —</b> <?= nl2br(htmlspecialchars($r['purpose'])) ?>
                </div>
            </div>

            <!-- Approval Status -->
            <div class="grid grid-cols-2 gap-3 border-t pt-5 text-center text-xs">
                <div>
                    <b>Line Manager</b>
                    <?php if ($r['lm_status'] === 'Approved'): ?>
                        <p class="sign text-base">System Approved ✓</p>
                        <b><?= htmlspecialchars($r['lm_name'] ?: 'Approved Manager') ?></b>
                        <small class="block">
                            <?= htmlspecialchars(fs_gate_pass_action_time($r['lm_action_at'] ?? (($r['approval_route'] ?? '') === 'direct_hr' ? ($r['created_at'] ?? null) : null)) ?? 'Approval time not recorded') ?>
                        </small>
                    <?php else: ?>
                        <p class="text-amber-600 font-bold">Pending</p>
                    <?php endif; ?>
                </div>

                <div>
                    <b>HR Manager</b>
                    <?php if ($r['hr_status'] === 'Approved'): ?>
                        <p class="sign text-base">System Approved ✓</p>
                        <b><?= htmlspecialchars($r['hr_name'] ?: 'HR Manager') ?></b>
                        <small class="block">
                            <?= htmlspecialchars(fs_gate_pass_action_time($r['hr_action_at'] ?? null) ?? 'Approval time not recorded') ?>
                        </small>
                    <?php elseif ($r['hr_status'] === 'Rejected'): ?>
                        <p class="text-red-600 font-bold">Rejected</p>
                    <?php else: ?>
                        <p class="text-amber-600 font-bold">Pending HR</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Return Status Badge -->
            <div class="mt-5 p-3 rounded-xl text-center text-sm font-bold <?= $r['return_status'] === 'Returned' ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700' ?>">
                <?= $r['return_status'] === 'Returned' 
                    ? 'ပြန်ရောက်ပြီး — ' . date('d-m-Y h:i A', strtotime($r['returned_at'])) 
                    : 'ပြန်မရောက်သေးပါ' ?>
            </div>
        </article>

        <!-- Actions Section -->
        <div class="no-print grid gap-3 mt-4">
            <button onclick="print()" class="py-3 panel rounded-xl font-bold">Print / Save PDF</button>

            <?php if ($uid === (int) $r['requester_id'] && $r['return_status'] !== 'Returned'): ?>
                <button id="returnBtn" class="py-4 rounded-xl bg-[#2e7d32] font-black">ပြန်ရောက်ပါပြီ(GPS)</button>
                <form id="returnForm" method="post">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="action" value="returned">
                    <input type="hidden" name="latitude">
                    <input type="hidden" name="longitude">
                    <input type="hidden" name="accuracy">
                </form>
            <?php endif; ?>
        </div>
    </main>

    <!-- Client-side Geolocation Handling -->
    <script>
        document.getElementById('returnBtn')?.addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'GPS ရယူနေသည်...';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const form = document.getElementById('returnForm');
                    form.latitude.value = position.coords.latitude;
                    form.longitude.value = position.coords.longitude;
                    form.accuracy.value = position.coords.accuracy;
                    form.submit();
                },
                () => {
                    alert('GPS မရပါ။ Location permission ဖွင့်ပြီး ပြန်စမ်းပါ။');
                    btn.disabled = false;
                    btn.textContent = 'ပြန်ရောက်ပါပြီ (GPS)';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0
                }
            );
        });
    </script>
</body>
</html>