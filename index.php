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
define("VERSION",       "0.2.0");

// Debug mode for local testing
define("DEBUG",         true);

// Database connection information
define("MYSQL_DB_HOST", "localhost");
define("MYSQL_DB_PORT", 3306);
define("MYSQL_DB_NAME", "u813379533_steps");
define("MYSQL_DB_USER", "u813379533_admin");
define("MYSQL_DB_PASS", "CdDKJ#4Zi");

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

//====================================================================
// FUNCTIONS
//====================================================================

/**
 * Connects to the database and returns the data object
 * 
 * @param config Array with connection information
 */
function database($config = []) {
  // If parameters not specified, use definitions
  $host = $config["host"] ?? MYSQL_DB_HOST;
  $port = $config["port"] ?? MYSQL_DB_PORT;
  $name = $config["name"] ?? MYSQL_DB_NAME;
  $user = $config["user"] ?? MYSQL_DB_USER;
  $pass = $config["pass"] ?? MYSQL_DB_PASS;

  // Give it a try
  try {
    // Create a new connection
    $dbh = new PDO(
      "mysql:host=$host;dbname=$name;port=$port", $user, $pass
    );

    // Return PDO
    return $dbh;
  } catch (PDOException $e) {
    // Return nothing
    return null;
  }
}

/**
 * Installs the application for the first time
 */
