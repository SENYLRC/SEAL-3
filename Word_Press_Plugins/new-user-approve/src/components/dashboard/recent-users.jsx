import React, { useState, useEffect } from "react";
import axios from "axios";
import InputLabel from "@mui/material/InputLabel";
import MenuItem from "@mui/material/MenuItem";
import FormControl from "@mui/material/FormControl";
import Select, { SelectChangeEvent } from "@mui/material/Select";
import Box from "@mui/material/Box";

// custom Components
import Recent_User_Table from "./recent_users_table";
import Get_Pro_Banner from "./get-pro-banner";
import Guides_Doc from "./guides-doc";

const Recent_Users = () => {
  const [age, setAge] = useState("");
  const [usersdata, setUsersData] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  return (
    <>
      <Recent_User_Table />
      <Get_Pro_Banner banner_type="full" />
      <Guides_Doc />
    </>
  );
};

export default Recent_Users;
