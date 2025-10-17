import React, { useState, useEffect } from "react";
import { sprintf, __ } from "@wordpress/i18n";
import { get_invitation_code_setttings } from "../../functions";

const Invitation_Code_settings = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState();
  const [enable_invite_code, setEnableInviteCode] = useState();

  // fetching invitation code settings
  const fetchInvitationCodeSettings = async () => {
    const response = await get_invitation_code_setttings();
    const data = response.data;
    setEnableInviteCode(data.nua_invitation_code_setting.invite_code_toggle);
  };

  useEffect(() => {
    fetchInvitationCodeSettings();
  }, []);

  const HandleTogleChange = (event) => {
    const { name, checked } = event.target;
    setEnableInviteCode(checked);
  };

  const handleSaveChanges = async () => {
    try {
      setLoading(true);

      const Settings = {
        enable_invitation_code: enable_invite_code,
      };
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <div className="import-code-section">
        <div className="enable_invitation_code setting-option">
          <span className="setting-label enable-invitation-code-label">
            {" "}
            {__("Enable/Disable:", "new-user-approve")}{" "}
          </span>
          <div className="enable-invitation-code-element setting-element">
            <label className="nua_switch" for="nua_enable_invitation_code">
              <input
                id="nua_enable_invitation_code"
                name="nua_enable_invitation_code"
                type="checkbox"
                checked={enable_invite_code}
                onChange={HandleTogleChange}
              />
              <span className="nua_slider round"></span>
            </label>
            <p className="description">
              {" "}
              {__(
                `Invitation Code for user to register.`,
                "new-user-approve"
              )}{" "}
            </p>
          </div>
        </div>

        <div className="setting-save-btn setting-option">
          <button className="nua-btn" onClick={handleSaveChanges}>
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
    </>
  );
};

export default Invitation_Code_settings;
