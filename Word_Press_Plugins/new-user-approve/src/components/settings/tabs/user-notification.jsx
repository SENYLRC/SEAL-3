import React, { useState, useEffect } from "react";
import { sprintf, __ } from "@wordpress/i18n";
import WPEditor from "../../wp-editor/WPEditor";
import PopupModal from "../../popup-modal";

const User_Notification = () => {
  const [isPopupVisible, setPopupVisible] = useState(false);
  // tags
  const [tags_list, setTagsList] = useState([]);
  const [showTags, setShowTags] = useState(false);

  // loading
  const [loading, setLoading] = useState(false);

  useEffect(() => {}, []);
  // for email message
  const handleEditorChange = ({ editorName, editorContent }) => {};

  const HandleTogleChange = (event) => {
    setPopupVisible(true);
  };
  // for email subjects
  const HandleMessageChange = (event) => {};

  const handleSaveChange = async (event) => {
    setPopupVisible(true);
  };

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
        className="registration_settings nua_main_settings nua-users-settings"
        style={{ paddingBottom: "0px" }}
      >
        <div className="nua_inner_settings">
          <div id="registration_heading">
            <h3>
              {" "}
              {__("User Notification Emails", "new-user-approve")}
              {proIcon}
            </h3>
            <p>
              {" "}
              {__(
                "Users will receive notification emails when their status has been updated by a site admin.",
                "new-user-approve"
              )}{" "}
            </p>
          </div>
        </div>
        <hr />
        <div className="nua_inner_settings nua-setting-pro">
          <div className="setting-section approve-notification-section user-approve-section">
            <h2> {__("Approve Notification", "new-user-approve")}</h2>

            <div className="user_settings nua_settings">
              <div className="approve_notification_email_subject setting-option">
                <span className="setting-label approve-notification-email-subject-label">
                  {" "}
                  {__("Notification subject", "new-user-approve")}{" "}
                </span>
                <div className="approve-notification-email-subject-element setting-element">
                  <input
                    type="text"
                    size={40}
                    onFocus={setPopupVisible}
                    name="approve_notification_email_subject"
                    className="auto-code-field"
                    value={""}
                    onChange={HandleMessageChange}
                  />
                </div>
              </div>

              <div className="approve_notification_message setting-option">
                <span className="setting-label approve-notification-message-label">
                  {" "}
                  {__("Approve Notification Message", "new-user-approve")}{" "}
                </span>
                <div
                  className="approve-notification-message-element nua-editor-element setting-element"
                  style={{ position: "relative" }}
                >
                  <WPEditor
                    editorId="approve-notification-message"
                    editorName="nua-approve-notification-message"
                    onChange={handleEditorChange}
                    editorContent={""}
                  />
                  {renderEditorBlocker()}
                  <p className="description">
                    {" "}
                    {__(
                      "This message is sent to users once they are approved. Customizations can be made to the message above using the following email tags:",
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
                      name="nua_approve_send_email_as_html"
                      type="checkbox"
                      checked={""}
                      onChange={HandleTogleChange}
                    />
                    <span className="nua_slider round"></span>
                  </label>
                  <p className="description">
                    {" "}
                    {__(
                      "Send notification message as html",
                      "new-user-approve"
                    )}{" "}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
        <hr />
        <div className="nua_inner_settings nua-setting-pro">
          <div className="setting-section deny-notification-section user-deny-section">
            <h2> {__("Deny Notification", "new-user-approve")}</h2>

            <div className="user_settings nua_settings">
              <div className="deny-stop-notification-email setting-option">
                <span className="setting-label deny-stop-notification-email-label">
                  {__("Suppress denial message", "new-user-approve")}
                  <span className="nua-tooltip-wrapper">
                    <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                    <div className="nua-tooltip-text">
                      {__(
                        `Don't send the denial message to denied users.`,
                        "new-user-approve"
                      )}
                    </div>
                  </span>
                </span>

                <div className="deny-stop-notification-email-element setting-element">
                  <label
                    className="nua_switch"
                    htmlFor="nua_deny_stop_notification_email"
                  >
                    <input
                      id="nua_deny_stop_notification_email"
                      name="nua_deny_stop_notification_email"
                      type="checkbox"
                      checked={""}
                      onChange={HandleTogleChange}
                    />
                    <span className="nua_slider round"></span>
                  </label>
                </div>
              </div>

              <div className="deny_notification_email_subject setting-option">
                <span className="setting-label deny-notification-email-subject-subject-label">
                  {" "}
                  {__("Deny notification subject", "new-user-approve")}{" "}
                </span>
                <div className="deny-notification-email-subject-element setting-element">
                  <input
                    type="text"
                    onFocus={setPopupVisible}
                    size={40}
                    name="nua_deny_notification_email_subject"
                    className="auto-code-field"
                    value={""}
                    onChange={HandleMessageChange}
                  />
                </div>
              </div>

              <div className="deny_notification_message setting-option">
                <span className="setting-label deny-notification-message-label">
                  {" "}
                  {__("Deny Notification Message", "new-user-approve")}{" "}
                </span>
                <div
                  className="deny-notification-message-element nua-editor-element setting-element"
                  style={{ position: "relative" }}
                >
                  <WPEditor
                    editorId="deny-notification-message"
                    editorName="nua-deny-notification-message"
                    onChange={handleEditorChange}
                    editorContent={""}
                  />
                  {renderEditorBlocker()}
                  <p className="description">
                    {__(
                      "This message is sent to users once they are denied. Customizations can be made to the message above using the following email tags:",
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
                className="deny_send_email_as_html setting-option"
                style={{ marginTop: "0px" }}
              >
                <span className="setting-label deny-notification-email-label">
                  {" "}
                </span>
                <div className="deny-send-email-as-html-element setting-element">
                  <label
                    className="nua_switch"
                    htmlFor="nua_deny_send_email_as_html"
                  >
                    <input
                      id="nua_deny_send_email_as_html"
                      name="nua_deny_send_email_as_html"
                      type="checkbox"
                      checked={""}
                      onChange={HandleTogleChange}
                    />
                    <span className="nua_slider round"></span>
                  </label>
                  <p className="description" style={{ marginTop: "0px" }}>
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
        <hr />
        <div className="nua_inner_settings nua-setting-pro">
          <div className="setting-section welcome-notification-section user-welcome-section">
            <h2>{__("Welcome Notification", "new-user-approve")}</h2>

            <div className="user_settings nua_settings">
              <div className="welcome-stop-notification-email setting-option">
                <span className="setting-label welcome-stop-notification-email-label">
                  {__("User Welcome Email.", "new-user-approve")}
                  <span className="nua-tooltip-wrapper">
                    <span className="dashicons dashicons-editor-help nua-tooltip-icon"></span>
                    <div className="nua-tooltip-text">
                      {__(
                        "Send welcome email to users after registration.",
                        "new-user-approve"
                      )}
                    </div>
                  </span>
                </span>

                <div className="welcome-stop-notification-email-element setting-element">
                  <label
                    className="nua_switch"
                    htmlFor="nua_welcome_stop_notification_email"
                  >
                    <input
                      id="nua_welcome_stop_notification_email"
                      name="nua_welcome_stop_notification_email"
                      type="checkbox"
                      checked={""}
                      onChange={HandleTogleChange}
                    />
                    <span className="nua_slider round"></span>
                  </label>
                </div>
              </div>

              <div className="welcome_notification_email_subject setting-option">
                <span className="setting-label welcome-notification-email-subject-subject-label">
                  {" "}
                  {__("User Welcome Email Subject", "new-user-approve")}{" "}
                </span>
                <div className="welcome-notification-email-subject-element setting-element">
                  <input
                    type="text"
                    size={40}
                    onFocus={setPopupVisible}
                    name="nua_welcome_notification_email_subject"
                    className="auto-code-field"
                    value={""}
                    onChange={HandleMessageChange}
                  />
                </div>
              </div>

              <div className="welcome_notification_message setting-option">
                <span className="setting-label welcome-notification-message-label">
                  {" "}
                  {__("User Welcome Email Message", "new-user-approve")}{" "}
                </span>
                <div
                  className="welcome-notification-message-element nua-editor-element setting-element"
                  style={{ position: "relative" }}
                >
                  <WPEditor
                    editorId="welcome-notification-message"
                    editorName="nua-welcome-notification-message"
                    onChange={handleEditorChange}
                    editorContent={""}
                  />
                  {renderEditorBlocker()}
                  <p className="description">
                    {__(
                      "Users will receive Welcome emails when they register to the site.",
                      "new-user-approve"
                    )}
                  </p>

                  {showTags &&
                    tags_list &&
                    Object.keys(tags_list).length > 0 && (
                      <div className="nua-tags-container">
                        {Object.entries(tags_list).map(
                          ([key, value]) =>
                            key === "{sitename}" && (
                              <span key={key}>
                                <a className="nua-tags">{key}</a> - {value}
                              </span>
                            )
                        )}
                      </div>
                    )}
                </div>
              </div>

              <div
                className="welcome_send_email_as_html setting-option"
                style={{ marginTop: "0px" }}
              >
                <span className="setting-label welcome-notification-email-label">
                  {" "}
                </span>
                <div className="welcome-send-email-as-html-element setting-element">
                  <label
                    className="nua_switch"
                    htmlFor="nua_welcome_send_email_as_html"
                  >
                    <input
                      id="nua_welcome_send_email_as_html"
                      name="nua_welcome_send_email_as_html"
                      type="checkbox"
                      checked={""}
                      onChange={HandleTogleChange}
                    />
                    <span className="nua_slider round"></span>
                  </label>
                  <p className="description" style={{ marginTop: "0px" }}>
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

export default User_Notification;
