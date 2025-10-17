import React, { useState, useEffect } from "react";
import InputLabel from "@mui/material/InputLabel";
import MenuItem from "@mui/material/MenuItem";
import FormControl from "@mui/material/FormControl";
import Select, { SelectChangeEvent } from "@mui/material/Select";
import Box from "@mui/material/Box";
import { sprintf, __ } from "@wordpress/i18n";
// import Update_User_Role from './fetch_users/update-user-role';

const userFilter = ({ resetFilter, setFilterBy }) => {
  const [filter, setFilter] = useState("");
  const [usersdata, setUsersData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const handleChange = (event) => {
    const filter_by = event.target.value;
    setFilter(event.target.value);
    setFilterBy(event.target.value);
  };
  return (
    <>
      <Box sx={{ minWidth: 120, border: "0px solid" }}>
        <FormControl fullWidth>
          <div className="select-container">
            <select
              name="recent-users-filter"
              id="users-filter"
              value={resetFilter == true ? "30 days ago" : filter}
              onChange={handleChange}
            >
              <option value={"30 days ago"}>
                {" "}
                {__("Last 30 days", "new-user-approve")}{" "}
              </option>
              <option value={"1 week ago"}>
                {" "}
                {__("By a week", "new-user-approve")}{" "}
              </option>
              <option value={"today"}>
                {" "}
                {__("By today", "new-user-approve")}{" "}
              </option>
              <option value={"yesterday"}>
                {" "}
                {__("By yesterday", "new-user-approve")}{" "}
              </option>
            </select>
          </div>
        </FormControl>
      </Box>
    </>
  );
};

export default userFilter;
