import React, { useState, useEffect } from "react";
import { styled } from "@mui/system";
import { Tabs } from "@mui/base/Tabs";
import { TabsList } from "@mui/base/TabsList";
import { TabPanel } from "@mui/base/TabPanel";
import { Tab, tabClasses } from "@mui/base/Tab";
import { sprintf, __ } from "@wordpress/i18n";

import {
  BrowserRouter as Router,
  Routes,
  Route,
  Link,
  useNavigate,
  useLocation,
} from "react-router-dom";
// custom components
import Users from "./fetch_users/users";
import All_Users from "./fetch_users/all-users";
import Approved_Users from "./fetch_users/approved-users";
import Pending_Users from "./fetch_users/pending-users";
import Denied_Users from "./fetch_users/denied-users";

const User_SubTabs = () => {
  const navigate = useNavigate();
  const location = useLocation();
  // const currentTab =  location.pathname.substring(1) || 'all-users';
  const currentTab = location.pathname.split("/")[2] || "tab=all-users";
  useEffect(() => {
    if (currentTab == "tab=all-users") {
      navigate("tab=all-users");
    }
  }, []);
  // const [updateTab, setUpdateTab] = useState('/all-users');
  const handleTabChange = (event, newTab) => {
    navigate(`${newTab}`);
  };

  return (
    <React.Fragment>
      <Tabs
        className="users_subtabs"
        value={currentTab}
        onChange={handleTabChange}
      >
        <TabsList className="users_subtabs_list">
          <Tab
            value={"tab=all-users"}
            className={currentTab == "tab=all-users" ? "nua_active_subtab" : ""}
          >
            {" "}
            {__("All Users", "new-user-approve")}{" "}
          </Tab>
          <Tab
            value={"tab=pending-users"}
            className={
              currentTab == "tab=pending-users" ? "nua_active_subtab" : ""
            }
          >
            {" "}
            {__("Pending", "new-user-approve")}
          </Tab>
          <Tab
            value={"tab=approved-users"}
            className={
              currentTab == "tab=approved-users" ? "nua_active_subtab" : ""
            }
          >
            {" "}
            {__("Approved", "new-user-approve")}{" "}
          </Tab>
          <Tab
            value={"tab=denied-users"}
            className={
              currentTab == "tab=denied-users" ? "nua_active_subtab" : ""
            }
          >
            {" "}
            {__("Denied", "new-user-approve")}
          </Tab>
          <Tab
            value={"tab=user_roles"}
            className={
              currentTab == "tab=user_roles" ? "nua_active_subtab" : ""
            }
          >
            {" "}
            {__("Role Change Request", "new-user-approve")}
          </Tab>
        </TabsList>

        <Routes>
          <Route
            path={"tab=all-users"}
            element={
              <TabPanel
                className="dash-tabPanel"
                value={"tab=all-users"}
                index="all-users"
              >
                <Users usertab={"all_users"} />
              </TabPanel>
            }
          />
          <Route
            path="tab=approved-users"
            element={
              <TabPanel
                className="dash-tabPanel"
                value={"tab=approved-users"}
                index="approved-users"
              >
                <Users usertab={"approved_users"} />
              </TabPanel>
            }
          />
          <Route
            path="tab=pending-users"
            element={
              <TabPanel
                className="dash-tabPanel"
                value={"tab=pending-users"}
                index="pending-users"
              >
                <Users usertab={"pending_users"} />
              </TabPanel>
            }
          />
          <Route
            path="tab=denied-users"
            element={
              <TabPanel
                className="dash-tabPanel"
                value={"tab=denied-users"}
                index="denied-users"
              >
                <Users usertab={"denied_users"} />
              </TabPanel>
            }
          />
          <Route
            path="tab=user_roles"
            element={
              <TabPanel
                className="dash-tabPanel"
                value={"tab=user_roles"}
                index="user-roles"
              >
                <Users usertab={"user_roles"} />
              </TabPanel>
            }
          />
        </Routes>
      </Tabs>
    </React.Fragment>
  );
};

export default User_SubTabs;