function install() {
  // Connect to the database to create the initial tables
  $dbh = database();
  if ($dbh) {
    // Create procedure that adds foreign keys if they don't exist
    $dbh->exec(
      <<<EOD
        DROP PROCEDURE IF EXISTS AddForeignKeyIfNotExist;

        CREATE PROCEDURE AddForeignKeyIfNotExist(
            IN p_table_name VARCHAR(64),
            IN p_column_name VARCHAR(64),
            IN p_ref_table_name VARCHAR(64),
            IN p_ref_column_name VARCHAR(64),
            IN p_constraint_name VARCHAR(64)
        )
        BEGIN
            IF NOT EXISTS (
                SELECT 1 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                AND TABLE_NAME = p_table_name
                AND CONSTRAINT_NAME = p_constraint_name
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ) THEN
                SET @sql = CONCAT(
                    'ALTER TABLE `', p_table_name, '` ',
                    'ADD CONSTRAINT `', p_constraint_name, '` ',
                    'FOREIGN KEY (`', p_column_name, '`) ',
                    'REFERENCES `', p_ref_table_name, '` 
                      (`', p_ref_column_name, '`) ',
                    'ON DELETE CASCADE ON UPDATE CASCADE'
                );
                PREPARE stmt FROM @sql;
                EXECUTE stmt;
                DEALLOCATE PREPARE stmt;
            END IF;
        END;
      EOD
    );

    // Create the contexts table, for holding different contexts
    $dbh->exec(
      <<<EOD
        CREATE TABLE IF NOT EXISTS `contexts` (
        `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT
          COMMENT 'Unique ID',
        `active` BOOLEAN NOT NULL DEFAULT FALSE
          COMMENT 'Currently used or not',
        PRIMARY KEY (`ID`))
        ENGINE = InnoDB
        CHARSET = utf8mb4 COLLATE utf8mb4_unicode_ci
        COMMENT = 'Switchable contexts';

        INSERT IGNORE INTO `contexts` (`ID`, `active`)
        VALUES ('1', '1');
      EOD
    );

    // Create the nodes table, for the hierarchy of nodes
    $dbh->exec(
      <<<EOD
        CREATE TABLE IF NOT EXISTS `nodes` (
        `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT
          COMMENT 'Unique ID',
        `contextID` INT UNSIGNED NOT NULL
          COMMENT 'Context',
        `parentID` INT UNSIGNED NULL DEFAULT NULL
          COMMENT 'Parent node',
        `type` ENUM('text', 'section', 'list', 'routine')
          NOT NULL DEFAULT 'text'
          COMMENT 'Node type',
        `position` INT NOT NULL DEFAULT '0'
          COMMENT 'Position on page',
        PRIMARY KEY (`ID`))
        ENGINE = InnoDB
        CHARSET = utf8mb4 COLLATE utf8mb4_unicode_ci
        COMMENT = 'Interrelated nodes';

        CALL AddForeignKeyIfNotExist(
          'nodes',
          'contextID',
          'contexts',
          'ID', 
          'nodes_ibfk_contextID'
        );

        CALL AddForeignKeyIfNotExist(
          'nodes',
          'parentID',
          'nodes',
          'ID', 
          'nodes_ibfk_parentID'
        );
      EOD
    );

    // Create the texts table, for simple blocks of text
    $dbh->exec(
      <<<EOD
        CREATE TABLE IF NOT EXISTS `texts` (
        `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT
          COMMENT 'Unique ID',
        `nodeID` INT UNSIGNED NOT NULL
          COMMENT 'Assigned node',
        `content` TEXT NULL DEFAULT NULL
          COMMENT 'Full content',
        `lang` VARCHAR(2) NOT NULL DEFAULT 'en'
          COMMENT 'Language',
        `version` INT NOT NULL DEFAULT 0
          COMMENT 'Changes version',
        PRIMARY KEY (`ID`))
        ENGINE = InnoDB DEFAULT
        CHARSET = utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT = 'Text blocks';

        CALL AddForeignKeyIfNotExist(
          'texts',
          'nodeID',
          'nodes',
          'ID', 
          'texts_ibfk_nodeID'
        );
      EOD
    );

    // Read this same file
    $code = file_get_contents(__FILE__);

    // Remove installer invocation
    $code = str_replace("install();\r\n", "// install();\r\n", $code);

    // To test in debug mode, save somewhere else
    $path = DEBUG ? "test.php" : "index.php";

    // Save code with new configuration
    file_put_contents($path, $code);
  }
}

// Install (invocation is commented out once installed)
install();

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
        // Try connecting to the database to save the note
        $dbh = database();

        // Validate request data
        if (!is_numeric($request["node"]) || $request["node"] < 1) {
          $response["message"] = "Invalid node";
          break;
        }

        if (!isset($request["text"]) || empty($request["text"])) {
          $response["message"] = "No content specified";
          break;
        }

        if ($dbh && $request["node"] > 0) {
          // Create a new node for the node
          $stmt = $dbh->prepare(
            "INSERT IGNORE INTO `nodes` " .
            "(`ID`, `contextID`, `parentID`, `type`, `position`) " .
            "VALUES (:node, 1, NULL, 'text', 0)"
          );

          $stmt->bindParam(':node', $request["node"]);
          $stmt->execute();

          // Save the note in the texts table
          $stmt = $dbh->prepare(
            "REPLACE INTO `texts` " .
            "(`ID`, `nodeID`, `content`, `lang`, `version`) " .
            "VALUES (:node, :node, :content, 'en', 0)"
          );

          $stmt->bindParam(':node', $request["node"]);
          $stmt->bindParam(':content', $request["text"]);
          $stmt->execute();

          $response["success"] = true;
        }
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
        // A flag indicating the Escape key has been pressed
        let esc = false;

        /**
         * Makes the specified element's content editable
         * 
         * @param dom     Element that will become editable
         * @param content Raw editable content
         */
        function makeEditable(dom, content) {
          // Make the content editable and in plain format
          dom.contentEditable = true;
          dom.textContent = content;

          // Reset flags
          esc = false;
        }

        editables.forEach((editable, idx) => {
          // Store initial plain texts
          stagedTexts[idx] = editable.innerText;

          // Enable content editing on mouse release after long press
          editable.addEventListener(
            "dblclick",
            (e) => {
              if (editable.contentEditable != "true") {
                makeEditable(editable, stagedTexts[idx]);
              }
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
              // Move cursor after the newline
              range.collapse(false);
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
                    "node": idx + 1
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
