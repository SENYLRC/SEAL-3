<?php
// adminlib_accounts.php — Manage SEAL WordPress user accounts by Home System

// ------------------
// Security + Session
// ------------------
if (session_status() === PHP_SESSION_NONE) {
    session_name('seal_admin_session');
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf_token = $_SESSION['csrf'];

// ------------------
// Includes
// ------------------
require '/var/www/seal_wp_script/seal_function.php';
require '/var/www/seal_wp_script/seal_db.inc';

// ------------------
// Helpers
// ------------------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k,$d=''){ return isset($_POST[$k]) ? $_POST[$k] : $d; }
function req($k,$d=''){ return isset($_REQUEST[$k]) ? $_REQUEST[$k] : $d; }

if (!function_exists('self_url')) {
    function self_url() {
        if (function_exists('get_permalink')) return get_permalink();
        return $_SERVER['REQUEST_URI'];
    }
}

// ------------------
// Access Control
// ------------------
$current_user = wp_get_current_user();
$allowed_roles = ['administrator', 'libsys'];
if ( ! array_intersect( $allowed_roles, (array) $current_user->roles ) ) {
    echo '<div style="padding:30px;text-align:center;color:#a00;font-family:sans-serif;">
            <h2>🚫 Access Denied</h2>
            <p>You must have the <strong>Lib Systems Staff</strong> or <strong>Administrator</strong> role to access this page.</p>
          </div>';
    exit;
}

$SYSTEM = $SYSTEM ?? '';
$action = req('action','list');
$recnum = (int)req('recnum',0);
?>

<style>
.seal-shell {font-family:system-ui,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;color:#111;}
.seal-wrap {max-width:1100px;margin:0 auto;padding:18px;}
.seal-card {background:#fff;border:1px solid #e5e7eb;border-radius:14px;box-shadow:0 1px 2px rgba(0,0,0,.04);padding:18px;margin-bottom:16px;}
.seal-title {font-size:22px;font-weight:700;margin:0 0 8px;}
.seal-sub {font-size:14px;color:#555;margin-bottom:12px;}
.seal-row {display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;}
.seal-row .input {padding:8px 10px;border:1px solid #d1d5db;border-radius:8px;min-width:220px;}
.seal-btn {display:inline-block;padding:9px 14px;border-radius:10px;border:1px solid #0f766e;background:#0d9488;color:#fff;text-decoration:none;font-weight:600;cursor:pointer;}
.seal-btn.secondary {background:#fff;color:#0f766e;}
.seal-btn.danger {background:#b91c1c;border-color:#991b1b;}
.seal-table {width:100%;border-collapse:collapse;}
.seal-table th,.seal-table td {padding:10px 8px;border-bottom:1px solid #eef2f7;text-align:left;}
.seal-table th {font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#6b7280;background:#fafafa;}
@media (max-width:720px){.seal-row .input{min-width:100%;}}
</style>

<div class="seal-shell"><div class="seal-wrap">

<?php
// --------------------------------------------------
// DELETE
// --------------------------------------------------
if ($action === 'delete' && $recnum) {
    if (!isset($_GET['csrf']) || $_GET['csrf'] !== $_SESSION['csrf']) {
        echo '<div class="seal-card"><div class="seal-title">Security Error</div><div class="seal-sub">Invalid token.</div></div>';
    } else {
        wp_delete_user($recnum);
        echo '<div class="seal-card"><div class="seal-title">✅ Account Deleted</div>
              <a class="seal-btn" href="'.h(self_url()).'">Return to List</a></div>';
    }
}

// --------------------------------------------------
// ADD NEW ACCOUNT (POST)
// --------------------------------------------------
if ($action === 'add' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        echo '<div class="seal-card"><div class="seal-title">Security Error</div><div class="seal-sub">Invalid token.</div></div>';
    } else {
        $username = sanitize_user(post('user_login',''));
        $email    = sanitize_email(post('user_email',''));
        $password = (string)post('user_pass','');
        $institution = sanitize_text_field(post('institution',''));
        $address_loc_code = sanitize_text_field(post('address_loc_code',''));

        if ( empty($username) || empty($email) || empty($password) || empty($institution) ) {
            echo '<div class="seal-card"><div class="seal-title">❌ Missing Required Fields</div><div class="seal-sub">Please fill out all required fields.</div></div>';
        } elseif ( username_exists($username) || email_exists($email) ) {
            echo '<div class="seal-card"><div class="seal-title">⚠️ Account Exists</div><div class="seal-sub">A user with that username or email already exists.</div></div>';
        } else {
            $uid = wp_create_user($username, $password, $email);
            if (is_wp_error($uid)) {
                echo '<div class="seal-card"><div class="seal-title">❌ Error Creating Account</div><div class="seal-sub">'.h($uid->get_error_message()).'</div></div>';
            } else {
                $user = new WP_User($uid);
                $user->set_role('library_staff');

                update_user_meta($uid, 'institution', $institution);
                update_user_meta($uid, 'home_system', $SYSTEM);
                update_user_meta($uid, 'address_loc_code', $address_loc_code);

                echo '<div class="seal-card"><div class="seal-title">✅ Account Created</div>
                      <div class="seal-sub">User <strong>'.h($username).'</strong> added successfully as Library Staff.</div>
                      <a class="seal-btn" href="'.h(self_url()).'">Back to List</a></div>';
            }
        }
    }
}

// --------------------------------------------------
// SHOW ADD FORM
// --------------------------------------------------
if ($action === 'new') {
?>
<div class="seal-card">
    <div class="seal-title">Add New Library Staff Account</div>
    <form method="post" action="<?php echo h(self_url()); ?>?action=add">
        <input type="hidden" name="csrf" value="<?php echo h($csrf_token); ?>">
        <div class="seal-row">
            <label><b>Username*</b><br><input class="input" type="text" name="user_login" required></label>
            <label><b>Email*</b><br><input class="input" type="email" name="user_email" required></label>
        </div>
        <div class="seal-row">
            <label><b>Password*</b><br><input class="input" type="password" name="user_pass" required></label>
            <label><b>Institution*</b><br><input class="input" type="text" name="institution" required></label>
        </div>
        <div class="seal-row">
            <label><b>LOC Code</b><br><input class="input" type="text" name="address_loc_code"></label>
        </div>
        <div style="margin-top:12px;">
            <button class="seal-btn" type="submit">Create Account</button>
            <a class="seal-btn secondary" href="<?php echo h(self_url()); ?>">Cancel</a>
        </div>
    </form>
</div>
<?php
}

// --------------------------------------------------
// SAVE (EDIT)
// --------------------------------------------------
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        echo '<div class="seal-card"><div class="seal-title">Security Error</div><div class="seal-sub">Invalid token.</div></div>';
    } else {
        $uid = (int)post('recnum', 0);
        $email    = sanitize_email(post('user_email', ''));
        $password = (string)post('user_pass', '');
        $fields = [
            'institution','home_system','phone','alt_email','address_loc_code','oclc_symbol',
            'delivery_address1','delivery_address2','delivery_city','delivery_state','delivery_zip'
        ];
        $meta = [];
        foreach ($fields as $f) $meta[$f] = sanitize_text_field(post($f,''));

        $userdata = ['ID'=>$uid, 'user_email'=>$email];
        if (!empty($password)) $userdata['user_pass']=$password;
        wp_update_user($userdata);

        foreach ($meta as $k=>$v) update_user_meta($uid,$k,$v);

        echo '<div class="seal-card"><div class="seal-title">✅ Account Updated</div>
              <a class="seal-btn" href="'.h(self_url()).'">Back to List</a></div>';
    }
}

// --------------------------------------------------
// EDIT
// --------------------------------------------------
if ($action === 'edit' && $recnum) {
    $user = get_userdata($recnum);
    if (!$user) {
        echo '<div class="seal-card"><div class="seal-title">❌ User not found</div></div>';
    } else {
        $fields = [
            'institution','home_system','phone','alt_email','address_loc_code','oclc_symbol',
            'delivery_address1','delivery_address2','delivery_city','delivery_state','delivery_zip'
        ];
        $meta = [];
        foreach ($fields as $key) $meta[$key] = get_user_meta($recnum, $key, true) ?: '';
        $display_name = $user->display_name ?: $user->user_login;
?>
<div class="seal-card">
    <div class="seal-title">Edit Account: <?php echo h($display_name); ?> (<?php echo h($user->user_login); ?>)</div>
    <div class="seal-sub">Home System: <b><?php echo h($meta['home_system']); ?></b> • User ID: <?php echo (int)$recnum; ?></div>

    <form method="post" action="<?php echo h(self_url()); ?>?action=save">
        <input type="hidden" name="csrf" value="<?php echo h($csrf_token); ?>">
        <input type="hidden" name="recnum" value="<?php echo (int)$recnum; ?>">

        <div class="seal-row">
            <label><b>Email</b><br><input class="input" type="email" name="user_email" value="<?php echo h($user->user_email); ?>"></label>
            <label><b>New Password</b><br><input class="input" type="password" name="user_pass" placeholder="Leave blank to keep existing"></label>
        </div>
        <div class="seal-row">
            <label><b>Institution</b><br><input class="input" type="text" name="institution" value="<?php echo h($meta['institution']); ?>"></label>
            <label><b>Home System</b><br><input class="input" type="text" name="home_system" value="<?php echo h($meta['home_system']); ?>"></label>
        </div>
        <div class="seal-row">
            <label><b>Phone</b><br><input class="input" type="text" name="phone" value="<?php echo h($meta['phone']); ?>"></label>
            <label><b>Alt Email</b><br><input class="input" type="email" name="alt_email" value="<?php echo h($meta['alt_email']); ?>"></label>
        </div>
        <div class="seal-row">
            <label><b>LOC Code</b><br><input class="input" type="text" name="address_loc_code" value="<?php echo h($meta['address_loc_code']); ?>"></label>
            <label><b>OCLC Symbol</b><br><input class="input" type="text" name="oclc_symbol" value="<?php echo h($meta['oclc_symbol']); ?>"></label>
        </div>

        <hr style="border-top:1px solid #ddd;margin:12px 0;">
        <div class="seal-title" style="font-size:18px;">Delivery Address</div>
        <div class="seal-row">
            <label><b>Address 1</b><br><input class="input" type="text" name="delivery_address1" value="<?php echo h($meta['delivery_address1']); ?>"></label>
            <label><b>Address 2</b><br><input class="input" type="text" name="delivery_address2" value="<?php echo h($meta['delivery_address2']); ?>"></label>
        </div>
        <div class="seal-row">
            <label><b>City</b><br><input class="input" type="text" name="delivery_city" value="<?php echo h($meta['delivery_city']); ?>"></label>
            <label><b>State</b><br><input class="input" type="text" name="delivery_state" value="<?php echo h($meta['delivery_state']); ?>"></label>
            <label><b>ZIP</b><br><input class="input" type="text" name="delivery_zip" value="<?php echo h($meta['delivery_zip']); ?>"></label>
        </div>

        <div style="margin-top:12px;">
            <button class="seal-btn" type="submit">Save Changes</button>
            <a class="seal-btn secondary" href="<?php echo h(self_url()); ?>">Cancel</a>
            <a class="seal-btn danger"
               href="<?php echo h(self_url()); ?>?action=delete&recnum=<?php echo (int)$recnum; ?>&csrf=<?php echo h($csrf_token); ?>"
               onclick="return confirm('Are you sure you want to permanently delete this account?');">Delete</a>
        </div>
    </form>
</div>
<?php
    }
}

// --------------------------------------------------
// LIST + SEARCH
// --------------------------------------------------
if ($action === 'list') {
    global $wpdb;
    $search = trim(req('search',''));
    $where = "WHERE 1=1";
    if (!empty($search)) {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where .= $wpdb->prepare(" AND (
            u.user_email LIKE %s OR
            u.display_name LIKE %s OR
            m1.meta_value LIKE %s OR
            m2.meta_value LIKE %s
        )", $like, $like, $like, $like);
    }

    $sql = "
        SELECT DISTINCT u.ID, u.display_name, u.user_login, u.user_email,
            m1.meta_value AS institution,
            m2.meta_value AS address_loc_code,
            m3.meta_value AS home_system
        FROM {$wpdb->users} u
        LEFT JOIN {$wpdb->usermeta} m1 ON (u.ID=m1.user_id AND m1.meta_key='institution')
        LEFT JOIN {$wpdb->usermeta} m2 ON (u.ID=m2.user_id AND m2.meta_key='address_loc_code')
        LEFT JOIN {$wpdb->usermeta} m3 ON (u.ID=m3.user_id AND m3.meta_key='home_system')
        $where
        HAVING m3.meta_value=%s
        ORDER BY u.display_name ASC
    ";
    $rows = $wpdb->get_results($wpdb->prepare($sql, $SYSTEM));

    echo '<div class="seal-card"><div class="seal-title">Accounts in '.h($SYSTEM).'</div>';
    echo '<form method="get" action="'.h(self_url()).'" class="seal-row" style="margin-bottom:15px;">';
    echo '<input type="hidden" name="action" value="list">';
    echo '<input class="input" type="text" name="search" placeholder="Search by name, email, LOC, or institution" value="'.h($search).'">';
    echo '<button class="seal-btn" type="submit">Search</button>';
    echo '<a class="seal-btn secondary" href="'.h(self_url()).'">Reset</a>';
    echo '<a class="seal-btn" style="margin-left:auto;" href="'.h(self_url()).'?action=new">+ Add Account</a>';
    echo '</form>';

    echo '<table class="seal-table">';
    echo '<tr><th>Name</th><th>Email</th><th>Institution</th><th>LOC Code</th><th>Action</th></tr>';
    foreach ($rows as $row) {
        echo '<tr>';
        echo '<td>'.h($row->display_name).'</td>';
        echo '<td>'.h($row->user_email).'</td>';
        echo '<td>'.h($row->institution).'</td>';
        echo '<td>'.h($row->address_loc_code).'</td>';
        echo '<td><a class="seal-btn secondary" href="'.h(self_url()).'?action=edit&recnum='.(int)$row->ID.'">Edit</a></td>';
        echo '</tr>';
    }
    echo '</table></div>';
}
?>
</div></div>