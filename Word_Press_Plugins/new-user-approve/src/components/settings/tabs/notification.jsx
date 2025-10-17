import React, { useState } from "react";
import { Tabs, TabsList, Tab, TabPanel } from "@mui/base";
import { __ } from "@wordpress/i18n";
import Admin_Notification from "./admin-notification";
import User_Notification from "./user-notification";

const Notification_Settings = () => {
  const [subTab, setSubTab] = useState("tab=admin_notification");

  const handleSubTabChange = (event, newValue) => {
    setSubTab(newValue);
  };

  return (
    <Tabs
      value={subTab}
      onChange={handleSubTabChange}
      className="nua_notification_sub_tabs"
    >
      <TabsList className="nua_notification_sub_tabstablist">
        <Tab
          value="tab=admin"
          className={subTab === "tab=admin" ? "nua_active_subtab" : ""}
        >
          {__("Admin Notification", "new-user-approve")}
        </Tab>
        <Tab
          value="tab=user"
          className={subTab === "tab=user" ? "nua_active_subtab" : ""}
        >
          {__("User Notification", "new-user-approve")}
        </Tab>
      </TabsList>

      <TabPanel value="tab=admin" className="dash-tabPanel">
        <Admin_Notification />
      </TabPanel>
      <TabPanel value="tab=user" className="dash-tabPanel">
        <User_Notification />
      </TabPanel>
    </Tabs>
  );
};

export default Notification_Settings;
