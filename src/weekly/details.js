/*
  Requirement: Populate the weekly detail page and handle the discussion forum.
*/

// --- Global Data Store ---
let currentWeekId = null;
let currentComments = [];

// --- Element Selections ---
const weekTitle = document.getElementById("week-title");
const weekStartDate = document.getElementById("week-start-date");
const weekDescription = document.getElementById("week-description");
const weekLinksList = document.getElementById("week-links-list");
const commentList = document.getElementById("comment-list");
const commentForm = document.getElementById("comment-form");
const newCommentInput = document.getElementById("new-comment");

// --- Functions ---

function getWeekIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

function renderWeekDetails(week) {
  weekTitle.textContent = week.title;
  weekStartDate.textContent = "Starts on: " + week.start_date;
  weekDescription.textContent = week.description;

  weekLinksList.innerHTML = "";

  (week.links || []).forEach((url) => {
    const li = document.createElement("li");
    const a = document.createElement("a");

    a.href = url;
    a.textContent = url;

    li.appendChild(a);
    weekLinksList.appendChild(li);
  });
}

function createCommentArticle(comment) {
  const article = document.createElement("article");

  const paragraph = document.createElement("p");
  paragraph.textContent = comment.text;

  const footer = document.createElement("footer");
  footer.textContent = "Posted by: " + comment.author;

  article.appendChild(paragraph);
  article.appendChild(footer);

  return article;
}

function renderComments() {
  commentList.innerHTML = "";

  currentComments.forEach((comment) => {
    commentList.appendChild(createCommentArticle(comment));
  });
}

async function handleAddComment(event) {
  event.preventDefault();

  const commentText = newCommentInput.value.trim();

  if (commentText === "") {
    return;
  }

  const response = await fetch("./api/index.php?action=comment", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      week_id: Number(currentWeekId),
      author: "Student",
      text: commentText
    })
  });

  const result = await response.json();

  if (result.success === true) {
    currentComments.push(result.data);
    renderComments();
    newCommentInput.value = "";
  }
}

async function initializePage() {
  currentWeekId = getWeekIdFromURL();

  if (currentWeekId === null || currentWeekId === "") {
    weekTitle.textContent = "Week not found.";
    return;
  }

  const [weekResponse, commentsResponse] = await Promise.all([
    fetch("./api/index.php?id=" + currentWeekId),
    fetch("./api/index.php?action=comments&week_id=" + currentWeekId)
  ]);

  const weekResult = await weekResponse.json();
  const commentsResult = await commentsResponse.json();

  currentComments = commentsResult.success ? (commentsResult.data || []) : [];

  if (weekResult.success === true && weekResult.data) {
    renderWeekDetails(weekResult.data);
    renderComments();
    commentForm.addEventListener("submit", handleAddComment);
  } else {
    weekTitle.textContent = "Week not found.";
  }
}

// --- Initial Page Load ---
initializePage();