import { json } from "react-router-dom";

export function action_status(current_status) {
  const statuses = {
    pending: ["approve", "deny"],
    denied: ["approve"],
    approved: ["deny"],
  };
  return statuses[current_status] || [];
}

function siteUrl() {
  const site_location = siteDetail.siteUrl;
  return site_location;
}

export function user_role_dummy() {
  let user_roles = [
    {
      username: "jhon",
      current_role: "Subscriber",
      email_address: "jhon@gmail.com",
      requested_role: "Editor",
    },

    {
      username: "vince",
      current_role: "Customer",
      email_address: "vince@gmail.com",
      requested_role: "Administrator",
    },
    {
      username: "martin",
      current_role: "Subscriber",
      email_address: "martin@hotmail.com",
      requested_role: "Editor",
    },

    {
      username: "dean",
      current_role: "Contributer",
      email_address: "dean@hotmail.com",
      requested_role: "Author",
    },
    {
      username: "lauren",
      current_role: "Subscriber",
      email_address: "lauren@help.com",
      requested_role: "___",
    },
  ];

  return user_roles;
}

export function site_url() {
  return siteUrl();
}

// Get Nua Codes

export const get_invited_users = async () => {
  const request_method = "get";
  try {
    const response = await fetch(
      `${
        NUARestAPI.get_invited_users + NUARestAPI.permalink_delimeter
      }method=${request_method}`,
      {
        method: "PUT",
        headers: {
          "X-WP-Nonce": wpApiSettings.nonce,
          "Content-Type": "application/json",
        },
      }
    );
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const update_user_status = async (end_point = "", user_data = []) => {
  const endPoint = end_point;
  const userdata = user_data;
  try {
    const response = await fetch(`${NUARestAPI.update_users}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json", // Set content type to JSON
        "X-WP-Nonce": wpApiSettings.nonce,
      },
      body: JSON.stringify(userdata),
    });

    if (!response.ok) {
      throw new Error("Network response was not ok");
    }

    const data = await response.json();
    return { message: "Success", data: data };
  } catch (error) {
    return { message: "Failed", error: error.message };
  }
};

//  fetch activity log

export const get_activity_log = async () => {
  try {
    const response = await fetch(`${NUARestAPI.get_activity_log}`, {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
    });

    if (!response.ok) {
      throw new Error("Network response was not ok");
    }

    const data = await response.json();
    return { message: "Success", data: data };
  } catch (error) {
    return { message: "Failed", error: error.message };
  }
};

export const update_general_settings = async ({ generalSettings }) => {
  const request_method = "update";
  try {
    const response = await fetch(
      `${
        NUARestAPI.general_settings + NUARestAPI.permalink_delimeter
      }method=${request_method}`,
      {
        method: "PUT",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": wpApiSettings.nonce,
        },
        body: JSON.stringify(generalSettings),
      }
    );
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const get_general_settings = async () => {
  const request_method = "get";
  try {
    const response = await fetch(
      `${
        NUARestAPI.general_settings + NUARestAPI.permalink_delimeter
      }method=${request_method}`,
      {
        method: "PUT",
        headers: {
          "X-WP-Nonce": wpApiSettings.nonce,
          "Content-Type": "application/json",
        },
      }
    );
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const get_user_roles = async () => {
  try {
    const response = await fetch(`${NUARestAPI.get_user_roles}`, {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
    });

    if (!response.ok) {
      throw new Error("Network response was not ok");
    }

    const data = await response.json();
    return { message: "Success", data: data };
  } catch (error) {
    return { message: "Failed", error: error.message };
  }
};

export const update_user_role = async ({ userID, updateRole }) => {
  const userdata = {
    user_id: userID,
    new_role: updateRole,
  };

  try {
    const response = await fetch(`${NUARestAPI.update_user_role}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json", // Set content type to JSON
        "X-WP-Nonce": wpApiSettings.nonce,
      },
      body: JSON.stringify(userdata),
    });

    if (!response.ok) {
      throw new Error("Network response was not ok");
    }

    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const get_api_key = async () => {
  try {
    const response = await fetch(`${NUARestAPI.get_api_key}`, {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
    });

    if (!response.ok) {
      throw new Error("Network response was not ok");
    }

    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const update_api_key = async ({ apiKey }) => {
  const api_key = { api_key: apiKey };

  try {
    const response = await fetch(`${NUARestAPI.update_api_key}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": wpApiSettings.nonce,
      },
      body: JSON.stringify(api_key),
    });

    if (!response.ok) {
      throw new Error("Network response was not ok");
    }

    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const save_invite_codes = async ({ endpoint, inviteCode }) => {
  const end_point = endpoint;

  try {
    const response = await fetch(`${NUARestAPI.save_invitation_code}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": wpApiSettings.nonce,
      },
      body: JSON.stringify(inviteCode),
    });

    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const get_invitation_code_setttings = async () => {
  try {
    const response = await fetch(`${NUARestAPI.get_invitation_code}`, {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
      },
    });
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const update_invitation_code = async ({ endpoint, updateCode }) => {
  let request_url = "";
  if (endpoint == "update-invitation-code") {
    request_url = NUARestAPI.update_invitation_code;
  }
  try {
    const response = await fetch(`${request_url}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": wpApiSettings.nonce,
      },
      body: JSON.stringify(updateCode),
    });

    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const delete_invCode = async ({ endpoint, code_ids }) => {
  let request_url = "";
  if (endpoint === "delete-invCode") {
    request_url = NUARestAPI.delete_invCode;
  }

  // Always ensure array format
  const idsArray = Array.isArray(code_ids) ? code_ids : [code_ids];

  try {
    const response = await fetch(`${request_url}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": wpApiSettings.nonce,
      },
      body: JSON.stringify({ code_ids: idsArray }),
    });
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

// setting help

export const get_help_settings = async () => {
  try {
    const response = await fetch(`${NUARestAPI.help_settings}`, {
      method: "GET",
      headers: {
        "X-WP-Nonce": wpApiSettings.nonce,
        "Content-Type": "application/json",
      },
    });
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

// Invite Email End
// -----------------------------------------------
export const get_all_statuses_users = async (countFilter) => {
  try {
    const response = await fetch(
      `${
        NUARestAPI.all_statuses_users + NUARestAPI.permalink_delimeter
      }filter_by=${countFilter}`,
      {
        method: "GET",
        headers: {
          "X-WP-Nonce": wpApiSettings.nonce,
          "Content-Type": "application/json",
        },
      }
    );
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const save_invitation_codes = async ({ endpoint, inviteCode }) => {
  let request_url = "";
  if (endpoint == "save-invitation-codes") {
    request_url = NUARestAPI.save_invitation_codes;
  }
  try {
    const response = await fetch(`${request_url}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": wpApiSettings.nonce,
      },
      body: JSON.stringify(inviteCode),
    });

    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

// Get Nua Codes

export const get_nua_codes = async () => {
  const request_method = "get";
  try {
    const response = await fetch(
      `${
        NUARestAPI.get_nua_invite_codes + NUARestAPI.permalink_delimeter
      }method=${request_method}`,
      {
        method: "PUT",
        headers: {
          "X-WP-Nonce": wpApiSettings.nonce,
          "Content-Type": "application/json",
        },
      }
    );
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const get_remaining_uses = async () => {
  const request_method = "get";
  try {
    const response = await fetch(
      `${
        NUARestAPI.get_remaining_uses + NUARestAPI.permalink_delimeter
      }method=${request_method}`,
      {
        method: "PUT",
        headers: {
          "X-WP-Nonce": wpApiSettings.nonce,
          "Content-Type": "application/json",
        },
      }
    );
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const get_total_uses = async () => {
  const request_method = "get";
  try {
    const response = await fetch(
      `${
        NUARestAPI.get_total_uses + NUARestAPI.permalink_delimeter
      }method=${request_method}`,
      {
        method: "PUT",
        headers: {
          "X-WP-Nonce": wpApiSettings.nonce,
          "Content-Type": "application/json",
        },
      }
    );
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const get_expiry = async () => {
  const request_method = "get";
  try {
    const response = await fetch(
      `${
        NUARestAPI.get_expiry + NUARestAPI.permalink_delimeter
      }method=${request_method}`,
      {
        method: "PUT",
        headers: {
          "X-WP-Nonce": wpApiSettings.nonce,
          "Content-Type": "application/json",
        },
      }
    );
    const data = await response.json();
    console.log(data);
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const get_status = async () => {
  const request_method = "get";
  try {
    const response = await fetch(
      `${
        NUARestAPI.get_status + NUARestAPI.permalink_delimeter
      }method=${request_method}`,
      {
        method: "PUT",
        headers: {
          "X-WP-Nonce": wpApiSettings.nonce,
          "Content-Type": "application/json",
        },
      }
    );
    const data = await response.json();
    return { data: data };
  } catch (error) {
    return { error };
  }
};

export const generateAPI = (length) => {
  const characters =
    "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
  let api_key = "";
  for (let i = 0; i < length; i++) {
    api_key += characters.charAt(Math.floor(Math.random() * characters.length));
  }
  return api_key;
};

export const format_selected_values = ({ valuesList }) => {
  var selected_values = Object.entries(valuesList).map(([value, label]) => ({
    value: value,
    label: label,
  }));
  return selected_values;
};
