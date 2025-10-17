import React, { useEffect, useState, useRef } from "react";
import { sprintf, __ } from "@wordpress/i18n";
import { get_help_settings } from "../../../functions";
import {
  Box,
  Button,
  TextField,
  Switch,
  FormControlLabel,
} from "@mui/material";
import Skeleton from "@mui/material/Skeleton";

const Help_Settings = () => {
  const [dignostic_info, setDignosticInfo] = useState([]);
  const [plugins_info, setPluginsInfo] = useState([]);
  const [loading, setLoading] = useState(true);
  const textareaRef = useRef(null);
  useEffect(() => {
    const fetchHelpSettings = async () => {
      const response = await get_help_settings("fetchDignostics");
      setDignosticInfo(response.data.data.dignostic_info);
      setPluginsInfo(response.data.data.plugin_info);
      setLoading(false);
    };
    fetchHelpSettings();
  }, []);

  const handleFocus = () => {
    textareaRef.current.select();
  };

  const handleDownloadChange = async () => {
    const element = document.createElement("a");
    const file = new Blob([textareaRef.current.value], { type: "text/plain" });
    element.href = URL.createObjectURL(file);
    element.download = "nua-diagnostic-info.txt";
    document.body.appendChild(element); // Required for this to work in FireFox
    element.click();
    document.body.removeChild(element); // Cle
  };

  let supportIcon = (
    <svg
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M14.5 21.5V19.2L15.8 19.45C17.25 19.75 18.6 18.7 18.7 17.25L19 14L20.45 13.4C20.95 13.2 21.15 12.6 20.85 12.1L19 9C18.7 5.2 16.55 1.5 11 1.5C5.3 1.5 2.5 5.7 2.5 10C2.5 11.85 3.15 13.45 4.15 14.8C5.05 16.05 5.5 17.55 5.5 19.05V21.45H14.5V21.5Z"
        fill="#FFB74D"
      />
      <path d="M14.5 21.5V19.2L11 18.5V21.5H14.5Z" fill="#FF9800" />
      <path
        d="M16.75 11.5C17.1642 11.5 17.5 11.1642 17.5 10.75C17.5 10.3358 17.1642 10 16.75 10C16.3358 10 16 10.3358 16 10.75C16 11.1642 16.3358 11.5 16.75 11.5Z"
        fill="#784719"
      />
      <path
        d="M10.7 1.5C6.15 1.5 2.5 5.15 2.5 9.7C2.5 15.25 5.5 15.4 5.5 19L6.8 18.55C7.85 18.2 8.75 17.4 9.15 16.35L10.55 12.95L13.5 11.5V8.5C13.5 8.5 17 6.6 17 3.35C15.5 2.1 12.85 1.5 10.7 1.5Z"
        fill="#618E5F"
      />
      <path
        d="M10.5 1.04999C10.2 1.04999 10 1.24999 10 1.54999V8.49999C10 8.79999 10.2 8.99999 10.5 8.99999C10.8 8.99999 11 8.79999 11 8.49999V1.54999C11 1.24999 10.8 1.04999 10.5 1.04999ZM18.45 15.95C14.5 15.95 13.3 13.5 13.25 13.4C13.15 13.15 12.85 13.05 12.6 13.15C12.35 13.25 12.25 13.55 12.35 13.8C12.4 13.95 13.85 16.95 18.45 16.95C18.75 16.95 18.95 16.75 18.95 16.45C18.95 16.15 18.7 15.95 18.45 15.95Z"
        fill="#778A9F"
      />
      <path
        d="M18.5 17.5C19.0523 17.5 19.5 17.0523 19.5 16.5C19.5 15.9477 19.0523 15.5 18.5 15.5C17.9477 15.5 17.5 15.9477 17.5 16.5C17.5 17.0523 17.9477 17.5 18.5 17.5Z"
        fill="#242424"
      />
      <path
        d="M10.5 15C12.433 15 14 13.433 14 11.5C14 9.567 12.433 8 10.5 8C8.567 8 7 9.567 7 11.5C7 13.433 8.567 15 10.5 15Z"
        fill="#242424"
      />
      <path
        d="M10.5 13.5C11.6046 13.5 12.5 12.6046 12.5 11.5C12.5 10.3954 11.6046 9.5 10.5 9.5C9.39543 9.5 8.5 10.3954 8.5 11.5C8.5 12.6046 9.39543 13.5 10.5 13.5Z"
        fill="#778A9F"
      />
    </svg>
  );

  return (
    <div className="settting-section nua-help-section">
      <div className="nua-setting-row">
        <div className="nua-setting-label">
          <h2 className="nua-setting-label enable-whitelist-label">
            {loading ? (
              <Skeleton variant="text" width={120} height={24} />
            ) : (
              __("Email Support", "new-user-approve")
            )}
          </h2>
        </div>

        <div className="nua-setting-control enable-whitelist-element setting-element">
          {loading ? (
            <>
              <Skeleton variant="text" width="100%" height={20} />
              <Skeleton variant="text" width="100%" height={20} />
              <Skeleton variant="text" width="100%" height={20} />
              <Skeleton variant="text" width="100%" height={20} />
              <Skeleton
                variant="text"
                width="100%"
                height={40}
                style={{ marginTop: 16 }}
              />
            </>
          ) : (
            <>
              <p className="description" style={{ marginTop: "0px" }}>
                {__(
                  "You may send an email to the following address to get plugin support.",
                  "new-user-approve"
                )}
              </p>
              <p className="description">
                {__(
                  "Please download the Diagnostic Info below and attach it to your email",
                  "new-user-approve"
                )}
              </p>
              <p className="description" style={{ width: "100%" }}>
                {__("Site Url :", "new-user-approve")}{" "}
                <span className="help-siteUrl">
                  {__(dignostic_info.site_url, "new-user-approve")}
                </span>
              </p>
              <p className="help-email description" style={{ width: "100%" }}>
                {supportIcon}
                <a
                  className="button nua-support-email"
                  target="_blank"
                  href="https://objectsws.atlassian.net/servicedesk/customer/portal/3/group/3"
                >
                  support@wpexperts.io
                </a>
              </p>
            </>
          )}
        </div>
      </div>

      <div className="nua-setting-row setting-option">
        <div className="nua-setting-label">
          <h2 className="nua-setting-label enable-whitelist-label">
            {loading ? (
              <Skeleton variant="text" width={120} height={24} />
            ) : (
              __("Diagnostic Info", "new-user-approve")
            )}
          </h2>
        </div>

        <div className="nua-setting-control enable-whitelist-element setting-element">
          {loading ? (
            <>
              <Skeleton
                variant="rectangular"
                width="100%"
                height={120}
                style={{ marginBottom: 16 }}
              />
              <Skeleton
                variant="text"
                width="100%"
                height={20}
                style={{ marginBottom: 8 }}
              />
              <Skeleton variant="rectangular" width="100%" height={36} />
            </>
          ) : (
            <>
              <textarea
                ref={textareaRef}
                readOnly="true"
                value={Object.entries(dignostic_info)
                  .map(([key, value]) => `${key.replace(/_/g, " ")}: ${value}`)
                  .join("\n")}
                onFocus={handleFocus}
                id="nua-diagnostics-textarea"
                name="nua-diagnostics"
                title={__(
                  "To copy the diagnostic info, click below then press Ctrl + C (PC) or Cmd + C (Mac).",
                  "new-user-approve"
                )}
              />
              <p className="description">
                {__(
                  "Please include this information when posting support requests.",
                  "new-user-approve"
                )}
              </p>
              <input
                type="submit"
                name="nua-download-diagnostics"
                id="nua-download-diagnostics"
                className="nua-btn"
                value={__("Download Diagnostic Info File", "new-user-approve")}
                onClick={handleDownloadChange}
              />
            </>
          )}
        </div>
      </div>
    </div>
  );
};

export default Help_Settings;
