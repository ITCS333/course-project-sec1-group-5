let currentWeekId = null;
let comments = [];

const title = document.getElementById("week-title");
const start = document.getElementById("week-start-date");
const desc = document.getElementById("week-description");
const linksList = document.getElementById("week-links-list");
const commentList = document.getElementById("comment-list");
const form = document.getElementById("comment-form");
const input = document.getElementById("new-comment");

function getId(){
  return new URLSearchParams(window.location.search).get("id");
}

function renderWeek(w){
  title.textContent = w.title;
  start.textContent = "Starts on: " + w.start_date;
  desc.textContent = w.description;

  linksList.innerHTML="";
  (w.links||[]).forEach(l=>{
    const li=document.createElement("li");
    li.innerHTML=`<a href="${l}">${l}</a>`;
    linksList.appendChild(li);
  });
}

function renderComments(){
  commentList.innerHTML="";
  comments.forEach(c=>{
    const art=document.createElement("article");
    art.innerHTML=`<p>${c.text}</p><footer>Posted by: ${c.author}</footer>`;
    commentList.appendChild(art);
  });
}

async function addComment(e){
  e.preventDefault();
  const text=input.value.trim();
  if(!text)return;

  const res=await fetch("./api/index.php?action=comment",{
    method:"POST",
    headers:{"Content-Type":"application/json"},
    body:JSON.stringify({week_id:Number(currentWeekId),author:"Student",text})
  });

  const r=await res.json();
  comments.push(r.data);
  renderComments();
  input.value="";
}

async function init(){
  currentWeekId=getId();
  if(!currentWeekId)return;

  const [w,c]=await Promise.all([
    fetch(`./api/index.php?id=${currentWeekId}`),
    fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`)
  ]);

  const wr=await w.json();
  const cr=await c.json();

  renderWeek(wr.data);
  comments=cr.data||[];
  renderComments();

  form.addEventListener("submit",addComment);
}

init();