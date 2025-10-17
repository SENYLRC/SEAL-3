import React, {
  useState,
  useEffect,
  forwardRef,
  useImperativeHandle,
} from "react";
import { sprintf, __ } from "@wordpress/i18n";
import { update_invitation_code } from "../../functions";
import "react-toastify/dist/ReactToastify.css";
import { toast, ToastContainer } from "react-toastify";

const EditInvitationCode = forwardRef(
  (
    {
      codeId: codeId,
      code: initialCode,
      usesLeft: initialUsesLeft,
      usageLimit: initialTotalUses,
      expiryDate: initialExpiryDate,
      status: initialStatus,
      handleCloseEditModal,
      fetchAutoCodes,
    },
    ref
  ) => {
    const [code, setCode] = useState(initialCode);
    const [usesLeft, setUsesLeft] = useState(initialUsesLeft);
    const [usageLimit, setTotalUses] = useState(initialTotalUses);
    const [expiryDate, setExpiryData] = useState(initialExpiryDate);
    const [status, setStatusList] = useState(initialStatus);
    const [total, setUsageData] = useState(initialTotalUses);
    const [loading, setLoading] = useState(false);

    const today = new Date().toISOString().split("T")[0];

    const handleEditSubmit = async (event) => {
      const endpoint = "update-invitation-code";
      const updateCode = {
        codeId: codeId,
        editCode: code,
        usesLeft: usesLeft,
        usageLimit: usageLimit,
        expiryDate: expiryDate,
        status: status,
      };

      try {
        setLoading(true);
        if (!code || !usageLimit || !expiryDate) {
          toast.error(
            __("Please fill all required fields.", "new-user-approve"),
            {
              position: "bottom-right",
              autoClose: 2000,
              hideProgressBar: false,
              closeOnClick: true,
              pauseOnHover: true,
              draggable: true,
              progress: undefined,
            }
          );
          return;
        }

        const response = await update_invitation_code({ endpoint, updateCode });

        if (response.data?.status === "success") {
          toast.success(
            __("Invitation code updated successfully!", "new-user-approve"),
            {
              position: "bottom-right",
              autoClose: 2000,
              hideProgressBar: false,
              closeOnClick: true,
              pauseOnHover: true,
              draggable: true,
              progress: undefined,
            }
          );
          setTimeout(() => {
            handleCloseEditModal?.();
            fetchAutoCodes?.();
          }, 200);
        } else if (response.data?.status === "error") {
          toast.error(__("Something went wrong", "new-user-approve"), {
            position: "bottom-right",
            autoClose: 2000,
            hideProgressBar: false,
            closeOnClick: true,
            pauseOnHover: true,
            draggable: true,
            progress: undefined,
          });
          return;
        }
      } catch (error) {
        toast.error(
          __("Network error. Please try again.", "new-user-approve"),
          {
            position: "bottom-right",
            autoClose: 2000,
            hideProgressBar: false,
            closeOnClick: true,
            pauseOnHover: true,
            draggable: true,
            progress: undefined,
          }
        );
      } finally {
        setLoading(false);
      }
    };

    useImperativeHandle(ref, () => ({
      handleEditSubmit,
    }));

    return (
      <div className="nua_edit_invitation_code">
        <span className="edit_invitation_code">
          <h4>{__("Invitation Code", "new-user-approve")}</h4>
          <input
            type="text"
            name="codes"
            className="auto-code-field"
            required
            value={code}
            onChange={(e) => setCode(e.target.value)}
          />
        </span>
        <div className="nua-field-col">
          <span className="nua-uses-left">
            <h4>{__("Uses Left", "new-user-approve")}</h4>
            <input
              type="number"
              name="usage_limit"
              min="1"
              readOnly="true"
              className="auto-code-field"
              value={usesLeft}
            />
          </span>

          <span className="nua-usage-limit">
            <h4>{__("Usage Limit", "new-user-approve")}</h4>
            <input
              type="number"
              name="total_code"
              min="1"
              className="auto-code-field"
              value={usageLimit}
              onChange={(e) => {
                const newLimit = parseInt(e.target.value) || 0;
                setTotalUses(newLimit);
                setUsesLeft(newLimit);
              }}
            />
          </span>
        </div>
        <div className="nua-field-col">
          <span className="nua-expiry-date">
            <h4>{__("Expiry Date", "new-user-approve")}</h4>
            <input
              type="date"
              name="expiry_date"
              required
              value={expiryDate}
              id="nua_invcode_date"
              className="nua_codetxt auto-code-field"
              onChange={(e) => setExpiryData(e.target.value)}
              min={today}
            />
          </span>

          <span className="nua-code-status">
            <h4>{__("Status", "new-user-approve")}</h4>
            <select
              name="code_status"
              required
              className="nua_codetxt auto-code-field"
              value={status}
              onChange={(e) => setStatusList(e.target.value)}
            >
              <option value="Active">{__("Active", "new-user-approve")}</option>
              <option value="InActive">
                {__("InActive", "new-user-approve")}
              </option>
              <option value="Expired">
                {__("Expired", "new-user-approve")}
              </option>
            </select>
          </span>
        </div>
      </div>
    );
  }
);
<ToastContainer />;
export default EditInvitationCode;
