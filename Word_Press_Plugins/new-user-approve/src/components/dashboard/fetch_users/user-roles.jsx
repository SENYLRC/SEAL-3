import React, { useEffect, useState } from "react";
import axios from "axios";
import { sprintf, __ } from "@wordpress/i18n";
import { user_role_dummy } from "../../../functions";
import {
  Table,
  TableBody,
  TableCell,
  TableContainer,
  TableHead,
  TableRow,
  Paper,
  Button,
  IconButton,
  TextField,
  Menu,
  MenuItem,
} from "@mui/material";

import Update_User_Role_Modal from "./update-user-role-modal";
import PopupModal from "../../popup-modal";
const icons = require.context(
  "../../../assets/icons",
  false,
  /\.(png|svg|jpe?g|)$/
);

const User_Roles = () => {
  const [loading, setLaoding] = useState(true);
  const [error, setError] = useState(null);
  const [anchorEl, setAnchorEl] = useState(null);
  const [user_id, setUserID] = useState(null);
  const [open, setOpen] = useState(false);

  const [reload, setReload] = useState(false);
  const [isPopupVisible, setPopupVisible] = useState(false);
  let usersData = user_role_dummy();

  const fetchUsers = async () => {
    try {
      setLaoding(true);
      const response = await axios.get(
        `${NUARestAPI.get_approved_user_roles}`,
        {
          headers: {
            "X-WP-Nonce": wpApiSettings.nonce,
          },
        }
      );
      const data = response.data;
      // setUserData(data);
    } catch (error) {
      setError(error);
    } finally {
      setLaoding(false);
    }
  };

  useEffect(() => {
    fetchUsers();
  }, [reload]);

  const handleEditChange = () => {
    setPopupVisible(true);
    setAnchorEl(null);
    setUserID(null);
  };

  const handleMenuOpen = (event, userId) => {
    setAnchorEl(event.currentTarget);
    setUserID(userId);
  };

  const handleMenuClose = () => {
    setAnchorEl(null);
    setUserID(null);
  };

  const handleCloseModal = () => {
    setOpen(false);
  };

  const handleMenuAction = (event, user_id) => {
    setOpen(true);
    setAnchorEl(null);
    setUserID(user_id);
  };

  let editIcon = (
    <svg
      width="16"
      height="16"
      viewBox="0 0 16 16"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M9.99999 4.00002L12 6.00002M8.66666 13.3334H14M3.33332 10.6667L2.66666 13.3334L5.33332 12.6667L13.0573 4.94269C13.3073 4.69265 13.4477 4.35357 13.4477 4.00002C13.4477 3.64647 13.3073 3.30739 13.0573 3.05736L12.9427 2.94269C12.6926 2.69273 12.3535 2.55231 12 2.55231C11.6464 2.55231 11.3074 2.69273 11.0573 2.94269L3.33332 10.6667Z"
        stroke="#242424"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );

  return (
    <div className="user_roles_list" style={{ position: "relative" }}>
      <h2 className="users_list_title">
        {__("User Roles", "new-user-approve")}
      </h2>

      <TableContainer
        className="user_roles_tbl_container usersTable"
        component={Paper}
        style={isPopupVisible ? styles.tableColorChange : {}}
      >
        <Table sx={{ minWidth: 650 }}>
          <TableHead>
            <TableRow
              sx={{
                backgroundColor: "#FAFAFA",
                maxHeight: 50,
                minHeight: 50,
                height: 50,
              }}
            >
              <TableCell> {__("Username", "new-user-approve")}</TableCell>
              <TableCell> {__("Current Role", "new-user-approve")}</TableCell>
              <TableCell sx={{ paddingLeft: 4 }}>
                {" "}
                {__("Email", "new-user-approve")}
              </TableCell>
              <TableCell> {__("Requested Role", "new-user-approve")}</TableCell>
              <TableCell align="left">
                {" "}
                {__("Action", "new-user-approve")}
              </TableCell>
              <TableCell></TableCell>
            </TableRow>
          </TableHead>
          {usersData.length > 0 ? (
            <TableBody onClick={() => setPopupVisible(true)}>
              {usersData.map((row) => (
                <TableRow id={row.username}>
                  <TableCell>
                    <a
                      href={""}
                      style={{
                        textDecoration: "none",
                        color: "#858585",
                        pointerEvents: "none",
                      }}
                    >
                      {row.username}
                    </a>
                  </TableCell>
                  <TableCell>{row.current_role}</TableCell>
                  <TableCell>{row.email_address}</TableCell>
                  <TableCell>{row.requested_role}</TableCell>
                  <TableCell align="left">
                    <div
                      style={{ display: "flex" }}
                      className="action_edit_btn"
                    >
                      <IconButton onClick={() => handleEditChange()}>
                        <span className="actionsIcon">{editIcon}</span>
                      </IconButton>
                    </div>
                  </TableCell>
                  <TableCell></TableCell>
                </TableRow>
              ))}
            </TableBody>
          ) : (
            <TableBody>
              <TableRow>
                <TableCell></TableCell>
                <TableCell></TableCell>
                <TableCell>
                  <div className="user-list-empty">
                    {loading == true ? (
                      <div className="new-user-approve-loading">
                        <div className="nua-spinner"></div>
                      </div>
                    ) : (
                      <div className="user-found-error">
                        <img src={not_found} alt="" />
                        <span>
                          {" "}
                          {__("No Data Available", "new-user-approve")}
                        </span>
                        <p className="description">
                          {__(
                            "There’s no data available to see!",
                            "new-user-approve"
                          )}
                        </p>
                      </div>
                    )}
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          )}
        </Table>
      </TableContainer>
      <PopupModal
        isVisible={isPopupVisible}
        onClose={() => setPopupVisible(false)}
      />
      <Update_User_Role_Modal
        user_id={user_id}
        open={open}
        handleClose={handleCloseModal}
        setReload={setReload}
      />
    </div>
  );
};

const styles = {};

export default User_Roles;
