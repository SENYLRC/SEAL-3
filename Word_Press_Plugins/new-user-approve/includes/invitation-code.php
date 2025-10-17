<?php

/**  Copyright 2013
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

if (!defined("ABSPATH")) {
    exit();
}
if (!class_exists("NUA_Invitation_Code")) {
    class NUA_Invitation_Code
    {
        private static $instance;

        private $screen_name = "nua-invitation-code";
        public $code_post_type = "invitation_code";
        public $usage_limit_key = "_nua_usage_limit";
        public $expiry_date_key = "_nua_code_expiry";
        public $status_key = "_nua_code_status";
        public $code_key = "_nua_code";
        public $total_code_key = "_total_nua_code";
        public $registered_users = "_registered_users";
        private $option_group = "nua_options_group";
        public $option_key = "new_user_approve_options";
        /**
         * Returns the main instance.
         *
         * @return NUA_Invitation_Code
         */
        public static function instance()
        {
            if (!isset(self::$instance)) {
                self::$instance = new NUA_Invitation_Code();
            }
            return self::$instance;
        }

        private function __construct()
        {
            //Action
            add_action("admin_init", [$this, "nua_deactivate_code"]);

            //Filter
            add_filter("manage_" . $this->code_post_type . "_posts_columns", [
                $this,
                "invitation_code_columns",
            ]);
            add_action(
                "manage_" . $this->code_post_type . "_posts_custom_column",
                [$this, "invitation_code_columns_content"],
                10,
                2
            );
            add_action("admin_head", [$this, "invitation_code_edit_page_css"]);
            add_filter(
                "nua_disable_welcome_email",
                [$this, "nua_disable_welcome_email_callback"],
                10,
                2
            );

            $options = get_option("new_user_approve_options");
            if (
                isset($options["nua_free_invitation"]) &&
                $options["nua_free_invitation"] === "enable"
            ) {
                add_action("register_form", [
                    $this,
                    "nua_invitation_code_field",
                ]);
                add_filter(
                    "register_post",
                    [$this, "nua_invitation_status_code_field_validation"],
                    6,
                    3
                );
                add_filter(
                    "woocommerce_register_post",
                    [$this, "nua_woocommerce_invitation_code_validation"],
                    10,
                    3
                );

                add_filter(
                    "new_user_approve_default_status",
                    [$this, "nua_invitation_status_code"],
                    10,
                    2
                );
                add_action("woocommerce_register_form", [
                    $this,
                    "nua_invitation_code_field",
                ]);
                add_action(
                    "um_after_form_fields",
                    [$this, "nua_invitation_code_field"],
                    10,
                    2
                );
                add_action(
                    "nua_invited_user",
                    [$this, "message_above_regform"],
                    10,
                    1
                );
                // compatibility with UsersWP plugin.
                add_action(
                    "uwp_template_fields",
                    [$this, "uwp_nua_invitation_code_field"],
                    10,
                    1
                );
                add_filter(
                    "uwp_validate_fields_before",
                    [$this, "uwp_invite_code_check"],
                    10,
                    3
                );
            }

            add_action("admin_notices", [$this, "nua_invite_code_errors"]);
        }

        public function uwp_nua_invitation_code_field($form_type)
        {
            if ($form_type !== "register") {
                return;
            }
            $options = get_option("new_user_approve_options");
            $required = false;
            if (!empty($options["nua_checkbox_textbox"])) {
                $required = true;
            }
            ?>
		
		<p class="nua_inv_field form-group"> 
		<?php if ($required == true): ?>
			<!-- snfr -->
			<label for="invitation_code"><?php esc_html_e(
       "Invitation Code",
       "new-user-approve"
   ); ?>&nbsp;
			  <span id="nua-required" aria-hidden="true" style="color:#a00">*</span>
			  <span class="screen-reader-text">Required</span>
			</label>
			<?php else: ?>
				<!-- snfr -->
				<label> <?php esc_html_e("Invitation Code", "new-user-approve"); ?></label>
			<?php endif; ?>
			<input type="text" class="nua_invitation_code form-control" name="nua_invitation_code"/>
			<?php wp_nonce_field(
       "nua_invitation_code_action",
       "nua_invitation_code_nonce"
   ); ?>
		</p>
		<?php
        }

        public function uwp_invite_code_check($errors, $data, $type)
        {
            if ($type !== "register") {
                return $errors;
            }

            $options = get_option("new_user_approve_options");

            // Use POST for nonce verification
            if (
                isset($_POST["nua_invitation_code_nonce"]) &&
                wp_verify_nonce(
                    $_POST["nua_invitation_code_nonce"],
                    "nua_invitation_code_action"
                )
            ) {
                if (
                    isset($data["nua_invitation_code"]) &&
                    !empty($data["nua_invitation_code"])
                ) {
                    $args = [
                        "numberposts" => -1,
                        "post_type" => $this->code_post_type,
                        "post_status" => "publish",
                        "meta_query" => [
                            "relation" => "AND",
                            [
                                [
                                    "key" => $this->code_key,
                                    "value" => sanitize_text_field(
                                        $data["nua_invitation_code"]
                                    ),
                                    "compare" => "=",
                                ],
                                [
                                    "key" => $this->usage_limit_key,
                                    "value" => "1",
                                    "compare" => ">=",
                                ],
                                [
                                    "key" => $this->expiry_date_key,
                                    "value" => time(),
                                    "compare" => ">=",
                                ],
                                [
                                    "key" => $this->status_key,
                                    "value" => "Active",
                                    "compare" => "=",
                                ],
                            ],
                        ],
                    ];

                    $posts = get_posts($args);
                    $flag = true;

                    foreach ($posts as $post_inv) {
                        $code_inv = get_post_meta(
                            $post_inv->ID,
                            $this->code_key,
                            true
                        );
                        if (
                            $code_inv ===
                            sanitize_text_field($data["nua_invitation_code"])
                        ) {
                            $flag = false;
                            global $inv_file_lock;
                            $inv_file_lock = $this->invite_code_hold(
                                $post_inv->ID
                            );
                            if ($inv_file_lock === false) {
                                $errors->add(
                                    "invcode_error",
                                    "<strong>Notice</strong>: Server is busy, please try again!"
                                );
                                return $errors;
                            }
                            return $errors;
                        }
                    }

                    if ($flag) {
                        $errors->add(
                            "invcode_error",
                            "<strong>ERROR</strong>: The Invitation code is invalid"
                        );
                        return $errors;
                    }

                    if (
                        isset($data["nua_invitation_code"]) &&
                        isset($options["nua_registration_deadline"]) &&
                        !isset($options["nua_auto_approve_deadline"])
                    ) {
                        $errors->add(
                            "invcode_error",
                            "<strong>Error</strong>: Cannot use Code because deadline exceeded."
                        );
                    }
                } elseif (
                    !isset($data["nua_invitation_code"]) ||
                    (isset($data["nua_invitation_code"]) &&
                        empty($data["nua_invitation_code"]) &&
                        !empty($options["nua_checkbox_textbox"]))
                ) {
                    $errors->add(
                        "invcode_error",
                        "<strong>ERROR</strong>: Please add an Invitation code."
                    );
                }
            } elseif (
                !isset($data["nua_invitation_code"]) ||
                (isset($data["nua_invitation_code"]) &&
                    empty($data["nua_invitation_code"]) &&
                    !empty($options["nua_checkbox_textbox"]))
            ) {
                $errors->add(
                    "invcode_nonce_error",
                    "<strong>ERROR</strong>: Something went wrong."
                );
            }

            return $errors;
        }

        public function nua_disable_welcome_email_callback($disabled, $user_id)
        {
            $status = get_user_meta($user_id, "pw_user_status", true);
            if ("approved" == $status) {
                $disabled = true;
            }
            return $disabled;
        }

        public function nua_deactivate_code()
        {
            if (
                isset($_GET["post_type"]) &&
                $_GET["post_type"] == $this->code_post_type &&
                is_admin()
            ) {
                if (
                    isset($_GET["post_id"]) &&
                    check_admin_referer(
                        "nua_deactivateactivate-" . absint($_GET["post_id"]),
                        "nonce"
                    )
                ) {
                    if (
                        isset($_GET["nua_action"]) &&
                        "activate" == $_GET["nua_action"]
                    ) {
                        update_post_meta(
                            absint($_GET["post_id"]),
                            $this->status_key,
                            "Active"
                        );
                    } else {
                        update_post_meta(
                            absint($_GET["post_id"]),
                            $this->status_key,
                            "InActive"
                        );
                    }
                }
            }
        }

        public function invitation_code_edit_page_css()
        {
            if (
                isset($_GET["post_type"]) &&
                $_GET["post_type"] == $this->code_post_type
            ) { ?>
			<style>
				.widefat td,
				.widefat th {
					height: 36px;
				}
			</style>
			<?php }
        }

        /**
         * Output the settings
         */
        public function invitation_code_settings()
        {
            $action = isset($_GET["action"])
                ? sanitize_text_field(wp_unslash($_GET["action"]))
                : "add-codes"; ?>
		<div class="wrap">
			<div id="icon-options-general" class="icon32"><br /></div>
			<h2 class="nua-settings-heading"><?php esc_html_e(
       "Invitation Code Settings",
       "new-user-approve"
   ); ?></h2>

			<div class="nav-tab-wrapper">
				
				<div id="nua-invitation-layout" style="position:relative;">
					
				</div>
			
			</div>
			<?php
        }

        public function get_the_required_tab($action, $tab)
        {
            if ("add-codes" == $action) {
                if ("manual" == $tab) {
                    $this->manual_add_codes();
                } else {
                    // 'auto' == $tab
                    $this->auto_add_codes();
                }
            } elseif ("import-codes" == $action) {
                $this->import_codes();
            } elseif ("email" == $action) {
                $this->email();
            }
            // else if ('Settings' == $action) {
            //  $this->option_invitation_code();
            // }
        }

        public function manual_add_codes()
        {
            $count = 0;
            $code_already_exists = [];
            if (isset($_POST["nua_manual_add"])) {
                if (!empty($_POST["nua-manual-add-nonce-field"])) {
                    $nonce = sanitize_text_field(
                        wp_unslash($_POST["nua-manual-add-nonce-field"])
                    );
                }
                if (!wp_verify_nonce($nonce, "nua-manual-add-nonce")) {
                    return;
                }

                $limit = empty($_POST["nua_manual_add"]["usage_limit"])
                    ? 1
                    : absint($_POST["nua_manual_add"]["usage_limit"]);
                $expiry = !empty($_POST["nua_manual_add"]["expiry_date"])
                    ? sanitize_text_field(
                        wp_unslash($_POST["nua_manual_add"]["expiry_date"])
                    )
                    : "";
                $Status = "Active";
                //$dateTime = new DateTime(str_replace('/','-',$expiry));
                //$expiry_timestamp = $dateTime->format('U');
                $expiry_timestamp = strtotime("$expiry 23:59:59");

                $code = !empty($_POST["nua_manual_add"]["codes"])
                    ? sanitize_textarea_field(
                        wp_unslash($_POST["nua_manual_add"]["codes"])
                    )
                    : "";
                $code = explode("\n", $code);

                foreach ($code as $in_code) {
                    if (empty(trim($in_code))) {
                        continue;
                    }

                    if ($this->invitation_code_already_exists($in_code)) {
                        $code_already_exists[] = $in_code;
                        continue;
                    }

                    $my_post = [
                        "post_title" => sanitize_text_field($in_code),
                        "post_status" => "publish",
                        "post_type" => $this->code_post_type,
                    ];

                    $post_code = wp_insert_post($my_post);
                    if (!empty($post_code)) {
                        update_post_meta(
                            $post_code,
                            $this->code_key,
                            sanitize_text_field($in_code)
                        );
                        update_post_meta(
                            $post_code,
                            $this->usage_limit_key,
                            $limit
                        );
                        update_post_meta(
                            $post_code,
                            $this->total_code_key,
                            $limit
                        );
                        update_post_meta(
                            $post_code,
                            $this->expiry_date_key,
                            $expiry_timestamp
                        );
                        update_post_meta(
                            $post_code,
                            $this->status_key,
                            $Status
                        );

                        $count++;
                    }
                }
                if (!empty($count)) {
                    $inv_code_success_msg =
                        $count > 1
                            ? "Codes Have Been Added Successfully"
                            : "Code Has Been Added Successfully"; ?>
				<p class="nua-success  nua-message" id="successMessage" > 
					<?php echo esc_html($inv_code_success_msg, "new-user-approve"); ?>
				</p> 
					<?php if (!empty($code_already_exists)) {
         $inv_code_exist_msg =
             count($code_already_exists) > 1
                 ? "Codes Already Exist"
                 : "Code Already Exists"; ?>
					<p class="nua-already-exists nua-error nua-message" id="errorMessage" > 
						<?php echo esc_html(
          sprintf(
              "%s " . $inv_code_exist_msg,
              implode(", ", $code_already_exists)
          ),
          "new-user-approve"
      ); ?>
					</p> 
						<?php
     }
                } elseif (empty($count) && !empty($code_already_exists)) {
                    $inv_code_exist_msg =
                        count($code_already_exists) > 1
                            ? "Codes Already Exist."
                            : "Code Already Exists."; ?>
					<p class="nua-already-exists nua-error nua-message" id="errorMessage" > 
					<?php echo esc_html(
         sprintf(
             "%s " . $inv_code_exist_msg,
             implode(", ", $code_already_exists)
         ),
         "new-user-approve"
     ); ?>
					</p> 
					<?php
                } else {
                     ?>
				<p class="nua-fail nua-error nua-message" id="failMessage" > 
					<?php echo esc_html(sprintf("Code Not Added.", "new-user-approve")); ?>
				</p> 
					<?php
                }
            }
            ?>
			<form method="post" action=''>
				<?php $nonce = wp_create_nonce("nua-manual-add-nonce"); ?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th><?php esc_html_e("Add Codes", "new-user-approve"); ?></th>
							<td>
								<div style="max-width: 600px;">
									<textarea id="nua_manual_add_add_codes" name="nua_manual_add[codes]" required class="nua-textarea"></textarea>
								</div>
								<p class="description"><?php esc_html_e(
            "Enter one code per line.",
            "new-user-approve"
        ); ?></p>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e("Usage Limit", "new-user-approve"); ?></th>
							<td>
								<input id="nua_manual_add_usage_limit" name="nua_manual_add[usage_limit]" placeholder="1" size="40" type="text" class="nua-text-field">
								<input type = "hidden"  name="nua-manual-add-nonce-field" value = "<?php esc_attr_e(
            $nonce
        ); ?>">

							</td>
						</tr>
						<tr>
							<th><?php esc_html_e("Expiry Date", "new-user-approve"); ?></th>
							<td>
								<input id="nua_manual_add_expiry_date" name="nua_manual_add[expiry_date]" size="40" type="date" class="nua-text-field">

							</td>
						</tr>
						<tr>
							<th colspan="2">
								<p class="submit nua-submit"><input type="submit" name="nua_manual_add[submit]" id="submit" class="button button-primary" value="Save Changes"></p>

							</th>
						</tr>
					</tbody>
			</form>
			</table>
		<?php
        }

        public function auto_add_codes()
        {
            ?>
		<h2>Get pro version to avail these feature<br></h2>
		<h3><a href='https://newuserapprove.com/pricing/?utm_source=wordpress&utm_medium=plugin#lifetime-plan' target = _blank>Click here to get the Pro Version</a></h3>
			<?php
        }
        public function import_codes()
        {
            ?>
		<h2>Get pro version to avail these feature<br></h2>
		<h3><a href='https://newuserapprove.com/pricing/?utm_source=wordpress&utm_medium=plugin#lifetime-plan' target = _blank>Click here to get the Pro Version</a></h3>
			<?php
        }
        public function email()
        {
            ?>
		<h2>Get pro version to avail these feature<br></h2>
		<h3><a href='https://newuserapprove.com/pricing/?utm_source=wordpress&utm_medium=plugin#lifetime-plan' target = _blank>Click here to get the Pro Version</a></h3>
			<?php
        }

        /**
         *
         * @since 2.5.2
         */
        public function invitation_code_already_exists($code)
        {
            $posts_with_meta = get_posts([
                "posts_per_page" => 1, // we only want to check if any exists, so don't need to get all of them
                "meta_key" => $this->code_key,
                "meta_value" => $code,
                "post_type" => $this->code_post_type,
            ]);

            if (count($posts_with_meta)) {
                return true;
            }
            return false;
        }
        /**
         *
         * @since 2.5.2
         */
        public function invitation_code_limit_check($code)
        {
            $is_inv_code_limit = [
                "numberposts" => 1,
                "post_type" => $this->code_post_type,
                // we are checking two things code and its limit , so we are using meta query
                "meta_query" => [
                    "relation" => "AND",
                    [
                        [
                            "key" => $this->code_key,
                            "value" => $code,
                            "compare" => "=",
                        ],
                        [
                            "key" => $this->usage_limit_key,
                            "value" => "1",
                            "compare" => ">=",
                        ],
                        [
                            "relation" => "OR",
                            [
                                "key" => $this->status_key,
                                "value" => "Active",
                                "compare" => "=",
                            ],
                            [
                                "key" => $this->status_key,
                                "value" => "Expired",
                                "compare" => "=",
                            ],
                        ],
                    ],
                ],
            ];

            $is_inv_code_limit = get_posts($is_inv_code_limit);
            if (count($is_inv_code_limit)) {
                return true;
            } else {
                return false;
            }
        }
        /**
         *
         * @since 2.5.2
         */
        public function invitation_code_expiry_check($code)
        {
            $is_inv_code_expired = [
                "numberposts" => 1,
                "post_type" => $this->code_post_type,
                // we are checking two things code and its expiry , so we are using meta query
                "meta_query" => [
                    "relation" => "AND",
                    [
                        [
                            "key" => $this->code_key,
                            "value" => $code,
                            "compare" => "=",
                        ],
                        [
                            "key" => $this->expiry_date_key,
                            "value" => time(),
                            "compare" => ">=",
                        ],
                        [
                            "relation" => "OR",
                            [
                                "key" => $this->status_key,
                                "value" => "Active",
                                "compare" => "=",
                            ],
                            [
                                "key" => $this->status_key,
                                "value" => "Expired",
                                "compare" => "=",
                            ],
                        ],
                    ],
                ],
            ];

            $is_inv_code_expired = get_posts($is_inv_code_expired);

            if (count($is_inv_code_expired)) {
                return false;
            } else {
                return true;
            }
        }

        public function get_available_invitation_codes()
        {
            $args = [
                "numberposts" => -1,
                "post_type" => $this->code_post_type,
                "post_status" => "publish",
                "meta_query" => [
                    "relation" => "AND",
                    [
                        [
                            "key" => $this->usage_limit_key,
                            "value" => "1",
                            "compare" => ">=",
                        ],

                        [
                            "key" => $this->expiry_date_key,
                            "value" => time(),
                            "compare" => ">=",
                        ],
                        [
                            "key" => $this->status_key,
                            "value" => "Active",
                            "compare" => "=",
                        ],
                    ],
                ],
            ];

            $codes = get_posts($args);

            return $codes;
        }

        public function nua_invitation_code_field()
        {
            $required = " *";
            if (true === apply_filters("nua_invitation_code_optional", true)) {
                $required = " (optional)";
            }
            ?>
			<?php $nonce = wp_create_nonce("nua-invitation-code-nonce"); ?>

			<p>
				<label> <?php esc_html_e(
        "Invitation Code",
        "new-user-approve"
    ); ?><span><?php esc_attr_e($required); ?></span></label>
				<input type="hidden"  name="nua_invitation_code_nonce_field" value = <?php esc_attr_e(
        $nonce
    ); ?>/>
				<input type="text" class="nua_invitation_code" name="nua_invitation_code" />
			</p>
			<?php
        }

        /**
         *
         * @since 2.5.2
         */
        public function inv_code_alreay_exists_notification()
        {
            $class = "notice notice-error";
            $message = esc_html__(
                "Invitation Code Already Exists.",
                "new-user-approve"
            );
            printf(
                '<div class="%1$s"><p>%2$s</p></div>',
                esc_attr($class),
                esc_html($message)
            );
            delete_transient("inv_code_exists"); // No need to keep this tip after displaying notification
        }

        public function invite_code_hold($inv_id)
        {
            $inv_file = fopen($this->invite_code_lock_file($inv_id), "w+");

            if (!flock($inv_file, LOCK_EX | LOCK_NB)) {
                return false;
            }

            ftruncate($inv_file, 0);
            fwrite($inv_file, microtime(true));
            return $inv_file;
        }

        public function invite_code_release($inv_file, $inv_id)
        {
            if (is_resource($inv_file)) {
                fflush($inv_file);
                flock($inv_file, LOCK_UN);
                fclose($inv_file);
                unlink($this->invite_code_lock_file($inv_id));

                return true;
            }

            return false;
        }

        public function invite_code_lock_file($inv_id)
        {
            return apply_filters(
                "invite_code_lock_file",
                get_temp_dir() . "/invite-code" . $inv_id . ".lock",
                $inv_id
            );
        }

        public function nua_invitation_status_code_field_validation(
            $user_login,
            $user_email,
            $errors
        ) {
            $options = get_option("new_user_approve_options");
            $code_optional = apply_filters(
                "nua_invitation_code_optional",
                true
            );
            $nonce = isset($_POST["nua_invitation_code_nonce_field"])
                ? sanitize_text_field(
                    wp_unslash($_POST["nua_invitation_code_nonce_field"])
                )
                : "";
            if (!wp_verify_nonce($nonce, "nua-invitation-code-nonce")) {
                $nonce = "";
            }

            if (
                isset($_POST["nua_invitation_code"]) &&
                !empty($_POST["nua_invitation_code"])
            ) {
                // display the Error on registration form when invitation code has expired or limit exceeded
                $code = sanitize_text_field(
                    wp_unslash($_POST["nua_invitation_code"])
                );
                $is_inv_code_exist = $this->invitation_code_already_exists(
                    $code
                );
                $is_inv_code_limit = $this->invitation_code_limit_check($code);
                $is_inv_expired = $this->invitation_code_expiry_check($code);
                if (true == $is_inv_code_exist && true == $is_inv_expired) {
                    $error_message = apply_filters(
                        "nua_invitation_code_err",
                        __(
                            "<strong>ERROR</strong>: Invitation code has been expired",
                            "new-user-approve"
                        ),
                        "",
                        $errors
                    );
                    $errors->add("invcode_error", $error_message);
                    return $errors;
                } elseif (
                    true == $is_inv_code_exist &&
                    false == $is_inv_code_limit
                ) {
                    $error_message = apply_filters(
                        "nua_invitation_code_err",
                        __(
                            "<strong>ERROR</strong>: Invitation code limit_exceeded",
                            "new-user-approve"
                        ),
                        "",
                        $errors
                    );
                    $errors->add("invcode_error", $error_message);
                    return $errors;
                }

                $args = [
                    "numberposts" => -1,
                    "post_type" => $this->code_post_type,
                    "post_status" => "publish",
                    "meta_query" => [
                        "relation" => "AND",
                        [
                            [
                                "key" => $this->code_key,
                                "value" => sanitize_text_field(
                                    wp_unslash($_POST["nua_invitation_code"])
                                ),
                                "compare" => "=",
                            ],
                            [
                                "key" => $this->usage_limit_key,
                                "value" => "1",
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->expiry_date_key,
                                "value" => time(),
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->status_key,
                                "value" => "Active",
                                "compare" => "=",
                            ],
                        ],
                    ],
                ];
                $posts = get_posts($args);
                $code_inv = "";
                foreach ($posts as $post_inv) {
                    $code_inv = get_post_meta(
                        $post_inv->ID,
                        $this->code_key,
                        true
                    );

                    if ($_POST["nua_invitation_code"] == $code_inv) {
                        global $inv_file_lock;
                        $inv_file_lock = $this->invite_code_hold($post_inv->ID);
                        if ($inv_file_lock === false) {
                            $errors->add(
                                "invcode_error",
                                "<strong>Notice</strong>: Server is busy, please try again!"
                            );
                            return $errors;
                        }

                        return $errors;
                    }
                }

                $error_message = apply_filters(
                    "nua_invitation_code_err",
                    __(
                        "<strong>ERROR</strong>: The Invitation code is invalid",
                        "new-user-approve"
                    ),
                    $code_inv,
                    $errors
                );
                $errors->add("invcode_error", $error_message);
            } elseif (
                !isset($_POST["nua_invitation_code"]) ||
                (isset($_POST["nua_invitation_code"]) &&
                    empty($_POST["nua_invitation_code"]) &&
                    !empty(get_option("nua_free_invitation")) &&
                    true !== $code_optional)
            ) {
                $error_message = apply_filters(
                    "nua_invitation_code_err",
                    __(
                        "<strong>ERROR</strong>: Please add an Invitation code.",
                        "new-user-approve"
                    ),
                    "",
                    $errors
                );
                $errors->add("invcode_error", $error_message);
            }
            return $errors;
        }

        public function nua_woocommerce_invitation_code_validation(
            $username,
            $email,
            $validation_errors
        ) {
            $code_optional = apply_filters(
                "nua_invitation_code_optional",
                true
            );

            $nonce = isset($_POST["nua_invitation_code_nonce_field"])
                ? sanitize_text_field(
                    wp_unslash($_POST["nua_invitation_code_nonce_field"])
                )
                : "";
            if (!wp_verify_nonce($nonce, "nua-invitation-code-nonce")) {
                $nonce = "";
            }

            if (isset($_POST["nua_invitation_code"])) {
                $code = sanitize_text_field(
                    wp_unslash($_POST["nua_invitation_code"])
                );

                if (empty($code)) {
                    // Don't run validation if field is empty and it's optional
                    if (
                        !empty(get_option("nua_free_invitation")) &&
                        true !== $code_optional
                    ) {
                        $error_message = apply_filters(
                            "nua_invitation_code_err",
                            __(
                                "<strong>ERROR</strong>: Please add an Invitation code.",
                                "new-user-approve"
                            ),
                            "",
                            $validation_errors
                        );
                        $validation_errors->add(
                            "invcode_error",
                            $error_message
                        );
                    }
                    return $validation_errors;
                }

                $is_inv_code_exist = $this->invitation_code_already_exists(
                    $code
                );
                $is_inv_code_limit = $this->invitation_code_limit_check($code);
                $is_inv_expired = $this->invitation_code_expiry_check($code);
                if (true == $is_inv_code_exist && true == $is_inv_expired) {
                    $error_message = apply_filters(
                        "nua_invitation_code_err",
                        __(
                            "<strong>ERROR</strong>: Invitation code has been expired",
                            "new-user-approve"
                        ),
                        "",
                        $validation_errors
                    );
                    $validation_errors->add("invcode_error", $error_message);
                    return $validation_errors;
                } elseif (
                    true == $is_inv_code_exist &&
                    false == $is_inv_code_limit
                ) {
                    $error_message = apply_filters(
                        "nua_invitation_code_err",
                        __(
                            "<strong>ERROR</strong>: Invitation code limit_exceeded",
                            "new-user-approve"
                        ),
                        "",
                        $validation_errors
                    );
                    $validation_errors->add("invcode_error", $error_message);
                    return $validation_errors;
                }

                $args = [
                    "numberposts" => -1,
                    "post_type" => $this->code_post_type,
                    "post_status" => "publish",
                    "meta_query" => [
                        "relation" => "AND",
                        [
                            [
                                "key" => $this->code_key,
                                "value" => sanitize_text_field(
                                    wp_unslash($_POST["nua_invitation_code"])
                                ),
                                "compare" => "=",
                            ],
                            [
                                "key" => $this->usage_limit_key,
                                "value" => "1",
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->expiry_date_key,
                                "value" => time(),
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->status_key,
                                "value" => "Active",
                                "compare" => "=",
                            ],
                        ],
                    ],
                ];
                $posts = get_posts($args);
                $code_inv = "";
                foreach ($posts as $post_inv) {
                    $code_inv = get_post_meta(
                        $post_inv->ID,
                        $this->code_key,
                        true
                    );

                    if ($_POST["nua_invitation_code"] == $code_inv) {
                        global $inv_file_lock;
                        $inv_file_lock = $this->invite_code_hold($post_inv->ID);
                        if ($inv_file_lock === false) {
                            $validation_errors->add(
                                "invcode_error",
                                "<strong>Notice</strong>: Server is busy, please try again!"
                            );
                            return $validation_errors;
                        }

                        return $validation_errors;
                    }
                }

                $error_message = apply_filters(
                    "nua_invitation_code_err",
                    __(
                        "<strong>ERROR</strong>: The Invitation code is invalid",
                        "new-user-approve"
                    ),
                    $code_inv,
                    $validation_errors
                );
                $validation_errors->add("invcode_error", $error_message);
            }

            return $validation_errors;
        }

        public function nua_invitation_status_code($status, $user_id)
        {
            $nonce = isset($_POST["nua_invitation_code_nonce_field"])
                ? sanitize_text_field(
                    wp_unslash($_POST["nua_invitation_code_nonce_field"])
                )
                : "";
            if (!wp_verify_nonce($nonce, "nua-invitation-code-nonce")) {
                $nonce = "";
            }

            if (
                isset($_POST["nua_invitation_code"]) &&
                !empty($_POST["nua_invitation_code"])
            ) {
                $args = [
                    "numberposts" => -1,
                    "post_type" => $this->code_post_type,
                    "post_status" => "publish",
                    "meta_query" => [
                        "relation" => "AND",
                        [
                            [
                                "key" => $this->code_key,
                                "value" => sanitize_text_field(
                                    wp_unslash($_POST["nua_invitation_code"])
                                ),
                                "compare" => "=",
                            ],
                            [
                                "key" => $this->usage_limit_key,
                                "value" => "1",
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->expiry_date_key,
                                "value" => time(),
                                "compare" => ">=",
                            ],
                            [
                                "key" => $this->status_key,
                                "value" => "Active",
                                "compare" => "=",
                            ],
                        ],
                    ],
                ];

                $posts = get_posts($args);

                foreach ($posts as $post_inv) {
                    $code_inv = get_post_meta(
                        $post_inv->ID,
                        $this->code_key,
                        true
                    );

                    if (
                        sanitize_text_field(
                            wp_unslash($_POST["nua_invitation_code"])
                        ) == $code_inv
                    ) {
                        $register_user = get_post_meta(
                            $post_inv->ID,
                            $this->registered_users,
                            true
                        );

                        if (empty($register_user)) {
                            update_post_meta(
                                $post_inv->ID,
                                $this->registered_users,
                                [$user_id]
                            );
                        } else {
                            //$unserilize_array = unserialize($register_user);
                            $register_user[] = $user_id;
                            update_post_meta(
                                $post_inv->ID,
                                $this->registered_users,
                                $register_user
                            );
                        }
                        $current_useage = get_post_meta(
                            $post_inv->ID,
                            $this->usage_limit_key,
                            true
                        );
                        --$current_useage;
                        update_post_meta(
                            $post_inv->ID,
                            $this->usage_limit_key,
                            $current_useage
                        );
                        // Release lock
                        global $inv_file_lock;
                        $this->invite_code_release(
                            $inv_file_lock,
                            $post_inv->ID
                        );

                        if ($current_useage == 0) {
                            update_post_meta(
                                $post_inv->ID,
                                $this->status_key,
                                "Expired"
                            );
                        }
                        $status = "approved";
                        pw_new_user_approve()->approve_user($user_id);
                        do_action("nua_invited_user", $user_id, $code_inv);
                        return $status;
                    }
                }
            }
            return $status;
        }

        public function message_above_regform($user_id)
        {
            add_filter(
                "new_user_approve_pending_message",
                [$this, "msg_on_auto_approve_invitation_callback"],
                10,
                1
            );
        }

        public function nua_invite_code_errors()
        {
            if (
                isset($_GET["post_usage_left_error"]) &&
                !empty($_GET["post_usage_left_error"])
            ) {
                $post_id = $_GET["post_usage_left_error"];
                if (get_post($post_id)) {
                    if (
                        isset($_GET["error"]) &&
                        "incorrect_date" == $_GET["error"]
                    ) {
                        wp_delete_post($post_id, true);
                        printf(
                            '<div class="error notice"><p>Error: given date is an incorrect </p></div>',
                            "new-user-approve"
                        );
                        return;
                    } elseif (!isset($_GET["error"])) {
                        wp_delete_post($post_id, true);
                        printf(
                            '<div class="error notice"><p>Error: usage left number must not greater than total limit </p></div>',
                            "new-user-approve"
                        );
                        return;
                    }
                }
            }
        }

        public function msg_on_auto_approve_invitation_callback($message)
        {
            // $opt=pw_new_user_approve_options()->option_key();
            // $id = 'nua_registration_auto_approve_complete_message';
            //require_once ( plugin_dir_path(__FILE__).'/includes/messages.php');
            $message = nua_auto_approve_message();
            $message = nua_do_email_tags($message, [
                "context" => "approved_message",
            ]);
            // $message=pw_new_user_approve_options()->auto_approve_registration_complete_message($message);
            return $message;
        }

        public function invitation_code_columns($columns)
        {
            unset($columns["date"]);
            unset($columns["title"]);
            $columns["inv_code"] = __("Invitation Code", "new-user-approve");
            $columns["usage"] = __("Uses Remaining", "new-user-approve");
            $columns["expiry"] = __("Expiry", "new-user-approve");
            $columns["status"] = __("Status", "new-user-approve");
            $columns["actions"] = __("Actions", "new-user-approve");

            return $columns;
        }

        public function invitation_code_columns_content($column, $post_id)
        {
            switch ($column) {
                case "usage":
                    echo esc_attr(
                        get_post_meta($post_id, $this->usage_limit_key, true) .
                            "/" .
                            get_post_meta($post_id, $this->total_code_key, true)
                    );
                    break;

                case "expiry":
                    $exp_date = get_post_meta(
                        $post_id,
                        $this->expiry_date_key,
                        true
                    );
                    if (!empty($exp_date)) {
                        echo esc_attr(gmdate("Y-m-d", $exp_date));
                    }
                    break;
                case "status":
                    echo esc_attr(
                        get_post_meta($post_id, $this->status_key, true)
                    );
                    break;
                case "inv_code":
                    echo esc_attr(
                        get_post_meta($post_id, $this->code_key, true)
                    );
                    break;
                case "actions":
                    if ("trash" != get_post_status($post_id)) {
                        if (
                            "InActive" ==
                            get_post_meta($post_id, $this->status_key, true)
                        ) {

                            $activation_link = admin_url(
                                "edit.php?post_type=" . $this->code_post_type
                            );
                            $activation_link .=
                                "&nua_action=activate&post_id=" .
                                $post_id .
                                "&nonce=" .
                                wp_create_nonce(
                                    "nua_deactivateactivate-" . $post_id
                                );
                            ?>
								<a href="<?php echo esc_url($activation_link); ?>"><?php esc_html_e(
										"Activate",
										"new-user-approve-options"
									); ?></a> | <a href="<?php echo esc_url(
										get_edit_post_link($post_id)
									); ?>"><?php esc_html_e(
										"Edit",
										"new-user-approve-options"
									); ?></a> | <a href="<?php echo esc_url(
										get_delete_post_link($post_id)
									); ?>"><?php esc_html_e("Delete", "new-user-approve-options"); ?>
								</a>
							<?php
                        } else {

                            $deactivate_link = admin_url(
                                "edit.php?post_type=" . $this->code_post_type
                            );
                            $deactivate_link .=
                                "&nua_action=deactivate&post_id=" .
                                $post_id .
                                "&nonce=" .
                                wp_create_nonce(
                                    "nua_deactivateactivate-" . $post_id
                                );
                            ?>
								<a href="<?php echo esc_url($deactivate_link); ?>"><?php esc_html_e(
									"Deactivate",
									"new-user-approve"
								); ?></a> | <a href="<?php echo esc_url(
									get_edit_post_link($post_id)
								); ?>"><?php esc_html_e(
									"Edit",
									"new-user-approve"
								); ?></a> | <a href="<?php echo esc_url(
									get_delete_post_link($post_id)
								); ?>"><?php esc_html_e("Delete", "new-user-approve"); ?>
								</a>
							<?php
                        }
                    }

                    break;
            }
        }

    } // End Class
}
// phpcs:ignore
function nua_invitation_code()
{
    return NUA_Invitation_Code::instance();
}

nua_invitation_code();
