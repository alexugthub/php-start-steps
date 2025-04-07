<?php
/**
 * Start Steps
 * 
 * A humble beginning for what starts as a note taking web application
 *
 * @link https://alexu.click/projects/steps
 */

//====================================================================
// CONFIGURATION
//====================================================================

// Application version
define("VERSION", "0.1.0");

// Debug mode for local testing
define("DEBUG",   true);

//====================================================================
// REQUEST
//====================================================================

// Request method (GET, POST, PUT, DELETE)
$method         = strtolower($_SERVER["REQUEST_METHOD"]);
// Data from all supported requests
$request        = array_merge(
                    json_decode(
                      file_get_contents("php://input"), true
                    ) ?? [],
                    $_GET ?? []
                  );
// All the headers in the request, in lowercase
$headers        = array_change_key_case(getallheaders(), CASE_LOWER);
// Type of content to return
$type           = $headers["accept"] ?? "text/html";
// Operation requested from the client side
$operation      = $headers["operation"] ?? "";
// Default title
$title          = "Steps";
// Default description
$description    = "A simple note taking application (for now)";

//====================================================================
// RESPONSE
//====================================================================

// Data for the JSON response
$response     = [
  // Whether request is considered successful or not
  "success"   => false
];

// Handle request and generate response based on content type
switch ($type):

  //==================================================================
  // JSON
  //==================================================================

  // Client side requests of the APIs operations
  case "application/json":
    // Handle each method and requested operation
    switch ("$method:$operation") {
      // Update text of note
      case "post:note":
        // Read this same file
        $code = file_get_contents(__FILE__);

        // Get node name to be replaced
        $node = strtolower($request["node"]);

        // Convert special characters to HTML entities
        $text = htmlspecialchars($request["text"], ENT_QUOTES);

        // Replace content of main element in this file
        $indent = "    ";
        $pattern = '/\s+(<' . $node . '[\s\S]*?>)\n?([\s\S]*?)' .
          '(<\/' . $node . '>)/i';
        $replace = PHP_EOL . $indent . '$1' . $text . $indent . '$3';
        $code = preg_replace($pattern, $replace, $code);

        // To test debug mode, save somewhere else
        $path = DEBUG ? "test.php" : "index.php";

        // Save code with new configuration
        file_put_contents($path, $code);

        $response["success"] = true;

        break;
    }

    // Return response as JSON
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($response);
    break;

  //==================================================================
  // DEFAULT
  //==================================================================

  default:
