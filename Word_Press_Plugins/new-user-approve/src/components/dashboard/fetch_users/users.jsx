import React, { useState } from "react";
import All_Users from "./all-users";
import Approved_Users from "./approved-users";
import Pending_Users from "./pending-users";
import Denied_Users from "./denied-users";
// import BookNow from "../booknow";
import User_Roles from "./user-roles";
import { sprintf, __ } from "@wordpress/i18n";

import { useNavigate, useLocation } from "react-router-dom";

const Users = ({ usertab }) => {
  const [usertabs, setUserTab] = useState("");
  const RenderUsers = () => {
    switch (usertab) {
      case "all_users":
        return <All_Users />;
      case "approved_users":
        return <Approved_Users />;
      case "pending_users":
        return <Pending_Users />;
      case "denied_users":
        return <Denied_Users />;
      case "user_roles":
        return <User_Roles />;
      default:
        return <h2> {__("No Users", "new-user-approve")} </h2>;
    }
  };

  return (
    <div>
      <div className="users_list">
        {RenderUsers()}
        {/* <BookNow/> */}
      </div>
    </div>
  );
};

export default Users;
