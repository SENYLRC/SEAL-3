import React, { useState, useEffect, useRef } from "react";
import Alert from "@mui/material/Alert";
import Pagination from "@mui/material/Pagination";
import Stack from "@mui/material/Stack";
import axios from "axios";
import EditInvitationCode from "../invitation-code/edit-invitation-code";
import "react-toastify/dist/ReactToastify.css";
import { toast, ToastContainer } from "react-toastify";
import CloseIcon from "@mui/icons-material/Close";
const images = require.context("../../assets/images", false, /\.(png|jpe?g|)$/);
import PopupModal from "../../components/popup-modal";
import {
  Box,
  Button,
  TextField,
  Input,
  Switch,
  TableContainer,
  Dialog,
  DialogTitle,
  DialogContent,
  DialogActions,
  Typography,
  Table,
  TableHead,
  TableRow,
  TableCell,
  TableBody,
  IconButton,
  Paper,
  Chip,
  MenuItem,
  FormControl,
  InputLabel,
  Checkbox,
  Select,
} from "@mui/material";
import Skeleton from "@mui/material/Skeleton";
import { save_invitation_codes } from "../../functions";
import { delete_invCode } from "../../functions";
import { sprintf, __ } from "@wordpress/i18n";
import ImportCodes from "./import-codes";
import InvitationEmail from "./invitation-email";
import {
  BrowserRouter as Router,
  Routes,
  Route,
  useParams,
  Link,
  useNavigate,
  useLocation,
} from "react-router-dom";
// import BookNow from "../dashboard/booknow";
import Add_Code from "./add-code-subtabs";