?>
<!doctype html>
<html lang="en-US">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport"
          content="width=device-width, initial-scale=1, minimum-scale=1" />
    <meta name="description"
          content="<?= $description ?>" />
    <title><?= $title ?></title>
    <style>
      /* Global rules */
      :root {
        --background-color: rgba(32, 32, 32, 1);
        --surface-color: rgba(242, 242, 242, 1);
        --accent-color: rgba(0, 0, 0, 1);
        --link-color: rgba(22, 182, 214, 1);
        --text-width: 70ch;
      }

      * {
        font-family: "Consolas", monospace;
        font-optical-sizing: auto;
      }

      *[contenteditable="true"] {
        background-color: var(--accent-color);
        outline: none;
        user-select: initial;
      }

      body {
        align-items: center;
        background-color: var(--background-color);
        color: var(--surface-color);
        margin: 1em;
        padding: 0;
      }

      main,
      header,
      footer {
        margin: 0 auto;
        width: 100%;
        @media (min-width: 800px) {
          width: var(--text-width);
        }
      }

      h1,
      header > p {
        text-align: center;
      }

      main {
        min-height: 2em;
        padding: .5em;
        text-align: justify;
        white-space: pre;
      }

      a,
      a:active,
      a:visited {
        color: var(--link-color);
        text-decoration: none;
      }

      a:hover {
        font-weight: bold;
      }
    </style>
    <script>
      //==============================================================
      // HELPERS
      //==============================================================

      /**
       * Shortcut for getting an element by selector
       */
      const esel = (sel) => document.querySelector(sel);

      /**
       * Shortcut for sending POST requests with parameters
       * 
       * @param operation Requested operation
       * @param data      Additional request data
       * @param cb        Callback function on finish
       */
      const post = (operation, data, cb) => {
        // Send asynchronous requests
        (async () => {
            const response = await fetch(
              "",
              {
                method: "POST",
                headers: {
                  "Operation": operation,
                  "Accept": "application/json",
                  "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
              }
            );

            // Invoke callback with result as JSON
            cb(await response.json());
          }
        )();
      }

      /**
       * Converts all links in a plain text to anchors
       * 
       * @param dom  Plain content container
       * @param text Plain text
       */
      const linkify = (dom, text) => {
        // Don't try to understand it, just accept that it works
        const regex = new RegExp(
          `(?:https?:)?:?//` +
          `(?:[a-zA-Z0-9-]+\\.)+` +
          `[a-zA-Z]{2,}` +
          `(?:/[^\\s]*)?`,
          'g'
        );

        // Replace plain links with anchors
        dom.innerHTML = text.replace(
          regex,
          m => {
            // If the match starts with ://, prepend https
            const href = m.startsWith('://') ? `https${m}` : m;
            return `<a href="${href}" target="_blank">${m}</a>`;
          }
        );
      }

      //==============================================================
      // ACTIONS
      //==============================================================

      /**
       * Entry point
       */
      window.addEventListener("load", () => {
        // Get all editable elements
        const editables = document.querySelectorAll(".editable");

        // Timer for long press events
        let touchTimer;
        // A variable for temporarily storing texts
        let stagedTexts = [];
        // A flag indicating the mouse down event is taking place
        let mdown = false;
        // A flag indicating the Escape key has been pressed
        let esc = false;

        editables.forEach((editable, idx) => {
          // Store initial plain texts
          stagedTexts[idx] = editable.innerText;

          // Enable content editing on long press
          editable.addEventListener(
            "mousedown",
            (e) => {
              touchTimer = setTimeout(() => {
                if (editable.contentEditable == "true") return;

                mdown = true;
              }, 500);
            }
          );

          // Enable content editing on mouse release after long press
          editable.addEventListener(
            "mouseup",
            (e) => {
              if (mdown && editable.contentEditable != true) {
                // Make the content editable and in plain format
                editable.contentEditable = true;
                editable.textContent = stagedTexts[idx];

                // Focus on text
                editable.focus();

                // Reset flags
                esc = false;
                mdown = false;
              }
            }
          );

          // Discard content editing if selecting text
          editable.addEventListener(
            "mousemove",
            (e) => {
              clearInterval(touchTimer);
              mdown = false;
            }
          );

          // Prevent browser from adding <br> on Enter key
          editable.addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
              e.preventDefault();
              const selection = window.getSelection();
              const range = selection.getRangeAt(0);
              range.deleteContents();
              range.insertNode(document.createTextNode("\n"));
              range.collapse(false); // Move cursor after the newline
              selection.removeAllRanges();
              selection.addRange(range);
            }
          });

          // Disable content editing and discard changes on escape key
          editable.addEventListener(
            "keyup",
            (e) => {
              if (e.key == "Escape") {
                esc = true;

                editable.contentEditable = false;
                // Restore initial text
                editable.innerText = stagedTexts[idx];
                // Highlight links
                linkify(editable, stagedTexts[idx]);
              }
            }
          );

          // Disable content editing on loosing focus
          editable.addEventListener(
            "blur",
            (e) => {
              editable.contentEditable = false;

              // Update text if something has changed
              if (!esc && (editable.innerText != stagedTexts[idx])) {
                // Update staged text with changes
                stagedTexts[idx] = editable.innerText;

                // Send request to the server
                post(
                  "note",
                  {
                    "text": editable.innerText,
                    "node": e.target.nodeName
                  },
                  (result) => {
                    if (!result.success) alert("Failed to save text");
                  }
                );
              }

              // Highlight links
              linkify(editable, stagedTexts[idx]);
            }
          );

          // insert clean, plain text on paste event
          editable.addEventListener(
            "paste",
            (e) => {
              e.preventDefault();

              // Get text from the clipboard
              const text = (
                event.clipboardData || window.clipboardData
              ).getData('text/plain');

              // Get the current selection
              const selection = window.getSelection();
              if (!selection.rangeCount) return;

              // Insert the text at the cursor
              const range = selection.getRangeAt(0);

              // Remove any selected content
              range.deleteContents();
              range.insertNode(document.createTextNode(text));

              // Move cursor after the inserted text
              range.setStartAfter(editable.lastChild);
              range.setEndAfter(editable.lastChild);
              selection.removeAllRanges();
              selection.addRange(range);
            }
          );

          // Highlight links
          linkify(editable, stagedTexts[idx]);
        });
      });
    </script>
  </head>
  <body>
    <header>
      <h1 class="editable"><?= $title ?></h1>
      <p class="editable"><?= $description ?></p>
    </header>
    <main class="editable">Long press to edit this note. Press outside text to save it.
Press the Escape key to cancel the changes.

Links like https://alexu.click open in a new tab
    </main>
    <footer>
    </footer>
    <dialog>
    </dialog>
  </body>
</html>
<?php
endswitch;
?>
