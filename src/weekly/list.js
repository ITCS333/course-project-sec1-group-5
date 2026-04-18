const weekListSection = document.getElementById("week-list-section");

function createWeekArticle(week){
  const article = document.createElement("article");

  const h2 = document.createElement("h2");
  h2.textContent = week.title;

  const p1 = document.createElement("p");
  p1.textContent = "Starts on: " + week.start_date;

  const p2 = document.createElement("p");
  p2.textContent = week.description;

  const a = document.createElement("a");
  a.href = "details.html?id=" + week.id;
  a.textContent = "View Details & Discussion";

  article.appendChild(h2);
  article.appendChild(p1);
  article.appendChild(p2);
  article.appendChild(a);

  return article;
}

async function loadWeeks(){
  const res = await fetch("./api/index.php");
  const result = await res.json();

  weekListSection.innerHTML = "";

  result.data.forEach(week=>{
    weekListSection.appendChild(createWeekArticle(week));
  });
}

loadWeeks();