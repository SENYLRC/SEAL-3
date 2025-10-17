<?php

add_action(
    "mepr-product-registration-metabox",
    "nuamp_add_registration_field",
    10,
    3
);

function nuamp_add_registration_field($product)
{
    $nua_approval = get_post_meta(
        $product->rec->ID,
        "_mepr_nua_approval",
        true
    ); ?>
 <div id="nua-mp-require-approval" class="mepr-product-adv-item">
	<input type="checkbox" name="_mepr_nua_approval" id="_mepr_nua_approval" <?php echo esc_attr(
     $nua_approval
 )
     ? "checked"
     : ""; ?> />
	<label for="_mepr_nua_approval"><?php esc_html_e(
     "Members Require NUA Approval",
     "new-user-approve"
 ); ?></label>
	<?php wp_nonce_field("mepr_nua_approval_action", "mepr_nua_approval_nonce"); ?>

	<?php MeprAppHelper::info_tooltip(
     "_mepr_nua_approval",
     __("Members Require NUA Approval", "new-user-approve"),
     __(
         "Enable this option if you want membership to be only activated after user profile is approved using New User Approve settings, If users profile is denied then the membership will become inactive",
         "new-user-approve"
     )
 );
}

add_action(
    "mepr-membership-save-meta",
    "nuamp_add_registration_field_save",
    10,
    3
);

function nuamp_add_registration_field_save($product)
{
    if (
        isset($_POST["mepr_nua_approval_nonce"]) &&
        wp_verify_nonce(
            $_POST["mepr_nua_approval_nonce"],
            "mepr_nua_approval_action"
        )
    ) {
        if (isset($_POST["post_ID"])) {
            $nua_approval = 0;
            if (
                isset($_POST["_mepr_nua_approval"]) &&
                $_POST["_mepr_nua_approval"] == "on"
            ) {
                $nua_approval = 1;
            }

            update_post_meta(
                absint($_POST["post_ID"]),
                "_mepr_nua_approval",
                $nua_approval
            );
        }
    }
}

function memberpress_add_nua_cloumn($cols)
{
    $cols["col_nua_approval"] = __("Approval", "new-user-approve");
    return $cols;
}
add_filter("mepr-admin-members-cols", "memberpress_add_nua_cloumn");

function memberpress_add_nua_rows(
    $attributes,
    $rec,
    $column_name,
    $column_display_name
) {
    if ($column_name == "col_nua_approval") {

        $user_status = pw_new_user_approve()->get_user_status($rec->ID);

        $approve_link = add_query_arg([
            "nua-action" => "approve",
            "user" => $rec->ID,
        ]);
        $approve_link = remove_query_arg(["new_role"], $approve_link);
        $approve_link = wp_nonce_url($approve_link, "new-user-approve-mempr");

        $deny_link = add_query_arg([
            "nua-action" => "deny",
            "user" => $rec->ID,
        ]);
        $deny_link = remove_query_arg(["new_role"], $deny_link);
        $deny_link = wp_nonce_url($deny_link, "new-user-approve-mempr");

        $approve_action =
            '<a style="color:green" href="' .
            esc_url($approve_link) .
            '">' .
            __("Approve", "new-user-approve") .
            "</a>";
        $deny_action =
            '<a style="color:red" href="' .
            esc_url($deny_link) .
            '">' .
            __("Deny", "new-user-approve") .
            "</a>";

        if ($user_status == "pending") { ?>
			<td> 
			<p><?php echo esc_attr(ucfirst($user_status)); ?> </p>
			<?php if ($rec->ID != get_current_user_id() && !is_super_admin($rec->ID)) { ?>
				<p><?php echo esc_attr($approve_action); ?> | <?php echo esc_attr(
     $deny_action
 ); ?></p>
				</td>
				<?php }} elseif ($user_status == "approved") { ?>
			<td > 
			<p><?php echo esc_attr(ucfirst($user_status)); ?> </p>
			<?php if ($rec->ID != get_current_user_id() && !is_super_admin($rec->ID)) { ?>
				<p><?php echo esc_attr($deny_action); ?></p>
				</td>
				<?php }} elseif ($user_status == "denied") { ?>
			<td > 
			<p><?php echo esc_attr(ucfirst($user_status)); ?> </p>
			<?php if ($rec->ID != get_current_user_id() && !is_super_admin($rec->ID)) { ?>
				<p><?php echo esc_attr($approve_action); ?></p>
				</td>
				<?php }}
        ?>
		
		<?php
    }
}

add_action("mepr_members_list_table_row", "memberpress_add_nua_rows", 10, 4);

add_action("admin_head", "memberpress_nua_col_width");

function memberpress_nua_col_width()
{
    echo '<style>
    .column-col_nua_approval {
        width: 10%;
    } 
  </style>';
}

add_action(
    "memberpress_page_memberpress-members",
    "update_user_status_from_memberpress_members_page"
);

function update_user_status_from_memberpress_members_page()
{
    if (
        isset($_GET["nua-action"]) &&
        in_array($_GET["nua-action"], ["approve", "deny"]) &&
        !isset($_GET["new_role"])
    ) {
        check_admin_referer("new-user-approve-mempr");
        $status = sanitize_key($_GET["nua-action"]);
        $user = absint($_GET["user"]);

        pw_new_user_approve()->update_user_status($user, $status);
    }
}
