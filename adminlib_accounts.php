<?php
// adminlib_accounts.php — Manage WordPress user accounts by System

// --- Session (for CSRF) ---
session_start();
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

// --- WP includes ---
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

// --- Helpers ---
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k,$d=''){ return isset($_POST[$k]) ? $_POST[$k] : $d; }
function req($k,$d=''){ return isset($_REQUEST[$k]) ? $_REQUEST[$k] : $d; }

// --- DB ---
$db = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);

// --- Current system (from seal_function.php) ---
$SYSTEM = $SYSTEM ?? '';

// --- Routing ---
$action     = req('action','list');           // list | edit | save
$recnum     = req('recnum','');               // WP user ID
$per_page   = req('per_page','25');
$page       = max(1,(int)req('page','1'));

// --- Search inputs ---
$q_user     = req('q_user','');   // username
$q_email    = req('q_email','');  // email
$q_inst     = req('q_inst','');   // institution
$q_phone    = req('q_phone','');  // phone

?>
<style>
/* styling shell */
.seal-shell {font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, "Helvetica Neue", Arial, sans-serif; color:#111; }
.seal-wrap {max-width: 1100px; margin: 0 auto; padding: 18px;}
.seal-card {background:#fff; border:1px solid #e5e7eb; border-radius:14px; box-shadow: 0 1px 2px rgba(0,0,0,.04); padding:18px; margin-bottom:16px;}
.seal-title {font-size: 22px; font-weight:700; margin: 0 0 8px;}
.seal-sub {font-size:14px; color:#555; margin-bottom: 12px;}
.seal-row {display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;}
.seal-row .input, .seal-row select {padding:8px 10px; border:1px solid #d1d5db; border-radius:8px; min-width: 220px;}
.seal-btn {display:inline-block; padding:9px 14px; border-radius:10px; border:1px solid #0f766e; background:#0d9488; color:#fff; text-decoration:none; font-weight:600; cursor:pointer;}
.seal-btn.secondary { background:#fff; color:#0f766e; }
.seal-muted {color:#555;}
.seal-table {width:100%; border-collapse: collapse;}
.seal-table th, .seal-table td { padding:10px 8px; border-bottom:1px solid #eef2f7; text-align:left; }
.seal-table th { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#6b7280; background:#fafafa;}
.seal-hr { border:0; border-top:1px solid #e5e7eb; margin:16px 0;}
.seal-note { font-size:13px; color:#6b7280;}
@media (max-width: 720px) {
  .seal-row .input, .seal-row select { min-width: 100%; }
}
</style>

<div class="seal-shell"><div class="seal-wrap">
<?php

// ---------- SAVE ----------
if ($action === 'save' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        echo '<div class="seal-card"><div class="seal-title">Security error</div><div class="seal-sub">Invalid token.</div></div>';
    } else {
        $uid = (int)post('recnum',0);

        // confirm user is in same system
        $chk = "SELECT MAX(CASE WHEN meta_key='home_system' THEN meta_value END) AS home_system
                FROM wp_usermeta WHERE user_id=$uid";
        $res = mysqli_query($db,$chk);
        $row = mysqli_fetch_assoc($res);
        if (!$row || strtoupper($row['home_system']) !== strtoupper($SYSTEM)) {
            echo '<div class="seal-card"><div class="seal-title">Not allowed</div><div class="seal-sub">User not in your system.</div></div>';
        } else {
            // fields
            $email       = mysqli_real_escape_string($db, trim(post('user_email','')));
            $institution = mysqli_real_escape_string($db, trim(post('institution','')));
            $phone       = mysqli_real_escape_string($db, trim(post('phone','')));
            $alt_email   = mysqli_real_escape_string($db, trim(post('alt_email','')));

            // update wp_users
            mysqli_query($db, "UPDATE wp_users SET user_email='$email' WHERE ID=$uid");

            // update wp_usermeta
            $pairs = [
                'institution' => $institution,
                'phone'       => $phone,
                'alt_email'   => $alt_email
            ];
            foreach($pairs as $k=>$v){
                $sql = "INSERT INTO wp_usermeta (user_id,meta_key,meta_value)
                        VALUES ($uid,'$k','$v')
                        ON DUPLICATE KEY UPDATE meta_value='$v'";
                mysqli_query($db,$sql);
            }

            echo '<div class="seal-card"><div class="seal-title">Account updated</div>
                  <a class="seal-btn" href="'.h($_SERVER['REDIRECT_URL']).'">Back to search</a></div>';
        }
    }
}

// ---------- EDIT ----------
if ($action === 'edit' && $recnum !== '') {
    $uid = (int)$recnum;
    $sql = "SELECT u.ID, u.user_login, u.user_email,
                   MAX(CASE WHEN m.meta_key='institution' THEN m.meta_value END) AS institution,
                   MAX(CASE WHEN m.meta_key='phone' THEN m.meta_value END) AS phone,
                   MAX(CASE WHEN m.meta_key='alt_email' THEN m.meta_value END) AS alt_email,
                   MAX(CASE WHEN m.meta_key='home_system' THEN m.meta_value END) AS home_system
            FROM wp_users u
            LEFT JOIN wp_usermeta m ON u.ID=m.user_id
            WHERE u.ID=$uid
            GROUP BY u.ID";
    $res = mysqli_query($db,$sql);
    $r = mysqli_fetch_assoc($res);

    if (!$r || strtoupper($r['home_system']) !== strtoupper($SYSTEM)) {
        echo '<div class="seal-card"><div class="seal-title">Not found / not allowed</div><div class="seal-sub">No user in your system.</div></div>';
    } else {
        ?>
        <div class="seal-card">
            <div class="seal-title">Edit Account (<?php echo h($r['user_login']); ?>)</div>
            <div class="seal-sub">System: <b><?php echo h($SYSTEM); ?></b> • User ID: <?php echo (int)$r['ID']; ?></div>
            <form method="post" action="<?php echo h($_SERVER['REDIRECT_URL']); ?>?action=save">
                <input type="hidden" name="csrf" value="<?php echo h($_SESSION['csrf']); ?>">
                <input type="hidden" name="recnum" value="<?php echo (int)$r['ID']; ?>">

                <div class="seal-row">
                    <label><b>Email</b><br>
                        <input class="input" type="text" name="user_email" value="<?php echo h($r['user_email']); ?>">
                    </label>
                    <label><b>Institution</b><br>
                        <input class="input" type="text" name="institution" value="<?php echo h($r['institution']); ?>">
                    </label>
                    <label><b>Phone</b><br>
                        <input class="input" type="text" name="phone" value="<?php echo h($r['phone']); ?>">
                    </label>
                </div>

                <div class="seal-row">
                    <label><b>Alternative Email</b><br>
                        <input class="input" type="text" name="alt_email" value="<?php echo h($r['alt_email']); ?>">
                    </label>
                </div>

                <div style="margin-top:12px;">
                    <button class="seal-btn" type="submit">Save changes</button>
                    <a class="seal-btn secondary" href="<?php echo h($_SERVER['REDIRECT_URL']); ?>">Cancel</a>
                </div>
            </form>
        </div>
        <?php
    }
}

// ---------- LIST ----------
if ($action === 'list') {
    // Build WHERE for direct user fields
    $WHERE = [];
    if ($q_user)  $WHERE[] = "u.user_login LIKE '%".mysqli_real_escape_string($db,$q_user)."%'";
    if ($q_email) $WHERE[] = "u.user_email LIKE '%".mysqli_real_escape_string($db,$q_email)."%'";
    $WHERE_SQL = $WHERE ? "WHERE ".implode(" AND ",$WHERE) : "";

    // HAVING for meta fields
    $HAVING = "HAVING home_system = '".mysqli_real_escape_string($db,$SYSTEM)."'";
    if ($q_inst)  $HAVING .= " AND institution LIKE '%".mysqli_real_escape_string($db,$q_inst)."%'";
    if ($q_phone) $HAVING .= " AND phone LIKE '%".mysqli_real_escape_string($db,$q_phone)."%'";
    
    $sql = "SELECT u.ID, u.user_login, u.user_email,
                   MAX(CASE WHEN m.meta_key='institution' THEN m.meta_value END) AS institution,
                   MAX(CASE WHEN m.meta_key='phone' THEN m.meta_value END) AS phone,
                   MAX(CASE WHEN m.meta_key='home_system' THEN m.meta_value END) AS home_system
            FROM wp_users u
            LEFT JOIN wp_usermeta m ON u.ID=m.user_id
            $WHERE_SQL
            GROUP BY u.ID
            $HAVING
            ORDER BY u.user_login ASC";
    $res = mysqli_query($db,$sql);

    echo '<div class="seal-card">';
    echo '<div class="seal-title">Accounts in Your System</div>';
    echo '<div class="seal-sub">System: <b>'.h($SYSTEM).'</b>. Search and edit WP accounts tied to this system.</div>';
    echo '<form method="get" action="'.h($_SERVER['REDIRECT_URL']).'">';
    echo '<input type="hidden" name="action" value="list">';
    echo '<div class="seal-row">';
    echo '<label><b>Username</b><br><input class="input" type="text" name="q_user" value="'.h($q_user).'"></label>';
    echo '<label><b>Email</b><br><input class="input" type="text" name="q_email" value="'.h($q_email).'"></label>';
    echo '<label><b>Institution</b><br><input class="input" type="text" name="q_inst" value="'.h($q_inst).'"></label>';
    echo '<label><b>Phone</b><br><input class="input" type="text" name="q_phone" value="'.h($q_phone).'"></label>';
    echo '<div style="margin-left:auto;">
            <a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'">Clear</a>
            <button class="seal-btn" type="submit">Search</button>
          </div>';
    echo '</div></form></div>';

    // results
    echo '<div class="seal-card"><table class="seal-table">';
    echo '<tr><th>Username</th><th>Email</th><th>Institution</th><th>Phone</th><th>Action</th></tr>';
    if ($res) {
        while($row=mysqli_fetch_assoc($res)){
            echo '<tr>';
            echo '<td>'.h($row['user_login']).'</td>';
            echo '<td>'.h($row['user_email']).'</td>';
            echo '<td>'.h($row['institution']).'</td>';
            echo '<td>'.h($row['phone']).'</td>';
            echo '<td><a class="seal-btn secondary" href="'.h($_SERVER['REDIRECT_URL']).'?action=edit&recnum='.(int)$row['ID'].'">Edit</a></td>';
            echo '</tr>';
        }
    }
    echo '</table></div>';
}
?>
</div></div>