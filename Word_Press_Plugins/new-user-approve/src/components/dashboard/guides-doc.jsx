import React, { useState, useEffect } from "react";
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
import BookNow from "./booknow";
import { ConstructionRounded } from "@mui/icons-material";
import { sprintf, __ } from "@wordpress/i18n";

const icons = require.context("../../assets/icons", false, /\.svg$/);

const Guides_Docs = () => {
  const docs_icon = icons(`./doc-features.svg`);
  return (
    <>
      <div id="nua_guide_doc_section">
        <TableContainer
          className="nua_doc_items_list_container usersTable"
          component={Paper}
        >
          <Table sx={{ minWidth: 650 }}>
            <TableHead>
              <TableRow>
                <TableCell className="guide_doc_cell">
                  {" "}
                  {__("Guides and Documentation", "new-user-approve")}
                </TableCell>
              </TableRow>
            </TableHead>
            <TableBody>
              {/* {data.map((row) => ( */}
              <TableRow>
                <div className="nua_doc_item_lists">
                  <a href="https://newuserapprove.com/docs/pro-and-free/introduction/">
                    <span className="nua-doc-item">
                      <img src={docs_icon} alt="" />
                      {__("Introduction", "new-user-approve")}
                    </span>
                  </a>

                  <a href="https://newuserapprove.com/docs/pro-and-free/new-user-interface/">
                    <span className="nua-doc-item">
                      <img src={docs_icon} alt="" />
                      {__("New User Approve Interface", "new-user-approve")}
                    </span>
                  </a>

                  <a href="https://newuserapprove.com/docs/pro-and-free/registration-settings/">
                    <span className="nua-doc-item">
                      <img src={docs_icon} alt="" />
                      {__("Registration Settings", "new-user-approve")}
                    </span>
                  </a>

                  <a href="https://newuserapprove.com/docs/pro-and-free/auto-approve/">
                    <span className="nua-doc-item">
                      <img src={docs_icon} alt="" />
                      {__("Auto Approve", "new-user-approve")}
                    </span>
                  </a>

                  <a href="https://newuserapprove.com/docs/pro-and-free/compatibility/">
                    <span className="nua-doc-item">
                      <img src={docs_icon} alt="" />
                      {__("Compatibility", "new-user-approve")}
                    </span>
                  </a>

                  <a href="https://newuserapprove.com/docs/pro-and-free/installation-guide/">
                    <span className="nua-doc-item">
                      <img src={docs_icon} alt="" />
                      {__("Installation Guide", "new-user-approve")}
                    </span>
                  </a>

                  <a href="https://newuserapprove.com/docs/pro-and-free/features/">
                    <span className="nua-doc-item">
                      <img src={docs_icon} alt="" />
                      {__("Features", "new-user-approve")}
                    </span>
                  </a>

                  <a href="https://newuserapprove.com/docs/pro-and-free/approve-new-users-settings-tabs/">
                    <span className="nua-doc-item">
                      <img src={docs_icon} alt="" />
                      {__("Approve New User Settings", "new-user-approve")}
                    </span>
                  </a>

                  <a href="https://newuserapprove.com/docs/pro-and-free/notifications/">
                    <span className="nua-doc-item">
                      <img src={docs_icon} alt="" />
                      {__("Notifications", "new-user-approve")}
                    </span>
                  </a>
                </div>
              </TableRow>

              {/* ))} */}
            </TableBody>
          </Table>
        </TableContainer>
        <BookNow />
      </div>
    </>
  );
};

export default Guides_Docs;
