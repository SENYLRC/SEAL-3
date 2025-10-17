import React, { useState, useEffect } from "react";
import { get_admin_notification_settings } from "../../../functions";
import { update_admin_notification_settings } from "../../../functions";
import { sprintf, __ } from "@wordpress/i18n";
import Select from "react-select";
import WPEditor from "../../wp-editor/WPEditor";
import { format_selected_values } from "../../../functions";
import { width } from "@mui/system";
import { toast } from "react-toastify";
import PopupModal from "../../popup-modal";
const Admin_Notification = () => {
  const [isPopupVisible, setPopupVisible] = useState(false);

  const [tags_list, setTagsList] = useState([]);
  const [showTags, setShowTags] = useState(false);

  // loading
  const [loading, setLoading] = useState(false);

  useEffect(() => {}, []);

  const HandleTogleChange = (event) => {
    setPopupVisible(true);
  };

  const handleEditorChange = ({ editorName, editorContent }) => {
    // setAdminNotificationEmail(editorContent);
  };
  const handleAdminEmailUsers = (selected_users) => {};
  const HandleMessageChange = (event) => {};

  const handleSaveChange = async (event) => {
    setPopupVisible(true);
  };
  let arrowIcon = (
    <svg
      width="14"
      height="8"
      viewBox="0 0 14 8"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M6.28886 7.15694L0.631863 1.49994L2.04586 0.0859376L6.99586 5.03594L11.9459 0.0859375L13.3599 1.49994L7.70286 7.15694C7.51534 7.34441 7.26103 7.44972 6.99586 7.44972C6.7307 7.44972 6.47639 7.34441 6.28886 7.15694Z"
        fill="#618E5F"
      />
    </svg>
  );

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

  const renderEditorBlocker = () => (
    <div
      className="nua-editor-overlay"
      onClick={() => setPopupVisible(true)}
      onFocus={() => setPopupVisible(true)}
      onMouseDown={() => setPopupVisible(true)}
      tabIndex={0}
    />
  );

  return (
    <React.Fragment>
      <div
        className="registration_settings nua_main_settings"
        style={{ paddingBottom: "0px" }}
      >
        <div className="nua_inner_settings">
          <div id="registration_heading">
            <h3>
              {__("Admin Notification Emails", "new-user-approve")}
              {proIcon}
            </h3>
            <p>
              {__(
                "Notification emails are sent to the site admin when a user needs to be updated.",
                "new-user-approve"
              )}
            </p>
          </div>
        </div>
        <hr />
        <div className="nua_inner_settings nua-setting-pro">
          <div className="setting-section admin-notification-option-section admin-option-section">
            <h2>{__("Notification Option", "new-user-approve")}</h2>

            <div className="admin_settings nua_settings">
              <div className="send_to_all_admin setting-option">
                <span className="setting-label send-to-all-admin-label">
                  {__(
                    "Send notification emails to all admins",
                    "new-user-approve"
                  )}
                  <span className="nua-tooltip-wrapper">
                    <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                    <div className="nua-tooltip-text">
                      {__(
                        "By default, only the site admin will be notified when a user is awaiting approval. Checking this option will send the notification to all users with admin access.",
                        "new-user-approve"
                      )}
                    </div>
                  </span>
                </span>

                <div className="send-to-all-admin-element setting-element">
                  <label className="nua_switch" htmlFor="nua_send_to_all_admin">
                    <input
                      id="nua_send_to_all_admin"
                      name="nua_send_to_all_admin"
                      type="checkbox"
                      checked={""}
                      onChange={HandleTogleChange}
                    />
                    <span className="nua_slider round"></span>
                  </label>
                </div>
              </div>

              <div className="status_updated_notification setting-option">
                <span className="setting-label status-updated-notification-label">
                  {__(
                    `Notify admins when a user's status is updated`,
                    "new-user-approve"
                  )}
                  <span className="nua-tooltip-wrapper">
                    <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                    <div className="nua-tooltip-text">
                      {__(
                        "Useful for when there is more than one site admin.",
                        "new-user-approve"
                      )}
                    </div>
                  </span>
                </span>

                <div className="status-updated-notification-element setting-element">
                  <label
                    className="nua_switch"
                    htmlFor="nua_status_updated_notification"
                  >
                    <input
                      id="nua_status_updated_notification"
                      name="nua_status_updated_notification"
                      type="checkbox"
                      checked={""}
                      onChange={HandleTogleChange}
                    />
                    <span className="nua_slider round"></span>
                  </label>
                </div>
              </div>

              <div className="stop_send_to_site_admin setting-option">
                <span className="setting-label stop-send-to-site-admin-label">
                  {__(
                    `Don't send notification emails to current site admin`,
                    "new-user-approve"
                  )}
                  <span className="nua-tooltip-wrapper">
                    <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                    <div className="nua-tooltip-text">
                      {__("", "new-user-approve")}
                    </div>
                  </span>
                </span>

                <div className="stop-send-to-site-admin-element setting-element">
                  <label
                    className="nua_switch"
                    htmlFor="nua_stop_send_to_site_admin"
                  >
                    <input
                      id="nua_stop_send_to_site_admin"
                      name="nua_stop_send_to_site_admin"
                      type="checkbox"
                      checked={""}
                      onChange={HandleTogleChange}
                    />
                    <span className="nua_slider round"></span>
                  </label>
                </div>
              </div>

              <div className="admin_email_specificUsers setting-option">
                <span className="setting-label admin-email-specificUsers-label">
                  {__(
                    "Send Email Notification to other than admins",
                    "new-user-approve"
                  )}
                  <span className="nua-tooltip-wrapper">
                    <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                    <div className="nua-tooltip-text">
                      {__(
                        "Send admin notification emails to specific users.",
                        "new-user-approve"
                      )}
                    </div>
                  </span>
                </span>

                <div className="admin-email-specificUsers-element setting-element">
                  <label
                    className="nua_switch"
                    htmlFor="nua_admin_email_specificUsers"
                  >
                    <input
                      id="nua_admin_email_specificUsers"
                      name="nua_admin_email_specificUsers"
                      type="checkbox"
                      checked={""}
                      onChange={HandleTogleChange}
                    />
                    <span className="nua_slider round"></span>
                  </label>
                </div>
              </div>

              {"" == true ? (
                <div className="admin_email_specificUsers setting-option">
                  <span className="setting-label admin-email-specificUsers-label">
                    {" "}
                  </span>
                  <div className="admin-email-specificUsers-element setting-element">
                    <div className="user_email_specific">
                      <Select
                        isMulti
                        name="nua_admin_email_specificEmail"
                        className="nua_admin_email_specificEmail invite-email-select"
                        placeholder="Select users"
                        sx={{ width: 100 }}
                        // options={
                        //     admin_email_users.map((user) => (

                        //         {value :user.user_email, label: user.username + ' | ' + user.user_email  }
                        //     ))
                        // }
                        onChange={handleAdminEmailUsers}
                        value={""}
                        classNamePrefix=""
                      />
                    </div>
                  </div>
                </div>
              ) : (
                ""
              )}
            </div>
          </div>
        </div>
        <hr />
        <div className="nua_inner_settings nua-setting-pro">
          <div className="setting-section admin-notification-email-section admin-email-section">
            <h2>{__("Notification Emails", "new-user-approve")}</h2>

            <div className="admin_settings nua_settings">
              <div className="notification_admin_email_subject setting-option">
                <span className="setting-label notification-admin-email-subject-label">
                  {" "}
                  {__("Notification subject", "new-user-approve")}{" "}
                </span>
                <div className="notification-admin-email-subject-element setting-element">
                  <input
                    type="text"
                    size={40}
                    onFocus={setPopupVisible}
                    name="nua_notification_admin_email_subject"
                    className="auto-code-field"
                    value={""}
                    onChange={HandleMessageChange}
                  />
                </div>
              </div>

              <div className="admin_notification_email setting-option">
                <span className="setting-label admin-notification-email-label">
                  {" "}
                  {__("Notification Message", "new-user-approve")}{" "}
                </span>
                <div
                  className="admin-notification-email-element nua-editor-element setting-element"
                  style={{ position: "relative" }}
                >
                  <WPEditor
                    editorId="admin-notification-email"
                    editorName="nua-admin-notification-email"
                    onChange={handleEditorChange}
                    editorContent={""}
                  />
                  {renderEditorBlocker()}
                  <p className="description">
                    {" "}
                    {__(
                      "This message is sent to the site admin when a user registers for the site. Customizations can be made to the message above using the following email tags:",
                      "new-user-approve"
                    )}
                  </p>

                  {showTags &&
                    tags_list &&
                    Object.keys(tags_list).length > 0 && (
                      <div className="nua-tags-wrapper">
                        {Object.entries(tags_list).map((item, index) => (
                          <div key={index} className="nua-tag-item">
                            <a className="nua-tags">{item[0]}</a> - {item[1]}
                          </div>
                        ))}
                      </div>
                    )}
                </div>
              </div>

              <div
                className="send_email_as_html setting-option"
                style={{ marginTop: "0px" }}
              >
                <span className="setting-label admin-notification-email-label">
                  {" "}
                </span>
                <div className="send-email-as-html-element setting-element">
                  <label
                    className="nua_switch"
                    htmlFor="nua_send_email_as_html"
                  >
                    <input
                      id="nua_send_email_as_html"
                      name="nua_send_email_as_html"
                      type="checkbox"
                      checked={""}
                      onChange={HandleTogleChange}
                    />
                    <span className="nua_slider round"></span>
                  </label>
                  <p className="description">
                    {" "}
                    {__(
                      "Send notification message as html.",
                      "new-user-approve"
                    )}{" "}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div className="setting-save-btn setting-option">
          <button
            className={`nua-btn save-changes ${loading ? "loading" : ""}`}
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
      <PopupModal
        isVisible={isPopupVisible}
        onClose={() => setPopupVisible(false)}
      />
    </React.Fragment>
  );
};

export default Admin_Notification;
