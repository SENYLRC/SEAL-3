import React, { useEffect, useState, useRef } from "react";

const WPEditor = ({ editorId, editorName, onChange, editorContent }) => {
  const [content, setContent] = useState(editorContent);
  const [loading, setLoading] = useState(true);
  const editorRef = useRef(null);

  useEffect(() => {
    if (window.wp && window.wp.editor) {
      initializeEditor();
    }

    return () => {
      if (window.wp && window.wp.editor) {
        window.wp.editor.remove(editorId);
      }
    };
  }, []);

  useEffect(() => {
    if (editorRef.current) {
      const editor = editorRef.current;
      const currentContent = editor.getContent();
      if (currentContent !== editorContent) {
        editor.setContent(editorContent || "");
      }
      setLoading(false); // Content has been fetched and loaded
    }
  }, [editorContent]);

  const initializeEditor = () => {
    jQuery(document).ready(function () {
      window.wp.editor.initialize(editorId, {
        teeny: true,
        tinymce: {
          // plugins: 'charmap,colorpicker,hr,lists,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wptextpattern', // Include toolbar plugin
          // toolbar1: "formatselect,bold,italic,bullist,numlist,alignleft,aligncenter,alignright, wplink", // Add toolbar toggle button
          autoresize_min_height: 100,
          wp_autoresize_on: true,
          plugins:
            "charmap,paste,tabfocus,textcolor,fullscreen,wordpress,wpautoresize,wpeditimage,wpemoji,wpgallery,wplink,wptextpattern",
          toolbar1:
            "formatselect,bold,italic,bullist,numlist,alignleft,aligncenter,alignright,underline,link,unlink,forecolor",
          toolbar2: "",
          menubar: false,
          setup: function (editor) {
            editorRef.current = editor;
            editor.on("change", function () {
              const newContent = editor.getContent();
              setContent(newContent);
              handleEditorChange({ editorName, editorContent: newContent });
            });
          },
        },
        quicktags: true,
      });
    });
  };

  const handleEditorChange = ({ editorName, editorContent }) => {
    if (onChange) {
      onChange({ editorName, editorContent });
    }
  };

  return (
    <div style={{ position: "relative" }}>
      <textarea
        id={editorId}
        name={editorName}
        value={content}
        onChange={(e) => {
          const newContent = e.target.value;
          setContent(newContent);
          handleEditorChange({ editorName, editorContent: newContent });
        }}
        style={{ position: "relative", zIndex: 0 }}
        disabled={true} // disabled the editor while loading
      ></textarea>
    </div>
  );
};

export default WPEditor;
