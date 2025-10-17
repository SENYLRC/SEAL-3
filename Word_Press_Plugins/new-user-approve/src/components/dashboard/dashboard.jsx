import React, { Component } from "react";
import { styled } from "@mui/system";
import { Tabs } from "@mui/base/Tabs";
import { TabsList as BaseTabsList } from "@mui/base/TabsList";
import { TabPanel as BaseTabPanel } from "@mui/base/TabPanel";
import { buttonClasses } from "@mui/base/Button";
import { Tab as BaseTab, tabClasses } from "@mui/base/Tab";
import TOPBAR from "./topbar";
import DASH_TABS from "./tabs";
// import RECENT_USERS from './recent-users';

class NUA_Dashboard extends Component {
  constructor(props) {
    super(props);
    this.state = {
      title: "",
    };
  }
  handleButtonClick(message) {
    alert(message);
  }
  ComponentDidMount() {
    this.setState({ title: "New User Approve" });
  }

  render() {
    const title = this.state.title;
    return (
      <>
        <TOPBAR />
        <DASH_TABS />
      </>
    );
  }
}

export default NUA_Dashboard;
