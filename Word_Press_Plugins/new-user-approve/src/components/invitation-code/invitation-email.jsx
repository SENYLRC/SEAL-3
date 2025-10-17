import React, {
  useState,
  useEffect,
  forwardRef,
  useImperativeHandle,
} from "react";
import {
  Box,
  Button,
  TextField,
  Input,
  Switch,
  FormControlLabel,
} from "@mui/material";
import Select, { SelectChangeEvent } from "@mui/material/Select";
import Typography from "@mui/material/Typography";
import MenuItem from "@mui/material/MenuItem";
import FormControl from "@mui/material/FormControl";
import PageSelect from "react-select";
import CodeSelect from "react-select";
import { sprintf, __ } from "@wordpress/i18n";
import WPEditor from "../wp-editor/WPEditor";
// import "react-toastify/dist/ReactToastify.css";
import PopupModal from "../../components/popup-modal";

import { send_invite_email } from "../../functions";

const Invitation_Email = forwardRef(({ closeModal }, ref) => {
  const [isPopupVisible, setPopupVisible] = useState(false);
  const [regPage, setRegPage] = useState(null);
  const [regPageID, setRegPageID] = useState(null);

  const [inviteCode, setInviteCode] = useState("");
  const [inviteCodeID, setInviteCodeID] = useState(null);

  const [usersEmail, setUsersEmail] = useState("");
  const [emailSubject, setEmailSubject] = useState("");
  const [emailMessage, setEmailMessage] = useState("");
  const [sendHTML, setSendHTML] = useState(false);
  const [allPages, setAllPages] = useState([]);
  const [allInviteCodes, setAllInviteCodes] = useState([]);

  // loading
  const [loading, setLoading] = useState(false);

  // response notification:

  const handleRegPageChange = (regPage) => {
    setPopupVisible(true);
    setRegPage(regPage); // for select
    setRegPageID(regPage.value); // this value sends with api.
  };
  const handleInviteCodeChange = (inviteCode) => {
    setPopupVisible(true);
    setInviteCode(inviteCode); // for select
    setInviteCodeID(inviteCode.value); // this value sends with api
  };

  const handleUsersEmailChange = (event) => {
    const { value } = event.target;
    setUsersEmail(value);
  };

  const handleEmailSubjectChange = (event) => {
    const { value } = event.target;
    setEmailSubject(value);
  };

  const handleEditorChange = ({ editorName, editorContent }) => {
    switch (editorName) {
      case "nua-email-message":
        setEmailMessage(editorContent);
        break;
    }
  };

  const handleToggleChange = (event) => {
    setPopupVisible(true);
  };

  const handleEmail = async (event) => {
    setPopupVisible(true);
  };

  useImperativeHandle(ref, () => ({
    handleEmail,
    isLoading: loading,
  }));

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
      <div className="invitation-email-box nua-setting-pro">
        {/* --------------------------------------------------------------- */}
        {/* <div className="notificationDiv"> */}
        {/* { emailResponseStatus === 'success' ? ( <span className={'invite-email-saved-' + emailResponseStatus}> {emailResponseMsg} </span> ) : '' }
                    { emailResponseStatus === 'failed' ? ( <span className={'invite-email-saved-' + emailResponseStatus} style={{ color: 'red' }}>{__('Error', 'new-user-approve')}: {emailResponseMsg} </span> ) : '' } */}
        {/* </div> */}

        <div className="nua-field-col">
          <span className="nua-code-email">
            <h4> {__("Registration Page", "new-user-approve")} </h4>
            <PageSelect
              className="basic-single invite-email-select"
              placeholder="Select a page"
              name="page-select"
              onFocus={setPopupVisible}
              value={regPage}
              onChange={handleRegPageChange}
              options={Object.entries(allPages).map(([key, value]) => ({
                value: value.page_id,
                label: value.page_title,
              }))}
            />
          </span>
          {/* <p className='description'>
                    {__(`Select page where users will be redirected when click on invitation link.`, 'new-user-approve')}
                </p> */}
          {/* --------------------------------------------------------------- */}
          <span className="nua-code-email">
            <h4> {__("Invitation Code", "new-user-approve")} </h4>
            <CodeSelect
              className="basic-single invite-email-select"
              placeholder="Select a code"
              id="select-invite-code"
              name="code-select"
              onFocus={setPopupVisible}
              value={inviteCode}
              onChange={handleInviteCodeChange}
              options={allInviteCodes.map((code) => ({
                value: code,
                label: code,
              }))}
            />
          </span>
        </div>
        {/* <p className='description'>
                    {__(`Select Invitation code to send in email.`, 'new-user-approve')}
                </p> */}
        {/* --------------------------------------------------------------- */}
        <h4> {__("User Email", "new-user-approve")} </h4>

        <div className="users-email setting-option">
          <div className="users-email-element setting-element">
            <textarea
              onFocus={setPopupVisible}
              name="users-email"
              className="users-email nua-setting-textarea"
              row={40}
              value={usersEmail}
              onChange={handleUsersEmailChange}
            />
            <p className="description">
              {__("Enter Email Addresses, comma separated", "new-user-approve")}
            </p>
          </div>
        </div>

        {/* --------------------------------------------------------------- */}
        <h4 style={{ marginTop: "32px" }}>
          {" "}
          {__("Email Subject", "new-user-approve")}{" "}
        </h4>
        <div className="invite_email_subject setting-option">
          <div className="invite-email-subject-element setting-element">
            <input
              onFocus={setPopupVisible}
              type="text"
              size={40}
              name="invite_email_subject"
              className="auto-code-field"
              value={emailSubject}
              onChange={handleEmailSubjectChange}
            />
          </div>
        </div>

        <h4 style={{ marginTop: "32px", marginBottom: "0px" }}>
          {__("Email Message", "new-user-approve")}{" "}
        </h4>
        <div className="email-message setting-option">
          <div
            className="email-message-element nua-editor-element setting-element"
            style={{ position: "relative" }}
          >
            <WPEditor
              editorId="email-message"
              editorName="nua-email-message"
              onChange={handleEditorChange}
              editorContent={emailMessage}
            />
            {renderEditorBlocker()}
            <p className="description">
              {__(
                "Email Message to send, use {registration} for registration page link and {code} for invitation code.",
                "new-user-approve"
              )}
            </p>
          </div>
        </div>
        {/* --------------------------------------------------------------- */}
        <div className="invite_code_email_as_html setting-option">
          <div className="invite-code-email-as-html-element setting-element">
            <h4 style={{ marginBottom: "0px" }}>
              {" "}
              {__("Send email message as html.", "new-user-approve")}{" "}
            </h4>
            <label className="nua_switch" htmlFor="invite-code-email-as-html">
              <input
                id="invite-code-email-as-html"
                name="invite-code-email-as-html"
                type="checkbox"
                checked={sendHTML}
                onChange={handleToggleChange}
              />
              <span className="nua_slider round"></span>
            </label>
          </div>
        </div>

        {/* --------------------------------------------------------------- */}

        <div
          className="invite-code-email-btn setting-option"
          style={{ marginBottom: "10px" }}
        ></div>
      </div>
      <PopupModal
        isVisible={isPopupVisible}
        onClose={() => setPopupVisible(false)}
      />
    </React.Fragment>
  );
});

export default Invitation_Email;
