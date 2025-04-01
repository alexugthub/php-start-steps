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
define("VERSION", "1.0.0");

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

        // Convert special characters to HTML entities
        $text = htmlspecialchars($request["text"], ENT_QUOTES);

        // Replace content of main element in this file
        $indent = "    ";
        $pattern = '/\s+<main>\n?([\s\S]*?)<\/main>/i';
        $replace = "\r\n$indent<main>";
        $replace .= $text;
        $replace .= "$indent</main>";
        preg_match($pattern, $code, $matches, PREG_OFFSET_CAPTURE);
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

      //==============================================================
      // ACTIONS
      //==============================================================

      /**
       * Entry point
       */
      window.addEventListener("load", () => {
        // Timer for long press events
        let touchTimer;
        // A variable for temporarily storing text
        let stagedText;
        // A flag indicating the Escape key has been pressed
        let esc = false;

        // Enable content editing on long press
        esel("main").addEventListener(
          "mousedown",
          (e) => {
            touchTimer = setTimeout(() => {
              if (e.target.contentEditable == "true") return;

              // Make the content editable and store it temporarily
              e.target.contentEditable = true;
              stagedText = e.target.innerText

              // Focus on text
              e.target.focus();

              esc = false;
            }, 500);
          }
        );

        // Disable content editing and discard changes on escape key
        esel("main").addEventListener(
          "keyup",
          (e) => {
            if (e.keyCode == 27) {
              esc = true;

              e.target.contentEditable = false;
              // Restore initial text
              e.target.innerText = stagedText;

              stagedText = "";
            }
          }
        );

        // Disable content editing on loosing focus
        esel("main").addEventListener(
          "blur",
          (e) => {
            e.target.contentEditable = false;

            // Update text if something has changed
            if (!esc && (e.target.innerText.trim() != stagedText)) {
              // Send request to the server
              post(
                "note",
                {
                  "text": e.target.innerText
                },
                (result) => {
                  if (!result.success) alert("Failed to save text");
                }
              );
            }

            stagedText = "";
          }
        );
      });

    </script>
  </head>
  <body>
    <header>
      <h1><?= $title ?></h1>
      <p><?= $description ?></p>
    </header>
    <main>Long press to edit this note. Press outside text to save it.
Press the Escape key to cancel the changes.</main>
    <footer>
    </footer>
    <dialog>
    </dialog>
  </body>
</html>
<?php
endswitch;
?>
