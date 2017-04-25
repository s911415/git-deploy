<?php
require_once("config.php");

$content = file_get_contents("php://input");
$json    = json_decode($content, true);
$file    = fopen(LOGFILE, "a");
$time    = time();

date_default_timezone_set("UTC");
fputs($file, date("d-m-Y (H:i:s)", $time) . "\n");

// function to forbid access
function forbid() {
    header("HTTP/1.0 403 Forbidden");
    fputs($file, "*** ACCESS DENIED ***" . "\n\n\n");
    fclose($file);
    exit;
}

// Check for a GitHub signature
if (!empty(TOKEN) && isset($_SERVER["HTTP_X_HUB_SIGNATURE"])) {
    list($algo, $hash) = explode("=", $_SERVER["HTTP_X_HUB_SIGNATURE"], 2) + array("", "");

    if ($hash !== hash_hmac($algo, $content, TOKEN)) {
        forbid();
    }
// Check for a GitLab token
} elseif (!empty(TOKEN) && isset($_SERVER["HTTP_X_GITLAB_TOKEN"])) {
    if ($_SERVER["HTTP_X_GITLAB_TOKEN"] !== sha1(TOKEN)) {
        forbid();
    }
// Check for a $_GET token
} elseif (!empty(TOKEN) && isset($_GET["token"])) {
    if ($_GET["token"] !== TOKEN) {
        forbid();
    }
// if none of the above match, but a token exists, exit
} elseif (!empty(TOKEN)) {
    forbid();
} else {
    if ($json["ref"] === BRANCH) {
        fputs($file, $content . PHP_EOL);

        if (file_exists(DIR . ".git") && is_dir(DIR)) {
            try {
                chdir(DIR);
                shell_exec(GIT . " pull");

                if (!empty(AFTER_PULL)) {
                    try {
                        shell_exec(AFTER_PULL);
                    } catch (Exception $e) {
                        fputs($file, $e . "\n");
                    }
                }

                fputs($file, "*** AUTO PULL SUCCESFUL ***" . "\n");
            } catch (Exception $e) {
                fputs($file, $e . "\n");
            }
        } else {
            fputs($file, "DIR is not a repository" . "\n");
        }
    } else{
        fputs($file, "Push in: " . $json["ref"] . "\n");
    }
}

fputs($file, "\n\n" . PHP_EOL);
fclose($file);
