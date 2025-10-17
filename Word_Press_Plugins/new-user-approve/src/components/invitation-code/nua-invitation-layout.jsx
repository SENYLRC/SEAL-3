import React, { useState, useEffect, Component } from "react";
import Invitation_Code_Tabs from "./invitation-code-main-tabs";

class NUA_Invitation_Layout extends Component {
  constructor(props) {
    super(props);

    this.state = {};
  }

  render() {
    return (
      <>
        <Invitation_Code_Tabs />
      </>
    );
  }
}

export default NUA_Invitation_Layout;
