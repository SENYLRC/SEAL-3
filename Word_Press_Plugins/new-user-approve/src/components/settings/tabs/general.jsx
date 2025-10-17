import React, { useEffect, useState } from "react";
import { get_general_settings } from "../../../functions";
import { update_general_settings } from "../../../functions";
import Select from "react-select";
import { __ } from "@wordpress/i18n";
import { ToastContainer, toast } from "react-toastify";
import ContentCopyIcon from "@mui/icons-material/ContentCopy";
import { Tabs } from "@mui/base/Tabs";
import { TabsList } from "@mui/base/TabsList";
import { TabPanel } from "@mui/base/TabPanel";
import { Tab, tabClasses } from "@mui/base/Tab";
import RegistrationTab from "../tabs/registration";
import Admin_Notification from "../tabs/admin-notification";
import User_Notification from "../tabs/user-notification";
import HelpTab from "../tabs/help";
import {
  BrowserRouter as Router,
  Routes,
  Route,
  useNavigate,
  useLocation,
} from "react-router-dom";
import PopupModal from "../../popup-modal";

const General_Settings = () => {
  const [isPopupVisible, setPopupVisible] = useState(false);

  const navigate = useNavigate();
  const location = useLocation();
  const currentTab = location.pathname.split("/")[2] || "tab=general";

  useEffect(() => {
    if (currentTab == "tab=general") {
      navigate("tab=general");
    }
  }, []);

  const handleTabChange = (event, newTab) => {
    navigate(`${newTab}`);
  };

  const [enable_invitation_code, setEnableInviteCode] = useState(false);

  const [loading, setLoading] = useState(false);

  const HandleTogleChange = (event) => {
    setPopupVisible(true);
  };

  const HandleTogleChangeInvitation = (event) => {
    const { name, checked } = event.target;
    switch (name) {
      case "nua_free_invitation":
        setEnableInviteCode(checked);
        break;
      default:
    }
  };

  const HandleMessageChange = (e) => {
    setPopupVisible(true);
  };

  const handleRestNumber = async () => {
    setPopupVisible(true);
  };

  const handleUserRoleListChange = (roles_selectedList) => {
    setPopupVisible(true);
  };
  const PopupShow = (e) => {
    setPopupVisible(true);
  };

  const handleAdminAddressChange = (event) => {
    setPopupVisible(true);
  };

  const handleSaveChange = async (event) => {
    const generalSettings = {
      nua_settings_tab: "general",
      nua_free_invitation: enable_invitation_code,
    };

    try {
      setLoading(true);

      // Save general settings
      const generalResponse = await update_general_settings({
        generalSettings,
      });

      if (generalResponse?.data?.status === "success") {
        toast.success(__("Settings saved successfully!", "new-user-approve"), {
          position: "bottom-right",
          autoClose: 2000,
          hideProgressBar: false,
          closeOnClick: true,
          pauseOnHover: true,
          draggable: true,
          progress: undefined,
        });
      } else {
        toast.error(
          __("Something went wrong while saving.", "new-user-approve")
        );
      }
    } catch (error) {
      console.error("Error while updating settings", error);
      toast.error(
        __("Error saving settings. Please try again.", "new-user-approve")
      );
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const general_settings = async () => {
      const response = await get_general_settings();
      setEnableInviteCode(response.data.data.nua_free_invitation);
    };
    general_settings();
  }, []);

  const now = new Date();
  const localISOTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
    .toISOString()
    .slice(0, 16);

  let proIcon = (
    <svg
      className="proicon"
      width="33"
      height="16"
      viewBox="0 0 33 16"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect width="33" height="16" rx="4" fill="#FFBF46" />
      <path
        d="M8.608 11V5.4H10.688C11.0827 5.4 11.432 5.48 11.736 5.64C12.04 5.79467 12.2773 6.01333 12.448 6.296C12.6187 6.57333 12.704 6.896 12.704 7.264C12.704 7.62667 12.6213 7.94933 12.456 8.232C12.2907 8.51467 12.064 8.73867 11.776 8.904C11.488 9.064 11.1547 9.144 10.776 9.144H9.704V11H8.608ZM9.704 8.136H10.752C10.9973 8.136 11.1973 8.056 11.352 7.896C11.512 7.73067 11.592 7.52 11.592 7.264C11.592 7.008 11.504 6.8 11.328 6.64C11.1573 6.48 10.936 6.4 10.664 6.4H9.704V8.136ZM13.4283 11V5.4H15.5083C15.903 5.4 16.2523 5.47733 16.5563 5.632C16.8603 5.78667 17.0976 6 17.2683 6.272C17.439 6.53867 17.5243 6.85067 17.5243 7.208C17.5243 7.56 17.4336 7.87467 17.2523 8.152C17.0763 8.424 16.8336 8.63733 16.5243 8.792C16.215 8.94133 15.863 9.016 15.4683 9.016H14.5243V11H13.4283ZM16.5083 11L15.2123 8.752L16.0523 8.152L17.7483 11H16.5083ZM14.5243 8.016H15.5163C15.6816 8.016 15.8283 7.98133 15.9563 7.912C16.0896 7.84267 16.1936 7.74667 16.2683 7.624C16.3483 7.50133 16.3883 7.36267 16.3883 7.208C16.3883 6.968 16.3003 6.77333 16.1243 6.624C15.9536 6.47467 15.7323 6.4 15.4603 6.4H14.5243V8.016ZM21.1446 11.096C20.5792 11.096 20.0779 10.9733 19.6406 10.728C19.2086 10.4773 18.8699 10.136 18.6246 9.704C18.3792 9.26667 18.2566 8.768 18.2566 8.208C18.2566 7.63733 18.3792 7.136 18.6246 6.704C18.8699 6.26667 19.2059 5.92533 19.6326 5.68C20.0646 5.43467 20.5606 5.312 21.1206 5.312C21.6859 5.312 22.1819 5.43733 22.6086 5.688C23.0406 5.93333 23.3792 6.27467 23.6246 6.712C23.8699 7.144 23.9926 7.64267 23.9926 8.208C23.9926 8.768 23.8699 9.26667 23.6246 9.704C23.3846 10.136 23.0486 10.4773 22.6166 10.728C22.1899 10.9733 21.6992 11.096 21.1446 11.096ZM21.1446 10.096C21.4859 10.096 21.7846 10.016 22.0406 9.856C22.3019 9.69067 22.5046 9.46667 22.6486 9.184C22.7979 8.90133 22.8726 8.576 22.8726 8.208C22.8726 7.83467 22.7979 7.50667 22.6486 7.224C22.4992 6.94133 22.2939 6.72 22.0326 6.56C21.7712 6.39467 21.4672 6.312 21.1206 6.312C20.7846 6.312 20.4832 6.39467 20.2166 6.56C19.9552 6.72 19.7499 6.94133 19.6006 7.224C19.4512 7.50667 19.3766 7.83467 19.3766 8.208C19.3766 8.576 19.4512 8.90133 19.6006 9.184C19.7499 9.46667 19.9579 9.69067 20.2246 9.856C20.4912 10.016 20.7979 10.096 21.1446 10.096Z"
        fill="#664C1C"
      />
    </svg>
  );

  return (
    <React.Fragment>
      <Tabs
        className="nua_notification_sub_tabs"
        defaultValue={"tab=general"}
        value={currentTab}
        onChange={handleTabChange}
      >
        <TabsList className="nua_notification_sub_tabstablist">
          <Tab
            value={"tab=general"}
            className={currentTab == "tab=general" ? "nua_active_subtab" : ""}
          >
            {__("General", "new-user-approve")}
          </Tab>
          <Tab
            value={"tab=registration"}
            className={
              currentTab == "tab=registration" ? "nua_active_subtab" : ""
            }
          >
            {__("Registration Notification", "new-user-approve")}
          </Tab>
          <Tab
            value={"tab=admin_notification"}
            className={
              currentTab == "tab=admin_notification" ? "nua_active_subtab" : ""
            }
          >
            {__("Admin Notification", "new-user-approve")}
          </Tab>
          <Tab
            value={"tab=user_notification"}
            className={
              currentTab == "tab=user_notification" ? "nua_active_subtab" : ""
            }
          >
            {__("User Notification", "new-user-approve")}
          </Tab>
          <Tab
            value={"tab=help"}
            className={currentTab == "tab=help" ? "nua_active_subtab" : ""}
          >
            {__("Help", "new-user-approve")}
          </Tab>
        </TabsList>
        <Routes>
          <Route
            path="tab=general"
            element={
              <TabPanel
                className="dash-tabPanel"
                value={"tab=general"}
                index="general"
              >
                <div
                  style={{ paddingBottom: "0px" }}
                  className="nua_setting_tab nua_main_settings"
                >
                  <div className="nua_inner_settings">
                    <h2>
                      {__("Invitation Code Settings", "new-user-approve")}
                    </h2>

                    {/* Enable Invitation Code */}
                    <div className="nua-setting-row nua-inv-code">
                      <div className="nua-setting-label">
                        <span>
                          {__("Enable Invitation Code", "new-user-approve")}
                        </span>

                        <span className="nua-tooltip-wrapper">
                          <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                          <div className="nua-tooltip-text">
                            {__(
                              "Invitation Code for user to register without admin approval.",
                              "new-user-approve"
                            )}
                          </div>
                        </span>

                        <span></span>
                      </div>

                      <div className="nua-setting-control">
                        <label
                          className="nua_switch"
                          htmlFor="nua_free_invitation"
                        >
                          <input
                            id="nua_free_invitation"
                            name="nua_free_invitation"
                            type="checkbox"
                            checked={enable_invitation_code}
                            onChange={HandleTogleChangeInvitation}
                          />
                          <span className="nua_slider round"></span>
                        </label>
                      </div>
                    </div>

                    {/* Make Invitation Code Required */}
                    <div className="nua-setting-row">
                      <div className="nua-setting-label">
                        <span className="nua-setting-pro">
                          {__(
                            "Make Invitation code Required",
                            "new-user-approve"
                          )}
                        </span>
                        <span>{proIcon}</span>
                        <span className="nua-tooltip-wrapper nua-setting-pro">
                          <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                          <div className="nua-tooltip-text">
                            {__(
                              "The Invitation code field on the registration page will be required if the checkbox is checked.",
                              "new-user-approve"
                            )}
                          </div>
                        </span>
                      </div>

                      <div className="nua-setting-control nua-setting-pro">
                        <label
                          className="nua_switch"
                          htmlFor="nua_make_invitation_code_required"
                        >
                          <input
                            id="nua_make_invitation_code_required"
                            name="nua_make_invitation_code_required"
                            type="checkbox"
                            checked={""}
                            onChange={HandleTogleChange}
                          />
                          <span className="nua_slider round"></span>
                        </label>
                      </div>
                    </div>
                  </div>

                  <hr />

                  <div className="nua_inner_settings">
                    <h2>
                      {__("Dashboard Settings", "new-user-approve")}
                      {proIcon}
                    </h2>

                    <div className="nua-setting-row nua-setting-pro">
                      <div className="nua-setting-label">
                        <span>{__("Dashboard Stats", "new-user-approve")}</span>

                        <span className="nua-tooltip-wrapper">
                          <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                          <div className="nua-tooltip-text">
                            {__(
                              "Enable this to show plugin's stats on the admin dashboard.",
                              "new-user-approve"
                            )}
                            <span className="nua-tooltip-arrow"></span>
                          </div>
                        </span>

                        <span></span>
                      </div>

                      <div className="nua-setting-control">
                        <label
                          className="nua_switch"
                          htmlFor="nua_hide_dashboard_stats"
                        >
                          <input
                            id="nua_hide_dashboard_stats"
                            name="nua_hide_dashboard_stats"
                            type="checkbox"
                            checked={""}
                            onChange={HandleTogleChange}
                          />
                          <span className="nua_slider round"></span>
                        </label>
                      </div>
                    </div>

                    <hr />
                  </div>

                  <div className="nua_inner_settings">
                    <h2>
                      {__("Password & Security Settings", "new-user-approve")}
                      {proIcon}
                    </h2>

                    <div className="nua-setting-row nua-setting-pro">
                      <div className="nua-setting-label">
                        <span>
                          {__("Bypass password reset", "new-user-approve")}
                        </span>

                        <span className="nua-tooltip-wrapper">
                          <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                          <div className="nua-tooltip-text">
                            {__(
                              "Don't reset the original password after approving a user. This is useful if you are allowing a user to set their password at registration.",
                              "new-user-approve"
                            )}
                            <span className="nua-tooltip-arrow"></span>
                          </div>
                        </span>

                        <span></span>
                      </div>

                      <div className="nua-setting-control">
                        <label
                          className="nua_switch"
                          htmlFor="nua_bypass_password_reset"
                        >
                          <input
                            id="nua_bypass_password_reset"
                            name="nua_bypass_password_reset"
                            type="checkbox"
                            checked={""}
                            onChange={HandleTogleChange}
                          />
                          <span className="nua_slider round"></span>
                        </label>
                      </div>
                    </div>
                  </div>

                  <hr />

                  <div className="nua_inner_settings">
                    <h2>
                      {__("Approval Settings", "new-user-approve")}
                      {proIcon}
                    </h2>

                    {/* Enable Auto-Approve */}
                    <div className="nua-setting-row nua-setting-pro">
                      <div className="nua-setting-label">
                        <span>
                          {__("Enable Auto-Approve", "new-user-approve")}
                        </span>

                        <span className="nua-tooltip-wrapper">
                          <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                          <div className="nua-tooltip-text">
                            {__(
                              "Enable Auto-Approve functionality.",
                              "new-user-approve"
                            )}
                          </div>
                        </span>

                        <span></span>
                      </div>

                      <div className="nua-setting-control">
                        <label
                          className="nua_switch"
                          htmlFor="nua_enable_auto_approve"
                        >
                          <input
                            id="nua_enable_auto_approve"
                            name="nua_enable_auto_approve"
                            type="checkbox"
                            checked={""}
                            onChange={HandleTogleChange}
                          />
                          <span className="nua_slider round"></span>
                        </label>
                      </div>
                    </div>

                    {/* Enable User Role Request */}
                    <div className="nua-setting-row nua-setting-pro">
                      <div className="nua-setting-label">
                        <span>
                          {__(
                            "Select User Role Request ( Registration )",
                            "new-user-approve"
                          )}
                        </span>

                        <span className="nua-tooltip-wrapper">
                          <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                          <div className="nua-tooltip-text">
                            {__(
                              "Enable this option to show user role request on registeration page.",
                              "new-user-approve"
                            )}
                          </div>
                        </span>

                        <span></span>
                      </div>

                      <div className="nua-setting-control">
                        <label
                          className="nua_switch"
                          htmlFor="nua_enable_user_role_request"
                        >
                          <input
                            id="nua_enable_user_role_request"
                            name="nua_enable_user_role_request"
                            type="checkbox"
                            checked={""}
                            onChange={HandleTogleChange}
                          />
                          <span className="nua_slider round"></span>
                        </label>
                      </div>
                    </div>

                    {/* Auto Approval for Specific User Roles */}
                    <div className="nua-setting-row nua-setting-pro">
                      <div className="nua-setting-label">
                        <span>
                          {__(
                            "Auto Approval for Specific User Roles",
                            "new-user-approve"
                          )}
                        </span>

                        <span className="nua-tooltip-wrapper">
                          <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                          <div className="nua-tooltip-text">
                            {__(
                              "Select roles to be auto-approve.",
                              "new-user-approve"
                            )}
                          </div>
                        </span>

                        <span></span>
                      </div>

                      <div className="nua-setting-control">
                        <div
                          className="row d-flex justify-content mt-100 invite-email-select"
                          id="roles_chooser"
                        >
                          <div className="col-md-3 nua_user_roles">
                            <Select
                              isDisabled
                              isMulti
                              classNamePrefix="Select Roles"
                              onChange={handleUserRoleListChange}
                              placeholder="Select Roles"
                            />
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <hr />
                  <div className="nua_inner_settings">
                    <h2>
                      {__("Role Management Settings", "new-user-approve")}
                      {proIcon}
                    </h2>

                    {/* Allow Role Change Request */}
                    <div className="nua-setting-row nua-setting-pro">
                      <div className="nua-setting-label">
                        <span style={{ maxWidth: "200px" }}>
                          {__(
                            "Allow User Role Change Request ( My-account )",
                            "new-user-approve"
                          )}
                        </span>

                        <span className="nua-tooltip-wrapper">
                          <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                          <div className="nua-tooltip-text">
                            {__(
                              "Enable this option to allow user to upgrade the request on my-account page.",
                              "new-user-approve"
                            )}
                          </div>
                        </span>

                        <span></span>
                      </div>

                      <div className="nua-setting-control">
                        <label
                          className="nua_switch"
                          htmlFor="nua_allow_user_role_change_request"
                        >
                          <input
                            id="nua_allow_user_role_change_request"
                            name="nua_allow_user_role_change_request"
                            type="checkbox"
                            checked={""}
                            onChange={HandleTogleChange}
                          />
                          <span className="nua_slider round"></span>
                        </label>
                      </div>
                    </div>

                    {/* Shortcode Display */}
                    <div className="nua-setting-row nua-setting-pro">
                      <div className="nua-setting-label">
                        <span>{__("Shortcode", "new-user-approve")}</span>
                        <span></span>
                      </div>

                      <div className="nua-setting-control">
                        <p id="nua-to-copy-shortcode" onClick={PopupShow}>
                          [nua_user_role_request]
                          <ContentCopyIcon title="Copy" id="nua-copy-icon" />
                        </p>
                      </div>
                    </div>

                    {/* Copied to clipboard text */}
                    <span id="nua_copied_text">
                      <span
                        id="dashicons-shortcode"
                        className="dashicons dashicons-shortcode"
                      ></span>
                      &nbsp;{__("Copied to clipboard", "new-user-approve")}
                    </span>
                  </div>

                  <hr />
                  <div className="nua_inner_settings">
                    <h2>
                      {__(
                        "Registration Management Settings",
                        "new-user-approve"
                      )}
                      {proIcon}
                    </h2>

                    <div className="nua-setting-row nua-setting-pro">
                      {/* Left: Label + Tooltip */}
                      <div className="nua-setting-label">
                        <span>
                          {__("Registration Deadline", "new-user-approve")}
                        </span>
                        <span className="nua-tooltip-wrapper">
                          <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                          <div className="nua-tooltip-text">
                            {__(
                              "Set deadline for registration. This will close the registration after deadline.",
                              "new-user-approve"
                            )}
                          </div>
                        </span>
                        <span></span> {/* Added span after as per request */}
                      </div>

                      {/* Right: Checkbox */}
                      <div className="nua-setting-control">
                        <label
                          className="nua_switch"
                          htmlFor="nua_registration_deadline"
                        >
                          <input
                            id="nua_registration_deadline"
                            name="nua_registration_deadline"
                            type="checkbox"
                            checked={""}
                            onChange={HandleTogleChange}
                          />
                          <span className="nua_slider round"></span>
                        </label>
                      </div>
                    </div>
                  </div>

                  <hr />

                  <div className="nua_inner_settings">
                    <h2>
                      {__("Administrator Settings", "new-user-approve")}
                      {proIcon}
                    </h2>

                    <div className="nua-setting-row nua-setting-pro">
                      <div className="nua-setting-label">
                        <span>
                          {__(
                            "Administrator Email Address",
                            "new-user-approve"
                          )}
                        </span>

                        <span className="nua-tooltip-wrapper">
                          <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                          <div className="nua-tooltip-text">
                            {__(
                              "Change the admin/sender Email.",
                              "new-user-approve"
                            )}
                          </div>
                        </span>
                      </div>

                      {/* Right: Input field */}
                      <div className="nua-setting-control">
                        <input
                          id="nua_admin_email_address"
                          name="nua_admin_email_address"
                          type="text"
                          className="auto-code-field"
                          placeholder="example@email.com"
                          value={""}
                          onClick={PopupShow}
                          onChange={handleAdminAddressChange}
                        />
                      </div>
                    </div>
                  </div>

                  {/* setting save button */}
                  <div
                    className="setting-save-btn setting-option"
                    style={{ marginBottom: "0px" }}
                  >
                    <button
                      className={`nua-btn save-changes ${
                        loading ? "loading" : ""
                      }`}
                      onClick={handleSaveChange}
                    >
                      {__("Save Changes", "new-user-approve")}
                      {loading == true ? (
                        <div className="new-user-approve-loading">
                          <div className="nua-spinner"></div>
                        </div>
                      ) : (
                        ""
                      )}
                    </button>
                  </div>
                </div>
              </TabPanel>
            }
          />
          <Route
            path="tab=registration"
            element={
              <TabPanel
                className="dash-tabPanel"
                value={"tab=registration"}
                index="registration"
              >
                <RegistrationTab />
              </TabPanel>
            }
          />
          <Route
            path="tab=admin_notification"
            element={
              <TabPanel
                className="dash-tabPanel"
                value={"tab=admin_notification"}
                index="admin_notification"
              >
                <Admin_Notification />
              </TabPanel>
            }
          />
          <Route
            path="tab=user_notification"
            element={
              <TabPanel
                className="dash-tabPanel"
                value={"tab=user_notification"}
                index="user_notification"
              >
                <User_Notification />
              </TabPanel>
            }
          />
          <Route
            path="tab=help"
            element={
              <TabPanel
                className="dash-tabPanel"
                value={"tab=help"}
                index="help"
              >
                <HelpTab />
              </TabPanel>
            }
          />
        </Routes>
      </Tabs>
      <ToastContainer />
      <PopupModal
        isVisible={isPopupVisible}
        onClose={() => setPopupVisible(false)}
      />
    </React.Fragment>
  );
};

export default General_Settings;
