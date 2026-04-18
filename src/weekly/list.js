const section = document.getElementById("week-list-section");

function createWeekArticle(w){
  const a = document.createElement("article");
  a.innerHTML = `
    <h2>${w.title}</h2>
    <p>Starts on: ${w.start_date}</p>
    <p>${w.description}</p>
    <a href="details.html?id=${w.id}">View Details & Discussion</a>
  `;
  return a;
}

async function loadWeeks(){
  const res = await fetch("./api/index.php");
  const data = await res.json();

  section.innerHTML = "";
  data.data.forEach(w => section.appendChild(createWeekArticle(w)));
}

loadWeeks();