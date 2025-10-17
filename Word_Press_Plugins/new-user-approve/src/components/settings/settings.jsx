
// import MenuTopBar from '../dashboard/topbar';
import Settings_Tabs from "./settings-tabs";
import { ToastContainer, toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
const Settings = () => {
  return (
    <>
      {/* <MenuTopBar/> */}
      <ToastContainer />
      <Settings_Tabs />
    </>
  );
};

export default Settings;
