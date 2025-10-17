import React, {
  useImperativeHandle,
  useEffect,
  forwardRef,
  useState,
} from "react";
import { get_all_statuses_users } from "../../functions";
import { sprintf, __ } from "@wordpress/i18n";
const icons = require.context("../../assets/icons", false, /\.svg$/);
import Skeleton from "@mui/material/Skeleton";

const CounterList = forwardRef(({ countFilter }, ref) => {
  let total_request = icons(`./analytic-total-req.svg`);
  let pending_request = icons(`./analytic-pending-req.svg`);
  let approved_request = icons(`./analytic-approve-req.svg`);
  let rejected_request = icons(`./analytic-reject-req.svg`);
  const [total_users, setTotalUsers] = useState(0);
  const [pending_users, setPendingUsers] = useState(0);
  const [approved_users, setApprovedUsers] = useState(0);
  const [denied_users, setDeniedUsers] = useState(0);
  const [prevFilter, setPrevFilter] = useState("");
  const [isLoading, setIsLoading] = useState(true);

  const analytics_users = async () => {
    setIsLoading(true);
    const response = await get_all_statuses_users(countFilter);
    setTotalUsers(response.data.total);
    setPendingUsers(response.data.pending);
    setApprovedUsers(response.data.approved);
    setDeniedUsers(response.data.denied);
    setIsLoading(false);
  };

  useEffect(() => {
    analytics_users();
  }, [countFilter]);

  useImperativeHandle(ref, () => ({
    refreshCounter: analytics_users,
  }));

  return (
    <>
      <div className="nua_user_counter_list">
        <div className="recent_user_counter total_requests">
          <span className="counter__icon counter__icon--left">
            {isLoading ? (
              <Skeleton variant="circular" width={70} height={70} />
            ) : (
              <img width="70px" src={total_request} />
            )}
          </span>
          <div className="recent_user_number_status">
            <span className="counter__icon counter__icon--right">
              {" "}
              {__("Total Requests", "new-user-approve")}{" "}
            </span>
            <span className="counter__number">
              {isLoading ? <Skeleton width={40} /> : total_users}
            </span>
          </div>
        </div>

        <div className="recent_user_counter pending_requests">
          <span className="counter__icon counter__icon--left">
            {isLoading ? (
              <Skeleton variant="circular" width={70} height={70} />
            ) : (
              <img width="70px" src={pending_request} />
            )}
          </span>
          <div className="recent_user_number_status">
            <span className="counter__icon counter__icon--right">
              {" "}
              {__("Pending Requests", "new-user-approve")}{" "}
            </span>
            <span className="counter__number">
              {isLoading ? <Skeleton width={40} /> : pending_users}
            </span>
          </div>
        </div>

        <div className="recent_user_counter approved_requests">
          <span className="counter__icon counter__icon--left">
            {isLoading ? (
              <Skeleton variant="circular" width={70} height={70} />
            ) : (
              <img width="70px" src={approved_request} />
            )}
          </span>
          <div className="recent_user_number_status">
            <span className="counter__icon counter__icon--right">
              {" "}
              {__("Approved Requests", "new-user-approve")}{" "}
            </span>
            <span className="counter__number">
              {isLoading ? <Skeleton width={40} /> : approved_users}
            </span>
          </div>
        </div>

        <div className="recent_user_counter denied_requests">
          <span className="counter__icon counter__icon--left">
            {isLoading ? (
              <Skeleton variant="circular" width={70} height={70} />
            ) : (
              <img width="70px" src={rejected_request} />
            )}
          </span>
          <div className="recent_user_number_status">
            <span className="counter__icon counter__icon--right">
              {" "}
              {__("Rejected Requests", "new-user-approve")}{" "}
            </span>
            <span className="counter__number">
              {isLoading ? <Skeleton width={40} /> : denied_users}
            </span>
          </div>
        </div>
      </div>
    </>
  );
});

export default CounterList;
