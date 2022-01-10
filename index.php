<?php

/**
 *Made by Luke 10/14/2021
 *This script checks for the difference between questions stored in two different databases and allows restore of deleted questions. 
 *You can change the config file in the directory to change features and aneable advanced mode. 
 *Config options:
 *[questions-view-slug-prefix] : string | Prefix for question slugs for the view button. 
 *[data-base-1] : string | The backup database.
 *[data-base-2] : string | The production database.
 *[question-limit] : number | The number of questions to limit the tool query too.
 *[advanced] : boolean | Shows all the data in the display and presents a form to change the config data live.
 *[advanced-options]
 *->[raw-data] Show raw data underneath question
 *->[database-analysis-data] Shoe data base anaylsis underneath question
 *->[metrics] Show and capture matric data 
 *->[metric-time-calc-time-zone] The time zone to use to start the metric calculations 
 *->[find-people] Wether or not to include people in the question output
 *->[ignore-archive-user] Wether or not to ignore the arhive user when finding people 
 *->[question-data-bar-top] Display the top data bar for the questions.
 *->[question-data-bar-bottom] Display the top data bar for the questions.
 *->[php-error-reporting-on] Wether or not to turn on php error reporting 
 *-> 
 */
///Set up tool from config 
require("./config.php");
$configData = getConfig();
if (isset($configData["dev"])) {
    define("INDEV",  $configData["dev"]);
}
$configDataRaw  = json_encode($configData, JSON_PRETTY_PRINT);
if (INDEV) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
if (!isset($configData)) {
    echo "ERRRO: Config Data not found. Exiting.......";
    die();
}
$accepableDataBases;
if (!isset($configData["accetpable-databases"]) || count($configData["accetpable-databases"]) == 0) {
    echo "ERROR: Not acceptable databases set";
    die();
} else {

    foreach ($configData["accetpable-databases"] as $db) {
        if (trim($db) == "") {
            echo "ERROR: Not acceptable databases set";
            die();
        }
    }

    $accepableDataBases = $configData["accetpable-databases"];
}
if (isset($configData["advanced"]) || isset($_GET["maxed"])) {

    if (isset($_GET["maxed"])) {
        define("ADVANCED",  true);
    } else {
        define("ADVANCED",   $configData["advanced"]);
    }
}



if ($configData["advanced-options"] && ADVANCED) {
    $ap = $configData["advanced-options"];
    if (isset($ap["raw-data"])) {
        define("RAWDATA", $ap["raw-data"]);
    }
    if (isset($ap["database-analysis-data"])) {
        define("DATABASEANALYSIS", $ap["database-analysis-data"]);
    }
    if (isset($ap["metrics"])) {
        define("METRICS", $ap["metrics"]);
    }
    if (isset($ap["metric-time-calc-time-zone"])) {
        define("METRICSTIMEZONE", $ap["metric-time-calc-time-zone"]);
    }
    if (isset($ap["find-people"])) {
        define("FINDPEOPLE", $ap["find-people"]);
    }
    if (isset($ap["question-data-bar-top"])) {
        define("QUESTIONDATABARTOP", $ap["question-data-bar-top"]);
    }
    if (isset($ap["question-data-bar-bottom"])) {
        define("QUESTIONDATABARBOTTOM", $ap["question-data-bar-bottom"]);
    }
    if (isset($ap["php-error-reporting-on"])) {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ERROR);
        error_reporting(E_WARNING);
    }
}

if (isset($_POST["hot-reload"])) {

    if (isset($_POST["quesiton-limit"])) {
        $questionLimit = $_POST["quesiton-limit"];
    } else {
        $questionLimit = $configData["question-limit"];
    }
    if (isset($_POST["data-base-1"])) {
        $database1 = $_POST["data-base-1"];
    } else {
        $database1 = $configData["data-base-1"];
    }
    if (isset($_POST["data-base-2"])) {
        $database2 = $_POST["data-base-2"];
    } else {
        $database2 =  $configData["data-base-2"];
    }
    if (isset($_POST["ignore-archive-user"])) {
        $ignoreArchiveUser = true;
    } else {
        $ignoreArchiveUser =  $configData["advanced-options"]["ignore-archive-user"];
    }
    if (isset($_POST["questions-view-slug-prefix"])) {
        $questionViewSlugPrefix = $_POST["questions-view-slug-prefix"];
    } else {
        $questionViewSlugPrefix = $configData["questions-view-slug-prefix"];
    }
} else {
    $questionLimit = $configData["question-limit"];
    $database1 = $configData["data-base-1"];
    $database2 = $configData["data-base-2"];
    $ignoreArchiveUser = $configData["advanced-options"]["ignore-archive-user"];
    $questionViewSlugPrefix = $configData["questions-view-slug-prefix"];
}