const Add_Code_SubTabs = () => {
  const [isPopupVisible, setPopupVisible] = useState(false);
  // manual generate
  const [activeTab, setActiveTab] = useState("manual-code");
  const [expiryDate, setExpiryDate] = useState(null);
  const [InviteCodes, setInviteCodes] = useState("");
  const [usageLimit, setUsageLimit] = useState(1);
  const [usageLeft, setusageLeft] = useState(1);
  const [usageData, setUsageData] = useState([]);

  const [codeUpdatedMessage, setCodeUpdatedMessage] = useState("");
  const [codeFailedMessage, setcodeFailedMessage] = useState("");
  const [codeStatus, setCodeStatus] = useState("");
  const [codeExists, setCodeExists] = useState("");
  const [searchTerm, setSearchTerm] = useState("");

  // auto generate
  const [codePrefix, setCodePerfix] = useState("");
  const [codeSuffix, setCodeSuffix] = useState("");
  const [codeQuantity, setCodeQuantity] = useState("");
  const [codeUsage, setCodeUsage] = useState("");
  const [autoCodeDate, setAutoCodeDate] = useState("");
  const [incorrectDate, setIncorrectDate] = useState(false);

  const [openAddCodeModal, setOpenAddCodeModal] = useState(false);
  const [openImportModal, setOpenImportModal] = useState(false);
  const [openAutoGenerateModal, setOpenAutoGenerateModal] = useState(false);
  const [openSendEmailModal, setOpenSendEmailModal] = useState(false);
  const [openViewModal, setOpenViewModal] = useState(false);
  const [openEditModal, setOpenEditModal] = useState(false);
  const [openDeleteModal, setOpenDeleteModal] = useState(false);
  const [selectedCodes, setSelectedCodes] = useState([]); // for bulk delete
  const [selectedCodeId, setSelectedCodeId] = useState(null); // for single delete

  const handleOpenAddCodes = () => setOpenAddCodeModal(true);
  const handleCloseAddCodes = () => setOpenAddCodeModal(false);

  const handleOpenImportCodes = () => setOpenImportModal(true);
  const handleCloseImportCodes = () => setOpenImportModal(false);

  const handleOpenAutoGenerate = () => setOpenAutoGenerateModal(true);
  const handleCloseAutoGenerate = () => setOpenAutoGenerateModal(false);

  const handleOpenSendEmail = () => setOpenSendEmailModal(true);
  const handleCloseSendEmail = () => setOpenSendEmailModal(false);

  const importRef = useRef();
  const emailRef = useRef();
  const editRef = useRef();

  const handleSelectAll = (e) => {
    if (e.target.checked) {
      setSelectedCodes(rows.map((row) => row.code_id));
    } else {
      setSelectedCodes([]);
    }
  };

  const handleSelectOne = (codeId) => {
    setSelectedCodes((prev) =>
      prev.includes(codeId)
        ? prev.filter((id) => id !== codeId)
        : [...prev, codeId]
    );
  };

  // Open bulk delete modal
  const handleOpenBulkDeleteModal = () => {
    if (selectedCodes.length > 0) {
      setOpenDeleteModal(true);
    }
  };

  // Bulk delete confirmation
  const handleBulkDeleteConfirmation = async () => {
    try {
      setLoading(true);

      // Prepare code IDs array depending on single or bulk delete
      const codeIdsToDelete = selectedCodeId ? [selectedCodeId] : selectedCodes;

      if (!codeIdsToDelete || codeIdsToDelete.length === 0) {
        toast.error(
          __("No code IDs provided for deletion", "new-user-approve"),
          { position: "bottom-right" }
        );
        setLoading(false);
        return;
      }

      const response = await delete_invCode({
        endpoint: "delete-invCode",
        code_ids: codeIdsToDelete,
      });

      const status = response?.data?.status;
      const message = response?.data?.message || "Unknown error";

      if (status === "Success") {
        setRows((prevRows) =>
          prevRows.filter((row) => !codeIdsToDelete.includes(row.code_id))
        );
        setTotalRows((prevTotal) => prevTotal - codeIdsToDelete.length);

        // Clear the selections after delete
        setSelectedCodes([]);
        setSelectedCodeId(null);

        toast.success(__(message, "new-user-approve"), {
          position: "bottom-right",
          autoClose: 2000,
        });
        const newTotalRows = totalRows - codeIdsToDelete.length;
        const newPageCount = Math.ceil(newTotalRows / rowsPerPage);

        if (page > newPageCount) {
          setPage(newPageCount); // This will trigger useEffect/fetchData automatically
        } else {
          await fetchData();
        }
      } else {
        throw new Error(message);
      }
    } catch (error) {
      toast.error(
        __(error.message || "An error occurred", "new-user-approve"),
        { position: "bottom-right", autoClose: 2000 }
      );
    } finally {
      setLoading(false);
      setOpenDeleteModal(false);
    }
  };

  const handleOpenDeleteModal = (code_id) => {
    setSelectedCodeId(code_id);
    setOpenDeleteModal(true);
  };

  const handleCloseDeleteModal = () => {
    setOpenDeleteModal(false);
    setSelectedCodeId(null);
  };

  const handleDeleteConfirmation = async () => {
    if (!selectedCodeId) return;

    try {
      setLoading(true);
      const response = await delete_invCode({
        endpoint: "delete-invCode",
        code_id: selectedCodeId,
      });

      const status = response?.data?.status;
      const message = response?.data?.message || "Unknown error";

      if (status === "Success") {
        setRows((prevRows) =>
          prevRows.filter((row) => row.code_id !== selectedCodeId)
        );
        setTotalRows((prevTotal) => prevTotal - 1);

        toast.success(__(message, "new-user-approve"), {
          position: "bottom-right",
          autoClose: 2000,
          hideProgressBar: false,
          closeOnClick: true,
          pauseOnHover: true,
          draggable: true,
        });
      } else {
        throw new Error(message);
      }
    } catch (error) {
      toast.error(
        __(error.message || "An error occurred", "new-user-approve"),
        {
          position: "bottom-right",
          autoClose: 2000,
          hideProgressBar: false,
          closeOnClick: true,
          pauseOnHover: true,
          draggable: true,
        }
      );
    } finally {
      setLoading(false);
    }

    if (typeof handleCloseDeleteModal === "function") {
      handleCloseDeleteModal();
    }
  };

  const handleOpenViewModal = (row) => {
    setSelectedRow(row);
    setOpenViewModal(true);
  };

  const handleCloseViewModal = () => {
    setSelectedRow(null);
    setOpenViewModal(false);
  };

  const handleOpenEditModal = (row) => {
    setSelectedRow(row);
    setOpenEditModal(true);
  };

  const handleCloseEditModal = () => {
    setSelectedRow(null);
    setOpenEditModal(false);
  };

  const [rows, setRows] = useState([]);
  const [totalRows, setTotalRows] = useState(0);
  const [selectedRow, setSelectedRow] = useState(null);
  const [page, setPage] = useState(1);
  const [rowsPerPage, setRowsPerPage] = useState(10);

  // loading
  const [loading, setLoading] = useState(false);

  // navigation
  const navigate = useNavigate();
  const location = useLocation();
  const currentTab = location.pathname.split("/")[2] || "tab=all-codes";

  const get_codes = async () => {
    try {
      const response = await axios.get(
        `${
          NUARestAPI.get_nua_invite_codes + NUARestAPI.permalink_delimeter
        }page=${page}&limit=${rowsPerPage}&search=${searchTerm}`,
        {
          headers: {
            "X-WP-Nonce": wpApiSettings.nonce,
          },
        }
      );

      const { codes, total } = response.data;
      setRows(codes);
      setTotalRows(total);
    } catch (error) {
      console.error("Error fetching codes:", error);
    }
  };

  const get_remaining_uses = async () => {
    try {
      const response = await axios.get(
        `${NUARestAPI.get_remaining_uses + NUARestAPI.permalink_delimeter}`,
        {
          headers: {
            "X-WP-Nonce": wpApiSettings.nonce,
          },
        }
      );

      const data = response.data;

      const formattedUsage = Array.isArray(data)
        ? data.map((item) => ({
            code_id: item.code_id,
            uses_left: item.uses_left,
            total_remaining: item.usage_limit, // usage_limit renamed as total_remaining
          }))
        : [];

      setUsageData(data);
    } catch (error) {
      console.error("Error fetching remaining uses:", error);
    }
  };

  const get_expiry = async () => {
    try {
      const response = await axios.get(
        `${NUARestAPI.get_expiry + NUARestAPI.permalink_delimeter}`,
        {
          headers: {
            "X-WP-Nonce": wpApiSettings.nonce,
          },
        }
      );
      const data = response.data;
      setExpiryData(data);
    } catch (error) {
      console.error("Error fetching expiry date:", error);
    }
  };

  const get_status = async () => {
    try {
      const response = await axios.get(
        `${NUARestAPI.get_status + NUARestAPI.permalink_delimeter}`,
        {
          headers: {
            "X-WP-Nonce": wpApiSettings.nonce,
          },
        }
      );
      const data = response.data;
      if (Array.isArray(data)) {
        setStatusList(data);
      } else {
        console.error("Expected array from get_status response");
      }
    } catch (error) {
      console.error("Error fetching status list:", error);
    }
  };

  const get_total_uses = async () => {
    try {
      const response = await axios.get(
        `${NUARestAPI.get_total_uses + NUARestAPI.permalink_delimeter}`,
        {
          headers: {
            "X-WP-Nonce": wpApiSettings.nonce,
          },
        }
      );
      const data = response.data;
      setTotalUses(data);
    } catch (error) {
      console.error("Error fetching total uses:", error);
    }
  };

  const get_invited_users = async () => {
    try {
      const response = await axios.get(
        `${NUARestAPI.get_invited_users + NUARestAPI.permalink_delimeter}`,
        {
          headers: {
            "X-WP-Nonce": wpApiSettings.nonce,
          },
        }
      );
      const data = response.data;
      setinvitedUsers(data);
    } catch (error) {
      console.error("Error fetching total uses:", error);
    }
  };

  const [totalUses, setTotalUses] = useState({});
  const [expiryData, setExpiryData] = useState([]);
  const [invitedData, setinvitedUsers] = useState([]);
  const [selectedStatus, setSelectedStatus] = useState("Active");
  const [statusList, setStatusList] = useState([]);

  const fetchData = async () => {
    setLoading(true);

    try {
      await Promise.all([
        get_codes(),
        get_remaining_uses(),
        get_total_uses(),
        get_expiry(),
        get_status(),
        get_invited_users(),
      ]);
    } catch (error) {
      console.error("Error fetching data", error);
    } finally {
      setLoading(false);
      setSelectedCodes([]);
      setSelectedCodeId(null);
    }
  };

  useEffect(() => {
    if (currentTab == "tab=all-codes") {
      navigate("tab=all-codes");
    }
    fetchData();
  }, [page, rowsPerPage, searchTerm]);

  const pageCount = Math.ceil(totalRows / rowsPerPage);

  // get initial number of page
  const startIndex = (page - 1) * rowsPerPage + 1;
  // get total number of data
  const endIndex = Math.min(page * rowsPerPage, totalRows);

  const handleRowsPerPageChange = (event) => {
    setRowsPerPage(event.target.value);
    setPage(1); // reset to first page when rows per page changes
  };

  const getInvitedUsersForCode = (codeId) => {
    return invitedData.filter((user) => user.code_id === codeId);
  };

  const handleAddCodes = (event) => {
    const { value } = event.target;
    setInviteCodes(value);
  };

  const handleUsageLimit = (event) => {
    const { value } = event.target;
    setusageLeft(value);
    setUsageLimit(value);
  };

  const handleDateChange = (event) => {
    const { value } = event.target;
    if (getCurrentDate() > value) {
      setIncorrectDate(true);
    } else {
      setIncorrectDate(false);
    }
    setExpiryDate(value);
  };

  const handleSubmit = async (event) => {
    if (incorrectDate === true) {
      return;
    }

    if (usageLimit <= 0) {
      return;
    }

    const endpoint = "save-invitation-codes";
    const inviteCode = {
      codes: InviteCodes,
      usage_limit: usageLimit,
      usageLeft: usageLeft,
      expiry_date: expiryDate,
      code_status: selectedStatus,
    };

    try {
      setLoading(true);
      const response = await save_invitation_codes({ endpoint, inviteCode });

      if (response.data.status == "success") {
        toast.success(response.data.message, {
          position: "bottom-right",
          autoClose: 2000,
          hideProgressBar: false,
          closeOnClick: true,
          pauseOnHover: true,
          draggable: true,
          progress: undefined,
        });
        const newCount = Array.isArray(response.data.code_id)
          ? response.data.code_id.length
          : 0;
        setTotalRows((prevTotal) => prevTotal + newCount);
        setCodeExists(response.data.code_error);
        setInviteCodes("");
        setUsageLimit(1);
        setusageLeft(1);
        let formattedCodes = [];

        if (Array.isArray(response.data.codes)) {
          formattedCodes = response.data.codes.map((code, index) => ({
            invitation_code: code,
            code_id: response.data.code_id[index] || response.data.code_id,
            usage_limit: response.data.usage_limit,
            uses_left: response.data.usageLeft,
          }));

          setRows((prev) => [...formattedCodes, ...prev]);

          // Set expiry data (same date applied to all new codes)
          if (response.data.expiry_date) {
            const formattedExpiry = formattedCodes.map((code) => ({
              code_id: code.code_id,
              expiry_data: response.data.expiry_date,
            }));
            setExpiryData((prev) => [...formattedExpiry, ...prev]);
          }

          if (response.data.code_status) {
            const formattedStatus = formattedCodes.map((code) => ({
              code_id: code.code_id,
              code_status: response.data.code_status,
            }));

            setStatusList((prev) => [...formattedStatus, ...prev]);
          }

          if (response.data.usageLeft) {
            const formattedLeft = formattedCodes.map((code) => ({
              code_id: code.code_id,
              uses_left: response.data.usageLeft,
              usage_limit: response.data.usage_limit,
            }));
            setUsageData((prev) => [...formattedLeft, ...prev]);
          }
        }
        handleCloseAddCodes();
      }

      if (response.data.status == "error") {
        toast.error(response.data.message, {
          position: "bottom-right",
          autoClose: 2000,
          hideProgressBar: false,
          closeOnClick: true,
          pauseOnHover: true,
          draggable: true,
          progress: undefined,
        });
      }
    } finally {
      setLoading(false);
      let timer = setTimeout(() => {
        setCodeStatus("");
        setCodeExists("");
      }, 3000);
      return () => clearTimeout(timer);
    }
  };

  const fetchAutoCodes = async () => {
    try {
      const [response, response2, response3, response4] = await Promise.all([
        get_codes(),
        get_expiry(),
        get_status(),
        get_remaining_uses(),
      ]);
    } catch (error) {
      console.error("Failed to fetch auto codes or expiry data:", error);
    }
  };

  const handleAutoSubmit = async (event) => {
    setPopupVisible(true);
  };

  // auto code generate handlers
  const handleCodePrefix = (event) => {
    setPopupVisible(true);
  };

  const handleCodeSuffix = (event) => {
    const { value } = event.target;
    setPopupVisible(true);
  };

  const handleCodeQuantity = (event) => {
    const { value } = event.target;
    setPopupVisible(true);
  };

  const handleAutoCodeUsage = (event) => {
    const { value } = event.target;
    setPopupVisible(true);
  };

  const handleAutoCodeDate = (event) => {
    setPopupVisible(true);
  };

  const getCurrentDate = () => {
    const today = new Date();
    return today.toISOString().split("T")[0];
  };

  const handleImportClick = async () => {
    if (importRef.current) {
      setLoading(true);
      await importRef.current.handleImport({
        target: { name: "import_csv" },
        currentTarget: { name: "import_csv" },
      });
      setLoading(false); // You could skip this if the child already sets its own loading state visually
    }
  };

  const handleSendEmail = async () => {
    const child = emailRef.current;
    if (child) {
      setLoading(true); // optional - preload
      await child.handleEmail();
      setLoading(child.isLoading); // this might not be instant; better to listen to events or use Option 1
    }
  };

  const handleEditClick = () => {
    editRef.current?.handleEditSubmit({});
  };

  const today = new Date().toISOString().split("T")[0];

  let codeIcon = (
    <svg
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M19 12.998H13V18.998H11V12.998H5V10.998H11V4.99799H13V10.998H19V12.998Z"
        fill="#242424"
      />
    </svg>
  );
  let generateIcon = (
    <svg
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M10 11H7.101L7.102 10.991C7.23367 10.352 7.48914 9.74488 7.854 9.20399C8.39829 8.40165 9.16203 7.77295 10.054 7.39299C10.3573 7.26432 10.67 7.16732 10.992 7.10199C11.658 6.96757 12.344 6.96757 13.01 7.10199C13.9665 7.29931 14.8443 7.77245 15.535 8.46299L16.951 7.05099C16.3134 6.41193 15.5582 5.90223 14.727 5.54999C14.3031 5.3711 13.8627 5.23444 13.412 5.14199C12.4818 4.95357 11.5232 4.95357 10.593 5.14199C10.1419 5.2347 9.70115 5.3717 9.277 5.55099C8.02753 6.08109 6.95793 6.96108 6.197 8.08499C5.68539 8.84311 5.32731 9.69415 5.143 10.59C5.115 10.725 5.1 10.863 5.08 11H2L6 15L10 11ZM14 13H16.899L16.898 13.008C16.6363 14.2896 15.8809 15.4168 14.795 16.146C14.2554 16.5132 13.6478 16.7688 13.008 16.898C12.3424 17.0323 11.6566 17.0323 10.991 16.898C10.352 16.7663 9.7449 16.5108 9.204 16.146C8.93836 15.9668 8.69055 15.7626 8.464 15.536L7.05 16.95C7.68799 17.5888 8.44354 18.0982 9.275 18.45C9.699 18.63 10.142 18.767 10.59 18.858C11.5199 19.0463 12.4781 19.0463 13.408 18.858C15.2006 18.486 16.7774 17.4295 17.803 15.913C18.314 15.1554 18.6717 14.3051 18.856 13.41C18.883 13.275 18.899 13.137 18.919 13H22L18 8.99999L14 13Z"
        fill="#242424"
      />
    </svg>
  );
  let importIcon = (
    <svg
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M13 13.175L16.243 9.933L17.657 11.347L12 17.004L6.343 11.347L7.757 9.933L11 13.175V2H13V13.175ZM4 16H6V20H18V16H20V20C20 21.1 19.1 22 18 22H6C4.9 22 4 21.037 4 20V16Z"
        fill="#242424"
      />
    </svg>
  );
  let sendIcon = (
    <svg
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M5.38182 6.75H18.6182L12 12.1645L5.38182 6.75ZM3.81818 17.25V7.76675L9.39545 12.3299L6.59091 14.625H7.99455L10.0973 12.9048L12 14.4605L13.9027 12.9048L16.0055 14.625H17.4091L14.6045 12.3299L20.1818 7.76675V17.25H3.81818ZM2 5V19H22V5H2Z"
        fill="#242424"
      />
    </svg>
  );
  let searchIcon = (
    <svg
      width="24"
      height="24"
      viewBox="0 0 24 24"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M10 18C11.775 17.9998 13.4989 17.4056 14.897 16.312L19.293 20.708L20.707 19.294L16.311 14.898C17.4051 13.4997 17.9997 11.7755 18 10C18 5.589 14.411 2 10 2C5.589 2 2 5.589 2 10C2 14.411 5.589 18 10 18ZM10 4C13.309 4 16 6.691 16 10C16 13.309 13.309 16 10 16C6.691 16 4 13.309 4 10C4 6.691 6.691 4 10 4Z"
        fill="#9DADC1"
      />
    </svg>
  );

  let viewIcon = (
    <svg
      width="16"
      height="16"
      viewBox="0 0 16 16"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M14.3627 7.36331C14.5653 7.64731 14.6667 7.78998 14.6667 7.99998C14.6667 8.21065 14.5653 8.35265 14.3627 8.63665C13.452 9.91398 11.126 12.6666 8.00001 12.6666C4.87334 12.6666 2.54801 9.91331 1.63734 8.63665C1.43468 8.35265 1.33334 8.20998 1.33334 7.99998C1.33334 7.78931 1.43468 7.64731 1.63734 7.36331C2.54801 6.08598 4.87401 3.33331 8.00001 3.33331C11.1267 3.33331 13.452 6.08665 14.3627 7.36331Z"
        stroke="#242424"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
      <path
        d="M10 8C10 7.46957 9.78929 6.96086 9.41421 6.58579C9.03914 6.21071 8.53043 6 8 6C7.46957 6 6.96086 6.21071 6.58579 6.58579C6.21071 6.96086 6 7.46957 6 8C6 8.53043 6.21071 9.03914 6.58579 9.41421C6.96086 9.78929 7.46957 10 8 10C8.53043 10 9.03914 9.78929 9.41421 9.41421C9.78929 9.03914 10 8.53043 10 8Z"
        stroke="#242424"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );

  let editIcon = (
    <svg
      width="16"
      height="16"
      viewBox="0 0 16 16"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M9.99999 4.00002L12 6.00002M8.66666 13.3334H14M3.33332 10.6667L2.66666 13.3334L5.33332 12.6667L13.0573 4.94269C13.3073 4.69265 13.4477 4.35357 13.4477 4.00002C13.4477 3.64647 13.3073 3.30739 13.0573 3.05736L12.9427 2.94269C12.6926 2.69273 12.3535 2.55231 12 2.55231C11.6464 2.55231 11.3074 2.69273 11.0573 2.94269L3.33332 10.6667Z"
        stroke="#242424"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );

  let deleteIcon = (
    <svg
      width="16"
      height="16"
      viewBox="0 0 16 16"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M5.46667 2.75198H5.33334C5.40667 2.75198 5.46667 2.69438 5.46667 2.62398V2.75198ZM5.46667 2.75198H10.5333V2.62398C10.5333 2.69438 10.5933 2.75198 10.6667 2.75198H10.5333V3.90398H11.7333V2.62398C11.7333 2.05918 11.255 1.59998 10.6667 1.59998H5.33334C4.74501 1.59998 4.26667 2.05918 4.26667 2.62398V3.90398H5.46667V2.75198ZM13.8667 3.90398H2.13334C1.83834 3.90398 1.60001 4.13278 1.60001 4.41598V4.92798C1.60001 4.99838 1.66001 5.05598 1.73334 5.05598H2.74001L3.15167 13.424C3.17834 13.9696 3.64834 14.4 4.21667 14.4H11.7833C12.3533 14.4 12.8217 13.9712 12.8483 13.424L13.26 5.05598H14.2667C14.34 5.05598 14.4 4.99838 14.4 4.92798V4.41598C14.4 4.13278 14.1617 3.90398 13.8667 3.90398ZM11.655 13.248H4.34501L3.94167 5.05598H12.0583L11.655 13.248Z"
        fill="#BD5E5E"
      />
    </svg>
  );

  let deleteConfirmation = (
    <svg
      width="68"
      height="68"
      viewBox="0 0 68 68"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect width="68" height="68" rx="34" fill="#FFEDED" />
      <path
        d="M49.6977 43.2809L36.2266 18.3269C35.2718 16.5577 32.7282 16.5577 31.7726 18.3269L18.3023 43.2809C18.095 43.665 17.991 44.0962 18.0006 44.5323C18.0102 44.9684 18.133 45.3946 18.357 45.7693C18.581 46.1439 18.8985 46.4542 19.2786 46.67C19.6587 46.8857 20.0884 46.9994 20.5257 47H47.4703C47.908 47.0001 48.3382 46.8868 48.7188 46.6714C49.0995 46.456 49.4176 46.1457 49.642 45.771C49.8665 45.3962 49.9896 44.9697 49.9994 44.5333C50.0091 44.0968 49.9052 43.6653 49.6977 43.2809ZM34 43.1367C33.6873 43.1367 33.3817 43.0442 33.1217 42.8709C32.8618 42.6976 32.6591 42.4514 32.5395 42.1632C32.4198 41.8751 32.3885 41.558 32.4495 41.2522C32.5105 40.9463 32.6611 40.6653 32.8822 40.4448C33.1033 40.2242 33.3849 40.0741 33.6916 40.0132C33.9982 39.9524 34.3161 39.9836 34.605 40.1029C34.8938 40.2223 35.1407 40.4244 35.3144 40.6837C35.4881 40.943 35.5808 41.2479 35.5808 41.5598C35.5808 41.978 35.4143 42.3791 35.1178 42.6748C34.8214 42.9705 34.4193 43.1367 34 43.1367ZM35.7168 27.2773L35.2631 36.8962C35.2631 37.2308 35.1298 37.5516 34.8927 37.7882C34.6555 38.0248 34.3338 38.1577 33.9984 38.1577C33.663 38.1577 33.3413 38.0248 33.1042 37.7882C32.867 37.5516 32.7337 37.2308 32.7337 36.8962L32.28 27.2812C32.2699 27.0514 32.3061 26.822 32.3867 26.6065C32.4673 26.391 32.5906 26.194 32.7492 26.027C32.9078 25.8601 33.0984 25.7267 33.3098 25.6348C33.5212 25.5429 33.7489 25.4945 33.9795 25.4922H33.996C34.2282 25.4921 34.4579 25.5389 34.6714 25.6298C34.8849 25.7207 35.0777 25.8538 35.2382 26.021C35.3988 26.1883 35.5236 26.3862 35.6053 26.603C35.687 26.8197 35.7239 27.0507 35.7136 27.282L35.7168 27.2773Z"
        fill="#C9605C"
      />
    </svg>
  );

  let notFound = (
    <svg
      width="68"
      height="48"
      viewBox="0 0 68 48"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <g clipPath="url(#clip0_774_1213)">
        <path
          d="M30.4245 6.77262H23.8308V0.504771C23.8308 0.225642 23.6037 0 23.3227 0H5.52032C5.23934 0 5.01221 0.225642 5.01221 0.504771V9.85306C5.01221 10.1322 5.23934 10.3578 5.52032 10.3578H28.5166L30.8165 7.59997C31.0908 7.2707 30.8552 6.77262 30.4245 6.77262Z"
          fill="url(#paint0_linear_774_1213)"
        />
        <path
          d="M36.3433 3.5499L29.4266 11.8435H2.02731C1.15915 11.8435 0.465961 12.5622 0.501293 13.423L1.89776 46.2648C1.93309 47.0772 2.6044 47.7173 3.42209 47.7173H64.0774C64.8934 47.7173 65.5648 47.0788 65.6018 46.2699L67.4979 4.58451C67.5366 3.72206 66.8434 3 65.9736 3H37.516C37.0617 3 36.631 3.20057 36.3416 3.54823L36.3433 3.5499Z"
          fill="url(#paint1_linear_774_1213)"
        />
      </g>
      <defs>
        <linearGradient
          id="paint0_linear_774_1213"
          x1="17.9732"
          y1="0"
          x2="17.9732"
          y2="10.3578"
          gradientUnits="userSpaceOnUse"
        >
          <stop stopColor="#ECF6EE" />
          <stop offset="1" stopColor="#E1EEE3" />
        </linearGradient>
        <linearGradient
          id="paint1_linear_774_1213"
          x1="33.9997"
          y1="3"
          x2="33.9997"
          y2="47.7173"
          gradientUnits="userSpaceOnUse"
        >
          <stop stopColor="#ECF6EE" />
          <stop offset="1" stopColor="#E1EEE3" />
        </linearGradient>
        <clipPath id="clip0_774_1213">
          <rect
            width="67"
            height="48"
            fill="white"
            transform="translate(0.5)"
          />
        </clipPath>
      </defs>
    </svg>
  );
  let proIcon = (
    <svg
      width="33"
      height="16"
      viewBox="0 0 33 16"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <rect width="33" height="16" rx="4" fill="#FFBF46" />
      <path
        d="M8.608 11V5.4H10.688C11.0827 5.4 11.432 5.48 11.736 5.64C12.04 5.79467 12.2773 6.01333 12.448 6.296C12.6187 6.57333 12.704 6.896 12.704 7.264C12.704 7.62667 12.6213 7.94933 12.456 8.232C12.2907 8.51467 12.064 8.73867 11.776 8.904C11.488 9.064 11.1547 9.144 10.776 9.144H9.704V11H8.608ZM9.704 8.136H10.752C10.9973 8.136 11.1973 8.056 11.352 7.896C11.512 7.73067 11.592 7.52 11.592 7.264C11.592 7.008 11.504 6.8 11.328 6.64C11.1573 6.48 10.936 6.4 10.664 6.4H9.704V8.136ZM13.4283 11V5.4H15.5083C15.903 5.4 16.2523 5.47733 16.5563 5.632C16.8603 5.78667 17.0976 6 17.2683 6.272C17.439 6.53867 17.5243 6.85067 17.5243 7.208C17.5243 7.56 17.4336 7.87467 17.2523 8.152C17.0763 8.424 16.8336 8.63733 16.5243 8.792C16.215 8.94133 15.863 9.016 15.4683 9.016H14.5243V11H13.4283ZM16.5083 11L15.2123 8.752L16.0523 8.152L17.7483 11H16.5083ZM14.5243 8.016H15.5163C15.6816 8.016 15.8283 7.98133 15.9563 7.912C16.0896 7.84267 16.1936 7.74667 16.2683 7.624C16.3483 7.50133 16.3883 7.36267 16.3883 7.208C16.3883 6.968 16.3003 6.77333 16.1243 6.624C15.9536 6.47467 15.7323 6.4 15.4603 6.4H14.5243V8.016ZM21.1446 11.096C20.5792 11.096 20.0779 10.9733 19.6406 10.728C19.2086 10.4773 18.8699 10.136 18.6246 9.704C18.3792 9.26667 18.2566 8.768 18.2566 8.208C18.2566 7.63733 18.3792 7.136 18.6246 6.704C18.8699 6.26667 19.2059 5.92533 19.6326 5.68C20.0646 5.43467 20.5606 5.312 21.1206 5.312C21.6859 5.312 22.1819 5.43733 22.6086 5.688C23.0406 5.93333 23.3792 6.27467 23.6246 6.712C23.8699 7.144 23.9926 7.64267 23.9926 8.208C23.9926 8.768 23.8699 9.26667 23.6246 9.704C23.3846 10.136 23.0486 10.4773 22.6166 10.728C22.1899 10.9733 21.6992 11.096 21.1446 11.096ZM21.1446 10.096C21.4859 10.096 21.7846 10.016 22.0406 9.856C22.3019 9.69067 22.5046 9.46667 22.6486 9.184C22.7979 8.90133 22.8726 8.576 22.8726 8.208C22.8726 7.83467 22.7979 7.50667 22.6486 7.224C22.4992 6.94133 22.2939 6.72 22.0326 6.56C21.7712 6.39467 21.4672 6.312 21.1206 6.312C20.7846 6.312 20.4832 6.39467 20.2166 6.56C19.9552 6.72 19.7499 6.94133 19.6006 7.224C19.4512 7.50667 19.3766 7.83467 19.3766 8.208C19.3766 8.576 19.4512 8.90133 19.6006 9.184C19.7499 9.46667 19.9579 9.69067 20.2246 9.856C20.4912 10.016 20.7979 10.096 21.1446 10.096Z"
        fill="#664C1C"
      />
    </svg>
  );

  let bulkDelete = (
    <svg
      width="16"
      height="16"
      viewBox="0 0 16 16"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
    >
      <path
        d="M5.46664 2.7521H5.33331C5.40664 2.7521 5.46664 2.6945 5.46664 2.6241V2.7521ZM5.46664 2.7521H10.5333V2.6241C10.5333 2.6945 10.5933 2.7521 10.6666 2.7521H10.5333V3.9041H11.7333V2.6241C11.7333 2.0593 11.255 1.6001 10.6666 1.6001H5.33331C4.74498 1.6001 4.26664 2.0593 4.26664 2.6241V3.9041H5.46664V2.7521ZM13.8666 3.9041H2.13331C1.83831 3.9041 1.59998 4.1329 1.59998 4.4161V4.9281C1.59998 4.9985 1.65998 5.0561 1.73331 5.0561H2.73998L3.15164 13.4241C3.17831 13.9697 3.64831 14.4001 4.21664 14.4001H11.7833C12.3533 14.4001 12.8216 13.9713 12.8483 13.4241L13.26 5.0561H14.2666C14.34 5.0561 14.4 4.9985 14.4 4.9281V4.4161C14.4 4.1329 14.1616 3.9041 13.8666 3.9041Z"
        fill="#DA6262"
      />
    </svg>
  );

  return (
    <React.Fragment>
      <div className="invitation_code_subtabs">
        <div className="invitation_code_subtabs_list">
          <button onClick={handleOpenAddCodes} className="addCode btn">
            {codeIcon}
            {__("Add Codes", "new-user-approve")}
          </button>

          <button
            onClick={handleOpenAutoGenerate}
            className="autoGenerator btn"
          >
            {" "}
            {generateIcon}
            {__("Auto Generate", "new-user-approve")}
          </button>
          <button onClick={handleOpenImportCodes} className="addImport btn">
            {" "}
            {importIcon}
            {__("Import Codes", "new-user-approve")}
          </button>
          <button onClick={handleOpenSendEmail} className="send-email-btn btn ">
            {" "}
            {sendIcon}
            {__("Send Email", "new-user-approve")}
          </button>
        </div>

        {/* Add codes */}
        <Dialog
          open={openAddCodeModal}
          onClose={handleCloseAddCodes}
          maxWidth="md"
          fullWidth={true}
          className="openNuaTabsModal add-codes-modal"
        >
          <DialogTitle>{__("Add Codes", "new-user-approve")}</DialogTitle>
          <IconButton
            aria-label="close"
            className="nua-modal-close"
            onClick={handleCloseAddCodes}
            sx={(theme) => ({
              position: "absolute",
              right: 8,
              top: 12,
              color: theme.palette.grey[500],
            })}
          >
            <CloseIcon />
          </IconButton>
          <DialogContent dividers>
            {incorrectDate === true ? (
              <Stack
                sx={{ width: "100%" }}
                spacing={2}
                className="nua-invitation_code_alert"
              >
                <Alert variant="outlined" severity="error">
                  {__("Selected date is incorrect", "new-user-approve")}
                </Alert>
              </Stack>
            ) : (
              ""
            )}

            {usageLimit <= 0 ? (
              <Stack
                sx={{ width: "100%" }}
                spacing={2}
                className="nua-invitation_code_alert"
              >
                <Alert variant="outlined" severity="error">
                  {__("Usage limit must be greater than 0", "new-user-approve")}
                </Alert>
              </Stack>
            ) : (
              ""
            )}

            {codeStatus === "success" ? (
              <Stack
                sx={{ width: "100%" }}
                spacing={2}
                className="nua-invitation_code_alert"
              >
                <Alert variant="outlined" severity="success">
                  {__(codeUpdatedMessage, "new-user-approve")}
                </Alert>
              </Stack>
            ) : (
              ""
            )}

            {codeExists != "" ? (
              <Stack
                sx={{ width: "100%", marginTop: 2 }}
                spacing={2}
                className="nua-invitation_code_alert"
              >
                <Alert variant="outlined" severity="error">
                  {__(codeExists, "new-user-approve")}
                </Alert>
              </Stack>
            ) : (
              ""
            )}

            {codeStatus === "error" ? (
              <Stack
                sx={{ width: "100%", marginTop: 2 }}
                spacing={2}
                className="nua_invitation_code_alert"
              >
                <Alert variant="outlined" severity="error">
                  {__(codeFailedMessage, "new-user-approve")}
                </Alert>
              </Stack>
            ) : (
              ""
            )}
            {/* add codes */}
            <span className="add-invitation-code">
              <h4> {__("Codes", "new-user-approve")} </h4>
              <div id="invitation-code-booknow-container">
                <textarea
                  name="add_invite_code"
                  id="add_invite_code"
                  className="add_invite_code_area nua-setting-textarea"
                  value={InviteCodes}
                  onChange={handleAddCodes}
                />
                {/* <BookNow /> */}
              </div>
              <p className="description codeDescription">
                {__(`Enter one code per line.`, "new-user-approve")}
              </p>
            </span>
            <div className="nua-field-col">
              {/* <span className="invitation-code-usage">
                <h4>{__("Uses Left", "new-user-approve")}</h4>
                <input
                  type="number"
                  name="usage_limit"
                  readOnly
                  min={0}
                  className="usage_left_input auto-code-field"
                  value={usageLeft}
                />
              </span> */}

              <span className="invitation-code-usage">
                <h4>{__("Usage Limit", "new-user-approve")}</h4>
                <input
                  type="number"
                  name="invite_code_usage_limit"
                  min={0}
                  className="usage_limit_input auto-code-field"
                  value={usageLimit}
                  onChange={handleUsageLimit}
                />
              </span>
            </div>

            <div className="nua-field-col">
              <span className="nua-code-status">
                <h4>{__("Status", "new-user-approve")}</h4>
                <select
                  name="code_status"
                  required
                  className="nua_codetxt auto-code-field"
                  value={selectedStatus}
                  onChange={(e) => setSelectedStatus(e.target.value)}
                >
                  <option value="Active">Active</option>
                  <option value="InActive">InActive</option>
                  {/* <option value="Expired">Expired</option> */}
                </select>
              </span>

              <span className="invitation-code-expiry">
                <h4>{__("Expiry Date", "new-user-approve")}</h4>
                <TextField
                  sx={{ width: 250 }}
                  className="invitation-code-expiry-date"
                  type="date"
                  variant="outlined"
                  value={expiryDate || ""}
                  onChange={handleDateChange}
                  inputProps={{
                    min: today,
                  }}
                />
              </span>
            </div>
          </DialogContent>
          <DialogActions style={{ padding: "20px" }}>
            <Button
              className="cancelBtn"
              onClick={handleCloseAddCodes}
              color="primary"
            >
              {__("Cancel", "new-user-approve")}
            </Button>
            <button className="nua-btn" onClick={handleSubmit}>
              {" "}
              {__("Add Codes", "new-user-approve")}
              {loading == true ? (
                <div className="new-user-approve-loading">
                  <div className="nua-spinner"></div>
                </div>
              ) : (
                ""
              )}
            </button>
          </DialogActions>
        </Dialog>

        {/* Auto code */}
        <Dialog
          open={openAutoGenerateModal}
          onClose={handleCloseAutoGenerate}
          maxWidth="md"
          fullWidth={true}
          className="openNuaTabsModal"
        >
          <DialogTitle>
            {__("Auto Generate", "new-user-approve")}
            <span className="nua-pro-icon">{proIcon}</span>
          </DialogTitle>
          <IconButton
            aria-label="close"
            className="nua-modal-close"
            onClick={handleCloseAutoGenerate}
            sx={(theme) => ({
              position: "absolute",
              right: 8,
              top: 12,
              color: theme.palette.grey[500],
            })}
          >
            <CloseIcon />
          </IconButton>
          <DialogContent dividers className="nua-auto-code nua-setting-pro">
            <div className="nua-field-col-a">
              <span className="auto-code-prefix">
                <h4> {__("Code Prefix", "new-user-approve")} </h4>
                <input
                  type="text"
                  className="code-prefix auto-code-field"
                  name="code-prefix"
                  disabled
                  value={codePrefix}
                  onChange={handleCodePrefix}
                />
              </span>

              <span className="auto-code-suffix">
                <h4> {__("Code Suffix", "new-user-approve")} </h4>
                <input
                  type="text"
                  className="code-suffix auto-code-field"
                  name="code-suffix"
                  value={codeSuffix}
                  disabled
                  onChange={handleCodeSuffix}
                />
              </span>

              <span className="auto-code-quantity">
                <h4>{__("Code Quantity", "new-user-approve")}</h4>
                <input
                  type="number"
                  className="code-quantity auto-code-field"
                  name="code-quantity"
                  min={1}
                  disabled
                  value={codeQuantity}
                  onChange={handleCodeQuantity}
                />
              </span>
            </div>
            <div className="nua-field-col-b">
              <span className="auto-code-usage-limit">
                <h4>{__("Usage Limit", "new-user-approve")}</h4>
                <input
                  type="number"
                  className="auto-code-usageLimit auto-code-field"
                  name="auto-code-usageLimit"
                  min={1}
                  disabled
                  value={codeUsage}
                  onChange={handleAutoCodeUsage}
                />
              </span>

              <span className="auto-code-expiry">
                <h4>{__("Expiry Date", "new-user-approve")}</h4>
                <TextField
                  className="auto-code-expiry-date auto-code-field"
                  type="date"
                  variant="outlined"
                  // label="Custom Date Picker"
                  value={autoCodeDate}
                  onChange={handleAutoCodeDate}
                  disabled
                  sx={{ width: 250 }}
                  inputProps={{
                    min: today,
                  }}
                />
              </span>
            </div>
          </DialogContent>
          <DialogActions style={{ padding: "20px" }}>
            <Button
              className="cancelBtn"
              onClick={handleCloseAutoGenerate}
              color="primary"
            >
              {__("Cancel", "new-user-approve")}
            </Button>
            <button className="nua-btn" onClick={handleAutoSubmit}>
              {" "}
              {__("Generate", "new-user-approve")}
              {loading == true ? (
                <div className="new-user-approve-loading">
                  <div className="nua-spinner"></div>
                </div>
              ) : (
                ""
              )}
            </button>
          </DialogActions>
        </Dialog>

        {/* import codes */}
        <Dialog
          open={openImportModal}
          onClose={handleCloseImportCodes}
          maxWidth="md"
          fullWidth={true}
          className="openNuaTabsModal"
        >
          <DialogTitle>
            {__("Import Codes", "new-user-approve")}
            <span className="nua-pro-icon">{proIcon}</span>
          </DialogTitle>
          <IconButton
            aria-label="close"
            className="nua-modal-close"
            onClick={handleCloseImportCodes}
            sx={(theme) => ({
              position: "absolute",
              right: 8,
              top: 12,
              color: theme.palette.grey[500],
            })}
          >
            <CloseIcon />
          </IconButton>
          <DialogContent dividers>
            <ImportCodes
              ref={importRef}
              fetchAutoCodes={fetchAutoCodes}
              handleCloseImportCodes={handleCloseImportCodes}
            />
          </DialogContent>
          <DialogActions style={{ padding: "20px" }}>
            <Button
              className="cancelBtn"
              onClick={handleCloseImportCodes}
              color="primary"
            >
              {__("Cancel", "new-user-approve")}
            </Button>

            <button
              className="importBtn nua-btn"
              onClick={handleImportClick}
              name="import_csv"
            >
              {" "}
              {__("Import Now", "new-user-approve")}
              {loading == true ? (
                <div className="new-user-approve-loading">
                  <div className="nua-spinner"></div>
                </div>
              ) : (
                ""
              )}
            </button>
          </DialogActions>
        </Dialog>

        {/* send email */}
        <Dialog
          open={openSendEmailModal}
          onClose={handleCloseSendEmail}
          maxWidth="md"
          fullWidth={true}
          className="openNuaTabsModal"
        >
          <DialogTitle>
            {__("Send Email", "new-user-approve")}
            <span className="nua-pro-icon">{proIcon}</span>
          </DialogTitle>
          <IconButton
            aria-label="close"
            className="nua-modal-close"
            onClick={handleCloseSendEmail}
            sx={(theme) => ({
              position: "absolute",
              right: 8,
              top: 12,
              color: theme.palette.grey[500],
            })}
          >
            <CloseIcon />
          </IconButton>
          <DialogContent dividers>
            <InvitationEmail ref={emailRef} closeModal={handleCloseSendEmail} />
          </DialogContent>

          <DialogActions style={{ padding: "20px" }}>
            <Button
              className="cancelBtn"
              onClick={handleCloseSendEmail}
              color="primary"
            >
              {__("Cancel", "new-user-approve")}
            </Button>

            <Button
              name="import_csv"
              className="importBtn nua-btn"
              onClick={handleSendEmail}
            >
              {__("Send Email", "new-user-approve")}
              {loading == true ? (
                <div className="new-user-approve-loading">
                  <div className="nua-spinner"></div>
                </div>
              ) : (
                ""
              )}
            </Button>
          </DialogActions>
        </Dialog>

        {/* </Routes> */}
        <div className="rowsCount">
          <h2 className="users_list_title">
            {" "}
            {__("All Codes", "new-user-approve")}
          </h2>
          <div className="inv-search">
            <div
              style={{
                display: "flex",
                flexDirection: "column",
                alignItems: "flex-start",
                position: "relative",
                width: 340,
              }}
            >
              <TextField
                className="nua-code-search"
                placeholder="Search codes..."
                variant="outlined"
                size="small"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                sx={{ marginBottom: 1 }}
              />
              <div className="code-search-icon">{searchIcon}</div>
            </div>
          </div>

          <Stack
            direction="row"
            justifyContent="space-between"
            alignItems="center"
            mb={2}
          >
            <span className="selectSpan" style={{ marginRight: 8 }}>
              {__("Show:", "new-user-approve")}
            </span>
            <FormControl size="small" sx={{ minWidth: 120 }}>
              <Select
                labelId="rows-per-page-label"
                value={rowsPerPage}
                onChange={handleRowsPerPageChange}
              >
                <MenuItem value={10}>{__("10", "new-user-approve")}</MenuItem>
                <MenuItem value={20}>{__("20", "new-user-approve")}</MenuItem>
                <MenuItem value={50}>{__("50", "new-user-approve")}</MenuItem>
                <MenuItem value={100}>{__("100", "new-user-approve")}</MenuItem>
              </Select>
            </FormControl>
            <span className="selectSpan" style={{ marginLeft: 8 }}>
              {__("entries", "new-user-approve")}
            </span>
          </Stack>

          {/* <span>{totalRows}{' '}{__('Codes', 'new-user-approve') }</span> */}
        </div>
        {selectedCodes.length > 0 && (
          <div
            className="bulk-actions-bar"
            style={{
              display: "flex",
              alignItems: "center",
              marginBottom: "10px",
            }}
          >
            <Typography variant="body1" sx={{ marginRight: 2 }}>
              <span className="nua_bulkActions">
                {__("Bulk actions", "new-user-approve")}:
              </span>{" "}
              <span className="nua_bulkLength">
                {`${selectedCodes.length} `}
                {__("code(s) selected", "new-user-approve")}
              </span>
            </Typography>
            <Button
              className="bulkDeny bulkButton"
              onClick={() => handleOpenDeleteModal(null)}
              variant="outlined"
              color="warning"
            >
              {bulkDelete}
              {__("Delete", "new-user-approve")}
            </Button>
          </div>
        )}
        {/* Add codes Html */}
        <Paper
          className="invitation-codes-header"
          elevation={1}
          sx={{ borderRadius: 2, padding: 3, marginTop: 4 }}
        >
          <Table>
            <TableHead>
              <TableRow>
                <TableCell>
                  <input
                    type="checkbox"
                    className="nua_checkbox"
                    checked={
                      selectedCodes.length === rows.length && rows.length > 0
                    }
                    onChange={handleSelectAll}
                  />
                  <strong>{__("Invitation Code", "new-user-approve")}</strong>
                </TableCell>
                <TableCell>
                  <strong>{__("Uses Remaining", "new-user-approve")}</strong>
                </TableCell>
                <TableCell>
                  <strong>{__("Expiry Date", "new-user-approve")}</strong>
                </TableCell>
                <TableCell>
                  <strong>{__("Status", "new-user-approve")}</strong>
                </TableCell>
                <TableCell className="actionsRow">
                  <strong>{__("Actions", "new-user-approve")}</strong>
                </TableCell>
              </TableRow>
            </TableHead>

            <TableBody>
              {loading
                ? Array.from({ length: 5 }).map((_, index) => (
                    <TableRow key={index}>
                      <TableCell>
                        <Skeleton variant="text" height={20} />
                      </TableCell>
                      <TableCell>
                        <Skeleton variant="text" height={20} />
                      </TableCell>
                      <TableCell>
                        <Skeleton variant="text" height={20} />
                      </TableCell>
                      <TableCell>
                        <Skeleton variant="text" height={20} />
                      </TableCell>
                      <TableCell>
                        <Skeleton variant="text" height={20} width={100} />
                      </TableCell>
                    </TableRow>
                  ))
                : rows.map((item, index) => {
                    return (
                      <TableRow class key={index}>
                        <TableCell>
                          <input
                            type="checkbox"
                            className="nua_checkbox"
                            checked={selectedCodes.includes(item.code_id)}
                            onChange={() => handleSelectOne(item.code_id)}
                          />
                          {item.invitation_code}
                        </TableCell>

                        <TableCell>
                          {(() => {
                            const match = usageData.find(
                              (e) => e.code_id === item.code_id
                            );
                            return match
                              ? `${match.uses_left}/${match.usage_limit}`
                              : "N/A";
                          })()}
                        </TableCell>

                        <TableCell>
                          {(() => {
                            const expiryItem = expiryData.find(
                              (e) => e.code_id === item.code_id
                            );
                            return expiryItem
                              ? expiryItem.expiry_data
                              : "No Date";
                          })()}
                        </TableCell>

                        <TableCell>
                          {(() => {
                            const codeStatusObj = Array.isArray(statusList)
                              ? statusList.find(
                                  (s) => s.code_id === item.code_id
                                )
                              : null;

                            const statusLabel = codeStatusObj
                              ? codeStatusObj.code_status
                              : "No status";

                            return (
                              <Chip
                                label={statusLabel}
                                color="default"
                                sx={{
                                  backgroundColor:
                                    statusLabel === "Active"
                                      ? "#EDFFEF"
                                      : statusLabel === "Expired"
                                      ? "#FFEDED"
                                      : "#f0f0f0",
                                  color:
                                    statusLabel === "Active"
                                      ? "#537A52"
                                      : statusLabel === "Expired"
                                      ? "#BD5E5E"
                                      : "#666",
                                  fontWeight: 500,
                                }}
                              />
                            );
                          })()}
                        </TableCell>

                        <TableCell className="actionsRow">
                          <Dialog
                            open={openViewModal}
                            onClose={handleCloseViewModal}
                            maxWidth="md"
                            fullWidth={true}
                            className="openNuaModal NuaModal"
                          >
                            <DialogTitle>
                              {__("View Invitation Code", "new-user-approve")}
                            </DialogTitle>
                            <IconButton
                              aria-label="close"
                              className="nua-modal-close"
                              onClick={handleCloseViewModal}
                              sx={(theme) => ({
                                position: "absolute",
                                right: 8,
                                top: 12,
                                color: theme.palette.grey[500],
                              })}
                            >
                              <CloseIcon />
                            </IconButton>
                            {selectedRow && (
                              <div className="inv-code-details">
                                <div className="inv-field-group-initial">
                                  <label className="invitation-field__label">
                                    {__("Invitation Code", "new-user-approve")}
                                  </label>
                                  <input
                                    readOnly="true"
                                    className="invitation-field__input"
                                    value={selectedRow.invitation_code}
                                  />
                                </div>

                                <div className="inv-field-group">
                                  <div className="invitation-field">
                                    <label className="invitation-field__label">
                                      {__("Uses Left", "new-user-approve")}
                                    </label>
                                    <input
                                      readOnly="true"
                                      className="invitation-field__input"
                                      value={selectedRow.uses_left}
                                    />
                                  </div>

                                  <div className="invitation-field">
                                    <label className="invitation-field__label">
                                      {__("Usage Limit", "new-user-approve")}
                                    </label>
                                    <input
                                      readOnly="true"
                                      className="invitation-field__input"
                                      value={selectedRow.usage_limit}
                                    />
                                  </div>

                                  <div className="invitation-field">
                                    <label className="invitation-field__label">
                                      {__("Expiry Date", "new-user-approve")}
                                    </label>
                                    <input
                                      readOnly="true"
                                      className="invitation-field__input"
                                      value={
                                        expiryData.find(
                                          (e) =>
                                            e.code_id === selectedRow.code_id
                                        )?.expiry_data || "No Date"
                                      }
                                    />
                                  </div>

                                  <div className="invitation-field">
                                    <label className="invitation-field__label">
                                      {__("Status", "new-user-approve")}
                                    </label>
                                    <input
                                      readOnly="true"
                                      className="invitation-field__input"
                                      value={
                                        statusList.find(
                                          (e) =>
                                            e.code_id === selectedRow.code_id
                                        )?.code_status || "No status"
                                      }
                                    />
                                  </div>
                                </div>
                              </div>
                            )}
                            <DialogContent className="viewCode-dialog" dividers>
                              {selectedRow && (
                                <div>
                                  <span className="users-reg-text">
                                    {__(
                                      "Users that have registered by using this invitation code",
                                      "new-user-approve"
                                    )}
                                  </span>
                                  {getInvitedUsersForCode(selectedRow.code_id)
                                    .length > 0 ? (
                                    <TableContainer
                                      className="users-reg-table"
                                      component={Paper}
                                      sx={{ mt: 2 }}
                                    >
                                      <Table size="small">
                                        <TableHead>
                                          <TableRow>
                                            <TableCell>
                                              {__(" User ID")}
                                            </TableCell>
                                            <TableCell>
                                              {__(" User Email")}
                                            </TableCell>
                                            <TableCell>
                                              {__(" User Name")}
                                            </TableCell>
                                          </TableRow>
                                        </TableHead>
                                        <TableBody>
                                          {getInvitedUsersForCode(
                                            selectedRow.code_id
                                          ).map((user, index) => (
                                            <TableRow key={index}>
                                              <TableCell>
                                                {user.user_id || "N/A"}
                                              </TableCell>
                                              <TableCell>
                                                {user.user_email}
                                              </TableCell>
                                              <TableCell>
                                                {user.user_link ? (
                                                  <a
                                                    href={user.user_link}
                                                    target="_blank"
                                                    rel="noopener"
                                                  >
                                                    {user.user_name}
                                                  </a>
                                                ) : (
                                                  "N/A"
                                                )}
                                              </TableCell>
                                            </TableRow>
                                          ))}
                                        </TableBody>
                                      </Table>
                                    </TableContainer>
                                  ) : (
                                    <Typography variant="body2" sx={{ mt: 1 }}>
                                      <Table size="small">
                                        <TableHead>
                                          <TableRow>
                                            <TableCell>
                                              {__(" User ID")}
                                            </TableCell>
                                            <TableCell>
                                              {__(" User Email")}
                                            </TableCell>
                                            <TableCell>
                                              {__(" User Name")}
                                            </TableCell>
                                          </TableRow>
                                        </TableHead>
                                        <TableBody>
                                          <TableRow>
                                            <TableCell>
                                              {__(" No User Found.")}
                                            </TableCell>
                                            <TableCell>{}</TableCell>
                                            <TableCell>{}</TableCell>
                                          </TableRow>
                                        </TableBody>
                                      </Table>
                                    </Typography>
                                  )}
                                </div>
                              )}
                            </DialogContent>
                            <DialogActions></DialogActions>
                          </Dialog>

                          {/* View Button */}
                          <IconButton onClick={() => handleOpenViewModal(item)}>
                            <span className="actionsIcon">{viewIcon}</span>
                          </IconButton>

                          <Dialog
                            open={openEditModal}
                            onClose={handleCloseEditModal}
                            maxWidth="md"
                            fullWidth
                            className="openNuaModal NuaModal openNuaTabsModal"
                          >
                            <DialogTitle>
                              {__("Edit Invitation Code", "new-user-approve")}
                            </DialogTitle>
                            <IconButton
                              aria-label="close"
                              className="nua-modal-close"
                              onClick={handleCloseEditModal}
                              sx={(theme) => ({
                                position: "absolute",
                                right: 8,
                                top: 12,
                                color: theme.palette.grey[500],
                              })}
                            >
                              <CloseIcon />
                            </IconButton>
                            <DialogContent dividers>
                              {selectedRow && (
                                <>
                                  <EditInvitationCode
                                    ref={editRef}
                                    fetchAutoCodes={fetchAutoCodes}
                                    codeId={selectedRow.code_id}
                                    code={selectedRow.invitation_code}
                                    handleCloseEditModal={handleCloseEditModal}
                                    usesLeft={selectedRow.uses_left}
                                    usageLimit={selectedRow.usage_limit}
                                    expiryDate={
                                      expiryData.find(
                                        (e) => e.code_id === selectedRow.code_id
                                      )?.expiry_data || "No Date"
                                    }
                                    status={
                                      statusList.find(
                                        (e) => e.code_id === selectedRow.code_id
                                      )?.code_status || "No status"
                                    }
                                  />
                                </>
                              )}

                              {/* code */}
                            </DialogContent>
                            <DialogActions style={{ padding: "20px" }}>
                              <Button
                                className="cancelBtn"
                                onClick={handleCloseEditModal}
                                color="primary"
                              >
                                {__("Cancel", "new-user-approve")}
                              </Button>
                              <Button
                                className={` importBtn nua-btn save-changes ${
                                  loading ? "loading" : ""
                                }`}
                                onClick={handleEditClick}
                              >
                                {__("Save Changes", "new-user-approve")}
                              </Button>
                            </DialogActions>
                          </Dialog>
                          <IconButton onClick={() => handleOpenEditModal(item)}>
                            <span className="actionsIcon">{editIcon}</span>
                          </IconButton>

                          <Dialog
                            open={openDeleteModal}
                            onClose={handleCloseDeleteModal}
                            maxWidth="xs"
                            fullWidth={true}
                            className="openNuaModal delete-inv-modal openNuaTabsModal NuaModal"
                          >
                            {/* uzair */}
                            <DialogTitle className="delete-title"></DialogTitle>
                            <IconButton
                              aria-label="close"
                              className="nua-modal-close"
                              onClick={handleCloseDeleteModal}
                              sx={(theme) => ({
                                position: "absolute",
                                right: 8,
                                top: 12,
                                color: theme.palette.grey[500],
                              })}
                            >
                              <CloseIcon />
                            </IconButton>

                            <DialogContent
                              dividers
                              className="delete-confirmation"
                            >
                              <div className="sure-icon">
                                {deleteConfirmation}
                              </div>
                              <div className="delete-text">
                                <h3>{__("Are you sure?")}</h3>
                                <p>
                                  {__(
                                    "The invitation code(s) will be permanently deleted and you won’t be able to see it again.",
                                    "new-user-approve"
                                  )}
                                </p>
                              </div>
                            </DialogContent>
                            <DialogActions style={{ padding: "20px" }}>
                              <Button
                                className="cancelBtn"
                                onClick={handleCloseDeleteModal}
                                color="primary"
                              >
                                {__("Cancel", "new-user-approve")}
                              </Button>

                              <Button
                                className="importBtn nua-btn"
                                onClick={handleBulkDeleteConfirmation}
                              >
                                {__("Delete", "new-user-approve")}
                                {loading == true ? (
                                  <div className="new-user-approve-loading">
                                    <div className="nua-spinner"></div>
                                  </div>
                                ) : (
                                  ""
                                )}
                              </Button>
                            </DialogActions>
                          </Dialog>

                          <IconButton
                            onClick={() => handleOpenDeleteModal(item.code_id)}
                          >
                            <span className="actionsIcon">{deleteIcon}</span>
                          </IconButton>
                        </TableCell>
                      </TableRow>
                    );
                  })}
            </TableBody>
          </Table>
          {rows.length === 0 ? (
            <div
              className="user-list-empty recent-user-empty-list"
              style={{ textAlign: "center" }}
            >
              <div className="user-found-error inv-code">
                {notFound}
                <span>
                  {__("No invitation code found", "new-user-approve")}
                </span>
              </div>
            </div>
          ) : (
            rows.map((row) => (
              <div key={row.code_id}>
                <span>{row.code}</span>
              </div>
            ))
          )}
        </Paper>
        {!loading && (
          <Stack
            spacing={2}
            alignItems="center"
            mt={2}
            className="nua-table-pagination"
          >
            <Pagination
              count={Math.max(1, pageCount)}
              page={page}
              onChange={(event, value) => setPage(value)}
              variant="outlined"
              shape="rounded"
              className="nua-nav-pagination"
            />
            <Typography variant="body2" className="nua-table-total-data">
              {`${startIndex}–${endIndex} of ${totalRows}`}
              <span style={{ marginLeft: 5 }}>
                {__("entries", "new-user-approve")}
              </span>
            </Typography>
          </Stack>
        )}
      </div>

      <PopupModal
        isVisible={isPopupVisible}
        onClose={() => setPopupVisible(false)}
      />
      <ToastContainer />
    </React.Fragment>
  );
};

export default Add_Code_SubTabs;
