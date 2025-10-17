import React, { Component, useEffect } from "react";
import { styled } from "@mui/system";
import { Tabs } from "@mui/base/Tabs";
import { TabsList } from "@mui/base/TabsList";
import { TabPanel } from "@mui/base/TabPanel";
import { Tab, tabClasses } from "@mui/base/Tab";
// import Invitation_Codes from './Invitation_Codes';
import Add_Code_SubTabs from "./add-code-subtabs";
import ImportCodes from "./import-codes";
import Invitation_Email from "./invitation-email";
import { sprintf, __ } from "@wordpress/i18n";
import Invitation_Code_settings from "./settings";

import {
  BrowserRouter as Router,
  Routes,
  Route,
  useParams,
  Link,
  useNavigate,
  useLocation,
} from "react-router-dom";

const icons = require.context("./../../assets/icons", false, /\.svg$/);

const Invitation_Code_Tabs = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const currentTab =
    location.pathname === "/" ? "add-codes" : location.pathname.split("/")[1];
  let pro_label = icons("./pro-label.svg");

  useEffect(() => {
    if (currentTab == "add-codes") {
      navigate("/action=add-codes");
    }
  }, []);

  const handleTabChange = (event, newTab) => {
    navigate(`/${newTab}`);
  };

  return (
    <Tabs
      className="nua_inviCode_parent_tabs"
      defaultValue={"action=add-codes"}
      value={currentTab}
      onChange={handleTabChange}
    >
      <TabsList className="nua_inviCode_parent_tabs_tablist">
        <Tab
          value={"action=add-codes"}
          component={Link}
          to={`/add-codes`}
          className={currentTab == "action=add-codes" ? "nua_active_tab" : ""}
        >
          {__("Add Codes", "new-user-approve")}
        </Tab>
        <Tab
          value={"action=settings"}
          component={Link}
          to={`/action=settings`}
          className={currentTab == "action=settings" ? "nua_active_tab" : ""}
        >
          {__("Settings", "new-user-approve")}
        </Tab>
        <Tab
          value={"action=import-codes"}
          component={Link}
          to={`/import-codes`}
          className={
            currentTab == "action=import-codes" ? "nua_active_tab" : ""
          }
        >
          <span className="import-code-tab">
            {__("Import Codes", "new-user-approve")}{" "}
            <img src={pro_label} alt="" />
          </span>
        </Tab>
        <Tab
          value={"action=email"}
          component={Link}
          to={`/email`}
          className={currentTab == "action=email" ? "nua_active_tab" : ""}
        >
          <span className="email-tab">
            {__("Email", "new-user-approve")} <img src={pro_label} alt="" />
          </span>
        </Tab>
      </TabsList>

      <TabPanel
        className=""
        value={"action=add-codes"}
        index="add-codes"
        style={{ position: "relative" }}
      >
        <Add_Code_SubTabs />
      </TabPanel>

      <TabPanel className="" value={"action=settings"} index="settings">
        <Invitation_Code_settings />
      </TabPanel>

      <TabPanel
        className="import-code-tabpanel"
        value={"action=import-codes"}
        index="import-codes"
      >
        <ImportCodes />
      </TabPanel>

      <TabPanel
        className="invitation-email-tabpanel"
        style={{ position: "relative" }}
        value={"action=email"}
        index="email"
      >
        <Invitation_Email />
      </TabPanel>
    </Tabs>
  );
};

export default Invitation_Code_Tabs;