if (!checkDataBases($accepableDataBases, $database1, $database2)) {
    die();
}


$html = defineHTML($database1, $database2, $questionViewSlugPrefix, $questionLimit, $ignoreArchiveUser);
$htmlHead = $html["head"];
$toolForm = $html["tool-form"];

if (ADVANCED && METRICS) {
    date_default_timezone_set(METRICSTIMEZONE);
    $startTime = microtime(true);
}

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
try {
    $pdo = new PDO($dsn, $mysql_user, $mysql_password, $options);
} catch (\PDOException $e) {
    echo $e->getMessage();
    die();
}



if (ADVANCED && METRICS) {
    $metrics = array();

    try {
        $db1CountResult = $pdo->query("SELECT count(1) FROM $database1.questions");
        $db1CountResult = $db1CountResult->fetch(PDO::FETCH_ASSOC);
        $db1Count = $db1CountResult[0];
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    try {
        $db2CountResult = $pdo->query("SELECT count(1) FROM $database2.questions");
        $db2CountResult =  $db2CountResult->fetch(PDO::FETCH_ASSOC);
        $db2Count = $db2CountResult[0];
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    $metrics["db1Count"] = $db1Count;
    $metrics["db2Count"] = $db2Count;
}

//Get missing questions ids 
$missingQuestionIDReturn = getMissingQuestionsIDs($pdo, $database1, $database2, $questionLimit);
$idsString = $missingQuestionIDReturn["ids"];
$personIdsString = $missingQuestionIDReturn["personIds"];


//Get missings questions full data 
$data = getQuestionData($pdo, $database1, $idsString);

//Get questions asset data 
$questionsAssetsReturn = getQuestionAssetsData($pdo, $database1, $database2, $data, $idsString);
$assetFoundMap = $questionsAssetsReturn['assetFoundMap'];
$assetQuestionMap = $questionsAssetsReturn['assetQuestionMap'];


//Get questions reply data 
$questionsRepliesReturn = getQuestionsReplyData($pdo, $database1, $database2, $data, $idsString);
$replyFoundMap = $questionsRepliesReturn['replyFoundMap'];
$replyQuestionMap = $questionsRepliesReturn['replyQuestionMap'];
$replyIds = $questionsRepliesReturn['replyIds'];
$replyData = $questionsRepliesReturn['replyData'];

//Get questions reply assets data 
$questionsRepliesAssetsReturn = getQuestionRelpyAssetsData($pdo, $database1, $database2, $replyIds, $replyData);
$replyAssetFoundMap = $questionsRepliesAssetsReturn['replyAssetsFoundMap'];
$replyAssetQuestionMap = $questionsRepliesAssetsReturn['replyAssetsQuestionMap'];

//print_r($replyAssetQuestionMap);die();
if (FINDPEOPLE) {
    //Get questions people data 
    $questionsPersonReturn = getQuestionPersonData($pdo, $database1, $database2, $data, $idsString);
    $peopleFoundMap = $questionsPersonReturn['peopleFoundMap'];
    $peopleQuestionMap = $questionsPersonReturn['peopleQuestionMap'];
}



if (ADVANCED && METRICS) {
    $endTime = microtime(true);
    $totalQueryTime = ($endTime - $startTime);
    $metrics["totalQuestions"]  =  $missingQuestionIDReturn["totalQuestions"];
    $metrics["assetsFound"]  =  $questionsAssetsReturn['assetsFound'];
    $metrics["totalAssets"]  =  $questionsAssetsReturn['totalAssets'];
    $metrics["repliesFound"] =  $questionsRepliesReturn['repliesFound'];
    $metrics["totalReplies"] =  $questionsRepliesReturn['totalReplies'];
    $metrics["repliesFound"] =  $questionsRepliesReturn['repliesFound'];
    $metrics["totalReplies"] =  $questionsRepliesReturn['totalReplies'];
    if (FINDPEOPLE) {
        $metrics["peopleFound"]  =  $questionsPersonReturn['peopleFound'];
        $metrics["totalPeople"]  =  $questionsPersonReturn['totalPeople'];
    }
    $metrics["totalQueryTime"] =  $totalQueryTime;
    $metricsHTML = getMetricsHTML($metrics);
    $advacnedStyle = "";
} else {
    $metricsHTML = "";
    $totalQueryTime = 0;
    $advacnedStyle = "style='display:none;'";
}

echo <<<HTML
<html>
{$htmlHead}
<body>
<h1>Restore Questions</h1>
{$toolForm}
{$metricsHTML}
<div class="more-info" {$advacnedStyle}>
        <svg class="more-info-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path style="background-color:white;" d="M12 3l12 18h-24z"/></svg>
        <span class="more-info-title">Conifg Data Raw</span>    
        </div>
        <div class="more-info-data">
            <pre>{$configDataRaw}</pre>
</div>
<div class="more-info" {$advacnedStyle}>
        <svg class="more-info-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path style="background-color:white;" d="M12 3l12 18h-24z"/></svg>
        <span class="more-info-title">Last Response From Server</span>    
        </div>
        <div class="more-info-data">
            <pre id="server-reponse"></pre>
</div>
HTML;


foreach ($data as $q) {
    $replyHTML = "";
    $assetRawHTML = "";
    $peopleHTML = "";
    $assetsCompareHTML = "";
    $peopleCompareHTML = "";
    $replyCompareHTML = "";
    $assetIds = "";
    $replyIds = "";
    $replyAssetIds = "";
    $peopleIds = "";
    $hasReplies = "<span class='false'>FALSE</span>";
    $hasAssets = "<span class='false'>FALSE</span>";
    $hasLinkedPeople = "";
    $replyAssetCompareHTML = "";


    if (isset($replyQuestionMap[$q['id']])) {
        $hasReplies = "<span class='true'>TRUE</span>";
        if (ADVANCED) {
            if (RAWDATA) {
                $replyHTML = getMoreInfo($replyQuestionMap[$q['id']], "Reply Data");
            } else {
                $replyHTML = "";
            }
            if (DATABASEANALYSIS) {
                $replyCompareHTML = getComparision($replyQuestionMap[$q['id']], $replyFoundMap, "REPLIES");
            } else {
                $replyCompareHTML  = "";
            }
        }

        foreach ($replyQuestionMap[$q['id']] as $r) {
            if (isset($replyAssetQuestionMap[$r['id']])) {
                $replyAssetIds .= getNeededRestoreIds($replyAssetQuestionMap[$r['id']], $replyAssetFoundMap);
                $replyAssetCompareHTML .= getComparision($replyAssetQuestionMap[$r['id']], $replyAssetFoundMap, "REPLIE {$r['id']} ASSETS");
            }
        }


        $replyIds = getNeededRestoreIds($replyQuestionMap[$q['id']], $replyFoundMap);
    }
    if (isset($assetQuestionMap[$q['id']])) {
        $hasAssets = "<span class='true'>TRUE</span>";
        if (ADVANCED) {
            if (RAWDATA) {
                $assetRawHTML = getMoreInfo($assetQuestionMap[$q['id']], "Asset Data");
            } else {
                $assetRawHTML = "";
            }
            if (DATABASEANALYSIS) {
                $assetsCompareHTML = getComparision($assetQuestionMap[$q['id']], $assetFoundMap, "ASSETS");
            } else {
                $assetsCompareHTML = "";
            }
        }
        $assetIds = getNeededRestoreIds($assetQuestionMap[$q['id']], $assetFoundMap);
    }
    if (FINDPEOPLE) {
        $hasLinkedPeople = "<b>HAS PEOPLE:</b> <span class='false'>FALSE</span>";
        if (isset($peopleQuestionMap[$q['id']])) {
            $hasLinkedPeople = "<b>HAS PEOPLE:</b> <span class='true'>TRUE</span>";
            if (ADVANCED) {
                if (RAWDATA) {
                    $peopleHTML = getMoreInfo($peopleQuestionMap[$q['id']], "Author Data");
                } else {
                    $peopleHTML = "";
                }
                if (DATABASEANALYSIS) {
                    $peopleCompareHTML = getComparision($peopleQuestionMap[$q['id']], $peopleFoundMap, "PEOPLE");
                } else {
                    $peopleCompareHTML = "";
                }
            }

            $peopleIds = getNeededRestoreIds($peopleQuestionMap[$q['id']], $peopleFoundMap);
        }
    }

    if (DATABASEANALYSIS) {
        $dataBaseAnalysisHTML = <<<HTML
        <hr class="more-info-bar" >
        <div class="more-info" {$advacnedStyle}>
        <svg class="more-info-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path style="background-color:white;" d="M12 3l12 18h-24z"/></svg>
        <span class="more-info-title">Data Base Comparison Analysis </span>    
        </div>
        <div class="more-info-data">
        $replyCompareHTML
        $assetsCompareHTML
        $replyAssetCompareHTML 
        $peopleCompareHTML
        </div>
HTML;
    } else {
        $dataBaseAnalysisHTML = "";
    }

    if (RAWDATA) {
        $rawData = getMoreInfo($q, "Raw Data");
        $rawDataHTML = <<<HTML
        <hr class="more-info-bar" {$advacnedStyle}>
        <div class="more-info" {$advacnedStyle}>
        <svg class="more-info-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path style="background-color:white;" d="M12 3l12 18h-24z"/></svg>
        <span class="more-info-title">Raw Data</span>    
        </div>
        <div class="more-info-data">
        $rawData
        $replyHTML
        $assetRawHTML
        $peopleHTML
        </div>
HTML;
    } else {
        $rawData = "";
        $rawDataHTML = "";
    }
    if (QUESTIONDATABARTOP) {
        $questionDataBarTop = <<<HTML
<div class="question-data-bar" {$advacnedStyle}>
<b>ID:</b> {$q["id"]}
<b>CREATED:</b> {$q["created_at"]}
<b>UPDATED:</b> {$q["updated_at"]}
<b>REPLIES:</b> {$q["question_replies_count"]}
</div>
HTML;
    } else {
        $questionDataBarTop = "";
    }
    if (QUESTIONDATABARBOTTOM) {
        $questionDataBarBottom = <<<HTML
<div class="question-data-bar" {$advacnedStyle}>
<b>HAS REPLIES:</b> {$hasReplies}
<b>HAS ASSETS:</b> {$hasAssets}
    {$hasLinkedPeople}
</div>
HTML;
    } else {
        $questionDataBarBottom = "";
    }
    echo <<<HTML
    <div id="{$q["id"]}" class="question">
        <div class="question-content">
    <hr>
        <div class="question-body">
        {$questionDataBarTop}
        {$questionDataBarBottom}
        <div class="question-subject">
        <div class="question-body-title"> SUBJECT </div>
        <div class="question-subject-title">
        {$q["subject"]} $assetIds 
</div>
</div>
<hr class="body-info-bar">
        <div class="question-body-title"> BODY </div>
        <div class="question-body-text">
        {$q["body"]}
        </div>
        $rawDataHTML 
        $dataBaseAnalysisHTML
        <br>
        <button data-replyassetids="{$replyAssetIds}" data-assetids="{$assetIds}"  data-replyids="{$replyIds}"  data-qid="{$q['id']}" class="restore-button">RESTORE</button>
        <button  data-replyassetids="{$replyAssetIds}" data-assetids="{$assetIds}"  data-replyids="{$replyIds}"  data-qid="{$q['id']}" class="ignore-button">IGNORE</button>
        <button data-slug="{$q['slug']}" class="view-live-button">VIEW LIVE</button>
        </div>
    <hr>
</div>
    </div>
HTML;
}

echo <<<HTML
</body>
</html>
HTML;

/**
 * Get Missiong Questions IDS
 *
 * @param [PDO] $pdo
 * @param [string] $database1
 * @param [string] $database2
 * @param [number] $questionLimit
 * @return [array] { 
 * 
 *ids [string] | String of all ids,
 *
 *personIds [string] | String of person ids related to the questios,
 *
 *totalQuestions [number] | Total number of missing questions found,  
 * \}
 */
function getMissingQuestionsIDs($pdo, $database1, $database2, $questionLimit = 100)
{



    try {
        $stmt = $pdo->query("SELECT $database1.questions.id,$database1.questions.person_id,$database1.questions.question_replies_count   FROM $database1.questions WHERE 
$database1.questions.mid = 0 AND $database1.questions.status IS NULL AND
$database1.questions.id  NOT IN
(SELECT $database2.questions.id   FROM $database2.questions WHERE 
$database2.questions.mid = 0 AND $database2.questions.status IS NULL)
ORDER BY $database1.questions.question_replies_count DESC LIMIT  $questionLimit ");
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }



    $questionMap = [];
    $ids = array();
    $personids = array();
    foreach ($stmt as $row) {
        $questionMap[$row['id']] = $row;
        $ids[] = $row["id"];
        $personids[] =  $row["person_id"];
    }
    $totalQuestions = count($ids);
    if ($totalQuestions == 0) {
        noQuestionsFound();
    }
    //Get the archived questions. 
    $idsString = implode(",", $ids);
    $personIdsString = implode(",", $personids);


    return array(
        "ids" => $idsString,
        "personIds" => $personIdsString,
        "totalQuestions" => $totalQuestions
    );
}
/**
 * Get Question Data
 * Gets the raw question data questions for the backup database. 
 * @param [PDO] $pdo
 * @param [string] $database1
 * @param [string[]] $questionIDS
 * @return data Raw question data. 
 */
function getQuestionData($pdo, $database1, $questionIDS)
{


    try {
        $stmt = $pdo->query("SELECT *  FROM $database1.questions WHERE id IN($questionIDS) ORDER BY question_replies_count DESC");
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }


    return $stmt->fetchAll();
}

/**
 * # Get Question Assets Data
 * ---
 * Get the asset data for the question supplied. 
 * @param [PDO] $pdo
 * @param [string] $database1
 * @param [string] $database2
 * @param [array] $questionData
 * @param [string] $questionIDs
 * @return [array] {
 * 
 * assetFoundMap [array] | Map of assets that were found 
 * 
 * assetQuestionMap [array] | Assets linked to thier questions
 * 
 * totalAssets [number] | Total number of assets  
 * 
 * assetsFound [number] | Total number of asssets found 
 * 
 * }
 */
function getQuestionAssetsData($pdo, $database1, $database2, $questionData, $questionIDs)
{



    try {
        $assets = $pdo->query("SELECT * FROM $database1.assets WHERE assetable_id IN($questionIDs) AND  assetable_type = 'Question' ");
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    $assetQuestionMap = [];
    $assetMap = [];
    $totalAssets = 0;
    foreach ($assets as $a) {
        $totalAssets++;
        foreach ($questionData as $q) {
            if ($q["id"] == $a["assetable_id"]) {
                isset($assetQuestionMap[$q["id"]]) ?  true :  $assetQuestionMap[$q["id"]] = [];
                $assetQuestionMap[$q["id"]][$a["id"]] = $a;
                $assetMap[$a["id"]] = true;
            }
        }
    }

    try {
        $assetsTwo = $pdo->query("SELECT * FROM $database2.assets WHERE assetable_id IN($questionIDs) AND assetable_type = 'Question'");
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    $assetFoundMap = [];
    $assetsFound = 0;
    foreach ($assetsTwo as $a) {
        if (isset($assetMap[$a["id"]])) {
            $assetFoundMap[$a["id"]] = true;
            $assetsFound++;
        } else {
            $assetFoundMap[$a["id"]] = false;
        }
    }
    return array(
        "assetFoundMap" => $assetFoundMap,
        "assetQuestionMap" => $assetQuestionMap,
        "totalAssets" => $totalAssets,
        "assetsFound" => $assetsFound
    );
}

/**
 * # Get Question Person Data
 * ---
 * Get data for the people related to the questions 
 * @param [PDO] $pdo
 * @param [string] $database1
 * @param [string] $database2
 * @param [array] $questionData
 * @param [string] $personIdsString
 * @param boolean $ignoreArchiveUser = false 
 * @return [array] {
 * 
 * peopleFoundMap [array] | Map of people that were found 
 * 
 * peopleQuestionMap [array] | People linked to thier questions
 * 
 * totalPeople [number] | Total number of people  
 * 
 * peopleFound [number] | Total number of people found 
 * 
 * }
 */
function getQuestionPersonData($pdo, $database1, $database2, $questionData, $personIdsString, $ignoreArchiveUser = false)
{
    $peopleSQL = <<<SQL
  SELECT id,username,fname,lname,title,questions_count,last_sign_in_at FROM $database1.people WHERE id IN($personIdsString)
SQL;
    if ($ignoreArchiveUser) {
        $peopleSQL .= " AND username != '2CarPros-Archives' ";
    }

    try {
        $people = $pdo->query($peopleSQL);
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    $peopleMap = [];
    $peopleQuestionMap = [];
    $totalPeople = 0;
    foreach ($people as $p) {
        $totalPeople++;
        foreach ($questionData as $q) {
            if ($q["person_id"] == $p["id"]) {
                isset($peopleQuestionMap[$q["id"]]) ?  true :  $peopleQuestionMap[$q["id"]] = [];
                $peopleQuestionMap[$q["id"]][] = $p;
                $peopleMap[$p["id"]] = true;
            }
        }
    }

    try {
        $peopleTwo = $pdo->query("SELECT * FROM $database2.people  WHERE id  IN($personIdsString)");
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    $peopleFoundMap = [];
    $peopleFound = 0;
    foreach ($peopleTwo as $p) {
        if (isset($peopleMap[$p["id"]])) {
            $peopleFoundMap[$p["id"]] = true;
            $peopleFound++;
        } else {
            $peopleFoundMap[$p["id"]] = false;
        }
    }

    return array(
        "peopleFoundMap" => $peopleFoundMap,
        "peopleQuestionMap" => $peopleQuestionMap,
        "totalPeople" => $totalPeople,
        "peopleFound" => $peopleFound
    );
}

/**
 * # Get Question Reply Data
 * ---
 * Get data for the replies related to the questions 
 * @param [PDO] $pdo
 * @param [string] $database1
 * @param [string] $database2
 * @param [array] $questionsData
 * @param [string] $questionIDS
 * @param boolean $ignoreArchiveUser = false 
 * @return [array] {
 * 
 * replyFoundMap [array] | Map of replies that were found 
 * 
 * replyQuestionMap [array] | Replies linked to thier questions
 * 
 * totalReplies [number] | Total number of replies  
 * 
 * repliesFound [number] | Total number of replies found 
 * 
 * }
 */
function getQuestionsReplyData($pdo, $database1, $database2, $questionsData, $questionIDS)
{


    try {
        $replies = $pdo->query("SELECT *  FROM $database1.question_replies WHERE question_id IN($questionIDS)");
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    $replyMap = [];
    $replyQuestionMap = [];
    $totalReplies = 0;
    $ids = [];
    foreach ($replies as $r) {
        $totalReplies++;
        foreach ($questionsData as $q) {
            if ($r["question_id"] == $q["id"]) {
                isset($replyQuestionMap[$q["id"]]) ?  true :  $replyQuestionMap[$q["id"]] = [];
                $replyQuestionMap[$q["id"]][] = $r;
                $replyMap[$r["id"]] = true;
                $ids[] = $r["id"];
            }
        }
    }

    try {
        $repliesTwo = $pdo->query("SELECT * FROM $database2.question_replies  WHERE question_id  IN($questionIDS)");
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    $replyFoundMap = [];
    $repliesFound = 0;
    foreach ($repliesTwo as $r) {
        if (isset($replyMap[$r["id"]])) {
            $replyFoundMap[$r["id"]] = true;
            $repliesFound++;
        } else {
            $replyFoundMap[$r["id"]] = false;
        }
    }


    $replyIds = implode(",", $ids);
    return array(
        "replyData" => $replies,
        "replyFoundMap" => $replyFoundMap,
        "replyQuestionMap" => $replyQuestionMap,
        "totalReplies" => $totalReplies,
        "repliesFound" => $repliesFound,
        "replyIds" =>  $replyIds
    );
}
/**
 * # Get Question Reply Asset Data
 * ---
 * Get data for the replies asset related to the questions 
 * @param [PDO] $pdo
 * @param [string] $database1
 * @param [string] $database2
 * @param [string] $questionIDS
 * @param [array] $questionData
 * @param boolean $ignoreArchiveUser = false 
 * @return [array] {
 * 
 * replyAssetsFoundMap [array] | Map of replies that were found 
 * 
 * replyAssetsQuestionMap [array] | Replies linked to thier questions
 * 
 * totalReplyAssets [number] | Total number of replies  
 * 
 * repliesAssetsFound [number] | Total number of replies found 
 * 
 * }
 */
function getQuestionRelpyAssetsData($pdo, $database1, $database2, $replyIDs, $replyData)
{
 
    
    try {
        $assets = $pdo->query("SELECT * FROM $database1.assets WHERE assetable_id IN($replyIDs) AND  assetable_type = 'QuestionReply' ");
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }


    $assetQuestionMap = [];
    $assetMap = [];
    $totalAssets = 0;
    foreach ($assets as $a) {
        $totalAssets++;
        foreach ($replyData as $q) {
            if ($q["id"] == $a["assetable_id"]) {
                isset($assetQuestionMap[$q["id"]]) ?  true :  $assetQuestionMap[$q["id"]] = [];
                $assetQuestionMap[$q["id"]][$a["id"]] = $a;
                $assetMap[$a["id"]] = true;
            }
        }
    }

    try {
        $assetsTwo = $pdo->query("SELECT * FROM $database2.assets WHERE assetable_id IN($replyIDs) AND assetable_type = 'QuestionReply'");
    } catch (\PDOException $e) {
        echo $e->getMessage();
    }

    $assetFoundMap = [];
    $assetsFound = 0;
    foreach ($assetsTwo as $a) {

        if (isset($assetMap[$a["id"]])) {
            $assetFoundMap[$a["id"]] = true;
            $assetsFound++;
        } else {
            $assetFoundMap[$a["id"]] = false;
        }
    }
    return array(
        "replyAssetsFoundMap" => $assetFoundMap,
        "replyAssetsQuestionMap" => $assetQuestionMap,
        "totalReplyAssets" => $totalAssets,
        "replyAssetsFound" => $assetsFound
    );
}


function getNeededRestoreIds($questoinMap, $foundMap)
{

    $returnData = [];
    foreach ($questoinMap as $q) {
        if (!isset($foundMap[$q['id']])) {
            $returnData[] = $q['id'];
        }
    }

    return implode(",", $returnData);
}



function checkDataBases($accepableDataBases, $db1, $db2)
{

    return (in_array($db1, $accepableDataBases) && in_array($db2, $accepableDataBases));
}

/**
 * getComparision
 *  Compares data for the replies, assets, and people to see if they are the same in each database. 
 * @param [type] $mapOne The data for database 1
 * @param [type] $mapTwo The found data for database 2
 * @return void
 */
function getComparision($mapOne, $mapTwo, $title)
{
    $html = "";
    foreach ($mapOne as $dataOne) {
        $html .= "<b>ID : {$dataOne['id']} </b>";
        if (isset($mapTwo[$dataOne['id']])) {
            $html .= "<span class='true'>FOUND</span>";
        } else {
            $html .= "<span class='false'>NOT FOUND</span>";
        }
        $html .= "<br>";
    }
    return getMoreInfoWarap($html, $title, false);
}

function getMoreInfoWarap($html, $title = "", $pre = true)
{
    if ($pre) {
        $html = "<pre>$html</pre>";
    }
    return <<<HTML
    <div class="more-info">
    <svg class="more-info-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path style="background-color:white;" d="M12 3l12 18h-24z"/></svg>
    <span class="more-info-title">$title</span>    
    </div>
    <div class="more-info-data">
    $html 
    </div>
HTML;
}

function getMoreInfo($data, $title)
{
    $dataHTML = htmlentities(json_encode($data, JSON_PRETTY_PRINT));
    return getMoreInfoWarap($dataHTML, $title);
}

function defineHTML($database1, $database2, $questionViewSlugPrefix, $questionLimit = 1000, $ignoreArchiveUser = false)
{

    $htmlHead = <<<HTML
<head>
<link rel="stylesheet" href="./main.css">
<script src="./main.js"></script>
</head>
HTML;
    $ignoreArchiveUserCheck = $ignoreArchiveUser ? "checked" : "";
    if (ADVANCED) {
        $toolForm = <<<HTML
<div class="more-info">
        <svg class="more-info-arrow" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path style="background-color:white;" d="M12 3l12 18h-24z"/></svg>
        <span class="more-info-title">Run Tool</span>    
        </div>
        <div class="more-info-data">
                <form method="post" atcion="./index.php" class="tool-form"  id="run-tool-form">
                <div class="tool-form-group">
                <label for="question-limit">Question Limit
                <input type="number" name="quesiton-limit" id="question-limit" value="{$questionLimit}"/>
                </label>
                </div>
                <div class="tool-form-group">
                <label for="data-base-1">Data Base 1
                <input type="text" name="data-base-1" id="data-base-1" value="{$database1}"/>
                </label>
                </div>
                <div class="tool-form-group">
                <label for="data-base-2">Data Base 2
                <input type="text" name="data-base-2" id="data-base-2" value="{$database2}"/>
                </label>
                <div class="tool-form-group">
                <label for="questions-view-slug-prefix">Question View Slug Prefix
                <input type="text" name="questions-view-slug-prefix" id="questions-view-slug-prefix" value="{$questionViewSlugPrefix}"/>
                </label>
                </div>
                <div class="tool-form-group">
                <input type="checkbox" name="ignore-archive-user" id="ignore-arhcive-user"  {$ignoreArchiveUserCheck}/>
                <label for="ignore-arhcive-user">Ignore Archive User </label>
                </div>
                <!--When running in advanced mode allow for changing of the config values through this form.-->
                <input type="hidden" name="hot-reload" value="true">
                <div class="tool-form-group">
                <input class="tool-form-submit advanced" type="submit" id="run-tool" value="Run Tool"/>
                </div> 
                </div>
</form>
</div>
HTML;
    } else {
        $toolForm = <<<HTML
<form method="post" atcion="./index.php" class="tool-form"  id="run-tool-form">
<input type="hidden" name="data-base-1" id="data-base-1" value="{$database1}">
<input type="hidden" name="data-base-2" id="data-base-2" value="{$database2}">
<input type="hidden" name="ignore-arhcive-user" id="ignore-arhcive-user" value="{$ignoreArchiveUserCheck}">
<input type="hidden" name="question-limit" id="question-limit" value="{$questionLimit}">
<input type="hidden" name="questions-view-slug-prefix" id="questions-view-slug-prefix" value="{$questionViewSlugPrefix}">
<div class="tool-form-group">
<input class="tool-form-submit" type="submit" id="run-tool" value="Run Tool"/>
</div> 
</div>
</form>
HTML;
    }


    return array(
        "head" => $htmlHead,
        "tool-form" => $toolForm
    );
}

//Ran when no questions are found 
function noQuestionsFound()
{
    $head = $GLOBALS["htmlHead"];
    $toolForm = $GLOBALS["toolForm"];
    echo <<<HTML
<html>
{$head}
<body>
<h1>Restore Questions</h1>
{$toolForm}
<h2>No Questions Found</h2>
</body>
</html>
HTML;
    die();
}

function getMetricsHTML($metrics)
{
    $peopleText = "";
    if (FINDPEOPLE) {
        $peopleText = <<<TEXT

Total Linked People : {$metrics["totalPeople"]} 
Total Found People : {$metrics["peopleFound"]}

TEXT;
    }

    $returnText = <<<TEXT
Found Missing Questions : {$metrics["totalQuestions"]}
Total Linked Assets : {$metrics["totalAssets"]} 
Total Found Assets : {$metrics["assetsFound"]}
TEXT;
    $returnText .= $peopleText;

    $returnText .= <<<TEXT
Total Linked Replies : {$metrics["totalReplies"]} 
Total Found Replies : {$metrics["repliesFound"]}
Data Base 1 : {$GLOBALS["database1"]}
Data Base 1 Total Questions :{$metrics["db1Count"]}
Data Base 2 :  {$GLOBALS["database2"]}
Data Base 2 Total Questions :{$metrics["db2Count"]}
Query Time : {$metrics["totalQueryTime"]} seconds
TEXT;
    $returnText = trim($returnText);
    return getMoreInfoWarap($returnText, "Tool Stats", true);
}
