"use strict";

window.addEventListener("load", () => {
  //add expand toggle to more-info
  const list = document.getElementsByClassName("more-info");
  for (let i = 0; i < list.length; i++) {
    let open = false;
    const elm = list[i];
    const moreInfo = list[i].nextElementSibling;
    const svg = elm.firstElementChild;
    elm.addEventListener("click", () => {
      if (open) {
        open = false;
        moreInfo.className = "more-info-data";
        svg.className.baseVal = "more-info-arrow";
      } else {
        open = true;
        moreInfo.className = "more-info-data open";
        svg.className.baseVal = "more-info-arrow open";
      }
    });
  }
  //view-live-button
  //addEvent listeners to restore buttons
  const restoreButtons = document.getElementsByClassName("restore-button");
  for (let i = 0; i < restoreButtons.length; i++) {
    const button = restoreButtons[i];
    const id = button.dataset.qid;
    const assets = button.dataset.assetids.split(",");
    const replies = button.dataset.replyids.split(",");
    const replyAssets = button.dataset.replyassetids.split(",");
    button.addEventListener("click", () => {
      if (confirm("Restore the question?")) {
        if (!validateRunForm()) {
          return;
        }
        const data = getToolParams(id, assets, replies, replyAssets);
        data.restore = true;
        runRestore(data, true);
      }
    });
  }

  //addEvent listeners to ignore buttons
  const ignoreButtons = document.getElementsByClassName("ignore-button");
  for (let i = 0; i < ignoreButtons.length; i++) {
    const button = ignoreButtons[i];
    const id = button.dataset.qid;
    const assets = button.dataset.assetids.split(",");
    const replies = button.dataset.replyids.split(",");
    const replyAssets = button.dataset.replyassetids.split(",");
    button.addEventListener("click", () => {
      if (confirm("Ignore the question?")) {
        if (!validateRunForm()) {
          return;
        }
        const data = getToolParams(id, assets, replies, replyAssets);
        data.restore = false;
        runRestore(data, false);
      }
    });
  }

  const questionViewSlugPrefix = document.getElementById(
    "questions-view-slug-prefix"
  ).value;
  const viewLiveButtons = document.getElementsByClassName("view-live-button");
  for (let i = 0; i < viewLiveButtons.length; i++) {
    const button = viewLiveButtons[i];
    const slug = button.dataset.slug;
    button.addEventListener("click", () => {
      window.open(`${questionViewSlugPrefix}${slug}/`, "_blank");
    });
  }
});

async function runRestore(data, restore) {
  //set up display
  const question = document.getElementById(data["question-id"]);
  const restoreTitle = document.createElement("div");
  restoreTitle.className = "question-restoring";
  if (restore) {
    restoreTitle.innerText = "Restroing Question";
  } else {
    restoreTitle.innerText = "Ignoring Question";
  }
  const content = question.firstElementChild;
  content.className = "question-content restoring";
  await sleep(500);
  content.before(restoreTitle);
  const loadInte = loadText(restoreTitle);
  await sleep(1000);
  //restore the question

  const returnData = await restoreQuestion(data);
  //clean up display
  clearInterval(loadInte);
  if(typeof returnData !== "object"){
    alert("An error many have occured. Check last server response logged in console.");
  }

  if (restore) {
    restoreTitle.innerText = "Question Restored Successfully";
  } else {
    restoreTitle.innerText = "Question Ignored Successfully";
  }
  document.getElementById("server-reponse").innerText = JSON.stringify(
    returnData,
    null,
    5
  );
  await sleep(2000);
  question.remove();
}

async function restoreQuestion(data) {
  const returnData = await postData("./restore_question.php", data);
  console.log(returnData);
  return returnData;
}

//only run after validation
function getToolParams(questionId, assets, replies, replyAssets) {
  return {
    "question-id": questionId,
    "data-base-1": document.getElementById("data-base-1").value,
    "data-base-2": document.getElementById("data-base-2").value,
    assets: assets,
    replies: replies,
    "reply-assets": replyAssets,
  };
}

function validateRunForm() {
  const form = document.getElementById("run-tool-form");
  console.log(form);

  const dataBase1 = document.getElementById("data-base-1").value;
  if (dataBase1 == null || dataBase1.trim() == "") {
    alert("Data base 1 is not set. Please set before running.");
    return false;
  }
  const dataBase2 = document.getElementById("data-base-2").value;
  if (typeof dataBase1 === "null" || dataBase2.trim() == "") {
    alert("Data base 2 is not set. Please set before running.");
    return false;
  }
  const questionLimit = document.getElementById("question-limit").value;
  if (!parseInt(questionLimit) || questionLimit <= 0) {
    alert("Question limit is not set.Please set before running.");
    return false;
  }

  return true;
}

/**
 * Helper Functions
 *
 */

async function postData(url = "", data = {}) {
  const response = await fetch(url, {
    method: "POST",
    mode: "cors",
    cache: "no-cache",
    credentials: "same-origin",
    headers: {
      "Content-Type": "application/json",
    },
    redirect: "follow",
    referrerPolicy: "no-referrer",
    body: JSON.stringify(data),
  });

  const reponseData = await response.text();

  try{
    return JSON.parse(reponseData);
  } catch(error) {
    return reponseData;
  }

 
}

function sleep(time) {
  return new Promise((resolve) => setTimeout(resolve, time));
}

function loadText(element) {
  let text = element.innerText;
  let i = 0;
  let j = 0;
  let cap = 3;
  return setInterval(() => {
    let s = "";
    i++;
    j = i;
    while (j--) {
      s = s + ".";
    }
    element.innerText = text + s;
    if (i > cap) {
      i = 0;
    }
  }, 200);
}
