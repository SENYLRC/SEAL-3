import React, { useState, useEffect } from "react";
import Button from "@mui/material/Button";
import { styled } from "@mui/material/styles";
import Dialog from "@mui/material/Dialog";
import DialogTitle from "@mui/material/DialogTitle";
import DialogContent from "@mui/material/DialogContent";
import DialogActions from "@mui/material/DialogActions";
import IconButton from "@mui/material/IconButton";
import CloseIcon from "@mui/icons-material/Close";
import Typography from "@mui/material/Typography";
import MenuItem from "@mui/material/MenuItem";
import FormControl from "@mui/material/FormControl";
import RoleSelect from "react-select";
import { get_user_roles } from "../../../functions";
import { update_user_role } from "../../../functions";
import { sprintf, __ } from "@wordpress/i18n";

const BootstrapDialog = styled(Dialog)(({ theme }) => ({
  "& .MuiDialogContent-root": {
    padding: theme.spacing(2),
  },
  "& .MuiDialogActions-root": {
    padding: theme.spacing(1),
  },
}));

const Update_User_Role_Modal = ({ user_id, open, handleClose, setReload }) => {
  const [roles, setRoles] = useState([]);
  const [newRole, setNewRole] = useState("");
  const [updateRole, setUpdateRole] = useState("");
  const [userID, setUserID] = useState(null);
  const [error, setError] = useState(false);
  const [loading, setLaoding] = useState(true);
  const [success_message, setSuccessMessage] = useState("");

  const handleChange = (newRole) => {
    const filter_by = newRole.value;
    setNewRole(newRole); // for select
    setUpdateRole(filter_by); // for updating the role using api
    setUserID(user_id);
  };

  useEffect(() => {
    const all_roles = async () => {
      const response = await get_user_roles();
      if (response.message == "Success") {
        setRoles(response.data);
      }
      if (response.message == "Failed") {
        setError;
      }
    };
    all_roles();
  }, []);

  const handleUpdateRole = async () => {
    const response = await update_user_role({ userID, updateRole });
    const result = response.data;

    if (result.status == "success") {
      // display successful message and close the modal also
      setSuccessMessage("user has been updated");
      setTimeout(() => {
        setSuccessMessage("");
        handleClose();
        setReload((prev) => !prev);
      }, 3000);
    }
    if (result.status == "error") {
      // display error message and close the modal
    }
  };

  return (
    <React.Fragment>
      <BootstrapDialog
        onClose={handleClose}
        aria-labelledby="update-user-roles-dialog"
        open={open}
      >
        <DialogTitle sx={{ m: 0, p: 2 }} id="update-user-roles-dialog">
          {__("Upgrade User Role", "new-user-approve")}
        </DialogTitle>
        <IconButton
          aria-label="close"
          onClick={handleClose}
          sx={{
            position: "absolute",
            right: 8,
            top: 8,
            color: (theme) => theme.palette.grey[500],
          }}
        >
          <CloseIcon />
        </IconButton>
        <DialogContent dividers>
          <Typography gutterBottom>
            {/* select start */}
            <FormControl sx={{ m: 1, minHeight: 400, minWidth: 400 }}>
              {success_message != "" ? (
                <div className="success-message">{success_message}</div>
              ) : (
                <RoleSelect
                  className="basic-single invite-email-select"
                  placeholder="Select a Role"
                  id="select-roles"
                  name="code-select"
                  value={newRole}
                  onChange={handleChange}
                  options={Object.entries(roles).map(([key, value]) => ({
                    value: key,
                    label: value,
                  }))}
                />
              )}
            </FormControl>
            {/* select end */}
          </Typography>
        </DialogContent>
        <DialogActions>
          <Button autoFocus onClick={handleClose}>
            {__("Close", "new-user-approve")}
          </Button>
          <Button autoFocus onClick={handleUpdateRole}>
            {__("Save changes", "new-user-approve")}
          </Button>
        </DialogActions>
      </BootstrapDialog>
    </React.Fragment>
  );
};

export default Update_User_Role_Modal;
