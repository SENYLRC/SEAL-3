import React, { Component } from "react";

import NUA_Dashboard from "./components/dashboard/dashboard";
import { HashRouter as Router, Route, Routes } from "react-router-dom";
import NUA_Invitation_Layout from "./components/invitation-code/nua-invitation-layout";

class App extends Component {
  render() {
    return (
      <>
        <Router>
          <Routes>
            <Route path="/" element={<NUA_Dashboard />} />
            <Route path="/action=users/*" element={<NUA_Dashboard />} />
            <Route path="/action=user-roles" element={<NUA_Dashboard />} />
            <Route path="/action=inv-codes/*" element={<NUA_Dashboard />} />
            <Route
              path="/action=import-codes"
              element={<NUA_Invitation_Layout />}
            />
            <Route path="/action=email" element={<NUA_Invitation_Layout />} />
            <Route path="/action=auto-approve/*" element={<NUA_Dashboard />} />
            <Route path="/action=integrations" element={<NUA_Dashboard />} />
            <Route path="/action=settings/*" element={<NUA_Dashboard />} />
          </Routes>
        </Router>
      </>
    );
  }
}

export default App;
