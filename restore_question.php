<?php
date_default_timezone_set('America/New_York');
require("./config.php");
$configData = getConfig();
if (isset($configData["dev"])) {
    define("INDEV",  $configData["dev"]);
}
//Checks 
$str_data = file_get_contents('php://input');
$data = json_decode($str_data, true);
if (!isset($data["data-base-1"])) {
    echo "Data not correct.";
    die();
}
if (!isset($data["data-base-2"])) {
    echo "Data not correct.";
    die();
}
if (!isset($data["question-id"])) {
    echo "Data not correct.";
    die();
}
if (!isset($data["assets"])) {
    echo "Data not correct.";
    die();
}
if (!isset($data["replies"])) {
    echo "Data not correct.";
    die();
}
if (!isset($data["restore"])) {
    echo "Data not correct.";
    die();
}
if (!isset($data["reply-assets"])) {
    echo "Data not correct.";
    die();
}
$response = [];
$questionID = $data["question-id"];
$database1 = $data["data-base-1"];
$database2 = $data["data-base-2"];
$replies = $data["replies"];
$replyAssets = $data["reply-assets"];
$assets = $data["assets"];
$restore = $data["restore"]; 

/*
$response = [];
$questionID ="";
$database1 = "";
$database2 ="";
$replies = "";
$replyAssets ="";
$assets = "";
$restore =""; 
*/


//functions
function exitAndLog($response)
{
    echo json_encode($response);
    exit();
}
$date = new Datetime("now");
$response["start"] =  $date->format("Y-m-d H:i:s");
$response["restore"] = $restore;
$response["done"] = true;
$response["question-id"] = $questionID;

if (INDEV) {
    $appRootPath = getenv('APP_ROOT_PATH');
    require("$appRootPath/config.php");
} else {
    chdir(dirname(__FILE__));
    require("../../config.php");
}

$charset = "utf8";
$dsn = "mysql:host=$mysql_server;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
$pdo;
try {
    $pdo = new PDO($dsn, $mysql_user, $mysql_password, $options);
} catch (\PDOException $e) {
    echo $e->getMessage();
    die();
}



if($restore) {
    $reponseNew = restoreQuestions($pdo,$database1,$database2,$questionID,$assets,$replies,$replyAssets,$response);
    exitAndLog($reponseNew);
} else {
   $reponseNew = ignoreQuestions($pdo,$database1,$questionID,$response );
    exitAndLog($reponseNew);
}


function ignoreQuestions($pdo,$database1,$questionID,$response ) {
    $query = <<<SQL
    UPDATE $database1.questions SET $database1.questions.status = "archived" WHERE id = $questionID 
SQL;

try {
    $stmt = $pdo->query($query);
} catch (\PDOException $e) {
    echo $e->getMessage();
     $response["done"] = false;
     $response["error"] = $e->getMessage();
     $response["note"] = "Failed on updating question: $questionID";
     exitAndLog(($response));
 }
 $response["note"] = "All questions were ignored.";
 return $response;
}

function restoreQuestions($pdo,$database1,$database2,$questionID,$assets,$replies,$replyAssets,$response) {
$query = <<<SQL
   INSERT INTO $database2.questions SELECT $database1.questions.* FROM $database1.questions WHERE id = ?
SQL;
try {
    $stmt = $pdo->prepare($query)->execute([$questionID]);
} catch (\PDOException $e) {
    $response["done"] = false;
    $response["error"] = $e->getMessage();
    $response["note"] = "Failed on inserting question with id $questionID into $database2";
    exitAndLog(($response));
}

foreach ($assets as $a) {
if($a == "") continue;
$date = new Datetime("now");
$response["restored-assets"][$a]['id'] = $a;
$response["restored-assets"][$a]['start'] =  $date->format("Y-m-d H:i:s");
$query = <<<SQL
INSERT INTO $database2.assets SELECT $database1.assets.* FROM $database1.assets WHERE id = $a 
SQL;
try {
    $stmt = $pdo->query($query);
} catch (\PDOException $e) {
        $response["restored-assets"]["done"] = false;
        $response["restored-assets"]["error"] =  $e->getMessage();
        $response["restored-assets"]["note"] = "Failed on inserting into assets with id $a into $database2";
        exitAndLog(($response));
    }
    $response["restored-assets"][$a]['note'] = "Asset with id $a restoration was successful";
}

foreach ($replies as $r) {
if($r == "") continue;
isset($response["restored-replies"]) ? true : $response["restored-replies"] = [];
$response["restored-replies"][$r] = [];
$date = new Datetime("now");
$response["restored-replies"][$r]['id'] = $r;
$response["restored-replies"][$r]['start'] =  $date->format("Y-m-d H:i:s");
$query = <<<SQL
INSERT INTO $database2.question_replies SELECT $database1.question_replies.* FROM $database1.question_replies WHERE id = $r 
SQL;
try {
    $stmt = $pdo->query($query);
} catch (\PDOException $e) {
        $response["restored-replies"]["done"] = false;
        $response["restored-replies"]["error"] =  $e->getMessage();
        $response["restored-replies"]["note"] = "Failed on inserting replies with id $r into $database2";
        exitAndLog(($response));
    }
    $response["restored-replies"][$r]['note'] = "Reply with id $r restoration was successful";
}

foreach ($replyAssets as $ra) {
    if($a == "") continue;
    $date = new Datetime("now");
    $response["restored-reply-assets"][$a]['id'] = $a;
    $response["restored=reply-assets"][$a]['start'] =  $date->format("Y-m-d H:i:s");
    $query = <<<SQL
    INSERT INTO $database2.assets SELECT $database1.assets.* FROM $database1.assets WHERE id = $a 
SQL;
try {
    $stmt = $pdo->query($query);
} catch (\PDOException $e) {
            $response["restored-reply-assets"]["done"] = false;
            $response["restored-reply-assets"]["error"] =$e->getMessage();
            $response["restored-reply-assets"]["note"] = "Failed on inserting into reply assets with id $a into $database2";
            exitAndLog(($response));
        }
        $response["restored-reply-assets"][$a]['note'] = "Reply asset with id $a restoration was successful";
    }



$response["note"] = "All restorations were successful.";
    return $response;
}


