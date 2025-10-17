import React, { useState, useEffect } from "react";
import { fontSize, styled } from "@mui/system";
import { Tabs } from "@mui/base/Tabs";
import { TabsList } from "@mui/base/TabsList";
import { TabPanel } from "@mui/base/TabPanel";
import { Tab, tabClasses } from "@mui/base/Tab";
import {
  BrowserRouter as Router,
  Routes,
  Route,
  Link,
  useNavigate,
  useLocation,
} from "react-router-dom";
import { sprintf, __ } from "@wordpress/i18n";

import General_Settings from "./tabs/general";
import Registration_Settings from "./tabs/registration";
import Notification_Settings from "./tabs/notification";
import Help_Settings from "./tabs/help";

const Settings_Tabs = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const currentTab =
    location.pathname === "/"
      ? "general-settings"
      : location.pathname.split("/")[1];

  const handleTabChange = (event, newTab) => {
    if (newTab == "general-settings") {
      navigate("/");
    } else {
      navigate(`/${newTab}`);
    }
  };

  return (
    <React.Fragment>
      <Tabs
        className="nua_settings_parent_tabs"
        defaultValue={"general-settings"}
        value={currentTab}
        onChange={handleTabChange}
      >
        <TabsList className="nua_settings_parent_tablist">
          <Tab
            value={"general-settings"}
            component={Link}
            to={`/general-settings`}
            className={currentTab == "general-settings" ? "nua_active_tab" : ""}
          >
            {__("General Settings", "new-user-approve")}
          </Tab>
          <Tab
            value={"action=registration-settings"}
            component={Link}
            to={`/registration`}
            className={
              currentTab == "action=registration-settings"
                ? "nua_active_tab"
                : ""
            }
          >
            {__("Registration Settings", "new-user-approve")}
          </Tab>
          <Tab
            value={"action=notification-settings"}
            component={Link}
            to={`/notification`}
            className={
              currentTab == "action=notification-settings"
                ? "nua_active_tab"
                : ""
            }
          >
            {__("Notification", "new-user-approve")}
          </Tab>
          <Tab
            value={"action=help"}
            component={Link}
            to={`/help`}
            className={` help_setting_tab ${
              currentTab == "action=help" ? "nua_active_tab" : ""
            }`}
          >
            {__("Help", "new-user-approve")}
          </Tab>
          <span className="base-Tab-root" style={{ width: "60%" }}></span>
        </TabsList>

        <TabPanel
          className="dash-tabPanel"
          value={"general-settings"}
          index="general"
        >
          <General_Settings />
        </TabPanel>

        <TabPanel
          className="dash-tabPanel"
          value={"action=registration-settings"}
          index="registration"
        >
          <Registration_Settings />
        </TabPanel>

        <TabPanel
          className=""
          value={"action=notification-settings"}
          index="notification"
        >
          <Notification_Settings />
        </TabPanel>

        <TabPanel className="dash-tabPanel" value={"action=help"} index="help">
          <Help_Settings />
        </TabPanel>
      </Tabs>
    </React.Fragment>
  );
};

export default Settings_Tabs;
