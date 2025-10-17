import React, {
  useState,
  useEffect,
  forwardRef,
  useImperativeHandle,
} from "react";
import {
  Box,
  Button,
  TextField,
  Input,
  Switch,
  FormControlLabel,
} from "@mui/material";
import { sprintf, __ } from "@wordpress/i18n";
import "react-toastify/dist/ReactToastify.css";
import PopupModal from "../../components/popup-modal";

const ImportCodes = forwardRef(
  ({ fetchAutoCodes, handleCloseImportCodes }, ref) => {
    const [isPopupVisible, setPopupVisible] = useState(false);

    const handlefileChange = (event) => {
      setPopupVisible(true);
    };

    const handleImport = async (event) => {
      setPopupVisible(true);
    };

    let downloadIcon = (
      <svg
        width="16"
        height="16"
        viewBox="0 0 16 16"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M3 11.9474H13V13H3V11.9474ZM8.55556 8.88L11.9283 5.68421L12.7139 6.42842L8 10.8947L3.28611 6.42895L4.07167 5.68421L7.44444 8.88053V3H8.55556V8.88Z"
          fill="#618E5F"
        />
      </svg>
    );

    let importIcon = (
      <svg
        width="32"
        height="32"
        viewBox="0 0 32 32"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M21.2786 26.728H5.33327V5.33864H14.6386V11.9786H21.2786V13.3333H24.0133V10.6853L21.2799 7.9493L18.6666 5.33864L16.0133 2.6853H5.3466C4.64134 2.68777 3.96587 2.96994 3.46843 3.46989C2.97098 3.96983 2.6922 4.64671 2.69327 5.35197L2.67993 26.6853C2.67887 27.3906 2.95765 28.0674 3.4551 28.5674C3.95254 29.0673 4.62801 29.3495 5.33327 29.352H24.0133V26.6853L21.2786 26.728ZM28.3466 24.0186L30.6799 16.0186H28.6799L27.3466 20.592L26.0133 16.0186H24.0133L26.3466 24.0186H28.3466Z"
          fill="#618E5F"
        />
        <path
          d="M13.3468 16.0187H9.34676C8.99346 16.0197 8.65494 16.1605 8.40512 16.4104C8.1553 16.6602 8.01448 16.9987 8.01343 17.352V22.6853C8.01448 23.0386 8.1553 23.3772 8.40512 23.627C8.65494 23.8768 8.99346 24.0176 9.34676 24.0187H13.3468C13.7001 24.0176 14.0386 23.8768 14.2884 23.627C14.5382 23.3772 14.679 23.0386 14.6801 22.6853V21.352H12.6801V22.0187H10.0134V18.0187H12.6801V18.6853H14.6801V17.352C14.679 16.9987 14.5382 16.6602 14.2884 16.4104C14.0386 16.1605 13.7001 16.0197 13.3468 16.0187ZM22.6801 18.0267V16.0187H17.3468C16.9931 16.0187 16.654 16.1592 16.404 16.4092C16.1539 16.6592 16.0134 16.9984 16.0134 17.352V19.6947C16.0134 20.0483 16.1539 20.3874 16.404 20.6375C16.654 20.8875 16.9931 21.028 17.3468 21.028H20.6934V22.0187H16.0134V24.028H21.3468C21.7004 24.028 22.0395 23.8875 22.2896 23.6375C22.5396 23.3874 22.6801 23.0483 22.6801 22.6947V20.352C22.6801 19.9984 22.5396 19.6593 22.2896 19.4092C22.0395 19.1592 21.7004 19.0187 21.3468 19.0187H18.0001V18.028L22.6801 18.0267Z"
          fill="#618E5F"
        />
      </svg>
    );

    let crossIcon = (
      <svg
        width="10"
        height="10"
        viewBox="0 0 10 10"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M2 2L8 8M2 8L8 2"
          stroke="#707070"
          strokeWidth="1.5"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
    );

    useImperativeHandle(ref, () => ({
      handleImport,
    }));

    return (
      <React.Fragment>
        <div className="import-code-section nua-setting-pro">
          <div className="import-code">
            <div className="import-csv-file">
              <div className="import-content">
                {importIcon}
                <h4>{__("Import a CSV file", "new-user-approve")}</h4>
                <p>{__("or simply drag and drop", "new-user-approve")}</p>
              </div>
              <TextField
                className="import-csv"
                name="import-csv"
                type=""
                variant="outlined"
                onClick={handlefileChange}
              />
            </div>
          </div>
          <div className="download-sample-codes-csv">
            <button
              name="sample_csv"
              onClick={handleImport}
              className="download-button"
              style={{
                display: "flex",
                alignItems: "center",
                border: "none",
                background: "none",
                cursor: "pointer",
              }}
            >
              {downloadIcon}
              <h3 style={{ margin: 0 }}>
                {__("Download sample CSV", "new-user-approve")}
              </h3>
            </button>
          </div>
        </div>
        <PopupModal
          isVisible={isPopupVisible}
          onClose={() => setPopupVisible(false)}
        />
      </React.Fragment>
    );
  }
);

export default ImportCodes;
