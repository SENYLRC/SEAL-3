import React, { useState } from "react";
import { sprintf, __ } from "@wordpress/i18n";

const icons = require.context(
  "../../assets/icons",
  false,
  /\.(png|svg|jpe?g|)$/
);

const Jumbotron = () => {
  const AddBanner = icons(`./pro-banner-vector-laptop.svg`);

  function getPro(e) {
    window.open(
      "https://newuserapprove.com/pricing/?utm_source=plugin&utm_medium=dashboard_pro_banner",
      "_blank"
    );
    return null;
  }
  return (
    <div className="nua-pro-small-banner dashboard-screen">
      <div className="nua-popup-inner-content">
        <div className="popup-inner-img">
          <img src={AddBanner} alt="banner" />
        </div>

        <div className="nua-pro-small-banner-content">
          <h1>
            {__(
              "Authenticate User Approval Process With New User Approve",
              "new-user-approve"
            )}
          </h1>
          <p>
            {__(
              "Join over 20,000 users who are making their WordPress sites free from spam registration and fake user signups",
              "new-user-approve"
            )}
          </p>
          <div className="nua-pro-small-banner-btns">
            <button
              onClick={() =>
                window.open(
                  "https://newuserapprove.com/pricing/?utm_source=plugin&utm_medium=dashboard_pro_banner",
                  "_blank"
                )
              }
            >
              {__("Get Pro Now", "new-user-approve")}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Jumbotron;
